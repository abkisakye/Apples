<?php

namespace App\Console\Commands;

use App\Services\Access\AccessImportService;
use Illuminate\Console\Command;
use Throwable;

class ImportAccessCoreData extends Command
{
    protected $signature = 'access:import-core-data
                            {--path= : Full path to the Access .mdb file}
                            {--master-only : Import only master data}
                            {--fresh : Clear imported tables before importing}';

    protected $description = 'Import core business data from the legacy Access MDB into Laravel tables.';

    public function handle(AccessImportService $importService): int
    {
        ini_set('memory_limit', '1024M');
        set_time_limit(0);

        $databasePath = $this->option('path') ?: base_path('VENUS BUSINESS MANAGER-PREMIER-POSB-FEB21.mdb');
        $masterOnly = (bool) $this->option('master-only');

        if (! file_exists($databasePath)) {
            $this->error("Access database not found at: {$databasePath}");

            return self::FAILURE;
        }

        try {
            if ($this->option('fresh')) {
                $this->warn('Clearing imported tables first...');
                $importService->clearData(! $masterOnly);
                $this->call('db:seed', ['--force' => true]);
            }

            $this->info('Importing Access data...');
            $summary = $importService->import($databasePath, ! $masterOnly);

            foreach ($summary as $key => $count) {
                $this->line(str_pad($key, 24).': '.$count);
            }

            $this->newLine();
            $this->info('Access import completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
