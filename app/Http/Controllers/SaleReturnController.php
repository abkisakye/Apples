<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\AccessService;
use App\Support\ApprovalPinService;
use App\Support\ProductUnitConversionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnController extends Controller
{
    public function create(Sale $sale): View
    {
        $sale->load(['customer:id,name,phone,location', 'store:id,name', 'items.product:id,name', 'items.productUnit:id,unit_name']);

        $this->guardReturnEligibility($sale);

        $returnedByItem = SaleReturnItem::query()
            ->selectRaw('sale_item_id, COALESCE(SUM(quantity), 0) as returned_qty')
            ->whereIn('sale_item_id', $sale->items->pluck('id'))
            ->whereHas('saleReturn', fn ($query) => $query->where('status', 'posted'))
            ->groupBy('sale_item_id')
            ->pluck('returned_qty', 'sale_item_id');

        $returnRows = $sale->items->map(function ($item) use ($returnedByItem) {
            $alreadyReturned = (int) round((float) ($returnedByItem[$item->id] ?? 0));
            $available = max((int) round((float) $item->quantity) - $alreadyReturned, 0);

            return [
                'item' => $item,
                'already_returned' => $alreadyReturned,
                'available_qty' => $available,
            ];
        })->filter(fn (array $row) => $row['available_qty'] > 0)->values();

        return view('sale_returns.create', [
            'sale' => $sale,
            'returnRows' => $returnRows,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'requiresApprovalPin' => ! app(AccessService::class)->can('sales.override'),
        ]);
    }

    public function store(
        Request $request,
        Sale $sale,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        ProductUnitConversionService $conversionService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'return_date' => ['required', 'date'],
            'return_type' => ['required', 'in:refund,credit_note,exchange'],
            'payment_mode_id' => ['nullable', 'exists:payment_modes,id'],
            'approval_pin' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.sale_item_id' => ['nullable', 'exists:sale_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $sale->load(['items', 'customer', 'store']);
        $this->guardReturnEligibility($sale);

        $selectedRows = collect($validated['items'])
            ->filter(fn (array $row) => ! empty($row['sale_item_id']) && (int) ($row['quantity'] ?? 0) > 0)
            ->values();

        if ($selectedRows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one quantity to return.',
            ]);
        }

        $returnedByItem = SaleReturnItem::query()
            ->selectRaw('sale_item_id, COALESCE(SUM(quantity), 0) as returned_qty')
            ->whereIn('sale_item_id', $selectedRows->pluck('sale_item_id'))
            ->whereHas('saleReturn', fn ($query) => $query->where('status', 'posted'))
            ->groupBy('sale_item_id')
            ->pluck('returned_qty', 'sale_item_id');

        $prepared = $this->prepareReturnItems($sale, $selectedRows, $returnedByItem, $conversionService);
        $settlement = $this->calculateSettlement((float) $sale->balance_due, $prepared, $validated['return_type']);

        if ($validated['return_type'] === 'refund' && $settlement['refund_amount'] > 0) {
            $this->enforceSensitiveActionApproval(
                app(AccessService::class),
                $validated['approval_pin'] ?? null,
                'An admin approval PIN is required to post a cash refund from this account.'
            );
        }

        if ($validated['return_type'] === 'refund' && $settlement['refund_amount'] > 0 && empty($validated['payment_mode_id'])) {
            throw ValidationException::withMessages([
                'payment_mode_id' => 'Choose how the refund was paid out before posting this return.',
            ]);
        }

        $paymentModeId = $validated['return_type'] === 'refund' && $settlement['refund_amount'] > 0
            ? $validated['payment_mode_id']
            : null;

        $return = DB::transaction(function () use ($validated, $sale, $prepared, $settlement, $paymentModeId, $documentNumberService) {
            $return = SaleReturn::query()->create([
                'return_no' => $documentNumberService->make('sale_return', $validated['return_date']),
                'return_date' => $validated['return_date'],
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'store_id' => $sale->store_id,
                'payment_mode_id' => $paymentModeId,
                'return_type' => $validated['return_type'],
                'returned_total' => $settlement['returned_total'],
                'refund_amount' => $settlement['refund_amount'],
                'store_credit_amount' => $settlement['store_credit_amount'],
                'status' => 'posted',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($prepared as $row) {
                $saleItem = $row['sale_item'];

                $returnItem = $return->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'product_unit_id' => $saleItem->product_unit_id,
                    'quantity' => $row['quantity'],
                    'unit_price' => $row['unit_price'],
                    'line_total' => $row['line_total'],
                    'base_quantity' => $row['base_quantity'],
                    'conversion_factor_snapshot' => $row['conversion_factor_snapshot'],
                ]);

                InventoryTransaction::create([
                    'transaction_date' => $return->return_date,
                    'store_id' => $return->store_id,
                    'product_id' => $saleItem->product_id,
                    'product_unit_id' => $saleItem->product_unit_id,
                    'reference_type' => 'sale_return',
                    'reference_id' => $returnItem->id,
                    'reference_no' => $return->return_no,
                    'movement_type' => 'sale_return',
                    'quantity_in' => $row['quantity'],
                    'quantity_out' => 0,
                    'base_quantity_in' => $row['base_quantity'],
                    'base_quantity_out' => 0,
                    'conversion_factor_snapshot' => $row['conversion_factor_snapshot'],
                    'unit_cost' => $saleItem->cost_price_snapshot,
                    'unit_price' => $saleItem->unit_price,
                ]);
            }

            $sale->update([
                'balance_due' => $settlement['remaining_sale_balance'],
                'remarks' => trim(($sale->remarks ? $sale->remarks.' | ' : '').'Return '.$return->return_no.' posted'),
            ]);

            return $return;
        });

        $auditLogService->record('sale_return.posted', $return, "Sale return {$return->return_no} posted.", [
            'sale_id' => $sale->id,
            'return_type' => $return->return_type,
            'returned_total' => $return->returned_total,
            'balance_reduction' => $settlement['balance_reduction'],
            'refund_amount' => $return->refund_amount,
            'store_credit_amount' => $return->store_credit_amount,
            'approval_required' => $validated['return_type'] === 'refund' && $settlement['refund_amount'] > 0,
        ]);

        $redirect = route('sale-returns.show', $return);
        if ($return->return_type === 'exchange') {
            return redirect($redirect)
                ->with('status', "Sale return {$return->return_no} posted successfully. Review the settlement summary, then create the replacement sale from this return document.");
        }

        return redirect($redirect)
            ->with('status', "Sale return {$return->return_no} posted successfully.");
    }

    public function show(SaleReturn $saleReturn): View
    {
        $saleReturn->load([
            'sale:id,sale_no,sale_date,balance_due',
            'customer:id,name,phone,location',
            'store:id,name',
            'paymentMode:id,name',
            'items.product:id,name',
            'items.productUnit:id,unit_name',
            'replacementSale:id,sale_no',
        ]);

        return view('sale_returns.show', [
            'saleReturn' => $saleReturn,
            'settlement' => $this->summarizePostedReturn($saleReturn),
        ]);
    }

    public function print(SaleReturn $saleReturn): View
    {
        $saleReturn->load([
            'sale:id,sale_no,sale_date,balance_due',
            'customer:id,name,phone,location',
            'store:id,name',
            'paymentMode:id,name',
            'items.product:id,name',
            'items.productUnit:id,unit_name',
            'replacementSale:id,sale_no',
        ]);

        return view('sale_returns.print', [
            'saleReturn' => $saleReturn,
            'settlement' => $this->summarizePostedReturn($saleReturn),
        ]);
    }

    private function guardReturnEligibility(Sale $sale): void
    {
        if ($sale->status !== 'posted') {
            throw ValidationException::withMessages([
                'sale' => 'Only posted sales can accept return documents.',
            ]);
        }
    }

    private function prepareReturnItems(Sale $sale, Collection $selectedRows, Collection $returnedByItem, ProductUnitConversionService $conversionService): Collection
    {
        $saleItems = $sale->items->keyBy('id');

        return $selectedRows->map(function (array $row) use ($saleItems, $returnedByItem, $conversionService) {
            $saleItem = $saleItems->get((int) $row['sale_item_id']);
            if (! $saleItem) {
                throw ValidationException::withMessages([
                    'items' => 'One of the selected sale lines is invalid.',
                ]);
            }

            $alreadyReturned = (int) round((float) ($returnedByItem[$saleItem->id] ?? 0));
            $availableQty = max((int) round((float) $saleItem->quantity) - $alreadyReturned, 0);
            $quantity = max((int) $row['quantity'], 0);
            $conversionFactor = (float) ($saleItem->conversion_factor_snapshot ?: $conversionService->conversionFactorSnapshot($saleItem->productUnit));
            $baseQuantity = round($quantity * ($conversionFactor > 0 ? $conversionFactor : 1), 3);

            if ($quantity > $availableQty) {
                throw ValidationException::withMessages([
                    'items' => 'Returned quantity cannot be more than the remaining sold quantity.',
                ]);
            }

            return [
                'sale_item' => $saleItem,
                'quantity' => $quantity,
                'unit_price' => (float) $saleItem->unit_price,
                'line_total' => round($quantity * (float) $saleItem->unit_price, 2),
                'base_quantity' => $baseQuantity,
                'conversion_factor_snapshot' => round($conversionFactor > 0 ? $conversionFactor : 1, 6),
            ];
        });
    }

    /**
     * @param  Collection<int, array{line_total: float}>  $prepared
     * @return array{returned_total: float, balance_reduction: float, remaining_sale_balance: float, excess_value: float, refund_amount: float, store_credit_amount: float}
     */
    private function calculateSettlement(float $currentBalanceDue, Collection $prepared, string $returnType): array
    {
        $returnedTotal = round((float) $prepared->sum('line_total'), 2);
        $balanceReduction = round(min($currentBalanceDue, $returnedTotal), 2);
        $remainingSaleBalance = round(max($currentBalanceDue - $returnedTotal, 0), 2);
        $excessValue = round(max($returnedTotal - $currentBalanceDue, 0), 2);
        $refundAmount = $returnType === 'refund' ? $excessValue : 0;
        $storeCreditAmount = in_array($returnType, ['credit_note', 'exchange'], true) ? $excessValue : 0;

        return [
            'returned_total' => $returnedTotal,
            'balance_reduction' => $balanceReduction,
            'remaining_sale_balance' => $remainingSaleBalance,
            'excess_value' => $excessValue,
            'refund_amount' => round($refundAmount, 2),
            'store_credit_amount' => round($storeCreditAmount, 2),
        ];
    }

    /**
     * @return array{balance_reduction: float, refund_amount: float, store_credit_amount: float, next_step: string}
     */
    private function summarizePostedReturn(SaleReturn $saleReturn): array
    {
        $refundAmount = round((float) $saleReturn->refund_amount, 2);
        $storeCreditAmount = round((float) $saleReturn->store_credit_amount, 2);
        $balanceReduction = round(max((float) $saleReturn->returned_total - $refundAmount - $storeCreditAmount, 0), 2);

        $nextStep = match ($saleReturn->return_type) {
            'refund' => $refundAmount > 0
                ? 'Refund has been recorded. Keep the payout receipt together with this return note.'
                : 'No cash refund was needed. The return only reduced the outstanding sale balance.',
            'credit_note' => $storeCreditAmount > 0
                ? 'Store credit is available for the customer to use on the next sale.'
                : 'The return only reduced the customer balance on the original sale.',
            'exchange' => $saleReturn->replacementSale
                ? 'Replacement sale has already been linked to this exchange.'
                : 'Next step: start the replacement sale and link it back to this exchange note.',
            default => 'Return posted successfully.',
        };

        return [
            'balance_reduction' => $balanceReduction,
            'refund_amount' => $refundAmount,
            'store_credit_amount' => $storeCreditAmount,
            'next_step' => $nextStep,
        ];
    }

    private function enforceSensitiveActionApproval(AccessService $access, ?string $approvalPin, string $message): void
    {
        if ($access->can('sales.override')) {
            return;
        }

        if (app(ApprovalPinService::class)->verify($approvalPin)) {
            return;
        }

        throw ValidationException::withMessages([
            'approval_pin' => $message,
        ]);
    }
}
