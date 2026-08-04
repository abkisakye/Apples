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
        {--rollback-excluded : Revert excluded units that were accidentally set to fractional 0.25 precision 2}
        {--min=0.25 : Minimum wholesale quantity to apply where blank}
        {--precision=2 : Minimum quantity precision to apply}
        {--include= : Comma-separated extra unit names to include}
        {--exclude= : Comma-separated unit names to exclude}';

    protected $description = 'Safely enable fractional selling settings for likely wholesale product units.';

    private const DEFAULT_INCLUDED_UNITS = [
        'box',
        'boxes',
        'carton',
        'cartons',
        'dozen',
        'dozens',
    ];

    private const DEFAULT_EXCLUDED_UNITS = [
        'bag',
        'bags',
        'sack',
        'sacks',
        'tin',
        'tins',
        'jerrican',
        'jerricans',
        'bottle',
        'bottles',
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
        $rollbackExcluded = (bool) $this->option('rollback-excluded');
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

        $excluded = 0;
        $candidates = collect();
        $excludedRows = collect();

        foreach ($units as $unit) {
            $unitName = (string) $unit->unit_name;
            $explicitlyIncluded = $this->matchesAnyName($unitName, $includeNames);
            $explicitlyExcluded = $this->matchesAnyName($unitName, $excludeNames);
            $defaultExcluded = $this->isDefaultExcludedUnit($unitName);

            if ($explicitlyExcluded || ($defaultExcluded && ($rollbackExcluded || ! $explicitlyIncluded))) {
                $excluded++;

                if ($rollbackExcluded) {
                    $candidates->push($unit);
                } else {
                    $excludedRows->push($unit);
                }

                continue;
            }

            if (! $rollbackExcluded && ($this->isDefaultIncludedUnit($unitName) || $explicitlyIncluded)) {
                $candidates->push($unit);
            }
        }

        $rows = [];
        $wouldUpdate = 0;
        $skipped = 0;

        foreach ($candidates as $unit) {
            $oldMinimum = $unit->minimum_wholesale_quantity === null ? null : (float) $unit->minimum_wholesale_quantity;
            $newFractional = $rollbackExcluded ? false : true;
            $newPrecision = $rollbackExcluded ? 0 : max((int) $unit->quantity_precision, $precision);
            $newMinimum = $rollbackExcluded ? null : ($oldMinimum !== null && $oldMinimum > 0 ? $oldMinimum : $minimum);
            $shouldUpdate = $rollbackExcluded
                ? $this->shouldRollbackExcludedUnit($unit, $oldMinimum)
                : $this->shouldEnableUnit($unit, $precision, $oldMinimum);

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
                $shouldUpdate
                    ? ($rollbackExcluded ? ($commit ? 'Rolled Back' : 'Would Rollback') : ($commit ? 'Updated' : 'Would Update'))
                    : 'Skipped',
            ];
        }

        foreach ($excludedRows as $unit) {
            $oldMinimum = $unit->minimum_wholesale_quantity === null ? null : (float) $unit->minimum_wholesale_quantity;
            $rows[] = [
                $unit->product?->name ?? 'Unknown product',
                $unit->unit_name,
                $unit->allow_fractional_quantity ? 'Yes' : 'No',
                $unit->allow_fractional_quantity ? 'Yes' : 'No',
                (string) (int) $unit->quantity_precision,
                (string) (int) $unit->quantity_precision,
                $this->formatNullableQuantity($oldMinimum),
                $this->formatNullableQuantity($oldMinimum),
                'Excluded',
            ];
        }

        if ($commit) {
            DB::transaction(function () use ($candidates, $precision, $minimum, $rollbackExcluded): void {
                foreach ($candidates as $unit) {
                    $oldMinimum = $unit->minimum_wholesale_quantity === null ? null : (float) $unit->minimum_wholesale_quantity;
                    $shouldUpdate = $rollbackExcluded
                        ? $this->shouldRollbackExcludedUnit($unit, $oldMinimum)
                        : $this->shouldEnableUnit($unit, $precision, $oldMinimum);

                    if (! $shouldUpdate) {
                        continue;
                    }

                    $attributes = $rollbackExcluded
                        ? [
                            'allow_fractional_quantity' => false,
                            'quantity_precision' => 0,
                            'minimum_wholesale_quantity' => null,
                        ]
                        : [
                            'allow_fractional_quantity' => true,
                            'quantity_precision' => max((int) $unit->quantity_precision, $precision),
                            'minimum_wholesale_quantity' => $oldMinimum !== null && $oldMinimum > 0 ? $oldMinimum : $minimum,
                        ];

                    ProductUnit::query()
                        ->whereKey($unit->id)
                        ->update($attributes);
                }
            });
        }

        if ($rollbackExcluded) {
            $this->info($commit ? 'Excluded unit fractional settings rolled back.' : 'Dry-run only. No excluded product units were changed.');
        } else {
            $this->info($commit ? 'Fractional wholesale settings committed.' : 'Dry-run only. No product units were changed.');
        }
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
        $this->line($rollbackExcluded ? (($commit ? 'Rolled back' : 'Would rollback').': '.$wouldUpdate) : (($commit ? 'Updated' : 'Would update').': '.$wouldUpdate));
        $this->line('Skipped: '.$skipped);
        $this->line('Excluded units: '.$excluded);
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

    private function isDefaultIncludedUnit(string $unitName): bool
    {
        return $this->matchesAnyName($unitName, self::DEFAULT_INCLUDED_UNITS);
    }

    private function isDefaultExcludedUnit(string $unitName): bool
    {
        return $this->matchesAnyName($unitName, self::DEFAULT_EXCLUDED_UNITS);
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

    private function shouldEnableUnit(ProductUnit $unit, int $precision, ?float $oldMinimum): bool
    {
        return ! (bool) $unit->allow_fractional_quantity
            || (int) $unit->quantity_precision < $precision
            || $oldMinimum === null
            || $oldMinimum <= 0;
    }

    private function shouldRollbackExcludedUnit(ProductUnit $unit, ?float $oldMinimum): bool
    {
        return (bool) $unit->allow_fractional_quantity
            && (int) $unit->quantity_precision === 2
            && $oldMinimum !== null
            && abs($oldMinimum - 0.25) < 0.0005;
    }

    private function matchesAnyName(string $unitName, array $names): bool
    {
        $normalized = $this->normalizeName($unitName);

        foreach ($names as $name) {
            if (preg_match('/\b'.preg_quote($name, '/').'\b/', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
