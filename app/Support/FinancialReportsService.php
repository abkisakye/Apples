<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\SaleReturnItem;
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
        $search = trim((string) $request->query('q', $request->query('search', '')));
        $products = $this->filteredProducts($request)->get();

        $rows = $products
            ->flatMap(fn (Product $product) => $product->units->map(fn (ProductUnit $unit) => $this->priceMarginRow($product, $unit)))
            ->when($search !== '', fn (Collection $rows) => $rows->filter(fn ($row) => $this->priceMarginRowMatchesSearch($row, $search)))
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
        $paymentModeId = $request->integer('payment_mode_id');
        $userId = $request->integer('user_id');
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
            ->whereHas('sale', function ($query) use ($fromDate, $toDate, $storeId, $paymentModeId, $userId) {
                $query->posted()
                    ->whereDate('sale_date', '>=', $fromDate)
                    ->whereDate('sale_date', '<=', $toDate)
                    ->when($storeId > 0, fn ($inner) => $inner->where('store_id', $storeId))
                    ->when($paymentModeId > 0, fn ($inner) => $inner->where('payment_mode_id', $paymentModeId))
                    ->when($userId > 0, fn ($inner) => $inner->where('created_by', $userId));
            })
            ->when($categoryId > 0, fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
            ->when($search !== '', fn ($query) => $this->applySaleItemSearch($query, $search))
            ->get();

        $returnItems = SaleReturnItem::query()
            ->with([
                'saleReturn:id,sale_id,return_no,return_date,store_id,status',
                'saleReturn.store:id,name',
                'saleItem:id,cost_price_snapshot,unit_price',
                'product:id,name,code,category_id',
                'product.category:id,name',
                'productUnit:id,product_id,unit_name,cost_price',
            ])
            ->whereHas('saleReturn', function ($query) use ($fromDate, $toDate, $storeId, $paymentModeId) {
                $query->where('status', 'posted')
                    ->whereDate('return_date', '>=', $fromDate)
                    ->whereDate('return_date', '<=', $toDate)
                    ->when($storeId > 0, fn ($inner) => $inner->where('store_id', $storeId))
                    ->when($paymentModeId > 0, fn ($inner) => $inner->where('payment_mode_id', $paymentModeId));
            })
            ->when($userId > 0, fn ($query) => $query->whereHas('saleReturn.sale', fn ($sale) => $sale->where('created_by', $userId)))
            ->when($categoryId > 0, fn ($query) => $query->whereHas('product', fn ($product) => $product->where('category_id', $categoryId)))
            ->when($search !== '', fn ($query) => $this->applySaleReturnItemSearch($query, $search))
            ->get();

        $costMaps = $this->unitCostMaps($items->pluck('product_unit_id')->merge($returnItems->pluck('product_unit_id'))->filter()->unique()->values());
        $saleLineRows = $items
            ->map(fn (SaleItem $item) => $this->grossProfitLineRow($item, $costMaps))
            ->when($costStatus !== 'all', fn (Collection $rows) => $rows->filter(fn ($row) => $this->matchesGrossProfitCostStatus($row, $costStatus)))
            ->values();
        $returnLineRows = $returnItems
            ->map(fn (SaleReturnItem $item) => $this->grossProfitReturnLineRow($item, $costMaps))
            ->when($costStatus !== 'all', fn (Collection $rows) => $rows->filter(fn ($row) => $this->matchesGrossProfitCostStatus($row, $costStatus)))
            ->values();
        $lineRows = $saleLineRows->merge($returnLineRows)->values();

        $summary = $this->profitSummary($lineRows, $fromDate, $toDate);
        $productRows = $this->profitByProduct($lineRows);
        $categoryRows = $this->profitByCategory($lineRows);
        $dailyRows = $this->profitByDate($lineRows);
        $missingCostRows = $saleLineRows->filter(fn ($row) => ! $row->has_cost)->values();
        $expenses = $this->expenseSummary($fromDate, $toDate, $storeId);
        $fundingRows = $this->purchaseFundingSummary($fromDate, $toDate, $storeId);

        $summary['top_profit_product'] = $productRows->first(fn ($row) => $row->has_reliable_margin)?->product_name ?? 'N/A';
        $summary['top_profit_category'] = $categoryRows->first(fn ($row) => $row->has_reliable_margin)?->category_name ?? 'N/A';
        $summary['expense_total'] = $expenses['total'];
        $summary['estimated_net_profit'] = round($summary['net_estimated_gross_profit'] - $expenses['total'], 2);

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'filters' => [
                'store_id' => $storeId,
                'category_id' => $categoryId,
                'payment_mode_id' => $paymentModeId,
                'user_id' => $userId,
                'q' => $search,
                'cost_status' => $costStatus,
            ],
            'summary' => $summary,
            'summaryRows' => collect([(object) [
                'label' => $fromDate === $toDate ? Carbon::parse($fromDate)->format('d M Y') : Carbon::parse($fromDate)->format('d M Y').' - '.Carbon::parse($toDate)->format('d M Y'),
                'sales_revenue' => $summary['sales_revenue'],
                'returned_revenue' => $summary['returned_revenue'],
                'net_sales_revenue' => $summary['net_sales_revenue'],
                'estimated_cogs' => $summary['estimated_cogs'],
                'returned_cogs' => $summary['returned_cogs'],
                'net_estimated_cogs' => $summary['net_estimated_cogs'],
                'estimated_gross_profit' => $summary['estimated_gross_profit'],
                'net_estimated_gross_profit' => $summary['net_estimated_gross_profit'],
                'estimated_margin_percent' => $summary['net_margin_percent'],
                'missing_cost_lines' => $summary['missing_cost_lines'],
                'warning_label' => $summary['missing_cost_lines'] > 0 ? 'Missing cost review needed' : 'OK',
            ]]),
            'productRows' => $productRows,
            'categoryRows' => $categoryRows,
            'dailyRows' => $dailyRows,
            'missingCostRows' => $missingCostRows,
            'expenseRows' => $expenses['rows'],
            'fundingRows' => $fundingRows,
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
            'returned_quantity' => 0.0,
            'unit_price' => $unitPrice,
            'sales_revenue' => $revenue,
            'returned_revenue' => 0.0,
            'net_sales_revenue' => $revenue,
            'current_cost_price' => (float) ($item->productUnit?->cost_price ?? 0),
            'unit_cost' => $unitCost,
            'estimated_cogs' => $estimatedCogs,
            'returned_cogs' => 0.0,
            'net_estimated_cogs' => $estimatedCogs,
            'estimated_gross_profit' => $estimatedGrossProfit,
            'net_estimated_gross_profit' => $estimatedGrossProfit,
            'estimated_margin_percent' => $hasCost && $revenue > 0 ? round(($estimatedGrossProfit / $revenue) * 100, 2) : null,
            'net_margin_percent' => $hasCost && $revenue > 0 ? round(($estimatedGrossProfit / $revenue) * 100, 2) : null,
            'has_cost' => $hasCost,
            'has_reliable_margin' => $hasCost,
            'cost_source' => $costSource,
            'warning_label' => $hasCost ? $costSource : 'Missing cost',
            'line_type' => 'sale',
        ];
    }

    private function grossProfitReturnLineRow(SaleReturnItem $item, Collection $costMaps): object
    {
        $quantity = (float) $item->quantity;
        $unitPrice = (float) $item->unit_price;
        $lineTotal = (float) $item->line_total;
        $revenue = round($lineTotal > 0 ? $lineTotal : $quantity * $unitPrice, 2);
        [$unitCost, $costSource] = $this->resolveSaleReturnItemUnitCost($item, $costMaps);
        $hasCost = $unitCost > 0;
        $returnedCogs = $hasCost ? round($quantity * $unitCost, 2) : 0.0;
        $netGrossProfit = round(0 - $revenue - (0 - $returnedCogs), 2);

        return (object) [
            'sale_id' => (int) ($item->saleReturn?->sale_id ?? 0),
            'sale_no' => $item->saleReturn?->return_no ?? 'Unknown return',
            'sale_date' => $item->saleReturn?->return_date?->toDateString() ?? '',
            'store_id' => (int) ($item->saleReturn?->store_id ?? 0),
            'store_name' => $item->saleReturn?->store?->name ?? 'Unassigned store',
            'product_id' => (int) $item->product_id,
            'product_name' => $item->product?->name ?? 'Unknown product',
            'product_code' => $item->product?->code,
            'category_id' => (int) ($item->product?->category_id ?? 0),
            'category_name' => $item->product?->category?->name ?? 'Uncategorised',
            'product_unit_id' => (int) $item->product_unit_id,
            'unit_name' => $item->productUnit?->unit_name ?? 'Unit',
            'quantity' => 0.0,
            'returned_quantity' => $quantity,
            'unit_price' => $unitPrice,
            'sales_revenue' => 0.0,
            'returned_revenue' => $revenue,
            'net_sales_revenue' => -$revenue,
            'current_cost_price' => (float) ($item->productUnit?->cost_price ?? 0),
            'unit_cost' => $unitCost,
            'estimated_cogs' => 0.0,
            'returned_cogs' => $returnedCogs,
            'net_estimated_cogs' => -$returnedCogs,
            'estimated_gross_profit' => 0.0,
            'net_estimated_gross_profit' => $netGrossProfit,
            'estimated_margin_percent' => null,
            'net_margin_percent' => null,
            'has_cost' => $hasCost,
            'has_reliable_margin' => $hasCost,
            'cost_source' => $costSource,
            'warning_label' => $hasCost ? $costSource : 'Missing cost',
            'line_type' => 'return',
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

    private function resolveSaleReturnItemUnitCost(SaleReturnItem $item, Collection $costMaps): array
    {
        $snapshot = (float) ($item->saleItem?->cost_price_snapshot ?? 0);
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
        $returnedRevenue = round((float) $rows->sum('returned_revenue'), 2);
        $netRevenue = round($revenue - $returnedRevenue, 2);
        $cogs = round((float) $rows->sum('estimated_cogs'), 2);
        $returnedCogs = round((float) $rows->sum('returned_cogs'), 2);
        $netCogs = round($cogs - $returnedCogs, 2);
        $grossProfit = round($revenue - $cogs, 2);
        $netGrossProfit = round($netRevenue - $netCogs, 2);
        $missingCostLines = $rows->filter(fn ($row) => ! $row->has_cost)->count();

        return [
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'sales_revenue' => $revenue,
            'returned_revenue' => $returnedRevenue,
            'net_sales_revenue' => $netRevenue,
            'estimated_cogs' => $cogs,
            'returned_cogs' => $returnedCogs,
            'net_estimated_cogs' => $netCogs,
            'estimated_gross_profit' => $grossProfit,
            'net_estimated_gross_profit' => $netGrossProfit,
            'estimated_margin_percent' => $missingCostLines > 0 || $revenue <= 0 ? null : round(($grossProfit / $revenue) * 100, 2),
            'net_margin_percent' => $missingCostLines > 0 || $netRevenue <= 0 ? null : round(($netGrossProfit / $netRevenue) * 100, 2),
            'sales_count' => $rows->where('line_type', 'sale')->pluck('sale_id')->unique()->count(),
            'quantity_sold' => round((float) $rows->sum('quantity'), 3),
            'quantity_returned' => round((float) $rows->sum('returned_quantity'), 3),
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
            ->sortByDesc('net_estimated_gross_profit')
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
            ->sortByDesc('net_estimated_gross_profit')
            ->values();
    }

    private function profitByDate(Collection $rows): Collection
    {
        return $rows
            ->groupBy('sale_date')
            ->map(function (Collection $group, string $date) {
                $row = $this->profitGroupRow($group, [
                    'date' => $date,
                    'sales_count' => $group->where('line_type', 'sale')->pluck('sale_id')->unique()->count(),
                ]);

                return $row;
            })
            ->sortBy('date')
            ->values();
    }

    private function profitGroupRow(Collection $group, array $attributes): object
    {
        $revenue = round((float) $group->sum('sales_revenue'), 2);
        $returnedRevenue = round((float) $group->sum('returned_revenue'), 2);
        $netRevenue = round($revenue - $returnedRevenue, 2);
        $cogs = round((float) $group->sum('estimated_cogs'), 2);
        $returnedCogs = round((float) $group->sum('returned_cogs'), 2);
        $netCogs = round($cogs - $returnedCogs, 2);
        $grossProfit = round($revenue - $cogs, 2);
        $netGrossProfit = round($netRevenue - $netCogs, 2);
        $missingCostLines = $group->filter(fn ($row) => ! $row->has_cost)->count();
        $costSources = $group->pluck('cost_source')->filter(fn ($source) => $source !== 'Missing cost')->unique()->values();

        return (object) array_merge($attributes, [
            'quantity_sold' => round((float) $group->sum('quantity'), 3),
            'quantity_returned' => round((float) $group->sum('returned_quantity'), 3),
            'sales_revenue' => $revenue,
            'returned_revenue' => $returnedRevenue,
            'net_sales_revenue' => $netRevenue,
            'estimated_cogs' => $cogs,
            'returned_cogs' => $returnedCogs,
            'net_estimated_cogs' => $netCogs,
            'estimated_gross_profit' => $grossProfit,
            'net_estimated_gross_profit' => $netGrossProfit,
            'estimated_margin_percent' => $missingCostLines > 0 || $revenue <= 0 ? null : round(($grossProfit / $revenue) * 100, 2),
            'net_margin_percent' => $missingCostLines > 0 || $netRevenue <= 0 ? null : round(($netGrossProfit / $netRevenue) * 100, 2),
            'missing_cost_lines' => $missingCostLines,
            'has_reliable_margin' => $missingCostLines === 0 && $netRevenue > 0,
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
            ->with(['category:id,name', 'supplier:id,name', 'baseProductUnit', 'units' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByDesc('conversion_factor')
                ->orderBy('unit_name')])
            ->where('is_active', true)
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
            ->when($search !== '', fn ($query) => $this->applyReportProductSearch($query, $search))
            ->orderBy('name');
    }

    private function applySaleItemSearch($query, string $search)
    {
        return $query->where(function ($inner) use ($search) {
            $inner->whereHas('product', fn ($product) => $this->applyProductCoreSearch($product, $search))
                ->orWhereHas('productUnit', fn ($unit) => $this->applyUnitSearch($unit, $search));
        });
    }

    private function applySaleReturnItemSearch($query, string $search)
    {
        return $query->where(function ($inner) use ($search) {
            $inner->whereHas('product', fn ($product) => $this->applyProductCoreSearch($product, $search))
                ->orWhereHas('productUnit', fn ($unit) => $this->applyUnitSearch($unit, $search));
        });
    }

    private function applyReportProductSearch($query, string $search)
    {
        return $query->where(function ($inner) use ($search) {
            $this->applyProductCoreSearch($inner, $search)
                ->orWhereHas('units', fn ($unit) => $this->applyUnitSearch($unit, $search));
        });
    }

    private function applyProductCoreSearch($query, string $search)
    {
        return $query->where(function ($inner) use ($search) {
            $inner->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('item_group', 'like', "%{$search}%")
                ->orWhereHas('category', fn ($category) => $category->where('name', 'like', "%{$search}%"))
                ->orWhereHas('supplier', fn ($supplier) => $supplier->where('name', 'like', "%{$search}%"));
        });
    }

    private function applyUnitSearch($query, string $search)
    {
        return $query->where(function ($inner) use ($search) {
            $inner->where('unit_name', 'like', "%{$search}%")
                ->orWhere('barcode', 'like', "%{$search}%")
                ->orWhere('part_number', 'like', "%{$search}%");
        });
    }

    private function priceMarginRowMatchesSearch(object $row, string $search): bool
    {
        return $this->productCoreMatchesSearch($row->product, $search)
            || $this->unitMatchesSearch($row->unit, $search);
    }

    private function productCoreMatchesSearch(Product $product, string $search): bool
    {
        $needle = Str::lower($search);

        return collect([
            $product->name,
            $product->code,
            $product->item_group,
            $product->category?->name,
            $product->supplier?->name,
        ])->filter()
            ->contains(fn ($value) => Str::contains(Str::lower((string) $value), $needle));
    }

    private function unitMatchesSearch(ProductUnit $unit, string $search): bool
    {
        $needle = Str::lower($search);

        return collect([
            $unit->unit_name,
            $unit->barcode,
            $unit->part_number,
        ])->filter()
            ->contains(fn ($value) => Str::contains(Str::lower((string) $value), $needle));
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

    private function expenseSummary(string $fromDate, string $toDate, int $storeId): array
    {
        $rows = Expense::query()
            ->with('expenseCategory:id,name')
            ->posted()
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->get()
            ->groupBy(fn (Expense $expense) => $expense->categoryName() ?: 'Uncategorised')
            ->map(fn (Collection $group, string $category) => (object) [
                'category_name' => $category,
                'expense_count' => $group->count(),
                'amount' => round((float) $group->sum('amount'), 2),
            ])
            ->sortByDesc('amount')
            ->values();

        return [
            'rows' => $rows,
            'total' => round((float) $rows->sum('amount'), 2),
        ];
    }

    private function purchaseFundingSummary(string $fromDate, string $toDate, int $storeId): Collection
    {
        return Purchase::query()
            ->with('fundingSource:id,name,sort_order')
            ->posted()
            ->whereDate('purchase_date', '>=', $fromDate)
            ->whereDate('purchase_date', '<=', $toDate)
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->get()
            ->groupBy(fn (Purchase $purchase) => $purchase->fundingSource?->name ?: 'Unspecified')
            ->map(function (Collection $group, string $sourceName) {
                $first = $group->first();

                return (object) [
                    'funding_source' => $sourceName,
                    'sort_order' => (int) ($first?->fundingSource?->sort_order ?? 9999),
                    'purchase_count' => $group->count(),
                    'purchase_total' => round((float) $group->sum('total_amount'), 2),
                    'amount_paid' => round((float) $group->sum('amount_paid'), 2),
                    'balance_due' => round((float) $group->sum('balance_due'), 2),
                ];
            })
            ->sortBy('sort_order')
            ->values();
    }
}
