@php($currency = config('business.currency', 'UGX'))
@php($salesCollection = $sales->getCollection())
@php($cashSales = $salesCollection->where('sale_type', 'cash'))
@php($creditSales = $salesCollection->where('sale_type', 'credit'))

<section class="cards desk-cards">
    <div class="card"><div class="label">{{ $type === 'cash' ? 'Cash Sales' : ($type === 'credit' ? 'Credit Sales' : 'Sales On Page') }}</div><div class="value">{{ number_format($salesCollection->count()) }}</div></div>
    <div class="card"><div class="label">Cash Value</div><div class="value money">{{ $currency }} {{ number_format((float) $cashSales->sum('total_amount'), 0) }}</div></div>
    <div class="card"><div class="label">Credit Value</div><div class="value money">{{ $currency }} {{ number_format((float) $creditSales->sum('total_amount'), 0) }}</div></div>
    <div class="card"><div class="label">Outstanding</div><div class="value money">{{ $currency }} {{ number_format((float) $salesCollection->sum('balance_due'), 0) }}</div></div>
</section>

<div class="panel desk-panel">
    <p class="list-note">This list shows sales receipts and invoices only. Stock purchases are managed from the Purchases page.</p>
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
                            <div class="table-meta">{{ $sale->store?->name ?? config('business.name', 'Apples Of Gold') }}</div>
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
