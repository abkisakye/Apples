@extends('layouts.app', ['title' => 'Supplier Statement'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    <div class="page-head">
        <div>
            <h2>{{ $supplier->name }} Statement</h2>
            <p>Review this supplier account in one place, including purchases posted, supplier returns, payments made, and the running balance.</p>
        </div>
        <div class="actions">
            <a href="{{ route('suppliers.statement.print', $supplier) }}" target="_blank" class="button-link">Print Statement</a>
            <a href="{{ route('suppliers.statement.pdf', $supplier) }}" class="button-link">Download PDF</a>
            <a href="{{ route('suppliers.statement.export', $supplier) }}" class="button-link">Export CSV</a>
            @if ($access->can('supplier_payments.manage'))
                <a href="{{ route('supplier-payments.create', ['supplier_id' => $supplier->id]) }}" class="button-link primary">Post Payment</a>
            @endif
            <a href="{{ route('suppliers.index') }}" class="button-link">Back to Suppliers</a>
        </div>
    </div>

    <section class="cards">
        <div class="card"><div class="label">Opening Balance</div><div class="value money">{{ $currency }} {{ number_format($summary['opening_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Purchases</div><div class="value money">{{ $currency }} {{ number_format($summary['total_purchases'], 0) }}</div></div>
        <div class="card"><div class="label">Returns / Credits</div><div class="value money">{{ $currency }} {{ number_format($summary['total_returns'], 0) }}</div></div>
        <div class="card"><div class="label">Payments</div><div class="value money">{{ $currency }} {{ number_format($summary['total_payments'], 0) }}</div></div>
        <div class="card"><div class="label">Closing Balance</div><div class="value money">{{ $currency }} {{ number_format($summary['closing_balance'], 0) }}</div></div>
        <div class="card"><div class="label">Entries</div><div class="value">{{ number_format(count($entries)) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Supplier Profile</h3>
            <p class="list-note">Use these details when checking who supplied the goods and where follow-up should go.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 34%;">Supplier</th>
                        <td>{{ $supplier->name }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Phone</th>
                        <td>{{ $supplier->phone ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Location</th>
                        <td>{{ $supplier->location ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Address</th>
                        <td>{{ $supplier->address ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Opening Balance</th>
                        <td>{{ $currency }} {{ number_format((float) $supplier->opening_balance, 0) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h3>Account Summary</h3>
            <p class="list-note">Debits increase what you owe the supplier. Payments and supplier returns reduce the balance as credits.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 42%;">Opening Balance</th>
                        <td>{{ $currency }} {{ number_format($summary['opening_balance'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Total Purchases</th>
                        <td>{{ $currency }} {{ number_format($summary['total_purchases'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Payments Made</th>
                        <td>{{ $currency }} {{ number_format($summary['total_payments'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Supplier Returns / Credits</th>
                        <td>{{ $currency }} {{ number_format($summary['total_returns'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Closing Balance</th>
                        <td>{{ $currency }} {{ number_format($summary['closing_balance'], 0) }}</td>
                    </tr>
                </tbody>
            </table>
            <p class="list-note" style="margin-top: 14px;">Use this page during supplier reconciliation, payment planning, and month-end accounts review.</p>
        </div>
    </section>

    <section class="panel">
        <h3>Statement Entries</h3>
        <p class="list-note">Read this like a running supplier account: purchases raise the balance, while payments and supplier returns reduce it.</p>
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
                        <td colspan="7" class="muted">No supplier transactions yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </section>
@endsection
