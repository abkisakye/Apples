<?php

namespace App\Services;

use App\Exports\TableExport;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelExportService
{
    public function download(string $filename, array $headings, iterable $rows): BinaryFileResponse
    {
        $normalizedFilename = str_ends_with(strtolower($filename), '.xlsx') ? $filename : $filename.'.xlsx';
        $normalizedRows = [];

        foreach ($rows as $row) {
            $normalizedRows[] = is_array($row) ? $row : (array) $row;
        }

        return Excel::download(new TableExport($headings, $normalizedRows), $normalizedFilename);
    }
}
