<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUnit extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:3',
            'selling_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'opening_stock_qty' => 'decimal:3',
            'is_pos_unit' => 'boolean',
            'allow_fractional_quantity' => 'boolean',
            'quantity_precision' => 'integer',
            'is_base_unit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockCountUnitEntries(): HasMany
    {
        return $this->hasMany(StockCountUnitEntry::class);
    }
}
