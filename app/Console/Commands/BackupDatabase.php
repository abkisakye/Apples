<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'ops:backup-database {--path= : Custom output directory}';

    protected $description = 'Create a local database backup for SQLite or MySQL.';

    public function handle(): int
    {
        $connection = config('database.default');
        $directory = $this->option('path') ?: storage_path('app/backups');

        File::ensureDirectoryExists($directory);

        try {
            if ($connection === 'sqlite') {
                return $this->backupSqlite($directory);
            }

            if ($connection === 'mysql') {
                return $this->backupMysql($directory);
            }

            $this->error("Backup is not configured for the [{$connection}] database connection.");

            return self::FAILURE;
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function backupSqlite(string $directory): int
    {
        $source = config('database.connections.sqlite.database');

        if (! $source || ! File::exists($source)) {
            throw new RuntimeException('SQLite database file was not found.');
        }

        $target = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'sqlite-backup-'.now()->format('Ymd-His').'.sqlite';
        File::copy($source, $target);

        $this->info("SQLite backup created: {$target}");

        return self::SUCCESS;
    }

    private function backupMysql(string $directory): int
    {
        $database = (string) config('database.connections.mysql.database');
        $host = (string) config('database.connections.mysql.host');
        $port = (string) config('database.connections.mysql.port');
        $username = (string) config('database.connections.mysql.username');
        $password = (string) config('database.connections.mysql.password');
        $target = rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'mysql-backup-'.now()->format('Ymd-His').'.sql';

        $process = new Process([
            'mysqldump',
            '--host='.$host,
            '--port='.$port,
            '--user='.$username,
            '--password='.$password,
            '--single-transaction',
            '--skip-lock-tables',
            $database,
        ], base_path());

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('MySQL backup failed. Make sure mysqldump is installed and available on PATH.');
        }

        File::put($target, $process->getOutput());
        $this->info("MySQL backup created: {$target}");

        return self::SUCCESS;
    }
}
