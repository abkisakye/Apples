<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Payment {{ $payment->payment_no }}</title>
    <style>
        /* Base styles for all formats */
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
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 4px 20px rgba(47, 38, 22, 0.10);
            border-radius: 8px;
            overflow: hidden;
        }

        /* Header styles */
        .doc-header {
            background: #066838;
            color: #fff;
            padding: 24px 32px;
            border-bottom: 4px solid #d4af37;
        }

        .brand-info {
            text-align: center;
            margin-bottom: 20px;
        }

        .brand-name {
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .brand-tagline {
            font-size: 12px;
            opacity: 0.8;
        }

        .brand-meta {
            font-size: 11px;
            opacity: 0.7;
            margin-top: 8px;
        }

        .doc-title-section {
            text-align: center;
            border-top: 1px solid rgba(212,175,55,0.40);
            border-bottom: 1px solid rgba(212,175,55,0.40);
            padding: 16px 0;
            margin-top: 12px;
        }

        .doc-label {
            font-size: 13px;
            letter-spacing: 2px;
            opacity: 0.8;
        }

        .doc-name {
            font-size: 22px;
            font-weight: bold;
            font-family: monospace;
            margin-top: 6px;
        }

        .doc-meta {
            display: flex;
            justify-content: space-between;
            margin-top: 16px;
            font-size: 12px;
            background: rgba(255,255,255,0.10);
            padding: 10px 16px;
            border-radius: 6px;
        }

        /* Content sections */
        .content-section {
            padding: 24px 32px;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 32px;
            border-bottom: 1px solid #e3dcc7;
            padding-bottom: 24px;
        }

        .overview-item {
            text-align: center;
        }

        .section-kicker {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #6d6554;
            font-weight: 500;
        }

        .metric-value {
            font-size: 28px;
            font-weight: bold;
            color: #066838;
            margin-top: 8px;
        }

        /* Two column layout */
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            margin-bottom: 32px;
        }

        .panel {
            background: #fbf8ef;
            border: 1px solid #e3dcc7;
            border-radius: 8px;
            padding: 16px;
        }

        .panel-title {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #5e4500;
            display: block;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #d4af37;
        }

        .profile-name {
            font-size: 18px;
            font-weight: 600;
            color: #2f2616;
            margin-bottom: 8px;
        }

        .profile-line {
            font-size: 13px;
            color: #6d6554;
            margin-bottom: 4px;
        }

        .summary-table {
            width: 100%;
            font-size: 13px;
        }

        .summary-table tr td:first-child {
            font-weight: 600;
            color: #6d6554;
            padding: 6px 0;
            width: 45%;
        }

        .summary-table tr td:last-child {
            color: #2f2616;
            padding: 6px 0;
        }

        /* Accounts note */
        .accounts-note {
            background: #fbf1cf;
            border-left: 4px solid #d4af37;
            padding: 16px;
            margin-bottom: 24px;
            border-radius: 6px;
        }

        .detail-copy {
            font-size: 13px;
            line-height: 1.6;
            color: #5e4500;
        }

        /* Footer */
        .footer-note {
            text-align: center;
            font-size: 10px;
            color: #6d6554;
            padding: 20px 32px;
            border-top: 1px solid #e3dcc7;
            background: #fbf8ef;
        }

        .signature-row {
            display: flex;
            justify-content: space-between;
            padding: 24px 32px 32px;
            gap: 32px;
        }

        .signature-item {
            flex: 1;
            border-top: 1px solid #d1c08a;
            padding-top: 16px;
            font-size: 12px;
            color: #6d6554;
            text-align: center;
        }

        /* Thermal print styles - only for thermal mode */
        @media print {
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
        }

        /* Specific thermal receipt styles */
        @media print {
            body.thermal-mode {
                font-family: "Courier New", monospace;
            }

            body.thermal-mode .page {
                max-width: 80mm;
            }

            body.thermal-mode .doc-header {
                background: white !important;
                color: black !important;
                border-bottom: 1px dashed #888 !important;
                padding: 12px 16px !important;
            }

            body.thermal-mode .overview-grid {
                grid-template-columns: 1fr;
                gap: 8px;
                border-bottom: 1px dashed #888;
            }

            body.thermal-mode .two-columns {
                grid-template-columns: 1fr;
                gap: 16px;
            }

            body.thermal-mode .metric-value {
                font-size: 20px;
            }

            body.thermal-mode .panel {
                background: none;
                border: none;
                padding: 8px 0;
            }
        }
    </style>
</head>
<body class="{{ request()->string('theme')->toString() === 'thermal' ? 'thermal-mode' : '' }}">
    @php($currency = config('business.currency', 'UGX'))
    @php($thermal = request()->string('theme')->toString() === 'thermal')
    @php($printedAt = now())
    @php($amountPaidNow = (float) $payment->amount)
    {{-- This voucher is normally viewed immediately after posting, so the purchase balance is the balance after this payment. --}}
    @php($balanceAfterPayment = (float) ($payment->purchase?->balance_due ?? 0))
    @php($balanceBeforePayment = $balanceAfterPayment + $amountPaidNow)

    @if ($thermal)
        <style>
            @page { 
                size: 80mm auto; 
                margin: 4mm; 
            }
            
            body, body.thermal-mode {
                font-family: "Courier New", monospace !important;
                font-size: 11px !important;
                padding: 0 !important;
                background: white !important;
            }
            
            .page {
                max-width: 76mm !important;
                margin: 0 auto !important;
                border-radius: 0 !important;
                box-shadow: none !important;
            }
            
            .doc-header {
                background: white !important;
                color: black !important;
                border-bottom: 1px dashed #888 !important;
                padding: 8px 12px !important;
            }
            
            .brand-name {
                font-size: 14px !important;
            }
            
            .doc-name {
                font-size: 12px !important;
            }
            
            .overview-grid {
                grid-template-columns: 1fr !important;
                gap: 8px !important;
                padding: 12px !important;
                margin-bottom: 8px !important;
            }
            
            .metric-value {
                font-size: 16px !important;
            }
            
            .two-columns {
                grid-template-columns: 1fr !important;
                gap: 12px !important;
                padding: 0 12px !important;
                margin-bottom: 12px !important;
            }
            
            .panel {
                background: none !important;
                border: none !important;
                padding: 8px 0 !important;
            }
            
            .content-section {
                padding: 0 !important;
            }
            
            .overview-item {
                text-align: left !important;
                border-bottom: 1px dashed #ddd;
                padding: 6px 0;
            }
            
            .accounts-note {
                margin: 0 12px 12px !important;
                padding: 10px !important;
            }
            
            .footer-note {
                padding: 12px !important;
                border-top: 1px dashed #888 !important;
            }
            
            .signature-row {
                display: none !important;
            }

            .thermal-receipt {
                color: #000;
                padding: 8px 10px 10px !important;
            }

            .thermal-business {
                text-align: center;
                border-bottom: 1px dashed #777;
                padding-bottom: 6px;
                margin-bottom: 7px;
            }

            .thermal-business-name {
                font-size: 14px;
                font-weight: 700;
                line-height: 1.2;
            }

            .thermal-business-line {
                font-size: 10px;
                line-height: 1.25;
                margin-top: 2px;
            }

            .thermal-title {
                text-align: center;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                border-bottom: 1px dashed #777;
                padding-bottom: 6px;
                margin-bottom: 7px;
            }

            .thermal-row {
                display: flex;
                justify-content: space-between;
                gap: 8px;
                font-size: 10.5px;
                line-height: 1.35;
                padding: 2px 0;
            }

            .thermal-row span:first-child {
                flex: 0 0 28mm;
            }

            .thermal-row span:last-child {
                flex: 1;
                text-align: right;
                overflow-wrap: anywhere;
            }

            .thermal-money {
                border-top: 1px dashed #777;
                border-bottom: 1px dashed #777;
                margin: 7px 0;
                padding: 6px 0;
            }

            .thermal-money .thermal-row {
                font-size: 12px;
                font-weight: 700;
            }

            .thermal-signatures {
                margin-top: 10px;
                font-size: 10px;
                line-height: 1.7;
            }
        </style>
    @endif

    <div class="toolbar">
        <button onclick="window.print()">🖨️ Print / Save PDF</button>
    </div>

    <div class="page">
        @if ($thermal)
            <div class="thermal-receipt">
                <div class="thermal-business">
                    <div class="thermal-business-name">{{ config('business.name', 'Apples Of Gold') }}</div>
                    @if (config('business.phone'))
                        <div class="thermal-business-line">Tel: {{ config('business.phone') }}</div>
                    @endif
                    @if (config('business.address'))
                        <div class="thermal-business-line">{{ config('business.address') }}</div>
                    @endif
                </div>

                <div class="thermal-title">Supplier Payment Voucher</div>

                <div class="thermal-row">
                    <span>No:</span>
                    <span>{{ $payment->payment_no }}</span>
                </div>
                <div class="thermal-row">
                    <span>Date:</span>
                    <span>{{ optional($payment->payment_date)->format('d M Y H:i') ?: $printedAt->format('d M Y H:i') }}</span>
                </div>
                <div class="thermal-row">
                    <span>Supplier:</span>
                    <span>{{ $payment->supplier?->name ?? '-' }}</span>
                </div>
                <div class="thermal-row">
                    <span>Purchase:</span>
                    <span>{{ $payment->purchase?->purchase_no ?? '-' }}</span>
                </div>
                <div class="thermal-row">
                    <span>Payment Mode:</span>
                    <span>{{ $payment->paymentMode?->name ?? 'Not set' }}</span>
                </div>
                <div class="thermal-row">
                    <span>Reference:</span>
                    <span>{{ $payment->reference_no ?: $payment->supplier_invoice_no ?: $payment->cheque_number ?: '-' }}</span>
                </div>

                <div class="thermal-money">
                    <div class="thermal-row">
                        <span>Paid:</span>
                        <span>{{ $currency }} {{ number_format($amountPaidNow, 0) }}</span>
                    </div>
                    <div class="thermal-row">
                        <span>Balance After:</span>
                        <span>{{ $currency }} {{ number_format($balanceAfterPayment, 0) }}</span>
                    </div>
                </div>

                <div class="thermal-signatures">
                    <div>Prepared By: __________________</div>
                    <div>Received By: __________________</div>
                </div>
            </div>
        @else
        <!-- Header Section -->
        <div class="doc-header">
            <div class="brand-info">
                <div class="brand-name">{{ config('business.name', 'Apples Of Gold') }}</div>
                  <!-- <div class="brand-tagline">{{ config('business.tagline', 'Business Management System') }}</div> -->
                <div class="brand-meta">
                    {{ config('business.address', 'Mutungo') }}<br>
                    Tel: {{ config('business.phone', '+256700000000') }} / Email: {{ config('business.email', 'info@example.com') }}<br>
                    TIN: {{ config('business.tin', 'TIN-NUMBER') }}
                </div>
            </div>
            <div class="doc-title-section">
                <div class="doc-label">Supplier Payment Voucher</div>
                <div class="doc-name">{{ $payment->payment_no }}</div>
                <div class="doc-meta">
                    <span>Date: {{ optional($payment->payment_date)->format('d M Y') }}</span>
                    <span>Applied To: {{ $payment->purchase?->purchase_no ?? '-' }}</span>
                    <span>Payment Mode: {{ $payment->paymentMode?->name ?? 'Not set' }}</span>
                    <span>Printed: {{ $printedAt->format('d M Y H:i') }}</span>
                </div>
            </div>
        </div>

        <!-- Overview Section -->
        <div class="content-section">
            <div class="overview-grid">
                <div class="overview-item">
                    <div class="section-kicker">AMOUNT PAID</div>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">BALANCE AFTER PAYMENT</div>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) ($payment->purchase?->balance_due ?? 0), 0) }}</div>
                </div>
                <div class="overview-item">
                    <div class="section-kicker">STATUS</div>
                    <div class="metric-value">{{ ucfirst($payment->status ?? 'posted') }}</div>
                </div>
            </div>

            <!-- Two Column Content -->
            <div class="two-columns">
                <div class="panel">
                    <div class="panel-title">PAID TO</div>
                    <div class="profile-name">{{ $payment->supplier?->name ?? '-' }}</div>
                    <div class="profile-line">{{ $payment->supplier?->phone ?: 'No phone on record' }}</div>
                    <div class="profile-line">{{ $payment->supplier?->address ?: ($payment->supplier?->country ?: '-') }}</div>
                </div>

                <div class="panel">
                    <div class="panel-title">PAYMENT SUMMARY</div>
                    <table class="summary-table">
                        <tr><td>Store</td><td>{{ $payment->store?->name ?? '-' }}</td></tr>
                        <tr><td>Reference</td><td>{{ $payment->reference_no ?: $payment->supplier_invoice_no ?: $payment->cheque_number ?: '-' }}</td></tr>
                        <tr><td>Purchase Number</td><td>{{ $payment->purchase?->purchase_no ?? '-' }}</td></tr>
                        <tr><td>Before Payment</td><td>{{ $currency }} {{ number_format($balanceBeforePayment, 0) }}</td></tr>
                        <tr><td>Paid Now</td><td>{{ $currency }} {{ number_format($amountPaidNow, 0) }}</td></tr>
                        <tr><td>Balance After</td><td>{{ $currency }} {{ number_format($balanceAfterPayment, 0) }}</td></tr>
                    </table>
                </div>
            </div>

            <!-- Accounts Note -->
            <div class="accounts-note">
                <div class="panel-title" style="border-bottom: none; padding-bottom: 0; margin-bottom: 8px;">📝 ACCOUNTS NOTE</div>
                <div class="detail-copy">
                    This confirms that {{ $currency }} {{ number_format($amountPaidNow, 0) }} was paid to {{ $payment->supplier?->name ?? 'the supplier' }} and applied to purchase {{ $payment->purchase?->purchase_no ?? '-' }}.
                    @if ($payment->remarks)
                        <br><br><strong>Remarks:</strong> {{ $payment->remarks }}
                    @endif
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-note">
            {{ config('business.statement_footer', 'This document is system-generated and intended for account reconciliation.') }}
        </div>

        <!-- Signature Row -->
        <div class="signature-row">
            <div class="signature-item">Prepared By</div>
            <div class="signature-item">Received By</div>
        </div>
        @endif
    </div>

    <script>
        // Auto-print if print parameter is present
        if (window.location.search.includes('print=1')) {
            window.onload = function() {
                window.print();
            }
        }
    </script>
</body>
</html>
