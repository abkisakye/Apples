<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Return {{ $purchaseReturn->return_no }}</title>
    <style>
        @include('partials.print_document_styles')
    </style>
</head>
<body onload="window.print()">
    @php($currency = config('business.currency', 'UGX'))
    @php($printedAt = now())
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Supplier Return Note',
            'documentName' => $purchaseReturn->return_no,
            'documentMetaLines' => [
                'Date: '.optional($purchaseReturn->return_date)->format('d M Y'),
                'Linked Purchase: '.($purchaseReturn->purchase?->purchase_no ?? '-'),
                'Supplier: '.($purchaseReturn->supplier?->name ?? '-'),
                'Store: '.($purchaseReturn->store?->name ?? '-'),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="overview overview-4">
            <tr>
                <td>
                    <span class="section-kicker">Return Type</span>
                    <div class="metric-value">{{ ucwords(str_replace('_', ' ', $purchaseReturn->return_type)) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Returned Value</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchaseReturn->returned_total, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Refund Paid</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchaseReturn->refund_amount, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Supplier Credit</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $purchaseReturn->supplier_credit_amount, 0) }}</div>
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
                @foreach ($purchaseReturn->items as $item)
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
                        <span class="panel-title">Settlement Summary</span>
                        <table class="summary-table">
                            <tr><td>Linked Purchase</td><td>{{ $purchaseReturn->purchase?->purchase_no ?? '-' }}</td></tr>
                            <tr><td>Refund Mode</td><td>{{ $purchaseReturn->paymentMode?->name ?? 'Not used' }}</td></tr>
                            <tr><td>Refund Paid</td><td>{{ $currency }} {{ number_format((float) $purchaseReturn->refund_amount, 0) }}</td></tr>
                            <tr><td>Supplier Credit</td><td>{{ $currency }} {{ number_format((float) $purchaseReturn->supplier_credit_amount, 0) }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Remarks</span>
                        <div class="detail-copy">{{ $purchaseReturn->remarks ?: 'No additional remarks were recorded.' }}</div>
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
