<?php

namespace App\Console\Commands;

use App\Models\ProductUnit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class EnableFractionalWholesaleProductUnits extends Command
{
    protected $signature = 'product-units:enable-fractional-wholesale
        {--dry-run : Preview changes without writing}
        {--commit : Write fractional wholesale settings}
        {--min=0.25 : Minimum wholesale quantity to apply where blank}
        {--precision=2 : Minimum quantity precision to apply}
        {--include= : Comma-separated extra unit names to include}
        {--exclude= : Comma-separated unit names to exclude}';

    protected $description = 'Safely enable fractional selling settings for likely wholesale product units.';

    private const PACK_KEYWORDS = [
        'box',
        'boxes',
        'carton',
        'cartons',
        'dozen',
        'dozens',
        'bag',
        'bags',
        'sack',
        'sacks',
        'packet',
        'packets',
        'pack',
        'packs',
        'crate',
        'crates',
        'case',
        'cases',
        'bundle',
        'bundles',
        'jerrican',
        'jerricans',
        'tin',
        'tins',
    ];

    private const RETAIL_UNITS = [
        'piece',
        'pieces',
        'pc',
        'pcs',
        'each',
        'single',
    ];

    public function handle(): int
    {
        if ($this->option('dry-run') && $this->option('commit')) {
            $this->error('Use either --dry-run or --commit, not both.');

            return self::FAILURE;
        }

        $commit = (bool) $this->option('commit');
        $minimum = round(max((float) $this->option('min'), 0), 3);
        $precision = max((int) $this->option('precision'), 0);
        $includeNames = $this->optionNames('include');
        $excludeNames = $this->optionNames('exclude');

        if ($minimum <= 0) {
            $this->error('--min must be greater than zero.');

            return self::FAILURE;
        }

        $units = ProductUnit::query()
            ->with('product:id,name')
            ->orderBy('id')
            ->get();

        $retailExcluded = 0;
        $candidates = collect();

        foreach ($units as $unit) {
            $unitName = (string) $unit->unit_name;
            $normalized = $this->normalizeName($unitName);

            if (in_array($normalized, $excludeNames, true) || $this->isRetailUnit($unitName)) {
                $retailExcluded++;
                continue;
            }

            if ($this->isPackUnit($unitName) || in_array($normalized, $includeNames, true)) {
                $candidates->push($unit);
            }
        }

        $rows = [];
        $wouldUpdate = 0;
        $skipped = 0;

        foreach ($candidates as $unit) {
            $newFractional = true;
            $newPrecision = max((int) $unit->quantity_precision, $precision);
            $oldMinimum = $unit->minimum_wholesale_quantity === null ? null : (float) $unit->minimum_wholesale_quantity;
            $newMinimum = $oldMinimum !== null && $oldMinimum > 0 ? $oldMinimum : $minimum;

            $shouldUpdate = ! (bool) $unit->allow_fractional_quantity
                || (int) $unit->quantity_precision < $precision
                || $oldMinimum === null
                || $oldMinimum <= 0;

            if ($shouldUpdate) {
                $wouldUpdate++;
            } else {
                $skipped++;
            }

            $rows[] = [
                $unit->product?->name ?? 'Unknown product',
                $unit->unit_name,
                $unit->allow_fractional_quantity ? 'Yes' : 'No',
                $newFractional ? 'Yes' : 'No',
                (string) (int) $unit->quantity_precision,
                (string) $newPrecision,
                $this->formatNullableQuantity($oldMinimum),
                $this->formatNullableQuantity($newMinimum),
                $shouldUpdate ? ($commit ? 'Updated' : 'Would Update') : 'Skipped',
            ];
        }

        if ($commit) {
            DB::transaction(function () use ($candidates, $precision, $minimum): void {
                foreach ($candidates as $unit) {
                    $oldMinimum = $unit->minimum_wholesale_quantity === null ? null : (float) $unit->minimum_wholesale_quantity;
                    $newPrecision = max((int) $unit->quantity_precision, $precision);
                    $newMinimum = $oldMinimum !== null && $oldMinimum > 0 ? $oldMinimum : $minimum;
                    $shouldUpdate = ! (bool) $unit->allow_fractional_quantity
                        || (int) $unit->quantity_precision < $precision
                        || $oldMinimum === null
                        || $oldMinimum <= 0;

                    if (! $shouldUpdate) {
                        continue;
                    }

                    ProductUnit::query()
                        ->whereKey($unit->id)
                        ->update([
                            'allow_fractional_quantity' => true,
                            'quantity_precision' => $newPrecision,
                            'minimum_wholesale_quantity' => $newMinimum,
                        ]);
                }
            });
        }

        $this->info($commit ? 'Fractional wholesale settings committed.' : 'Dry-run only. No product units were changed.');
        $this->table([
            'Product',
            'Unit',
            'Old fractional setting',
            'New fractional setting',
            'Old precision',
            'New precision',
            'Old minimum wholesale qty',
            'New minimum wholesale qty',
            'Action',
        ], $rows);
        $this->line('Rows considered: '.$candidates->count());
        $this->line(($commit ? 'Updated' : 'Would update').': '.$wouldUpdate);
        $this->line('Skipped: '.$skipped);
        $this->line('Excluded retail units: '.$retailExcluded);
        $this->line('Conversion factors changed: 0');
        $this->line('Selling prices changed: 0');
        $this->line('Cost prices changed: 0');
        $this->line('Inventory transactions changed: 0');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function optionNames(string $option): array
    {
        $value = trim((string) $this->option($option));

        if ($value === '') {
            return [];
        }

        return collect(explode(',', $value))
            ->map(fn (string $name) => $this->normalizeName($name))
            ->filter()
            ->values()
            ->all();
    }

    private function isPackUnit(string $unitName): bool
    {
        $normalized = $this->normalizeName($unitName);

        foreach (self::PACK_KEYWORDS as $keyword) {
            if (preg_match('/\b'.preg_quote($keyword, '/').'\b/', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isRetailUnit(string $unitName): bool
    {
        return in_array($this->normalizeName($unitName), self::RETAIL_UNITS, true);
    }

    private function normalizeName(string $name): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($name)) ?? '');
    }

    private function formatNullableQuantity(?float $quantity): string
    {
        if ($quantity === null) {
            return '-';
        }

        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.') ?: '0';
    }
}
