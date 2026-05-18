@extends('layouts.app', ['title' => 'Purchases'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($purchaseCollection = $purchases->getCollection())
    <div class="page-head">
        <div>
            <h2>Purchases</h2>
            <p>Track goods coming in, see outstanding supplier balances, and print purchase documents from one page.</p>
        </div>
        <div class="actions">
            @if ($access->can('supplier_payments.manage'))
                <a href="{{ route('supplier-payments.create') }}" class="button-link">Record Supplier Payment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create') }}" class="button-link primary">Record Purchase</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">On This Page</div><div class="value">{{ number_format($purchaseCollection->count()) }}</div></div>
        <div class="card"><div class="label">Cash Purchases</div><div class="value">{{ number_format($purchaseCollection->where('purchase_type', 'cash')->count()) }}</div></div>
        <div class="card"><div class="label">Credit Purchases</div><div class="value">{{ number_format($purchaseCollection->where('purchase_type', 'credit')->count()) }}</div></div>
        <div class="card"><div class="label">Outstanding</div><div class="value money">{{ number_format($purchaseCollection->sum('balance_due'), 0) }}</div></div>
    </section>

    <section class="panel">
        <form method="get" class="filters">
            <input type="text" name="q" value="{{ $search }}" placeholder="Search purchase no or supplier">
            <select name="type">
                <option value="">All purchase types</option>
                <option value="cash" @selected($type === 'cash')>Cash purchases</option>
                <option value="credit" @selected($type === 'credit')>Credit purchases</option>
            </select>
            <button type="submit">Filter</button>
        </form>

        <div class="table-wrap table-mobile-friendly">
        <table>
            <thead>
                <tr>
                    <th>Purchase</th>
                    <th>Supplier</th>
                    <th>Type</th>
                    <th>Totals</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($purchases as $purchase)
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title"><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->purchase_no }}</a></div>
                                <div class="table-meta">{{ optional($purchase->purchase_date)->format('d M Y') ?: '-' }}</div>
                                <div class="table-meta">{{ $purchase->store?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title">{{ $purchase->supplier?->name ?? 'Supplier not set' }}</div>
                                <div class="status-inline">
                                    @if ($purchase->balance_due > 0)
                                        <span class="badge credit">Balance due</span>
                                    @else
                                        <span class="badge success">Cleared</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="status-inline">
                                <span class="badge {{ $purchase->purchase_type === 'credit' ? 'credit' : '' }}">{{ ucfirst($purchase->purchase_type) }}</span>
                                <span class="badge {{ $purchase->status === 'posted' ? 'success' : 'credit' }}">{{ ucfirst($purchase->status) }}</span>
                            </div>
                        </td>
                        <td class="money">
                            <div class="cell-stack">
                                <div>Total: {{ $currency }} {{ number_format((float) $purchase->total_amount, 0) }}</div>
                                <div class="table-meta">Balance: {{ $currency }} {{ number_format((float) $purchase->balance_due, 0) }}</div>
                                @if ($purchase->credit_due_date)
                                    <div class="table-meta">Due {{ $purchase->credit_due_date->format('d M Y') }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            <details class="row-actions-menu">
                                <summary class="row-actions-toggle">
                                    <span class="action-chip">
                                        <span>Actions</span>
                                        <span class="caret">&#9662;</span>
                                    </span>
                                </summary>
                                <div class="row-actions-dropdown">
                                    <a href="{{ route('purchases.show', $purchase) }}" class="row-action-link">
                                        <span>Open Purchase</span>
                                        <span class="meta">View</span>
                                    </a>
                                    <a href="{{ route('purchases.print', $purchase) }}" target="_blank" class="row-action-link">
                                        <span>Print Purchase</span>
                                        <span class="meta">Print</span>
                                    </a>
                                @if ($access->can('supplier_payments.manage') && $purchase->balance_due > 0 && $purchase->status === 'posted')
                                        <a href="{{ route('supplier-payments.create', ['supplier_id' => $purchase->supplier_id]) }}" class="row-action-link primary">
                                            <span>Record Payment</span>
                                            <span class="meta">Pay</span>
                                        </a>
                                        <a href="{{ route('suppliers.statement', $purchase->supplier_id) }}" class="row-action-link">
                                            <span>Supplier Statement</span>
                                            <span class="meta">Stmt</span>
                                        </a>
                                @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No purchases match this view yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>

        <div class="pagination">
            {{ $purchases->links() }}
        </div>
    </section>
@endsection
