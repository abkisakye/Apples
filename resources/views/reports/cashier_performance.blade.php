@extends('layouts.app', ['title' => 'Cashier Performance'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($salesCountTotal = (int) $rows->sum('sales_count'))
    @php($salesValueTotal = (float) $rows->sum('sales_total'))
    @php($discountTotal = (float) $rows->sum('discount_total'))
    <div class="page-head">
        <div>
            <h2>Cashier Performance</h2>
            <p>This compares sales activity, discounts, credit issued, collections, and shift control by staff member.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
            <a href="{{ route('reports.daily-closing') }}" class="button-link">Daily Closing</a>
            <a href="{{ route('cash-shifts.index') }}" class="button-link">Cash Shifts</a>
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
        <div class="card"><div class="label">Staff Shown</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Sales Count</div><div class="value">{{ number_format($salesCountTotal) }}</div></div>
        <div class="card"><div class="label">Sales Value</div><div class="value money">{{ $currency }} {{ number_format($salesValueTotal, 0) }}</div></div>
        <div class="card"><div class="label">Discounts</div><div class="value money">{{ $currency }} {{ number_format($discountTotal, 0) }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Read this report as a supervision screen: it shows who sold, who collected, and where shift differences need a follow-up conversation.</p>
        <div class="table-wrap table-mobile-friendly">
            <table>
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Sales Activity</th>
                        <th>Money Flow</th>
                        <th>Shift Control</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ $row->user->name }}</div>
                                    <div class="status-inline">
                                        <span class="badge soft">{{ $row->user->displayRoleName() }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack money">
                                    <div>{{ number_format($row->sales_count) }} sales</div>
                                    <div class="table-meta">Average basket: {{ $currency }} {{ number_format($row->average_basket, 0) }}</div>
                                </div>
                            </td>
                            <td class="money">
                                <div class="cell-stack">
                                    <div>Sales value: {{ $currency }} {{ number_format($row->sales_total, 0) }}</div>
                                    <div class="table-meta">Discounts: {{ $currency }} {{ number_format($row->discount_total, 0) }}</div>
                                    <div class="table-meta">Credit issued: {{ $currency }} {{ number_format($row->credit_issued, 0) }}</div>
                                    <div class="table-meta">Collections: {{ $currency }} {{ number_format($row->customer_payments, 0) }}</div>
                                </div>
                            </td>
                            <td class="money">
                                <div class="cell-stack">
                                    <div>{{ number_format($row->shift_count) }} shifts</div>
                                    <div class="status-inline">
                                        <span class="badge {{ $row->shift_difference == 0 ? 'success' : 'credit' }}">Difference: {{ $currency }} {{ number_format($row->shift_difference, 0) }}</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No cashier activity was found for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
