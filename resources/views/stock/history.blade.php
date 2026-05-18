@extends('layouts.app', ['title' => 'Stock History'])

@section('content')
    @php($transactionCollection = $transactions instanceof \Illuminate\Support\Collection ? $transactions : collect($transactions))
    @php($movementLabels = [
        'sale' => 'Sale',
        'purchase' => 'Purchase',
        'stock_transfer' => 'Transfer',
        'stock_adjustment' => 'Adjustment',
        'stock_count' => 'Physical Count',
    ])
    <div class="page-head">
        <div>
            <h2>{{ $productUnit->product?->name }} - {{ $productUnit->unit_name }}</h2>
            <p>Track how this stock item moved across sales, purchases, transfers, and adjustments, and compare that movement to the current system count when checking physical stock.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.history.export', [$productUnit, 'store_id' => $storeId]) }}" class="button-link">Export CSV</a>
            <a href="{{ route('stock.balances') }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Movements Shown</div><div class="value">{{ number_format($transactionCollection->count()) }}</div></div>
        <div class="card"><div class="label">Units In</div><div class="value">{{ number_format((float) $transactionCollection->sum('quantity_in'), 0) }}</div></div>
        <div class="card"><div class="label">Units Out</div><div class="value">{{ number_format((float) $transactionCollection->sum('quantity_out'), 0) }}</div></div>
        <div class="card"><div class="label">Store Filter</div><div class="value">{{ $stores->firstWhere('id', $storeId)?->name ?? 'All Stores' }}</div></div>
    </section>

    <section class="panel">
        <form method="get" class="filters">
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>

            <p class="list-note">This page is helpful when checking shortages, tracing stock movements, or explaining how a balance changed in a specific store.</p>
        <div class="table-wrap table-mobile-friendly">
        <table>
            <thead>
                <tr>
                    <th>Movement</th>
                    <th>Reference</th>
                    <th>Units</th>
                    <th>Remarks</th>
                    <th>Open</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($transactions as $transaction)
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title">{{ optional($transaction->transaction_date)->format('d M Y') ?: '-' }}</div>
                                <div class="table-meta">{{ $transaction->store?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                                <div class="status-inline">
                                    <span class="badge {{ in_array($transaction->movement_type, ['sale', 'transfer_out', 'adjustment_out', 'count_out'], true) ? 'credit' : 'success' }}">
                                        {{ \Illuminate\Support\Str::headline($transaction->movement_type) }}
                                    </span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="table-title">{{ $transaction->reference_no ?? '-' }}</div>
                            <div class="table-meta">{{ $movementLabels[$transaction->reference_type] ?? 'Stock entry' }}</div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div class="table-meta">In: {{ number_format((float) $transaction->quantity_in, 0) }}</div>
                                <div class="table-meta">Out: {{ number_format((float) $transaction->quantity_out, 0) }}</div>
                            </div>
                        </td>
                        <td>{{ $transaction->remarks ?? '-' }}</td>
                        <td>
                            @if ($transaction->reference_type === 'stock_transfer' && $access->can('stock.view'))
                                <div class="action-stack">
                                    <a href="{{ route('stock.transfers.show', $transaction->reference_no) }}" class="action-chip">Open Transfer</a>
                                </div>
                            @elseif ($transaction->reference_type === 'stock_adjustment' && $access->can('stock.view'))
                                <div class="action-stack">
                                    <a href="{{ route('stock.adjustments.show', $transaction->reference_no) }}" class="action-chip">Open Adjustment</a>
                                </div>
                            @elseif ($transaction->reference_type === 'stock_count' && $access->can('stock.view'))
                                <div class="action-stack">
                                    <a href="{{ route('stock.counts.show', $transaction->reference_no) }}" class="action-chip">Open Count</a>
                                </div>
                            @elseif ($transaction->reference_type === 'sale' && $access->can('sales.view'))
                                <div class="action-stack">
                                    <a href="{{ route('sales.index', ['q' => $transaction->reference_no]) }}" class="action-chip">Open Sale</a>
                                </div>
                            @elseif ($transaction->reference_type === 'purchase' && $access->can('purchases.view'))
                                <div class="action-stack">
                                    <a href="{{ route('purchases.index', ['q' => $transaction->reference_no]) }}" class="action-chip">Open Purchase</a>
                                </div>
                            @elseif ($transaction->reference_type === 'customer_payment' && $access->can('customer_payments.manage'))
                                <div class="action-stack">
                                    <a href="{{ route('customer-payments.index', ['q' => $transaction->reference_no]) }}" class="action-chip">Open Payment</a>
                                </div>
                            @elseif ($transaction->reference_type === 'supplier_payment' && $access->can('supplier_payments.manage'))
                                <div class="action-stack">
                                    <a href="{{ route('supplier-payments.index', ['q' => $transaction->reference_no]) }}" class="action-chip">Open Payment</a>
                                </div>
                            @elseif ($transaction->reference_type === 'stock_adjustment')
                                <span class="muted">Restricted</span>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No stock movements were found for this item.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
        <p class="list-note">Use this movement trail when a physical stock count does not match the system count and you need to trace where the difference may have come from.</p>
    </section>
@endsection
