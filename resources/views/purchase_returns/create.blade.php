@extends('layouts.app', ['title' => 'Purchase Return'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Supplier Return For {{ $purchase->purchase_no }}</h2>
            <p>Record returned goods properly so the stock movement, supplier exposure, and printable return trail stay clear.</p>
        </div>
        <div class="actions">
            <a href="{{ route('purchases.show', $purchase) }}" class="button-link">Back to Purchase</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Supplier</div><div class="value">{{ $purchase->supplier?->name ?? '-' }}</div></div>
        <div class="card"><div class="label">Purchase Total</div><div class="value money">{{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</div></div>
        <div class="card"><div class="label">Outstanding</div><div class="value money">{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</div></div>
    </section>

    <form method="post" action="{{ route('purchases.returns.store', $purchase) }}" class="entry-form">
        @csrf

        <section class="panel">
            <div class="form-grid">
                <label class="form-field">
                    <span>Return Date</span>
                    <input type="date" name="return_date" value="{{ old('return_date', now()->toDateString()) }}" required>
                </label>
                <label class="form-field">
                    <span>Return Type</span>
                    <select name="return_type" required>
                        <option value="refund" @selected(old('return_type') === 'refund')>Refund</option>
                        <option value="supplier_credit" @selected(old('return_type') === 'supplier_credit')>Supplier Credit</option>
                        <option value="exchange" @selected(old('return_type') === 'exchange')>Exchange</option>
                    </select>
                </label>
                <label class="form-field">
                    <span>Refund Payment Mode</span>
                    <select name="payment_mode_id">
                        <option value="">Not required</option>
                        @foreach ($paymentModes as $mode)
                            <option value="{{ $mode->id }}" @selected((string) old('payment_mode_id') === (string) $mode->id)>{{ $mode->name }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <label class="form-field" style="margin-top: 12px;">
                <span>Remarks</span>
                <textarea name="remarks" rows="3">{{ old('remarks') }}</textarea>
            </label>
        </section>

        <section class="panel">
            <h3>Returned Items</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Bought Qty</th>
                            <th>Returned Already</th>
                            <th>Available To Return</th>
                            <th>Unit Cost</th>
                            <th>Return Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($returnRows as $index => $row)
                            <tr>
                                <td>
                                    <div class="table-title">{{ $row['item']->product?->name ?? '-' }}</div>
                                    <div class="table-meta">{{ $row['item']->productUnit?->unit_name ?? '-' }}</div>
                                </td>
                                <td>{{ number_format((float) $row['item']->quantity, 0) }}</td>
                                <td>{{ number_format((float) $row['already_returned'], 0) }}</td>
                                <td>{{ number_format((float) $row['available_qty'], 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $row['item']->unit_cost, 0) }}</td>
                                <td>
                                    <input type="hidden" name="items[{{ $index }}][purchase_item_id]" value="{{ $row['item']->id }}">
                                    <input type="number" step="1" min="0" max="{{ $row['available_qty'] }}" name="items[{{ $index }}][quantity]" value="{{ old('items.'.$index.'.quantity', 0) }}">
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="actions">
            <button type="submit">Post Supplier Return</button>
        </div>
    </form>
@endsection
