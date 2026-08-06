<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\PurchaseFundingSource;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\ProductUnitConversionService;
use App\Support\ProductUnitCostSyncService;
use App\Support\MoneyInput;
use App\Support\StoreAssignmentService;
use App\Support\AccessService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        $type = trim((string) $request->string('type'));
        $balance = trim((string) $request->string('balance'));
        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();

        $purchases = Purchase::query()
            ->with(['supplier:id,name,phone,email', 'store:id,name', 'paymentMode:id,name', 'fundingSource:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('purchase_no', 'like', "%{$search}%")
                        ->orWhere('supplier_invoice_no', 'like', "%{$search}%")
                        ->orWhere('purchase_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('purchase_date', 'like', "%{$search}%")
                        ->orWhereHas('supplier', fn ($supplier) => $supplier
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%"))
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('paymentMode', fn ($paymentMode) => $paymentMode->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('fundingSource', fn ($source) => $source->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(in_array($type, ['cash', 'credit'], true), fn ($query) => $query->where('purchase_type', $type))
            ->when($balance === 'outstanding', fn ($query) => $query->posted()->where('balance_due', '>', 0))
            ->when($dateFrom && $dateTo, fn ($query) => $query->whereDate('purchase_date', '>=', $dateFrom)->whereDate('purchase_date', '<=', $dateTo))
            ->latest('purchase_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        if ($request->ajax()) {
            return view('purchases.partials.index_results', compact('purchases', 'search', 'type', 'balance', 'dateFrom', 'dateTo'));
        }

        return view('purchases.index', compact('purchases', 'search', 'type', 'balance', 'dateFrom', 'dateTo'));
    }

    public function create(Request $request): View
    {
        $currentStore = auth()->user()?->defaultStore;
        $correctPurchaseId = $request->integer('correct_purchase_id');
        $selectedUnit = $this->resolveSelectedUnit(
            $request->integer('product_unit_id'),
            $request->integer('product_id')
        );
        $sourcePurchase = null;
        $prefill = [
            'supplier_id' => null,
            'store_id' => $currentStore?->id,
            'purchase_date' => now()->toDateString(),
            'payment_mode_id' => null,
            'purchase_funding_source_id' => null,
            'amount_paid' => 0,
            'credit_period_days' => 30,
            'supplier_invoice_no' => null,
            'remarks' => null,
            'items' => $selectedUnit ? [[
                'product_unit_id' => $selectedUnit->id,
                'quantity' => 1,
                'unit_cost' => (float) $selectedUnit->cost_price,
                'selling_price' => (float) $selectedUnit->selling_price,
            ]] : [],
        ];

        if ($correctPurchaseId > 0) {
            $sourcePurchase = Purchase::query()
                ->with(['items.productUnit.product:id,name,code', 'payments', 'returns'])
                ->findOrFail($correctPurchaseId);

            $this->ensurePurchaseCorrectionAllowed();
            $this->guardCorrectionEligibility($sourcePurchase);

            $prefill = [
                'supplier_id' => $sourcePurchase->supplier_id,
                'store_id' => $sourcePurchase->store_id,
                'purchase_date' => $sourcePurchase->purchase_date?->toDateString() ?? now()->toDateString(),
                'payment_mode_id' => $sourcePurchase->payment_mode_id,
                'purchase_funding_source_id' => $sourcePurchase->purchase_funding_source_id,
                'amount_paid' => (float) $sourcePurchase->amount_paid,
                'credit_period_days' => (int) ($sourcePurchase->credit_period_days ?? 30),
                'supplier_invoice_no' => $sourcePurchase->supplier_invoice_no,
                'remarks' => trim('Correction for '.$sourcePurchase->purchase_no.($sourcePurchase->remarks ? ' | '.$sourcePurchase->remarks : '')),
                'items' => $sourcePurchase->items->map(function ($item) {
                    return [
                        'product_unit_id' => $item->product_unit_id,
                        'quantity' => (int) round((float) $item->quantity),
                        'unit_cost' => (float) $item->unit_cost,
                        'selling_price' => (float) ($item->productUnit?->selling_price ?? 0),
                        'line_total' => (float) $item->line_total,
                    ];
                })->all(),
            ];
        }

        return view('purchases.create', [
            'currentStore' => $currentStore ?? Store::query()->orderBy('name')->first(['id', 'name']),
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->withSum(['purchases as outstanding_credit' => fn ($query) => $query->posted()], 'balance_due')
                ->orderByRaw("CASE WHEN LOWER(TRIM(name)) = 'others' THEN 0 ELSE 1 END")
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'country']),
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'fundingSources' => PurchaseFundingSource::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name']),
            'productUnits' => ProductUnit::query()
                ->with('product:id,name,code')
                ->where('is_active', true)
                ->whereHas('product', fn ($query) => $query->where('is_active', true))
                ->orderBy('product_id')
                ->orderBy('unit_name')
                ->get(['id', 'product_id', 'unit_name', 'selling_price', 'cost_price', 'barcode', 'part_number']),
            'sourcePurchase' => $sourcePurchase,
            'prefillPurchase' => $prefill,
            'returnTo' => $this->safeReturnTo($request->input('return_to')),
        ]);
    }

    public function correct(Purchase $purchase): RedirectResponse
    {
        $this->ensurePurchaseCorrectionAllowed();
        $this->guardCorrectionEligibility($purchase);

        return redirect()->route('purchases.create', ['correct_purchase_id' => $purchase->id]);
    }

    public function quickSupplierStore(Request $request, AuditLogService $auditLogService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')],
            'phone' => ['nullable', 'string', 'max:255'],
        ]);

        $supplier = Supplier::query()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        $auditLogService->record('supplier.quick_created', $supplier, "Supplier {$supplier->name} quick-created from purchase entry.", [
            'supplier_id' => $supplier->id,
        ]);

        return response()->json([
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'phone' => $supplier->phone,
                'country' => $supplier->country,
                'credit' => 0,
                'search' => strtolower(trim(implode(' ', array_filter([$supplier->name, $supplier->phone, $supplier->country])))),
            ],
        ], 201);
    }

    public function show(Purchase $purchase): View
    {
        $purchase->load([
            'supplier:id,name,phone,country',
            'store:id,name',
            'paymentMode:id,name',
            'fundingSource:id,name',
            'items.product:id,name',
            'items.productUnit:id,unit_name',
            'payments.paymentMode:id,name',
            'returns.paymentMode:id,name',
            'correctedFrom:id,purchase_no',
            'replacedBy:id,purchase_no',
        ]);

        return view('purchases.show', compact('purchase'));
    }

    public function print(Purchase $purchase): View
    {
        $purchase->load([
            'supplier:id,name,phone,country',
            'store:id,name',
            'paymentMode:id,name',
            'fundingSource:id,name',
            'items.product:id,name',
            'items.productUnit:id,unit_name',
            'payments.paymentMode:id,name',
        ]);

        return view('purchases.print', compact('purchase'));
    }

    public function store(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService,
        ProductUnitConversionService $conversionService,
        ProductUnitCostSyncService $costSyncService
    ): RedirectResponse
    {
        $input = $request->all();
        $input['amount_paid'] = MoneyInput::normalize($input['amount_paid'] ?? null);

        foreach (($input['items'] ?? []) as $index => $item) {
            $input['items'][$index]['unit_cost'] = MoneyInput::normalize($item['unit_cost'] ?? null);
            $input['items'][$index]['selling_price'] = MoneyInput::normalize($item['selling_price'] ?? null);
            $input['items'][$index]['line_total'] = MoneyInput::normalize($item['line_total'] ?? null);
        }

        $request->merge($input);

        $validated = $request->validate([
            'purchase_date' => ['required', 'date'],
            'store_id' => ['required', 'exists:stores,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_type' => ['nullable', 'in:cash,credit'],
            'payment_mode_id' => ['nullable', 'exists:payment_modes,id'],
            'purchase_funding_source_id' => ['nullable', 'exists:purchase_funding_sources,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:255'],
            'credit_period_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
            'corrected_from_purchase_id' => ['nullable', 'exists:purchases,id'],
            'items' => ['required', 'array'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_cost' => ['nullable', 'numeric', 'min:0'],
            'items.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($validated['items'])
            ->filter(fn (array $item) => ! empty($item['product_unit_id']) && ! empty($item['quantity']))
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one purchase item before saving the purchase.',
            ]);
        }

        $productUnits = ProductUnit::query()
            ->with('product:id,name')
            ->whereIn('id', $items->pluck('product_unit_id'))
            ->get()
            ->keyBy('id');
        $correctionSourcePurchase = null;

        if (! empty($validated['corrected_from_purchase_id'])) {
            $this->ensurePurchaseCorrectionAllowed();
            $correctionSourcePurchase = Purchase::query()
                ->with(['items', 'payments', 'returns'])
                ->findOrFail($validated['corrected_from_purchase_id']);
            $this->guardCorrectionEligibility($correctionSourcePurchase);
        }

        $storeId = $storeAssignmentService->resolveStoreId((int) $validated['store_id'], $request->user(), app(\App\Support\AccessService::class));

        $purchase = DB::transaction(function () use ($validated, $items, $productUnits, $documentNumberService, $storeId, $conversionService, $costSyncService, $correctionSourcePurchase) {
            $preparedItems = $items->map(function (array $item) use ($productUnits, $conversionService) {
                /** @var ProductUnit|null $unit */
                $unit = $productUnits->get((int) $item['product_unit_id']);
                $quantity = max((int) $item['quantity'], 1);
                $conversionFactor = $conversionService->conversionFactorSnapshot($unit);
                $baseQuantity = $conversionService->toBaseQuantity($quantity, $unit);
                $enteredLineTotal = array_key_exists('line_total', $item) && $item['line_total'] !== null && $item['line_total'] !== ''
                    ? round((float) $item['line_total'], 2)
                    : null;
                $unitCost = $enteredLineTotal !== null && $quantity > 0
                    ? round($enteredLineTotal / $quantity, 2)
                    : round((float) ($item['unit_cost'] ?? $unit?->cost_price ?? 0), 2);
                $lineTotal = $enteredLineTotal !== null
                    ? $enteredLineTotal
                    : round($quantity * $unitCost, 2);
                $sellingPrice = array_key_exists('selling_price', $item) && $item['selling_price'] !== null && $item['selling_price'] !== ''
                    ? round((float) $item['selling_price'], 2)
                    : null;

                return [
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'base_quantity' => $baseQuantity,
                    'conversion_factor_snapshot' => $conversionFactor,
                    'unit_cost' => $unitCost,
                    'selling_price' => $sellingPrice,
                    'line_total' => $lineTotal,
                ];
            });

            $subtotal = round((float) $preparedItems->sum('line_total'), 2);
            $requestedType = $validated['purchase_type'] ?? null;
            $enteredAmount = round((float) ($validated['amount_paid'] ?? 0), 2);

            if (! array_key_exists('amount_paid', $validated) || $validated['amount_paid'] === null || $validated['amount_paid'] === '') {
                $enteredAmount = match ($requestedType) {
                    'cash' => $subtotal,
                    'credit' => 0,
                    default => $subtotal,
                };
            }

            $isCredit = $requestedType === 'credit' || $enteredAmount < $subtotal;
            $purchaseType = $isCredit ? 'credit' : 'cash';
            $amountApplied = min($enteredAmount, $subtotal);
            $balanceDue = max($subtotal - $amountApplied, 0);
            $paymentModeId = $validated['payment_mode_id']
                ?? $this->defaultPaymentModeId($purchaseType === 'credit' ? 'Credit' : 'Cash');
            $fundingSourceId = ! empty($validated['purchase_funding_source_id'])
                ? (int) $validated['purchase_funding_source_id']
                : null;
            $requiresActualMoneySource = $requestedType === 'cash' || $purchaseType === 'cash' || $amountApplied > 0;

            if ($requiresActualMoneySource && ! $fundingSourceId) {
                throw ValidationException::withMessages([
                    'purchase_funding_source_id' => 'Please select where the purchase money came from.',
                ]);
            }

            if ($requiresActualMoneySource && $fundingSourceId && $this->isSupplierCreditFundingSourceId($fundingSourceId)) {
                throw ValidationException::withMessages([
                    'purchase_funding_source_id' => 'Paid purchases must use an actual money source, not Supplier Credit / Not Paid Yet.',
                ]);
            }

            if (! $fundingSourceId && $purchaseType === 'credit') {
                $fundingSourceId = $this->defaultFundingSourceId('Supplier Credit / Not Paid Yet');
            }

            $purchase = Purchase::create([
                'purchase_no' => $documentNumberService->make('purchase', $validated['purchase_date']),
                'purchase_date' => $validated['purchase_date'],
                'supplier_id' => $validated['supplier_id'],
                'store_id' => $storeId,
                'purchase_type' => $purchaseType,
                'payment_mode_id' => $paymentModeId,
                'purchase_funding_source_id' => $fundingSourceId,
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'vat_amount' => 0,
                'total_amount' => $subtotal,
                'amount_paid' => $amountApplied,
                'balance_due' => $balanceDue,
                'credit_period_days' => $purchaseType === 'credit' ? ($validated['credit_period_days'] ?? 30) : null,
                'credit_due_date' => $purchaseType === 'credit'
                    ? Carbon::parse($validated['purchase_date'])->addDays((int) ($validated['credit_period_days'] ?? 30))->toDateString()
                    : null,
                'status' => 'posted',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($preparedItems as $item) {
                /** @var ProductUnit $unit */
                $unit = $item['unit'];

                $purchaseItem = $purchase->items()->create([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'quantity' => $item['quantity'],
                    'base_quantity' => $item['base_quantity'],
                    'conversion_factor_snapshot' => $item['conversion_factor_snapshot'],
                    'unit_cost' => $item['unit_cost'],
                    'vat_amount' => 0,
                    'discount_amount' => 0,
                    'line_total' => $item['line_total'],
                ]);
                $costSyncService->syncFromPurchaseItem($purchaseItem);
                if ($item['selling_price'] !== null) {
                    ProductUnit::query()
                        ->whereKey($unit->id)
                        ->update(['selling_price' => $item['selling_price']]);
                }

                InventoryTransaction::create([
                    'transaction_date' => $purchase->purchase_date,
                    'store_id' => $purchase->store_id,
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'reference_type' => 'purchase',
                    'reference_id' => $purchaseItem->id,
                    'reference_no' => $purchase->purchase_no,
                    'movement_type' => 'purchase',
                    'quantity_in' => $item['quantity'],
                    'quantity_out' => 0,
                    'base_quantity_in' => $item['base_quantity'],
                    'base_quantity_out' => 0,
                    'conversion_factor_snapshot' => $item['conversion_factor_snapshot'],
                    'unit_cost' => $item['unit_cost'],
                    'unit_price' => null,
                ]);
            }

            if ($correctionSourcePurchase) {
                $this->voidPurchaseRecord($correctionSourcePurchase, 'Corrected and reposted as '.$purchase->purchase_no, false);
                $correctionSourcePurchase->update(['replaced_by_purchase_id' => $purchase->id]);
                $purchase->update(['corrected_from_purchase_id' => $correctionSourcePurchase->id]);
            }

            return $purchase;
        });

        $auditLogService->record('purchase.posted', $purchase, "Purchase {$purchase->purchase_no} posted.", [
            'purchase_type' => $purchase->purchase_type,
            'total_amount' => $purchase->total_amount,
            'corrected_from_purchase_id' => $purchase->corrected_from_purchase_id,
        ]);

        if ($purchase->corrected_from_purchase_id) {
            $auditLogService->record('purchase.corrected', $purchase, "Purchase {$purchase->purchase_no} replaced purchase {$purchase->correctedFrom?->purchase_no}.", [
                'purchase_id' => $purchase->id,
                'corrected_from_purchase_id' => $purchase->corrected_from_purchase_id,
            ]);
        }

        $returnTo = $this->safeReturnTo($validated['return_to'] ?? null);

        if ($returnTo) {
            return redirect()
                ->to($returnTo)
                ->with('status', "Purchase {$purchase->purchase_no} posted successfully.");
        }

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('status', "Purchase {$purchase->purchase_no} posted successfully.");
    }

    public function void(Request $request, Purchase $purchase, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($purchase, $validated) {
            $this->voidPurchaseRecord($purchase, $validated['void_reason'] ?? null);
        });

        $auditLogService->record('purchase.voided', $purchase, "Purchase {$purchase->purchase_no} voided.", [
            'purchase_id' => $purchase->id,
            'reason' => $validated['void_reason'] ?? null,
        ]);

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('status', "Purchase {$purchase->purchase_no} voided successfully.");
    }

    private function guardCorrectionEligibility(Purchase $purchase): void
    {
        if ($purchase->status !== 'posted') {
            throw ValidationException::withMessages([
                'corrected_from_purchase_id' => 'Only posted purchases can be corrected.',
            ]);
        }

        if ($purchase->payments()->exists() || $purchase->returns()->exists()) {
            throw ValidationException::withMessages([
                'corrected_from_purchase_id' => 'This purchase already has follow-up transactions, so it cannot be corrected by reposting.',
            ]);
        }
    }

    private function ensurePurchaseCorrectionAllowed(): void
    {
        $access = app(AccessService::class);

        abort_unless($access->hasRole('admin') || $access->can('purchases.correct'), 403);
    }

    private function voidPurchaseRecord(Purchase $purchase, ?string $reason = null, bool $enforceFollowOnChecks = true): void
    {
        if ($purchase->status !== 'posted') {
            throw ValidationException::withMessages([
                'void_reason' => 'Only posted purchases can be voided.',
            ]);
        }

        if ($enforceFollowOnChecks && ($purchase->payments()->exists() || $purchase->returns()->exists())) {
            throw ValidationException::withMessages([
                'void_reason' => 'This purchase already has payments or returns posted against it and cannot be voided directly.',
            ]);
        }

        $purchase->loadMissing('items');

        foreach ($purchase->items as $item) {
            $conversionFactor = (float) ($item->conversion_factor_snapshot ?: 1);
            $conversionFactor = $conversionFactor > 0 ? $conversionFactor : 1;
            $baseQuantity = (float) ($item->base_quantity ?: ((float) $item->quantity * $conversionFactor));

            InventoryTransaction::create([
                'transaction_date' => $purchase->purchase_date,
                'store_id' => $purchase->store_id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'reference_type' => 'purchase_void',
                'reference_id' => $item->id,
                'reference_no' => $purchase->purchase_no,
                'movement_type' => 'purchase_void',
                'quantity_in' => 0,
                'quantity_out' => $item->quantity,
                'base_quantity_in' => 0,
                'base_quantity_out' => $baseQuantity,
                'conversion_factor_snapshot' => $conversionFactor,
                'unit_cost' => $item->unit_cost,
                'unit_price' => null,
            ]);
        }

        $purchase->update([
            'status' => 'void',
            'amount_paid' => 0,
            'balance_due' => 0,
            'remarks' => trim(($purchase->remarks ? $purchase->remarks.' | ' : '').'VOIDED'.($reason ? ': '.$reason : '')),
        ]);
    }

    private function defaultPaymentModeId(string $preferredName): ?int
    {
        return PaymentMode::query()
            ->whereRaw('UPPER(name) = ?', [strtoupper($preferredName)])
            ->value('id')
            ?? PaymentMode::query()->orderBy('name')->value('id');
    }

    private function defaultFundingSourceId(string $preferredName): ?int
    {
        return PurchaseFundingSource::query()
            ->whereRaw('UPPER(name) = ?', [strtoupper($preferredName)])
            ->value('id')
            ?? PurchaseFundingSource::query()->where('is_active', true)->orderBy('sort_order')->value('id');
    }

    private function isSupplierCreditFundingSourceId(int $fundingSourceId): bool
    {
        return PurchaseFundingSource::query()
            ->whereKey($fundingSourceId)
            ->whereRaw('UPPER(TRIM(name)) = ?', ['SUPPLIER CREDIT / NOT PAID YET'])
            ->exists();
    }

    private function resolveSelectedUnit(int $productUnitId = 0, int $productId = 0): ?ProductUnit
    {
        if ($productUnitId > 0) {
            return ProductUnit::query()
                ->where('is_active', true)
                ->find($productUnitId, ['id', 'product_id', 'cost_price', 'selling_price']);
        }

        if ($productId > 0) {
            return ProductUnit::query()
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderByDesc('is_pos_unit')
                ->orderBy('unit_name')
                ->first(['id', 'product_id', 'cost_price', 'selling_price']);
        }

        return null;
    }

    private function safeReturnTo(?string $returnTo): ?string
    {
        if (! is_string($returnTo) || trim($returnTo) === '') {
            return null;
        }

        $host = parse_url($returnTo, PHP_URL_HOST);

        if ($host === null || $host === request()->getHost()) {
            return $returnTo;
        }

        return null;
    }
}
