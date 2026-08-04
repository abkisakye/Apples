@extends('layouts.app', ['title' => 'Gross Margin Summary'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0')
    @php($formatMargin = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%')
    @include('reports.partials.owner_print_styles')

    <div class="page-head">
        <div>
            <h2>Gross Margin Summary</h2>
            <p>Consolidated sales summary with estimated costs, returns, gross profit, and margin.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button-link">Export CSV</a>
            <a href="{{ route('reports.gross-profit', request()->query()) }}" class="button-link">Back to Report</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <form method="get" class="filters">
            <input type="date" name="date_from" value="{{ $fromDate }}">
            <input type="date" name="date_to" value="{{ $toDate }}">
            <select name="period">
                <option value="">Custom</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
                <option value="month" @selected($period === 'month')>This month</option>
            </select>
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected((int) $filters['store_id'] === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search product, code, category, or unit">
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="owner-report">
        <div class="owner-report-head">
            <h2>APPLES OF GOLD WHOLESALERS</h2>
            <h3>Consolidated Sales Summary with Gross Margins</h3>
            <p class="owner-report-meta">Period: {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        <div style="overflow:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Sales Amount</th>
                        <th>Cost Amount</th>
                        <th>Returns / Adjustment</th>
                        <th>Gross Profit</th>
                        <th>Gross Profit %</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productRows as $row)
                        <tr>
                            <td>
                                <a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->product_name }}</strong></a>
                                <div class="muted">{{ $row->category_name }} | {{ $row->warning_label }}</div>
                            </td>
                            <td class="money">{{ $formatQty($row->quantity_sold) }}</td>
                            <td class="money">{{ $currency }} {{ number_format($row->sales_revenue, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format($row->net_estimated_cogs, 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format($row->returned_revenue, 0) }}</td>
                            <td class="money">{{ $row->has_reliable_margin ? $currency.' '.number_format($row->net_estimated_gross_profit, 0) : 'N/A' }}</td>
                            <td class="money">{{ $row->has_reliable_margin ? $formatMargin($row->net_margin_percent) : 'N/A' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No gross margin rows matched the selected filters.</td></tr>
                    @endforelse
                    <tr class="owner-total-row">
                        <td>Total</td>
                        <td></td>
                        <td class="money">{{ $currency }} {{ number_format($summary['sales_revenue'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($summary['net_estimated_cogs'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($summary['returned_revenue'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($summary['net_estimated_gross_profit'], 0) }}</td>
                        <td class="money">{{ $formatMargin($summary['net_margin_percent']) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="owner-grand-total">
            <span>Total Gross Profit</span>
            <span class="money">{{ $currency }} {{ number_format($summary['net_estimated_gross_profit'], 0) }}</span>
        </div>

        @include('partials.developer_credit')
    </section>
@endsection
