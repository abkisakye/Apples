<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

    public function grossProfitReport(Request $request): array
    {
        [$fromDate, $toDate, $period] = $this->resolveDateRange($request);
        $storeId = $request->integer('store_id');
        $categoryId = $request->integer('category_id');
        $search = trim((string) $request->query('q', $request->query('search', '')));
        $costStatus = (string) $request->query('cost_status', 'all');

        $items = SaleItem::query()
            ->with([
                'sale:id,sale_no,sale_date,store_id,status',
                'sale.store:id,name',
                'product:id,name,code,category_id',
                'product.category:id,name',
                'productUnit:id,product_id,unit_name,cost_price',
            ])
            ->whereHas('sale', function ($query) use ($fromDate, $toDate, $storeId) {
                $query->posted()
                    ->whereDate('sale_date', '>=', $fromDate)
                    ->whereDate('sale_date', '<=', $toDate)
                    ->when($storeId > 0, fn ($inner) => $inner->where('store_id', $storeId));
            })
            ->when($categoryId > 0, fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->whereHas('product', function ($product) use ($search) {
                        $product->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"));
                    })->orWhereHas('productUnit', fn ($unit) => $unit->where('unit_name', 'like', "%{$search}%"));
                });
            })
            ->get();

        $costMaps = $this->unitCostMaps($items->pluck('product_unit_id')->filter()->unique()->values());
        $lineRows = $items
            ->map(fn (SaleItem $item) => $this->grossProfitLineRow($item, $costMaps))
            ->when($costStatus !== 'all', fn (Collection $rows) => $rows->filter(fn ($row) => $this->matchesGrossProfitCostStatus($row, $costStatus)))
            ->values();

        $summary = $this->profitSummary($lineRows, $fromDate, $toDate);
        $productRows = $this->profitByProduct($lineRows);
        $categoryRows = $this->profitByCategory($lineRows);
        $dailyRows = $this->profitByDate($lineRows);
        $missingCostRows = $lineRows->filter(fn ($row) => ! $row->has_cost)->values();

        $summary['top_profit_product'] = $productRows->first(fn ($row) => $row->has_reliable_margin)?->product_name ?? 'N/A';
        $summary['top_profit_category'] = $categoryRows->first(fn ($row) => $row->has_reliable_margin)?->category_name ?? 'N/A';

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'filters' => [
                'store_id' => $storeId,
                'category_id' => $categoryId,
                'q' => $search,
                'cost_status' => $costStatus,
            ],
            'summary' => $summary,
            'summaryRows' => collect([(object) [
                'label' => $fromDate === $toDate ? Carbon::parse($fromDate)->format('d M Y') : Carbon::parse($fromDate)->format('d M Y').' - '.Carbon::parse($toDate)->format('d M Y'),
                'sales_revenue' => $summary['sales_revenue'],
                'estimated_cogs' => $summary['estimated_cogs'],
                'estimated_gross_profit' => $summary['estimated_gross_profit'],
                'estimated_margin_percent' => $summary['estimated_margin_percent'],
                'missing_cost_lines' => $summary['missing_cost_lines'],
                'warning_label' => $summary['missing_cost_lines'] > 0 ? 'Missing cost review needed' : 'OK',
            ]]),
            'productRows' => $productRows,
            'categoryRows' => $categoryRows,
            'dailyRows' => $dailyRows,
            'missingCostRows' => $missingCostRows,
        ];
    }

    private function grossProfitLineRow(SaleItem $item, Collection $costMaps): object
    {
        $quantity = (float) $item->quantity;
        $unitPrice = (float) $item->unit_price;
        $lineTotal = (float) $item->line_total;
        $revenue = round($lineTotal > 0 ? $lineTotal : $quantity * $unitPrice, 2);
        [$unitCost, $costSource] = $this->resolveSaleItemUnitCost($item, $costMaps);
        $hasCost = $unitCost > 0;
        $estimatedCogs = $hasCost ? round($quantity * $unitCost, 2) : 0.0;
        $estimatedGrossProfit = round($revenue - $estimatedCogs, 2);

        return (object) [
            'sale_id' => (int) $item->sale_id,
            'sale_no' => $item->sale?->sale_no ?? 'Unknown sale',
            'sale_date' => $item->sale?->sale_date?->toDateString() ?? '',
            'store_id' => (int) ($item->sale?->store_id ?? 0),
            'store_name' => $item->sale?->store?->name ?? 'Unassigned store',
            'product_id' => (int) $item->product_id,
            'product_name' => $item->product?->name ?? 'Unknown product',
            'product_code' => $item->product?->code,
            'category_id' => (int) ($item->product?->category_id ?? 0),
            'category_name' => $item->product?->category?->name ?? 'Uncategorised',
            'product_unit_id' => (int) $item->product_unit_id,
            'unit_name' => $item->productUnit?->unit_name ?? 'Unit',
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'sales_revenue' => $revenue,
            'current_cost_price' => (float) ($item->productUnit?->cost_price ?? 0),
            'unit_cost' => $unitCost,
            'estimated_cogs' => $estimatedCogs,
            'estimated_gross_profit' => $estimatedGrossProfit,
            'estimated_margin_percent' => $hasCost && $revenue > 0 ? round(($estimatedGrossProfit / $revenue) * 100, 2) : null,
            'has_cost' => $hasCost,
            'has_reliable_margin' => $hasCost,
            'cost_source' => $costSource,
            'warning_label' => $hasCost ? $costSource : 'Missing cost',
        ];
    }

    private function resolveSaleItemUnitCost(SaleItem $item, Collection $costMaps): array
    {
        $snapshot = (float) $item->cost_price_snapshot;
        if ($snapshot > 0) {
            return [$snapshot, 'Sale cost snapshot'];
        }

        $configuredCost = (float) ($item->productUnit?->cost_price ?? 0);
        if ($configuredCost > 0) {
            return [$configuredCost, 'Product unit cost'];
        }

        $unitId = (int) $item->product_unit_id;
        $latestCost = (float) ($costMaps['latest'][$unitId] ?? 0);
        if ($latestCost > 0) {
            return [$latestCost, 'Latest purchase cost'];
        }

        $weightedCost = (float) ($costMaps['weighted'][$unitId] ?? 0);
        if ($weightedCost > 0) {
            return [$weightedCost, 'Weighted purchase cost'];
        }

        return [0.0, 'Missing cost'];
    }

    private function unitCostMaps(Collection $productUnitIds): Collection
    {
        if ($productUnitIds->isEmpty()) {
            return collect(['latest' => collect(), 'weighted' => collect()]);
        }

        $ids = $productUnitIds->map(fn ($id) => (int) $id)->all();

        $latest = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'posted')
            ->whereIn('purchase_items.product_unit_id', $ids)
            ->where('purchase_items.unit_cost', '>', 0)
            ->orderBy('purchase_items.product_unit_id')
            ->orderByDesc('purchases.purchase_date')
            ->orderByDesc('purchase_items.id')
            ->get(['purchase_items.product_unit_id', 'purchase_items.unit_cost'])
            ->groupBy('product_unit_id')
            ->map(fn (Collection $rows) => (float) $rows->first()->unit_cost);

        $weighted = DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', 'posted')
            ->whereIn('purchase_items.product_unit_id', $ids)
            ->where('purchase_items.unit_cost', '>', 0)
            ->selectRaw('
                purchase_items.product_unit_id,
                COALESCE(SUM(CASE WHEN purchase_items.line_total > 0 THEN purchase_items.line_total ELSE purchase_items.quantity * purchase_items.unit_cost END), 0) as total_cost,
                COALESCE(SUM(purchase_items.quantity), 0) as total_quantity
            ')
            ->groupBy('purchase_items.product_unit_id')
            ->get()
            ->filter(fn ($row) => (float) $row->total_quantity > 0)
            ->mapWithKeys(fn ($row) => [(int) $row->product_unit_id => round((float) $row->total_cost / (float) $row->total_quantity, 2)]);

        return collect(['latest' => $latest, 'weighted' => $weighted]);
    }

    private function profitSummary(Collection $rows, string $fromDate, string $toDate): array
    {
        $revenue = round((float) $rows->sum('sales_revenue'), 2);
        $cogs = round((float) $rows->sum('estimated_cogs'), 2);
        $grossProfit = round($revenue - $cogs, 2);
        $missingCostLines = $rows->filter(fn ($row) => ! $row->has_cost)->count();

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'sales_revenue' => $revenue,
            'estimated_cogs' => $cogs,
            'estimated_gross_profit' => $grossProfit,
            'estimated_margin_percent' => $missingCostLines > 0 || $revenue <= 0 ? null : round(($grossProfit / $revenue) * 100, 2),
            'sales_count' => $rows->pluck('sale_id')->unique()->count(),
            'quantity_sold' => round((float) $rows->sum('quantity'), 3),
            'item_lines_sold' => $rows->count(),
            'missing_cost_lines' => $missingCostLines,
            'missing_cost_revenue' => round((float) $rows->filter(fn ($row) => ! $row->has_cost)->sum('sales_revenue'), 2),
            'top_profit_product' => 'N/A',
            'top_profit_category' => 'N/A',
        ];
    }

    private function profitByProduct(Collection $rows): Collection
    {
        return $rows
            ->groupBy('product_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return $this->profitGroupRow($group, [
                    'product_id' => $first->product_id,
                    'product_name' => $first->product_name,
                    'product_code' => $first->product_code,
                    'category_name' => $first->category_name,
                ]);
            })
            ->sortByDesc('estimated_gross_profit')
            ->values();
    }

    private function profitByCategory(Collection $rows): Collection
    {
        return $rows
            ->groupBy('category_id')
            ->map(function (Collection $group) {
                $first = $group->first();
                $row = $this->profitGroupRow($group, [
                    'category_id' => $first->category_id,
                    'category_name' => $first->category_name,
                ]);
                $row->products_sold = $group->pluck('product_id')->unique()->count();

                return $row;
            })
            ->sortByDesc('estimated_gross_profit')
            ->values();
    }

    private function profitByDate(Collection $rows): Collection
    {
        return $rows
            ->groupBy('sale_date')
            ->map(function (Collection $group, string $date) {
                $row = $this->profitGroupRow($group, [
                    'date' => $date,
                    'sales_count' => $group->pluck('sale_id')->unique()->count(),
                ]);

                return $row;
            })
            ->sortBy('date')
            ->values();
    }

    private function profitGroupRow(Collection $group, array $attributes): object
    {
        $revenue = round((float) $group->sum('sales_revenue'), 2);
        $cogs = round((float) $group->sum('estimated_cogs'), 2);
        $grossProfit = round($revenue - $cogs, 2);
        $missingCostLines = $group->filter(fn ($row) => ! $row->has_cost)->count();
        $costSources = $group->pluck('cost_source')->filter(fn ($source) => $source !== 'Missing cost')->unique()->values();

        return (object) array_merge($attributes, [
            'quantity_sold' => round((float) $group->sum('quantity'), 3),
            'sales_revenue' => $revenue,
            'estimated_cogs' => $cogs,
            'estimated_gross_profit' => $grossProfit,
            'estimated_margin_percent' => $missingCostLines > 0 || $revenue <= 0 ? null : round(($grossProfit / $revenue) * 100, 2),
            'missing_cost_lines' => $missingCostLines,
            'has_reliable_margin' => $missingCostLines === 0 && $revenue > 0,
            'warning_label' => $missingCostLines > 0
                ? 'Missing cost review needed'
                : ($costSources->count() === 1 ? $costSources->first() : 'Mixed cost sources'),
        ]);
    }

    private function matchesGrossProfitCostStatus(object $row, string $status): bool
    {
        return match ($status) {
            'has_cost' => $row->has_cost,
            'missing_cost' => ! $row->has_cost,
            'estimated_cost' => $row->has_cost && $row->cost_source !== 'Sale cost snapshot',
            default => true,
        };
    }

    private function resolveDateRange(Request $request): array
    {
        $period = trim((string) $request->string('period'));
        $today = Carbon::today();

        [$defaultFrom, $defaultTo] = match ($period !== '' ? $period : 'month') {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
        };

        $fromDate = Carbon::parse((string) $request->input('date_from', $request->input('from', $defaultFrom)))->toDateString();
        $toDate = Carbon::parse((string) $request->input('date_to', $request->input('to', $defaultTo)))->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate, $period];
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
