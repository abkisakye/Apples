<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Models\PurchaseReturnItem;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\AccessService;
use App\Support\StockAvailabilityService;
use App\Support\StoreAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnController extends Controller
{
    public function create(Purchase $purchase): View
    {
        $purchase->load(['supplier:id,name,phone,country', 'store:id,name', 'items.product:id,name', 'items.productUnit:id,unit_name']);
        $this->guardReturnEligibility($purchase);

        $returnedByItem = PurchaseReturnItem::query()
            ->selectRaw('purchase_item_id, COALESCE(SUM(quantity), 0) as returned_qty')
            ->whereIn('purchase_item_id', $purchase->items->pluck('id'))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', 'posted'))
            ->groupBy('purchase_item_id')
            ->pluck('returned_qty', 'purchase_item_id');

        $returnRows = $purchase->items->map(function ($item) use ($returnedByItem) {
            $alreadyReturned = (int) round((float) ($returnedByItem[$item->id] ?? 0));
            $available = max((int) round((float) $item->quantity) - $alreadyReturned, 0);

            return [
                'item' => $item,
                'already_returned' => $alreadyReturned,
                'available_qty' => $available,
            ];
        })->filter(fn (array $row) => $row['available_qty'] > 0)->values();

        return view('purchase_returns.create', [
            'purchase' => $purchase,
            'returnRows' => $returnRows,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(
        Request $request,
        Purchase $purchase,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService,
        StockAvailabilityService $stockAvailabilityService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'return_date' => ['required', 'date'],
            'return_type' => ['required', 'in:refund,supplier_credit,exchange'],
            'payment_mode_id' => ['nullable', 'exists:payment_modes,id'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.purchase_item_id' => ['nullable', 'exists:purchase_items,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $purchase->load(['items', 'supplier', 'store']);
        $this->guardReturnEligibility($purchase);
        $storeAssignmentService->resolveStoreId((int) $purchase->store_id, $request->user(), app(AccessService::class), 'purchase');

        $selectedRows = collect($validated['items'])
            ->filter(fn (array $row) => ! empty($row['purchase_item_id']) && (int) ($row['quantity'] ?? 0) > 0)
            ->values();

        if ($selectedRows->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Enter at least one quantity to return.',
            ]);
        }

        $returnedByItem = PurchaseReturnItem::query()
            ->selectRaw('purchase_item_id, COALESCE(SUM(quantity), 0) as returned_qty')
            ->whereIn('purchase_item_id', $selectedRows->pluck('purchase_item_id'))
            ->whereHas('purchaseReturn', fn ($query) => $query->where('status', 'posted'))
            ->groupBy('purchase_item_id')
            ->pluck('returned_qty', 'purchase_item_id');

        $return = DB::transaction(function () use ($validated, $purchase, $selectedRows, $returnedByItem, $documentNumberService, $stockAvailabilityService) {
            $purchaseItems = $purchase->items->keyBy('id');

            $prepared = $selectedRows->map(function (array $row) use ($purchaseItems, $returnedByItem) {
                $purchaseItem = $purchaseItems->get((int) $row['purchase_item_id']);
                if (! $purchaseItem) {
                    throw ValidationException::withMessages([
                        'items' => 'One of the selected purchase lines is invalid.',
                    ]);
                }

                $alreadyReturned = (int) round((float) ($returnedByItem[$purchaseItem->id] ?? 0));
                $availableQty = max((int) round((float) $purchaseItem->quantity) - $alreadyReturned, 0);
                $quantity = max((int) $row['quantity'], 0);

                if ($quantity > $availableQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Returned quantity cannot be more than the remaining purchased quantity.',
                    ]);
                }

                return [
                    'purchase_item' => $purchaseItem,
                    'quantity' => $quantity,
                    'unit_cost' => (float) $purchaseItem->unit_cost,
                    'line_total' => round($quantity * (float) $purchaseItem->unit_cost, 2),
                ];
            });

            $returnedTotal = round((float) $prepared->sum('line_total'), 2);
            $overRecovered = max($returnedTotal - (float) $purchase->balance_due, 0);
            $refundAmount = $validated['return_type'] === 'refund' ? round($overRecovered, 2) : 0;
            $supplierCreditAmount = in_array($validated['return_type'], ['supplier_credit', 'exchange'], true) ? round($overRecovered, 2) : 0;

            if ($refundAmount > 0 && empty($validated['payment_mode_id'])) {
                throw ValidationException::withMessages([
                    'payment_mode_id' => 'Choose how the supplier refund was received before posting this return.',
                ]);
            }

            $return = PurchaseReturn::query()->create([
                'return_no' => $documentNumberService->make('purchase_return', $validated['return_date']),
                'return_date' => $validated['return_date'],
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'store_id' => $purchase->store_id,
                'payment_mode_id' => $validated['payment_mode_id'] ?? null,
                'return_type' => $validated['return_type'],
                'returned_total' => $returnedTotal,
                'refund_amount' => $refundAmount,
                'supplier_credit_amount' => $supplierCreditAmount,
                'status' => 'posted',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($prepared as $row) {
                $purchaseItem = $row['purchase_item'];
                $stockAvailabilityService->ensureAvailable(
                    (int) $purchase->store_id,
                    $purchaseItem->productUnit,
                    (int) $row['quantity'],
                    'items'
                );

                $returnItem = $return->items()->create([
                    'purchase_item_id' => $purchaseItem->id,
                    'product_id' => $purchaseItem->product_id,
                    'product_unit_id' => $purchaseItem->product_unit_id,
                    'quantity' => $row['quantity'],
                    'unit_cost' => $row['unit_cost'],
                    'line_total' => $row['line_total'],
                ]);

                InventoryTransaction::create([
                    'transaction_date' => $return->return_date,
                    'store_id' => $return->store_id,
                    'product_id' => $purchaseItem->product_id,
                    'product_unit_id' => $purchaseItem->product_unit_id,
                    'reference_type' => 'purchase_return',
                    'reference_id' => $returnItem->id,
                    'reference_no' => $return->return_no,
                    'movement_type' => 'purchase_return',
                    'quantity_in' => 0,
                    'quantity_out' => $row['quantity'],
                    'unit_cost' => $purchaseItem->unit_cost,
                    'unit_price' => null,
                ]);
            }

            $purchase->update([
                'balance_due' => round(max((float) $purchase->balance_due - $returnedTotal, 0), 2),
                'remarks' => trim(($purchase->remarks ? $purchase->remarks.' | ' : '').'Return '.$return->return_no.' posted'),
            ]);

            return $return;
        });

        $auditLogService->record('purchase_return.posted', $return, "Purchase return {$return->return_no} posted.", [
            'purchase_id' => $purchase->id,
            'return_type' => $return->return_type,
            'returned_total' => $return->returned_total,
        ]);

        return redirect()
            ->route('purchase-returns.show', $return)
            ->with('status', "Purchase return {$return->return_no} posted successfully.")
            ->with('auto_print_document', true);
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $purchaseReturn->load([
            'purchase:id,purchase_no,purchase_date',
            'supplier:id,name,phone,country,address',
            'store:id,name',
            'paymentMode:id,name',
            'items.product:id,name',
            'items.productUnit:id,unit_name',
        ]);

        return view('purchase_returns.show', compact('purchaseReturn'));
    }

    public function print(PurchaseReturn $purchaseReturn): View
    {
        $purchaseReturn->load([
            'purchase:id,purchase_no,purchase_date',
            'supplier:id,name,phone,country,address',
            'store:id,name',
            'paymentMode:id,name',
            'items.product:id,name',
            'items.productUnit:id,unit_name',
        ]);

        return view('purchase_returns.print', compact('purchaseReturn'));
    }

    private function guardReturnEligibility(Purchase $purchase): void
    {
        if ($purchase->status !== 'posted') {
            throw ValidationException::withMessages([
                'purchase' => 'Only posted purchases can accept return documents.',
            ]);
        }
    }
}
