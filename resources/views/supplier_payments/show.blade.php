@extends('layouts.app', ['title' => 'Supplier Payment Details'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Supplier Payment {{ $payment->payment_no }}</h2>
            <p>Use this page to confirm the supplier payment, the related purchase, and the remaining supplier balance after posting.</p>
        </div>
        <div class="actions">
            <a href="{{ route('supplier-payments.print', $payment) }}" target="_blank" class="button-link">Full Document</a>
            <a href="{{ route('supplier-payments.print', ['supplierPayment' => $payment, 'theme' => 'thermal']) }}" target="_blank" class="button-link primary">Print Payment Receipt</a>
            @if ($payment->purchase)
                <a href="{{ route('purchases.show', $payment->purchase) }}" class="button-link">Open Purchase</a>
            @endif
            <a href="{{ route('supplier-payments.index') }}" class="button-link">Back to Payments</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Supplier</div><div class="value">{{ $payment->supplier?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Amount</div><div class="value money">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $payment->store?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Remaining Balance</div><div class="value money">{{ $currency }} {{ number_format((float) ($payment->purchase?->balance_due ?? 0), 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Payment Details</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Payment No</th><td>{{ $payment->payment_no }}</td></tr>
                    <tr><th style="text-align:left;">Payment Date</th><td>{{ optional($payment->payment_date)->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">Supplier</th><td>{{ $payment->supplier?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Phone</th><td>{{ $payment->supplier?->phone ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Country</th><td>{{ $payment->supplier?->country ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Payment Mode</th><td>{{ $payment->paymentMode?->name ?? 'Not set' }}</td></tr>
                    <tr><th style="text-align:left;">Reference</th><td>{{ $payment->reference_no ?: $payment->supplier_invoice_no ?: $payment->cheque_number ?: '-' }}</td></tr>
                    <tr><th style="text-align:left;">Remarks</th><td>{{ $payment->remarks ?: '-' }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Applied Purchase</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Purchase No</th><td>{{ $payment->purchase?->purchase_no ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Purchase Date</th><td>{{ optional($payment->purchase?->purchase_date)->format('d M Y') ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Purchase Type</th><td>{{ $payment->purchase ? ucfirst($payment->purchase->purchase_type) : '-' }}</td></tr>
                    <tr><th style="text-align:left;">Purchase Total</th><td>{{ $currency }} {{ number_format((float) ($payment->purchase?->total_amount ?? 0), 0) }}</td></tr>
                    <tr><th style="text-align:left;">Balance After Payment</th><td>{{ $currency }} {{ number_format((float) ($payment->purchase?->balance_due ?? 0), 0) }}</td></tr>
                </tbody>
            </table>
            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('supplier-payments.print', ['supplierPayment' => $payment, 'theme' => 'thermal']) }}" target="_blank" class="button-link primary">Print Thermal Receipt</a>
            </div>
        </div>
    </section>

@endsection
