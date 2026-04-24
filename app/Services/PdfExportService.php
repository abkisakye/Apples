<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PdfExportService
{
    public function download(string $view, array $data, string $filename, string $paper = 'a4'): Response
    {
        $normalizedFilename = str_ends_with(strtolower($filename), '.pdf') ? $filename : $filename.'.pdf';

        return Pdf::loadView($view, array_merge($data, ['isPdf' => true]))
            ->setPaper($paper)
            ->download($normalizedFilename);
    }
}
