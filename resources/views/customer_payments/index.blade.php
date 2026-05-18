@extends('layouts.app', ['title' => 'Customer Payments'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <style>
        .desk-filters {
            display: grid;
            grid-template-columns: minmax(0, 1.3fr) minmax(150px, .8fr) 140px auto auto;
            gap: 8px;
            align-items: center;
            margin-bottom: 10px;
        }
        .desk-filters input,
        .desk-filters select {
            min-width: 0;
        }
        .desk-panel {
            padding: 10px;
        }
        .desk-panel table {
            min-width: 0;
        }
        .desk-panel th,
        .desk-panel td {
            padding: 7px 6px;
        }
        .desk-panel .table-title,
        .desk-panel .table-title a {
            font-size: .86rem;
        }
        .desk-panel .table-meta {
            font-size: .78rem;
        }
        @media (max-width: 980px) {
            .desk-filters {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Customer Payments</h2>
            <p>Use this page to find posted customer payments quickly by payment number, customer, date, or related sale. This is the simplest place to confirm that a payment was received.</p>
        </div>
        <div class="actions">
            <a href="{{ route('customer-payments.create') }}" class="button-link primary">Record Payment</a>
            <a href="{{ route('customer-payments.index', ['period' => 'today']) }}" class="button-link {{ $period === 'today' ? 'primary' : '' }}">Today</a>
            <a href="{{ route('customer-payments.index', ['period' => 'week']) }}" class="button-link {{ $period === 'week' ? 'primary' : '' }}">This Week</a>
            <a href="{{ route('customers.index') }}" class="button-link">Customers</a>
        </div>
    </div>

    <section class="panel desk-panel">
        <form method="get" class="filters desk-filters">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search payment no, customer, sale no, or reference">
            <select name="customer_id">
                <option value="">All customers</option>
                @foreach ($customers as $customer)
                    <option value="{{ $customer->id }}" @selected((string) $customerId === (string) $customer->id)>{{ $customer->name }}</option>
                @endforeach
            </select>
            <select name="period">
                <option value="">Any date</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This Week</option>
            </select>
            <button type="submit">Filter</button>
            @if ($search !== '' || $customerId > 0 || $period !== '')
                <a href="{{ route('customer-payments.index') }}" class="button-link">Clear</a>
            @endif
        </form>

        <div class="table-wrap table-mobile-friendly">
            <table>
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th>Customer</th>
                        <th>Sale</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title"><a href="{{ route('customer-payments.show', $payment) }}"><strong>{{ $payment->payment_no }}</strong></a></div>
                                    <div class="table-meta">{{ optional($payment->payment_date)->format('d M Y') ?: '-' }}</div>
                                    <div class="table-meta">{{ $payment->paymentMode?->name ?? 'Payment mode not set' }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ $payment->customer?->name ?? '-' }}</div>
                                    <div class="table-meta">{{ $payment->customer?->location ?: 'No location' }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    @if ($payment->sale)
                                        <div class="table-title"><a href="{{ route('sales.show', $payment->sale) }}">{{ $payment->sale->sale_no }}</a></div>
                                    @else
                                        <div class="table-title">No linked sale</div>
                                    @endif
                                    <div class="table-meta">{{ $payment->store?->name ?? config('business.name', 'Apples Of Gold') }}</div>
                                    <div class="table-meta">{{ $payment->reference_no ?: $payment->cheque_number ?: 'No reference' }}</div>
                                </div>
                            </td>
                            <td class="money">
                                <div class="cell-stack">
                                    <div>{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</div>
                                    <div class="status-inline">
                                        <span class="badge success">Posted</span>
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
                                        <a href="{{ route('customer-payments.show', $payment) }}" class="row-action-link">
                                            <span>Open Payment</span>
                                            <span class="meta">View</span>
                                        </a>
                                        <a href="{{ route('customer-payments.print', ['customerPayment' => $payment, 'theme' => 'thermal']) }}" target="_blank" class="row-action-link">
                                            <span>Thermal Print</span>
                                            <span class="meta">Print</span>
                                        </a>
                                        @if ($payment->sale)
                                            <a href="{{ route('sales.show', $payment->sale) }}" class="row-action-link">
                                                <span>Open Sale</span>
                                                <span class="meta">Sale</span>
                                            </a>
                                        @endif
                                        @if ($payment->customer)
                                            <a href="{{ route('customers.statement', $payment->customer) }}" class="row-action-link">
                                                <span>Customer Statement</span>
                                                <span class="meta">Stmt</span>
                                            </a>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No customer payments match this view yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="pagination">
            {{ $payments->links() }}
        </div>
    </section>
@endsection
