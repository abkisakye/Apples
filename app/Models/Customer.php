<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
            'credit_limit' => 'decimal:2',
            'allow_credit_sales' => 'boolean',
            'is_walk_in' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    public function openingBalancePayments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class)->where('account_reference_type', 'opening_balance');
    }

    public function saleReturns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function creditSales(): HasMany
    {
        return $this->hasMany(Sale::class)->where('sale_type', 'credit')->where('status', 'posted');
    }

    public function openingBalanceOutstanding(): float
    {
        $paymentsTotal = $this->relationLoaded('openingBalancePayments')
            ? (float) $this->openingBalancePayments->where('status', 'posted')->sum('amount')
            : (float) $this->openingBalancePayments()->where('status', 'posted')->sum('amount');

        return round(max((float) $this->opening_balance - $paymentsTotal, 0), 2);
    }
}
