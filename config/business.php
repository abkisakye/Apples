<?php

return [
    'name' => env('BUSINESS_NAME', 'Apples Of Gold'),
    'tagline' => env('BUSINESS_TAGLINE', 'Business Management System'),
    'address' => env('BUSINESS_ADDRESS', ''),
    'phone' => env('BUSINESS_PHONE', ''),
    'email' => env('BUSINESS_EMAIL', ''),
    'tin' => env('BUSINESS_TIN', ''),
    'logo_url' => env('BUSINESS_LOGO_URL', ''),
    'currency' => env('BUSINESS_CURRENCY', 'UGX'),
    'receipt_footer' => env('BUSINESS_RECEIPT_FOOTER', 'Thank you for your business. Goods once sold are not returnable unless approved by management.'),
    'invoice_footer' => env('BUSINESS_INVOICE_FOOTER', 'Please settle outstanding balances by the due date shown on this invoice.'),
    'statement_footer' => env('BUSINESS_STATEMENT_FOOTER', 'This statement is system-generated and intended for account reconciliation.'),
    'developer_name' => env('SYSTEM_DEVELOPER_NAME', 'Kisakye Allan'),
    'developer_company' => env('SYSTEM_DEVELOPER_COMPANY', 'Rolans Software Solutions'),
    'developer_phone' => env('SYSTEM_DEVELOPER_PHONE', ''),
    'admin_approval_pin' => env('BUSINESS_ADMIN_APPROVAL_PIN', ''),
    'cashier_discount_limit' => (float) env('BUSINESS_CASHIER_DISCOUNT_LIMIT', 0),
];
