@php
    $requestedTheme = request()->string('theme')->toString();
    $thermal = $requestedTheme !== 'full';
    $isFullTheme = ! $thermal;
    $thermalPaymentLineCount = max($sale->payments->where('status', 'posted')->count(), 1);
    $thermalPageHeight = min(600, max(130, 95 + ($sale->items->count() * 7) + ($thermalPaymentLineCount * 5) + 25));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sale->sale_type === 'cash' ? 'Receipt' : 'Invoice' }} {{ $sale->sale_no }}</title>
    <style>
        @if ($isFullTheme)
            @include('partials.print_document_styles', ['pageWidth' => '700px'])
            @page {
                size: A4;
                margin: 10mm;
            }
            html,
            body {
                width: 100%;
            }
            body.full-a4-body {
                background: #ffffff;
                font-size: 11px;
                line-height: 1.28;
            }
            .full-a4-body .toolbar {
                max-width: 700px;
                padding: 8px 0 6px;
            }
            .full-a4-body .page.full-document {
                width: 100%;
                max-width: 700px;
                padding: 12px 14px 14px;
                border: 1px solid #e3dfd1;
                box-shadow: none;
                margin: 0 auto;
            }
            .full-a4-body .header {
                display: table;
                table-layout: fixed;
                margin-bottom: 10px;
                border-bottom-width: 2px;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .header td {
                padding-bottom: 8px;
            }
            .full-a4-body .brand-icon-wrap {
                width: 44px;
                padding-right: 8px;
            }
            .full-a4-body .brand-icon {
                width: 38px;
                height: 38px;
            }
            .full-a4-body .brand-name {
                font-size: 20px;
                line-height: 1.05;
            }
            .full-a4-body .brand-tagline,
            .full-a4-body .brand-meta,
            .full-a4-body .doc-meta {
                font-size: 10px;
                line-height: 1.35;
            }
            .full-a4-body .brand-meta {
                margin-top: 5px;
            }
            .full-a4-body .doc-block {
                width: 210px;
            }
            .full-a4-body .doc-label {
                font-size: 9px;
                margin-bottom: 3px;
            }
            .full-a4-body .doc-name {
                font-size: 18px;
                line-height: 1.1;
                margin-bottom: 4px;
            }
            .full-a4-body .content {
                display: table;
                border-collapse: separate;
                border-spacing: 8px 0;
                table-layout: fixed;
                margin: 0 -8px 9px;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .header tbody,
            .full-a4-body .content tbody,
            .full-a4-body .ledger tbody {
                display: table-row-group;
            }
            .full-a4-body .header tr,
            .full-a4-body .content tr,
            .full-a4-body .ledger tr {
                display: table-row;
            }
            .full-a4-body .header td,
            .full-a4-body .content td,
            .full-a4-body .ledger td,
            .full-a4-body .ledger th {
                display: table-cell;
            }
            .full-a4-body .content td {
                width: 50%;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .panel {
                min-height: 0;
                padding: 9px 10px;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .panel-title {
                font-size: 9px;
                margin-bottom: 4px;
            }
            .full-a4-body .profile-name {
                font-size: 14px;
                margin-bottom: 4px;
            }
            .full-a4-body .profile-line,
            .full-a4-body .detail-copy {
                font-size: 10.5px;
                line-height: 1.32;
            }
            .full-a4-body .summary-table {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .summary-table tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .summary-table td {
                padding: 4px 0;
                font-size: 10.5px;
            }
            .full-a4-body .ledger {
                display: table;
                table-layout: fixed;
                margin: 4px 0 9px;
                page-break-inside: auto;
                break-inside: auto;
            }
            .full-a4-body .ledger tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .ledger th,
            .full-a4-body .ledger td {
                padding: 5px 6px;
                font-size: 10.5px;
            }
            .full-a4-body .ledger th {
                font-size: 8.5px;
                letter-spacing: .05em;
            }
            .full-a4-body .totals-block,
            .full-a4-body .footer-note,
            .full-a4-body .receipt-closing {
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .full-a4-body .receipt-closing {
                page-break-before: auto;
                break-before: auto;
            }
            .full-a4-body .footer-note {
                margin-top: 8px;
                padding-top: 7px;
                font-size: 10.5px;
                line-height: 1.3;
            }
            @media print {
                @page {
                    size: A4;
                    margin: 10mm;
                }
                html,
                body.full-a4-body {
                    width: auto;
                    margin: 0;
                    padding: 0;
                    background: #ffffff;
                }
                .full-a4-body .toolbar {
                    display: none;
                }
                .full-a4-body .page.full-document {
                    width: 188mm;
                    max-width: 188mm;
                    margin: 0 auto;
                    padding: 4mm 5mm;
                    border: 1px solid #e3dfd1;
                    box-shadow: none;
                }
                .full-a4-body .header {
                    margin-bottom: 5mm;
                    border-bottom-width: 1.5pt;
                }
                .full-a4-body .header td {
                    padding-bottom: 3mm;
                }
                .full-a4-body .brand-icon-wrap {
                    width: 13mm;
                    padding-right: 2mm;
                }
                .full-a4-body .brand-icon {
                    width: 10mm;
                    height: 10mm;
                }
                .full-a4-body .brand-name {
                    font-size: 16pt;
                }
                .full-a4-body .brand-tagline,
                .full-a4-body .brand-meta,
                .full-a4-body .doc-meta,
                .full-a4-body .profile-line,
                .full-a4-body .detail-copy,
                .full-a4-body .summary-table td,
                .full-a4-body .footer-note {
                    font-size: 8.2pt;
                    line-height: 1.25;
                }
                .full-a4-body .doc-block {
                    width: 58mm;
                }
                .full-a4-body .doc-label,
                .full-a4-body .panel-title {
                    font-size: 7.2pt;
                }
                .full-a4-body .doc-name {
                    font-size: 15pt;
                    margin-bottom: 2mm;
                }
                .full-a4-body .content {
                    display: table;
                    border-spacing: 3mm 0;
                    margin: 0 -3mm 3mm;
                }
                .full-a4-body .header tbody,
                .full-a4-body .content tbody,
                .full-a4-body .ledger tbody {
                    display: table-row-group;
                }
                .full-a4-body .header tr,
                .full-a4-body .content tr,
                .full-a4-body .ledger tr {
                    display: table-row;
                }
                .full-a4-body .header td,
                .full-a4-body .content td,
                .full-a4-body .ledger td,
                .full-a4-body .ledger th {
                    display: table-cell;
                }
                .full-a4-body .content td {
                    width: 50%;
                    margin-bottom: 0;
                }
                .full-a4-body .panel {
                    padding: 3mm;
                    min-height: 0;
                }
                .full-a4-body .profile-name {
                    font-size: 11pt;
                    margin-bottom: 1.4mm;
                }
                .full-a4-body .summary-table td {
                    padding: 1.35mm 0;
                }
                .full-a4-body .ledger {
                    display: table;
                    margin: 2mm 0 3mm;
                    page-break-inside: auto;
                    break-inside: auto;
                }
                .full-a4-body .ledger th,
                .full-a4-body .ledger td {
                    padding: 1.55mm 1.8mm;
                    font-size: 8.2pt;
                }
                .full-a4-body .ledger th {
                    font-size: 7pt;
                }
                .full-a4-body .footer-note {
                    margin-top: 3mm;
                    padding-top: 2mm;
                }
            }
        @else
            @page {
                size: 80mm {{ $thermalPageHeight }}mm;
                margin: 0;
            }
            * {
                box-sizing: border-box;
            }
            html,
            body {
                width: 80mm;
                max-width: 80mm;
                margin: 0;
                padding: 0;
                background: #ffffff;
                color: #000000;
            }
            body {
                font-family: "Courier New", Consolas, monospace;
                font-size: 13px;
                line-height: 1.25;
            }
            .no-print {
                width: 76mm;
                margin: 0 auto 8px;
                padding: 8px 0 0;
                font-family: "Trebuchet MS", "Segoe UI", sans-serif;
                font-size: 12px;
                line-height: 1.35;
                color: #1f2937;
            }
            .print-actions {
                display: flex;
                gap: 6px;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
            }
            .print-actions button,
            .print-actions a {
                border: 0;
                border-radius: 8px;
                padding: 8px 10px;
                background: #111827;
                color: #ffffff;
                font-weight: 800;
                text-decoration: none;
                cursor: pointer;
            }
            .print-instructions {
                margin-top: 7px;
                padding: 7px 8px;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                background: #f9fafb;
            }
            .receipt-paper {
                width: 72mm;
                max-width: 72mm;
                margin: 0 auto;
                padding: 2mm;
                background: #ffffff;
                font-family: "Courier New", Consolas, monospace;
                font-size: 13px;
                line-height: 1.25;
                color: #000000;
            }
            .center {
                text-align: center;
            }
            .brand {
                font-size: 16px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: .02em;
            }
            .business-meta {
                font-size: 12px;
                line-height: 1.25;
            }
            .rule {
                margin: 5px 0;
                border-top: 1px dashed #000000;
            }
            .receipt-title {
                margin: 5px 0;
                font-size: 13px;
                font-weight: 900;
                text-align: center;
                text-transform: uppercase;
            }
            .receipt-row {
                display: flex;
                justify-content: space-between;
                gap: 6px;
                align-items: flex-start;
            }
            .receipt-row span:first-child {
                flex: 1 1 auto;
            }
            .receipt-row strong,
            .receipt-row span:last-child {
                flex: 0 0 auto;
                max-width: 45mm;
                text-align: right;
                overflow-wrap: anywhere;
            }
            table.receipt-items {
                width: 100%;
                max-width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }
            .receipt-items th,
            .receipt-items td {
                padding: 2px 0;
                vertical-align: top;
            }
            .receipt-items th {
                border-bottom: 1px dashed #000000;
                font-size: 11px;
                text-align: left;
            }
            .receipt-items th:nth-child(1),
            .receipt-items td:nth-child(1) {
                width: 38mm;
            }
            .receipt-items th:nth-child(2),
            .receipt-items td:nth-child(2) {
                width: 8mm;
            }
            .receipt-items th:nth-child(3),
            .receipt-items td:nth-child(3) {
                width: 11mm;
            }
            .receipt-items th:nth-child(4),
            .receipt-items td:nth-child(4) {
                width: 13mm;
            }
            .qty,
            .money {
                text-align: right;
                white-space: nowrap;
            }
            .item-name {
                overflow-wrap: anywhere;
                word-break: normal;
            }
            .totals {
                margin-top: 4px;
            }
            .grand-total {
                font-size: 15px;
                font-weight: 900;
            }
            .payment-breakdown-title {
                margin-top: 4px;
                font-weight: 900;
                text-transform: uppercase;
            }
            .footer {
                margin-top: 7px;
                text-align: center;
                font-size: 11px;
            }
            .print-credit {
                margin-top: 7px;
                padding-top: 5px;
                border-top: 1px dashed #000000;
                color: #000000;
                font-size: 10px;
                line-height: 1.25;
                text-align: center;
            }
            @media screen {
                .receipt-screen-wrap {
                    display: flex;
                    justify-content: center;
                    width: 100%;
                }
            }
            @media print {
                html,
                body {
                    width: 80mm;
                    max-width: 80mm;
                    margin: 0 !important;
                    padding: 0 !important;
                    background: #ffffff !important;
                }
                .receipt-paper {
                    width: 72mm;
                    max-width: 72mm;
                    margin: 0;
                    padding: 2mm;
                    box-shadow: none;
                }
                .no-print,
                .format-selector,
                .print-actions,
                nav,
                aside,
                header,
                footer,
                button,
                a.button-link {
                    display: none !important;
                }
            }
        @endif
    </style>
</head>
<body class="{{ $isFullTheme ? 'full-a4-body' : '' }}">
    @php($currency = config('business.currency', 'UGX'))
    @php($documentTitle = $sale->sale_type === 'cash' ? 'Sales Receipt' : 'Sales Invoice')
    @php($paidAmount = (float) ($sale->cash_tendered ?: $sale->amount_paid))
    @php($customerName = $sale->customer?->name ?? 'Walk-in customer')
    @php($printedAt = now())
    @if ($thermal)
        <div class="no-print">
            <div class="print-actions">
                <button type="button" onclick="window.print()">Print Receipt</button>
                <a href="{{ route('sales.show', $sale) }}">Back to Sale</a>
            </div>
            <div class="print-instructions">
                For POS printer: use Paper 80mm, Margins None, Scale 100, Headers and footers Off. Do not use Fit to page width.
            </div>
        </div>

        <div class="receipt-screen-wrap">
        <div class="receipt-paper">
            <div class="center">
                <div class="brand">{{ strtoupper(config('business.name', 'Apples Of Gold')) }}</div>
                @if (config('business.tagline'))
                    <div class="business-meta">{{ config('business.tagline') }}</div>
                @endif
                @if (config('business.address'))
                    <div class="business-meta">{{ config('business.address') }}</div>
                @endif
                @if (config('business.phone'))
                    <div class="business-meta">Tel: {{ config('business.phone') }}</div>
                @endif
            </div>

            <div class="rule"></div>
            <div class="receipt-title">{{ $sale->sale_type === 'cash' ? 'Cash Sale Receipt' : 'Credit Sale Invoice' }}</div>
            <div class="receipt-row"><span>Receipt No:</span><strong>{{ $sale->sale_no }}</strong></div>
            <div class="receipt-row"><span>Invoice No:</span><strong>{{ $sale->sale_no }}</strong></div>
            <div class="receipt-row"><span>Date:</span><strong>{{ optional($sale->sale_date)->format('d M Y') }}</strong></div>
            <div class="receipt-row"><span>Time:</span><strong>{{ $printedAt->format('H:i') }}</strong></div>
            <div class="receipt-row"><span>Cashier:</span><strong>{{ $sale->createdBy?->name ?? $sale->createdBy?->username ?? '-' }}</strong></div>
            <div class="receipt-row"><span>Customer:</span><strong>{{ $customerName }}</strong></div>
            <div class="rule"></div>

            <table class="receipt-items">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="qty">Qty</th>
                        <th class="money">Price</th>
                        <th class="money">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="item-name">{{ $item->display_item_label ?? $item->product?->name ?? '-' }}</td>
                            <td class="qty">{{ rtrim(rtrim(number_format((float) $item->quantity, 3), '0'), '.') }}</td>
                            <td class="money">{{ number_format((float) $item->unit_price, 0) }}</td>
                            <td class="money">{{ number_format((float) $item->line_total, 0) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="rule"></div>
            <div class="totals">
                <div class="receipt-row"><span>Subtotal</span><strong>{{ $currency }} {{ number_format((float) $sale->subtotal, 0) }}</strong></div>
                @if ((float) $sale->discount_amount > 0)
                    <div class="receipt-row"><span>Discount</span><strong>{{ $currency }} {{ number_format((float) $sale->discount_amount, 0) }}</strong></div>
                @endif
                <div class="receipt-row grand-total"><span>Total</span><strong>{{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</strong></div>
                <div class="receipt-row"><span>Paid</span><strong>{{ $currency }} {{ number_format((float) $paidAmount, 0) }}</strong></div>
                @if ($sale->change_given > 0)
                    <div class="receipt-row"><span>Change</span><strong>{{ $currency }} {{ number_format((float) $sale->change_given, 0) }}</strong></div>
                @endif
                @if ($sale->balance_due > 0)
                    <div class="receipt-row"><span>Balance</span><strong>{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</strong></div>
                    <div class="receipt-row"><span>Due</span><strong>{{ optional($sale->credit_due_date)->format('d M Y') ?: '-' }}</strong></div>
                @endif
                <div class="payment-breakdown-title">Payment</div>
                @php($postedPayments = $sale->payments->where('status', 'posted'))
                @forelse ($postedPayments as $payment)
                    <div class="receipt-row">
                        <span>{{ $payment->paymentMode?->name ?? $sale->paymentMode?->name ?? 'Payment' }}</span>
                        <strong>{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</strong>
                    </div>
                @empty
                    <div class="receipt-row">
                        <span>{{ $sale->paymentMode?->name ?? 'Payment' }}</span>
                        <strong>{{ $currency }} {{ number_format((float) $sale->amount_paid, 0) }}</strong>
                    </div>
                @endforelse
            </div>

            @if ($sale->remarks)
                <div class="rule"></div>
                <div>Note: {{ $sale->remarks }}</div>
            @endif

            <div class="rule"></div>
            <div class="footer">
                {{ $sale->sale_type === 'cash'
                    ? config('business.receipt_footer', 'Thank you for shopping with Apples Of Gold.')
                    : config('business.invoice_footer', 'Please settle outstanding balances by the due date shown.') }}
            </div>
            @include('partials.developer_credit')
        </div>
        </div>
    @else

    <div class="toolbar"><button onclick="window.print()">Print</button></div>

    <div class="page full-document">
        @include('partials.print_document_header', [
            'documentLabel' => $documentTitle,
            'documentName' => $sale->sale_no,
            'documentMetaLines' => [
                'Date: '.optional($sale->sale_date)->format('d M Y'),
                'Type: '.ucfirst($sale->sale_type),
                'Payment Mode: '.($sale->paymentMode?->name ?? '-'),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Customer</span>
                        <div class="profile-name">{{ $customerName }}</div>
                        <div class="profile-line">{{ $sale->customer?->phone ?: 'No phone recorded' }}</div>
                        <div class="profile-line">{{ $sale->customer?->location ?: ($sale->customer?->address ?: 'No location recorded') }}</div>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Transaction Summary</span>
                        <table class="summary-table">
                            <tr><td>Store</td><td>{{ $sale->store?->name ?? '-' }}</td></tr>
                            <tr><td>Status</td><td>{{ ucfirst($sale->status) }}</td></tr>
                            <tr><td>Paid Now</td><td>{{ $currency }} {{ number_format($paidAmount, 0) }}</td></tr>
                            <tr><td>Balance</td><td>{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</td></tr>
                            <tr>
                                <td>{{ $sale->sale_type === 'credit' ? 'Due Date' : ($sale->change_given > 0 ? 'Change' : 'Type') }}</td>
                                <td>
                                    @if ($sale->sale_type === 'credit')
                                        {{ optional($sale->credit_due_date)->format('d M Y') ?: '-' }}
                                    @elseif ($sale->change_given > 0)
                                        {{ $currency }} {{ number_format((float) $sale->change_given, 0) }}
                                    @else
                                        Cash sale
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="ledger">
            <colgroup>
                <col style="width: 34%;">
                <col style="width: 16%;">
                <col style="width: 10%;">
                <col style="width: 20%;">
                <col style="width: 20%;">
            </colgroup>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Unit</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->display_item_label ?? $item->product?->name ?? '-' }}</td>
                        <td>{{ $item->productUnit?->unit_name ?? 'Unit not set' }}</td>
                        <td class="qty">{{ number_format((float) $item->quantity, 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format((float) $item->unit_price, 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format((float) $item->line_total, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="content receipt-closing">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">{{ $sale->sale_type === 'cash' ? 'Receipt Note' : 'Invoice Note' }}</span>
                        <div class="detail-copy">
                            {{ $sale->sale_type === 'cash'
                                ? 'This document confirms payment received and goods issued to the customer.'
                                : 'This document confirms goods issued and shows the remaining balance to be settled by the due date.' }}
                            @if ($sale->remarks)
                                <br><br>Remarks: {{ $sale->remarks }}
                            @endif
                        </div>
                    </div>
                </td>
                <td>
                    <div class="panel totals-block">
                        <span class="panel-title">Totals</span>
                        <table class="summary-table">
                            <tr><td>Subtotal</td><td>{{ $currency }} {{ number_format((float) $sale->subtotal, 0) }}</td></tr>
                            @if ((float) $sale->discount_amount > 0)
                                <tr><td>Discount</td><td>- {{ $currency }} {{ number_format((float) $sale->discount_amount, 0) }}</td></tr>
                            @endif
                            <tr><td>Paid</td><td>{{ $currency }} {{ number_format((float) $sale->amount_paid, 0) }}</td></tr>
                            <tr><td>Balance</td><td>{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</td></tr>
                            @if ($sale->change_given > 0)
                                <tr><td>Change</td><td>{{ $currency }} {{ number_format((float) $sale->change_given, 0) }}</td></tr>
                            @endif
                            <tr><td>Total</td><td>{{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">
            {{ $sale->sale_type === 'cash'
                ? config('business.receipt_footer', 'Thank you for your business.')
                : config('business.invoice_footer', 'Please settle outstanding balances by the due date shown on this invoice.') }}
        </div>
        @include('partials.developer_credit')
    </div>
    @endif
</body>
</html>
