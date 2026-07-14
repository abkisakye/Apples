<section class="cards">
    <div class="card"><div class="label">Products</div><div class="value">{{ number_format($productSummary['total']) }}</div></div>
    <div class="card"><div class="label">With Code</div><div class="value">{{ number_format($productSummary['with_code']) }}</div></div>
    <div class="card"><div class="label">Linked Suppliers</div><div class="value">{{ number_format($productSummary['linked_suppliers']) }}</div></div>
    <div class="card"><div class="label">Reorder Setup</div><div class="value">{{ number_format($productSummary['reorder_ready']) }}</div></div>
</section>

<div class="panel">
    <p class="list-note">Open a product profile to review its selling units, recent movement, and buying history instead of stopping at the master list.</p>
    <div class="table-wrap table-mobile-friendly">
    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Category</th>
                <th>Supplier</th>
                <th>Setup</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td>
                        <div class="cell-stack">
                            <div class="table-title"><a href="{{ route('products.show', $product) }}">{{ $product->name }}</a></div>
                            <div class="table-meta">{{ $product->code ? 'Code: '.$product->code : 'No code assigned' }}</div>
                            <div class="table-meta">{{ $product->item_group ?? 'General item' }}</div>
                        </div>
                    </td>
                    <td>{{ $product->category?->name ?? 'Uncategorized' }}</td>
                    <td>{{ $product->supplier?->name ?? 'No default supplier' }}</td>
                    <td>
                        <div class="cell-stack">
                            <div class="table-meta">Units: {{ number_format($product->units_count) }}</div>
                            <div class="status-inline">
                                <span class="badge {{ $product->is_active ? 'success' : 'credit' }}">{{ $product->is_active ? 'Active' : 'Inactive' }}</span>
                                <span class="badge {{ (float) $product->reorder_level > 0 ? '' : 'credit' }}">
                                    Reorder {{ number_format((float) $product->reorder_level, 0) }}
                                </span>
                            </div>
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
                                <a href="{{ route('products.show', $product) }}" class="row-action-link">
                                    <span>Open Product</span>
                                    <span class="meta">View</span>
                                </a>
                            @if ($access->can('purchases.manage'))
                                    <a href="{{ route('purchases.create', ['product_id' => $product->id, 'return_to' => url()->full()]) }}" class="row-action-link primary">
                                        <span>Add Stock</span>
                                        <span class="meta">Buy</span>
                                    </a>
                                    <a href="{{ route('products.edit', $product) }}" class="row-action-link">
                                        <span>Edit Product</span>
                                        <span class="meta">Edit</span>
                                    </a>
                            @endif
                            @if ($access->can('stock.view'))
                                    <a href="{{ route('stock.balances', ['q' => $product->code ?: $product->name]) }}" class="row-action-link">
                                        <span>Check Stock</span>
                                        <span class="meta">Stock</span>
                                    </a>
                            @endif
                            @if ($access->can('stock.manage'))
                                    <a href="{{ route('stock.adjustments.create', ['product_id' => $product->id, 'return_to' => url()->full()]) }}" class="row-action-link">
                                        <span>Adjust Stock</span>
                                        <span class="meta">Adj</span>
                                    </a>
                            @endif
                            @if ($access->can('purchases.manage'))
                                    <form method="post" action="{{ route('products.status', $product) }}">
                                        @csrf
                                        <input type="hidden" name="is_active" value="{{ $product->is_active ? 0 : 1 }}">
                                        <button type="submit" class="row-action-link {{ $product->is_active ? 'accent' : 'good' }}">
                                            <span>{{ $product->is_active ? 'Archive Product' : 'Activate Product' }}</span>
                                            <span class="meta">{{ $product->is_active ? 'Off' : 'On' }}</span>
                                        </button>
                                    </form>
                            @endif
                            </div>
                        </details>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">No products match this view yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination">{{ $products->links() }}</div>
</div>
