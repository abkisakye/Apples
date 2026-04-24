<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CleanImportedData extends Command
{
    protected $signature = 'access:clean-imported-data';

    protected $description = 'Apply safe cleanup rules to imported legacy data.';

    public function handle(): int
    {
        $summary = [
            'customer_emails' => 0,
            'supplier_emails' => 0,
            'supplier_phones' => 0,
            'purchase_due_dates' => 0,
        ];

        DB::transaction(function () use (&$summary): void {
            Customer::query()->whereNotNull('email')->get()->each(function (Customer $customer) use (&$summary): void {
                $normalized = Str::lower(trim((string) $customer->email));

                if ($customer->email !== $normalized) {
                    $customer->update(['email' => $normalized]);
                    $summary['customer_emails']++;
                }
            });

            Supplier::query()->get()->each(function (Supplier $supplier) use (&$summary): void {
                $updates = [];

                if ($supplier->email) {
                    $normalized = Str::lower(trim((string) $supplier->email));

                    if ($supplier->email !== $normalized) {
                        $updates['email'] = $normalized;
                        $summary['supplier_emails']++;
                    }
                }

                if (! $supplier->phone && $supplier->postal_code) {
                    $digits = preg_replace('/\D+/', '', (string) $supplier->postal_code) ?: '';

                    if (strlen($digits) >= 9) {
                        $updates['phone'] = $supplier->postal_code;
                        $updates['postal_code'] = null;
                        $summary['supplier_phones']++;
                    }
                }

                if ($updates !== []) {
                    $supplier->update($updates);
                }
            });

            Purchase::query()
                ->where('purchase_type', 'credit')
                ->whereNull('credit_due_date')
                ->get()
                ->each(function (Purchase $purchase) use (&$summary): void {
                    $days = $purchase->credit_period_days ?: 30;

                    if ($purchase->purchase_date) {
                        $purchase->update([
                            'credit_period_days' => $days,
                            'credit_due_date' => $purchase->purchase_date->copy()->addDays($days)->toDateString(),
                        ]);
                        $summary['purchase_due_dates']++;
                    }
                });
        });

        foreach ($summary as $label => $count) {
            $this->line(str_pad(str_replace('_', ' ', ucfirst($label)), 28).': '.$count);
        }

        $this->newLine();
        $this->info('Imported data cleanup completed.');

        return self::SUCCESS;
    }
}
