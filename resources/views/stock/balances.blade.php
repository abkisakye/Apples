@extends('layouts.app', ['title' => 'Stock Balance'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($lowItems = $rows->filter(fn ($row) => (float) $row->reorder_level > 0 && (float) $row->balance_qty <= (float) $row->reorder_level))
    <div class="page-head">
        <div>
            <h2>Stock Balance</h2>
            <p>See the current system count, what is low, and where staff should check movement history before making changes.</p>
        </div>
        <div class="actions">
            @if ($access->can('stock.manage'))
                <a href="{{ route('stock.counts.index') }}" class="button-link">Count Log</a>
                <a href="{{ route('stock.transfers.index') }}" class="button-link">Transfer Log</a>
                <a href="{{ route('stock.adjustments.index') }}" class="button-link">Adjustment Log</a>
                <a href="{{ route('stock.counts.create', request()->only('store_id', 'category_id', 'q')) }}" class="button-link primary">Physical Count</a>
                <a href="{{ route('stock.transfers.create') }}" class="button-link">Stock Transfer</a>
                <a href="{{ route('stock.adjustments.create') }}" class="button-link">Stock Adjustment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create') }}" class="button-link">Record Purchase</a>
            @endif
            <a href="{{ route('stock.balances.export', request()->only('store_id', 'category_id', 'q')) }}" class="button-link">Export CSV</a>
            <a href="{{ route('stock.reorder', request()->only('store_id', 'category_id', 'q')) }}" class="button-link primary">View Reorder List</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Items Shown</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Low Stock</div><div class="value">{{ number_format($lowItems->count()) }}</div></div>
        <div class="card"><div class="label">Total Stock Value</div><div class="value money">{{ number_format($rows->sum('stock_value'), 0) }}</div></div>
        <div class="card"><div class="label">Negative / Zero</div><div class="value">{{ number_format($rows->filter(fn ($row) => (float) $row->balance_qty <= 0)->count()) }}</div></div>
    </section>

    <section class="panel">
        <form method="get" class="filters">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search product, code, or unit">
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected($filters['store_id'] === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($filters['category_id'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <button type="submit">Filter</button>
        </form>

        <div class="table-wrap table-mobile-friendly">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>System Stock</th>
                    <th>Value</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>
                            <div class="table-title">
                                <a href="{{ route('stock.history', $row->id) }}">{{ $row->product_name }}</a>
                            </div>
                            <div class="table-meta">{{ $row->product_code ?: 'No code' }}</div>
                            <div class="table-meta">{{ $row->unit_name }} / {{ $row->category_name ?? 'Uncategorized' }}</div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div class="status-inline">
                                    <span class="badge {{ (float) $row->balance_qty <= (float) $row->reorder_level && (float) $row->reorder_level > 0 ? 'credit' : 'success' }}">
                                        System Count {{ number_format((float) $row->balance_qty, 0) }}
                                    </span>
                                    <span class="badge soft">Reorder At {{ number_format((float) $row->reorder_level, 0) }}</span>
                                </div>
                                <div class="table-meta">Received: {{ number_format((float) $row->quantity_in, 0) }} / Issued: {{ number_format((float) $row->quantity_out, 0) }}</div>
                            </div>
                        </td>
                        <td class="money">
                            <div class="cell-stack">
                                <div>{{ $currency }} {{ number_format((float) $row->stock_value, 0) }}</div>
                                <div class="table-meta">{{ (float) $row->balance_qty <= 0 ? 'Check this unit soon.' : 'Current stock value' }}</div>
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
                                    <a href="{{ route('stock.history', $row->id) }}" class="row-action-link">
                                        <span>Movement History</span>
                                        <span class="meta">Hist</span>
                                    </a>
                                @if ((float) $row->reorder_level > 0 && (float) $row->balance_qty <= (float) $row->reorder_level)
                                        <a href="{{ route('stock.reorder', request()->only('store_id', 'category_id', 'q')) }}" class="row-action-link accent">
                                            <span>View Reorder Alert</span>
                                            <span class="meta">Low</span>
                                        </a>
                                @endif
                                @if ($access->can('stock.manage'))
                                        <a href="{{ route('stock.counts.create', ['store_id' => $filters['store_id'], 'q' => $row->product_name]) }}" class="row-action-link accent">
                                            <span>Physical Count</span>
                                            <span class="meta">Count</span>
                                        </a>
                                        <a href="{{ route('stock.adjustments.create', ['product_unit_id' => $row->id, 'return_to' => url()->full()]) }}" class="row-action-link">
                                            <span>Stock Adjustment</span>
                                            <span class="meta">Adj</span>
                                        </a>
                                @endif
                                @if ($access->can('purchases.manage'))
                                        <a href="{{ route('purchases.create', ['product_unit_id' => $row->id, 'return_to' => url()->full()]) }}" class="row-action-link primary">
                                            <span>Add Stock</span>
                                            <span class="meta">Buy</span>
                                        </a>
                                @endif
                                </div>
                            </details>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        <p class="list-note">This page shows the system stock count. When someone does a physical stock count in the shop, compare that count with this figure and post only the difference through the physical count screen so the variance stays documented.</p>
    </section>
@endsection
