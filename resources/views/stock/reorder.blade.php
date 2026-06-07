@extends('layouts.app', ['title' => 'Reorder List'])

@section('content')
    <style>
        .stock-unit-title {
            display: grid;
            gap: 4px;
        }
        .stock-product-link {
            font-weight: 800;
        }
        .stock-unit-chip {
            display: inline-flex;
            align-items: center;
            width: fit-content;
            padding: 4px 8px;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: var(--panel-soft);
            color: var(--ink);
            font-size: .8rem;
            font-weight: 800;
        }
        .stock-unit-chip span {
            color: var(--muted);
            font-weight: 700;
            margin-right: 4px;
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Reorder List</h2>
            <p>Products at or below their base-unit reorder level based on the current product stock count.</p>
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
        <div class="card"><div class="label">Base Units Short</div><div class="value">{{ number_format($rows->sum('shortage'), 0) }}</div></div>
        <div class="card"><div class="label">Zero / Negative</div><div class="value">{{ number_format($rows->filter(fn ($row) => (float) $row->base_balance <= 0)->count()) }}</div></div>
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
                            <div class="stock-unit-title">
                                @if ($row->primary_unit_id)
                                    <a href="{{ route('stock.history', $row->primary_unit_id) }}" class="stock-product-link">{{ $row->product_name }}</a>
                                @else
                                    <span class="stock-product-link">{{ $row->product_name }}</span>
                                @endif
                                <div class="stock-unit-chip">Base unit: {{ $row->base_unit_label }}</div>
                            </div>
                            <div class="table-meta">{{ $row->product_code ?: 'No code' }}</div>
                            <div class="table-meta">Category: {{ $row->category_name ?? 'Uncategorized' }}</div>
                            <div class="table-meta">Units: {{ $row->configured_units ?: 'No active units' }}</div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div class="status-inline">
                                    <span class="badge credit">Shortage: {{ $row->shortage_label }}</span>
                                    <span class="badge soft">Base Stock {{ $row->base_stock_label }}</span>
                                </div>
                                <div class="table-meta">Reorder Level: {{ $row->reorder_level_label }}</div>
                                <div class="table-meta">Breakdown: {{ $row->friendly_breakdown }}</div>
                            </div>
                        </td>
                        <td>
                            <div class="action-stack">
                                @if ($row->primary_unit_id)
                                    <a href="{{ route('stock.history', $row->primary_unit_id) }}" class="action-chip">History</a>
                                @endif
                                @if ($access->can('purchases.manage'))
                                    <a href="{{ route('purchases.create', ['product_id' => $row->product_id, 'return_to' => url()->full()]) }}" class="action-chip primary">Reorder</a>
                                @endif
                                @if ($access->can('stock.manage'))
                                    <a href="{{ route('stock.counts.create', ['store_id' => $filters['store_id'], 'q' => $row->product_name]) }}" class="action-chip">Count</a>
                                    <a href="{{ route('stock.adjustments.create', ['product_id' => $row->product_id, 'return_to' => url()->full()]) }}" class="action-chip">Adjust</a>
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
