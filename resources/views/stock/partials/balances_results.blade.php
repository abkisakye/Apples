@php($currency = config('business.currency', 'UGX'))
@php($rowCollection = collect(method_exists($rows, 'items') ? $rows->items() : $rows))
@php($lowItems = $rowCollection->filter(fn ($row) => (float) $row->reorder_level > 0 && (float) $row->base_balance <= (float) $row->reorder_level))

<section class="cards">
    <div class="card"><div class="label">Items Shown</div><div class="value">{{ number_format($rowCollection->count()) }}</div></div>
    <div class="card"><div class="label">Low Stock</div><div class="value">{{ number_format($lowItems->count()) }}</div></div>
    <div class="card"><div class="label">Shown Stock Value</div><div class="value money">{{ number_format($rowCollection->sum('stock_value'), 0) }}</div></div>
    <div class="card"><div class="label">Negative / Zero</div><div class="value">{{ number_format($rowCollection->filter(fn ($row) => (float) $row->base_balance <= 0)->count()) }}</div></div>
</section>

<section class="panel">
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
            @forelse ($rowCollection as $row)
                <tr>
                    <td>
                        <div class="stock-unit-title">
                            <a href="{{ route('stock.product-history', $row->product_id) }}" class="stock-product-link">{{ $row->product_name }}</a>
                            <div class="stock-unit-chip">Base unit: {{ $row->base_unit_label }}</div>
                        </div>
                        <div class="table-meta">{{ $row->product_code ?: 'No code' }}</div>
                        <div class="table-meta">Category: {{ $row->category_name ?? 'Uncategorized' }}</div>
                        <div class="table-meta">Units: {{ $row->configured_units ?: 'No active units' }}</div>
                    </td>
                    <td>
                        <div class="cell-stack">
                            <div class="status-inline">
                                <span class="badge {{ (float) $row->base_balance <= (float) $row->reorder_level && (float) $row->reorder_level > 0 ? 'credit' : 'success' }}">
                                    Base Stock {{ $row->base_stock_label }}
                                </span>
                                <span class="badge soft">Reorder At {{ $row->reorder_level_label }}</span>
                            </div>
                            <div class="table-meta">Breakdown: {{ $row->friendly_breakdown }}</div>
                        </div>
                    </td>
                    <td class="money">
                        <div class="cell-stack">
                            <div>{{ $currency }} {{ number_format((float) $row->stock_value, 0) }}</div>
                            <div class="table-meta">{{ (float) $row->base_balance <= 0 ? 'Check this product soon.' : 'Current base stock value' }}</div>
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
                                <a href="{{ route('stock.product-history', ['product' => $row->product_id, 'store_id' => $filters['store_id']]) }}" class="row-action-link">
                                    <span>View History</span>
                                    <span class="meta">Hist</span>
                                </a>
                            @if ((float) $row->reorder_level > 0 && (float) $row->base_balance <= (float) $row->reorder_level)
                                    <a href="{{ route('stock.reorder', request()->only('store_id', 'category_id', 'q')) }}" class="row-action-link accent">
                                        <span>View Reorder Alert</span>
                                        <span class="meta">Low</span>
                                    </a>
                            @endif
                            @if ($access->can('stock.manage'))
                                    <a href="{{ route('stock.counts.create', ['store_id' => $filters['store_id'], 'product_id' => $row->product_id, 'q' => $row->product_name]) }}" class="row-action-link accent">
                                        <span>Physical Count</span>
                                        <span class="meta">Count</span>
                                    </a>
                                    <a href="{{ route('stock.transfers.create', ['product_id' => $row->product_id, 'return_to' => url()->full()]) }}" class="row-action-link">
                                        <span>Transfer Stock</span>
                                        <span class="meta">Move</span>
                                    </a>
                                    <a href="{{ route('stock.adjustments.create', ['product_id' => $row->product_id, 'return_to' => url()->full()]) }}" class="row-action-link">
                                        <span>Stock Adjustment</span>
                                        <span class="meta">Adj</span>
                                    </a>
                            @endif
                            @if ($access->can('purchases.manage'))
                                    <a href="{{ route('purchases.create', ['product_id' => $row->product_id, 'return_to' => url()->full()]) }}" class="row-action-link primary">
                                        <span>Record Purchase</span>
                                        <span class="meta">Buy</span>
                                    </a>
                            @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No stock balance rows match this view yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    @if (method_exists($rows, 'hasPages') && $rows->hasPages())
        <div class="pagination">{{ $rows->links() }}</div>
    @endif
    <p class="list-note">This page shows stock controlled in each product's base unit. Sales and purchases can still use cartons, sacks, dozens, pieces, or kg while the balance remains one product-level figure.</p>
</section>
