<?php

return [
    'cash_sale' => [
        'prefix' => 'RCPT',
        'table' => 'sales',
        'column' => 'sale_no',
    ],
    'credit_sale' => [
        'prefix' => 'INV',
        'table' => 'sales',
        'column' => 'sale_no',
    ],
    'purchase' => [
        'prefix' => 'PUR',
        'table' => 'purchases',
        'column' => 'purchase_no',
    ],
    'sale_return' => [
        'prefix' => 'SRT',
        'table' => 'sale_returns',
        'column' => 'return_no',
    ],
    'purchase_return' => [
        'prefix' => 'PRT',
        'table' => 'purchase_returns',
        'column' => 'return_no',
    ],
    'customer_payment' => [
        'prefix' => 'CPY',
        'table' => 'customer_payments',
        'column' => 'payment_no',
    ],
    'supplier_payment' => [
        'prefix' => 'SPY',
        'table' => 'supplier_payments',
        'column' => 'payment_no',
    ],
    'capital_entry' => [
        'prefix' => 'CAP',
        'table' => 'capital_entries',
        'column' => 'entry_no',
    ],
    'expense' => [
        'prefix' => 'EXP',
        'table' => 'expenses',
        'column' => 'expense_no',
    ],
    'cash_shift' => [
        'prefix' => 'SHF',
        'table' => 'cash_shifts',
        'column' => 'shift_no',
    ],
    'stock_transfer' => [
        'prefix' => 'TRF',
        'table' => 'inventory_transactions',
        'column' => 'reference_no',
    ],
    'stock_adjustment' => [
        'prefix' => 'ADJ',
        'table' => 'inventory_transactions',
        'column' => 'reference_no',
    ],
    'stock_count' => [
        'prefix' => 'CNT',
        'table' => 'stock_counts',
        'column' => 'count_no',
    ],
];
