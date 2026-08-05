@extends('layouts.app', ['title' => 'Owner Report Bundle'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($businessName = config('business.name', 'APPLES OF GOLD WHOLESALERS'))
    @php($formatMoney = fn ($value) => $currency.' '.number_format((float) $value, 0))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0')
    @php($formatPercent = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%')
    @php($bundleQuery = request()->except('export'))
    @include('reports.partials.owner_print_styles')

    <style>
        .bundle-section {
            margin-top: 18px;
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }
        .bundle-cover {
            min-height: 720px;
            display: grid;
            align-content: center;
            gap: 18px;
            text-align: center;
        }
        .bundle-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            padding: 12px;
            text-align: left;
        }
        .bundle-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            background: #fffdfa;
        }
        .bundle-card .label {
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--muted);
            font-size: .72rem;
        }
        .bundle-card .value {
            margin-top: 6px;
            font-weight: 900;
            font-size: 1.08rem;
        }
        .bundle-meta {
            display: inline-grid;
            gap: 6px;
            margin: 0 auto;
            text-align: left;
        }
        .bundle-mini-table {
            overflow: auto;
        }
        .bundle-note {
            margin: 8px 10px 0;
            color: var(--muted);
            font-size: .86rem;
        }
        @media print {
            .bundle-section {
                page-break-before: always;
                border-radius: 0;
                margin-top: 0;
            }
            .bundle-cover {
                page-break-before: auto;
                min-height: 96vh;
            }
            .bundle-card {
                break-inside: avoid;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>Owner Report Bundle</h2>
            <p>Month-end closing pack combining the owner summary, sales, income, margin, daily closing, management pack, and action alerts.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print / Save as PDF</button>
            <a href="{{ route('reports.owner-report-bundle', array_merge($bundleQuery, ['export' => 'csv'])) }}" class="button-link">Export Bundle CSV</a>
            <a href="{{ route('reports.financial-summary', ['date_from' => $fromDate, 'date_to' => $toDate]) }}" class="button-link">Back to Financial Summary</a>
            <a href="{{ route('reports.owner-dashboard', $bundleQuery) }}" class="button-link">Open Owner Dashboard</a>
            <a href="{{ route('reports.monthly-management-pack', $bundleQuery) }}" class="button-link">Open Monthly Management Pack</a>
            <a href="{{ route('reports.daily-closing-pack', $bundleQuery) }}" class="button-link">Open Daily Closing Pack</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <form method="get" class="filters">
            <input type="date" name="date_from" value="{{ $fromDate }}">
            <input type="date" name="date_to" value="{{ $toDate }}">
            <select name="period">
                <option value="">Custom</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
                <option value="month" @selected($period === 'month')>This month</option>
            </select>
            <select name="store_id">
                <option value="0">All Shops</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected((int) $filters['store_id'] === (int) $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="user_id">
                <option value="0">All Cashiers</option>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" @selected((int) $filters['user_id'] === (int) $user->id)>{{ $user->name ?? $user->username }}</option>
                @endforeach
            </select>
            <select name="payment_mode_id">
                <option value="0">All Payment Modes</option>
                @foreach ($paymentModes as $mode)
                    <option value="{{ $mode->id }}" @selected((int) $filters['payment_mode_id'] === (int) $mode->id)>{{ $mode->name }}</option>
                @endforeach
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="owner-report">
        <div class="bundle-cover">
            <div>
                <h1 style="margin:0;">{{ $businessName }}</h1>
                <h2 style="margin:8px 0 0;">Owner Report Bundle</h2>
                <p class="owner-report-meta">Month-End Closing Pack</p>
            </div>
            <div class="bundle-meta">
                <div><strong>Period:</strong> {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</div>
                <div><strong>Store / Shop:</strong> {{ $selectedStore?->name ?? 'All Shops' }}</div>
                <div><strong>Cashier / User:</strong> {{ $selectedUser?->name ?? $selectedUser?->username ?? 'All Cashiers' }}</div>
                <div><strong>Payment Mode:</strong> {{ $selectedPaymentMode?->name ?? 'All Payment Modes' }}</div>
                <div><strong>Generated:</strong> {{ now()->format('d M Y, h:i A') }}</div>
                <div><strong>Prepared by:</strong> Apples Of Gold System</div>
            </div>
            <div class="bundle-summary-grid">
                @foreach ([
                    'Gross Sales' => $coverSummary['gross_sales'],
                    'Returns / Refunds' => $coverSummary['returns'],
                    'Net Sales' => $coverSummary['net_sales'],
                    'Estimated COGS' => $coverSummary['estimated_cogs'],
                    'Estimated Gross Profit' => $coverSummary['estimated_gross_profit'],
                    'Expenses' => $coverSummary['expenses'],
                    'Estimated Net Profit' => $coverSummary['estimated_net_profit'],
                    'Purchases' => $coverSummary['purchases'],
                    'Supplier Credit / Unpaid Purchases' => $coverSummary['supplier_credit'],
                    'Cash Difference' => $coverSummary['cash_difference'],
                ] as $label => $value)
                    <div class="bundle-card">
                        <div class="label">{{ $label }}</div>
                        <div class="value">{{ $formatMoney($value) }}</div>
                    </div>
                @endforeach
                <div class="bundle-card">
                    <div class="label">Health Score</div>
                    <div class="value">{{ $coverSummary['health_score'] }}/100 - {{ $coverSummary['health_label'] }}</div>
                </div>
            </div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Cash Sales / Income Summary</span><span>Summary Cash Sales/Income by Shop Report</span></div>
            @forelse ($cashSales['shopGroups'] as $shopGroup)
                <div class="owner-report-section-title"><span>SHOP: {{ $shopGroup['store_name'] }}</span><span>Total SHOP: {{ $formatMoney($shopGroup['total']) }}</span></div>
                @foreach ($shopGroup['saleGroups'] as $saleGroup)
                    <div class="owner-report-section-title"><span>{{ $saleGroup['label'] }}</span><span>Total {{ $saleGroup['label'] }}: {{ $formatMoney($saleGroup['total']) }}</span></div>
                    <div class="bundle-mini-table">
                        <table>
                            <thead><tr><th>S/N</th><th>Item</th><th>Qty</th><th>Av. rate</th><th>Total Amount</th></tr></thead>
                            <tbody>
                                @foreach ($saleGroup['rows'] as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $row->item_label }}</td>
                                        <td>{{ $formatQty($row->quantity) }}</td>
                                        <td class="money">{{ $formatMoney($row->average_rate) }}</td>
                                        <td class="money">{{ $formatMoney($row->total_amount) }}</td>
                                    </tr>
                                @endforeach
                                <tr class="owner-total-row"><td colspan="4">Total {{ $saleGroup['label'] }}</td><td class="money">{{ $formatMoney($saleGroup['total']) }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                @endforeach
            @empty
                <p class="bundle-note">No posted sales were found for the selected filters.</p>
            @endforelse
            <div class="owner-grand-total"><span>Grand Total</span><span>{{ $formatMoney($cashSales['grandTotal']) }}</span></div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Consolidated Sales Detail</span><span>Consolidated Cash Sales/Income Report</span></div>
            @forelse ($consolidatedSales['shopGroups'] as $shopGroup)
                <div class="owner-report-section-title"><span>SHOP: {{ $shopGroup['store_name'] }}</span><span>Total for Store: {{ $formatMoney($shopGroup['total']) }}</span></div>
                <div class="bundle-mini-table">
                    <table>
                        <thead><tr><th>Date</th><th>Reference / Sale No</th><th>Item</th><th>Qty</th><th>Rate</th><th>Total Amount</th></tr></thead>
                        <tbody>
                            @foreach ($shopGroup['rows'] as $row)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</td>
                                    <td><a href="{{ $row->reference_url }}">{{ $row->reference }}</a></td>
                                    <td>{{ $row->item }}</td>
                                    <td>{{ $formatQty($row->quantity) }}</td>
                                    <td class="money">{{ $formatMoney($row->rate) }}</td>
                                    <td class="money">{{ $formatMoney($row->total_amount) }}</td>
                                </tr>
                            @endforeach
                            <tr class="owner-total-row"><td colspan="5">Total for Store</td><td class="money">{{ $formatMoney($shopGroup['total']) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            @empty
                <p class="bundle-note">No detailed sales were found for the selected filters.</p>
            @endforelse
            <div class="owner-grand-total"><span>Total Cash Collected</span><span>{{ $formatMoney($consolidatedSales['grandTotal']) }}</span></div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Income and Expenditure</span><span>Income and Expenditure by Account</span></div>
            <p class="bundle-note">{{ $incomeExpenditure['bfAvailable'] ? 'B/F: '.$formatMoney($incomeExpenditure['bfAmount']) : 'B/F not available in this system yet' }}</p>
            <div class="bundle-mini-table">
                <table>
                    <thead><tr><th>Date</th><th>Reference</th><th>Section</th><th>Item</th><th>Qty</th><th>Rate</th><th>Income</th><th>Expenditure</th></tr></thead>
                    <tbody>
                        @forelse ($incomeExpenditure['rows'] as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</td>
                                <td>@if ($row->reference_url)<a href="{{ $row->reference_url }}">{{ $row->reference }}</a>@else{{ $row->reference }}@endif</td>
                                <td>{{ $row->section }}</td>
                                <td>{{ $row->item }}</td>
                                <td>{{ $row->quantity === null ? '-' : $formatQty($row->quantity) }}</td>
                                <td class="money">{{ $row->rate === null ? '-' : $formatMoney($row->rate) }}</td>
                                <td class="money">{{ $formatMoney($row->income) }}</td>
                                <td class="money">{{ $formatMoney($row->expenditure) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="muted">No income or expenditure rows matched the selected filters.</td></tr>
                        @endforelse
                        <tr class="owner-total-row"><td colspan="6">Total Income</td><td class="money">{{ $formatMoney($incomeExpenditure['totalIncome']) }}</td><td></td></tr>
                        <tr class="owner-total-row"><td colspan="6">Total Expenditure</td><td></td><td class="money">{{ $formatMoney($incomeExpenditure['totalExpenditure']) }}</td></tr>
                        <tr class="owner-total-row"><td colspan="7">Net Movement / Net Balance</td><td class="money">{{ $formatMoney($incomeExpenditure['netMovement']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Gross Margin Summary</span><span>Consolidated Sales Summary with Gross Margins</span></div>
            <div class="bundle-mini-table">
                <table>
                    <thead><tr><th>Item</th><th>Qty</th><th>Sales Amount</th><th>Cost Amount</th><th>Returns / Adjustment</th><th>Gross Profit</th><th>Gross Profit %</th></tr></thead>
                    <tbody>
                        @forelse ($grossProfit['productRows'] as $row)
                            <tr>
                                <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}">{{ $row->product_name }}</a><div class="muted">{{ $row->warning_label }}</div></td>
                                <td>{{ $formatQty($row->quantity_sold) }}</td>
                                <td class="money">{{ $formatMoney($row->sales_revenue) }}</td>
                                <td class="money">{{ $formatMoney($row->net_estimated_cogs) }}</td>
                                <td class="money">{{ $formatMoney($row->returned_revenue) }}</td>
                                <td class="money">{{ $row->has_reliable_margin ? $formatMoney($row->net_estimated_gross_profit) : 'N/A' }}</td>
                                <td>{{ $row->has_reliable_margin ? $formatPercent($row->net_margin_percent) : 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No margin rows matched the selected filters.</td></tr>
                        @endforelse
                        <tr class="owner-total-row">
                            <td>Total</td>
                            <td>{{ $formatQty($grossProfit['summary']['quantity_sold']) }}</td>
                            <td class="money">{{ $formatMoney($grossProfit['summary']['sales_revenue']) }}</td>
                            <td class="money">{{ $formatMoney($grossProfit['summary']['net_estimated_cogs']) }}</td>
                            <td class="money">{{ $formatMoney($grossProfit['summary']['returned_revenue']) }}</td>
                            <td class="money">{{ $formatMoney($grossProfit['summary']['net_estimated_gross_profit']) }}</td>
                            <td>{{ $formatPercent($grossProfit['summary']['net_margin_percent']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Daily Closing Summary</span><span>Cashier accountability and daily trading</span></div>
            <div class="bundle-summary-grid">
                @foreach ([
                    'Gross Sales' => $dailyClosing['summary']['gross_sales'],
                    'Returns' => $dailyClosing['summary']['returned_sales'],
                    'Net Sales' => $dailyClosing['summary']['net_sales'],
                    'Expenses' => $dailyClosing['summary']['expenses'],
                    'Estimated Net Profit' => $dailyClosing['summary']['estimated_net_profit'],
                    'Cash Difference' => $dailyClosing['summary']['cash_difference'],
                ] as $label => $value)
                    <div class="bundle-card"><div class="label">{{ $label }}</div><div class="value">{{ $formatMoney($value) }}</div></div>
                @endforeach
            </div>
            <div class="grid-two" style="margin:10px;">
                <div class="bundle-mini-table">
                    <table>
                        <thead><tr><th>Payment Mode</th><th>Sales</th><th>Returns</th><th>Net</th></tr></thead>
                        <tbody>
                            @forelse ($dailyClosing['paymentRows'] as $row)
                                <tr><td>{{ $row->payment_mode }}</td><td class="money">{{ $formatMoney($row->total_amount) }}</td><td class="money">{{ $formatMoney($row->returned_amount) }}</td><td class="money">{{ $formatMoney($row->net_amount) }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="muted">No payment rows matched.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="bundle-mini-table">
                    <table>
                        <thead><tr><th>Cashier / User</th><th>Sales</th><th>Expected Cash</th><th>Handover</th><th>Difference</th></tr></thead>
                        <tbody>
                            @forelse ($dailyClosing['cashierRows'] as $row)
                                <tr><td>{{ $row->cashier }}</td><td>{{ number_format($row->sales_count) }}</td><td class="money">{{ $formatMoney($row->expected_cash) }}</td><td class="money">{{ $formatMoney($row->handover_amount) }}</td><td class="money">{{ $formatMoney($row->difference) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="muted">No cashier rows matched.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bundle-mini-table" style="margin:10px;">
                <table>
                    <thead><tr><th>Top Item</th><th>Qty</th><th>Rate</th><th>Sales</th><th>Profit</th><th>Warning</th></tr></thead>
                    <tbody>
                        @forelse ($dailyClosing['topItems'] as $row)
                            <tr><td>{{ $row->item }}</td><td>{{ $formatQty($row->quantity) }}</td><td class="money">{{ $formatMoney($row->average_rate) }}</td><td class="money">{{ $formatMoney($row->sales_amount) }}</td><td class="money">{{ $row->estimated_gross_profit === null ? 'N/A' : $formatMoney($row->estimated_gross_profit) }}</td><td>{{ $row->warning_label }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="muted">No top items matched.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Monthly Management Summary</span><span>Owner decisions and trading direction</span></div>
            <div class="bundle-mini-table">
                <table>
                    <thead><tr><th>Date</th><th>Sales</th><th>Returns</th><th>Net Sales</th><th>Gross Profit</th><th>Expenses</th><th>Net Profit</th><th>Margin %</th></tr></thead>
                    <tbody>
                        @foreach ($monthlyManagement['dailyRows'] as $row)
                            <tr><td><a href="{{ route('sales.index', ['date_from' => $row->date, 'date_to' => $row->date]) }}">{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</a></td><td class="money">{{ $formatMoney($row->sales) }}</td><td class="money">{{ $formatMoney($row->returns) }}</td><td class="money">{{ $formatMoney($row->net_sales) }}</td><td class="money">{{ $formatMoney($row->estimated_gross_profit) }}</td><td class="money">{{ $formatMoney($row->expenses) }}</td><td class="money">{{ $formatMoney($row->estimated_net_profit) }}</td><td>{{ $formatPercent($row->margin_percent) }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid-two" style="margin:10px;">
                <div class="bundle-mini-table">
                    <table>
                        <thead><tr><th>Product Performance</th><th>Revenue</th><th>Profit</th><th>Margin</th><th>Status</th></tr></thead>
                        <tbody>
                            @forelse ($monthlyManagement['topProfitableRows']->take(12) as $row)
                                <tr><td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}">{{ $row->product_name }} - {{ $row->unit_name }}</a></td><td class="money">{{ $formatMoney($row->revenue) }}</td><td class="money">{{ $formatMoney($row->profit) }}</td><td>{{ $formatPercent($row->margin_percent) }}</td><td>{{ $row->status }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="muted">No product performance rows matched.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="bundle-mini-table">
                    <table>
                        <thead><tr><th>Funding Source</th><th>Purchases</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead>
                        <tbody>
                            @forelse ($monthlyManagement['fundingRows'] as $row)
                                <tr><td><a href="{{ route('purchases.index', ['q' => $row->funding_source, 'date_from' => $fromDate, 'date_to' => $toDate]) }}">{{ $row->funding_source }}</a></td><td>{{ number_format($row->purchase_count) }}</td><td class="money">{{ $formatMoney($row->purchase_total) }}</td><td class="money">{{ $formatMoney($row->amount_paid) }}</td><td class="money">{{ $formatMoney($row->balance_due) }}</td></tr>
                            @empty
                                <tr><td colspan="5" class="muted">No purchase funding rows matched.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bundle-mini-table" style="margin:10px;">
                <table>
                    <thead><tr><th>Stock / Margin Alert</th><th>Unit</th><th>Issue</th><th>Suggested Action</th></tr></thead>
                    <tbody>
                        @forelse ($monthlyManagement['alertRows'] as $row)
                            <tr><td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}">{{ $row->product_name }}</a></td><td>{{ $row->unit_name }}</td><td>{{ $row->issue }}</td><td>{{ $row->suggested_action }}</td></tr>
                        @empty
                            <tr><td colspan="4" class="muted">No stock or margin alerts matched.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bundle-section">
            <div class="owner-report-section-title"><span>Owner Dashboard Alerts</span><span>Items needing attention and suggested actions</span></div>
            <p class="bundle-note">Health score: <strong>{{ $ownerDashboard['healthScore']['score'] }}/100 - {{ $ownerDashboard['healthScore']['label'] }}</strong></p>
            <div class="bundle-mini-table">
                <table>
                    <thead><tr><th>Severity</th><th>Issue</th><th>Business Meaning</th><th>Amount / Count</th><th>Suggested Action</th><th>Link</th></tr></thead>
                    <tbody>
                        @foreach ($ownerDashboard['ownerAlerts'] as $row)
                            <tr>
                                <td>{{ ucfirst($row->severity) }}</td>
                                <td><strong>{{ $row->issue }}</strong></td>
                                <td>{{ $row->business_meaning }}</td>
                                <td>{{ $row->amount_label }}</td>
                                <td>{{ $row->suggested_action }}</td>
                                <td><a href="{{ $row->link }}">Open</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.developer_credit')
    </section>
@endsection
