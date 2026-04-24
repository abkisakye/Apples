@extends('layouts.app', ['title' => 'Sales'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($salesCollection = $sales->getCollection())
    @php($cashSales = $salesCollection->where('sale_type', 'cash'))
    @php($creditSales = $salesCollection->where('sale_type', 'credit'))
    <style>
        .desk-cards {
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 8px;
            margin-bottom: 10px;
        }
        .desk-cards .card {
            padding: 10px;
        }
        .desk-cards .card .value {
            font-size: 1.08rem;
        }
        .desk-filters {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) 160px auto;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .desk-filters input,
        .desk-filters select {
            min-width: 0;
        }
        .desk-panel {
            padding: 10px;
        }
        .desk-panel table {
            min-width: 0;
        }
        .desk-panel th,
        .desk-panel td {
            padding: 7px 6px;
        }
        .desk-panel .table-title,
        .desk-panel .table-title a {
            font-size: .86rem;
        }
        .desk-panel .table-meta {
            font-size: .78rem;
        }
        @media (max-width: 900px) {
            .desk-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Sales</h2>
            <p>Review recent sales, spot unpaid balances quickly, and open the full sale record when you need to print or follow up.</p>
        </div>
        <div class="actions">
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create') }}" class="button-link">Record Customer Payment</a>
            @endif
            @if ($access->can('sales.manage'))
                <a href="{{ route('sales.create') }}" class="button-link primary">Record Sale</a>
            @endif
        </div>
    </div>

    <section class="cards desk-cards">
        <div class="card"><div class="label">On This Page</div><div class="value">{{ number_format($salesCollection->count()) }}</div></div>
        <div class="card"><div class="label">Cash Value</div><div class="value money">{{ $currency }} {{ number_format((float) $cashSales->sum('total_amount'), 0) }}</div></div>
        <div class="card"><div class="label">Credit Value</div><div class="value money">{{ $currency }} {{ number_format((float) $creditSales->sum('total_amount'), 0) }}</div></div>
        <div class="card"><div class="label">Outstanding</div><div class="value money">{{ $currency }} {{ number_format((float) $salesCollection->sum('balance_due'), 0) }}</div></div>
    </section>

    <form class="filters desk-filters" method="get">
        <input type="text" name="q" value="{{ $search }}" placeholder="Search sale number or customer">
        <select name="type">
            <option value="">All types</option>
            <option value="cash" @selected($type === 'cash')>Cash</option>
            <option value="credit" @selected($type === 'credit')>Credit</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <div class="panel desk-panel">
        <p class="list-note">Tip: use this page as a cashier queue. Open a sale to reprint the receipt, or jump straight to payment when a customer is settling a credit balance.</p>
        <div class="table-wrap table-mobile-friendly">
        <table>
            <thead>
                <tr>
                    <th>Sale</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Totals</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($sales as $sale)
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title"><a href="{{ route('sales.show', $sale) }}">{{ $sale->sale_no }}</a></div>
                                <div class="table-meta">{{ optional($sale->sale_date)->format('d M Y') ?: '-' }}</div>
                                <div class="table-meta">{{ $sale->store?->name ?? 'No store assigned' }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title">{{ $sale->customer?->name ?? 'Walk-in customer' }}</div>
                                <div class="status-inline">
                                    @if ($sale->customer?->is_walk_in)
                                        <span class="badge">Walk-in</span>
                                    @endif
                                    @if ($sale->balance_due > 0)
                                        <span class="badge credit">Balance due</span>
                                    @else
                                        <span class="badge success">Cleared</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="status-inline">
                                <span class="badge {{ $sale->sale_type === 'credit' ? 'credit' : '' }}">{{ ucfirst($sale->sale_type) }}</span>
                                <span class="badge {{ $sale->status === 'posted' ? 'success' : 'credit' }}">{{ ucfirst($sale->status) }}</span>
                            </div>
                        </td>
                        <td class="money">
                            <div class="cell-stack">
                                <div>Total: {{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</div>
                                <div class="table-meta">Balance: {{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</div>
                                @if ($sale->credit_due_date)
                                    <div class="table-meta">Due {{ $sale->credit_due_date->format('d M Y') }}</div>
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
                                    <a href="{{ route('sales.show', $sale) }}" class="row-action-link">
                                        <span>Open Sale</span>
                                        <span class="meta">View</span>
                                    </a>
                                    <a href="{{ route('sales.print', $sale) }}" target="_blank" class="row-action-link">
                                        <span>Print Receipt</span>
                                        <span class="meta">Print</span>
                                    </a>
                                    @if ($access->can('customer_payments.manage') && $sale->balance_due > 0 && $sale->status === 'posted')
                                        <a href="{{ route('customer-payments.create', ['customer_id' => $sale->customer_id]) }}" class="row-action-link primary">
                                            <span>Record Payment</span>
                                            <span class="meta">Pay</span>
                                        </a>
                                        <a href="{{ route('customers.statement', $sale->customer_id) }}" class="row-action-link">
                                            <span>Customer Statement</span>
                                            <span class="meta">Stmt</span>
                                        </a>
                                    @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No sales match this view yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <div class="pagination">{{ $sales->links() }}</div>
    </div>
@endsection
