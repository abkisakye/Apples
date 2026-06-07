<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'quantity_in' => 'decimal:3',
            'quantity_out' => 'decimal:3',
            'base_quantity_in' => 'decimal:3',
            'base_quantity_out' => 'decimal:3',
            'conversion_factor_snapshot' => 'decimal:6',
            'unit_cost' => 'decimal:2',
            'unit_price' => 'decimal:2',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productUnit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
