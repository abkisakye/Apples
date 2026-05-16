<?php

namespace App\Support;

use App\Models\InventoryTransaction;
use App\Models\ProductUnit;
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
}
