@extends('layouts.app', ['title' => 'Income & Expenditure'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => $value === null ? '-' : (rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0'))
    @include('reports.partials.owner_print_styles')

    <div class="page-head">
        <div>
            <h2>Income & Expenditure</h2>
            <p>Account-style report comparing posted sales income against posted cash expenses.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button-link">Export CSV</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Back to Report</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <form method="get" class="filters">
            <input type="date" name="date_from" value="{{ $fromDate }}">
            <input type="date" name="date_to" value="{{ $toDate }}">
            <select name="store_id">
                <option value="0">All Shops</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected((int) $filters['store_id'] === (int) $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="payment_mode_id">
                <option value="0">All Accounts / Payment Modes</option>
                @foreach ($paymentModes as $mode)
                    <option value="{{ $mode->id }}" @selected((int) $filters['payment_mode_id'] === (int) $mode->id)>{{ $mode->name }}</option>
                @endforeach
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="owner-report">
        <div class="owner-report-head">
            <h2>APPLES OF GOLD WHOLESALERS</h2>
            <h3>Income and Expenditure by Account Detailed Report</h3>
            <p class="owner-report-meta">Period: {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title">
                <span>B/F</span>
                <span>{{ $bfAvailable ? $currency.' '.number_format($bfAmount, 0) : 'B/F not available in this system yet' }}</span>
            </div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Reference</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Rate</th>
                            <th>Income</th>
                            <th>Expenditure</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</td>
                                <td>
                                    @if ($row->reference_url)
                                        <a href="{{ $row->reference_url }}">{{ $row->reference }}</a>
                                    @else
                                        {{ $row->reference }}
                                    @endif
                                    <div class="muted">{{ $row->section }}</div>
                                </td>
                                <td>{{ $row->item }}</td>
                                <td class="money">{{ $formatQty($row->quantity) }}</td>
                                <td class="money">{{ $row->rate === null ? '-' : $currency.' '.number_format((float) $row->rate, 0) }}</td>
                                <td class="money">{{ $row->income > 0 ? $currency.' '.number_format($row->income, 0) : '-' }}</td>
                                <td class="money">{{ $row->expenditure > 0 ? $currency.' '.number_format($row->expenditure, 0) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No income or expenditure rows matched the selected filters.</td></tr>
                        @endforelse
                        <tr class="owner-total-row">
                            <td colspan="5">Total Income</td>
                            <td class="money">{{ $currency }} {{ number_format($totalIncome, 0) }}</td>
                            <td></td>
                        </tr>
                        <tr class="owner-total-row">
                            <td colspan="6">Total Expenditure</td>
                            <td class="money">{{ $currency }} {{ number_format($totalExpenditure, 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="owner-grand-total">
            <span>{{ $bfAvailable ? 'Net Balance' : 'Net Movement' }}</span>
            <span class="money">{{ $currency }} {{ number_format($netMovement, 0) }}</span>
        </div>

        @include('partials.developer_credit')
    </section>
@endsection
