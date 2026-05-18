<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores')) {
            return;
        }

        $shopId = $this->ensureApplesShop();

        foreach ($this->storeLinkedTables() as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'store_id')) {
                DB::table($table)->whereNotNull('store_id')->update(['store_id' => $shopId]);
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'default_store_id')) {
            DB::table('users')->update(['default_store_id' => $shopId]);
        }

        DB::table('stores')
            ->where('id', '<>', $shopId)
            ->update([
                'is_active' => false,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This migration intentionally normalizes pilot data to one shop.
        // It does not try to recreate older test/branch assignments.
    }

    private function ensureApplesShop(): int
    {
        $now = now();
        $shop = DB::table('stores')->where('name', 'Apples Of Gold')->first();

        if (! $shop) {
            $shop = DB::table('stores')->orderBy('id')->first();
        }

        if ($shop) {
            $codeInUseElsewhere = DB::table('stores')
                ->where('code', 'AOG')
                ->where('id', '<>', $shop->id)
                ->exists();

            DB::table('stores')
                ->where('id', $shop->id)
                ->update([
                    'name' => 'Apples Of Gold',
                    'code' => $codeInUseElsewhere ? $shop->code : 'AOG',
                    'location' => $shop->location ?: 'Apples Of Gold',
                    'is_active' => true,
                    'updated_at' => $now,
                ]);

            return (int) $shop->id;
        }

        return (int) DB::table('stores')->insertGetId([
            'name' => 'Apples Of Gold',
            'code' => 'AOG',
            'location' => 'Apples Of Gold',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function storeLinkedTables(): array
    {
        return [
            'sales',
            'sale_returns',
            'purchases',
            'purchase_returns',
            'customer_payments',
            'supplier_payments',
            'capital_entries',
            'expenses',
            'cash_shifts',
            'inventory_transactions',
            'stock_counts',
        ];
    }
};
