<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustment {{ $referenceNo }}</title>
    <style>
        @include('partials.print_document_styles')
    </style>
</head>
<body>
    @php($printedAt = now())
    <div class="toolbar"><button onclick="window.print()">Print</button></div>
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Stock Adjustment Note',
            'documentName' => $referenceNo,
            'documentMetaLines' => [
                'Date: '.$adjustmentDate->format('d M Y'),
                'Store: '.($store?->name ?? '-'),
                'Type: '.\Illuminate\Support\Str::headline($movementType),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="overview">
            <tr>
                <td>
                    <span class="section-kicker">Total Lines</span>
                    <div class="metric-value">{{ number_format($rows->count()) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Total Quantity</span>
                    <div class="metric-value">{{ number_format((float) $rows->sum(fn ($row) => max($row->quantity_in, $row->quantity_out)), 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Adjustment Type</span>
                    <div class="note-copy">{{ \Illuminate\Support\Str::headline($movementType) }}</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Adjustment Summary</span>
                        <table class="summary-table">
                            <tr><td>Reference</td><td>{{ $referenceNo }}</td></tr>
                            <tr><td>Date</td><td>{{ $adjustmentDate->format('d M Y') }}</td></tr>
                            <tr><td>Store</td><td>{{ $store?->name ?? '-' }}</td></tr>
                            <tr><td>Type</td><td>{{ \Illuminate\Support\Str::headline($movementType) }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Remarks</span>
                        <div class="detail-copy">{{ $remarks ?: 'No remarks recorded.' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="ledger">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Quantity</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->product?->name ?? '-' }}</td>
                        <td>{{ $row->productUnit?->unit_name ?? '-' }}</td>
                        <td class="qty">{{ number_format((float) max($row->quantity_in, $row->quantity_out), 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-note">{{ config('business.statement_footer', 'This document is system-generated and intended for internal stock control.') }}</div>

        <table class="signature-row">
            <tr>
                <td>Prepared By</td>
                <td>Verified By</td>
            </tr>
        </table>
    </div>
</body>
</html>
