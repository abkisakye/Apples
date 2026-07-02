<?php

namespace App\Support;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockDisplayService
{
    public function __construct(private ProductUnitConversionService $conversionService)
    {
    }

    public function rows(Request $request, bool $reorderOnly = false): Collection
    {
        $storeId = $request->integer('store_id');
        $products = $this->productsForDisplay($request)->get();
        $balances = $this->baseBalances($products->pluck('id')->all(), $storeId);

        return $products
            ->map(function (Product $product) use ($balances) {
                $baseBalance = round((float) ($balances[$product->id] ?? 0), 3);
                $baseUnit = $this->conversionService->baseUnitForProduct($product);
                $baseUnitLabel = $product->base_unit_label ?: $baseUnit?->unit_name ?: 'base unit(s)';
                $baseCost = $this->baseUnitCost($product, $baseUnit);

                return (object) [
                    'id' => $product->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_code' => $product->code,
                    'category_name' => $product->category?->name,
                    'reorder_level' => (float) $product->reorder_level,
                    'base_balance' => $baseBalance,
                    'base_stock_label' => $this->formatQuantityWithUnit($baseBalance, $baseUnitLabel, $baseUnit),
                    'base_unit_label' => $baseUnitLabel,
                    'friendly_breakdown' => $this->friendlyBreakdown($baseBalance, $product, $baseUnit),
                    'configured_units' => $this->configuredUnitsLabel($product),
                    'stock_value' => round($baseBalance * $baseCost, 2),
                    'primary_unit_id' => $this->primaryUnit($product)?->id,
                    'shortage' => max(round((float) $product->reorder_level - $baseBalance, 3), 0),
                    'shortage_label' => $this->formatQuantityWithUnit(max(round((float) $product->reorder_level - $baseBalance, 3), 0), $baseUnitLabel, $baseUnit),
                    'reorder_level_label' => $this->formatQuantityWithUnit((float) $product->reorder_level, $baseUnitLabel, $baseUnit),
                ];
            })
            ->when($reorderOnly, fn (Collection $rows) => $rows
                ->filter(fn ($row) => (float) $row->reorder_level > 0 && (float) $row->base_balance <= (float) $row->reorder_level)
                ->values())
            ->values();
    }

    public function formatQuantityWithUnit(float|int|string $quantity, string $unitLabel, ?ProductUnit $unit = null): string
    {
        $quantity = round((float) $quantity, 3);
        $formatted = $this->formatNumber($quantity, max((int) ($unit?->quantity_precision ?? 0), 0));
        $label = Str::lower($unitLabel);

        if (abs($quantity) === 1.0 || $this->isUncountableUnit($label)) {
            return $formatted.' '.$label;
        }

        return $formatted.' '.Str::plural($label);
    }

    public function friendlyBreakdown(float|int|string $baseQuantity, Product $product, ?ProductUnit $baseUnit = null): string
    {
        $baseQuantity = round((float) $baseQuantity, 3);
        $baseUnit ??= $this->conversionService->baseUnitForProduct($product);
        $baseLabel = $product->base_unit_label ?: $baseUnit?->unit_name ?: 'base unit(s)';

        $largestPack = $product->units
            ->where('is_active', true)
            ->filter(fn (ProductUnit $unit) => $this->conversionService->conversionFactorSnapshot($unit) > 1)
            ->sortByDesc(fn (ProductUnit $unit) => $this->conversionService->conversionFactorSnapshot($unit))
            ->first();

        if (! $largestPack) {
            return $this->formatQuantityWithUnit($baseQuantity, $baseLabel, $baseUnit);
        }

        $factor = $this->conversionService->conversionFactorSnapshot($largestPack);
        $wholePacks = (int) floor($baseQuantity / $factor);
        $remainder = round($baseQuantity - ($wholePacks * $factor), 3);
        $parts = [];

        if ($wholePacks > 0) {
            $parts[] = $this->formatQuantityWithUnit($wholePacks, $largestPack->unit_name, $largestPack);
        }

        if ($remainder > 0 || $parts === []) {
            $parts[] = $this->formatQuantityWithUnit($remainder, $baseLabel, $baseUnit);
        }

        return implode(' + ', $parts);
    }

    public function productSummary(Product $product, int $storeId = 0): object
    {
        $product->loadMissing(['units' => fn ($query) => $query
            ->where('is_active', true)
            ->orderByDesc('conversion_factor')
            ->orderBy('unit_name'), 'baseProductUnit']);

        $baseUnit = $this->conversionService->baseUnitForProduct($product);
        $baseUnitLabel = $product->base_unit_label ?: $baseUnit?->unit_name ?: 'base unit(s)';
        $baseBalance = $this->baseBalanceForProduct((int) $product->id, $storeId);

        return (object) [
            'base_balance' => $baseBalance,
            'base_stock_label' => $this->formatQuantityWithUnit($baseBalance, $baseUnitLabel, $baseUnit),
            'base_unit_label' => $baseUnitLabel,
            'friendly_breakdown' => $this->friendlyBreakdown($baseBalance, $product, $baseUnit),
            'configured_units' => $this->configuredUnitsLabel($product),
            'base_unit' => $baseUnit,
        ];
    }

    public function baseBalanceForProduct(int $productId, int $storeId = 0): float
    {
        return round((float) ($this->baseBalances([$productId], $storeId)[$productId] ?? 0), 3);
    }

    public function historyRows(Product $product, int $storeId = 0): Collection
    {
        $product->loadMissing(['units', 'baseProductUnit']);
        $baseUnit = $this->conversionService->baseUnitForProduct($product);
        $baseUnitLabel = $product->base_unit_label ?: $baseUnit?->unit_name ?: 'base unit(s)';
        $runningBalance = 0.0;

        return InventoryTransaction::query()
            ->with(['store:id,name', 'productUnit:id,product_id,unit_name,conversion_factor', 'createdBy:id,name'])
            ->where('product_id', $product->id)
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(function (InventoryTransaction $transaction) use (&$runningBalance, $baseUnitLabel, $baseUnit) {
                $baseIn = $this->baseMovementQuantity($transaction, 'in');
                $baseOut = $this->baseMovementQuantity($transaction, 'out');
                $baseImpact = round($baseIn - $baseOut, 3);
                $runningBalance = round($runningBalance + $baseImpact, 3);

                return (object) [
                    'transaction' => $transaction,
                    'selected_quantity_label' => $this->selectedQuantityLabel($transaction),
                    'base_impact' => $baseImpact,
                    'base_impact_label' => $this->formatSignedBaseImpact($baseImpact, $baseUnitLabel, $baseUnit),
                    'running_balance' => $runningBalance,
                    'running_balance_label' => $this->formatQuantityWithUnit($runningBalance, $baseUnitLabel, $baseUnit),
                ];
            });
    }

    public function baseMovementQuantity(InventoryTransaction $transaction, string $direction): float
    {
        $baseColumn = $direction === 'in' ? 'base_quantity_in' : 'base_quantity_out';
        $quantityColumn = $direction === 'in' ? 'quantity_in' : 'quantity_out';
        $baseQuantity = (float) ($transaction->{$baseColumn} ?? 0);

        if ($baseQuantity != 0.0) {
            return round($baseQuantity, 3);
        }

        $factor = (float) ($transaction->conversion_factor_snapshot ?: $transaction->productUnit?->conversion_factor ?: 1);

        return round((float) ($transaction->{$quantityColumn} ?? 0) * ($factor > 0 ? $factor : 1), 3);
    }

    public function selectedQuantityLabel(InventoryTransaction $transaction): string
    {
        $quantity = max((float) $transaction->quantity_in, (float) $transaction->quantity_out);
        $unit = $transaction->productUnit;
        $unitLabel = $unit?->unit_name ?? 'unit';

        return $this->formatQuantityWithUnit($quantity, $unitLabel, $unit);
    }

    private function formatSignedBaseImpact(float $quantity, string $unitLabel, ?ProductUnit $unit = null): string
    {
        $prefix = $quantity >= 0 ? '+' : '-';

        return $prefix.$this->formatQuantityWithUnit(abs($quantity), $unitLabel, $unit);
    }

    private function productsForDisplay(Request $request)
    {
        $search = trim((string) $request->string('q'));
        $categoryId = $request->integer('category_id');

        return Product::query()
            ->with(['category:id,name', 'units' => fn ($query) => $query
                ->where('is_active', true)
                ->orderByDesc('conversion_factor')
                ->orderBy('unit_name'), 'baseProductUnit'])
            ->where('is_active', true)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('units', fn ($unitQuery) => $unitQuery->where('unit_name', 'like', "%{$search}%"));
                });
            })
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
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
            ->groupBy('inventory_transactions.product_id')
            ->pluck('base_balance', 'product_id');
    }

    private function configuredUnitsLabel(Product $product): string
    {
        return $product->units
            ->sortByDesc(fn (ProductUnit $unit) => $this->conversionService->conversionFactorSnapshot($unit))
            ->map(fn (ProductUnit $unit) => $unit->unit_name.' '.$this->formatNumber($this->conversionService->conversionFactorSnapshot($unit)))
            ->implode(', ');
    }

    private function primaryUnit(Product $product): ?ProductUnit
    {
        return $product->units->firstWhere('is_pos_unit', true)
            ?? $this->conversionService->baseUnitForProduct($product)
            ?? $product->units->first();
    }

    private function baseUnitCost(Product $product, ?ProductUnit $baseUnit): float
    {
        if ($baseUnit) {
            return (float) $baseUnit->cost_price;
        }

        $unit = $product->units->first();

        if (! $unit) {
            return 0;
        }

        return $this->conversionService->costPerBaseUnit((float) $unit->cost_price, $unit);
    }

    private function formatNumber(float|int|string $quantity, int $precision = 0): string
    {
        $quantity = round((float) $quantity, 3);
        $precision = max($precision, $this->hasDecimalRemainder($quantity) ? 3 : 0);
        $formatted = number_format($quantity, $precision, '.', '');

        if ($precision === 0) {
            return $formatted;
        }

        return rtrim(rtrim($formatted, '0'), '.') ?: '0';
    }

    private function hasDecimalRemainder(float $quantity): bool
    {
        return abs($quantity - floor($quantity)) > 0.0001;
    }

    private function isUncountableUnit(string $label): bool
    {
        return in_array($label, ['each', 'g', 'gram', 'grams', 'kg', 'kilogram', 'kilograms', 'ml', 'l', 'ltr', 'litre', 'litres', 'liter', 'liters'], true);
    }
}
