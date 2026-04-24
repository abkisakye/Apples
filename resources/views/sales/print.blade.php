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
            @page { size: 80mm auto; margin: 4mm; }
            body { font-family: "Courier New", monospace; font-size: 12px; }
            .toolbar { display: none; }
            .page { max-width: 76mm; padding: 0; margin: 0 auto; border: 0; box-shadow: none; }
            .header { border-bottom: 1px dashed #777; margin-bottom: 8px; }
            .brand-name, .doc-name { font-size: 16px; color: #000; }
            .brand-tagline, .brand-meta, .doc-meta, .doc-label { font-size: 11px; color: #000; }
            .section-kicker, .panel-title, .metric-value { color: #000; }
            .header, .overview, .content, .signature-row,
            .header tbody, .overview tbody, .content tbody, .signature-row tbody,
            .header tr, .overview tr, .content tr, .signature-row tr,
            .header td, .overview td, .content td, .signature-row td { display: block; width: 100%; }
            .doc-block { text-align: left; margin-top: 8px; }
            .overview, .content, .signature-row { margin: 0; border-spacing: 0; }
            .overview td, .content td { margin-bottom: 8px; border: 0; padding: 0 0 8px; }
            .panel { border: 0; padding: 6px 0; min-height: 0; }
            .ledger th { background: none; color: #000; border-bottom: 1px dashed #777; border-top: 0; border-left: 0; border-right: 0; }
            .ledger td { border-left: 0; border-right: 0; border-top: 0; border-bottom: 1px dotted #bbb; }
            .footer-note { text-align: center; border-top: 1px dashed #777; color: #000; }
        </style>
    @endif

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
</body>
</html>
