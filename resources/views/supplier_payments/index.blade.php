@extends('layouts.app', ['title' => 'Supplier Payments'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>Supplier Payments</h2>
            <p>Use this page to review supplier payments in one simple list, especially when checking what was paid, to who, and against which purchase.</p>
        </div>
        <div class="actions">
            <a href="{{ route('supplier-payments.create') }}" class="button-link primary">Record Payment</a>
            <a href="{{ route('supplier-payments.index', ['period' => 'today']) }}" class="button-link {{ $period === 'today' ? 'primary' : '' }}">Today</a>
            <a href="{{ route('supplier-payments.index', ['period' => 'week']) }}" class="button-link {{ $period === 'week' ? 'primary' : '' }}">This Week</a>
            <a href="{{ route('suppliers.index') }}" class="button-link">Suppliers</a>
        </div>
    </div>

    <section class="panel">
        <form method="get" class="filters">
            <input type="search" name="q" value="{{ $search }}" placeholder="Search payment no, supplier, purchase no, or reference">
            <select name="supplier_id">
                <option value="">All suppliers</option>
                @foreach ($suppliers as $supplier)
                    <option value="{{ $supplier->id }}" @selected((string) $supplierId === (string) $supplier->id)>{{ $supplier->name }}</option>
                @endforeach
            </select>
            <select name="period">
                <option value="">Any date</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This Week</option>
            </select>
            <button type="submit">Filter</button>
            @if ($search !== '' || $supplierId > 0 || $period !== '')
                <a href="{{ route('supplier-payments.index') }}" class="button-link">Clear</a>
            @endif
        </form>

        <div class="table-wrap table-mobile-friendly">
            <table>
                <thead>
                    <tr>
                        <th>Payment</th>
                        <th>Supplier</th>
                        <th>Purchase</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title"><a href="{{ route('supplier-payments.show', $payment) }}"><strong>{{ $payment->payment_no }}</strong></a></div>
                                    <div class="table-meta">{{ optional($payment->payment_date)->format('d M Y') ?: '-' }}</div>
                                    <div class="table-meta">{{ $payment->paymentMode?->name ?? 'Payment mode not set' }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    <div class="table-title">{{ $payment->supplier?->name ?? '-' }}</div>
                                    <div class="table-meta">{{ $payment->supplier?->country ?: 'No country' }}</div>
                                </div>
                            </td>
                            <td>
                                <div class="cell-stack">
                                    @if ($payment->purchase)
                                        <div class="table-title"><a href="{{ route('purchases.show', $payment->purchase) }}">{{ $payment->purchase->purchase_no }}</a></div>
                                    @else
                                        <div class="table-title">No linked purchase</div>
                                    @endif
                                    <div class="table-meta">{{ $payment->store?->name ?? 'No store assigned' }}</div>
                                    <div class="table-meta">{{ $payment->reference_no ?: $payment->supplier_invoice_no ?: $payment->cheque_number ?: 'No reference' }}</div>
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
                                        <a href="{{ route('supplier-payments.show', $payment) }}" class="row-action-link">
                                            <span>Open Payment</span>
                                            <span class="meta">View</span>
                                        </a>
                                        <a href="{{ route('supplier-payments.print', ['supplierPayment' => $payment, 'theme' => 'thermal']) }}" target="_blank" class="row-action-link">
                                            <span>Thermal Print</span>
                                            <span class="meta">Print</span>
                                        </a>
                                        @if ($payment->purchase)
                                            <a href="{{ route('purchases.show', $payment->purchase) }}" class="row-action-link">
                                                <span>Open Purchase</span>
                                                <span class="meta">Buy</span>
                                            </a>
                                        @endif
                                        @if ($payment->supplier)
                                            <a href="{{ route('suppliers.statement', $payment->supplier) }}" class="row-action-link">
                                                <span>Supplier Statement</span>
                                                <span class="meta">Stmt</span>
                                            </a>
                                        @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No supplier payments match this view yet.</td>
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
