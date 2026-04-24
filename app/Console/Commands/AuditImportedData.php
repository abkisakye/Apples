<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\ProductUnit;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Console\Command;

class AuditImportedData extends Command
{
    protected $signature = 'access:audit-import';

    protected $description = 'Summarize likely legacy-data cleanup items after importing Access records.';

    public function handle(): int
    {
        $stats = [
            'legacy_users' => User::query()->where('is_legacy_user', true)->count(),
            'customers_without_phone' => Customer::query()->where('is_system', false)->whereNull('phone')->count(),
            'suppliers_without_phone' => Supplier::query()->where('is_system', false)->whereNull('phone')->count(),
            'credit_sales_without_due_date' => Sale::query()->where('sale_type', 'credit')->whereNull('credit_due_date')->count(),
            'credit_purchases_without_due_date' => Purchase::query()->where('purchase_type', 'credit')->whereNull('credit_due_date')->count(),
            'inactive_or_disabled_units' => ProductUnit::query()->where('is_active', false)->count(),
            'system_customers' => Customer::query()->where('is_system', true)->count(),
            'system_suppliers' => Supplier::query()->where('is_system', true)->count(),
        ];

        $this->line('Imported Data Audit');
        $this->newLine();

        foreach ($stats as $label => $value) {
            $this->line(str_pad(str_replace('_', ' ', ucfirst($label)), 36).': '.$value);
        }

        $this->newLine();
        $this->line('Use this report to guide manual cleanup before final go-live.');

        return self::SUCCESS;
    }
}
