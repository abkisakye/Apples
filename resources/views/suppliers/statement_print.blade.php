<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Statement {{ $supplier->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #ffffff;
            color: #202124;
            font-family: "Trebuchet MS", "Segoe UI", sans-serif;
            font-size: 13px;
        }
        .page {
            max-width: 920px;
            margin: 0 auto;
            background: #ffffff;
            padding: 26px 28px 30px;
            border: 1px solid #e3dfd1;
            box-shadow: 0 16px 34px rgba(47, 38, 22, .07);
        }
        .toolbar {
            padding: 16px 0 8px;
            max-width: 920px;
            margin: 0 auto;
        }
        .toolbar button {
            border: 0;
            border-radius: 12px;
            padding: 10px 18px;
            background: linear-gradient(135deg, #d4af37, #ba9324);
            color: #4f3b04;
            cursor: pointer;
            font-weight: 700;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 3px solid #066838;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header td {
            vertical-align: top;
            padding: 0;
            border: 0;
        }
        .brand-block {
            padding-right: 18px;
        }
        .brand-row {
            width: 100%;
            border-collapse: collapse;
        }
        .brand-row td {
            border: 0;
            padding: 0;
            vertical-align: middle;
        }
        .brand-icon-wrap {
            width: 58px;
            padding-right: 10px;
        }
        .brand-icon {
            width: 48px;
            height: 48px;
            display: block;
        }
        .brand-name {
            font-size: 25px;
            line-height: 1.05;
            font-weight: 800;
            color: #066838;
            margin: 0 0 2px;
        }
        .brand-tagline {
            font-size: 12px;
            color: #7a6e46;
            margin: 0;
        }
        .brand-meta {
            margin-top: 8px;
            color: #555555;
            font-size: 12px;
            line-height: 1.5;
        }
        .doc-block {
            width: 260px;
            text-align: right;
        }
        .doc-label {
            font-size: 11px;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: #8a6f1e;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .doc-name {
            font-size: 24px;
            line-height: 1.1;
            font-weight: 800;
            color: #1f1f1f;
            margin-bottom: 6px;
        }
        .doc-meta {
            color: #555555;
            font-size: 12px;
            line-height: 1.55;
        }
        .overview {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin: 0 -10px 16px;
        }
        .overview td {
            width: 33.33%;
            border: 1px solid #dfdac7;
            background: #ffffff;
            padding: 12px 14px;
            vertical-align: top;
        }
        .section-kicker {
            display: block;
            margin-bottom: 6px;
            color: #066838;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .metric-value {
            font-size: 17px;
            line-height: 1.2;
            font-weight: 800;
            color: #1f1f1f;
        }
        .note-copy {
            color: #333333;
            line-height: 1.45;
        }
        .content {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin: 0 -12px 16px;
        }
        .content td {
            width: 50%;
            padding: 0;
            vertical-align: top;
            border: 0;
        }
        .panel {
            border: 1px solid #dfdac7;
            background: #ffffff;
            padding: 14px 16px;
            min-height: 118px;
        }
        .panel-title {
            display: block;
            margin-bottom: 8px;
            color: #066838;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .profile-name {
            font-size: 18px;
            font-weight: 800;
            color: #1f1f1f;
            margin-bottom: 6px;
        }
        .profile-line {
            color: #333333;
            line-height: 1.45;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            border: 0;
            border-bottom: 1px solid #ece7d8;
            padding: 7px 0;
            vertical-align: top;
        }
        .summary-table tr:last-child td {
            border-bottom: 0;
        }
        .summary-table td:first-child {
            color: #555555;
            padding-right: 12px;
        }
        .summary-table td:last-child {
            text-align: right;
            font-weight: 800;
            color: #1f1f1f;
            white-space: nowrap;
        }
        .ledger {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        .ledger th,
        .ledger td {
            border: 1px solid #ece7d8;
            padding: 8px 7px;
            text-align: left;
            vertical-align: top;
        }
        .ledger th {
            background: #066838;
            color: #ffffff;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }
        .ledger tbody tr:nth-child(even) td {
            background: #faf8f2;
        }
        .money {
            text-align: right;
            white-space: nowrap;
        }
        .footer-note {
            margin-top: 16px;
            padding-top: 10px;
            border-top: 1px dashed #cdbb7d;
            color: #555555;
            font-size: 12px;
            line-height: 1.45;
        }
        .signature-row {
            width: 100%;
            border-collapse: separate;
            border-spacing: 16px 0;
            margin: 22px -16px 0;
        }
        .signature-row td {
            width: 50%;
            border-top: 1px solid #cdbb7d;
            padding-top: 10px;
            color: #444444;
        }
        @media print {
            @page { size: A4; margin: 10mm; }
            body { background: #ffffff; }
            .toolbar { display: none; }
            .page {
                max-width: none;
                padding: 0;
                border: 0;
                box-shadow: none;
            }
        }
        @media (max-width: 720px) {
            .header,
            .overview,
            .content,
            .signature-row {
                border-spacing: 0;
                margin: 0;
            }
            .header,
            .overview,
            .content,
            .signature-row,
            .header tbody,
            .overview tbody,
            .content tbody,
            .signature-row tbody,
            .header tr,
            .overview tr,
            .content tr,
            .signature-row tr,
            .header td,
            .overview td,
            .content td,
            .signature-row td {
                display: block;
                width: 100%;
            }
            .doc-block {
                text-align: left;
                margin-top: 14px;
            }
            .overview td,
            .content td,
            .signature-row td {
                margin-bottom: 12px;
            }
        }
    </style>
</head>
<body>
    @php($currency = config('business.currency', 'UGX'))
    @php($isPdfDocument = (bool) ($isPdf ?? false))
    @php($pdfLogoPath = public_path('brand/apples-icon.png'))
    @php($pdfLogoData = file_exists($pdfLogoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($pdfLogoPath)) : null)
    @php($browserLogo = asset('brand/apples-icon.png'))
    @php($logoSrc = $isPdfDocument ? $pdfLogoData : $browserLogo)
    @php($statementDate = now())
    @unless($isPdfDocument)
        <div class="toolbar"><button onclick="window.print()">Print</button></div>
    @endunless
    <div class="page">
        <table class="header">
            <tr>
                <td class="brand-block">
                    <table class="brand-row">
                        <tr>
                            <td class="brand-icon-wrap">
                                @if ($logoSrc)
                                    <img src="{{ $logoSrc }}" alt="{{ config('business.name', 'Apples Of Gold') }} logo" class="brand-icon">
                                @endif
                            </td>
                            <td>
                                <div class="brand-name">{{ config('business.name', 'Apples Of Gold') }}</div>
                                <div class="brand-tagline">{{ config('business.tagline', 'Freshness & Value Every Day') }}</div>
                            </td>
                        </tr>
                    </table>
                    <div class="brand-meta">
                        @if (config('business.address')){{ config('business.address') }}<br>@endif
                        @if (config('business.phone'))Tel: {{ config('business.phone') }}@endif
                        @if (config('business.email')){{ config('business.phone') ? ' / ' : '' }}Email: {{ config('business.email') }}@endif
                        @if (config('business.tin'))<br>TIN: {{ config('business.tin') }}@endif
                    </div>
                </td>
                <td class="doc-block">
                    <div class="doc-label">Supplier Statement</div>
                    <div class="doc-name">{{ $supplier->name }}</div>
                    <div class="doc-meta">
                        Statement Date: {{ $statementDate->format('d M Y') }}<br>
                        Printed: {{ $statementDate->format('d M Y H:i') }}<br>
                        Currency: {{ $currency }}<br>
                        Account Type: Supplier
                    </div>
                </td>
            </tr>
        </table>

        <table class="overview">
            <tr>
                <td>
                    <span class="section-kicker">Closing Balance</span>
                    <div class="metric-value">{{ $currency }} {{ number_format($summary['closing_balance'], 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Entries</span>
                    <div class="metric-value">{{ number_format(count($entries)) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Statement Note</span>
                    <div class="note-copy">Use this statement to review supplier purchases, payments, and outstanding balances.</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Supplier Profile</span>
                        <div class="profile-name">{{ $supplier->name }}</div>
                        <div class="profile-line">{{ $supplier->phone ?: 'No phone on record' }}</div>
                        <div class="profile-line">{{ $supplier->location ?: ($supplier->address ?: '-') }}</div>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Account Summary</span>
                        <table class="summary-table">
                            <tr><td>Opening Balance</td><td>{{ $currency }} {{ number_format($summary['opening_balance'], 0) }}</td></tr>
                            <tr><td>Purchases</td><td>{{ $currency }} {{ number_format($summary['total_purchases'], 0) }}</td></tr>
                            <tr><td>Payments</td><td>{{ $currency }} {{ number_format($summary['total_payments'], 0) }}</td></tr>
                            <tr><td>Returns / Credits</td><td>{{ $currency }} {{ number_format($summary['total_returns'], 0) }}</td></tr>
                            <tr><td>Closing Balance</td><td>{{ $currency }} {{ number_format($summary['closing_balance'], 0) }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="ledger">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Details</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $entry)
                    <tr>
                        <td>{{ optional($entry['date'])->format('d M Y') }}</td>
                        <td>{{ $entry['type'] }}</td>
                        <td>{{ $entry['reference'] }}</td>
                        <td>{{ $entry['details'] }}</td>
                        <td class="money">{{ $currency }} {{ number_format($entry['debit'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($entry['credit'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($entry['running_balance'], 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-note">{{ config('business.statement_footer', 'This statement is system-generated and intended for account reconciliation.') }}</div>

        <table class="signature-row">
            <tr>
                <td>Prepared By</td>
                <td>Approved By</td>
            </tr>
        </table>
    </div>
</body>
</html>
