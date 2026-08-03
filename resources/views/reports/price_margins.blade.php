@extends('layouts.app', ['title' => 'Cost vs Selling Price'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Cost vs Selling Price</h2>
            <p>Review configured pack costs, selling prices, margin, and wholesale conversion warnings.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.stock-valuation') }}" class="button-link">Stock Valuation</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <p class="list-note"><strong>Estimated report.</strong> These reports are estimated where cost prices are missing or not recorded from purchases. Please review missing cost prices and conversion factors for accurate profit reports.</p>
        <form method="get" class="filters" style="margin-top:12px;">
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $search }}" placeholder="Search product, code, category, or unit">
            <select name="status">
                <option value="all" @selected($status === 'all')>All statuses</option>
                <option value="missing_cost" @selected($status === 'missing_cost')>Missing cost</option>
                <option value="zero_selling_price" @selected($status === 'zero_selling_price')>Zero selling price</option>
                <option value="selling_below_cost" @selected($status === 'selling_below_cost')>Selling below cost</option>
                <option value="healthy_margin" @selected($status === 'healthy_margin')>Healthy margin</option>
                <option value="conversion_review" @selected($status === 'conversion_review')>Possible Pack Conversion Review</option>
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Product Units</div><div class="value">{{ number_format($summary['total_product_units']) }}</div></div>
        <div class="card"><div class="label">Missing Cost Price</div><div class="value">{{ number_format($summary['missing_cost']) }}</div></div>
        <div class="card"><div class="label">Zero Selling Price</div><div class="value">{{ number_format($summary['zero_selling_price']) }}</div></div>
        <div class="card"><div class="label">Selling Below Cost</div><div class="value">{{ number_format($summary['selling_below_cost']) }}</div></div>
        <div class="card"><div class="label">Healthy Margin</div><div class="value">{{ number_format($summary['healthy_margin']) }}</div></div>
        <div class="card"><div class="label">Conversion Reviews</div><div class="value">{{ number_format($summary['conversion_review_count']) }}</div></div>
    </section>

    <section class="panel">
        <h3>Product Unit Margin Details</h3>
        <div style="overflow:auto; margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Unit / Pack</th>
                        <th>Conversion Factor</th>
                        <th>Cost Price</th>
                        <th>Selling Price</th>
                        <th>Margin Amount</th>
                        <th>Margin %</th>
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
                            <td>{{ $row->unit->unit_name }}</td>
                            <td>{{ rtrim(rtrim(number_format($row->conversion_factor, 3, '.', ''), '0'), '.') }}</td>
                            <td>{{ $currency }} {{ number_format($row->cost_price, 0) }}</td>
                            <td>{{ $currency }} {{ number_format($row->selling_price, 0) }}</td>
                            <td>{{ $row->margin_amount === null ? 'N/A' : $currency.' '.number_format($row->margin_amount, 0) }}</td>
                            <td>{{ $row->margin_percent === null ? 'N/A' : number_format($row->margin_percent, 2).'%' }}</td>
                            <td>
                                <span class="badge {{ $row->status_key === 'healthy_margin' && ! $row->conversion_review ? 'success' : 'credit' }}">{{ $row->warning_label }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="muted">No product unit margins matched the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('partials.developer_credit')
@endsection
