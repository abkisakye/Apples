@extends('layouts.app', ['title' => 'Reorder List'])

@section('content')
    <div class="page-head">
        <div>
            <h2>Reorder List</h2>
            <p>Products at or below their reorder level based on the current system stock count.</p>
        </div>
        <div class="actions">
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create') }}" class="button-link primary">Record Purchase</a>
            @endif
            @if ($access->can('stock.manage'))
                <a href="{{ route('stock.counts.create', request()->only('store_id', 'category_id', 'q')) }}" class="button-link">Physical Count</a>
                <a href="{{ route('stock.adjustments.create') }}" class="button-link">Stock Adjustment</a>
            @endif
            <a href="{{ route('stock.counts.index') }}" class="button-link">Count Log</a>
            <a href="{{ route('stock.reorder.export', request()->only('store_id', 'category_id', 'q')) }}" class="button-link">Export CSV</a>
            <a href="{{ route('stock.balances', request()->only('store_id', 'category_id', 'q')) }}" class="button-link">Back to Stock Balance</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Items To Reorder</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Total Units Short</div><div class="value">{{ number_format($rows->sum(fn ($row) => max((float) $row->reorder_level - (float) $row->balance_qty, 0)), 0) }}</div></div>
        <div class="card"><div class="label">Zero / Negative</div><div class="value">{{ number_format($rows->filter(fn ($row) => (float) $row->balance_qty <= 0)->count()) }}</div></div>
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
                    <th>Reorder Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
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
                                    <span class="badge credit">Short {{ number_format(max((float) $row->reorder_level - (float) $row->balance_qty, 0), 0) }}</span>
                                    <span class="badge soft">System Count {{ number_format((float) $row->balance_qty, 0) }}</span>
                                </div>
                                <div class="table-meta">Reorder at: {{ number_format((float) $row->reorder_level, 0) }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="action-stack">
                                <a href="{{ route('stock.history', $row->id) }}" class="action-chip">History</a>
                                @if ($access->can('purchases.manage'))
                                    <a href="{{ route('purchases.create') }}" class="action-chip primary">Reorder</a>
                                @endif
                                @if ($access->can('stock.manage'))
                                    <a href="{{ route('stock.counts.create', ['store_id' => $filters['store_id'], 'q' => $row->product_name]) }}" class="action-chip">Count</a>
                                    <a href="{{ route('stock.adjustments.create') }}" class="action-chip">Adjust</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted">No items are currently below reorder level.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endsection
