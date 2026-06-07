<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockCountUnitEntry extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'entered_quantity' => 'decimal:3',
            'conversion_factor_snapshot' => 'decimal:6',
            'base_quantity' => 'decimal:3',
        ];
    }

    public function stockCount(): BelongsTo
    {
        return $this->belongsTo(StockCount::class);
    }

    public function stockCountItem(): BelongsTo
    {
        return $this->belongsTo(StockCountItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }
}
