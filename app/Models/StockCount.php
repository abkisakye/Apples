<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockCount extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'count_date' => 'date',
            'line_count' => 'integer',
            'total_variance_qty' => 'integer',
            'total_variance_base_qty' => 'decimal:3',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockCountItem::class);
    }

    public function unitEntries(): HasMany
    {
        return $this->hasMany(StockCountUnitEntry::class);
    }
}
