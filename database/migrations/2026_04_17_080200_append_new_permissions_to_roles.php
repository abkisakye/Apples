<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $appends = [
            'manager' => ['expenses.view', 'expenses.manage', 'cash_shifts.manage', 'sales.override'],
            'cashier' => ['cash_shifts.manage'],
            'stock_clerk' => ['expenses.view'],
        ];

        foreach ($appends as $roleName => $permissionsToAdd) {
            $existing = DB::table('roles')->where('name', $roleName)->value('permissions');
            $decoded = json_decode($existing ?: '[]', true);
            $decoded = is_array($decoded) ? $decoded : [];
            $merged = array_values(array_unique(array_merge($decoded, $permissionsToAdd)));

            DB::table('roles')
                ->where('name', $roleName)
                ->update(['permissions' => json_encode($merged)]);
        }
    }

    public function down(): void
    {
        $removals = ['expenses.view', 'expenses.manage', 'cash_shifts.manage', 'sales.override'];

        foreach (['manager', 'cashier', 'stock_clerk'] as $roleName) {
            $existing = DB::table('roles')->where('name', $roleName)->value('permissions');
            $decoded = json_decode($existing ?: '[]', true);
            $decoded = is_array($decoded) ? $decoded : [];
            $filtered = array_values(array_filter($decoded, fn ($permission) => ! in_array($permission, $removals, true)));

            DB::table('roles')
                ->where('name', $roleName)
                ->update(['permissions' => json_encode($filtered)]);
        }
    }
};
