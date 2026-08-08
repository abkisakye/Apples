@php
    $currency = config('business.currency', 'UGX');
    $summary = $summary ?? [
        'sales_count' => 0,
        'gross_sales' => 0,
        'cash_sales' => 0,
        'credit_sales' => 0,
        'settled_paid' => 0,
        'outstanding' => 0,
        'voided_count' => 0,
        'voided_sales' => 0,
    ];
@endphp

<section class="cards desk-cards">
    <div class="card"><div class="label">Sales Count</div><div class="value">{{ number_format((int) $summary['sales_count']) }}</div></div>
    <div class="card"><div class="label">Gross Sales</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['gross_sales'], 0) }}</div></div>
    <div class="card"><div class="label">Cash Sales</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['cash_sales'], 0) }}</div></div>
    <div class="card"><div class="label">Credit Sales</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['credit_sales'], 0) }}</div></div>
    <div class="card"><div class="label">Settled / Paid</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['settled_paid'], 0) }}</div></div>
    <div class="card"><div class="label">Outstanding</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['outstanding'], 0) }}</div></div>
    <div class="card danger">
        <div class="label">Voided Sales</div>
        <div class="value money">{{ number_format((int) $summary['voided_count']) }} | {{ $currency }} {{ number_format((float) $summary['voided_sales'], 0) }}</div>
    </div>
</section>

<div class="panel desk-panel">
    <p class="list-note">This list keeps posted and voided sales visible for audit. Voided sales are excluded from business totals above.</p>
    <div class="table-wrap table-mobile-friendly">
    <table class="sales-table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Sale Date</th>
                <th>Customer</th>
                <th>Salesperson</th>
                <th>Total (UGX)</th>
                <th>Settled (UGX)</th>
                <th>Balance (UGX)</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $sale)
                @php($isVoided = in_array($sale->status, ['void', 'cancelled', 'canceled'], true))
                @php($paidAmount = (float) $sale->amount_paid)
                @php($balanceDue = (float) $sale->balance_due)
                @php($paymentStatus = $isVoided ? 'Void' : ($balanceDue <= 0 ? 'Paid' : ($paidAmount > 0 ? 'Partial' : 'Pending')))
                @php($statusClass = $isVoided ? 'danger' : ($paymentStatus === 'Paid' ? 'success' : ($paymentStatus === 'Partial' ? 'credit' : '')))
                <tr @class(['voided-row' => $isVoided])>
                    <td>
                        <div class="cell-stack">
                            <div class="table-title"><a href="{{ route('sales.show', $sale) }}">{{ $sale->sale_no }}</a></div>
                            <div class="status-inline">
                                <span class="badge {{ $sale->sale_type === 'credit' ? 'credit' : '' }}">{{ ucfirst($sale->sale_type) }}</span>
                                @if ($sale->paymentMode)
                                    <span class="badge">{{ $sale->paymentMode->name }}</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td>{{ optional($sale->sale_date)->format('d M Y') ?: '-' }}</td>
                    <td>
                        <div class="cell-stack">
                            <div class="table-title">{{ $sale->customer?->name ?? 'Walk-in customer' }}</div>
                            @if ($sale->customer?->phone)
                                <div class="table-meta">{{ $sale->customer->phone }}</div>
                            @endif
                        </div>
                    </td>
                    <td>{{ $sale->createdBy?->name ?? '-' }}</td>
                    <td class="money">{{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</td>
                    <td class="money">{{ $currency }} {{ number_format($paidAmount, 0) }}</td>
                    <td class="money">{{ $currency }} {{ number_format($balanceDue, 0) }}</td>
                    <td>
                        <div class="status-inline">
                            <span class="badge {{ $statusClass }}">{{ $paymentStatus }}</span>
                            @if (! $isVoided && $sale->credit_due_date && $balanceDue > 0)
                                <span class="badge credit">Due {{ $sale->credit_due_date->format('d M Y') }}</span>
                            @endif
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
                                <a href="{{ route('sales.show', $sale) }}" class="row-action-link">
                                    <span>Open Sale</span>
                                    <span class="meta">View</span>
                                </a>
                                <a href="{{ route('sales.print', $sale) }}" target="_blank" class="row-action-link">
                                    <span>Print Receipt</span>
                                    <span class="meta">Print</span>
                                </a>
                                @if ($access->can('customer_payments.manage') && $sale->balance_due > 0 && $sale->status === 'posted')
                                    <a href="{{ route('customer-payments.create', ['customer_id' => $sale->customer_id]) }}" class="row-action-link primary">
                                        <span>Record Payment</span>
                                        <span class="meta">Pay</span>
                                    </a>
                                    <a href="{{ route('customers.statement', $sale->customer_id) }}" class="row-action-link">
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
                    <td colspan="9" class="muted">No sales match this view yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
    </div>
    <div class="pagination">{{ $sales->links() }}</div>
</div>
