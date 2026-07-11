@extends('layouts.app', ['title' => 'Daily Sales Summary'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.'))
    <style>
        .sales-summary-title {
            text-align: center;
            margin-bottom: 14px;
        }
        .sales-summary-title h2,
        .sales-summary-title h3 {
            margin: 0;
        }
        .sales-summary-title h2 {
            font-size: 1.28rem;
            letter-spacing: .08em;
        }
        .sales-summary-title h3 {
            margin-top: 5px;
            font-size: 1rem;
            color: var(--muted);
        }
        .sales-report-group {
            margin-top: 14px;
            border: 1px solid var(--line);
            border-radius: 14px;
            overflow: hidden;
            background: #fff;
        }
        .sales-report-group-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 10px 12px;
            background: var(--brand-soft);
            color: var(--brand-strong);
            font-weight: 800;
        }
        .sales-report-subhead {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            padding: 8px 12px;
            background: var(--panel-soft);
            border-top: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            font-weight: 800;
        }
        .sales-report-total-row {
            background: #fbf8ef;
            font-weight: 800;
        }
        .sales-grand-total {
            margin-top: 14px;
            display: flex;
            justify-content: flex-end;
            gap: 14px;
            align-items: center;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: linear-gradient(135deg, var(--brand-soft), var(--accent-soft));
            font-weight: 900;
        }
        @media print {
            .sidebar,
            .topbar,
            .page-head .actions,
            .filters,
            .sales-summary-actions {
                display: none !important;
            }
            .shell {
                display: block;
            }
            .workspace {
                padding: 0;
            }
            .page {
                max-width: none;
            }
            .panel,
            .sales-report-group,
            .sales-grand-total {
                box-shadow: none;
            }
            body {
                background: #fff;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>Daily Sales Summary</h2>
            <p>Owner-facing item sales summary by shop and sale/payment type for the selected period.</p>
        </div>
        <div class="actions sales-summary-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ route('reports.daily-sales-summary.export', request()->query()) }}" class="button-link">Export Excel</a>
            <a href="{{ route('reports.daily-closing') }}" class="button-link">Daily Closing</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom: 16px;">
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
            <select name="status">
                <option value="posted" @selected($filters['status'] === 'posted')>Posted Only</option>
                <option value="all" @selected($filters['status'] === 'all')>All Non-void Sales</option>
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="panel">
        <div class="sales-summary-title">
            <h2>APPLES OF GOLD WHOLESALERS</h2>
            <h3>Summary Cash Sales/Income by Shop Report</h3>
            <p class="list-note">{{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        @forelse ($shopGroups as $shopGroup)
            <div class="sales-report-group">
                <div class="sales-report-group-head">
                    <span>SHOP: {{ $shopGroup['store_name'] }}</span>
                    <span class="money">Shop Total: {{ $currency }} {{ number_format($shopGroup['total'], 0) }}</span>
                </div>

                @foreach ($shopGroup['saleGroups'] as $saleGroup)
                    <div class="sales-report-subhead">
                        <span>{{ $saleGroup['label'] }}</span>
                        <span class="money">Total {{ $saleGroup['label'] }}: {{ $currency }} {{ number_format($saleGroup['total'], 0) }}</span>
                    </div>
                    <div class="table-wrap table-mobile-friendly">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width:64px;">S/N</th>
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
                                        <td><div class="table-title">{{ $row->item_label }}</div></td>
                                        <td class="money">{{ $formatQty($row->quantity) }}</td>
                                        <td class="money">{{ $currency }} {{ number_format((float) $row->average_rate, 0) }}</td>
                                        <td class="money">{{ $currency }} {{ number_format((float) $row->total_amount, 0) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="sales-report-total-row">
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

        <div class="sales-grand-total">
            <span>Grand Total</span>
            <span class="money">{{ $currency }} {{ number_format($grandTotal, 0) }}</span>
        </div>

        @include('partials.developer_credit')
    </section>
@endsection
