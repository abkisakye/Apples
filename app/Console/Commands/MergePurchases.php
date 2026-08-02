<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Purchase;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Throwable;

class MergePurchases extends Command
{
    protected $signature = 'purchases:merge
        {--target= : Purchase number that will remain}
        {--supplier= : Exact supplier name to select}
        {--from= : First purchase date in YYYY-MM-DD format}
        {--to= : Last purchase date in YYYY-MM-DD format}
        {--exclude=* : Purchase number to protect; repeat this option when needed}
        {--dry-run : Show and validate the merge without changing data}
        {--commit : Perform the validated merge}';

    protected $description = 'Safely consolidate posted purchases into one purchase without reposting stock';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $commit = (bool) $this->option('commit');

        if ($dryRun === $commit) {
            $this->error('Choose exactly one mode: --dry-run or --commit.');

            return SymfonyCommand::FAILURE;
        }

        $targetNo = trim((string) $this->option('target'));
        $supplierName = trim((string) $this->option('supplier'));
        $from = $this->normaliseDate((string) $this->option('from'));
        $to = $this->normaliseDate((string) $this->option('to'));
        $excludedNumbers = collect((array) $this->option('exclude'))
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->values();

        if ($targetNo === '' || $supplierName === '' || $from === null || $to === null) {
            $this->error('Required options: --target, --supplier, --from and --to.');
            $this->line('Dates must use YYYY-MM-DD.');

            return SymfonyCommand::FAILURE;
        }

        if ($from > $to) {
            $this->error('--from cannot be later than --to.');

            return SymfonyCommand::FAILURE;
        }

        if ($excludedNumbers->contains($targetNo)) {
            $this->error('The target purchase cannot also be excluded.');

            return SymfonyCommand::FAILURE;
        }

        try {
            $plan = $this->buildPlan(
                $targetNo,
                $supplierName,
                $from,
                $to,
                $excludedNumbers,
                false
            );

            $this->displayPlan($plan, $dryRun ? 'DRY RUN' : 'COMMIT REQUESTED');

            if ($dryRun) {
                $this->newLine();
                $this->info('Validation passed. No database changes were made.');
                $this->line('Confirmation: no data changed.');

                return SymfonyCommand::SUCCESS;
            }

            $this->newLine();
            $this->warn('This will move existing purchase items, update inventory reference numbers, and delete the merged source purchase headers.');
            $this->warn('It will not create or repost stock quantities.');

            if (! app()->runningUnitTests()
                && ! $this->confirm("Proceed with merging into {$targetNo}?", false)) {
                $this->info('Merge cancelled. No database changes were made.');

                return SymfonyCommand::SUCCESS;
            }

            $snapshotPath = null;

            DB::transaction(function () use (
                $targetNo,
                $supplierName,
                $from,
                $to,
                $excludedNumbers,
                $plan,
                &$snapshotPath
            ): void {
                $lockedPlan = $this->buildPlan(
                    $targetNo,
                    $supplierName,
                    $from,
                    $to,
                    $excludedNumbers,
                    true
                );

                if ($lockedPlan['plan_hash'] !== $plan['plan_hash']) {
                    throw new RuntimeException(
                        'The selected purchase data changed after the dry validation. Nothing was merged; run --dry-run again.'
                    );
                }

                $snapshotPath = $this->writeSnapshot($lockedPlan);
                $this->performMerge($lockedPlan);
                $this->verifyMerge($lockedPlan);
            }, 3);

            $this->newLine();
            $this->info("Purchase merge completed successfully into {$targetNo}.");
            $this->line('Snapshot: '.$snapshotPath);
            $this->line('Final total: UGX '.$this->money($plan['totals']['total']));
            $this->line('Final paid: UGX '.$this->money($plan['totals']['paid']));
            $this->line('Final balance: UGX '.$this->money($plan['totals']['balance']));
            $this->line('Final item count: '.$plan['counts']['final_items']);

            return SymfonyCommand::SUCCESS;
        } catch (Throwable $exception) {
            $this->newLine();
            $this->error($exception->getMessage());
            $this->line('No partial merge should remain because commit mode runs inside one database transaction.');

            return SymfonyCommand::FAILURE;
        }
    }

    /**
     * @param Collection<int, string> $excludedNumbers
     * @return array<string, mixed>
     */
    private function buildPlan(
        string $targetNo,
        string $supplierName,
        string $from,
        string $to,
        Collection $excludedNumbers,
        bool $lock
    ): array {
        $supplierKey = strtoupper(trim($supplierName));

        $query = Purchase::query()
            ->with('supplier:id,name')
            ->whereHas('supplier', function ($supplierQuery) use ($supplierKey): void {
                $supplierQuery->whereRaw('UPPER(TRIM(name)) = ?', [$supplierKey]);
            })
            ->whereBetween('purchase_date', [$from, $to])
            ->where('status', 'posted')
            ->when(
                $excludedNumbers->isNotEmpty(),
                fn ($purchaseQuery) => $purchaseQuery->whereNotIn('purchase_no', $excludedNumbers->all())
            )
            ->orderBy('purchase_date')
            ->orderBy('id');

        if ($lock) {
            $query->lockForUpdate();
        }

        /** @var Collection<int, Purchase> $purchases */
        $purchases = $query->get();

        if ($purchases->isEmpty()) {
            throw new RuntimeException('No posted purchases matched the supplier and date range.');
        }

        /** @var Purchase|null $target */
        $target = $purchases->firstWhere('purchase_no', $targetNo);

        if (! $target) {
            throw new RuntimeException(
                "Target {$targetNo} is not inside the selected supplier/date range or is not posted."
            );
        }

        $protectedQuery = Purchase::query()
            ->with('supplier:id,name')
            ->when(
                $excludedNumbers->isNotEmpty(),
                fn ($purchaseQuery) => $purchaseQuery->whereIn('purchase_no', $excludedNumbers->all()),
                fn ($purchaseQuery) => $purchaseQuery->whereRaw('1 = 0')
            )
            ->orderBy('id');

        if ($lock) {
            $protectedQuery->lockForUpdate();
        }

        /** @var Collection<int, Purchase> $protectedPurchases */
        $protectedPurchases = $protectedQuery->get();

        if ($protectedPurchases->count() !== $excludedNumbers->count()) {
            $found = $protectedPurchases->pluck('purchase_no');
            $missing = $excludedNumbers->diff($found)->implode(', ');

            throw new RuntimeException('Excluded purchase not found: '.$missing);
        }

        $selectedIds = $purchases->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $sourcePurchases = $purchases
            ->reject(fn (Purchase $purchase): bool => $purchase->id === $target->id)
            ->values();
        $sourceIds = $sourcePurchases->pluck('id')->map(fn ($id): int => (int) $id)->values();

        if ($sourcePurchases->isEmpty()) {
            throw new RuntimeException('There are no source purchases to merge into the target.');
        }

        $this->validatePurchaseHeaders($purchases, $supplierKey);
        $this->validatePurchaseRelationships($selectedIds);

        $items = DB::table('purchase_items')
            ->whereIn('purchase_id', $selectedIds->all())
            ->orderBy('id')
            ->get();

        $itemsByPurchase = $items->groupBy(fn (object $item): int => (int) $item->purchase_id);

        foreach ($purchases as $purchase) {
            $purchaseItems = $itemsByPurchase->get((int) $purchase->id, collect());

            if ($purchaseItems->isEmpty()) {
                throw new RuntimeException("Purchase {$purchase->purchase_no} has no purchase items.");
            }

            $itemSubtotal = $purchaseItems->sum(
                fn (object $item): int => $this->cents($item->line_total)
            );

            if ($itemSubtotal !== $this->cents($purchase->subtotal)) {
                throw new RuntimeException(
                    "Purchase {$purchase->purchase_no} item lines total UGX {$this->money($itemSubtotal)}, ".
                    "but its subtotal is UGX {$this->money($this->cents($purchase->subtotal))}."
                );
            }

            $calculatedTotal = $this->cents($purchase->subtotal)
                - $this->cents($purchase->discount_amount)
                + $this->cents($purchase->vat_amount);

            if ($calculatedTotal !== $this->cents($purchase->total_amount)) {
                throw new RuntimeException(
                    "Purchase {$purchase->purchase_no} header total does not equal subtotal - discount + VAT."
                );
            }
        }

        $sourceItems = $items
            ->whereIn('purchase_id', $sourceIds->all())
            ->values();
        $sourceItemIds = $sourceItems
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $allItemIds = $items
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $inventoryTransactions = DB::table('inventory_transactions')
            ->where('reference_type', 'purchase')
            ->whereIn('reference_id', $allItemIds->all())
            ->orderBy('id')
            ->get();

        $inventoryByItem = $inventoryTransactions
            ->groupBy(fn (object $transaction): int => (int) $transaction->reference_id);

        $purchaseNumberByItem = $items->mapWithKeys(function (object $item) use ($purchases): array {
            $purchase = $purchases->firstWhere('id', (int) $item->purchase_id);

            return [(int) $item->id => (string) $purchase?->purchase_no];
        });

        foreach ($allItemIds as $itemId) {
            $transactions = $inventoryByItem->get($itemId, collect());

            if ($transactions->count() !== 1) {
                throw new RuntimeException(
                    "Purchase item {$itemId} must have exactly one purchase inventory transaction; found {$transactions->count()}."
                );
            }

            $transaction = $transactions->first();
            $expectedReferenceNo = (string) $purchaseNumberByItem->get($itemId);

            if ((string) $transaction->reference_no !== $expectedReferenceNo) {
                throw new RuntimeException(
                    "Inventory transaction {$transaction->id} has reference {$transaction->reference_no}; ".
                    "expected {$expectedReferenceNo}."
                );
            }
        }

        $totals = [
            'subtotal' => $purchases->sum(fn (Purchase $purchase): int => $this->cents($purchase->subtotal)),
            'discount' => $purchases->sum(fn (Purchase $purchase): int => $this->cents($purchase->discount_amount)),
            'vat' => $purchases->sum(fn (Purchase $purchase): int => $this->cents($purchase->vat_amount)),
            'total' => $purchases->sum(fn (Purchase $purchase): int => $this->cents($purchase->total_amount)),
            'paid' => $purchases->sum(fn (Purchase $purchase): int => $this->cents($purchase->amount_paid)),
            'balance' => $purchases->sum(fn (Purchase $purchase): int => $this->cents($purchase->balance_due)),
        ];

        if ($totals['subtotal'] - $totals['discount'] + $totals['vat'] !== $totals['total']) {
            throw new RuntimeException('Combined purchase totals do not reconcile.');
        }

        if ($totals['balance'] !== 0 || $totals['paid'] !== $totals['total']) {
            throw new RuntimeException(
                'All selected purchases must be fully paid with zero balance before this merge.'
            );
        }

        $protectedSnapshot = $this->protectedSnapshot($protectedPurchases);
        $inventoryIntegrityHash = $this->inventoryIntegrityHash($inventoryTransactions);

        $zeroTotalPurchases = $purchases
            ->filter(fn (Purchase $purchase): bool => $this->cents($purchase->total_amount) === 0)
            ->pluck('purchase_no')
            ->values();

        $planCore = [
            'target_id' => (int) $target->id,
            'target_no' => (string) $target->purchase_no,
            'selected_ids' => $selectedIds->all(),
            'source_ids' => $sourceIds->all(),
            'source_item_ids' => $sourceItemIds->all(),
            'totals' => $totals,
            'selected_count' => $purchases->count(),
            'source_count' => $sourcePurchases->count(),
            'selected_item_count' => $items->count(),
            'source_item_count' => $sourceItems->count(),
            'inventory_count' => $inventoryTransactions->count(),
            'inventory_integrity_hash' => $inventoryIntegrityHash,
            'protected_hash' => $this->stableHash($protectedSnapshot),
        ];

        return [
            'target' => $target,
            'purchases' => $purchases,
            'source_purchases' => $sourcePurchases,
            'protected_purchases' => $protectedPurchases,
            'items' => $items,
            'source_items' => $sourceItems,
            'inventory_transactions' => $inventoryTransactions,
            'inventory_integrity_hash' => $inventoryIntegrityHash,
            'protected_snapshot' => $protectedSnapshot,
            'zero_total_purchases' => $zeroTotalPurchases,
            'totals' => $totals,
            'counts' => [
                'selected_purchases' => $purchases->count(),
                'source_purchases' => $sourcePurchases->count(),
                'source_items' => $sourceItems->count(),
                'final_items' => $items->count(),
                'inventory_transactions' => $inventoryTransactions->count(),
            ],
            'plan_hash' => $this->stableHash($planCore),
        ];
    }

    /**
     * @param Collection<int, Purchase> $purchases
     */
    private function validatePurchaseHeaders(Collection $purchases, string $supplierKey): void
    {
        $first = $purchases->first();

        foreach ($purchases as $purchase) {
            if (strtoupper(trim((string) $purchase->supplier?->name)) !== $supplierKey) {
                throw new RuntimeException("Purchase {$purchase->purchase_no} has the wrong supplier.");
            }

            if ((int) $purchase->supplier_id !== (int) $first->supplier_id) {
                throw new RuntimeException('Selected purchases do not all have the same supplier ID.');
            }

            if ((int) $purchase->store_id !== (int) $first->store_id) {
                throw new RuntimeException('Selected purchases do not all belong to the same store.');
            }

            if ((int) $purchase->payment_mode_id !== (int) $first->payment_mode_id) {
                throw new RuntimeException('Selected purchases do not all use the same payment mode.');
            }

            if ((string) $purchase->status !== 'posted') {
                throw new RuntimeException("Purchase {$purchase->purchase_no} is not posted.");
            }

            if ($purchase->corrected_from_purchase_id !== null || $purchase->replaced_by_purchase_id !== null) {
                throw new RuntimeException(
                    "Purchase {$purchase->purchase_no} participates in a correction chain and cannot be merged."
                );
            }

            if ($this->cents($purchase->balance_due) !== 0) {
                throw new RuntimeException("Purchase {$purchase->purchase_no} still has a balance.");
            }

            if ($this->cents($purchase->amount_paid) !== $this->cents($purchase->total_amount)) {
                throw new RuntimeException("Purchase {$purchase->purchase_no} is not fully paid.");
            }
        }
    }

    /**
     * @param Collection<int, int> $selectedIds
     */
    private function validatePurchaseRelationships(Collection $selectedIds): void
    {
        $ids = $selectedIds->all();

        $paymentCount = DB::table('supplier_payments')
            ->whereIn('purchase_id', $ids)
            ->count();

        if ($paymentCount > 0) {
            throw new RuntimeException(
                "Selected purchases have {$paymentCount} supplier-payment record(s). Merge aborted."
            );
        }

        $returnCount = DB::table('purchase_returns')
            ->whereIn('purchase_id', $ids)
            ->count();

        if ($returnCount > 0) {
            throw new RuntimeException(
                "Selected purchases have {$returnCount} purchase-return record(s). Merge aborted."
            );
        }

        $correctionReferenceCount = Purchase::query()
            ->whereNotIn('id', $ids)
            ->where(function ($query) use ($ids): void {
                $query->whereIn('corrected_from_purchase_id', $ids)
                    ->orWhereIn('replaced_by_purchase_id', $ids);
            })
            ->count();

        if ($correctionReferenceCount > 0) {
            throw new RuntimeException(
                'Another purchase references one of the selected purchases through a correction relationship.'
            );
        }

        $allowedTables = collect([
            'purchase_items',
            'supplier_payments',
            'purchase_returns',
        ]);

        foreach ($this->tablesContainingColumn('purchase_id')->diff($allowedTables) as $table) {
            $count = DB::table((string) $table)
                ->whereIn('purchase_id', $ids)
                ->count();

            if ($count > 0) {
                throw new RuntimeException(
                    "Unexpected purchase_id references found in table {$table}: " .
                    "{$count} selected purchase reference(s)."
                );
            }
        }
    }

    /**
     * Return database tables containing the requested column.
     *
     * Supports MySQL in development/production and SQLite in automated tests.
     *
     * @return Collection<int, string>
     */
    private function tablesContainingColumn(string $column): Collection
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            return collect(DB::select(
                <<<'SQL'
                    SELECT TABLE_NAME AS table_name
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                      AND COLUMN_NAME = ?
                    ORDER BY TABLE_NAME
                SQL,
                [$column]
            ))
                ->pluck('table_name')
                ->map(fn (mixed $table): string => (string) $table)
                ->filter()
                ->values();
        }

        if ($driver === 'sqlite') {
            $tables = collect(DB::select(
                <<<'SQL'
                    SELECT name AS table_name
                    FROM sqlite_master
                    WHERE type = 'table'
                      AND name NOT LIKE 'sqlite_%'
                    ORDER BY name
                SQL
            ))->pluck('table_name');

            return $tables
                ->map(fn (mixed $table): string => (string) $table)
                ->filter(fn (string $table): bool => Schema::hasColumn($table, $column))
                ->values();
        }

        return collect(Schema::getTables())
            ->map(function (array $table): ?string {
                return isset($table['name'])
                    ? (string) $table['name']
                    : (isset($table['table_name']) ? (string) $table['table_name'] : null);
            })
            ->filter(fn (?string $table): bool => $table !== null && Schema::hasColumn($table, $column))
            ->values();
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function displayPlan(array $plan, string $mode): void
    {
        /** @var Purchase $target */
        $target = $plan['target'];

        $this->newLine();
        $this->info("PURCHASE MERGE — {$mode}");
        $this->line(str_repeat('=', 72));
        $this->line('Target: '.$target->purchase_no);
        $this->line('Supplier: '.$target->supplier?->name);
        $this->line('Date: '.$target->purchase_date?->format('Y-m-d'));
        $this->newLine();

        $this->table(
            ['Measure', 'Value'],
            [
                ['Selected purchases', $plan['counts']['selected_purchases']],
                ['Source purchases to remove', $plan['counts']['source_purchases']],
                ['Source items to move', $plan['counts']['source_items']],
                ['Final target item count', $plan['counts']['final_items']],
                ['Inventory transactions retained', $plan['counts']['inventory_transactions']],
                ['Subtotal', 'UGX '.$this->money($plan['totals']['subtotal'])],
                ['Discount', 'UGX '.$this->money($plan['totals']['discount'])],
                ['VAT', 'UGX '.$this->money($plan['totals']['vat'])],
                ['Final total', 'UGX '.$this->money($plan['totals']['total'])],
                ['Final paid', 'UGX '.$this->money($plan['totals']['paid'])],
                ['Final balance', 'UGX '.$this->money($plan['totals']['balance'])],
            ]
        );

        /** @var Collection<int, Purchase> $protectedPurchases */
        $protectedPurchases = $plan['protected_purchases'];

        if ($protectedPurchases->isNotEmpty()) {
            $this->newLine();
            $this->warn('Protected purchases — these will not be changed:');
            $this->table(
                ['Purchase', 'Supplier', 'Total', 'Paid', 'Balance', 'Status'],
                $protectedPurchases->map(fn (Purchase $purchase): array => [
                    $purchase->purchase_no,
                    $purchase->supplier?->name,
                    'UGX '.$this->money($this->cents($purchase->total_amount)),
                    'UGX '.$this->money($this->cents($purchase->amount_paid)),
                    'UGX '.$this->money($this->cents($purchase->balance_due)),
                    $purchase->status,
                ])->all()
            );
        }

        /** @var Collection<int, string> $zeroTotalPurchases */
        $zeroTotalPurchases = $plan['zero_total_purchases'];

        if ($zeroTotalPurchases->isNotEmpty()) {
            $this->newLine();
            $this->warn('Zero-total purchase(s) whose item lines will still be preserved:');
            foreach ($zeroTotalPurchases as $purchaseNo) {
                $this->line(' - '.$purchaseNo);
            }
        }

        $this->newLine();
        $this->line('Purchase count: '.$plan['counts']['selected_purchases']);
        $this->line('Source count: '.$plan['counts']['source_purchases']);
        $this->line(
            'Zero-total purchases: '.(
                $zeroTotalPurchases->isEmpty()
                    ? 'none'
                    : $zeroTotalPurchases->implode(', ')
            )
        );
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function performMerge(array $plan): void
    {
        /** @var Purchase $target */
        $target = $plan['target'];
        /** @var Collection<int, Purchase> $sourcePurchases */
        $sourcePurchases = $plan['source_purchases'];
        /** @var Collection<int, object> $sourceItems */
        $sourceItems = $plan['source_items'];

        $sourceIds = $sourcePurchases
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $sourceItemIds = $sourceItems
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $movedItems = DB::table('purchase_items')
            ->whereIn('id', $sourceItemIds->all())
            ->update([
                'purchase_id' => $target->id,
                'updated_at' => now(),
            ]);

        if ($movedItems !== $sourceItemIds->count()) {
            throw new RuntimeException(
                "Expected to move {$sourceItemIds->count()} purchase items, but moved {$movedItems}."
            );
        }

        $updatedInventory = DB::table('inventory_transactions')
            ->where('reference_type', 'purchase')
            ->whereIn('reference_id', $sourceItemIds->all())
            ->update([
                'reference_no' => $target->purchase_no,
                'updated_at' => now(),
            ]);

        if ($updatedInventory !== $sourceItemIds->count()) {
            throw new RuntimeException(
                "Expected to update {$sourceItemIds->count()} inventory references, but updated {$updatedInventory}."
            );
        }

        $target->forceFill([
            'purchase_type' => 'cash',
            'subtotal' => $this->decimal($plan['totals']['subtotal']),
            'discount_amount' => $this->decimal($plan['totals']['discount']),
            'vat_amount' => $this->decimal($plan['totals']['vat']),
            'total_amount' => $this->decimal($plan['totals']['total']),
            'amount_paid' => $this->decimal($plan['totals']['paid']),
            'balance_due' => $this->decimal($plan['totals']['balance']),
            'credit_period_days' => null,
            'credit_due_date' => null,
            'remarks' => $this->mergedRemarks($target, $sourcePurchases),
            'updated_at' => now(),
        ])->save();

        $deletedHeaders = DB::table('purchases')
            ->whereIn('id', $sourceIds->all())
            ->delete();

        if ($deletedHeaders !== $sourceIds->count()) {
            throw new RuntimeException(
                "Expected to delete {$sourceIds->count()} source purchase headers, but deleted {$deletedHeaders}."
            );
        }
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function verifyMerge(array $plan): void
    {
        /** @var Purchase $originalTarget */
        $originalTarget = $plan['target'];
        /** @var Collection<int, Purchase> $sourcePurchases */
        $sourcePurchases = $plan['source_purchases'];
        /** @var Collection<int, object> $items */
        $items = $plan['items'];

        $sourceIds = $sourcePurchases
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();
        $allItemIds = $items
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        if (DB::table('purchases')->whereIn('id', $sourceIds->all())->exists()) {
            throw new RuntimeException('One or more source purchase headers still exist after the merge.');
        }

        $target = Purchase::query()->findOrFail($originalTarget->id);

        if ($this->cents($target->total_amount) !== $plan['totals']['total']
            || $this->cents($target->amount_paid) !== $plan['totals']['paid']
            || $this->cents($target->balance_due) !== $plan['totals']['balance']
            || $target->purchase_type !== 'cash'
            || $target->status !== 'posted') {
            throw new RuntimeException('The target purchase totals or status failed final verification.');
        }

        $finalItemCount = DB::table('purchase_items')
            ->where('purchase_id', $target->id)
            ->count();

        if ($finalItemCount !== $plan['counts']['final_items']) {
            throw new RuntimeException(
                "Final target item count is {$finalItemCount}; expected {$plan['counts']['final_items']}."
            );
        }

        $inventoryTransactions = DB::table('inventory_transactions')
            ->where('reference_type', 'purchase')
            ->whereIn('reference_id', $allItemIds->all())
            ->orderBy('id')
            ->get();

        if ($inventoryTransactions->count() !== $plan['counts']['inventory_transactions']) {
            throw new RuntimeException('Inventory transaction count changed during the merge.');
        }

        if ($inventoryTransactions->contains(
            fn (object $transaction): bool => (string) $transaction->reference_no !== $target->purchase_no
        )) {
            throw new RuntimeException('One or more inventory reference numbers were not moved to the target.');
        }

        if ($this->inventoryIntegrityHash($inventoryTransactions) !== $plan['inventory_integrity_hash']) {
            throw new RuntimeException('Inventory quantities, costs, products, dates, or movement details changed.');
        }

        /** @var Collection<int, Purchase> $protectedPurchases */
        $protectedPurchases = $plan['protected_purchases'];
        $freshProtected = Purchase::query()
            ->with('supplier:id,name')
            ->whereIn('id', $protectedPurchases->pluck('id')->all())
            ->orderBy('id')
            ->get();

        if ($this->stableHash($this->protectedSnapshot($freshProtected))
            !== $this->stableHash($plan['protected_snapshot'])) {
            throw new RuntimeException('A protected purchase changed during the merge.');
        }
    }

    /**
     * @param Collection<int, Purchase> $protectedPurchases
     * @return array<string, mixed>
     */
    private function protectedSnapshot(Collection $protectedPurchases): array
    {
        $purchaseIds = $protectedPurchases
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $items = $purchaseIds->isEmpty()
            ? collect()
            : DB::table('purchase_items')
                ->whereIn('purchase_id', $purchaseIds->all())
                ->orderBy('id')
                ->get();

        $itemIds = $items
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $inventoryTransactions = $itemIds->isEmpty()
            ? collect()
            : DB::table('inventory_transactions')
                ->where('reference_type', 'purchase')
                ->whereIn('reference_id', $itemIds->all())
                ->orderBy('id')
                ->get();

        return [
            'purchases' => $protectedPurchases
                ->map(fn (Purchase $purchase): array => $purchase->getAttributes())
                ->values()
                ->all(),
            'items' => $items->map(fn (object $row): array => (array) $row)->all(),
            'inventory_transactions' => $inventoryTransactions
                ->map(fn (object $row): array => (array) $row)
                ->all(),
        ];
    }

    /**
     * @param Collection<int, object> $inventoryTransactions
     */
    private function inventoryIntegrityHash(Collection $inventoryTransactions): string
    {
        $rows = $inventoryTransactions->map(fn (object $transaction): array => [
            'id' => (int) $transaction->id,
            'transaction_date' => (string) $transaction->transaction_date,
            'store_id' => (int) $transaction->store_id,
            'product_id' => (int) $transaction->product_id,
            'product_unit_id' => $transaction->product_unit_id === null
                ? null
                : (int) $transaction->product_unit_id,
            'reference_type' => (string) $transaction->reference_type,
            'reference_id' => (int) $transaction->reference_id,
            'movement_type' => (string) $transaction->movement_type,
            'quantity_in' => (string) $transaction->quantity_in,
            'quantity_out' => (string) $transaction->quantity_out,
            'base_quantity_in' => (string) $transaction->base_quantity_in,
            'base_quantity_out' => (string) $transaction->base_quantity_out,
            'conversion_factor_snapshot' => (string) $transaction->conversion_factor_snapshot,
            'unit_cost' => (string) $transaction->unit_cost,
            'unit_price' => $transaction->unit_price === null
                ? null
                : (string) $transaction->unit_price,
            'remarks' => $transaction->remarks === null
                ? null
                : (string) $transaction->remarks,
            'created_by' => $transaction->created_by === null
                ? null
                : (int) $transaction->created_by,
            'created_at' => (string) $transaction->created_at,
        ])->values()->all();

        return $this->stableHash($rows);
    }

    /**
     * @param array<string, mixed> $plan
     */
    private function writeSnapshot(array $plan): string
    {
        /** @var Purchase $target */
        $target = $plan['target'];
        $directory = storage_path('app/purchase-merges');

        File::ensureDirectoryExists($directory);

        $safeTarget = preg_replace('/[^A-Za-z0-9_-]/', '_', $target->purchase_no);
        $filename = sprintf(
            '%s/merge_%s_%s.json',
            $directory,
            $safeTarget,
            now()->format('Ymd_His_u')
        );

        $payload = [
            'created_at' => now()->toIso8601String(),
            'database' => DB::connection()->getDatabaseName(),
            'target_purchase_no' => $target->purchase_no,
            'plan_hash' => $plan['plan_hash'],
            'totals_in_cents' => $plan['totals'],
            'counts' => $plan['counts'],
            'purchases' => $plan['purchases']
                ->map(fn (Purchase $purchase): array => $purchase->getAttributes())
                ->values()
                ->all(),
            'purchase_items' => $plan['items']
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'inventory_transactions' => $plan['inventory_transactions']
                ->map(fn (object $row): array => (array) $row)
                ->all(),
            'protected' => $plan['protected_snapshot'],
        ];

        $json = json_encode(
            $payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );

        if (file_put_contents($filename, $json.PHP_EOL) === false) {
            throw new RuntimeException('Failed to write the pre-merge JSON snapshot.');
        }

        return $filename;
    }

    /**
     * @param Collection<int, Purchase> $sourcePurchases
     */
    private function mergedRemarks(Purchase $target, Collection $sourcePurchases): string
    {
        $existing = trim((string) $target->remarks);
        $dates = $sourcePurchases
            ->pluck('purchase_date')
            ->map(fn ($date): string => $date?->format('Y-m-d') ?? '')
            ->filter();

        $note = sprintf(
            'Consolidated %d opening purchase(s) into %s; source dates %s to %s.',
            $sourcePurchases->count(),
            $target->purchase_no,
            $dates->min(),
            $dates->max()
        );

        return $existing === '' ? $note : $existing.' | '.$note;
    }

    private function normaliseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = \DateTimeImmutable::getLastErrors();

        if (! $date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    private function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function money(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    private function decimal(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function stableHash(mixed $value): string
    {
        return hash(
            'sha256',
            json_encode($value, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)
        );
    }
}
