@extends('layouts.app', ['title' => 'Expense Details'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Expense {{ $expense->expense_no }}</h2>
            <p>Review the expense details, amount, payment mode, and reference before using this entry in reconciliations or reports.</p>
        </div>
        <div class="actions">
            <a href="{{ route('expenses.print', $expense) }}" target="_blank" class="button-link primary">Print</a>
            <a href="{{ route('expenses.index') }}" class="button-link">Back to Expenses</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Category</div><div class="value">{{ $expense->category }}</div></div>
        <div class="card"><div class="label">Amount</div><div class="value money">{{ $currency }} {{ number_format((float) $expense->amount, 0) }}</div></div>
        <div class="card"><div class="label">Mode</div><div class="value">{{ $expense->paymentMode?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Store</div><div class="value">{{ $expense->store?->name ?? '-' }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Expense Summary</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Expense Date</th><td>{{ optional($expense->expense_date)->format('d M Y') }}</td></tr>
                    <tr><th style="text-align:left;">Reference</th><td>{{ $expense->reference_no ?: '-' }}</td></tr>
                    <tr><th style="text-align:left;">Posted By</th><td>{{ $expense->creator?->name ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Status</th><td>{{ ucfirst($expense->status) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Notes</h3>
            <p class="list-note">{{ $expense->notes ?: 'No extra notes were recorded for this expense.' }}</p>
        </div>
    </section>
@endsection
