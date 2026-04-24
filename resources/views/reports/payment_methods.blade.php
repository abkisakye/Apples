@extends('layouts.app', ['title' => 'Payment Methods'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($totalInflow = (float) $rows->sum(fn ($row) => $row->sales_in + $row->customer_in))
    @php($totalOutflow = (float) $rows->sum(fn ($row) => $row->supplier_out + $row->expense_out))
    <div class="page-head">
        <div>
            <h2>Payment Method Breakdown</h2>
            <p>Use this report to reconcile cash, mobile money, bank, and other payment channels for the selected period.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
            <a href="{{ route('reports.daily-closing') }}" class="button-link">Daily Closing</a>
            <a href="{{ route('reports.cashier-performance') }}" class="button-link">Cashier Performance</a>
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
        <div class="card"><div class="label">Channels</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Total Inflow</div><div class="value money">{{ $currency }} {{ number_format($totalInflow, 0) }}</div></div>
        <div class="card"><div class="label">Total Outflow</div><div class="value money">{{ $currency }} {{ number_format($totalOutflow, 0) }}</div></div>
        <div class="card"><div class="label">Net</div><div class="value money">{{ $currency }} {{ number_format($totalInflow - $totalOutflow, 0) }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">This page is best for reconciliation: it shows which channels brought money in, which channels paid money out, and the net movement left behind.</p>
        <div class="table-wrap table-mobile-friendly">
            <table>
                <thead>
                    <tr>
                        <th>Payment Method</th>
                        <th>Inflow</th>
                        <th>Outflow</th>
                        <th>Net Movement</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ $row->name }}</div>
                                    <div class="status-inline">
                                        <span class="badge {{ $row->net_total >= 0 ? 'success' : 'credit' }}">{{ $row->net_total >= 0 ? 'Net in' : 'Net out' }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="money">
                                <div class="cell-stack">
                                    <div>Sales: {{ $currency }} {{ number_format($row->sales_in, 0) }}</div>
                                    <div class="table-meta">Customer payments: {{ $currency }} {{ number_format($row->customer_in, 0) }}</div>
                                </div>
                            </td>
                            <td class="money">
                                <div class="cell-stack">
                                    <div>Supplier payments: {{ $currency }} {{ number_format($row->supplier_out, 0) }}</div>
                                    <div class="table-meta">Expenses: {{ $currency }} {{ number_format($row->expense_out, 0) }}</div>
                                </div>
                            </td>
                            <td class="money"><strong>{{ $currency }} {{ number_format($row->net_total, 0) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No payment movement was found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
