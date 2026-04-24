<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SystemRecordsSeeder extends Seeder
{
    public function run(): void
    {
        Customer::updateOrCreate(
            ['name' => 'Walk-in Customer'],
            ['is_walk_in' => true, 'is_system' => true, 'is_active' => true]
        );

        Customer::updateOrCreate(
            ['name' => 'Unknown Customer'],
            ['is_walk_in' => false, 'is_system' => true, 'is_active' => true]
        );

        foreach (['OTHERS', 'OUT PURCHASE'] as $name) {
            Supplier::updateOrCreate(
                ['name' => $name],
                ['is_system' => true, 'is_active' => true]
            );
        }
    }
}
