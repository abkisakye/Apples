<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'admin' => ['description' => 'Admin', 'permissions' => ['*']],
            'manager' => [
                'description' => 'Manager',
                'permissions' => [
                    'dashboard.view', 'customers.view', 'customers.statement', 'suppliers.view', 'suppliers.statement',
                    'products.view', 'sales.view', 'sales.manage', 'purchases.view', 'purchases.manage',
                    'customer_payments.manage', 'supplier_payments.manage', 'capital.view', 'capital.manage',
                    'stock.view', 'reports.view', 'follow_ups.manage', 'activity_logs.view',
                    'expenses.view', 'expenses.manage', 'cash_shifts.manage', 'sales.override',
                ],
            ],
            'cashier' => [
                'description' => 'Cashier',
                'permissions' => [
                    'dashboard.view', 'customers.view', 'customers.statement', 'products.view',
                    'sales.view', 'sales.manage', 'customer_payments.manage', 'follow_ups.manage', 'cash_shifts.manage',
                ],
            ],
            'stock_clerk' => [
                'description' => 'Stock Clerk',
                'permissions' => [
                    'dashboard.view', 'suppliers.view', 'suppliers.statement', 'products.view',
                    'purchases.view', 'purchases.manage', 'supplier_payments.manage', 'stock.view', 'stock.manage', 'expenses.view',
                ],
            ],
            'guest' => ['description' => 'Guest', 'permissions' => []],
        ];

        foreach ($defaults as $role => $data) {
            Role::updateOrCreate(['name' => $role], $data);
        }
    }
}
