@extends('layouts.app', ['title' => 'Customer Payment Details'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Customer Payment {{ $payment->payment_no }}</h2>
            <p>Simple payment confirmation for supermarket follow-up. Use this page to confirm who paid, which sale it was applied to, and what balance remains.</p>
        </div>
        <div class="actions">
            <a href="{{ route('customer-payments.print', $payment) }}" target="_blank" class="button-link">Full Document</a>
            <a href="{{ route('customer-payments.print', ['customerPayment' => $payment, 'theme' => 'thermal']) }}" target="_blank" class="button-link primary">Print Payment Receipt</a>
            @if ($payment->sale)
                <a href="{{ route('sales.show', $payment->sale) }}" class="button-link">Open Sale</a>
            @endif
            <a href="{{ route('customer-payments.index') }}" class="button-link">Back to Payments</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Customer</div><div class="value">{{ $payment->customer?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Amount</div><div class="value money">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $payment->store?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Remaining Balance</div><div class="value money">{{ $currency }} {{ number_format((float) ($payment->sale?->balance_due ?? 0), 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Payment Details</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Payment No</th><td>{{ $payment->payment_no }}</td></tr>
                    <tr><th style="text-align:left;">Payment Date</th><td>{{ optional($payment->payment_date)->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">Customer</th><td>{{ $payment->customer?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Phone</th><td>{{ $payment->customer?->phone ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Location</th><td>{{ $payment->customer?->location ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Payment Mode</th><td>{{ $payment->paymentMode?->name ?? 'Not set' }}</td></tr>
                    <tr><th style="text-align:left;">Reference</th><td>{{ $payment->reference_no ?: $payment->cheque_number ?: '-' }}</td></tr>
                    <tr><th style="text-align:left;">Remarks</th><td>{{ $payment->remarks ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Applied Sale</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Sale No</th><td>{{ $payment->sale?->sale_no ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Sale Date</th><td>{{ optional($payment->sale?->sale_date)->format('d M Y') ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Sale Type</th><td>{{ $payment->sale ? ucfirst($payment->sale->sale_type) : '-' }}</td></tr>
                    <tr><th style="text-align:left;">Sale Total</th><td>{{ $currency }} {{ number_format((float) ($payment->sale?->total_amount ?? 0), 0) }}</td></tr>
                    <tr><th style="text-align:left;">Balance After Payment</th><td>{{ $currency }} {{ number_format((float) ($payment->sale?->balance_due ?? 0), 0) }}</td></tr>
                </tbody>
            </table>
            @if ($payment->sale)
                <p class="list-note">This payment was posted against sale {{ $payment->sale->sale_no }} and reduced the customer balance immediately.</p>
            @endif
            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('customer-payments.print', ['customerPayment' => $payment, 'theme' => 'thermal']) }}" target="_blank" class="button-link primary">Print Thermal Receipt</a>
            </div>
        </div>
    </section>

    @if (session('auto_print_receipt'))
        <script>
            (() => {
                const printUrl = @json(route('customer-payments.print', ['customerPayment' => $payment, 'theme' => 'thermal']));
                const popup = window.open(printUrl, '_blank', 'noopener,noreferrer');
                if (popup) {
                    popup.focus();
                }
            })();
        </script>
    @endif
@endsection
