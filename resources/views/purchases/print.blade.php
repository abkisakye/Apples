<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase {{ $purchase->purchase_no }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f7f3e8;
            padding: 20px;
            color: #2f2616;
        }

        .toolbar {
            text-align: center;
            margin-bottom: 20px;
        }

        .toolbar button {
            background: #066838;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            font-weight: 500;
        }

        .toolbar button:hover {
            background: #04512c;
        }

        .page {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(47, 38, 22, 0.10);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header styles - COMPACT */
        .doc-header {
            background: #066838;
            color: #fff;
            padding: 16px 24px;
            border-bottom: 3px solid #d4af37;
        }

        .brand-info {
            text-align: center;
            margin-bottom: 12px;
        }

        .brand-name {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .brand-meta {
            font-size: 10px;
            opacity: 0.7;
            line-height: 1.4;
        }

        .doc-title-section {
            text-align: center;
            border-top: 1px solid rgba(212,175,55,0.40);
            border-bottom: 1px solid rgba(212,175,55,0.40);
            padding: 10px 0;
            margin-top: 8px;
        }

        .doc-label {
            font-size: 11px;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .doc-name {
            font-size: 18px;
            font-weight: bold;
            font-family: monospace;
            margin-top: 4px;
        }

        .doc-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 10px;
            font-size: 10px;
            background: rgba(255,255,255,0.10);
            padding: 6px 12px;
            border-radius: 4px;
            flex-wrap: wrap;
            gap: 8px;
        }

        /* Content sections - COMPACT */
        .content-section {
            padding: 16px 24px;
        }

        /* Overview grid - 4 columns */
        .overview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e3dcc7;
        }

        .overview-item {
            text-align: center;
        }

        .section-kicker {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6d6554;
            font-weight: 600;
        }

        .metric-value {
            font-size: 18px;
            font-weight: bold;
            color: #066838;
            margin-top: 4px;
        }

        /* Two column layout - COMPACT */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .panel {
            background: #fbf8ef;
            border: 1px solid #e3dcc7;
            border-radius: 6px;
            padding: 12px;
        }

        .panel-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #5e4500;
            display: block;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1.5px solid #d4af37;
        }

        .profile-name {
            font-size: 15px;
            font-weight: 600;
            color: #2f2616;
            margin-bottom: 4px;
        }

        .profile-line {
            font-size: 11px;
            color: #6d6554;
            margin-bottom: 2px;
        }

        .summary-table {
            width: 100%;
            font-size: 11px;
        }

        .summary-table tr td:first-child {
            font-weight: 600;
            color: #6d6554;
            padding: 4px 0;
            width: 45%;
        }

        .summary-table tr td:last-child {
            color: #2f2616;
            padding: 4px 0;
        }

        /* Items table - COMPACT */
        .items-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #5e4500;
            display: block;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1.5px solid #d4af37;
        }

        .ledger {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .ledger th,
        .ledger td {
            border: 1px solid #e3dcc7;
            padding: 6px 8px;
            text-align: left;
        }

        .ledger th {
            background: #066838;
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .ledger tbody tr:nth-child(even) td {
            background: #fbf8ef;
        }

        .ledger td.money,
        .ledger td.qty {
            text-align: right;
        }

        /* Stock note - COMPACT */
        .stock-note {
            background: #fbf1cf;
            border-left: 3px solid #d4af37;
            padding: 10px 12px;
            border-radius: 4px;
        }

        .detail-copy {
            font-size: 11px;
            line-height: 1.5;
            color: #5e4500;
        }

        /* Footer - COMPACT */
        .footer-note {
            text-align: center;
            font-size: 9px;
            color: #6d6554;
            padding: 12px 24px;
            border-top: 1px solid #e3dcc7;
            background: #fbf8ef;
        }

        .signature-row {
            display: flex;
            justify-content: space-between;
            padding: 16px 24px 20px;
            gap: 32px;
        }

        .signature-item {
            flex: 1;
            border-top: 1px solid #d1c08a;
            padding-top: 10px;
            font-size: 10px;
            color: #6d6554;
            text-align: center;
        }

        /* Print optimization - ONE PAGE */
        @media print {
            @page {
                size: A4;
                margin: 8mm;
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
                border-radius: 0;
                box-shadow: none;
            }

            /* Prevent page breaks inside these elements */
            .overview-grid,
            .two-columns,
            .ledger,
            .stock-note,
            .signature-row {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }

        @media (max-width: 700px) {
            body {
                padding: 10px;
            }
            
            .doc-header {
                padding: 12px 16px;
            }
            
            .content-section {
                padding: 12px 16px;
            }
            
            .doc-meta {
                flex-direction: column;
                gap: 4px;
            }
            
            .overview-grid,
            .two-columns {
                grid-template-columns: 1fr;
                gap: 12px;
            }
            
            .overview-item {
                text-align: left;
            }
        }
    </style>
</head>
<body>
    @php($currency = config('business.currency', 'UGX'))
    @php($printedAt = now())
    @php($documentLabel = $purchase->purchase_type === 'credit' ? 'Purchase Invoice' : 'Purchase Voucher')
    @php($itemCount = $purchase->items->sum('quantity'))

    <div class="toolbar">
        <button onclick="window.print()">🖨️ Print / Save PDF</button>
    </div>

    <div class="page">
        <!-- Header Section -->
        <div class="doc-header">
            <div class="brand-info">
                <div class="brand-name">{{ config('business.name', 'Apples Of Gold') }}</div>
                <div class="brand-meta">
                    {{ config('business.address', 'Your business address') }}<br>
                    Tel: {{ config('business.phone', '+256700000000') }} / Email: {{ config('business.email', 'info@example.com') }}<br>
                    TIN: {{ config('business.tin', 'TIN-NUMBER') }}
                </div>
            </div>
            <div class="doc-title-section">
                <div class="doc-label">{{ $documentLabel }}</div>
                <div class="doc-name">{{ $purchase->purchase_no }}</div>
                <div class="doc-meta">
                    <span>Date: {{ optional($purchase->purchase_date)->format('d M Y') }}</span>
                    <span>Store: {{ $purchase->store?->name ?? '-' }}</span>
                    <span>Payment Mode: {{ $purchase->paymentMode?->name ?? 'Not set' }}</span>
                    <span>Printed: {{ $printedAt->format('d M Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Overview Section -->
        <div class="content-section">
            <div class="overview-grid">
                <div class="overview-item">
                    <div class="section-kicker">PURCHASE TOTAL</div>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">PAID NOW</div>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchase->amount_paid, 0) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">SUPPLIER BALANCE</div>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">ITEMS RECEIVED</div>
                    <div class="metric-value">{{ number_format($itemCount) }}</div>
                </div>
            </div>

            <!-- Two Column Content -->
            <div class="two-columns">
                <div class="panel">
                    <div class="panel-title">SUPPLIER</div>
                    <div class="profile-name">{{ $purchase->supplier?->name ?? 'Supplier not linked' }}</div>
                    <div class="profile-line">{{ $purchase->supplier?->phone ?: 'No phone on record' }}</div>
                    <div class="profile-line">{{ $purchase->supplier?->address ?: ($purchase->supplier?->country ?: '-') }}</div>
                </div>

                <div class="panel">
                    <div class="panel-title">PURCHASE SUMMARY</div>
                    <table class="summary-table">
                        <tr><td>Store</td><td>{{ $purchase->store?->name ?? '-' }}</td></tr>
                        <tr><td>Supplier Invoice</td><td>{{ $purchase->supplier_invoice_no ?: '-' }}</td></tr>
                        <tr><td>Purchase Type</td><td>{{ ucfirst($purchase->purchase_type) }}</td></tr>
                        <tr><td>Status</td><td>{{ ucfirst($purchase->status) }}</td></tr>
                        @if ($purchase->purchase_type === 'credit' && $purchase->credit_due_date)
                            <tr><td>Due Date</td><td>{{ optional($purchase->credit_due_date)->format('d M Y') }}</td></tr>
                        @endif
                    </table>
                </div>
            </div>

            <!-- Items Table -->
            <div class="items-title">📦 INCOMING ITEMS</div>
            <table class="ledger">
                <thead>
                    <tr>
                        <th>PRODUCT</th>
                        <th>UNIT</th>
                        <th>QTY</th>
                        <th>COST</th>
                        <th>TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($purchase->items as $item)
                        <tr>
                            <td>{{ $item->product?->name ?? '-' }}</td>
                            <td>{{ $item->productUnit?->unit_name ?? '-' }}</td>
                            <td class="qty">{{ number_format((float) $item->quantity, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $item->unit_cost, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $item->line_total, 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" style="text-align: center;">No items found</td></tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Stock Note and Totals - Side by side -->
            <div class="two-columns">
                <div class="stock-note">
                    <div style="font-weight: 700; font-size: 10px; margin-bottom: 6px; color: #5e4500;">📝 STOCK NOTE</div>
                    <div class="detail-copy">
                        This confirms stock was received from {{ $purchase->supplier?->name ?? 'the supplier' }} under purchase {{ $purchase->purchase_no }}.
                        @if ($purchase->purchase_type === 'credit')
                            Balance remains open until fully paid.
                        @endif
                        @if ($purchase->remarks)
                            <br><br><strong>Remarks:</strong> {{ $purchase->remarks }}
                        @endif
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-title">💰 TOTALS</div>
                    <table class="summary-table">
                        <tr><td>Subtotal</td><td>{{ $currency }} {{ number_format((float) $purchase->subtotal, 0) }}</td></tr>
                        <tr><td>Total</td><td>{{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</td></tr>
                        <tr><td>Paid Now</td><td>{{ $currency }} {{ number_format((float) $purchase->amount_paid, 0) }}</td></tr>
                        <tr style="border-top: 1.5px solid #d4af37;">
                            <td style="font-weight: 800; color: #066838;">Balance</td>
                            <td style="font-weight: 800; color: #066838;">{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            {{ config('business.statement_footer', 'This statement is system generated and intended for account reconciliation.') }}
        </div>

        <!-- Signature Row -->
        <div class="signature-row">
            <div class="signature-item">Prepared By</div>
            <div class="signature-item">Received By</div>
        </div>
    </div>

    <script>
        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                window.print();
            }
        }
    </script>
</body>
</html>
