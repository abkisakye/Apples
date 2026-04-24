<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase {{ $purchase->purchase_no }}</title>
    <style>
        @include('partials.print_document_styles')
    </style>
</head>
<body>
    @php($currency = config('business.currency', 'UGX'))
    @php($printedAt = now())
    <div class="toolbar"><button onclick="window.print()">Print</button></div>
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => $purchase->purchase_type === 'credit' ? 'Purchase Invoice' : 'Purchase Voucher',
            'documentName' => $purchase->purchase_no,
            'documentMetaLines' => array_filter([
                'Date: '.optional($purchase->purchase_date)->format('d M Y'),
                $purchase->purchase_type === 'credit' && $purchase->credit_due_date ? 'Due Date: '.optional($purchase->credit_due_date)->format('d M Y') : null,
                'Payment Mode: '.($purchase->paymentMode?->name ?? '-'),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ]),
        ])

        <table class="overview">
            <tr>
                <td>
                    <span class="section-kicker">Amount Paid</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchase->amount_paid, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Balance Due</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Accounts Note</span>
                    <div class="note-copy">{{ $purchase->purchase_type === 'credit' ? 'Supplier balance remains open after this document.' : 'Purchase was settled immediately at posting time.' }}</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Supplier</span>
                        <div class="profile-name">{{ $purchase->supplier?->name ?? 'Supplier not linked' }}</div>
                        <div class="profile-line">{{ $purchase->supplier?->phone ?: 'No phone on record' }}</div>
                        <div class="profile-line">{{ $purchase->supplier?->address ?: '-' }}</div>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Document Summary</span>
                        <table class="summary-table">
                            <tr><td>Store</td><td>{{ $purchase->store?->name ?? '-' }}</td></tr>
                            <tr><td>Type</td><td>{{ ucfirst($purchase->purchase_type) }}</td></tr>
                            <tr><td>Status</td><td>{{ ucfirst($purchase->status) }}</td></tr>
                            <tr><td>Balance</td><td>{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</td></tr>
                            @if ($purchase->supplier_invoice_no)
                                <tr><td>Supplier Ref</td><td>{{ $purchase->supplier_invoice_no }}</td></tr>
                            @endif
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <table class="ledger">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Qty</th>
                    <th>Unit Cost</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchase->items as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? '-' }}</td>
                        <td>{{ $item->productUnit?->unit_name ?? '-' }}</td>
                        <td class="qty">{{ number_format((float) $item->quantity, 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format((float) $item->unit_cost, 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format((float) $item->line_total, 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Supplier Note</span>
                        <div class="detail-copy">
                            {{ $purchase->purchase_type === 'credit'
                                ? 'This document records stock received and the supplier balance still payable.'
                                : 'This document records stock received and confirms an immediate supplier payment flow.' }}
                            <br><br>Remarks: {{ $purchase->remarks ?: '-' }}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Totals</span>
                        <table class="summary-table">
                            <tr><td>Subtotal</td><td>{{ $currency }} {{ number_format((float) $purchase->subtotal, 0) }}</td></tr>
                            <tr><td>Paid</td><td>{{ $currency }} {{ number_format((float) $purchase->amount_paid, 0) }}</td></tr>
                            <tr><td>Balance</td><td>{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</td></tr>
                            <tr><td>Total</td><td>{{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">{{ config('business.statement_footer', 'This document is system-generated and intended for account reconciliation.') }}</div>

        <table class="signature-row">
            <tr>
                <td>Prepared By</td>
                <td>Received By</td>
            </tr>
        </table>
    </div>
</body>
</html>
