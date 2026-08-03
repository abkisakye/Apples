<?php

namespace App\Console\Commands;

use App\Models\ProductUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncProductUnitCostsFromPurchases extends Command
{
    protected $signature = 'product-units:sync-costs-from-purchases
        {--dry-run : Preview changes without writing}
        {--commit : Write cost prices to product units}
        {--update-all : Replace existing non-zero cost prices with latest posted purchase cost}';

    protected $description = 'Sync product unit cost prices from latest valid posted purchase item costs.';

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('commit')) {
            $this->error('Use either --dry-run or --commit, not both.');

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $updateAll = (bool) $this->option('update-all');
        $candidates = $this->latestPurchaseCosts();
        $rows = [];
        $updated = 0;
        $skipped = 0;

        foreach ($candidates as $candidate) {
            $oldCost = round((float) $candidate->old_cost, 2);
            $newCost = round((float) $candidate->new_cost, 2);
            $shouldUpdate = $newCost > 0 && ($updateAll || $oldCost <= 0);

            if ($shouldUpdate) {
                $updated++;
            } else {
                $skipped++;
            }

            $rows[] = [
                $candidate->product_name,
                $candidate->unit_name,
                number_format($oldCost, 2),
                number_format($newCost, 2),
                $candidate->purchase_no.' / '.$candidate->purchase_date,
                $shouldUpdate ? ($commit ? 'Updated' : 'Would update') : 'Skipped',
            ];
        }

        if ($commit) {
            DB::transaction(function () use ($candidates, $updateAll): void {
                foreach ($candidates as $candidate) {
                    $oldCost = round((float) $candidate->old_cost, 2);
                    $newCost = round((float) $candidate->new_cost, 2);

                    if ($newCost <= 0 || (! $updateAll && $oldCost > 0)) {
                        continue;
                    }

                    ProductUnit::query()
                        ->whereKey((int) $candidate->product_unit_id)
                        ->update(['cost_price' => $newCost]);
                }
            });
        }

        $missingCostRows = ProductUnit::query()
            ->where(fn ($query) => $query->whereNull('cost_price')->orWhere('cost_price', '<=', 0))
            ->count();

        $this->info($commit ? 'Product unit cost sync committed.' : 'Dry-run only. No product unit costs were changed.');
        $this->table(
            ['Product', 'Unit', 'Old Cost', 'New Cost', 'Purchase Used', 'Action'],
            $rows
        );
        $this->line('Rows considered: '.$candidates->count());
        $this->line(($commit ? 'Updated' : 'Would update').': '.$updated);
        $this->line('Skipped: '.$skipped);
        $this->line('Missing/zero cost rows after current data: '.$missingCostRows);

        return self::SUCCESS;
    }

    private function latestPurchaseCosts(): Collection
    {
        return DB::table('purchase_items')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('product_units', 'product_units.id', '=', 'purchase_items.product_unit_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->where('purchases.status', 'posted')
            ->where('purchase_items.unit_cost', '>', 0)
            ->select([
                'purchase_items.product_unit_id',
                'products.name as product_name',
                'product_units.unit_name',
                'product_units.cost_price as old_cost',
                'purchase_items.unit_cost as new_cost',
                'purchases.purchase_no',
                'purchases.purchase_date',
                'purchase_items.id as purchase_item_id',
            ])
            ->orderBy('purchase_items.product_unit_id')
            ->orderByDesc('purchases.purchase_date')
            ->orderByDesc('purchase_items.id')
            ->get()
            ->unique('product_unit_id')
            ->values();
    }
}
