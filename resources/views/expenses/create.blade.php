@extends('layouts.app', ['title' => 'New Expense'])

@section('content')
    <div class="page-head">
        <div>
            <h2>New Expense</h2>
            <p>Use this form for supermarket running costs such as rent, transport, repairs, lunch, wages, or utility payments.</p>
        </div>
        <div class="actions">
            <a href="{{ route('expenses.index') }}" class="button-link">Back to Expenses</a>
        </div>
    </div>

    <section class="grid-two">
        <div class="panel">
            <form method="post" action="{{ route('expenses.store') }}" class="entry-form">
                @csrf
                <div class="form-grid">
                    <label class="form-field">
                        <span>Expense Date</span>
                        <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" required>
                    </label>
                    <label class="form-field">
                        <span>Store</span>
                        <select name="store_id">
                            <option value="">Apples Of Gold</option>
                            @foreach ($stores as $store)
                                <option value="{{ $store->id }}" @selected((string) old('store_id', $defaultStoreId) === (string) $store->id)>{{ $store->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Category</span>
                        <input type="text" name="category" value="{{ old('category') }}" placeholder="e.g. Transport, Rent, Repairs, Wages" required>
                    </label>
                    <label class="form-field">
                        <span>Amount</span>
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                    </label>
                    <label class="form-field">
                        <span>Payment Mode</span>
                        <select name="payment_mode_id">
                            <option value="">Choose mode</option>
                            @foreach ($paymentModes as $mode)
                                <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="form-field">
                        <span>Reference</span>
                        <input type="text" name="reference_no" value="{{ old('reference_no') }}" placeholder="Invoice, receipt, or note reference">
                    </label>
                </div>

                <label class="form-field">
                    <span>Notes</span>
                    <textarea name="notes" rows="4" placeholder="Explain what this expense was for">{{ old('notes') }}</textarea>
                </label>

                <div class="actions">
                    <button type="submit">Save Expense</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <h3>Good Practice</h3>
            <p class="list-note">Keep categories simple and consistent so the owner can understand expenses quickly in reports.</p>
            <table>
                <tbody>
                    <tr><th style="text-align:left;">Examples</th><td>Transport, Electricity, Rent, Lunch, Repairs, Packaging</td></tr>
                    <tr><th style="text-align:left;">Reference</th><td>Add any receipt number or note reference when available.</td></tr>
                    <tr><th style="text-align:left;">Cash effect</th><td>If this was paid in cash, it will reduce expected cash during shift closing.</td></tr>
                </tbody>
            </table>
        </div>
    </section>
@endsection
