@extends('layouts.app', ['title' => 'Customers'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Customers</h2>
            <p>Keep customer accounts easy to follow with contact details, credit setup, and direct access to each customer statement.</p>
        </div>
        <div class="actions">
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create') }}" class="button-link primary">Record Customer Payment</a>
            @endif
            @if ($access->can('sales.manage'))
                <a href="{{ route('customers.create') }}" class="button-link">New Customer</a>
                <a href="{{ route('sales.create') }}" class="button-link">New Sale</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Customers</div><div class="value">{{ number_format($customerSummary['total']) }}</div></div>
        <div class="card"><div class="label">Walk-in Account</div><div class="value">{{ number_format($customerSummary['walk_in']) }}</div></div>
        <div class="card"><div class="label">With Phone</div><div class="value">{{ number_format($customerSummary['with_phone']) }}</div></div>
        <div class="card"><div class="label">Credit Accounts</div><div class="value">{{ number_format($customerSummary['credit_accounts']) }}</div></div>
        <div class="card"><div class="label">Opening Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $customerSummary['opening_balance'], 0) }}</div></div>
    </section>

    <form class="filters" method="get">
        <input type="text" id="customers-visible-filter" name="q" value="{{ $search }}" placeholder="Filter visible rows" title="Typing filters rows currently on this page. Press Filter to search all records." data-table-live-input>
        <select name="type">
            <option value="">All account types</option>
            <option value="regular" @selected($accountType === 'regular')>Named customers</option>
            <option value="credit" @selected($accountType === 'credit')>Credit customers</option>
            <option value="walk_in" @selected($accountType === 'walk_in')>Walk-in account</option>
        </select>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <div class="panel" data-table-live-filter data-table-live-input="#customers-visible-filter">
        <p class="list-note">Open a customer profile to review account health quickly, then jump to statement, payment, or a fresh sale from one place.</p>
        <div class="table-wrap table-mobile-friendly">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Contact</th>
                    <th>Account</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($customers as $customer)
                    @php($currentBalance = round((float) $customer->opening_balance + (float) ($customer->credit_sales_total ?? 0) - (float) ($customer->payments_total ?? 0) - (float) ($customer->returns_total ?? 0), 2))
                    <tr data-table-live-row>
                        <td>
                            <div class="cell-stack">
                                <div class="table-title">
                                    @if (! $customer->is_system)
                                        <a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a>
                                    @else
                                        {{ $customer->name }}
                                    @endif
                                </div>
                                <div class="status-inline">
                                    @if ($customer->is_walk_in)
                                        <span class="badge">Walk-in</span>
                                    @elseif ($customer->is_system)
                                        <span class="badge credit">System</span>
                                    @endif
                                    @if ((float) $customer->credit_limit > 0)
                                        <span class="badge">Limit {{ $currency }} {{ number_format((float) $customer->credit_limit, 0) }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div class="table-meta">{{ $customer->phone ?? 'No phone' }}</div>
                                <div class="table-meta">{{ $customer->location ?? 'No location' }}</div>
                                @if ($customer->address)
                                    <div class="table-meta">{{ $customer->address }}</div>
                                @endif
                            </div>
                        </td>
                        <td class="money">
                            <div class="cell-stack">
                                <div>Sales: {{ number_format($customer->sales_count) }}</div>
                                <div class="table-meta">Payments: {{ number_format($customer->payments_count) }}</div>
                                <div class="table-meta">Opening: {{ $currency }} {{ number_format((float) $customer->opening_balance, 0) }}</div>
                                <div class="status-inline">
                                    <span class="badge {{ $currentBalance > 0 ? 'credit' : 'success' }}">
                                        Current {{ $currency }} {{ number_format($currentBalance, 0) }}
                                    </span>
                                    @if ((float) ($customer->returns_total ?? 0) > 0)
                                        <span class="badge soft">Returns {{ $currency }} {{ number_format((float) $customer->returns_total, 0) }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="cell-stack">
                                <div>{{ $customer->customer_type ?? 'General' }}</div>
                                <div class="table-meta">
                                    <span class="badge {{ $customer->is_active ? 'success' : 'credit' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if (! $customer->is_system)
                                <details class="row-actions-menu">
                                    <summary class="row-actions-toggle">
                                        <span class="action-chip">
                                            <span>Actions</span>
                                            <span class="caret">&#9662;</span>
                                        </span>
                                    </summary>
                                    <div class="row-actions-dropdown">
                                        <a href="{{ route('customers.show', $customer) }}" class="row-action-link">
                                            <span>Open Profile</span>
                                            <span class="meta">View</span>
                                        </a>
                                        <a href="{{ route('customers.statement', $customer) }}" class="row-action-link">
                                            <span>Customer Statement</span>
                                            <span class="meta">Stmt</span>
                                        </a>
                                    @if ($access->can('sales.manage'))
                                            <a href="{{ route('customers.edit', $customer) }}" class="row-action-link">
                                                <span>Edit Customer</span>
                                                <span class="meta">Edit</span>
                                            </a>
                                    @endif
                                    @if ($access->can('customer_payments.manage'))
                                            <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}" class="row-action-link primary">
                                                <span>Record Payment</span>
                                                <span class="meta">Pay</span>
                                            </a>
                                    @endif
                                    @if ($access->can('sales.manage'))
                                            <a href="{{ route('sales.create', ['customer_id' => $customer->id]) }}" class="row-action-link">
                                                <span>Start Sale</span>
                                                <span class="meta">Sale</span>
                                            </a>
                                            <form method="post" action="{{ route('customers.status', $customer) }}">
                                                @csrf
                                                <input type="hidden" name="is_active" value="{{ $customer->is_active ? 0 : 1 }}">
                                                <button type="submit" class="row-action-link {{ $customer->is_active ? 'accent' : 'good' }}">
                                                    <span>{{ $customer->is_active ? 'Archive Customer' : 'Activate Customer' }}</span>
                                                    <span class="meta">{{ $customer->is_active ? 'Off' : 'On' }}</span>
                                                </button>
                                            </form>
                                    @endif
                                    </div>
                                </details>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="muted">No customers match this view yet.</td>
                    </tr>
                @endforelse
                <tr data-table-live-empty hidden>
                    <td colspan="5" class="muted">No matching records found.</td>
                </tr>
            </tbody>
        </table>
        </div>
        <div class="pagination">{{ $customers->links() }}</div>
    </div>
@endsection
