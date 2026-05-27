<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Stock Count {{ $referenceNo }}</title>
    <style>
        @include('partials.print_document_styles', ['pageWidth' => '980px'])
    </style>
</head>
<body>
    @php($printedAt = now())
    <div class="toolbar"><button onclick="window.print()">Print</button></div>
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Physical Stock Count',
            'documentName' => $referenceNo,
            'documentMetaLines' => [
                'Date: '.$countDate->format('d M Y'),
                'Store: '.($store?->name ?? '-'),
                'Counted By: '.($countedBy?->name ?? 'System User'),
                'Assigned To: '.($assignedTo?->name ?? ($countedBy?->name ?? 'System User')),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="overview overview-4">
            <tr>
                <td>
                    <span class="section-kicker">Total Lines</span>
                    <div class="metric-value">{{ number_format($rows->count()) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Total Variance</span>
                    <div class="metric-value">{{ number_format((int) $rows->sum('quantity_adjusted')) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Store</span>
                    <div class="note-copy">{{ $store?->name ?? '-' }}</div>
                </td>
                <td>
                    <span class="section-kicker">Section / Aisle</span>
                    <div class="note-copy">{{ $stockCount->section_name ?: ($stockCount->aisle_name ?: 'Not specified') }}</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Count Assignment</span>
                        <table class="summary-table">
                            <tr><td>Counted By</td><td>{{ $countedBy?->name ?? 'System User' }}</td></tr>
                            <tr><td>Assigned To</td><td>{{ $assignedTo?->name ?? ($countedBy?->name ?? 'System User') }}</td></tr>
                            <tr><td>Section</td><td>{{ $stockCount->section_name ?: '-' }}</td></tr>
                            <tr><td>Aisle</td><td>{{ $stockCount->aisle_name ?: '-' }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Remarks</span>
                        <div class="detail-copy">
                            {{ $remarks ?: 'No remarks recorded.' }}
                            <br><br>Assigned: {{ $assignedTo?->name ?? ($countedBy?->name ?? 'System User') }}
                            <br>Section: {{ $stockCount->section_name ?: ($stockCount->aisle_name ?: 'Not specified') }}
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <table class="ledger">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>System Count</th>
                    <th>Physical Count</th>
                    <th>Variance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row->product?->name ?? '-' }}</td>
                        <td>{{ $row->productUnit?->unit_name ?? '-' }}</td>
                        <td class="qty">{{ number_format((int) $row->system_qty) }}</td>
                        <td class="qty">{{ number_format((int) $row->physical_qty) }}</td>
                        <td class="qty">{{ (int) $row->variance_qty > 0 ? '+' : '' }}{{ number_format((int) $row->variance_qty) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-note">{{ config('business.statement_footer', 'This document is system-generated and intended for internal stock control.') }}</div>

        <table class="signature-row">
            <tr>
                <td>Counted By</td>
                <td>Verified By</td>
            </tr>
        </table>
    </div>
</body>
</html>
