<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Payment {{ $payment->payment_no }}</title>
    <style>
        @include('partials.print_document_styles')
    </style>
</head>
<body>
    @php($currency = config('business.currency', 'UGX'))
    @php($thermal = request()->string('theme')->toString() === 'thermal')
    @php($printedAt = now())
    @if ($thermal)
        <style>
            @page { size: 80mm auto; margin: 4mm; }
            body { font-family: "Courier New", monospace; font-size: 12px; }
            .toolbar { display: none; }
            .page { max-width: 76mm; padding: 0; margin: 0 auto; border: 0; box-shadow: none; }
            .header { border-bottom: 1px dashed #888; margin-bottom: 8px; }
            .brand-name, .doc-name { font-size: 16px; }
            .brand-tagline, .brand-meta, .doc-meta, .doc-label { font-size: 11px; color: #000; }
            .brand-name, .doc-name, .section-kicker, .panel-title, .metric-value { color: #000; }
            .header, .overview, .content, .signature-row,
            .header tbody, .overview tbody, .content tbody, .signature-row tbody,
            .header tr, .overview tr, .content tr, .signature-row tr,
            .header td, .overview td, .content td, .signature-row td { display: block; width: 100%; }
            .doc-block { text-align: left; margin-top: 8px; }
            .overview, .content, .signature-row { margin: 0; border-spacing: 0; }
            .overview td, .content td { margin-bottom: 8px; border: 0; padding: 0 0 8px; }
            .panel { border: 0; padding: 6px 0; min-height: 0; }
            .footer-note { text-align: center; border-top: 1px dashed #888; color: #000; }
        </style>
    @endif

    <div class="toolbar"><button onclick="window.print()">Print</button></div>

    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Customer Payment Receipt',
            'documentName' => $payment->payment_no,
            'documentMetaLines' => [
                'Date: '.optional($payment->payment_date)->format('d M Y'),
                'Applied To: '.($payment->sale?->sale_no ?? '-'),
                'Payment Mode: '.($payment->paymentMode?->name ?? 'Not set'),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="overview">
            <tr>
                <td>
                    <span class="section-kicker">Amount Received</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Balance After Payment</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) ($payment->sale?->balance_due ?? 0), 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Status</span>
                    <div class="metric-value">{{ ucfirst($payment->status ?? 'posted') }}</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Received From</span>
                        <div class="profile-name">{{ $payment->customer?->name ?? '-' }}</div>
                        <div class="profile-line">{{ $payment->customer?->phone ?: 'No phone on record' }}</div>
                        <div class="profile-line">{{ $payment->customer?->location ?: ($payment->customer?->address ?: '-') }}</div>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Payment Summary</span>
                        <table class="summary-table">
                            <tr><td>Store</td><td>{{ $payment->store?->name ?? '-' }}</td></tr>
                            <tr><td>Reference</td><td>{{ $payment->reference_no ?: $payment->cheque_number ?: '-' }}</td></tr>
                            <tr><td>Sale Number</td><td>{{ $payment->sale?->sale_no ?? '-' }}</td></tr>
                            <tr><td>Sale Total</td><td>{{ $currency }} {{ number_format((float) ($payment->sale?->total_amount ?? 0), 0) }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="content content-1">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Receipt Note</span>
                        <div class="detail-copy">
                            This confirms that {{ $currency }} {{ number_format((float) $payment->amount, 0) }} was received from {{ $payment->customer?->name ?? 'the customer' }} and applied to sale {{ $payment->sale?->sale_no ?? '-' }}.
                            @if ($payment->remarks)
                                <br><br>Remarks: {{ $payment->remarks }}
                            @endif
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">{{ config('business.receipt_footer', 'Thank you for your business.') }}</div>

        @unless ($thermal)
            <table class="signature-row">
                <tr>
                    <td>Prepared By</td>
                    <td>Received By</td>
                </tr>
            </table>
        @endunless
    </div>
</body>
</html>
