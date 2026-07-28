<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ManagementCentreController extends Controller
{
    public function __invoke(): View
    {
        $groups = [
            [
                'title' => 'Stock Sales',
                'summary' => 'Sales desk shortcuts for cash sales, credit sales, receipts, and customer activity.',
                'items' => [
                    ['label' => 'New Sale', 'route' => 'sales.create', 'ability' => 'sales.manage', 'description' => 'Open the cashier sale screen.'],
                    ['label' => 'Sales List', 'route' => 'sales.index', 'ability' => 'sales.view', 'description' => 'Review posted sales and receipts.'],
                    ['label' => 'Credit Sales', 'route' => 'sales.index', 'route_params' => ['type' => 'credit'], 'ability' => 'sales.view', 'description' => 'Follow up unpaid customer invoices.'],
                    ['label' => 'Customer Payments', 'route' => 'customer-payments.index', 'ability' => 'customer_payments.manage', 'description' => 'Record and review customer collections.'],
                ],
            ],
            [
                'title' => 'Stock Purchases',
                'summary' => 'Supplier delivery, invoice, payment, and buying workflows.',
                'items' => [
                    ['label' => 'New Purchase', 'route' => 'purchases.create', 'ability' => 'purchases.manage', 'description' => 'Receive stock from a supplier.'],
                    ['label' => 'Purchases List', 'route' => 'purchases.index', 'ability' => 'purchases.view', 'description' => 'Review supplier invoices and stock receipts.'],
                    ['label' => 'Outstanding Purchases', 'route' => 'purchases.index', 'route_params' => ['balance' => 'outstanding'], 'ability' => 'purchases.view', 'description' => 'See unpaid supplier purchases.'],
                    ['label' => 'Supplier Payments', 'route' => 'supplier-payments.index', 'ability' => 'supplier_payments.manage', 'description' => 'Record and review supplier payments.'],
                ],
            ],
            [
                'title' => 'Stock Control',
                'summary' => 'Daily stock balances, reorder, transfers, adjustments, and stock counts.',
                'items' => [
                    ['label' => 'Stock Balances', 'route' => 'stock.balances', 'ability' => 'stock.view', 'description' => 'Check current stock quantities and values.'],
                    ['label' => 'Add Existing Stock', 'route' => 'stock.opening-stock.create', 'ability' => 'stock.manage', 'description' => 'Enter old shop stock without creating supplier debt.'],
                    ['label' => 'Reorder Report', 'route' => 'stock.reorder', 'ability' => 'stock.view', 'description' => 'Find items at or below reorder level.'],
                    ['label' => 'Stock Transfers', 'route' => 'stock.transfers.index', 'ability' => 'stock.view', 'description' => 'Review stock moved between stores.'],
                    ['label' => 'Stock Adjustments', 'route' => 'stock.adjustments.index', 'ability' => 'stock.view', 'description' => 'Review corrections for damaged, missing, or extra stock.'],
                    ['label' => 'Stock Counts', 'route' => 'stock.counts.index', 'ability' => 'stock.view', 'description' => 'Review physical count drafts and posted counts.'],
                ],
            ],
            [
                'title' => 'Accounts',
                'summary' => 'Cash, credit, expenses, collections, supplier payments, and account balances.',
                'items' => [
                    ['label' => 'Customer Balances', 'route' => 'reports.customer-aging', 'ability' => 'reports.view', 'description' => 'Review customer credit aging.'],
                    ['label' => 'Supplier Balances', 'route' => 'reports.supplier-aging', 'ability' => 'reports.view', 'description' => 'Review unpaid supplier balances.'],
                    ['label' => 'Payment Modes', 'route' => 'reports.payment-methods', 'ability' => 'reports.view', 'description' => 'Compare cash, mobile money, bank, and other payment modes.'],
                    ['label' => 'Expenses', 'route' => 'expenses.index', 'ability' => 'expenses.view', 'description' => 'Review shop expenses.'],
                ],
            ],
            [
                'title' => 'Reports',
                'summary' => 'Management reports for trading, cashiers, daily closing, and credit control.',
                'items' => [
                    ['label' => 'Daily Sales Summary', 'route' => 'reports.daily-sales-summary', 'ability' => 'reports.view', 'description' => 'Item-level sales and income by shop, payment type, quantity, and average rate.'],
                    ['label' => 'Financial Summary', 'route' => 'reports.financial-summary', 'ability' => 'reports.view', 'description' => 'Sales, purchases, expenses, collections, and profit summary.'],
                    ['label' => 'Daily Closing', 'route' => 'reports.daily-closing', 'ability' => 'reports.view', 'description' => 'Close the day with cash, sales, credit, refunds, and expenses.'],
                    ['label' => 'Cashier Performance', 'route' => 'reports.cashier-performance', 'ability' => 'reports.view', 'description' => 'Review cashier sales, discounts, payments, and shifts.'],
                    ['label' => 'Customer Aging', 'route' => 'reports.customer-aging', 'ability' => 'reports.view', 'description' => 'Track overdue customer credit.'],
                    ['label' => 'Supplier Aging', 'route' => 'reports.supplier-aging', 'ability' => 'reports.view', 'description' => 'Track supplier credit due.'],
                ],
            ],
            [
                'title' => 'Setup',
                'summary' => 'Product, pack/unit, category, payment, store, and business setup shortcuts.',
                'items' => [
                    ['label' => 'Products', 'route' => 'products.index', 'ability' => 'products.view', 'description' => 'Maintain products and their selling packs.'],
                    ['label' => 'New Product', 'route' => 'products.create', 'ability' => 'purchases.manage', 'description' => 'Create one product with all wholesale and retail units.'],
                    ['label' => 'Product Categories', 'route' => 'master-data.index', 'route_params' => ['resource' => 'categories'], 'abilities' => ['business.manage', 'master_data.manage'], 'description' => 'Maintain product grouping.'],
                    ['label' => 'Payment Modes', 'route' => 'master-data.index', 'route_params' => ['resource' => 'payment-modes'], 'abilities' => ['business.manage', 'master_data.manage'], 'description' => 'Maintain cash, mobile money, bank, and credit modes.'],
                    ['label' => 'Stores', 'route' => 'master-data.index', 'route_params' => ['resource' => 'stores'], 'abilities' => ['business.manage', 'master_data.manage'], 'description' => 'Maintain shop/store locations.'],
                    ['label' => 'Business Settings', 'route' => 'settings.business.edit', 'ability' => 'business.manage', 'description' => 'Update shop branding and business details.'],
                ],
            ],
        ];

        return view('management_centre', compact('groups'));
    }
}
