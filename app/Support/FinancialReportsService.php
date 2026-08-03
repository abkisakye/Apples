<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FinancialReportsService
{
    private const BULK_UNIT_KEYWORDS = [
        'bag',
        'bags',
        'box',
        'boxes',
        'carton',
        'cartons',
        'case',
        'crate',
        'packet',
        'packets',
        'sack',
        'sacks',
    ];

    public function __construct(
        private ProductUnitConversionService $conversionService,
        private StockDisplayService $stockDisplayService
    ) {
    }

    public function stockValuation(Request $request): array
    {
        $storeId = $request->integer('store_id');
        $costFilter = (string) $request->query('cost_source', 'all');
        $includeZero = $request->boolean('include_zero_stock');
        $products = $this->filteredProducts($request)->get();
        $stores = Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])->keyBy('id');
        $balances = $this->baseBalances($products->pluck('id')->all(), $storeId);
        $costs = $this->estimatedBaseCosts($products->pluck('id')->all());

        $rows = $products->flatMap(function (Product $product) use ($balances, $costs, $stores, $storeId, $includeZero) {
            $productBalances = $balances->get($product->id, collect());

            if ($productBalances->isEmpty()) {
                $productBalances = collect([(object) [
                    'store_id' => $storeId ?: null,
                    'base_balance' => 0.0,
                ]]);
            }

            return $productBalances
                ->when(! $includeZero, fn (Collection $rows) => $rows->filter(fn ($balance) => (float) $balance->base_balance > 0))
                ->map(function ($balance) use ($product, $costs, $stores) {
                    $baseUnit = $this->conversionService->baseUnitForProduct($product);
                    $baseUnitLabel = $product->base_unit_label ?: $baseUnit?->unit_name ?: 'base unit(s)';
                    $baseBalance = round((float) $balance->base_balance, 3);
                    $cost = $costs[$product->id] ?? ['cost' => 0.0, 'source' => 'Missing cost'];
                    $conversionReview = $this->hasPossibleConversionReview($product->units);
                    $missingCost = (float) $cost['cost'] <= 0;
                    $warnings = collect([
                        $missingCost ? 'Missing cost' : null,
                        $conversionReview ? 'Possible Pack Conversion Review' : null,
                    ])->filter()->values();

                    return (object) [
                        'product' => $product,
                        'store_name' => $stores[$balance->store_id]->name ?? 'All stores',
                        'base_balance' => $baseBalance,
                        'base_stock_label' => $this->stockDisplayService->formatQuantityWithUnit($baseBalance, $baseUnitLabel, $baseUnit),
                        'base_unit_label' => $baseUnitLabel,
                        'estimated_cost_per_base_unit' => round((float) $cost['cost'], 2),
                        'estimated_stock_value' => round($baseBalance * (float) $cost['cost'], 2),
                        'cost_source' => $cost['source'],
                        'missing_cost' => $missingCost,
                        'zero_stock' => $baseBalance <= 0,
                        'conversion_review' => $conversionReview,
                        'warning_label' => $warnings->isEmpty() ? 'OK' : $warnings->implode(' / '),
                    ];
                });
        })->values();

        $rows = $rows
            ->when($costFilter === 'missing', fn (Collection $rows) => $rows->filter(fn ($row) => $row->missing_cost))
            ->when($costFilter === 'has', fn (Collection $rows) => $rows->filter(fn ($row) => ! $row->missing_cost))
            ->values();

        return [
            'rows' => $rows,
            'summary' => [
                'total_estimated_stock_value' => round((float) $rows->sum('estimated_stock_value'), 2),
                'products_with_stock' => $rows->filter(fn ($row) => $row->base_balance > 0)->pluck('product.id')->unique()->count(),
                'products_missing_cost' => $rows->filter(fn ($row) => $row->missing_cost)->pluck('product.id')->unique()->count(),
                'products_with_zero_stock' => $rows->filter(fn ($row) => $row->zero_stock)->pluck('product.id')->unique()->count(),
                'conversion_review_count' => $rows->filter(fn ($row) => $row->conversion_review)->pluck('product.id')->unique()->count(),
            ],
        ];
    }

    public function priceMargins(Request $request): array
    {
        $status = (string) $request->query('status', 'all');
        $products = $this->filteredProducts($request)->get();

        $rows = $products
            ->flatMap(fn (Product $product) => $product->units->map(fn (ProductUnit $unit) => $this->priceMarginRow($product, $unit)))
            ->when($status !== 'all', fn (Collection $rows) => $rows->filter(fn ($row) => $this->matchesMarginStatus($row, $status)))
            ->values();

        return [
            'rows' => $rows,
            'summary' => [
                'total_product_units' => $rows->count(),
                'missing_cost' => $rows->where('status_key', 'missing_cost')->count(),
                'zero_selling_price' => $rows->filter(fn ($row) => $row->selling_price <= 0)->count(),
                'selling_below_cost' => $rows->where('status_key', 'selling_below_cost')->count(),
                'healthy_margin' => $rows->where('status_key', 'healthy_margin')->count(),
                'conversion_review_count' => $rows->where('conversion_review', true)->count(),
            ],
        ];
    }

    private function filteredProducts(Request $request)
    {
        $search = trim((string) $request->query('q', $request->query('search', '')));
        $categoryId = $request->integer('category_id');

        return Product::query()
            ->with(['category:id,name', 'baseProductUnit', 'units' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByDesc('conversion_factor')
                ->orderBy('unit_name')])
            ->where('is_active', true)
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('units', fn ($unit) => $unit
                            ->where('unit_name', 'like', "%{$search}%")
                            ->orWhere('barcode', 'like', "%{$search}%")
                            ->orWhere('part_number', 'like', "%{$search}%"));
                });
            })
            ->orderBy('name');
    }

    private function baseBalances(array $productIds, int $storeId = 0): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return DB::table('inventory_transactions')
            ->leftJoin('product_units', 'product_units.id', '=', 'inventory_transactions.product_unit_id')
            ->whereIn('inventory_transactions.product_id', $productIds)
            ->when($storeId > 0, fn ($query) => $query->where('inventory_transactions.store_id', $storeId))
            ->selectRaw('
                inventory_transactions.product_id,
                inventory_transactions.store_id,
                COALESCE(SUM(
                    CASE
                        WHEN inventory_transactions.base_quantity_in != 0 THEN inventory_transactions.base_quantity_in
                        ELSE inventory_transactions.quantity_in * COALESCE(NULLIF(inventory_transactions.conversion_factor_snapshot, 0), NULLIF(product_units.conversion_factor, 0), 1)
                    END
                ), 0)
                -
                COALESCE(SUM(
                    CASE
                        WHEN inventory_transactions.base_quantity_out != 0 THEN inventory_transactions.base_quantity_out
                        ELSE inventory_transactions.quantity_out * COALESCE(NULLIF(inventory_transactions.conversion_factor_snapshot, 0), NULLIF(product_units.conversion_factor, 0), 1)
                    END
                ), 0) as base_balance
            ')
            ->groupBy('inventory_transactions.product_id', 'inventory_transactions.store_id')
            ->get()
            ->groupBy('product_id');
    }

    private function estimatedBaseCosts(array $productIds): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        $latestCosts = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('product_units', 'product_units.id', '=', 'purchase_items.product_unit_id')
            ->where('purchases.status', 'posted')
            ->whereIn('purchase_items.product_id', $productIds)
            ->where('purchase_items.unit_cost', '>', 0)
            ->selectRaw('
                purchase_items.product_id,
                purchase_items.unit_cost / COALESCE(NULLIF(purchase_items.conversion_factor_snapshot, 0), NULLIF(product_units.conversion_factor, 0), 1) as base_cost,
                ROW_NUMBER() OVER (PARTITION BY purchase_items.product_id ORDER BY purchases.purchase_date DESC, purchase_items.id DESC) as row_no
            ')
            ->get()
            ->where('row_no', 1)
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => [
                'cost' => round((float) $row->base_cost, 2),
                'source' => 'Latest purchase cost',
            ]]);

        $weightedCosts = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->leftJoin('product_units', 'product_units.id', '=', 'purchase_items.product_unit_id')
            ->where('purchases.status', 'posted')
            ->whereIn('purchase_items.product_id', $productIds)
            ->where('purchase_items.unit_cost', '>', 0)
            ->selectRaw('
                purchase_items.product_id,
                COALESCE(SUM(purchase_items.line_total), 0) as total_cost,
                COALESCE(SUM(
                    CASE
                        WHEN purchase_items.base_quantity IS NOT NULL AND purchase_items.base_quantity > 0 THEN purchase_items.base_quantity
                        ELSE purchase_items.quantity * COALESCE(NULLIF(purchase_items.conversion_factor_snapshot, 0), NULLIF(product_units.conversion_factor, 0), 1)
                    END
                ), 0) as total_base_quantity
            ')
            ->groupBy('purchase_items.product_id')
            ->get()
            ->filter(fn ($row) => (float) $row->total_base_quantity > 0)
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => [
                'cost' => round((float) $row->total_cost / (float) $row->total_base_quantity, 2),
                'source' => 'Weighted purchase cost',
            ]]);

        $unitCosts = Product::query()
            ->with(['units' => fn ($query) => $query->where('is_active', true), 'baseProductUnit'])
            ->whereIn('id', $productIds)
            ->get()
            ->mapWithKeys(function (Product $product) {
                $baseUnit = $this->conversionService->baseUnitForProduct($product);
                $unit = $baseUnit && (float) $baseUnit->cost_price > 0
                    ? $baseUnit
                    : $product->units->first(fn (ProductUnit $unit) => (float) $unit->cost_price > 0);

                if (! $unit) {
                    return [$product->id => ['cost' => 0.0, 'source' => 'Missing cost']];
                }

                return [$product->id => [
                    'cost' => $this->conversionService->costPerBaseUnit((float) $unit->cost_price, $unit),
                    'source' => 'Configured product unit cost',
                ]];
            });

        return collect($productIds)->mapWithKeys(fn ($id) => [(int) $id => $latestCosts[$id] ?? $weightedCosts[$id] ?? $unitCosts[$id] ?? [
            'cost' => 0.0,
            'source' => 'Missing cost',
        ]]);
    }

    private function priceMarginRow(Product $product, ProductUnit $unit): object
    {
        $costPrice = (float) $unit->cost_price;
        $sellingPrice = (float) $unit->selling_price;
        $hasCost = $costPrice > 0;
        $marginAmount = $hasCost ? round($sellingPrice - $costPrice, 2) : null;
        $marginPercent = $hasCost && $sellingPrice > 0 ? round(($marginAmount / $sellingPrice) * 100, 2) : null;
        $conversionReview = $this->isSuspiciousBulkUnit($unit);

        [$statusKey, $statusLabel] = match (true) {
            ! $hasCost => ['missing_cost', 'Missing cost'],
            $sellingPrice <= 0 => ['zero_selling_price', 'Zero selling price'],
            $sellingPrice < $costPrice => ['selling_below_cost', 'Selling below cost'],
            default => ['healthy_margin', 'Healthy margin'],
        };
        $extraWarnings = collect([
            $statusKey !== 'zero_selling_price' && $sellingPrice <= 0 ? 'Zero selling price' : null,
            $conversionReview ? 'Possible Pack Conversion Review' : null,
        ])->filter();

        return (object) [
            'product' => $product,
            'unit' => $unit,
            'conversion_factor' => $this->conversionService->conversionFactorSnapshot($unit),
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'margin_amount' => $marginAmount,
            'margin_percent' => $marginPercent,
            'status_key' => $statusKey,
            'status_label' => $statusLabel,
            'conversion_review' => $conversionReview,
            'warning_label' => collect([$statusLabel])->merge($extraWarnings)->implode(' / '),
        ];
    }

    private function matchesMarginStatus(object $row, string $status): bool
    {
        return match ($status) {
            'missing_cost' => $row->status_key === 'missing_cost',
            'zero_selling_price' => $row->selling_price <= 0,
            'selling_below_cost' => $row->status_key === 'selling_below_cost',
            'healthy_margin' => $row->status_key === 'healthy_margin',
            'conversion_review' => $row->conversion_review,
            default => true,
        };
    }

    private function hasPossibleConversionReview(Collection $units): bool
    {
        return $units->contains(fn (ProductUnit $unit) => $this->isSuspiciousBulkUnit($unit));
    }

    private function isSuspiciousBulkUnit(ProductUnit $unit): bool
    {
        if ($this->conversionService->conversionFactorSnapshot($unit) !== 1.0) {
            return false;
        }

        $name = Str::lower(trim($unit->unit_name));

        return collect(self::BULK_UNIT_KEYWORDS)->contains(fn (string $keyword) => $name === $keyword || Str::contains($name, $keyword));
    }
}
