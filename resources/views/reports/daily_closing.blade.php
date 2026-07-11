@extends('layouts.app', ['title' => 'Daily Closing'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($balancedShifts = $shiftRows->filter(fn ($shift) => $shift->counted_cash !== null && (float) $shift->shortage_overage === 0.0)->count())
    @php($openShiftCount = $shiftRows->where('status', 'open')->count())
    <div class="page-head">
        <div>
            <h2>Daily Closing Report</h2>
            <p>Use this page at the end of the day to review sales, discounts, returns, collections, expenses, and shift cash reconciliation.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.daily-sales-summary', ['date_from' => $date, 'date_to' => $date]) }}" class="button-link">Daily Sales Summary</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
            <a href="{{ route('reports.payment-methods', ['from' => $date, 'to' => $date]) }}" class="button-link">Payment Methods</a>
            <a href="{{ route('cash-shifts.index', ['period' => 'today']) }}" class="button-link">Cash Shifts</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom: 16px;">
        <form method="get" class="filters">
            <input type="date" name="date" value="{{ $date }}">
            <button type="submit">Apply</button>
            <a href="{{ route('reports.daily-closing', ['date' => now()->toDateString()]) }}" class="button-link">Today</a>
        </form>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['sales_total'], 0) }}</div></div>
        <div class="card"><div class="label">Discounts</div><div class="value money">{{ $currency }} {{ number_format($summary['discount_total'], 0) }}</div></div>
        <div class="card"><div class="label">Returns</div><div class="value money">{{ $currency }} {{ number_format($summary['return_total'], 0) }}</div></div>
        <div class="card"><div class="label">Refunds</div><div class="value money">{{ $currency }} {{ number_format($summary['refund_total'], 0) }}</div></div>
        <div class="card"><div class="label">Customer Payments</div><div class="value money">{{ $currency }} {{ number_format($summary['customer_payment_total'], 0) }}</div></div>
        <div class="card"><div class="label">Expenses</div><div class="value money">{{ $currency }} {{ number_format($summary['expense_total'], 0) }}</div></div>
        <div class="card"><div class="label">Credit Issued</div><div class="value money">{{ $currency }} {{ number_format($summary['credit_issued'], 0) }}</div></div>
    </section>
    <section class="cards">
        <div class="card"><div class="label">Balanced Shifts</div><div class="value">{{ number_format($balancedShifts) }}</div></div>
        <div class="card"><div class="label">Open Shifts</div><div class="value">{{ number_format($openShiftCount) }}</div></div>
        <div class="card"><div class="label">Cash Difference</div><div class="value money">{{ $currency }} {{ number_format($summary['cash_difference'], 0) }}</div></div>
        <div class="card"><div class="label">Channels Active</div><div class="value">{{ number_format($paymentModeRows->count()) }}</div></div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Cash Position</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:42%;">Expected Cash</th><td>{{ $currency }} {{ number_format($summary['cash_expected'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Counted Cash</th><td>{{ $currency }} {{ number_format($summary['cash_counted'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Difference</th><td><strong>{{ $currency }} {{ number_format($summary['cash_difference'], 0) }}</strong></td></tr>
                </tbody>
            </table>

            <h3 style="margin-top: 18px;">Payment Methods</h3>
            <div class="table-wrap table-mobile-friendly">
                <table>
                    <thead>
                        <tr>
                            <th>Method</th>
                            <th>Net Movement</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentModeRows as $row)
                            <tr>
                                <td>
                                    <div class="table-title">{{ $row->mode_name }}</div>
                                </td>
                                <td class="money">
                                    <div class="cell-stack">
                                        <div>{{ $currency }} {{ number_format((float) $row->amount, 0) }}</div>
                                        <div class="table-meta">Sales {{ number_format((float) $row->sales_amount, 0) }} / Customer {{ number_format((float) $row->customer_payment_amount, 0) }} / Refunds {{ number_format((float) $row->refund_amount, 0) }}</div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="muted">No payment activity was posted for this day.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Shift Closings</h3>
            <div class="table-wrap table-mobile-friendly">
                <table>
                    <thead>
                        <tr>
                            <th>Shift</th>
                            <th>Team</th>
                            <th>Cash</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($shiftRows as $shift)
                            <tr>
                                <td>
                                    <div class="cell-stack">
                                        <div class="table-title"><a href="{{ route('cash-shifts.show', $shift) }}">{{ $shift->shift_no }}</a></div>
                                        <div class="table-meta">{{ optional($shift->opened_at)->format('d M Y H:i') }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="cell-stack">
                                        <div class="table-title">{{ $shift->user?->name ?? '-' }}</div>
                                        <div class="table-meta">{{ $shift->store?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                                    </div>
                                </td>
                                <td class="money">
                                    <div class="cell-stack">
                                        <div>Expected: {{ $currency }} {{ number_format((float) $shift->expected_cash, 0) }}</div>
                                        <div class="table-meta">Counted: {{ $shift->counted_cash === null ? '-' : $currency.' '.number_format((float) $shift->counted_cash, 0) }}</div>
                                        <div class="table-meta">Difference: {{ $shift->shortage_overage === null ? '-' : $currency.' '.number_format((float) $shift->shortage_overage, 0) }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="status-inline">
                                        <span class="badge {{ $shift->status === 'closed' ? 'success' : 'credit' }}">{{ ucfirst($shift->status) }}</span>
                                        @if ($shift->counted_cash !== null && (float) $shift->shortage_overage === 0.0)
                                            <span class="badge success">Balanced</span>
                                        @elseif ($shift->counted_cash !== null)
                                            <span class="badge credit">Needs review</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="action-stack">
                                        <a href="{{ route('cash-shifts.show', $shift) }}" class="action-chip">View Shift</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="muted">No shifts were opened for this date.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
