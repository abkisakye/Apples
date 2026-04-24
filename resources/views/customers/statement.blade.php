@extends('layouts.app', ['title' => 'Customer Statement'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>{{ $customer->name }} Statement</h2>
            <p>Review this customer account in one place, including credit sales, returns, payments received, and the running balance.</p>
        </div>
        <div class="actions">
            <a href="{{ route('customers.statement.print', $customer) }}" target="_blank" class="button-link">Print Statement</a>
            <a href="{{ route('customers.statement.pdf', $customer) }}" class="button-link">Download PDF</a>
            <a href="{{ route('customers.statement.export', $customer) }}" class="button-link">Export CSV</a>
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create', ['customer_id' => $customer->id]) }}" class="button-link primary">Post Payment</a>
            @endif
            <a href="{{ route('customers.index') }}" class="button-link">Back to Customers</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Opening Balance</div><div class="value money">{{ $currency }} {{ number_format($summary['opening_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Credit Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['total_sales'], 0) }}</div></div>
        <div class="card"><div class="label">Returns / Credits</div><div class="value money">{{ $currency }} {{ number_format($summary['total_returns'], 0) }}</div></div>
        <div class="card"><div class="label">Payments</div><div class="value money">{{ $currency }} {{ number_format($summary['total_payments'], 0) }}</div></div>
        <div class="card"><div class="label">Closing Balance</div><div class="value money">{{ $currency }} {{ number_format($summary['closing_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Entries</div><div class="value">{{ number_format(count($entries)) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Customer Profile</h3>
            <p class="list-note">Use these details when you need to confirm who the account belongs to before posting or following up.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 34%;">Customer</th>
                        <td>{{ $customer->name }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Phone</th>
                        <td>{{ $customer->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Location</th>
                        <td>{{ $customer->location ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Address</th>
                        <td>{{ $customer->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Credit Limit</th>
                        <td>{{ $currency }} {{ number_format((float) $customer->credit_limit, 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Account Summary</h3>
            <p class="list-note">Debits increase what the customer owes. Payments and posted returns reduce the balance as credits.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 42%;">Opening Balance</th>
                        <td>{{ $currency }} {{ number_format($summary['opening_balance'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Total Credit Sales</th>
                        <td>{{ $currency }} {{ number_format($summary['total_sales'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Payments Received</th>
                        <td>{{ $currency }} {{ number_format($summary['total_payments'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Returns / Credits</th>
                        <td>{{ $currency }} {{ number_format($summary['total_returns'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Closing Balance</th>
                        <td>{{ $currency }} {{ number_format($summary['closing_balance'], 0) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="list-note" style="margin-top: 14px;">Use this page for credit follow-up, account review, and receipt reconciliation before you print the statement.</p>
        </div>
    </section>

    <section class="panel">
        <h3>Statement Entries</h3>
        <p class="list-note">Read this like a running account: sales raise the balance, while payments and sale returns bring it down.</p>
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
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td>{{ optional($entry['date'])->format('d M Y') }}</td>
                        <td>{{ $entry['type'] }}</td>
                        <td>{{ $entry['reference'] }}</td>
                        <td>{{ $entry['details'] }}</td>
                        <td class="money">{{ $currency }} {{ number_format($entry['debit'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($entry['credit'], 0) }}</td>
                        <td class="money">{{ $currency }} {{ number_format($entry['running_balance'], 0) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No customer transactions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endsection
