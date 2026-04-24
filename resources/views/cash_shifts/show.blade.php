@extends('layouts.app', ['title' => 'Cash Shift Details'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Cash Shift {{ $cashShift->shift_no }}</h2>
            <p>This page acts like a cashier closing sheet, showing the expected cash position for the shift.</p>
        </div>
        <div class="actions">
            @if ($cashShift->status === 'open')
                <a href="{{ route('cash-shifts.close-form', $cashShift) }}" class="button-link primary">Close Shift</a>
            @endif
            <a href="{{ route('cash-shifts.index') }}" class="button-link">Back to Shifts</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Cash Sales</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['cash_sales_total'], 0) }}</div></div>
        <div class="card"><div class="label">Customer Cash Payments</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['cash_customer_payments_total'], 0) }}</div></div>
        <div class="card"><div class="label">Cash Expenses</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['cash_expenses_total'], 0) }}</div></div>
        <div class="card"><div class="label">Expected Cash</div><div class="value money">{{ $currency }} {{ number_format((float) ($cashShift->status === 'closed' ? $cashShift->expected_cash : $summary['expected_cash']), 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Shift Summary</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Cashier</th><td>{{ $cashShift->user?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Store</th><td>{{ $cashShift->store?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Opened</th><td>{{ optional($cashShift->opened_at)->format('d M Y H:i') }}</td></tr>
                    <tr><th style="text-align:left;">Closed</th><td>{{ $cashShift->closed_at?->format('d M Y H:i') ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Opening Cash</th><td>{{ $currency }} {{ number_format((float) $cashShift->opening_balance, 0) }}</td></tr>
                    <tr><th style="text-align:left;">Status</th><td>{{ ucfirst($cashShift->status) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Closing Position</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Expected Cash</th><td>{{ $currency }} {{ number_format((float) ($cashShift->status === 'closed' ? $cashShift->expected_cash : $summary['expected_cash']), 0) }}</td></tr>
                    <tr><th style="text-align:left;">Counted Cash</th><td>{{ $cashShift->counted_cash === null ? '-' : $currency.' '.number_format((float) $cashShift->counted_cash, 0) }}</td></tr>
                    <tr><th style="text-align:left;">Short / Over</th><td>{{ $cashShift->shortage_overage === null ? '-' : $currency.' '.number_format((float) $cashShift->shortage_overage, 0) }}</td></tr>
                </tbody>
            </table>
            @if ($cashShift->opening_notes || $cashShift->closing_notes)
                <div style="margin-top:14px;">
                    @if ($cashShift->opening_notes)
                        <p class="list-note"><strong>Opening note:</strong> {{ $cashShift->opening_notes }}</p>
                    @endif
                    @if ($cashShift->closing_notes)
                        <p class="list-note"><strong>Closing note:</strong> {{ $cashShift->closing_notes }}</p>
                    @endif
                </div>
            @endif
        </div>
    </section>
@endsection
