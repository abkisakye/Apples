<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense {{ $expense->expense_no }}</title>
    <style>
        @include('partials.print_document_styles', ['pageWidth' => '760px'])
    </style>
</head>
<body onload="window.print()">
    @php($currency = config('business.currency', 'UGX'))
    @php($printedAt = now())
    <div class="page">
        @include('partials.print_document_header', [
            'documentLabel' => 'Expense Slip',
            'documentName' => $expense->expense_no,
            'documentMetaLines' => [
                'Date: '.optional($expense->expense_date)->format('d M Y'),
                'Category: '.$expense->categoryName(),
                'Store: '.($expense->store?->name ?? '-'),
                'Printed: '.$printedAt->format('d M Y H:i'),
            ],
        ])

        <table class="overview">
            <tr>
                <td>
                    <span class="section-kicker">Amount</span>
                    <div class="metric-value">{{ $currency }} {{ number_format((float) $expense->amount, 0) }}</div>
                </td>
                <td>
                    <span class="section-kicker">Payment Mode</span>
                    <div class="metric-value">{{ $expense->paymentMode?->name ?? '-' }}</div>
                </td>
                <td>
                    <span class="section-kicker">Reference</span>
                    <div class="note-copy">{{ $expense->reference_no ?: '-' }}</div>
                </td>
            </tr>
        </table>

        <table class="content">
            <tr>
                <td>
                    <div class="panel">
                        <span class="panel-title">Expense Summary</span>
                        <table class="summary-table">
                            <tr><td>Expense Number</td><td>{{ $expense->expense_no }}</td></tr>
                            <tr><td>Date</td><td>{{ optional($expense->expense_date)->format('d M Y') }}</td></tr>
                            <tr><td>Category</td><td>{{ $expense->categoryName() }}</td></tr>
                            <tr><td>Store</td><td>{{ $expense->store?->name ?? '-' }}</td></tr>
                            <tr><td>Payment Mode</td><td>{{ $expense->paymentMode?->name ?? '-' }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <span class="panel-title">Notes</span>
                        <div class="detail-copy">{{ $expense->notes ?: config('business.statement_footer', 'This document is system-generated and intended for internal business control.') }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
