@extends('layouts.app', ['title' => 'Product Stock History'])

@section('content')
    @php($movementLabels = [
        'sale' => 'Sale',
        'sale_return' => 'Sale Return',
        'purchase' => 'Purchase',
        'purchase_return' => 'Purchase Return',
        'stock_transfer' => 'Transfer',
        'stock_adjustment' => 'Adjustment',
        'stock_count' => 'Physical Count',
        'opening' => 'Opening Stock',
    ])

    <div class="page-head">
        <div>
            <h2>{{ $product->name }}</h2>
            <p><strong>Product Stock History</strong> &mdash; all movements for this product across every selling and buying unit.</p>
        </div>
        <div class="actions">
            <a href="{{ route('stock.balances', request()->only('store_id')) }}" class="button-link">Back to Stock</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Movements Shown</div><div class="value">{{ number_format($historyRows->count()) }}</div></div>
        <div class="card"><div class="label">Current Base Stock</div><div class="value" aria-label="Current Base Stock {{ $summary->base_stock_label }}">Current Base Stock {{ $summary->base_stock_label }}</div></div>
        <div class="card"><div class="label">Base Unit</div><div class="value">{{ $summary->base_unit_label }}</div></div>
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

        <p class="list-note">Breakdown: {{ $summary->friendly_breakdown }}. Selected quantity keeps the transaction unit from the document; base impact shows how stock control changed.</p>

        <div class="table-wrap table-mobile-friendly">
            <table>
                <thead>
                    <tr>
                        <th>Movement</th>
                        <th>Reference</th>
                        <th>Selected Quantity</th>
                        <th>Base Impact</th>
                        <th>Running Balance</th>
                        <th>User / Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($historyRows as $row)
                        @php($transaction = $row->transaction)
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ optional($transaction->transaction_date)->format('d M Y') ?: '-' }}</div>
                                    <div class="table-meta">{{ $transaction->store?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                                    <div class="status-inline">
                                        <span class="badge {{ $row->base_impact < 0 ? 'credit' : 'success' }}">
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
                                <div class="table-title">{{ $row->selected_quantity_label }}</div>
                                <div class="table-meta">{{ $transaction->productUnit?->unit_name ?? 'Selected unit' }}</div>
                            </td>
                            <td>
                                <span class="badge {{ $row->base_impact < 0 ? 'credit' : 'success' }}">Base {{ $row->base_impact_label }}</span>
                            </td>
                            <td>
                                <span class="badge soft">Balance {{ $row->running_balance_label }}</span>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <div>{{ $transaction->createdBy?->name ?? '-' }}</div>
                                    <div class="table-meta">{{ $transaction->remarks ?? '-' }}</div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="muted">No stock movements were found for this product.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
