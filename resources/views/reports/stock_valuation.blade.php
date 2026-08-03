@extends('layouts.app', ['title' => 'Stock Valuation'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Stock Valuation</h2>
            <p>Estimated current stock value by product, store, base stock, and cost source.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.price-margins') }}" class="button-link">Cost vs Selling Price</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <p class="list-note"><strong>Estimated report.</strong> These reports are estimated where cost prices are missing or not recorded from purchases. Please review missing cost prices and conversion factors for accurate profit reports.</p>
        <form method="get" class="filters" style="margin-top:12px;">
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected($storeId === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search product, code, category, or unit">
            <select name="cost_source">
                <option value="all" @selected($costSource === 'all')>All cost sources</option>
                <option value="missing" @selected($costSource === 'missing')>Missing cost</option>
                <option value="has" @selected($costSource === 'has')>Has cost</option>
            </select>
            <label class="button-link" style="display:inline-flex; align-items:center; gap:8px;">
                <input type="checkbox" name="include_zero_stock" value="1" @checked($includeZeroStock)>
                Include zero stock
            </label>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Estimated Stock Value</div><div class="value money">{{ $currency }} {{ number_format($summary['total_estimated_stock_value'], 0) }}</div></div>
        <div class="card"><div class="label">Products With Stock</div><div class="value">{{ number_format($summary['products_with_stock']) }}</div></div>
        <div class="card"><div class="label">Missing Cost Price</div><div class="value">{{ number_format($summary['products_missing_cost']) }}</div></div>
        <div class="card"><div class="label">Zero Stock</div><div class="value">{{ number_format($summary['products_with_zero_stock']) }}</div></div>
        <div class="card"><div class="label">Conversion Reviews</div><div class="value">{{ number_format($summary['conversion_review_count']) }}</div></div>
    </section>

    <section class="panel">
        <h3>Stock Value Details</h3>
        <div style="overflow:auto; margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Store</th>
                        <th>Current Base Stock</th>
                        <th>Base Unit</th>
                        <th>Estimated Cost / Base Unit</th>
                        <th>Estimated Stock Value</th>
                        <th>Cost Source Used</th>
                        <th>Warning / Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->product->name }}</strong>
                                <div class="muted">{{ $row->product->code ?: 'No code' }}</div>
                            </td>
                            <td>{{ $row->product->category?->name ?? 'Uncategorised' }}</td>
                            <td>{{ $row->store_name }}</td>
                            <td>{{ $row->base_stock_label }}</td>
                            <td><span class="badge soft">{{ $row->base_unit_label }}</span></td>
                            <td>{{ $currency }} {{ number_format($row->estimated_cost_per_base_unit, 2) }}</td>
                            <td><strong>{{ $currency }} {{ number_format($row->estimated_stock_value, 0) }}</strong></td>
                            <td>{{ $row->cost_source }}</td>
                            <td>
                                <span class="badge {{ $row->warning_label === 'OK' ? 'success' : 'credit' }}">{{ $row->warning_label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">No stock valuation rows matched the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('partials.developer_credit')
@endsection
