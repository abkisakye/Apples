<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Physical Stock Count {{ $referenceNo }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            color: #1a1a1a;
        }

        .toolbar {
            text-align: center;
            margin-bottom: 20px;
        }

        .toolbar button {
            background: #066838;
            color: white;
            border: none;
            padding: 10px 28px;
            border-radius: 4px;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
            font-family: inherit;
        }

        .toolbar button:hover {
            background: #04512c;
        }

        .page {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Header - Clean white with green accent line */
        .doc-header {
            background: white;
            padding: 24px 28px 16px;
            border-bottom: 2px solid #066838;
        }

        .brand-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .brand-name {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 4px;
            color: #1a1a1a;
        }

        .brand-tagline {
            font-size: 11px;
            color: #666;
        }

        .brand-meta {
            font-size: 10px;
            color: #888;
            margin-top: 6px;
            line-height: 1.4;
        }

        .doc-title-section {
            text-align: center;
            margin-top: 8px;
        }

        .doc-label {
            font-size: 11px;
            letter-spacing: 2px;
            color: #066838;
            font-weight: 600;
            text-transform: uppercase;
        }

        .doc-name {
            font-size: 22px;
            font-weight: bold;
            font-family: monospace;
            margin-top: 4px;
            color: #1a1a1a;
        }

        .doc-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid #e0e0e0;
            font-size: 11px;
            color: #666;
        }

        /* Content sections */
        .content-section {
            padding: 20px 28px;
        }

        /* Overview grid - 4 columns */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }

        .overview-item {
            text-align: center;
        }

        .section-kicker {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #066838;
            font-weight: 600;
        }

        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #1a1a1a;
            margin-top: 6px;
        }

        .note-copy {
            font-size: 14px;
            font-weight: 500;
            color: #1a1a1a;
            margin-top: 6px;
        }

        /* Two column layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
            margin-bottom: 28px;
        }

        .panel {
            border: 1px solid #e0e0e0;
            padding: 16px;
            background: white;
        }

        .panel-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #066838;
            display: block;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e0e0e0;
        }

        .summary-table {
            width: 100%;
            font-size: 12px;
        }

        .summary-table tr td:first-child {
            font-weight: 600;
            color: #555;
            padding: 6px 0;
            width: 35%;
        }

        .summary-table tr td:last-child {
            color: #1a1a1a;
            padding: 6px 0;
        }

        .detail-copy {
            font-size: 12px;
            line-height: 1.5;
            color: #333;
        }

        /* Ledger table */
        .ledger {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 28px;
            font-size: 12px;
        }

        .ledger th,
        .ledger td {
            border: 1px solid #e0e0e0;
            padding: 10px 12px;
            text-align: left;
        }

        .ledger th {
            background: #f8f8f8;
            color: #1a1a1a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ledger td {
            color: #1a1a1a;
        }

        .ledger td.qty {
            text-align: right;
        }

        /* Variance colors - subtle green/red */
        .variance-positive {
            color: #066838;
            font-weight: 600;
        }

        .variance-negative {
            color: #c62828;
            font-weight: 600;
        }

        .variance-zero {
            color: #888;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            font-size: 9px;
            color: #999;
            padding: 16px 28px;
            border-top: 1px solid #e0e0e0;
            background: white;
        }

        .signature-row {
            display: flex;
            justify-content: space-between;
            padding: 20px 28px 28px;
            gap: 40px;
        }

        .signature-item {
            flex: 1;
            border-top: 1px solid #ccc;
            padding-top: 12px;
            font-size: 11px;
            color: #666;
            text-align: center;
        }

        /* ========== PRINT STYLES ========== */
        @media print {
            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .toolbar {
                display: none;
            }

            .page {
                max-width: none;
                margin: 0;
                box-shadow: none;
            }

            .doc-header {
                background: white !important;
                border-bottom: 2px solid #066838 !important;
            }

            .panel {
                border: 1px solid #ccc !important;
                background: white !important;
            }

            .ledger th {
                background: #f0f0f0 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .footer-note {
                background: white !important;
            }

            .variance-positive {
                color: #066838 !important;
            }

            .variance-negative {
                color: #c62828 !important;
            }

            .overview-grid,
            .two-columns,
            .panel,
            .ledger,
            .signature-row {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            .ledger tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        /* Mobile responsive */
        @media (max-width: 700px) {
            body {
                padding: 10px;
            }
            
            .doc-header,
            .content-section,
            .footer-note,
            .signature-row {
                padding-left: 16px;
                padding-right: 16px;
            }
            
            .doc-meta {
                flex-direction: column;
                gap: 6px;
            }
            
            .overview-grid,
            .two-columns {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .overview-item {
                text-align: left;
            }
            
            .ledger {
                font-size: 10px;
                display: block;
                overflow-x: auto;
            }
            
            .ledger th,
            .ledger td {
                padding: 6px 8px;
            }
        }
    </style>
</head>
<body>
    @php($printedAt = now())
    @php($totalVariance = $rows->sum('variance_qty'))

    <div class="toolbar">
        <button onclick="window.print()">🖨️ Print / Save PDF</button>
    </div>

    <div class="page">
        <!-- Header Section -->
        <div class="doc-header">
            <div class="brand-info">
                <div class="brand-name">{{ config('business.name', 'Apples Of Gold') }}</div>
                <div class="brand-tagline">{{ config('business.tagline', 'Business Management System') }}</div>
                <div class="brand-meta">
                    {{ config('business.address', 'Your business address') }}<br>
                    Tel: {{ config('business.phone', '+256700000000') }} / Email: {{ config('business.email', 'info@example.com') }}<br>
                    TIN: {{ config('business.tin', 'TIN-NUMBER') }}
                </div>
            </div>
            <div class="doc-title-section">
                <div class="doc-label">PHYSICAL STOCK COUNT</div>
                <div class="doc-name">{{ $referenceNo }}</div>
                <div class="doc-meta">
                    <span>Date: {{ $countDate->format('d M Y') }}</span>
                    <span>Store: {{ $store?->name ?? '-' }}</span>
                    <span>Counted By: {{ $countedBy?->name ?? 'System User' }}</span>
                    <span>Printed: {{ $printedAt->format('d M Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Content Section -->
        <div class="content-section">
            <!-- Overview Grid -->
            <div class="overview-grid">
                <div class="overview-item">
                    <div class="section-kicker">TOTAL LINES</div>
                    <div class="metric-value">{{ number_format($rows->count()) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">TOTAL VARIANCE</div>
                    <div class="metric-value">{{ number_format((int) $totalVariance) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">STORE</div>
                    <div class="note-copy">{{ $store?->name ?? '-' }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">SECTION / AISLE</div>
                    <div class="note-copy">{{ $stockCount->section_name ?: ($stockCount->aisle_name ?: 'Not specified') }}</div>
                </div>
            </div>

            <!-- Items Table -->
            <table class="ledger">
                <thead>
                    <tr>
                        <th>PRODUCT</th>
                        <th>UNIT</th>
                        <th class="qty">SYSTEM COUNT</th>
                        <th class="qty">PHYSICAL COUNT</th>
                        <th class="qty">VARIANCE</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        @php($variance = (int) $row->variance_qty)
                        <tr>
                            <td>{{ $row->product?->name ?? '-' }}</td>
                            <td>{{ $row->productUnit?->unit_name ?? '-' }}</td>
                            <td class="qty">{{ number_format((int) $row->system_qty) }}</td>
                            <td class="qty">{{ number_format((int) $row->physical_qty) }}</td>
                            <td class="qty">
                                <span class="@if($variance > 0) variance-positive @elseif($variance < 0) variance-negative @else variance-zero @endif">
                                    {{ $variance > 0 ? '+' : '' }}{{ number_format($variance) }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align: center;">No items found</td></tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Count Assignment and Remarks side by side -->
            <div class="two-columns">
                <div class="panel">
                    <div class="panel-title">COUNT ASSIGNMENT</div>
                    <table class="summary-table">
                        <tr><td style="width: 35%;">Counted By</td><td>{{ $countedBy?->name ?? 'System User' }}</td></tr>
                        <tr><td>Assigned To</td><td>{{ $assignedTo?->name ?? ($countedBy?->name ?? 'System User') }}</td></tr>
                        <tr><td>Section</td><td>{{ $stockCount->section_name ?: '-' }}</td></tr>
                        <tr><td>Aisle</td><td>{{ $stockCount->aisle_name ?: '-' }}</td></tr>
                    </table>
                </div>

                <div class="panel">
                    <div class="panel-title">REMARKS & ASSIGNED</div>
                    <div class="detail-copy">
                        <strong>Remarks:</strong> {{ $remarks ?: 'No remarks recorded.' }}
                    </div>
                    <div class="detail-copy" style="margin-top: 12px; padding-top: 8px; border-top: 1px dashed #e0e0e0;">
                        <strong>Assigned:</strong> {{ $assignedTo?->name ?? ($countedBy?->name ?? 'System User') }}<br>
                        Section: {{ $stockCount->section_name ?: ($stockCount->aisle_name ?: 'Not specified') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            {{ config('business.statement_footer', 'This document is system-generated and intended for internal stock control.') }}
        </div>

        <!-- Signature Row -->
        <div class="signature-row">
            <div class="signature-item">Counted By</div>
            <div class="signature-item">Verified By</div>
        </div>
    </div>

    <script>
        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 300);
            };
        }
    </script>
</body>
</html>
