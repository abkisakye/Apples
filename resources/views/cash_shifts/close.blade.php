@extends('layouts.app', ['title' => 'Close Cash Shift'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Close {{ $cashShift->shift_no }}</h2>
            <p>Count the drawer cash and compare it with the expected amount generated from the shift activity.</p>
        </div>
        <div class="actions">
            <a href="{{ route('cash-shifts.show', $cashShift) }}" class="button-link">Back to Shift</a>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            <h3>Expected Cash</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:40%;">Opening Cash</th><td>{{ $currency }} {{ number_format((float) $cashShift->opening_balance, 0) }}</td></tr>
                    <tr><th style="text-align:left;">Cash Sales</th><td>{{ $currency }} {{ number_format((float) $summary['cash_sales_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Customer Cash Payments</th><td>{{ $currency }} {{ number_format((float) $summary['cash_customer_payments_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Cash Expenses</th><td>- {{ $currency }} {{ number_format((float) $summary['cash_expenses_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Expected Cash</th><td><strong>{{ $currency }} {{ number_format((float) $summary['expected_cash'], 0) }}</strong></td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <form method="post" action="{{ route('cash-shifts.close', $cashShift) }}" class="entry-form">
                @csrf
                <label class="form-field">
                    <span>Counted Cash</span>
                    <input type="number" step="0.01" min="0" name="counted_cash" value="{{ old('counted_cash') }}" required>
                </label>
                <label class="form-field">
                    <span>Closing Notes</span>
                    <textarea name="closing_notes" rows="4" placeholder="Explain any shortage, overage, or special issue">{{ old('closing_notes') }}</textarea>
                </label>
                <div class="actions">
                    <button type="submit">Close Shift</button>
                </div>
            </form>
        </div>
    </section>
@endsection
