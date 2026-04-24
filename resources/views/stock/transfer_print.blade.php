<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Transfer {{ $referenceNo }}</title>
    <style>
        @include('partials.print_document_styles')
    </style>
</head>
<body>
    @php($printedAt = now())
    <div class="toolbar"><button onclick="window.print()">Print</button></div>
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Stock Transfer Note',
            'documentName' => $referenceNo,
            'documentMetaLines' => [
                'Date: '.$transferDate->format('d M Y'),
                'From: '.($fromStore?->name ?? '-'),
                'To: '.($toStore?->name ?? '-'),
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
                    <div class="metric-value">{{ number_format((float) $rows->sum('quantity_out'), 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Transfer Route</span>
                    <div class="note-copy">{{ $fromStore?->name ?? '-' }} to {{ $toStore?->name ?? '-' }}</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Transfer Summary</span>
                        <table class="summary-table">
                            <tr><td>Reference</td><td>{{ $referenceNo }}</td></tr>
                            <tr><td>Date</td><td>{{ $transferDate->format('d M Y') }}</td></tr>
                            <tr><td>Source Store</td><td>{{ $fromStore?->name ?? '-' }}</td></tr>
                            <tr><td>Destination Store</td><td>{{ $toStore?->name ?? '-' }}</td></tr>
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
                        <td class="qty">{{ number_format((float) $row->quantity_out, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-note">{{ config('business.statement_footer', 'This document is system-generated and intended for internal stock control.') }}</div>

        <table class="signature-row">
            <tr>
                <td>Issued By</td>
                <td>Received By</td>
            </tr>
        </table>
    </div>
</body>
</html>
