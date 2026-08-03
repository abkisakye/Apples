@extends('layouts.app', ['title' => 'Financial Summary'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($netPositive = (float) $summary['net_profit'] >= 0)
    <div class="page-head">
        <div>
            <h2>Financial Summary</h2>
            <p>This report shows sales, discounts, cost of goods sold, expenses, and profit for the selected period.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.daily-sales-summary', ['date_from' => $fromDate, 'date_to' => $toDate]) }}" class="button-link">Daily Sales Summary</a>
            <a href="{{ route('reports.stock-valuation') }}" class="button-link">Stock Valuation</a>
            <a href="{{ route('reports.price-margins') }}" class="button-link">Cost vs Selling Price</a>
            <a href="{{ route('reports.daily-closing') }}" class="button-link">Daily Closing</a>
            <a href="{{ route('reports.payment-methods') }}" class="button-link">Payment Methods</a>
            <a href="{{ route('reports.cashier-performance') }}" class="button-link">Cashier Performance</a>
            <a href="{{ route('reports.customer-aging') }}" class="button-link">Aging</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom: 16px;">
        <form method="get" class="filters">
            <input type="date" name="from" value="{{ $fromDate }}">
            <input type="date" name="to" value="{{ $toDate }}">
            <select name="period">
                <option value="">Custom</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
                <option value="month" @selected($period === 'month')>This month</option>
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['sales_total'], 0) }}</div></div>
        <div class="card"><div class="label">Discounts</div><div class="value money">{{ $currency }} {{ number_format($summary['discount_total'], 0) }}</div></div>
        <div class="card"><div class="label">COGS</div><div class="value money">{{ $currency }} {{ number_format($summary['cogs'], 0) }}</div></div>
        <div class="card"><div class="label">Gross Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['gross_profit'], 0) }}</div></div>
        <div class="card"><div class="label">Expenses</div><div class="value money">{{ $currency }} {{ number_format($summary['expense_total'], 0) }}</div></div>
        <div class="card"><div class="label">Net Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['net_profit'], 0) }}</div></div>
    </section>
    <section class="panel" style="margin-bottom:16px;">
        <div class="status-inline">
            <span class="badge {{ $netPositive ? 'success' : 'credit' }}">{{ $netPositive ? 'Net positive' : 'Net under pressure' }}</span>
            <span class="badge soft">Collections {{ $currency }} {{ number_format($summary['collection_total'], 0) }}</span>
            <span class="badge soft">Purchases posted {{ $currency }} {{ number_format($summary['purchase_total'], 0) }}</span>
        </div>
        <p class="list-note">Use this summary first, then drill into payment methods, cashier performance, or daily closing when you need the reason behind the numbers.</p>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Financial Breakdown</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:42%;">Sales Value</th><td>{{ $currency }} {{ number_format($summary['sales_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Collections</th><td>{{ $currency }} {{ number_format($summary['collection_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Returned Sales</th><td>{{ $currency }} {{ number_format($summary['return_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Purchases Posted</th><td>{{ $currency }} {{ number_format($summary['purchase_total'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Gross Profit</th><td>{{ $currency }} {{ number_format($summary['gross_profit'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Net Profit</th><td><strong>{{ $currency }} {{ number_format($summary['net_profit'], 0) }}</strong></td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Daily Movement</h3>
            <div style="display:grid; gap:10px; margin-top: 14px;">
                @foreach ($daily as $point)
                    <div style="display:grid; grid-template-columns: 72px 1fr 110px; gap:10px; align-items:center;">
                        <div class="muted">{{ $point['label'] }}</div>
                        <div style="height: 12px; background:#efe7d8; border-radius:999px; overflow:hidden; display:flex;">
                            <div style="height: 100%; width: {{ round(($point['sales'] / $dailyMax) * 100, 2) }}%; background:linear-gradient(90deg, #0b5d3f, #1d8f63);"></div>
                            <div style="height: 100%; width: {{ round(($point['expenses'] / $dailyMax) * 100, 2) }}%; background:linear-gradient(90deg, #b4452b, #df7d55);"></div>
                        </div>
                        <div class="money">{{ number_format($point['sales'] - $point['expenses'], 0) }}</div>
                    </div>
                @endforeach
            </div>
            <p class="list-note">Green shows sales activity and orange shows expense pressure. The figure on the right is the daily difference.</p>
        </div>
    </section>

    @include('partials.developer_credit')
@endsection
