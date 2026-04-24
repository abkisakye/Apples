<?php

namespace App\Services\Access;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class AccessMdbReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function table(string $databasePath, string $tableName): array
    {
        return $this->query($databasePath, sprintf('SELECT * FROM [%s]', $tableName));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(string $databasePath, string $query): array
    {
        $script = base_path('scripts/export_access_query.ps1');

        if (! file_exists($script)) {
            throw new RuntimeException("Access export script not found at [{$script}].");
        }

        $process = new Process([
            'powershell',
            '-NoProfile',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $script,
            '-DatabasePath',
            $databasePath,
            '-Query',
            $query,
        ], base_path());

        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Failed to read from the Access database.');
        }

        $output = trim($process->getOutput());

        if ($output === '') {
            return [];
        }

        $rows = [];

        foreach (preg_split("/\r\n|\n|\r/", $output) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (Throwable $e) {
                throw new RuntimeException("Could not decode Access row JSON: {$line}", previous: $e);
            }

            $rows[] = is_array($decoded) ? $decoded : [];
        }

        return $rows;
    }
}
