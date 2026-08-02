<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DocumentNumberService
{
    public function make(string $type, string $date): string
    {
        $config = config("document_numbers.{$type}");

        if (! is_array($config)) {
            throw new InvalidArgumentException("Unknown document number type [{$type}].");
        }

        $prefix = (string) $config['prefix'];
        $table = (string) $config['table'];
        $column = (string) $config['column'];
        $datePart = Carbon::parse($date)->format('Ymd');
        $pattern = "{$prefix}-{$datePart}-%";
        $numberPattern = '/^'.preg_quote($prefix, '/').'-'.preg_quote($datePart, '/').'-(\d+)$/';

        $highestSequence = DB::table($table)
            ->where($column, 'like', $pattern)
            ->pluck($column)
            ->reduce(function (int $highest, mixed $documentNumber) use ($numberPattern): int {
                $documentNumber = trim((string) $documentNumber);

                if (! preg_match($numberPattern, $documentNumber, $matches)) {
                    return $highest;
                }

                return max($highest, (int) $matches[1]);
            }, 0);

        return sprintf('%s-%s-%04d', $prefix, $datePart, $highestSequence + 1);
    }
}
