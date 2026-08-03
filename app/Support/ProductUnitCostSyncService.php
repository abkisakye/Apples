<?php

namespace App\Support;

use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\PurchaseItem;

class ProductUnitCostSyncService
{
    public function syncFromPurchase(Purchase $purchase): int
    {
        if ($purchase->status !== 'posted') {
            return 0;
        }

        $purchase->loadMissing('items.productUnit');

        return $purchase->items->sum(fn (PurchaseItem $item) => $this->syncFromPurchaseItem($item));
    }

    public function syncFromPurchaseItem(PurchaseItem $item): int
    {
        $unitCost = round((float) $item->unit_cost, 2);

        if ($unitCost <= 0 || ! $item->product_unit_id) {
            return 0;
        }

        return ProductUnit::query()
            ->whereKey($item->product_unit_id)
            ->update(['cost_price' => $unitCost]);
    }
}
