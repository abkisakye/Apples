<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->json('permissions')->nullable()->after('description');
        });

        $defaults = [
            'admin' => ['*'],
            'manager' => [
                'dashboard.view', 'customers.view', 'customers.statement', 'suppliers.view', 'suppliers.statement',
                'products.view', 'sales.view', 'sales.manage', 'purchases.view', 'purchases.manage',
                'customer_payments.manage', 'supplier_payments.manage', 'capital.view', 'capital.manage',
                'stock.view', 'reports.view', 'follow_ups.manage', 'activity_logs.view',
            ],
            'cashier' => [
                'dashboard.view', 'customers.view', 'customers.statement', 'products.view',
                'sales.view', 'sales.manage', 'customer_payments.manage', 'follow_ups.manage',
            ],
            'stock_clerk' => [
                'dashboard.view', 'suppliers.view', 'suppliers.statement', 'products.view',
                'purchases.view', 'purchases.manage', 'supplier_payments.manage', 'stock.view', 'stock.manage',
            ],
            'guest' => [],
        ];

        foreach ($defaults as $name => $permissions) {
            DB::table('roles')
                ->where('name', $name)
                ->update(['permissions' => json_encode(array_values($permissions))]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table): void {
            $table->dropColumn('permissions');
        });
    }
};
