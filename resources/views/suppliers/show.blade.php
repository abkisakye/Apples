@extends('layouts.app', ['title' => 'Supplier Profile'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>{{ $supplier->name }}</h2>
            <p>Review supplier balance, recent purchases, payments, and statement activity from one profile page.</p>
        </div>
        <div class="actions">
            <a href="{{ route('suppliers.statement', $supplier) }}" class="button-link">Statement</a>
            @if ($access->can('supplier_payments.manage'))
                <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="button-link primary">Post Payment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('suppliers.edit', $supplier) }}" class="button-link">Edit Supplier</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Current Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['closing_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Opening Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['opening_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Purchases</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['total_purchases'], 0) }}</div></div>
        <div class="card"><div class="label">Returns / Credits</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['total_returns'], 0) }}</div></div>
        <div class="card"><div class="label">Payments</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['total_payments'], 0) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Supplier Details</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Phone</th><td>{{ $supplier->phone ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Email</th><td>{{ $supplier->email ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Address</th><td>{{ $supplier->address ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Country</th><td>{{ $supplier->country ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">TIN</th><td>{{ $supplier->tin ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Type</th><td>{{ $supplier->supplier_type ?? 'General supplier' }}</td></tr>
                    <tr><th style="text-align:left;">Payment Terms</th><td>{{ $supplier->payment_terms_days ? $supplier->payment_terms_days.' days' : '-' }}</td></tr>
                    <tr><th style="text-align:left;">Status</th><td><span class="badge {{ $supplier->is_active ? 'success' : 'credit' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Latest Statement Entries</h3>
            <p class="list-note">This gives accounts staff a quick view of what increased or reduced the supplier balance recently.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Reference</th>
                            <th>Debit</th>
                            <th>Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($entries as $entry)
                            <tr>
                                <td>{{ optional($entry['date'])->format('d M Y') }}</td>
                                <td>{{ $entry['type'] }}</td>
                                <td>{{ $entry['reference'] }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $entry['debit'], 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $entry['credit'], 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No statement lines have been recorded yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="grid-two" style="margin-top: 16px;">
        <div class="panel">
            <h3>Recent Purchases</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Store</th>
                            <th>Total</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPurchases as $purchase)
                            <tr>
                                <td>{{ optional($purchase->purchase_date)->format('d M Y') }}</td>
                                <td><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_no }}</a></td>
                                <td>{{ $purchase->store?->name ?? '-' }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No purchases have been posted for this supplier yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Recent Payments</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Purchase</th>
                            <th>Mode</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td>{{ optional($payment->payment_date)->format('d M Y') }}</td>
                                <td><a href="{{ route('supplier-payments.show', $payment) }}">{{ $payment->payment_no }}</a></td>
                                <td>{{ $payment->purchase?->purchase_no ?? '-' }}</td>
                                <td>{{ $payment->paymentMode?->name ?? '-' }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No supplier payments have been posted yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
