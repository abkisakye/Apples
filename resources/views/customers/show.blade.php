@extends('layouts.app', ['title' => 'Customer Profile'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>{{ $customer->name }}</h2>
            <p>Use this profile to review the customer account quickly before posting a payment, making another sale, or opening the full statement.</p>
        </div>
        <div class="actions">
            @if ($access->can('customers.statement'))
                <a href="{{ route('customers.statement', $customer) }}" class="button-link">Statement</a>
                <a href="{{ route('customers.statement.print', $customer) }}" target="_blank" class="button-link">Print Statement</a>
            @endif
            @if ($access->can('sales.manage'))
                <a href="{{ route('customers.edit', $customer) }}" class="button-link">Edit Customer</a>
            @endif
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}" class="button-link primary">Post Payment</a>
            @endif
            @if ($access->can('sales.manage'))
                <a href="{{ route('sales.create', ['customer_id' => $customer->id]) }}" class="button-link">New Sale</a>
            @endif
            <a href="{{ route('customers.index') }}" class="button-link">Back to Customers</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Current Balance</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['closing_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Credit Outstanding</div><div class="value money">{{ $currency }} {{ number_format((float) $customer->creditSales->sum('balance_due'), 0) }}</div></div>
        <div class="card"><div class="label">Sales</div><div class="value">{{ number_format($customer->sales->count()) }}</div></div>
        <div class="card"><div class="label">Payments</div><div class="value">{{ number_format($customer->payments->count()) }}</div></div>
        <div class="card"><div class="label">Returns / Credits</div><div class="value money">{{ $currency }} {{ number_format((float) $summary['total_returns'], 0) }}</div></div>
        <div class="card"><div class="label">Pending Follow-ups</div><div class="value">{{ number_format($followUps) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Customer Details</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Phone</th><td>{{ $customer->phone ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Location</th><td>{{ $customer->location ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Address</th><td>{{ $customer->address ?? '-' }}</td></tr>
                    <tr><th style="text-align:left;">Type</th><td>{{ $customer->customer_type ?? 'General' }}</td></tr>
                    <tr>
                        <th style="text-align:left;">Credit Sales</th>
                        <td><span class="badge {{ $customer->allow_credit_sales ? 'success' : 'credit' }}">{{ $customer->allow_credit_sales ? 'Approved' : 'Not approved' }}</span></td>
                    </tr>
                    <tr><th style="text-align:left;">Credit Limit</th><td>{{ $currency }} {{ number_format((float) $customer->credit_limit, 0) }}</td></tr>
                    <tr><th style="text-align:left;">Status</th><td><span class="badge {{ $customer->is_active ? 'success' : 'credit' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Account Snapshot</h3>
            <table>
                <tbody>
                    <tr><th style="text-align:left; width:38%;">Opening Balance</th><td>{{ $currency }} {{ number_format((float) $summary['opening_balance'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Total Credit Sales</th><td>{{ $currency }} {{ number_format((float) $summary['total_sales'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Total Payments</th><td>{{ $currency }} {{ number_format((float) $summary['total_payments'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Returns / Credits</th><td>{{ $currency }} {{ number_format((float) $summary['total_returns'], 0) }}</td></tr>
                    <tr><th style="text-align:left;">Closing Balance</th><td>{{ $currency }} {{ number_format((float) $summary['closing_balance'], 0) }}</td></tr>
                </tbody>
            </table>
            <p class="list-note">This balance follows the customer statement logic: opening balance plus credit sales minus posted payments and posted returns.</p>
        </div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Recent Sales</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Sale No</th>
                            <th>Date</th>
                            <th>Store</th>
                            <th>Type</th>
                            <th>Total</th>
                            <th>Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentSales as $sale)
                            <tr>
                                <td><a href="{{ route('sales.show', $sale) }}">{{ $sale->sale_no }}</a></td>
                                <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                                <td>{{ $sale->store?->name ?? '-' }}</td>
                                <td><span class="badge {{ $sale->sale_type === 'credit' ? 'credit' : 'success' }}">{{ ucfirst($sale->sale_type) }}</span></td>
                                <td class="money">{{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No sales recorded for this customer yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Recent Payments</h3>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Payment No</th>
                            <th>Date</th>
                            <th>Applied Sale</th>
                            <th>Mode</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentPayments as $payment)
                            <tr>
                                <td><a href="{{ route('customer-payments.show', $payment) }}">{{ $payment->payment_no }}</a></td>
                                <td>{{ optional($payment->payment_date)->format('d M Y') }}</td>
                                <td>{{ $payment->sale?->sale_no ?? '-' }}</td>
                                <td>{{ $payment->paymentMode?->name ?? '-' }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $payment->amount, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No payments posted for this customer yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top: 16px;">
        <h3>Latest Statement Entries</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Details</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($entries as $entry)
                        <tr>
                            <td>{{ optional($entry['date'])->format('d M Y') }}</td>
                            <td>{{ $entry['type'] }}</td>
                            <td>{{ $entry['reference'] }}</td>
                            <td>{{ $entry['details'] }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $entry['debit'], 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $entry['credit'], 0) }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $entry['running_balance'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="muted">No statement activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
