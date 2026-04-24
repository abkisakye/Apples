<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sale Return {{ $saleReturn->return_no }}</title>
    <style>
        @include('partials.print_document_styles')
    </style>
</head>
<body onload="window.print()">
    @php($currency = config('business.currency', 'UGX'))
    @php($printedAt = now())
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Sale Return Note',
            'documentName' => $saleReturn->return_no,
            'documentMetaLines' => [
                'Date: '.optional($saleReturn->return_date)->format('d M Y'),
                'Linked Sale: '.($saleReturn->sale?->sale_no ?? '-'),
                'Customer: '.($saleReturn->customer?->name ?? 'Walk-in customer'),
                'Store: '.($saleReturn->store?->name ?? '-'),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="overview overview-4">
            <tr>
                <td>
                    <span class="section-kicker">Return Type</span>
                    <div class="metric-value">{{ ucwords(str_replace('_', ' ', $saleReturn->return_type)) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Returned Value</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $saleReturn->returned_total, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Refund Paid</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $saleReturn->refund_amount, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Store Credit</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $saleReturn->store_credit_amount, 0) }}</div>
                </td>
            </tr>
        </table>

        <table class="ledger">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Unit</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($saleReturn->items as $item)
                    <tr>
                        <td>{{ $item->product?->name ?? '-' }}</td>
                        <td>{{ $item->productUnit?->unit_name ?? '-' }}</td>
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
                        <span class="panel-title">Settlement Outcome</span>
                        <div class="detail-copy">{{ $settlement['next_step'] }}</div>
                        <table class="summary-table" style="margin-top: 10px;">
                            <tr><td>Outstanding Reduced</td><td>{{ $currency }} {{ number_format((float) $settlement['balance_reduction'], 0) }}</td></tr>
                            <tr><td>Sale Balance After</td><td>{{ $currency }} {{ number_format((float) ($saleReturn->sale?->balance_due ?? 0), 0) }}</td></tr>
                            <tr><td>Refund Mode</td><td>{{ $saleReturn->paymentMode?->name ?? 'Not used' }}</td></tr>
                            <tr><td>Replacement Sale</td><td>{{ $saleReturn->replacementSale?->sale_no ?? 'Not created yet' }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Remarks</span>
                        <div class="detail-copy">{{ $saleReturn->remarks ?: 'No additional remarks were recorded.' }}</div>
                    </div>
                </td>
            </tr>
        </table>

        <div class="footer-note">{{ config('business.statement_footer', 'This document is system-generated and intended for account reconciliation.') }}</div>

        <table class="signature-row">
            <tr>
                <td>Prepared By</td>
                <td>Approved By</td>
            </tr>
        </table>
    </div>
</body>
</html>
