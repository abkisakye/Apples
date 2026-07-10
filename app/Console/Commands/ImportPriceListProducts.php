<?php

namespace App\Console\Commands;

use App\Support\PriceListImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportPriceListProducts extends Command
{
    protected $signature = 'products:import-price-list
                            {path : Path to the TXT, CSV, XLS, or XLSX price list report}
                            {--dry-run : Parse and report without writing to the database}
                            {--commit : Actually import master data}
                            {--update-prices : Update selling prices for existing product units}
                            {--category= : Import only one category section}
                            {--limit= : Limit parsed product rows for testing}';

    protected $description = 'Import product master data and prices from the APPLES OF GOLD price list report.';

    public function handle(PriceListImportService $importService): int
    {
        if ($this->option('dry-run') && $this->option('commit')) {
            $this->error('Choose either --dry-run or --commit, not both.');

            return self::FAILURE;
        }

        $path = $this->resolvePath((string) $this->argument('path'));

        if (! $path || ! is_file($path)) {
            $this->error('Price list file not found: '.(string) $this->argument('path'));

            return self::FAILURE;
        }

        $limit = $this->option('limit');
        $limit = $limit !== null && $limit !== '' ? max((int) $limit, 1) : null;
        $commit = (bool) $this->option('commit');

        try {
            $result = $importService->importFile($path, [
                'commit' => $commit,
                'update_prices' => (bool) $this->option('update-prices'),
                'category' => $this->option('category') ?: null,
                'limit' => $limit,
            ]);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->line($commit ? 'Mode: COMMIT' : 'Mode: DRY RUN');
        $this->line('File: '.$path);
        $this->newLine();

        $analysis = $result['analysis'];
        $parsed = $result['parsed'];

        $this->line('Parsed rows              : '.$analysis['total_rows']);
        $this->line('Categories found         : '.$analysis['categories_found']);
        $this->line('Products to create       : '.$analysis['products_to_create']);
        $this->line('Products to update       : '.$analysis['products_to_update']);
        $this->line('Product units to create  : '.$analysis['units_to_create']);
        $this->line('Product units to update  : '.$analysis['units_to_update']);
        $this->line('Rows skipped             : '.count($parsed['skipped']));
        $this->line('Conversion reviews       : '.count($analysis['conversion_reviews']));
        $this->line('Zero-price rows          : '.count($analysis['zero_price_rows']));

        if ($commit) {
            $this->newLine();
            $this->line('Categories created       : '.$result['changes']['categories_created']);
            $this->line('Categories updated       : '.$result['changes']['categories_updated']);
            $this->line('Products created         : '.$result['changes']['products_created']);
            $this->line('Products updated         : '.$result['changes']['products_updated']);
            $this->line('Units created            : '.$result['changes']['units_created']);
            $this->line('Units updated            : '.$result['changes']['units_updated']);
            $this->line('Conversion review count  : '.$result['changes']['conversion_review_count']);
            $this->line('Zero-price review count  : '.count($analysis['zero_price_rows']));
        }

        if ($analysis['example_rows'] !== []) {
            $this->newLine();
            $this->line('Example parsed rows:');
            foreach ($analysis['example_rows'] as $row) {
                $this->line('- '.$row['category'].' | '.$row['product_name'].' | '.$row['unit_name'].' | '.number_format((float) $row['selling_price'], 0));
            }
        }

        if ($analysis['conversion_reviews'] !== []) {
            $this->newLine();
            $this->warn('Rows needing conversion review:');
            foreach (array_slice($analysis['conversion_reviews'], 0, 15) as $row) {
                $this->line('- '.$row['product_name'].' / '.$row['unit_name'].' assumes '.$row['assumption']);
            }

            if (count($analysis['conversion_reviews']) > 15) {
                $this->line('...and '.(count($analysis['conversion_reviews']) - 15).' more.');
            }
        }

        if ($analysis['zero_price_rows'] !== []) {
            $this->newLine();
            $this->warn('Zero-price rows needing review:');
            foreach (array_slice($analysis['zero_price_rows'], 0, 15) as $row) {
                $this->line('- '.$row['product_name'].' / '.$row['unit_name'].' in '.$row['category']);
            }

            if (count($analysis['zero_price_rows']) > 15) {
                $this->line('...and '.(count($analysis['zero_price_rows']) - 15).' more.');
            }
        }

        if ($parsed['warnings'] !== []) {
            $this->newLine();
            $this->warn('Warnings:');
            foreach (array_slice($parsed['warnings'], 0, 10) as $warning) {
                $this->line('- '.$warning);
            }
        }

        $this->newLine();
        $this->info($commit ? 'Price list import completed.' : 'Dry-run complete. Re-run with --commit to write master data.');

        return self::SUCCESS;
    }

    private function resolvePath(string $path): ?string
    {
        $candidates = [
            $path,
            base_path($path),
            storage_path('app/'.$path),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return realpath($candidate) ?: $candidate;
            }
        }

        return null;
    }
}
