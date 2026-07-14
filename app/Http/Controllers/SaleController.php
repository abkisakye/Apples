<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\InventoryTransaction;
use App\Models\PaymentMode;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\AccessService;
use App\Support\ApprovalPinService;
use App\Support\ProductUnitConversionService;
use App\Support\StockAvailabilityService;
use App\Support\StoreAssignmentService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        $type = trim((string) $request->string('type'));
        $type = in_array($type, ['cash', 'credit'], true) ? $type : '';
        $dateFrom = $request->date('date_from')?->toDateString();
        $dateTo = $request->date('date_to')?->toDateString();
        $pageTitle = match ($type) {
            'cash' => 'Cash Sales',
            'credit' => 'Credit Sales',
            default => 'Sales',
        };

        $sales = Sale::query()
            ->with(['customer:id,name,phone', 'store:id,name', 'paymentMode:id,name', 'createdBy:id,name'])
            ->whereIn('sale_type', ['cash', 'credit'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('sale_no', 'like', "%{$search}%")
                        ->orWhere('sale_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('sale_date', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customer) => $customer
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('store', fn ($store) => $store->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('paymentMode', fn ($paymentMode) => $paymentMode->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('createdBy', fn ($user) => $user->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($type !== '', fn ($query) => $query->where('sale_type', $type))
            ->when($dateFrom && $dateTo, fn ($query) => $query->whereDate('sale_date', '>=', $dateFrom)->whereDate('sale_date', '<=', $dateTo))
            ->latest('sale_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        if ($request->ajax()) {
            return view('sales.partials.index_results', compact('sales', 'search', 'type', 'pageTitle', 'dateFrom', 'dateTo'));
        }

        return view('sales.index', compact('sales', 'search', 'type', 'pageTitle', 'dateFrom', 'dateTo'));
    }

    public function create(Request $request, StockAvailabilityService $stockAvailabilityService): View
    {
        $currentStore = auth()->user()?->defaultStore;
        $correctSaleId = $request->integer('correct_sale_id');
        $exchangeReturnId = $request->integer('exchange_return_id');
        $sourceSale = null;
        $exchangeReturn = null;
        $prefill = [
            'customer_id' => $request->integer('customer_id') ?: null,
            'amount_paid' => 0,
            'discount_amount' => 0,
            'credit_period_days' => 30,
            'remarks' => null,
            'items' => [],
        ];

        if ($correctSaleId > 0) {
            $sourceSale = Sale::query()
                ->with(['items.productUnit.product:id,name,code'])
                ->findOrFail($correctSaleId);

            $prefill = [
                'customer_id' => $sourceSale->customer_id,
                'amount_paid' => (float) $sourceSale->amount_paid,
                'discount_amount' => (float) $sourceSale->discount_amount,
                'credit_period_days' => (int) ($sourceSale->credit_period_days ?? 30),
                'remarks' => trim('Correction for '.$sourceSale->sale_no.($sourceSale->remarks ? ' | '.$sourceSale->remarks : '')),
                'items' => $sourceSale->items->map(function ($item) {
                    return [
                        'product_unit_id' => $item->product_unit_id,
                        'quantity' => (int) round((float) $item->quantity),
                        'unit_price' => (float) $item->unit_price,
                    ];
                })->all(),
            ];
        }

        if ($exchangeReturnId > 0) {
            $exchangeReturn = SaleReturn::query()
                ->with(['sale:id,sale_no,sale_date', 'items.product:id,name', 'items.productUnit:id,unit_name'])
                ->findOrFail($exchangeReturnId);

            if ($exchangeReturn->return_type === 'exchange') {
                $prefill = [
                    'customer_id' => $exchangeReturn->customer_id,
                    'amount_paid' => 0,
                    'discount_amount' => 0,
                    'credit_period_days' => 30,
                    'remarks' => trim('Replacement for '.$exchangeReturn->return_no.($exchangeReturn->sale?->sale_no ? ' / '.$exchangeReturn->sale->sale_no : '')),
                    'items' => $exchangeReturn->items->map(function ($item) {
                        return [
                            'product_unit_id' => $item->product_unit_id,
                            'quantity' => (int) round((float) $item->quantity),
                            'unit_price' => (float) $item->unit_price,
                        ];
                    })->all(),
                ];
            }
        }

        $displayStore = $currentStore ?? Store::query()->orderBy('name')->first(['id', 'name']);
        $productUnits = ProductUnit::query()
            ->with('product.category:id,name')
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query->where('is_active', true))
            ->orderBy('product_id')
            ->orderBy('unit_name')
            ->get(['id', 'product_id', 'unit_name', 'selling_price', 'cost_price', 'barcode', 'part_number', 'conversion_factor', 'is_base_unit']);

        $unitsByProduct = $productUnits
            ->groupBy('product_id')
            ->map(fn ($units) => $units->pluck('unit_name')->filter()->unique()->values()->all());

        $baseStockByProduct = $displayStore?->id
            ? $stockAvailabilityService->availableBaseQuantities($displayStore->id, $productUnits->pluck('product_id'))
            : collect();

        return view('sales.create', [
            'currentStore' => $displayStore,
            'customers' => Customer::query()
                ->where('is_active', true)
                ->withSum(['sales as outstanding_credit' => fn ($query) => $query->posted()->where('sale_type', 'credit')], 'balance_due')
                ->withSum(['openingBalancePayments as opening_payments_total' => fn ($query) => $query->posted()], 'amount')
                ->orderByDesc('is_walk_in')
                ->orderBy('name')
                ->get(['id', 'name', 'is_walk_in', 'location', 'opening_balance']),
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'productUnits' => $productUnits,
            'unitsByProduct' => $unitsByProduct,
            'baseStockByProduct' => $baseStockByProduct,
            'sourceSale' => $sourceSale,
            'exchangeReturn' => $exchangeReturn,
            'prefillSale' => $prefill,
            'activeShift' => CashShift::query()
                ->open()
                ->where('user_id', auth()->id())
                ->when($currentStore?->id, fn ($query) => $query->where('store_id', $currentStore->id))
                ->latest('opened_at')
                ->first(),
            'cashierDiscountLimit' => (float) config('business.cashier_discount_limit', 0),
            'requiresShift' => $this->requiresCashShift(app(AccessService::class)),
            'requiresApprovalPin' => ! app(AccessService::class)->can('sales.override'),
            'canOverridePrices' => app(AccessService::class)->can('sales.override'),
        ]);
    }

    public function correct(Sale $sale): RedirectResponse
    {
        return redirect()->route('sales.create', ['correct_sale_id' => $sale->id]);
    }

    public function show(Sale $sale): View
    {
        $sale->load([
            'customer:id,name,phone,location,address',
            'store:id,name',
            'paymentMode:id,name',
            'items.product:id,name,base_product_unit_id,base_unit_label',
            'items.product.baseProductUnit:id,unit_name',
            'items.productUnit:id,unit_name',
            'payments.paymentMode:id,name',
            'returns.paymentMode:id,name',
            'returns.replacementSale:id,sale_no',
            'correctedFrom:id,sale_no',
            'replacedBy:id,sale_no',
        ]);
        $this->decorateSaleItemsForDisplay($sale);

        return view('sales.show', compact('sale'));
    }

    public function print(Sale $sale): View
    {
        $sale->load([
            'customer:id,name,phone,location,address',
            'store:id,name',
            'paymentMode:id,name',
            'items.product:id,name,base_product_unit_id,base_unit_label',
            'items.product.baseProductUnit:id,unit_name',
            'items.productUnit:id,unit_name',
            'payments.paymentMode:id,name',
            'createdBy:id,name,username',
        ]);
        $this->decorateSaleItemsForDisplay($sale);

        return view('sales.print', compact('sale'));
    }

    public function store(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService,
        StockAvailabilityService $stockAvailabilityService,
        ApprovalPinService $approvalPinService,
        ProductUnitConversionService $conversionService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'sale_date' => ['required', 'date'],
            'store_id' => ['required', 'exists:stores,id'],
            'sale_type' => ['nullable', 'in:cash,credit'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'payment_mode_id' => ['nullable', 'exists:payment_modes,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'credit_period_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'amount_paid' => ['nullable', 'numeric', 'min:0'],
            'approval_pin' => ['nullable', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
            'corrected_from_sale_id' => ['nullable', 'exists:sales,id'],
            'exchange_return_id' => ['nullable', 'exists:sale_returns,id'],
            'items' => ['required', 'array'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $items = collect($validated['items'])
            ->filter(fn (array $item) => ! empty($item['product_unit_id']) && ! empty($item['quantity']))
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one sale item before saving the sale.',
            ]);
        }

        $productUnits = ProductUnit::query()
            ->with('product:id,name')
            ->whereIn('id', $items->pluck('product_unit_id'))
            ->get()
            ->keyBy('id');

        $access = app(AccessService::class);
        $storeId = $storeAssignmentService->resolveStoreId((int) $validated['store_id'], $request->user(), $access);

        if ($this->requiresCashShift($access)) {
            $activeShift = CashShift::query()
                ->open()
                ->where('user_id', auth()->id())
                ->where('store_id', $storeId)
                ->latest('opened_at')
                ->first();

            if (! $activeShift) {
                throw ValidationException::withMessages([
                    'sale_date' => 'Open a cash shift for this store before posting sales from this account.',
                ]);
            }
        }

        $preflightItems = $items->map(function (array $item, int $index) use ($productUnits, $conversionService) {
            /** @var ProductUnit|null $unit */
            $unit = $productUnits->get((int) $item['product_unit_id']);
            if (! $unit) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_unit_id" => 'One selected product is no longer available.',
                ]);
            }

            $quantity = round(max((float) $item['quantity'], 0.001), 3);
            $conversionService->validatePrecision($quantity, $unit, 'items');

            return [
                'unit' => $unit,
                'base_quantity' => $conversionService->toBaseQuantity($quantity, $unit),
            ];
        });

        $preflightCorrectionRestockByUnit = [];

        if (! empty($validated['corrected_from_sale_id'])) {
            $correctionSourceSale = Sale::query()->with('items')->findOrFail($validated['corrected_from_sale_id']);
            $this->guardCorrectionEligibility($correctionSourceSale);
            $preflightCorrectionRestockByUnit = $this->baseRestockByProduct($correctionSourceSale);
        }

        $this->ensureSaleItemsBaseStockAvailable(
            $preflightItems,
            $storeId,
            $stockAvailabilityService,
            $preflightCorrectionRestockByUnit
        );

        $saleContext = DB::transaction(function () use (
            $validated,
            $items,
            $productUnits,
            $documentNumberService,
            $access,
            $storeId,
            $stockAvailabilityService,
            $approvalPinService,
            $conversionService
        ) {
            $customerId = (int) ($validated['customer_id'] ?? 0);
            if (! $customerId) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Choose a customer before saving this sale.',
                ]);
            }

            $customer = Customer::query()->findOrFail($customerId);
            $priceOverrideApproved = $access->can('sales.override') || $approvalPinService->verify($validated['approval_pin'] ?? null);
            $priceOverrides = [];

            $preparedItems = $items->map(function (array $item, int $index) use ($productUnits, $priceOverrideApproved, &$priceOverrides, $conversionService) {
                /** @var ProductUnit|null $unit */
                $unit = $productUnits->get((int) $item['product_unit_id']);
                if (! $unit) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_unit_id" => 'One selected product is no longer available.',
                    ]);
                }

                $quantity = round(max((float) $item['quantity'], 0.001), 3);
                $conversionService->validatePrecision($quantity, $unit, 'items');
                $conversionFactor = $conversionService->conversionFactorSnapshot($unit);
                $baseQuantity = $conversionService->toBaseQuantity($quantity, $unit);
                $catalogPrice = round((float) $unit->selling_price, 2);
                $requestedPrice = round((float) ($item['unit_price'] ?? $catalogPrice), 2);

                if ($requestedPrice !== $catalogPrice && ! $priceOverrideApproved) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit_price" => "Price override for {$unit->product?->name} requires override access or the admin approval PIN.",
                    ]);
                }

                if ($requestedPrice !== $catalogPrice) {
                    $priceOverrides[] = [
                        'product_unit_id' => $unit->id,
                        'product_name' => $unit->product?->name,
                        'unit_name' => $unit->unit_name,
                        'catalog_price' => $catalogPrice,
                        'override_price' => $requestedPrice,
                    ];
                }

                $unitPrice = $requestedPrice;
                $lineTotal = round($quantity * $unitPrice, 2);

                return [
                    'unit' => $unit,
                    'quantity' => $quantity,
                    'base_quantity' => $baseQuantity,
                    'conversion_factor_snapshot' => $conversionFactor,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            });

            $subtotal = round((float) $preparedItems->sum('line_total'), 2);
            $discountAmount = min(round((float) ($validated['discount_amount'] ?? 0), 2), $subtotal);
            $this->enforceDiscountApproval($discountAmount, $access, $validated['approval_pin'] ?? null);
            $netTotal = round(max($subtotal - $discountAmount, 0), 2);
            $requestedType = $validated['sale_type'] ?? null;
            $enteredAmount = round((float) ($validated['amount_paid'] ?? 0), 2);

            if (! array_key_exists('amount_paid', $validated) || $validated['amount_paid'] === null || $validated['amount_paid'] === '') {
                $enteredAmount = match ($requestedType) {
                    'cash' => $netTotal,
                    'credit' => 0,
                    default => $netTotal,
                };
            }

            $isCredit = $requestedType === 'credit' || $enteredAmount < $netTotal;
            $saleType = $isCredit ? 'credit' : 'cash';

            $cashTendered = $saleType === 'cash' ? $enteredAmount : null;
            $amountApplied = min($enteredAmount, $netTotal);
            $balanceDue = max($netTotal - $amountApplied, 0);
            $changeGiven = $saleType === 'cash' ? max($enteredAmount - $netTotal, 0) : 0;

            if ($customer->is_walk_in && $balanceDue > 0) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Walk-in customer cannot carry credit. Choose a named customer or complete full payment.',
                ]);
            }

            if ($amountApplied > 0 && empty($validated['payment_mode_id'])) {
                throw ValidationException::withMessages([
                    'payment_mode_id' => 'Choose the payment mode before posting a paid sale.',
                ]);
            }

            $paymentModeId = $amountApplied > 0
                ? (int) $validated['payment_mode_id']
                : ($saleType === 'credit' ? $this->defaultPaymentModeId('Credit') : null);

            $correctionSourceSale = null;
            $correctionRestockByUnit = [];

            if (! empty($validated['corrected_from_sale_id'])) {
                $correctionSourceSale = Sale::query()->with('items')->findOrFail($validated['corrected_from_sale_id']);
                $this->guardCorrectionEligibility($correctionSourceSale);
                $correctionRestockByUnit = $this->baseRestockByProduct($correctionSourceSale);
            }

            $this->ensureSaleItemsBaseStockAvailable(
                $preparedItems,
                $storeId,
                $stockAvailabilityService,
                $correctionRestockByUnit
            );

            $sale = Sale::create([
                'sale_no' => $documentNumberService->make($saleType === 'credit' ? 'credit_sale' : 'cash_sale', $validated['sale_date']),
                'sale_date' => $validated['sale_date'],
                'store_id' => $storeId,
                'customer_id' => $customerId,
                'sale_type' => $saleType,
                'payment_mode_id' => $paymentModeId,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'vat_amount' => 0,
                'total_amount' => $netTotal,
                'amount_paid' => $amountApplied,
                'balance_due' => $balanceDue,
                'credit_period_days' => $saleType === 'credit' ? ($validated['credit_period_days'] ?? 30) : null,
                'credit_due_date' => $saleType === 'credit'
                    ? Carbon::parse($validated['sale_date'])->addDays((int) ($validated['credit_period_days'] ?? 30))->toDateString()
                    : null,
                'cash_tendered' => $cashTendered,
                'change_given' => $changeGiven,
                'status' => 'posted',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $remainingDiscount = $discountAmount;
            $itemCount = max($preparedItems->count(), 1);

            foreach ($preparedItems as $item) {
                /** @var ProductUnit $unit */
                $unit = $item['unit'];

                $allocatedDiscount = $discountAmount > 0
                    ? round(($item['line_total'] / max($subtotal, 0.01)) * $discountAmount, 2)
                    : 0;

                if ($itemCount === 1) {
                    $allocatedDiscount = $remainingDiscount;
                }

                $remainingDiscount = round($remainingDiscount - $allocatedDiscount, 2);
                $itemCount--;

                $saleItem = $sale->items()->create([
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'quantity' => $item['quantity'],
                    'base_quantity' => $item['base_quantity'],
                    'conversion_factor_snapshot' => $item['conversion_factor_snapshot'],
                    'unit_price' => $item['unit_price'],
                    'selling_price_snapshot' => $unit->selling_price,
                    'cost_price_snapshot' => $unit->cost_price,
                    'discount_amount' => $allocatedDiscount,
                    'vat_amount' => 0,
                    'line_total' => round(max($item['line_total'] - $allocatedDiscount, 0), 2),
                ]);

                InventoryTransaction::create([
                    'transaction_date' => $sale->sale_date,
                    'store_id' => $sale->store_id,
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'reference_type' => 'sale',
                    'reference_id' => $saleItem->id,
                    'reference_no' => $sale->sale_no,
                    'movement_type' => 'sale',
                    'quantity_in' => 0,
                    'quantity_out' => $item['quantity'],
                    'base_quantity_in' => 0,
                    'base_quantity_out' => $item['base_quantity'],
                    'conversion_factor_snapshot' => $item['conversion_factor_snapshot'],
                    'unit_cost' => $unit->cost_price,
                    'unit_price' => $item['unit_price'],
                ]);
            }

            if ($correctionSourceSale) {
                /** @var Sale $sourceSale */
                $sourceSale = $correctionSourceSale->loadMissing(['items', 'payments', 'returns']);
                $this->voidSaleRecord($sourceSale, 'Corrected and reposted as '.$sale->sale_no, false);
                $sourceSale->update(['replaced_by_sale_id' => $sale->id]);
                $sale->update(['corrected_from_sale_id' => $sourceSale->id]);
            }

            if (! empty($validated['exchange_return_id'])) {
                $exchangeReturn = SaleReturn::query()->findOrFail((int) $validated['exchange_return_id']);

                if ($exchangeReturn->return_type !== 'exchange') {
                    throw ValidationException::withMessages([
                        'exchange_return_id' => 'Only exchange returns can open a replacement sale.',
                    ]);
                }

                if ($exchangeReturn->replacement_sale_id && (int) $exchangeReturn->replacement_sale_id !== (int) $sale->id) {
                    throw ValidationException::withMessages([
                        'exchange_return_id' => 'This exchange return is already linked to another replacement sale.',
                    ]);
                }

                $exchangeReturn->update([
                    'replacement_sale_id' => $sale->id,
                    'remarks' => trim(($exchangeReturn->remarks ? $exchangeReturn->remarks.' | ' : '').'Replacement sale '.$sale->sale_no.' posted'),
                ]);

                $sale->update([
                    'remarks' => trim(($sale->remarks ? $sale->remarks.' | ' : '').'Replacement for '.$exchangeReturn->return_no),
                ]);
            }

            return [
                'sale' => $sale,
                'price_overrides' => $priceOverrides,
            ];
        });
        /** @var Sale $sale */
        $sale = $saleContext['sale'];

        $auditLogService->record('sale.posted', $sale, "Sale {$sale->sale_no} posted.", [
            'sale_type' => $sale->sale_type,
            'total_amount' => $sale->total_amount,
            'corrected_from_sale_id' => $sale->corrected_from_sale_id,
            'exchange_return_id' => $validated['exchange_return_id'] ?? null,
            'price_overrides' => $saleContext['price_overrides'],
        ]);

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', "Sale {$sale->sale_no} posted successfully.")
            ->with('auto_print_document', true);
    }

    public function void(Request $request, Sale $sale, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'void_reason' => ['nullable', 'string', 'max:500'],
            'approval_pin' => ['nullable', 'string', 'max:50'],
        ]);

        $this->enforceSensitiveActionApproval(app(AccessService::class), $validated['approval_pin'] ?? null, 'An admin approval PIN is required to void a posted sale from this account.');

        DB::transaction(function () use ($sale, $validated) {
            $this->voidSaleRecord($sale, $validated['void_reason'] ?? null);
        });

        $auditLogService->record('sale.voided', $sale, "Sale {$sale->sale_no} voided.", [
            'sale_id' => $sale->id,
            'reason' => $validated['void_reason'] ?? null,
        ]);

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', "Sale {$sale->sale_no} voided successfully.");
    }

    private function ensureSaleItemsBaseStockAvailable(
        Collection $preparedItems,
        int $storeId,
        StockAvailabilityService $stockAvailabilityService,
        array $availableBaseAdjustments = []
    ): void {
        foreach ($preparedItems as $item) {
            /** @var ProductUnit $unit */
            $unit = $item['unit'];
            $availableBaseAdjustment = (float) ($availableBaseAdjustments[$unit->product_id] ?? 0);

            $stockAvailabilityService->ensureBaseAvailable(
                $storeId,
                $unit,
                (float) $item['base_quantity'],
                'items',
                "{$unit->product?->name} - {$unit->unit_name} does not have enough base stock at the selected store.",
                $availableBaseAdjustment
            );
        }
    }

    private function baseRestockByProduct(Sale $sale): array
    {
        return $sale->items
            ->groupBy('product_id')
            ->map(fn ($group) => round((float) $group->sum(fn ($item) => (float) ($item->base_quantity ?? 0)), 3))
            ->all();
    }

    private function guardCorrectionEligibility(Sale $sale): void
    {
        if ($sale->status !== 'posted') {
            throw ValidationException::withMessages([
                'corrected_from_sale_id' => 'Only posted sales can be corrected.',
            ]);
        }

        if ($sale->payments()->exists() || $sale->returns()->exists()) {
            throw ValidationException::withMessages([
                'corrected_from_sale_id' => 'This sale already has follow-up transactions, so it cannot be corrected by reposting.',
            ]);
        }
    }

    private function voidSaleRecord(Sale $sale, ?string $reason = null, bool $enforceFollowOnChecks = true): void
    {
        if ($sale->status !== 'posted') {
            throw ValidationException::withMessages([
                'void_reason' => 'Only posted sales can be voided.',
            ]);
        }

        if ($enforceFollowOnChecks && ($sale->payments()->exists() || $sale->returns()->exists())) {
            throw ValidationException::withMessages([
                'void_reason' => 'This sale already has payments or returns posted against it and cannot be voided directly.',
            ]);
        }

        $sale->loadMissing('items');

        foreach ($sale->items as $item) {
            InventoryTransaction::create([
                'transaction_date' => $sale->sale_date,
                'store_id' => $sale->store_id,
                'product_id' => $item->product_id,
                'product_unit_id' => $item->product_unit_id,
                'reference_type' => 'sale_void',
                'reference_id' => $item->id,
                'reference_no' => $sale->sale_no,
                'movement_type' => 'sale_void',
                'quantity_in' => $item->quantity,
                'quantity_out' => 0,
                'unit_cost' => $item->cost_price_snapshot,
                'unit_price' => $item->unit_price,
            ]);
        }

        $sale->update([
            'status' => 'void',
            'amount_paid' => 0,
            'balance_due' => 0,
            'cash_tendered' => null,
            'change_given' => 0,
            'remarks' => trim(($sale->remarks ? $sale->remarks.' | ' : '').'VOIDED'.($reason ? ': '.$reason : '')),
        ]);
    }

    private function defaultPaymentModeId(string $preferredName): ?int
    {
        return PaymentMode::query()
            ->whereRaw('UPPER(name) = ?', [strtoupper($preferredName)])
            ->value('id')
            ?? PaymentMode::query()->orderBy('name')->value('id');
    }

    private function decorateSaleItemsForDisplay(Sale $sale): void
    {
        $sale->items->each(function ($item) {
            $item->setAttribute('display_item_label', $this->compactItemUnitLabel(
                (string) ($item->product?->name ?? ''),
                (string) ($item->productUnit?->unit_name ?? '')
            ));
            $item->setAttribute('base_stock_impact_label', $this->baseStockImpactLabel($item));
        });
    }

    private function compactItemUnitLabel(string $productName, string $unitName): string
    {
        $productName = trim(preg_replace('/\s+/', ' ', $productName) ?? '');
        $unitName = trim(preg_replace('/\s+/', ' ', $unitName) ?? '');

        if ($productName === '') {
            return $unitName ?: '-';
        }

        if ($unitName === '') {
            return $productName;
        }

        $suffix = $this->unitSuffixAfterProductName($productName, $unitName);

        if ($suffix === null && str_ends_with(strtolower($productName), 's')) {
            $suffix = $this->unitSuffixAfterProductName(substr($productName, 0, -1), $unitName);
        }

        if ($suffix !== null) {
            return $suffix === '' ? $productName : "{$productName} - {$suffix}";
        }

        return "{$productName} - {$unitName}";
    }

    private function unitSuffixAfterProductName(string $productName, string $unitName): ?string
    {
        $productLower = strtolower($productName);
        $unitLower = strtolower($unitName);

        if ($productLower === '' || ! str_starts_with($unitLower, $productLower)) {
            return null;
        }

        return trim(substr($unitName, strlen($productName)));
    }

    private function baseStockImpactLabel($item): string
    {
        $baseQuantity = (float) ($item->base_quantity ?? 0);

        if ($baseQuantity <= 0) {
            return '';
        }

        $baseUnit = $item->product?->base_unit_label
            ?: $item->product?->baseProductUnit?->unit_name
            ?: 'base unit';

        return 'Base stock out: '.$this->formatQuantity($baseQuantity).' '.$this->unitLabel($baseUnit, $baseQuantity);
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    private function unitLabel(string $label, float $quantity): string
    {
        $label = strtolower(trim($label));

        if (abs($quantity - 1.0) < 0.0005 || $label === '' || str_ends_with($label, 's')) {
            return $label;
        }

        if (str_ends_with($label, 'y')) {
            return substr($label, 0, -1).'ies';
        }

        if (str_ends_with($label, 'piece')) {
            return $label.'s';
        }

        return $label.'s';
    }

    private function requiresCashShift(AccessService $access): bool
    {
        return $access->can('cash_shifts.manage') && ! $access->can('sales.override');
    }

    private function enforceDiscountApproval(float $discountAmount, AccessService $access, ?string $approvalPin): void
    {
        if ($discountAmount <= 0 || $access->can('sales.override')) {
            return;
        }

        $limit = (float) config('business.cashier_discount_limit', 0);

        if ($discountAmount <= $limit) {
            return;
        }

        $this->enforceSensitiveActionApproval($access, $approvalPin, 'This discount is above the cashier limit. Enter the admin approval PIN to continue.');
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
