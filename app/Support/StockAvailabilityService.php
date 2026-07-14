<?php

namespace App\Support;

use App\Models\InventoryTransaction;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StockAvailabilityService
{
    public function availableQuantity(int $storeId, int $productUnitId): int
    {
        $row = InventoryTransaction::query()
            ->where('store_id', $storeId)
            ->where('product_unit_id', $productUnitId)
            ->selectRaw('COALESCE(SUM(quantity_in), 0) - COALESCE(SUM(quantity_out), 0) as balance_qty')
            ->first();

        return (int) round((float) ($row?->balance_qty ?? 0));
    }

    public function ensureAvailable(int $storeId, ProductUnit $unit, int $requestedQty, string $field, ?string $message = null): void
    {
        $availableQty = $this->availableQuantity($storeId, $unit->id);

        if ($requestedQty <= $availableQty) {
            return;
        }

        $itemLabel = trim(($unit->product?->name ?? 'Selected product').' - '.$unit->unit_name);

        throw ValidationException::withMessages([
            $field => $message ?: "{$itemLabel} has only {$availableQty} in stock at this store. You cannot post {$requestedQty}.",
        ]);
    }

    public function availableBaseQuantity(int $storeId, int $productId): float
    {
        $row = InventoryTransaction::query()
            ->leftJoin('product_units', 'product_units.id', '=', 'inventory_transactions.product_unit_id')
            ->where('inventory_transactions.store_id', $storeId)
            ->where('inventory_transactions.product_id', $productId)
            ->selectRaw('
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
                ), 0) as balance_qty
            ')
            ->first();

        return round((float) ($row?->balance_qty ?? 0), 3);
    }

    public function availableBaseQuantities(int $storeId, iterable $productIds): Collection
    {
        $productIds = collect($productIds)
            ->map(fn ($productId) => (int) $productId)
            ->filter()
            ->unique()
            ->values();

        if ($productIds->isEmpty()) {
            return collect();
        }

        return InventoryTransaction::query()
            ->leftJoin('product_units', 'product_units.id', '=', 'inventory_transactions.product_unit_id')
            ->where('inventory_transactions.store_id', $storeId)
            ->whereIn('inventory_transactions.product_id', $productIds)
            ->groupBy('inventory_transactions.product_id')
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
                ), 0) as balance_qty
            ')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->product_id => round((float) $row->balance_qty, 3)]);
    }

    public function ensureBaseAvailable(int $storeId, ProductUnit $unit, float $requestedBaseQty, string $field, ?string $message = null, float $availableBaseAdjustment = 0): void
    {
        $availableQty = round($this->availableBaseQuantity($storeId, (int) $unit->product_id) + $availableBaseAdjustment, 3);

        if ($requestedBaseQty <= $availableQty) {
            return;
        }

        $itemLabel = trim(($unit->product?->name ?? 'Selected product').' - '.$unit->unit_name);

        throw ValidationException::withMessages([
            $field => $message ?: "{$itemLabel} has only {$availableQty} base unit(s) in stock at this store. You cannot post {$requestedBaseQty}.",
        ]);
    }
}
