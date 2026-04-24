<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function make(string $type, string $date): string
    {
        $config = config("document_numbers.{$type}");

        if (! is_array($config)) {
            throw new \InvalidArgumentException("Unknown document number type [{$type}].");
        }

        $prefix = $config['prefix'];
        $table = $config['table'];
        $column = $config['column'];
        $datePart = Carbon::parse($date)->format('Ymd');
        $pattern = "{$prefix}-{$datePart}-%";
        $sequence = DB::table($table)
            ->where($column, 'like', $pattern)
            ->distinct()
            ->count($column) + 1;

        return sprintf('%s-%s-%04d', $prefix, $datePart, $sequence);
    }
}
