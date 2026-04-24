<?php

namespace App\Console\Commands;

use App\Services\Operations\ProductionReadinessService;
use Illuminate\Console\Command;

class GoLiveCheck extends Command
{
    protected $signature = 'ops:go-live-check';

    protected $description = 'Run a lightweight production readiness checklist.';

    public function handle(ProductionReadinessService $readinessService): int
    {
        $checks = $readinessService->checks();
        $hasFailures = false;

        $this->line('Go-Live Readiness');
        $this->newLine();

        foreach ($checks as $check) {
            $label = $check['section'].' - '.$check['label'];
            $passed = $check['ready'];
            $advice = $check['action'];
            $status = $passed ? 'PASS' : 'WARN';
            $value = $check['value'] === '' ? 'n/a' : $check['value'];
            $this->line(str_pad($status, 6).$label.' ['.$value.']');

            if (! $passed) {
                $hasFailures = true;
                $this->line('      '.$advice);
            }
        }

        $this->newLine();
        $this->line($hasFailures ? 'Go-live check finished with warnings.' : 'Go-live check passed.');

        return $hasFailures ? self::FAILURE : self::SUCCESS;
    }
}
