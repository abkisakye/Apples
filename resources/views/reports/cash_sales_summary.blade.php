@extends('layouts.app', ['title' => 'Cash Sales Summary'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0')
    @include('reports.partials.owner_print_styles')

    <div class="page-head">
        <div>
            <h2>Cash Sales Summary</h2>
            <p>Old-style owner report showing item sales by shop and payment type.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button-link">Export CSV</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Back to Report</a>
            <a href="{{ route('reports.consolidated-sales-detail', request()->query()) }}" class="button-link">View Details</a>
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
                <option value="0">All Payment Modes</option>
                @foreach ($paymentModes as $mode)
                    <option value="{{ $mode->id }}" @selected((int) $filters['payment_mode_id'] === (int) $mode->id)>{{ $mode->name }}</option>
                @endforeach
            </select>
            <select name="sale_type">
                <option value="all" @selected(($filters['sale_type'] ?: 'all') === 'all')>All Sale Types</option>
                <option value="cash" @selected($filters['sale_type'] === 'cash')>Cash Sale</option>
                <option value="credit" @selected($filters['sale_type'] === 'credit')>Credit Sale</option>
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="owner-report">
        <div class="owner-report-head">
            <h2>APPLES OF GOLD WHOLESALERS</h2>
            <h3>Summary Cash Sales/Income by Shop Report</h3>
            <p class="owner-report-meta">Period: {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        @forelse ($shopGroups as $shopGroup)
            <div class="owner-report-section">
                <div class="owner-report-section-title">
                    <span>SHOP: {{ $shopGroup['store_name'] }}</span>
                    <span>Total Shop: {{ $currency }} {{ number_format($shopGroup['total'], 0) }}</span>
                </div>

                @foreach ($shopGroup['saleGroups'] as $saleGroup)
                    <div class="owner-report-section-title">
                        <span>{{ $saleGroup['label'] }}</span>
                        <span>Total {{ $saleGroup['label'] }}: {{ $currency }} {{ number_format($saleGroup['total'], 0) }}</span>
                    </div>
                    <div style="overflow:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:58px;">S/N</th>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Av. rate</th>
                                    <th>Total Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($saleGroup['rows'] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $row->item_label }}</td>
                                        <td class="money">{{ $formatQty($row->quantity) }}</td>
                                        <td class="money">{{ $currency }} {{ number_format((float) $row->average_rate, 0) }}</td>
                                        <td class="money">{{ $currency }} {{ number_format((float) $row->total_amount, 0) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="owner-total-row">
                                    <td colspan="4">Total {{ $saleGroup['label'] }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($saleGroup['total'], 0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="bill-empty">No posted sales were found for the selected filters.</div>
        @endforelse

        <div class="owner-grand-total">
            <span>Grand Total</span>
            <span class="money">{{ $currency }} {{ number_format($grandTotal, 0) }}</span>
        </div>

        @include('partials.developer_credit')
    </section>
@endsection
