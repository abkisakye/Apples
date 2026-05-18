<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $sale->sale_type === 'cash' ? 'Receipt' : 'Invoice' }} {{ $sale->sale_no }}</title>
    <style>
        @include('partials.print_document_styles', ['pageWidth' => '860px'])
    </style>
</head>
<body>
    @php($currency = config('business.currency', 'UGX'))
    @php($thermal = request()->string('theme')->toString() === 'thermal')
    @php($documentTitle = $sale->sale_type === 'cash' ? 'Sales Receipt' : 'Sales Invoice')
    @php($paidAmount = (float) ($sale->cash_tendered ?: $sale->amount_paid))
    @php($customerName = $sale->customer?->name ?? 'Walk-in customer')
    @php($printedAt = now())
    @if ($thermal)
        <style>
            @page { size: 80mm auto; margin: 3mm; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                background: #fff;
                color: #000;
                font-family: "Courier New", Consolas, monospace;
                font-size: 11px;
                line-height: 1.25;
            }
            .toolbar {
                max-width: 80mm;
                margin: 0 auto;
                padding: 8px 0;
                text-align: center;
            }
            .toolbar button {
                border: 0;
                border-radius: 8px;
                padding: 8px 12px;
                background: #111;
                color: #fff;
                font-weight: 700;
            }
            .thermal-receipt {
                width: 74mm;
                margin: 0 auto;
                padding: 0;
            }
            .center { text-align: center; }
            .brand {
                font-size: 15px;
                font-weight: 900;
                text-transform: uppercase;
                letter-spacing: .04em;
            }
            .muted { color: #000; }
            .rule {
                margin: 6px 0;
                border-top: 1px dashed #000;
            }
            .receipt-title {
                font-weight: 900;
                text-align: center;
                text-transform: uppercase;
                margin: 5px 0;
            }
            .receipt-row {
                display: flex;
                justify-content: space-between;
                gap: 6px;
            }
            .receipt-row span:first-child {
                flex: 1;
            }
            .receipt-row strong,
            .receipt-row span:last-child {
                text-align: right;
                white-space: nowrap;
            }
            table {
                width: 100%;
                border-collapse: collapse;
            }
            th,
            td {
                padding: 2px 0;
                vertical-align: top;
            }
            th {
                border-bottom: 1px dashed #000;
                font-size: 10px;
                text-align: left;
            }
            .qty,
            .money {
                text-align: right;
                white-space: nowrap;
            }
            .item-name {
                max-width: 36mm;
                word-break: break-word;
            }
            .totals {
                margin-top: 4px;
            }
            .grand-total {
                font-size: 14px;
                font-weight: 900;
            }
            .footer {
                margin-top: 8px;
                text-align: center;
                font-size: 10px;
            }
            @media print {
                .toolbar { display: none; }
            }
        </style>
    @endif

    @if ($thermal)
        <div class="toolbar"><button onclick="window.print()">Print Receipt</button></div>

        <div class="thermal-receipt">
            <div class="center">
                <div class="brand">{{ strtoupper(config('business.name', 'Apples Of Gold')) }}</div>
                @if (config('business.tagline'))
                    <div>{{ config('business.tagline') }}</div>
                @endif
                @if (config('business.address'))
                    <div>{{ config('business.address') }}</div>
                @endif
                @if (config('business.phone'))
                    <div>Tel: {{ config('business.phone') }}</div>
                @endif
            </div>

            <div class="rule"></div>
            <div class="receipt-title">{{ $sale->sale_type === 'cash' ? 'Cash Sale Receipt' : 'Credit Sale Invoice' }}</div>
            <div class="receipt-row"><span>No:</span><strong>{{ $sale->sale_no }}</strong></div>
            <div class="receipt-row"><span>Date:</span><strong>{{ optional($sale->sale_date)->format('d M Y') }}</strong></div>
            <div class="receipt-row"><span>Time:</span><strong>{{ $printedAt->format('H:i') }}</strong></div>
            <div class="receipt-row"><span>Cashier:</span><strong>{{ $sale->createdBy?->name ?? $sale->createdBy?->username ?? '-' }}</strong></div>
            <div class="receipt-row"><span>Customer:</span><strong>{{ $customerName }}</strong></div>
            <div class="rule"></div>

            <table>
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
                            <td class="item-name">{{ $item->product?->name ?? '-' }}</td>
                            <td class="qty">{{ number_format((float) $item->quantity, 0) }}</td>
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
                <div class="receipt-row"><span>Mode</span><strong>{{ $sale->paymentMode?->name ?? '-' }}</strong></div>
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
        </div>
    @else

    <div class="toolbar"><button onclick="window.print()">Print</button></div>

    <div class="page">
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
                        <td>{{ $item->product?->name ?? '-' }}</td>
                        <td>{{ $item->productUnit?->unit_name ?? 'Unit not set' }}</td>
                        <td class="qty">{{ number_format((float) $item->quantity, 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format((float) $item->unit_price, 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format((float) $item->line_total, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="content">
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
                    <div class="panel">
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
    </div>
    @endif
</body>
</html>
