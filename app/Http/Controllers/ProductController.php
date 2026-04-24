<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseItem;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function create(): View
    {
        return view('products.create', $this->formData(new Product(['is_active' => true]), collect([$this->defaultUnitRow()])));
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $categoryId = $request->integer('category');
        $supplierId = $request->integer('supplier_id');
        $status = trim((string) $request->string('status'));

        $productsQuery = Product::query()
            ->with(['category:id,name', 'supplier:id,name'])
            ->withCount('units')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('item_group', 'like', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($supplierId > 0, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name');

        $products = (clone $productsQuery)
            ->paginate(20)
            ->withQueryString();

        $categories = Category::query()->orderBy('name')->get(['id', 'name']);
        $suppliers = Supplier::query()->orderBy('name')->get(['id', 'name']);
        $summaryBase = Product::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('item_group', 'like', "%{$search}%");
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($supplierId > 0, fn ($query) => $query->where('supplier_id', $supplierId))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false));

        return view('products.index', [
            'products' => $products,
            'productSummary' => [
                'total' => (clone $summaryBase)->count(),
                'with_code' => (clone $summaryBase)->whereNotNull('code')->count(),
                'linked_suppliers' => (clone $summaryBase)->whereNotNull('supplier_id')->count(),
                'reorder_ready' => (clone $summaryBase)->where('reorder_level', '>', 0)->count(),
            ],
            'categories' => $categories,
            'suppliers' => $suppliers,
            'search' => $search,
            'categoryId' => $categoryId,
            'supplierId' => $supplierId,
            'statusFilter' => $status,
        ]);
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        [$productData, $unitRows] = $this->validateProduct($request);

        $product = DB::transaction(function () use ($productData, $unitRows) {
            $product = Product::query()->create($productData);
            $this->syncUnits($product, $unitRows);

            return $product;
        });

        $auditLogService->record('product.created', $product, "Product {$product->name} created.", [
            'product_id' => $product->id,
            'unit_count' => count($unitRows),
        ]);

        return redirect()
            ->route('products.show', $product)
            ->with('status', "Product {$product->name} saved successfully.");
    }

    public function show(Product $product): View
    {
        $product->load(['category:id,name', 'supplier:id,name,phone,country', 'units']);

        $units = ProductUnit::query()
            ->where('product_id', $product->id)
            ->orderByDesc('is_pos_unit')
            ->orderByDesc('is_active')
            ->orderBy('unit_name')
            ->get();

        $stockSnapshot = InventoryTransaction::query()
            ->selectRaw('product_unit_id, COALESCE(SUM(quantity_in), 0) as quantity_in, COALESCE(SUM(quantity_out), 0) as quantity_out')
            ->where('product_id', $product->id)
            ->groupBy('product_unit_id')
            ->get()
            ->keyBy('product_unit_id');

        $unitRows = $units->map(function (ProductUnit $unit) use ($stockSnapshot) {
            $snapshot = $stockSnapshot->get($unit->id);
            $quantityIn = (int) round((float) ($snapshot?->quantity_in ?? 0));
            $quantityOut = (int) round((float) ($snapshot?->quantity_out ?? 0));
            $balance = $quantityIn - $quantityOut;

            return [
                'unit' => $unit,
                'quantity_in' => $quantityIn,
                'quantity_out' => $quantityOut,
                'balance_qty' => $balance,
                'stock_value' => round($balance * (float) $unit->cost_price, 2),
            ];
        });

        $recentMovements = InventoryTransaction::query()
            ->with(['store:id,name', 'productUnit:id,unit_name'])
            ->where('product_id', $product->id)
            ->latest('transaction_date')
            ->latest('id')
            ->limit(12)
            ->get(['id', 'transaction_date', 'store_id', 'product_unit_id', 'reference_type', 'reference_no', 'movement_type', 'quantity_in', 'quantity_out']);

        $recentSales = SaleItem::query()
            ->with(['sale:id,sale_no,sale_date,store_id', 'sale.store:id,name', 'productUnit:id,unit_name'])
            ->where('product_id', $product->id)
            ->latest('id')
            ->limit(8)
            ->get(['id', 'sale_id', 'product_unit_id', 'quantity', 'line_total']);

        $recentPurchases = PurchaseItem::query()
            ->with(['purchase:id,purchase_no,purchase_date,store_id', 'purchase.store:id,name', 'productUnit:id,unit_name'])
            ->where('product_id', $product->id)
            ->latest('id')
            ->limit(8)
            ->get(['id', 'purchase_id', 'product_unit_id', 'quantity', 'line_total']);

        return view('products.show', [
            'product' => $product,
            'unitRows' => $unitRows,
            'recentMovements' => $recentMovements,
            'recentSales' => $recentSales,
            'recentPurchases' => $recentPurchases,
            'productSummary' => [
                'units' => $units->count(),
                'active_units' => $units->where('is_active', true)->count(),
                'stock_value' => $unitRows->sum('stock_value'),
                'stock_balance_units' => $unitRows->sum('balance_qty'),
            ],
        ]);
    }

    public function edit(Product $product): View
    {
        $product->load('units');

        $unitRows = $product->units
            ->sortByDesc('is_pos_unit')
            ->sortByDesc('is_active')
            ->map(function (ProductUnit $unit) {
                return [
                    'id' => $unit->id,
                    'unit_name' => $unit->unit_name,
                    'conversion_factor' => (float) $unit->conversion_factor,
                    'selling_price' => (float) $unit->selling_price,
                    'cost_price' => (float) $unit->cost_price,
                    'barcode' => $unit->barcode,
                    'part_number' => $unit->part_number,
                    'is_active' => $unit->is_active,
                    'is_pos_unit' => $unit->is_pos_unit,
                ];
            })
            ->values();

        if ($unitRows->isEmpty()) {
            $unitRows = collect([$this->defaultUnitRow()]);
        }

        return view('products.edit', $this->formData($product, $unitRows));
    }

    public function update(Request $request, Product $product, AuditLogService $auditLogService): RedirectResponse
    {
        [$productData, $unitRows] = $this->validateProduct($request, $product);

        DB::transaction(function () use ($product, $productData, $unitRows) {
            $product->update($productData);
            $this->syncUnits($product, $unitRows);
        });

        $auditLogService->record('product.updated', $product, "Product {$product->name} updated.", [
            'product_id' => $product->id,
            'unit_count' => count($unitRows),
        ]);

        return redirect()
            ->route('products.show', $product)
            ->with('status', "Product {$product->name} updated successfully.");
    }

    public function updateStatus(Request $request, Product $product, AuditLogService $auditLogService): RedirectResponse
    {
        $product->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        $auditLogService->record('product.status_updated', $product, "Product {$product->name} status updated.", [
            'product_id' => $product->id,
            'is_active' => $product->is_active,
        ]);

        return redirect()
            ->route('products.index')
            ->with('status', "Product {$product->name} marked as ".($product->is_active ? 'active' : 'inactive').'.');
    }

    private function formData(Product $product, $unitRows): array
    {
        $unitRows = collect($unitRows)->values();
        $defaultUnitIndex = $unitRows->search(fn (array $unit) => (bool) ($unit['is_pos_unit'] ?? false));

        return [
            'product' => $product,
            'unitRows' => $unitRows,
            'defaultUnitIndex' => $defaultUnitIndex === false ? 0 : $defaultUnitIndex,
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
        ];
    }

    private function validateProduct(Request $request, ?Product $product = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('products', 'name')->ignore($product?->id)],
            'code' => ['nullable', 'string', 'max:255', Rule::unique('products', 'code')->ignore($product?->id)],
            'category_id' => ['nullable', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'item_group' => ['nullable', 'string', 'max:255'],
            'base_cost_price' => ['nullable', 'numeric', 'min:0'],
            'reorder_level' => ['nullable', 'integer', 'min:0'],
            'is_vat_applicable' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'units' => ['required', 'array', 'min:1'],
            'units.*.id' => ['nullable', 'integer'],
            'units.*.unit_name' => ['required', 'string', 'max:255'],
            'units.*.conversion_factor' => ['nullable', 'numeric', 'gt:0'],
            'units.*.selling_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'units.*.barcode' => ['nullable', 'string', 'max:255'],
            'units.*.part_number' => ['nullable', 'string', 'max:255'],
            'units.*.is_active' => ['nullable', 'boolean'],
            'default_unit_index' => ['nullable', 'integer', 'min:0'],
        ]);

        $unitRows = collect($validated['units'])
            ->map(function (array $unit) {
                return [
                    'id' => $unit['id'] ?? null,
                    'unit_name' => trim((string) $unit['unit_name']),
                    'conversion_factor' => round((float) ($unit['conversion_factor'] ?? 1), 3),
                    'selling_price' => round((float) ($unit['selling_price'] ?? 0), 2),
                    'cost_price' => round((float) ($unit['cost_price'] ?? 0), 2),
                    'barcode' => blank($unit['barcode'] ?? null) ? null : trim((string) $unit['barcode']),
                    'part_number' => blank($unit['part_number'] ?? null) ? null : trim((string) $unit['part_number']),
                    'is_active' => filter_var($unit['is_active'] ?? true, FILTER_VALIDATE_BOOL),
                ];
            })
            ->filter(fn (array $unit) => $unit['unit_name'] !== '')
            ->values();

        if ($unitRows->isEmpty()) {
            throw ValidationException::withMessages([
                'units' => 'Add at least one selling unit before saving the product.',
            ]);
        }

        $duplicateNames = $unitRows->groupBy(fn (array $unit) => mb_strtolower($unit['unit_name']))->filter(fn ($group) => $group->count() > 1);

        if ($duplicateNames->isNotEmpty()) {
            throw ValidationException::withMessages([
                'units' => 'Each product unit name must be unique within the same product.',
            ]);
        }

        $defaultIndex = min(max((int) ($validated['default_unit_index'] ?? 0), 0), max($unitRows->count() - 1, 0));

        $unitRows = $unitRows->values()->map(function (array $unit, int $index) use ($defaultIndex) {
            $unit['is_pos_unit'] = $index === $defaultIndex;

            return $unit;
        })->all();

        return [[
            'name' => $validated['name'],
            'code' => $validated['code'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'supplier_id' => $validated['supplier_id'] ?? null,
            'item_group' => $validated['item_group'] ?? null,
            'base_cost_price' => round((float) ($validated['base_cost_price'] ?? 0), 2),
            'reorder_level' => (int) ($validated['reorder_level'] ?? 0),
            'is_vat_applicable' => $request->boolean('is_vat_applicable'),
            'notes' => $validated['notes'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ], $unitRows];
    }

    private function syncUnits(Product $product, array $unitRows): void
    {
        $existingUnits = $product->units()->get()->keyBy('id');

        foreach ($unitRows as $unitData) {
            $unitId = $unitData['id'] ?? null;
            $attributes = Arr::except($unitData, ['id']);

            if ($unitId && $existingUnits->has($unitId)) {
                $existingUnits->get($unitId)?->update($attributes);
                continue;
            }

            $product->units()->create($attributes);
        }
    }

    private function defaultUnitRow(): array
    {
        return [
            'unit_name' => '',
            'conversion_factor' => 1,
            'selling_price' => 0,
            'cost_price' => 0,
            'barcode' => null,
            'part_number' => null,
            'is_active' => true,
            'is_pos_unit' => true,
        ];
    }
}
