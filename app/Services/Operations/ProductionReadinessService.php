<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ProductionReadinessService
{
    public function checks(): array
    {
        $appUrl = (string) config('app.url');
        $sessionDriver = (string) config('session.driver');
        $cacheStore = (string) config('cache.default');
        $queueConnection = (string) config('queue.default');
        $mailer = (string) config('mail.default');
        $backupPath = storage_path('app/backups');
        $backupParent = dirname($backupPath);
        $usesHttps = str_starts_with($appUrl, 'https://');

        return [
            [
                'section' => 'Application',
                'label' => 'Application Environment',
                'value' => (string) config('app.env'),
                'ready' => config('app.env') === 'production',
                'action' => 'Switch APP_ENV to production before go-live.',
            ],
            [
                'section' => 'Application',
                'label' => 'App Key',
                'value' => filled(config('app.key')) ? 'Set' : 'Missing',
                'ready' => filled(config('app.key')),
                'action' => 'Run php artisan key:generate and keep the final APP_KEY stable in production.',
            ],
            [
                'section' => 'Application',
                'label' => 'Debug Mode',
                'value' => config('app.debug') ? 'Enabled' : 'Disabled',
                'ready' => config('app.debug') === false,
                'action' => 'Set APP_DEBUG=false on the live server.',
            ],
            [
                'section' => 'Application',
                'label' => 'Application URL',
                'value' => $appUrl ?: 'Not set',
                'ready' => $usesHttps,
                'action' => 'Set APP_URL to the real HTTPS address.',
            ],
            [
                'section' => 'Data',
                'label' => 'Database Connection',
                'value' => (string) config('database.default'),
                'ready' => config('database.default') === 'mysql',
                'action' => 'Move from SQLite to MySQL or MariaDB for production.',
            ],
            [
                'section' => 'Data',
                'label' => 'Database Reachability',
                'value' => $this->databaseReachabilityLabel(),
                'ready' => $this->canReachDatabase(),
                'action' => 'Check database credentials, server access, and whether the database service is running.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Session Driver',
                'value' => $sessionDriver,
                'ready' => in_array($sessionDriver, ['database', 'redis'], true),
                'action' => 'Use database or redis sessions for multi-user production access.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Session Storage',
                'value' => $this->sessionStorageLabel($sessionDriver),
                'ready' => $this->sessionStorageReady($sessionDriver),
                'action' => 'Create the sessions table when using database sessions, or configure the chosen session backend fully.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Secure Session Cookie',
                'value' => $usesHttps ? ($this->secureSessionCookieEnabled() ? 'Enabled' : 'Disabled') : 'Not required locally',
                'ready' => ! $usesHttps || $this->secureSessionCookieEnabled(),
                'action' => 'Set SESSION_SECURE_COOKIE=true when the live site runs on HTTPS.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Cache Store',
                'value' => $cacheStore,
                'ready' => ! in_array($cacheStore, ['array', 'null'], true),
                'action' => 'Use database, redis, or another persistent cache store in production.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Cache Storage',
                'value' => $this->cacheStorageLabel($cacheStore),
                'ready' => $this->cacheStorageReady($cacheStore),
                'action' => 'Make sure the configured cache store is backed by the required table or service.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Queue Connection',
                'value' => $queueConnection,
                'ready' => $queueConnection !== 'sync',
                'action' => 'Use database, redis, or another real queue backend before handover.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Queue Tables',
                'value' => $this->queueTablesLabel($queueConnection),
                'ready' => $this->queueTablesReady($queueConnection),
                'action' => 'Create the jobs and failed_jobs tables when using the database queue driver.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Mail Delivery',
                'value' => $mailer,
                'ready' => ! in_array($mailer, ['log', 'array'], true),
                'action' => 'Use a real SMTP or mail provider for password resets and reminder messages.',
            ],
            [
                'section' => 'Operations',
                'label' => 'Backup Folder',
                'value' => $backupPath,
                'ready' => File::isDirectory($backupPath) ? File::isWritable($backupPath) : File::isWritable($backupParent),
                'action' => 'Make sure storage/app/backups is writable so php artisan ops:backup-database can run safely.',
            ],
            [
                'section' => 'Business',
                'label' => 'Business Identity',
                'value' => $this->businessIdentityLabel(),
                'ready' => filled(config('business.name')) && (filled(config('business.phone')) || filled(config('business.email'))),
                'action' => 'Complete Business Settings so receipts, invoices, and statements show real contact details.',
            ],
        ];
    }

    public function groupedChecks(): array
    {
        return collect($this->checks())
            ->groupBy('section')
            ->map(fn ($rows, $section) => [
                'section' => $section,
                'checks' => $rows->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function hasFailures(): bool
    {
        return collect($this->checks())->contains(fn (array $check) => ! $check['ready']);
    }

    private function canReachDatabase(): bool
    {
        try {
            DB::select('select 1 as connected');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function databaseReachabilityLabel(): string
    {
        return $this->canReachDatabase() ? 'Reachable' : 'Unavailable';
    }

    private function sessionStorageReady(string $sessionDriver): bool
    {
        if ($sessionDriver !== 'database') {
            return true;
        }

        return Schema::hasTable((string) config('session.table', 'sessions'));
    }

    private function sessionStorageLabel(string $sessionDriver): string
    {
        if ($sessionDriver !== 'database') {
            return ucfirst($sessionDriver ?: 'Unknown');
        }

        $table = (string) config('session.table', 'sessions');

        return Schema::hasTable($table) ? "Table: {$table}" : "Missing table: {$table}";
    }

    private function secureSessionCookieEnabled(): bool
    {
        return (bool) config('session.secure');
    }

    private function cacheStorageReady(string $cacheStore): bool
    {
        if ($cacheStore !== 'database') {
            return ! in_array($cacheStore, ['array', 'null'], true);
        }

        return Schema::hasTable((string) config('cache.stores.database.table', 'cache'));
    }

    private function cacheStorageLabel(string $cacheStore): string
    {
        if ($cacheStore !== 'database') {
            return ucfirst($cacheStore ?: 'Unknown');
        }

        $table = (string) config('cache.stores.database.table', 'cache');

        return Schema::hasTable($table) ? "Table: {$table}" : "Missing table: {$table}";
    }

    private function queueTablesReady(string $queueConnection): bool
    {
        if ($queueConnection !== 'database') {
            return $queueConnection !== 'sync';
        }

        return Schema::hasTable('jobs') && Schema::hasTable('failed_jobs');
    }

    private function queueTablesLabel(string $queueConnection): string
    {
        if ($queueConnection !== 'database') {
            return ucfirst($queueConnection ?: 'Unknown');
        }

        $jobs = Schema::hasTable('jobs') ? 'jobs' : 'missing jobs';
        $failedJobs = Schema::hasTable('failed_jobs') ? 'failed_jobs' : 'missing failed_jobs';

        return "{$jobs}, {$failedJobs}";
    }

    private function businessIdentityLabel(): string
    {
        $name = filled(config('business.name')) ? (string) config('business.name') : 'Name missing';
        $contact = filled(config('business.phone'))
            ? 'Phone set'
            : (filled(config('business.email')) ? 'Email set' : 'Contact missing');

        return "{$name} / {$contact}";
    }
}
