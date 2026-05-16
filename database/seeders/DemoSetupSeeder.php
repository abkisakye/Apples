<?php

namespace Database\Seeders;

use App\Models\CapitalSource;
use App\Models\Customer;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Role;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoSetupSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $mainStore = Store::query()->firstOrCreate(['name' => 'Demo Main Store'], ['is_active' => true]);
        $cashierRole = Role::query()->firstOrCreate(['name' => 'cashier'], ['description' => 'Cashier']);
        $managerRole = Role::query()->firstOrCreate(['name' => 'manager'], ['description' => 'Manager']);
        $stockRole = Role::query()->firstOrCreate(['name' => 'stock_clerk'], ['description' => 'Stock Clerk']);

        User::updateOrCreate(
            ['email' => 'manager.demo@apples.local'],
            ['name' => 'Demo Manager', 'username' => 'manager.demo', 'password' => 'password', 'role_id' => $managerRole->id, 'default_store_id' => $mainStore->id, 'is_active' => true]
        );
        User::updateOrCreate(
            ['email' => 'cashier.demo@apples.local'],
            ['name' => 'Demo Cashier', 'username' => 'cashier.demo', 'password' => 'password', 'role_id' => $cashierRole->id, 'default_store_id' => $mainStore->id, 'is_active' => true]
        );
        User::updateOrCreate(
            ['email' => 'stock.demo@apples.local'],
            ['name' => 'Demo Stock Clerk', 'username' => 'stock.demo', 'password' => 'password', 'role_id' => $stockRole->id, 'default_store_id' => $mainStore->id, 'is_active' => true]
        );

        Customer::query()->firstOrCreate(
            ['name' => 'Demo Customer'],
            ['phone' => '0700000001', 'address' => 'Kampala', 'is_active' => true]
        );
        Supplier::query()->firstOrCreate(
            ['name' => 'Demo Supplier'],
            ['phone' => '0700000002', 'address' => 'Kampala', 'is_active' => true]
        );
        $product = Product::query()->firstOrCreate(
            ['name' => 'Demo Product'],
            ['code' => 'DEMO-001', 'reorder_level' => 5, 'is_active' => true]
        );
        ProductUnit::query()->firstOrCreate(
            ['product_id' => $product->id, 'unit_name' => 'Each'],
            ['selling_price' => 10000, 'cost_price' => 7000, 'barcode' => '1234567890123', 'part_number' => 'DEMO-PART-1', 'is_active' => true]
        );

        PaymentMode::query()->firstOrCreate(['name' => 'Cash'], ['is_active' => true]);
        CapitalSource::query()->firstOrCreate(['name' => 'Demo Owner Injection'], ['source_type' => 'owner_injection', 'is_active' => true]);
    }
}
