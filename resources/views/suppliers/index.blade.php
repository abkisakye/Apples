@extends('layouts.app', ['title' => 'Suppliers'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Suppliers</h2>
            <p>Keep supplier accounts clean with contacts, opening balances, payment terms, and direct access to purchase history and statements.</p>
        </div>
        <div class="actions">
            @if ($access->can('supplier_payments.manage'))
                <a href="{{ route('supplier-payments.create') }}" class="button-link primary">Post Supplier Payment</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('suppliers.create') }}" class="button-link">New Supplier</a>
                <a href="{{ route('purchases.create') }}" class="button-link">New Purchase</a>
            @endif
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Listed Suppliers</div><div class="value">{{ number_format($supplierSummary['total']) }}</div></div>
        <div class="card"><div class="label">Active Suppliers</div><div class="value">{{ number_format($supplierSummary['active']) }}</div></div>
        <div class="card"><div class="label">With Phone</div><div class="value">{{ number_format($supplierSummary['with_phone']) }}</div></div>
        <div class="card"><div class="label">Opening Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $supplierSummary['opening_balance'], 0) }}</div></div>
    </section>

    <form class="filters" method="get">
        <input type="text" id="suppliers-visible-filter" name="q" value="{{ $search }}" placeholder="Filter visible rows" title="Typing filters rows currently on this page. Press Filter to search all records." data-table-live-input>
        <select name="status">
            <option value="">All statuses</option>
            <option value="active" @selected($statusFilter === 'active')>Active</option>
            <option value="inactive" @selected($statusFilter === 'inactive')>Inactive</option>
        </select>
        <button type="submit">Filter</button>
    </form>

    <div class="panel" data-table-live-filter data-table-live-input="#suppliers-visible-filter">
        <p class="list-note">Open a supplier profile to review balances, statement lines, recent payments, and purchases from one place.</p>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Supplier</th>
                        <th>Phone</th>
                        <th>Country</th>
                        <th>Purchases</th>
                        <th>Payments</th>
                        <th>Opening Balance</th>
                        <th>Current Balance</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($suppliers as $supplier)
                        @php($currentBalance = round((float) $supplier->opening_balance + (float) ($supplier->purchases_total ?? 0) - (float) ($supplier->payments_total ?? 0) - (float) ($supplier->returns_total ?? 0), 2))
                        <tr data-table-live-row>
                            <td>
                                <div class="table-title">
                                    @if (! $supplier->is_system)
                                        <a href="{{ route('suppliers.show', $supplier) }}">{{ $supplier->name }}</a>
                                    @else
                                        {{ $supplier->name }}
                                    @endif
                                </div>
                                <div class="table-meta">
                                    {{ $supplier->supplier_type ?: 'General supplier' }}
                                    @if ($supplier->tin)
                                        | TIN {{ $supplier->tin }}
                                    @endif
                                    @if ((float) ($supplier->returns_total ?? 0) > 0)
                                        | Returns {{ $currency }} {{ number_format((float) $supplier->returns_total, 0) }}
                                    @endif
                                </div>
                            </td>
                            <td>{{ $supplier->phone ?? '-' }}</td>
                            <td>
                                <div class="table-title">{{ $supplier->country ?? '-' }}</div>
                                @if ($supplier->address)
                                    <div class="table-meta">{{ $supplier->address }}</div>
                                @endif
                            </td>
                            <td>{{ number_format($supplier->purchases_count) }}</td>
                            <td>{{ number_format($supplier->payments_count) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $supplier->opening_balance, 0) }}</td>
                            <td class="money">
                                <span class="badge {{ $currentBalance > 0 ? 'credit' : 'success' }}">
                                    {{ $currency }} {{ number_format($currentBalance, 0) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $supplier->is_active ? 'success' : 'credit' }}">{{ $supplier->is_active ? 'Active' : 'Inactive' }}</span>
                            </td>
                            <td>
                                @if (! $supplier->is_system)
                                    <details class="row-actions-menu">
                                        <summary class="row-actions-toggle">
                                            <span class="action-chip">
                                                <span>Actions</span>
                                                <span class="caret">&#9662;</span>
                                            </span>
                                        </summary>
                                        <div class="row-actions-dropdown">
                                            <a href="{{ route('suppliers.show', $supplier) }}" class="row-action-link">
                                                <span>Open Profile</span>
                                                <span class="meta">View</span>
                                            </a>
                                            <a href="{{ route('suppliers.statement', $supplier) }}" class="row-action-link">
                                                <span>Supplier Statement</span>
                                                <span class="meta">Stmt</span>
                                            </a>
                                        @if ($access->can('purchases.manage'))
                                                <a href="{{ route('suppliers.edit', $supplier) }}" class="row-action-link">
                                                    <span>Edit Supplier</span>
                                                    <span class="meta">Edit</span>
                                                </a>
                                        @endif
                                        @if ($access->can('supplier_payments.manage'))
                                                <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="row-action-link primary">
                                                    <span>Post Payment</span>
                                                    <span class="meta">Pay</span>
                                                </a>
                                        @endif
                                        @if ($access->can('purchases.manage'))
                                                <form method="post" action="{{ route('suppliers.status', $supplier) }}">
                                                    @csrf
                                                    <input type="hidden" name="is_active" value="{{ $supplier->is_active ? 0 : 1 }}">
                                                    <button type="submit" class="row-action-link {{ $supplier->is_active ? 'accent' : 'good' }}">
                                                        <span>{{ $supplier->is_active ? 'Archive Supplier' : 'Activate Supplier' }}</span>
                                                        <span class="meta">{{ $supplier->is_active ? 'Off' : 'On' }}</span>
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
                    @endforeach
                    <tr data-table-live-empty hidden>
                        <td colspan="9" class="muted">No matching records found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="pagination">{{ $suppliers->links() }}</div>
    </div>
@endsection
