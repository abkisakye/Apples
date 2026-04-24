@extends('layouts.app', ['title' => 'Customer Aging'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($totalOutstanding = $rows->sum('total'))
    @php($overdueAccounts = $rows->filter(fn ($row) => (float) $row->days_90_plus > 0)->count())
    <div class="page-head">
        <div>
            <h2>Customer Aging Report</h2>
            <p>Outstanding customer balances grouped into aging buckets based on due dates.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.customer-aging.export') }}" class="button-link">Export CSV</a>
            <a href="{{ route('reports.supplier-aging') }}" class="button-link">Supplier Aging</a>
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.index', ['period' => 'today']) }}" class="button-link primary">Today Payments</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Accounts</div><div class="value">{{ number_format($rows->count()) }}</div></div>
        <div class="card"><div class="label">Total Outstanding</div><div class="value money">{{ $currency }} {{ number_format($totalOutstanding, 0) }}</div></div>
        <div class="card"><div class="label">90+ Days</div><div class="value money">{{ $currency }} {{ number_format($rows->sum('days_90_plus'), 0) }}</div></div>
        <div class="card"><div class="label">90+ Accounts</div><div class="value">{{ number_format($overdueAccounts) }}</div></div>
    </section>

    <section class="panel">
        <p class="list-note">Start with the red accounts first. The action column lets you open the statement or record a payment without leaving the report trail.</p>
        <div class="table-wrap table-mobile-friendly">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Current</th>
                    <th>1-30</th>
                    <th class="mobile-hide">31-60</th>
                    <th class="mobile-hide">61-90</th>
                    <th>90+</th>
                    <th>Total</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title"><a href="{{ route('customers.statement', $row->customer) }}">{{ $row->customer->name }}</a></div>
                                <div class="status-inline">
                                    <span class="badge {{ $row->days_90_plus > 0 ? 'credit' : 'success' }}">
                                        {{ $row->days_90_plus > 0 ? '90+ overdue' : 'Active account' }}
                                    </span>
                                    @if ($row->days_1_30 > 0)
                                        <span class="badge soft">1-30 due</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="money">{{ number_format($row->current, 0) }}</td>
                        <td class="money">{{ number_format($row->days_1_30, 0) }}</td>
                        <td class="money mobile-hide">{{ number_format($row->days_31_60, 0) }}</td>
                        <td class="money mobile-hide">{{ number_format($row->days_61_90, 0) }}</td>
                        <td class="money">
                            <span class="badge {{ $row->days_90_plus > 0 ? 'credit' : '' }}">{{ number_format($row->days_90_plus, 0) }}</span>
                        </td>
                        <td class="money">{{ number_format($row->total, 0) }}</td>
                        <td>
                            <div class="action-stack">
                                <a href="{{ route('customers.statement', $row->customer) }}" class="action-chip">Statement</a>
                                @if ($access->can('customer_payments.manage'))
                                    <a href="{{ route('customer-payments.create', ['customer_id' => $row->customer->id]) }}" class="action-chip primary">Record Payment</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">No outstanding customer accounts were found for this view.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endsection
