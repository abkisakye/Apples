<?php

namespace Database\Seeders;

use App\Models\CapitalSource;
use Illuminate\Database\Seeder;

class CapitalSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            ['name' => 'Retained Business Cash', 'source_type' => 'business_generated'],
            ['name' => 'Owner Injection', 'source_type' => 'owner_injection'],
            ['name' => 'External Investor', 'source_type' => 'external_investor'],
            ['name' => 'Loan', 'source_type' => 'loan'],
            ['name' => 'Other', 'source_type' => 'other'],
        ];

        foreach ($sources as $source) {
            CapitalSource::updateOrCreate(['name' => $source['name']], $source + ['is_active' => true]);
        }
    }
}
