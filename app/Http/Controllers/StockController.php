<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\ProductUnit;
use App\Models\StockCount;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Services\ExcelExportService;
use App\Support\AccessService;
use App\Support\ProductUnitConversionService;
use App\Support\StockDisplayService;
use App\Support\StockAvailabilityService;
use App\Support\StoreAssignmentService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockController extends Controller
{
    public function balances(Request $request, StockDisplayService $stockDisplayService): View
    {
        [$stores, $categories, $filters] = $this->stockReferenceData($request);
        $rows = $stockDisplayService->rows($request);

        return view('stock.balances', compact('rows', 'stores', 'categories', 'filters'));
    }

    public function reorder(Request $request, StockDisplayService $stockDisplayService): View
    {
        [$stores, $categories, $filters] = $this->stockReferenceData($request);
        $rows = $stockDisplayService->rows($request, true);

        return view('stock.reorder', compact('rows', 'stores', 'categories', 'filters'));
    }

    public function transfersIndex(): View
    {
        $rows = DB::table('inventory_transactions')
            ->selectRaw('reference_no, transaction_date, MIN(store_id) as any_store_id, COUNT(*) / 2 as line_count')
            ->where('reference_type', 'stock_transfer')
            ->groupBy('reference_no', 'transaction_date')
            ->orderByDesc('transaction_date')
            ->orderByDesc('reference_no')
            ->get();

        return view('stock.transfers_index', compact('rows'));
    }

    public function adjustmentsIndex(): View
    {
        $rows = DB::table('inventory_transactions')
            ->selectRaw('reference_no, transaction_date, movement_type, store_id, COUNT(*) as line_count')
            ->where('reference_type', 'stock_adjustment')
            ->groupBy('reference_no', 'transaction_date', 'movement_type', 'store_id')
            ->orderByDesc('transaction_date')
            ->orderByDesc('reference_no')
            ->get();

        return view('stock.adjustments_index', compact('rows'));
    }

    public function countsIndex(): View
    {
        $rows = StockCount::query()
            ->with('store:id,name', 'user:id,name', 'assignedUser:id,name')
            ->orderByDesc('count_date')
            ->orderByDesc('id')
            ->get();

        return view('stock.counts_index', compact('rows'));
    }

    public function balancesExport(Request $request, ExcelExportService $excelExportService, StockDisplayService $stockDisplayService): BinaryFileResponse
    {
        $rows = $stockDisplayService->rows($request);

        return $excelExportService->download('stock-balance.xlsx', [
            'Product', 'Code', 'Base Unit', 'Category', 'Reorder Level', 'Base Stock', 'Friendly Breakdown', 'Stock Value',
        ], $rows->map(fn ($row) => [
            $row->product_name,
            $row->product_code,
            $row->base_unit_label,
            $row->category_name,
            $row->reorder_level_label,
            $row->base_stock_label,
            $row->friendly_breakdown,
            $row->stock_value,
        ]));
    }

    public function reorderExport(Request $request, ExcelExportService $excelExportService, StockDisplayService $stockDisplayService): BinaryFileResponse
    {
        $rows = $stockDisplayService->rows($request, true);

        return $excelExportService->download('stock-reorder.xlsx', [
            'Product', 'Base Unit', 'Category', 'Reorder Level', 'Base Stock', 'Shortage', 'Friendly Breakdown',
        ], $rows->map(fn ($row) => [
            $row->product_name,
            $row->base_unit_label,
            $row->category_name,
            $row->reorder_level_label,
            $row->base_stock_label,
            $row->shortage_label,
            $row->friendly_breakdown,
        ]));
    }

    public function transferCreate(): View
    {
        $currentStore = auth()->user()?->defaultStore;

        return view('stock.transfer', [
            'currentStore' => $currentStore ?? Store::query()->orderBy('name')->first(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'productUnits' => ProductUnit::query()
                ->with('product:id,name')
                ->where('is_active', true)
                ->orderBy('product_id')
                ->orderBy('unit_name')
                ->get(['id', 'product_id', 'unit_name', 'cost_price', 'barcode', 'part_number']),
        ]);
    }

    public function transferStore(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService,
        StockAvailabilityService $stockAvailabilityService,
        ProductUnitConversionService $conversionService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'transfer_date' => ['required', 'date'],
            'from_store_id' => ['required', 'exists:stores,id'],
            'to_store_id' => ['required', 'exists:stores,id', 'different:from_store_id'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $items = collect($validated['items'])
            ->filter(fn (array $item) => ! empty($item['product_unit_id']) && ! empty($item['quantity']))
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item to transfer.',
            ]);
        }

        $units = ProductUnit::query()->whereIn('id', $items->pluck('product_unit_id'))->get()->keyBy('id');
        $referenceNo = $documentNumberService->make('stock_transfer', $validated['transfer_date']);
        $fromStoreId = $storeAssignmentService->resolveStoreId((int) $validated['from_store_id'], $request->user(), app(AccessService::class), 'from_store_id');
        $toStoreId = (int) $validated['to_store_id'];

        DB::transaction(function () use ($validated, $items, $units, $referenceNo, $fromStoreId, $toStoreId, $stockAvailabilityService, $conversionService) {
            foreach ($items as $index => $item) {
                /** @var ProductUnit $unit */
                $unit = $units->get((int) $item['product_unit_id']);
                $quantity = max((int) $item['quantity'], 1);
                $baseQuantity = $conversionService->toBaseQuantity($quantity, $unit);
                $conversionFactor = $conversionService->conversionFactorSnapshot($unit);
                $referenceId = abs(crc32($referenceNo.'-'.$index));
                $stockAvailabilityService->ensureBaseAvailable($fromStoreId, $unit, $baseQuantity, 'items');

                InventoryTransaction::create([
                    'transaction_date' => $validated['transfer_date'],
                    'store_id' => $fromStoreId,
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $referenceId,
                    'reference_no' => $referenceNo,
                    'movement_type' => 'transfer_out',
                    'quantity_in' => 0,
                    'quantity_out' => $quantity,
                    'base_quantity_in' => 0,
                    'base_quantity_out' => $baseQuantity,
                    'conversion_factor_snapshot' => $conversionFactor,
                    'unit_cost' => $unit->cost_price,
                    'remarks' => $validated['remarks'] ?? null,
                ]);

                InventoryTransaction::create([
                    'transaction_date' => $validated['transfer_date'],
                    'store_id' => $toStoreId,
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'reference_type' => 'stock_transfer',
                    'reference_id' => $referenceId,
                    'reference_no' => $referenceNo,
                    'movement_type' => 'transfer_in',
                    'quantity_in' => $quantity,
                    'quantity_out' => 0,
                    'base_quantity_in' => $baseQuantity,
                    'base_quantity_out' => 0,
                    'conversion_factor_snapshot' => $conversionFactor,
                    'unit_cost' => $unit->cost_price,
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            }
        });

        $auditLogService->record('stock_transfer.posted', null, "Stock transfer {$referenceNo} posted.", [
            'reference_no' => $referenceNo,
            'from_store_id' => $fromStoreId,
            'to_store_id' => $toStoreId,
        ]);

        return redirect()
            ->route('stock.transfers.show', $referenceNo)
            ->with('status', "Stock transfer {$referenceNo} posted successfully.")
            ->with('auto_print_document', true);
    }

    public function adjustmentCreate(Request $request): View
    {
        $currentStore = auth()->user()?->defaultStore;
        $selectedUnit = $this->resolveSelectedUnit(
            $request->integer('product_unit_id'),
            $request->integer('product_id')
        );
        $prefill = [
            'adjustment_type' => $request->string('adjustment_type')->value() === 'increase' ? 'increase' : 'decrease',
            'items' => $selectedUnit ? [[
                'product_unit_id' => $selectedUnit->id,
                'quantity' => 1,
            ]] : [],
        ];

        return view('stock.adjustment', [
            'currentStore' => $currentStore ?? Store::query()->orderBy('name')->first(['id', 'name']),
            'productUnits' => ProductUnit::query()
                ->with('product:id,name')
                ->where('is_active', true)
                ->orderBy('product_id')
                ->orderBy('unit_name')
                ->get(['id', 'product_id', 'unit_name', 'cost_price', 'barcode', 'part_number']),
            'prefillAdjustment' => $prefill,
            'returnTo' => $this->safeReturnTo($request->input('return_to')),
        ]);
    }

    public function countCreate(Request $request): View
    {
        $currentStore = auth()->user()?->defaultStore;
        $draftCount = null;
        $showStatus = in_array($request->string('show_status')->value(), ['all', 'pending', 'counted'], true)
            ? $request->string('show_status')->value()
            : 'pending';
        $countFocus = in_array($request->string('count_focus')->value(), ['all', 'low_stock', 'zero_or_negative'], true)
            ? $request->string('count_focus')->value()
            : 'all';
        $requestedPerPage = $request->integer('per_page');
        $perPage = $requestedPerPage >= 1 && $requestedPerPage <= 200
            ? $requestedPerPage
            : 50;

        if ($request->integer('draft_id') > 0) {
            $draftCount = StockCount::query()
                ->with('items')
                ->where('status', 'draft')
                ->findOrFail($request->integer('draft_id'));
        }

        $selectedStoreId = $draftCount?->store_id
            ?? ($request->integer('store_id') > 0
                ? $request->integer('store_id')
                : ($currentStore?->id ?? (int) Store::query()->orderBy('name')->value('id')));
        $selectedAssignedUserId = $draftCount?->assigned_user_id
            ?? ($request->integer('assigned_user_id') > 0 ? $request->integer('assigned_user_id') : auth()->id());

        $stockRequest = Request::create('/stock/counts/create', 'GET', [
            'q' => trim((string) $request->query('q', '')),
            'store_id' => $selectedStoreId,
            'category_id' => $request->integer('category_id'),
            'count_focus' => $countFocus,
        ]);

        [$stores, $categories, $filters] = $this->stockReferenceData($stockRequest);
        $savedItems = $draftCount?->items?->keyBy('product_unit_id') ?? collect();
        $savedUnitIds = $savedItems->keys()->map(fn ($id) => (int) $id)->values();
        $query = $this->stockRowsQuery($stockRequest);

        if ($showStatus === 'counted') {
            if ($savedUnitIds->isEmpty()) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('product_units.id', $savedUnitIds->all());
            }
        } elseif ($showStatus === 'pending' && $savedUnitIds->isNotEmpty()) {
            $query->whereNotIn('product_units.id', $savedUnitIds->all());
        }

        $rows = $query
            ->paginate($perPage, ['*'], 'page', max($request->integer('page', 1), 1))
            ->appends([
                'draft_id' => $draftCount?->id,
                'store_id' => $selectedStoreId,
                'q' => $filters['q'],
                'category_id' => $filters['category_id'],
                'count_focus' => $filters['count_focus'],
                'show_status' => $showStatus,
                'per_page' => $perPage,
            ]);

        $rows->getCollection()->transform(function ($row) {
            $row->stock_value = round((float) $row->balance_qty * (float) $row->cost_price, 2);

            return $row;
        });

        return view('stock.count', [
            'rows' => $rows,
            'stores' => $stores,
            'categories' => $categories,
            'filters' => $filters,
            'currentStore' => $currentStore,
            'selectedStore' => $stores->firstWhere('id', $filters['store_id']),
            'draftCount' => $draftCount,
            'savedItems' => $savedItems,
            'showStatus' => $showStatus,
            'perPage' => $perPage,
            'perPageOptions' => [50, 100, 200],
            'countStaff' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'selectedAssignedUserId' => $selectedAssignedUserId,
            'defaultSectionName' => old('section_name', $draftCount?->section_name),
            'countFocus' => $countFocus,
        ]);
    }

    public function adjustmentStore(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService,
        StockAvailabilityService $stockAvailabilityService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'adjustment_date' => ['required', 'date'],
            'store_id' => ['required', 'exists:stores,id'],
            'adjustment_type' => ['required', 'in:increase,decrease'],
            'remarks' => ['nullable', 'string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
            'items' => ['required', 'array'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $items = collect($validated['items'])
            ->filter(fn (array $item) => ! empty($item['product_unit_id']) && ! empty($item['quantity']))
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'Add at least one item to adjust.',
            ]);
        }

        $units = ProductUnit::query()->whereIn('id', $items->pluck('product_unit_id'))->get()->keyBy('id');
        $referenceNo = $documentNumberService->make('stock_adjustment', $validated['adjustment_date']);
        $storeId = $storeAssignmentService->resolveStoreId((int) $validated['store_id'], $request->user(), app(AccessService::class));

        DB::transaction(function () use ($validated, $items, $units, $referenceNo, $storeId, $stockAvailabilityService) {
            foreach ($items as $index => $item) {
                /** @var ProductUnit $unit */
                $unit = $units->get((int) $item['product_unit_id']);
                $quantity = max((int) $item['quantity'], 1);
                $referenceId = abs(crc32($referenceNo.'-'.$index));

                if ($validated['adjustment_type'] === 'decrease') {
                    $stockAvailabilityService->ensureAvailable($storeId, $unit, $quantity, 'items');
                }

                InventoryTransaction::create([
                    'transaction_date' => $validated['adjustment_date'],
                    'store_id' => $storeId,
                    'product_id' => $unit->product_id,
                    'product_unit_id' => $unit->id,
                    'reference_type' => 'stock_adjustment',
                    'reference_id' => $referenceId,
                    'reference_no' => $referenceNo,
                    'movement_type' => $validated['adjustment_type'] === 'increase' ? 'adjustment_in' : 'adjustment_out',
                    'quantity_in' => $validated['adjustment_type'] === 'increase' ? $quantity : 0,
                    'quantity_out' => $validated['adjustment_type'] === 'decrease' ? $quantity : 0,
                    'unit_cost' => $unit->cost_price,
                    'remarks' => $validated['remarks'] ?? null,
                ]);
            }
        });

        $auditLogService->record('stock_adjustment.posted', null, "Stock adjustment {$referenceNo} posted.", [
            'reference_no' => $referenceNo,
            'store_id' => $storeId,
            'adjustment_type' => $validated['adjustment_type'],
        ]);

        $returnTo = $this->safeReturnTo($validated['return_to'] ?? null);

        if ($returnTo) {
            return redirect()
                ->to($returnTo)
                ->with('status', "Stock adjustment {$referenceNo} posted successfully.");
        }

        return redirect()
            ->route('stock.adjustments.show', $referenceNo)
            ->with('status', "Stock adjustment {$referenceNo} posted successfully.")
            ->with('auto_print_document', true);
    }

    public function countStore(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService,
        StockAvailabilityService $stockAvailabilityService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:draft,post'],
            'stock_count_id' => ['nullable', 'exists:stock_counts,id'],
            'count_date' => ['required', 'date'],
            'store_id' => ['required', 'exists:stores,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'section_name' => ['nullable', 'string', 'max:120'],
            'remarks' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer'],
            'count_focus' => ['nullable', 'in:all,low_stock,zero_or_negative'],
            'show_status' => ['nullable', 'in:all,pending,counted'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'items' => ['required', 'array'],
            'items.*.product_unit_id' => ['nullable', 'exists:product_units,id'],
            'items.*.physical_count' => ['nullable', 'integer', 'min:0'],
            'items.*.is_counted' => ['nullable', 'boolean'],
        ]);

        $sheetUnitIds = collect($validated['items'])
            ->pluck('product_unit_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $countedItems = collect($validated['items'])
            ->filter(fn (array $item) => ! empty($item['product_unit_id']) && ! empty($item['is_counted']) && array_key_exists('physical_count', $item))
            ->values();

        if ($countedItems->isEmpty()) {
            $message = $validated['action'] === 'draft'
                ? 'Tick at least one line as counted before saving progress.'
                : 'Tick at least one line as counted before posting the physical count.';

            throw ValidationException::withMessages([
                'items' => $message,
            ]);
        }

        $storeId = $storeAssignmentService->resolveStoreId((int) $validated['store_id'], $request->user(), app(AccessService::class));

        $unitIds = $countedItems->pluck('product_unit_id')->map(fn ($id) => (int) $id)->unique()->values();
        $systemRows = $this->stockSnapshot($storeId, $unitIds->all());
        $units = ProductUnit::query()
            ->with('product:id,name')
            ->whereIn('id', $unitIds)
            ->get()
            ->keyBy('id');

        $trackedItems = $countedItems->map(function (array $item) use ($systemRows, $units) {
            $unitId = (int) $item['product_unit_id'];
            /** @var ProductUnit|null $unit */
            $unit = $units->get($unitId);
            $snapshot = $systemRows->get($unitId);

            if (! $unit || ! $snapshot) {
                return null;
            }

            $systemQty = (int) round((float) $snapshot->balance_qty);
            $physicalQty = max((int) ($item['physical_count'] ?? 0), 0);
            $varianceQty = $physicalQty - $systemQty;

            return [
                'unit' => $unit,
                'system_qty' => $systemQty,
                'physical_qty' => $physicalQty,
                'variance_qty' => $varianceQty,
                'quantity_adjusted' => abs($varianceQty),
            ];
        })->filter()->values();

        $varianceItems = $trackedItems->filter(fn (array $item) => $item['variance_qty'] !== 0)->values();
        $existingCount = null;

        if (! empty($validated['stock_count_id'])) {
            $existingCount = StockCount::query()
                ->with('items.productUnit:id,cost_price')
                ->findOrFail((int) $validated['stock_count_id']);
        }

        $currentRows = $trackedItems->map(function (array $item) {
            /** @var ProductUnit $unit */
            $unit = $item['unit'];

            return [
                'product_id' => $unit->product_id,
                'product_unit_id' => $unit->id,
                'system_qty' => $item['system_qty'],
                'physical_qty' => $item['physical_qty'],
                'variance_qty' => $item['variance_qty'],
                'quantity_adjusted' => $item['quantity_adjusted'],
                'unit_cost' => (float) $unit->cost_price,
            ];
        });

        $preservedRows = $existingCount
            ? $existingCount->items
                ->reject(fn ($item) => $sheetUnitIds->contains((int) $item->product_unit_id))
                ->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'product_unit_id' => $item->product_unit_id,
                    'system_qty' => (int) $item->system_qty,
                    'physical_qty' => (int) $item->physical_qty,
                    'variance_qty' => (int) $item->variance_qty,
                    'quantity_adjusted' => (int) $item->quantity_adjusted,
                    'unit_cost' => (float) ($item->productUnit?->cost_price ?? 0),
                ])
            : collect();

        $mergedRows = $preservedRows->concat($currentRows)->values();
        $mergedVarianceRows = $mergedRows->filter(fn (array $item) => (int) $item['variance_qty'] !== 0)->values();
        $postingUnits = ProductUnit::query()
            ->with('product:id,name')
            ->whereIn('id', $mergedVarianceRows->pluck('product_unit_id')->unique()->all())
            ->get()
            ->keyBy('id');

        $count = DB::transaction(function () use (
            $validated,
            $mergedRows,
            $mergedVarianceRows,
            $existingCount,
            $documentNumberService,
            $storeId,
            $stockAvailabilityService,
            $postingUnits
        ) {
            $count = $existingCount;

            if ($count) {
                if ($count->status !== 'draft') {
                    throw ValidationException::withMessages([
                        'stock_count_id' => 'Only draft stock counts can be updated.',
                    ]);
                }

                $count->update([
                    'count_date' => $validated['count_date'],
                    'store_id' => $storeId,
                    'user_id' => auth()->id(),
                    'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                    'section_name' => $validated['section_name'] ?? null,
                    'remarks' => $validated['remarks'] ?? null,
                    'line_count' => $mergedRows->count(),
                    'total_variance_qty' => (int) $mergedRows->sum('quantity_adjusted'),
                    'status' => $validated['action'] === 'post' ? 'posted' : 'draft',
                ]);

                $count->items()->delete();
            } else {
                $count = StockCount::query()->create([
                    'count_no' => $documentNumberService->make('stock_count', $validated['count_date']),
                    'count_date' => $validated['count_date'],
                    'store_id' => $storeId,
                    'user_id' => auth()->id(),
                    'assigned_user_id' => $validated['assigned_user_id'] ?? null,
                    'section_name' => $validated['section_name'] ?? null,
                    'remarks' => $validated['remarks'] ?? null,
                    'line_count' => $mergedRows->count(),
                    'total_variance_qty' => (int) $mergedRows->sum('quantity_adjusted'),
                    'status' => $validated['action'] === 'post' ? 'posted' : 'draft',
                ]);
            }

            foreach ($mergedRows as $item) {
                $count->items()->create([
                    'product_id' => $item['product_id'],
                    'product_unit_id' => $item['product_unit_id'],
                    'system_qty' => $item['system_qty'],
                    'physical_qty' => $item['physical_qty'],
                    'variance_qty' => $item['variance_qty'],
                    'quantity_adjusted' => $item['quantity_adjusted'],
                ]);
            }

            if ($validated['action'] === 'post') {
                foreach ($mergedVarianceRows as $item) {
                    if ((int) $item['variance_qty'] < 0) {
                        $unit = $postingUnits->get((int) $item['product_unit_id']);
                        if ($unit) {
                            $stockAvailabilityService->ensureAvailable(
                                $storeId,
                                $unit,
                                (int) $item['quantity_adjusted'],
                                'items',
                                "{$unit->product?->name} - {$unit->unit_name} no longer has enough stock to post this count reduction. Refresh the count and try again."
                            );
                        }
                    }

                    InventoryTransaction::create([
                        'transaction_date' => $validated['count_date'],
                        'store_id' => $storeId,
                        'product_id' => $item['product_id'],
                        'product_unit_id' => $item['product_unit_id'],
                        'reference_type' => 'stock_count',
                        'reference_id' => $count->id,
                        'reference_no' => $count->count_no,
                        'movement_type' => $item['variance_qty'] > 0 ? 'count_in' : 'count_out',
                        'quantity_in' => $item['variance_qty'] > 0 ? $item['quantity_adjusted'] : 0,
                        'quantity_out' => $item['variance_qty'] < 0 ? $item['quantity_adjusted'] : 0,
                        'unit_cost' => $item['unit_cost'],
                        'remarks' => $validated['remarks'] ?? null,
                    ]);
                }
            }

            return $count->fresh(['items']);
        });

        if ($validated['action'] === 'draft') {
            $auditLogService->record('stock_count.draft_saved', null, "Physical stock count draft {$count->count_no} saved.", [
                'reference_no' => $count->count_no,
                'store_id' => $storeId,
                'line_count' => $mergedRows->count(),
            ]);

            $redirectParams = [
                'draft_id' => $count->id,
                'store_id' => $storeId,
                'q' => trim((string) ($validated['q'] ?? '')),
                'count_focus' => $validated['count_focus'] ?? 'all',
                'show_status' => 'pending',
            ];

            if (! empty($validated['category_id'])) {
                $redirectParams['category_id'] = (int) $validated['category_id'];
            }
            if (! empty($validated['page']) && (int) $validated['page'] > 1) {
                $redirectParams['page'] = (int) $validated['page'];
            }
            if (! empty($validated['per_page']) && (int) $validated['per_page'] !== 50) {
                $redirectParams['per_page'] = (int) $validated['per_page'];
            }

            return redirect()
                ->route('stock.counts.create', $redirectParams)
                ->with('status', "Draft {$count->count_no} saved. {$trackedItems->count()} line(s) are marked as counted so far.");
        }

        $auditLogService->record('stock_count.posted', null, "Physical stock count {$count->count_no} posted.", [
            'reference_no' => $count->count_no,
            'store_id' => $storeId,
            'line_count' => $mergedRows->count(),
        ]);

        return redirect()
            ->route('stock.counts.show', $count->count_no)
            ->with('status', "Physical stock count {$count->count_no} posted successfully.")
            ->with('auto_print_document', true);
    }

    public function history(ProductUnit $productUnit, Request $request): View
    {
        $storeId = $request->integer('store_id');
        $productUnit->load('product:id,name');

        $transactions = InventoryTransaction::query()
            ->with('store:id,name')
            ->where('product_unit_id', $productUnit->id)
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return view('stock.history', [
            'productUnit' => $productUnit,
            'transactions' => $transactions,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'storeId' => $storeId,
        ]);
    }

    public function historyExport(ProductUnit $productUnit, Request $request, ExcelExportService $excelExportService): BinaryFileResponse
    {
        $storeId = $request->integer('store_id');
        $transactions = InventoryTransaction::query()
            ->with('store:id,name')
            ->where('product_unit_id', $productUnit->id)
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get();

        return $excelExportService->download("stock-history-{$productUnit->id}.xlsx", [
            'Date', 'Store', 'Reference', 'Movement', 'In', 'Out', 'Remarks',
        ], $transactions->map(fn ($transaction) => [
            optional($transaction->transaction_date)->format('Y-m-d'),
            $transaction->store?->name,
            $transaction->reference_no,
            $transaction->movement_type,
            $transaction->quantity_in,
            $transaction->quantity_out,
            $transaction->remarks,
        ]));
    }

    public function transferPrint(string $referenceNo): View
    {
        return view('stock.transfer_print', $this->transferDocumentData($referenceNo));
    }

    public function adjustmentPrint(string $referenceNo): View
    {
        return view('stock.adjustment_print', $this->adjustmentDocumentData($referenceNo));
    }

    public function countPrint(string $referenceNo): View
    {
        return view('stock.count_print', $this->countDocumentData($referenceNo));
    }

    public function transferShow(string $referenceNo): View
    {
        return view('stock.transfer_show', $this->transferDocumentData($referenceNo));
    }

    public function adjustmentShow(string $referenceNo): View
    {
        return view('stock.adjustment_show', $this->adjustmentDocumentData($referenceNo));
    }

    public function countShow(string $referenceNo): View
    {
        return view('stock.count_show', $this->countDocumentData($referenceNo));
    }

    private function transferDocumentData(string $referenceNo): array
    {
        $rows = InventoryTransaction::query()
            ->with('store:id,name', 'product:id,name', 'productUnit:id,unit_name')
            ->where('reference_type', 'stock_transfer')
            ->where('reference_no', $referenceNo)
            ->orderBy('id')
            ->get();

        abort_if($rows->isEmpty(), 404);

        return [
            'referenceNo' => $referenceNo,
            'transferDate' => Carbon::parse($rows->first()->transaction_date),
            'fromStore' => $rows->firstWhere('movement_type', 'transfer_out')?->store,
            'toStore' => $rows->firstWhere('movement_type', 'transfer_in')?->store,
            'rows' => $rows->where('movement_type', 'transfer_out')->values(),
            'remarks' => $rows->first()?->remarks,
        ];
    }

    private function adjustmentDocumentData(string $referenceNo): array
    {
        $rows = InventoryTransaction::query()
            ->with('store:id,name', 'product:id,name', 'productUnit:id,unit_name')
            ->where('reference_type', 'stock_adjustment')
            ->where('reference_no', $referenceNo)
            ->orderBy('id')
            ->get();

        abort_if($rows->isEmpty(), 404);

        return [
            'referenceNo' => $referenceNo,
            'adjustmentDate' => Carbon::parse($rows->first()->transaction_date),
            'store' => $rows->first()->store,
            'rows' => $rows,
            'movementType' => $rows->first()->movement_type,
            'remarks' => $rows->first()?->remarks,
        ];
    }

    private function countDocumentData(string $referenceNo): array
    {
        $count = StockCount::query()
            ->with([
                'store:id,name',
                'user:id,name',
                'assignedUser:id,name',
                'items.product:id,name',
                'items.productUnit:id,unit_name',
            ])
            ->where('count_no', $referenceNo)
            ->firstOrFail();

        return [
            'stockCount' => $count,
            'referenceNo' => $count->count_no,
            'countDate' => Carbon::parse($count->count_date),
            'store' => $count->store,
            'countedBy' => $count->user,
            'assignedTo' => $count->assignedUser,
            'rows' => $count->items,
            'remarks' => $count->remarks,
        ];
    }

    private function stockSnapshot(int $storeId, array $productUnitIds = []): \Illuminate\Support\Collection
    {
        return DB::table('product_units')
            ->join('products', 'products.id', '=', 'product_units.product_id')
            ->leftJoin('inventory_transactions', function ($join) use ($storeId) {
                $join->on('inventory_transactions.product_unit_id', '=', 'product_units.id')
                    ->where('inventory_transactions.store_id', '=', $storeId);
            })
            ->selectRaw('
                product_units.id,
                product_units.product_id,
                product_units.cost_price,
                COALESCE(SUM(inventory_transactions.quantity_in), 0) - COALESCE(SUM(inventory_transactions.quantity_out), 0) as balance_qty
            ')
            ->when($productUnitIds !== [], fn ($query) => $query->whereIn('product_units.id', $productUnitIds))
            ->groupBy('product_units.id', 'product_units.product_id', 'product_units.cost_price')
            ->get()
            ->keyBy('id');
    }

    private function resolveSelectedUnit(int $productUnitId = 0, int $productId = 0): ?ProductUnit
    {
        if ($productUnitId > 0) {
            return ProductUnit::query()
                ->with('product:id,name')
                ->where('is_active', true)
                ->find($productUnitId, ['id', 'product_id', 'unit_name', 'cost_price', 'barcode', 'part_number']);
        }

        if ($productId > 0) {
            return ProductUnit::query()
                ->with('product:id,name')
                ->where('product_id', $productId)
                ->where('is_active', true)
                ->orderByDesc('is_pos_unit')
                ->orderBy('unit_name')
                ->first(['id', 'product_id', 'unit_name', 'cost_price', 'barcode', 'part_number']);
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

    private function stockRows(Request $request, bool $reorderOnly = false): array
    {
        [$stores, $categories, $filters] = $this->stockReferenceData($request);
        $query = $this->stockRowsQuery($request, $reorderOnly);

        $rows = collect($query->get())->map(function ($row) {
            $row->stock_value = round((float) $row->balance_qty * (float) $row->cost_price, 2);

            return $row;
        });

        return [
            $rows,
            $stores,
            $categories,
            $filters,
        ];
    }

    private function stockRowsQuery(Request $request, bool $reorderOnly = false)
    {
        $search = trim((string) $request->string('q'));
        $storeId = $request->integer('store_id');
        $categoryId = $request->integer('category_id');
        $countFocus = trim((string) $request->string('count_focus'));

        $query = DB::table('product_units')
            ->join('products', 'products.id', '=', 'product_units.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoin('inventory_transactions', function ($join) use ($storeId) {
                $join->on('inventory_transactions.product_unit_id', '=', 'product_units.id');

                if ($storeId > 0) {
                    $join->where('inventory_transactions.store_id', '=', $storeId);
                }
            })
            ->selectRaw('
                product_units.id,
                products.name as product_name,
                products.code as product_code,
                product_units.unit_name,
                product_units.cost_price,
                product_units.selling_price,
                products.reorder_level,
                categories.name as category_name,
                COALESCE(SUM(inventory_transactions.quantity_in), 0) as quantity_in,
                COALESCE(SUM(inventory_transactions.quantity_out), 0) as quantity_out,
                COALESCE(SUM(inventory_transactions.quantity_in), 0) - COALESCE(SUM(inventory_transactions.quantity_out), 0) as balance_qty
            ')
            ->where('product_units.is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('products.name', 'like', "%{$search}%")
                        ->orWhere('products.code', 'like', "%{$search}%")
                        ->orWhere('product_units.unit_name', 'like', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('products.category_id', $categoryId))
            ->groupBy(
                'product_units.id',
                'products.name',
                'products.code',
                'product_units.unit_name',
                'product_units.cost_price',
                'product_units.selling_price',
                'products.reorder_level',
                'categories.name'
            )
            ->orderBy('products.name');

        if ($reorderOnly) {
            $query->havingRaw('balance_qty <= reorder_level')->havingRaw('reorder_level > 0');
        }

        if ($countFocus === 'low_stock') {
            $query->havingRaw('balance_qty <= reorder_level')->havingRaw('reorder_level > 0');
        } elseif ($countFocus === 'zero_or_negative') {
            $query->havingRaw('balance_qty <= 0');
        }

        return $query;
    }

    private function stockReferenceData(Request $request): array
    {
        return [
            Store::query()->orderBy('name')->get(['id', 'name']),
            Category::query()->orderBy('name')->get(['id', 'name']),
            [
                'q' => trim((string) $request->string('q')),
                'store_id' => $request->integer('store_id'),
                'category_id' => $request->integer('category_id'),
                'count_focus' => in_array($request->string('count_focus')->value(), ['all', 'low_stock', 'zero_or_negative'], true)
                    ? $request->string('count_focus')->value()
                    : 'all',
            ],
        ];
    }
}
