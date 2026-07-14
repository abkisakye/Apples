@php($currency = config('business.currency', 'UGX'))

<section class="panel desk-panel">
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
