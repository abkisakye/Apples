<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\PaymentMode;
use App\Models\Purchase;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ConvertOpeningStockPurchasesToPaid extends Command
{
    private const NOTE_MARKER = '[opening-stock-paid-conversion]';

    protected $signature = 'purchases:convert-opening-stock-to-paid
        {--dry-run : Show matching purchases without changing data}
        {--commit : Apply the conversion}
        {--supplier= : Match supplier name exactly}
        {--date= : Match one purchase date}
        {--from-date= : Match purchases from this date}
        {--to-date= : Match purchases up to this date}
        {--purchase-no= : Match one or more comma-separated purchase numbers}
        {--only-outstanding : Only include purchases with a positive balance}
        {--payment-mode=Cash : Payment mode to set on converted purchases}
        {--note=Opening stock already paid before system start : Note appended to converted purchases}';

    protected $description = 'Safely convert selected opening/current-stock purchase documents from credit/unpaid to cash/paid.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $commit = (bool) $this->option('commit');

        if ($dryRun && $commit) {
            $this->error('Choose either --dry-run or --commit, not both.');

            return self::FAILURE;
        }

        if (! $dryRun && ! $commit) {
            $dryRun = true;
        }

        if ($commit && ! $this->hasNarrowingSelector()) {
            $this->error('For safety, --commit requires at least one selector: --supplier, --date, --from-date, --to-date, or --purchase-no.');

            return self::FAILURE;
        }

        $purchases = $this->matchingPurchases()->get();

        $this->line(($dryRun ? 'Dry run' : 'Commit').' mode');
        $this->line('Matched purchases: '.$purchases->count());

        if ($purchases->isEmpty()) {
            $this->info('No matching opening-stock purchase documents found.');

            return self::SUCCESS;
        }

        $this->table(
            ['Purchase No', 'Date', 'Supplier', 'Type', 'Total', 'Paid', 'Balance'],
            $purchases->map(fn (Purchase $purchase) => [
                $purchase->purchase_no,
                optional($purchase->purchase_date)->toDateString(),
                $purchase->supplier?->name ?? '-',
                $purchase->purchase_type,
                number_format((float) $purchase->total_amount, 2),
                number_format((float) $purchase->amount_paid, 2),
                number_format((float) $purchase->balance_due, 2),
            ])->all()
        );

        if ($dryRun) {
            $this->warn('No database changes were made. Rerun with --commit and the same selectors to apply.');

            return self::SUCCESS;
        }

        $paymentMode = PaymentMode::query()->firstOrCreate(
            ['name' => (string) $this->option('payment-mode')],
            ['is_active' => true]
        );
        $note = trim((string) $this->option('note'));
        $converted = 0;

        DB::transaction(function () use ($purchases, $paymentMode, $note, &$converted): void {
            foreach ($purchases as $purchase) {
                $total = round((float) $purchase->total_amount, 2);

                if (
                    $purchase->purchase_type === 'cash'
                    && round((float) $purchase->balance_due, 2) <= 0
                    && round((float) $purchase->amount_paid, 2) >= $total
                ) {
                    continue;
                }

                $before = [
                    'purchase_type' => $purchase->purchase_type,
                    'payment_mode_id' => $purchase->payment_mode_id,
                    'amount_paid' => (float) $purchase->amount_paid,
                    'balance_due' => (float) $purchase->balance_due,
                    'credit_period_days' => $purchase->credit_period_days,
                    'credit_due_date' => optional($purchase->credit_due_date)->toDateString(),
                ];

                $purchase->update([
                    'purchase_type' => 'cash',
                    'payment_mode_id' => $paymentMode->id,
                    'amount_paid' => $total,
                    'balance_due' => 0,
                    'credit_period_days' => null,
                    'credit_due_date' => null,
                    'remarks' => $this->appendNote($purchase->remarks, $note),
                ]);

                ActivityLog::create([
                    'event' => 'purchase.opening_stock_marked_paid',
                    'subject_type' => Purchase::class,
                    'subject_id' => $purchase->id,
                    'description' => "Purchase {$purchase->purchase_no} marked as paid opening stock.",
                    'properties' => [
                        'before' => $before,
                        'after' => [
                            'purchase_type' => 'cash',
                            'payment_mode_id' => $paymentMode->id,
                            'amount_paid' => $total,
                            'balance_due' => 0,
                        ],
                        'note' => $note,
                    ],
                ]);

                $converted++;
            }
        });

        $this->info("Converted purchases: {$converted}");
        $this->line('Purchase items and inventory transactions were not changed.');
        $this->line('Supplier payment rows were not created because supplier statements already use purchases.amount_paid as paid-on-purchase credit.');

        return self::SUCCESS;
    }

    private function matchingPurchases(): Builder
    {
        $purchaseNumbers = collect(explode(',', (string) $this->option('purchase-no')))
            ->map(fn (string $value) => trim($value))
            ->filter()
            ->values();

        return Purchase::query()
            ->with(['supplier:id,name', 'paymentMode:id,name'])
            ->where('status', 'posted')
            ->where(function (Builder $query): void {
                $query->where('purchase_type', 'credit')
                    ->orWhere('balance_due', '>', 0);
            })
            ->when($this->option('supplier'), function (Builder $query, string $supplier): void {
                $query->whereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery
                    ->whereRaw('LOWER(name) = ?', [strtolower(trim($supplier))]));
            })
            ->when($this->option('date'), fn (Builder $query, string $date) => $query
                ->whereDate('purchase_date', Carbon::parse($date)->toDateString()))
            ->when($this->option('from-date'), fn (Builder $query, string $date) => $query
                ->whereDate('purchase_date', '>=', Carbon::parse($date)->toDateString()))
            ->when($this->option('to-date'), fn (Builder $query, string $date) => $query
                ->whereDate('purchase_date', '<=', Carbon::parse($date)->toDateString()))
            ->when($purchaseNumbers->isNotEmpty(), fn (Builder $query) => $query
                ->whereIn('purchase_no', $purchaseNumbers->all()))
            ->when($this->option('only-outstanding'), fn (Builder $query) => $query
                ->where('balance_due', '>', 0))
            ->orderBy('purchase_date')
            ->orderBy('purchase_no');
    }

    private function hasNarrowingSelector(): bool
    {
        foreach (['supplier', 'date', 'from-date', 'to-date', 'purchase-no'] as $option) {
            if (trim((string) $this->option($option)) !== '') {
                return true;
            }
        }

        return false;
    }

    private function appendNote(?string $existingRemarks, string $note): string
    {
        $existingRemarks = trim((string) $existingRemarks);
        $note = trim($note) !== '' ? trim($note) : 'Opening stock already paid before system start';
        $conversionNote = self::NOTE_MARKER.' '.$note;

        if (str_contains($existingRemarks, self::NOTE_MARKER)) {
            return $existingRemarks;
        }

        return trim($existingRemarks === '' ? $conversionNote : $existingRemarks.PHP_EOL.$conversionNote);
    }
}
