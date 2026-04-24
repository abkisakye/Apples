<?php

namespace Database\Seeders;

use App\Models\PaymentMode;
use Illuminate\Database\Seeder;

class PaymentModeSeeder extends Seeder
{
    public function run(): void
    {
        $modes = ['Cash', 'Mobile Money', 'Cheque', 'Direct Deposit/Transfer', 'Credit'];

        foreach ($modes as $mode) {
            PaymentMode::updateOrCreate(['name' => $mode], ['is_active' => true]);
        }
    }
}
