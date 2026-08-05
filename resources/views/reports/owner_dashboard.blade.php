@extends('layouts.app', ['title' => 'Owner Dashboard'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatMoney = fn ($value) => $currency.' '.number_format((float) $value, 0))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0')
    @php($formatPercent = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%')
    @php($dashboardExportQuery = array_merge(request()->except('export'), ['export' => 'csv']))
    @php($alertsExportQuery = array_merge(request()->except('export'), ['export' => 'alerts_csv']))
    @include('reports.partials.owner_print_styles')

    <style>
        .owner-health-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            padding: 10px;
        }
        .owner-health-card {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px;
            background: #fff;
        }
        .owner-health-card .label,
        .owner-alert-severity {
            text-transform: uppercase;
            letter-spacing: .05em;
            font-size: .7rem;
            color: var(--muted);
        }
        .owner-health-card .value {
            margin-top: 6px;
            font-size: 1.16rem;
            font-weight: 900;
        }
        .owner-status-good {
            border-color: #b9dec4;
            background: #f3fbf5;
        }
        .owner-status-watch {
            border-color: #ead48c;
            background: #fffaf0;
        }
        .owner-status-danger {
            border-color: #e3b6b6;
            background: #fff5f5;
        }
        .owner-score {
            display: grid;
            grid-template-columns: 160px 1fr;
            gap: 14px;
            align-items: center;
            padding: 12px;
        }
        .owner-score-number {
            border-radius: 14px;
            padding: 18px;
            text-align: center;
            font-size: 2.1rem;
            font-weight: 950;
        }
        .owner-chart-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 14px;
            margin-top: 14px;
        }
        .owner-chart {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: #fff;
            overflow: hidden;
        }
        .owner-chart h3 {
            margin: 0;
            padding: 10px 12px;
            font-size: .96rem;
            background: #fbf8ef;
            border-bottom: 1px solid var(--line);
        }
        .owner-chart-body {
            padding: 10px 12px;
        }
        .owner-chart-row {
            display: grid;
            grid-template-columns: minmax(92px, 150px) 1fr auto;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
            font-size: .82rem;
        }
        .owner-bar-track {
            min-width: 120px;
            height: 10px;
            overflow: hidden;
            border-radius: 999px;
            background: #f3efe3;
        }
        .owner-bar {
            height: 100%;
            border-radius: 999px;
            background: var(--brand);
        }
        .owner-bar.expense {
            background: #c49a22;
        }
        .owner-bar.profit {
            background: #2f7d4f;
        }
        .owner-bar.loss {
            background: #b94b4b;
        }
        .owner-trend-bars {
            display: grid;
            gap: 3px;
        }
        .owner-alert-chip {
            display: inline-flex;
            border-radius: 999px;
            padding: 4px 8px;
            font-weight: 900;
            font-size: .72rem;
            border: 1px solid var(--line);
        }
        @media (max-width: 760px) {
            .owner-score,
            .owner-chart-row {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="page-head">
        <div>
            <h2>Owner Dashboard / Business Health Dashboard</h2>
            <p>Visual owner report for profit, expenses, stock warnings, cash differences, and purchase funding.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ route('reports.owner-dashboard', $dashboardExportQuery) }}" class="button-link">Export Dashboard CSV</a>
            <a href="{{ route('reports.owner-dashboard', $alertsExportQuery) }}" class="button-link">Export Alerts CSV</a>
            <a href="{{ route('reports.owner-report-bundle', request()->except('export')) }}" class="button-link">Print Owner Pack</a>
            <a href="{{ route('reports.financial-summary', ['date_from' => $fromDate, 'date_to' => $toDate]) }}" class="button-link">Back to Financial Summary</a>
            <a href="{{ route('reports.monthly-management-pack', request()->except('export')) }}" class="button-link">Open Monthly Management Pack</a>
            <a href="{{ route('reports.daily-closing-pack', request()->except('export')) }}" class="button-link">Open Daily Closing Pack</a>
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
            <select name="category_id">
                <option value="0">All Categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === (int) $category->id)>{{ $category->name }}</option>
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
        <div class="owner-report-head">
            <h2>APPLES OF GOLD WHOLESALERS</h2>
            <h3>Owner Dashboard / Business Health Dashboard</h3>
            <p class="owner-report-meta">Period: {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Owner Health Score</span><span>{{ $healthScore['label'] }}</span></div>
            <div class="owner-score">
                <div class="owner-score-number owner-status-{{ $healthScore['status'] }}">{{ $healthScore['score'] }}/100</div>
                <div>
                    <p style="margin-top:0;"><strong>{{ $healthScore['label'] }}</strong></p>
                    <p class="list-note">Score starts at 100 and drops for missing costs, selling below cost, low margin, negative net profit, high expenses, cash differences, supplier credit, high returns, and loan-funded purchases.</p>
                </div>
            </div>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Owner Health Summary Cards</span><span>Good / Watch / Danger</span></div>
            <div class="owner-health-grid">
                @foreach ($healthCards as $card)
                    <div class="owner-health-card owner-status-{{ $card->status }}">
                        <div class="label">{{ $card->label }}</div>
                        <div class="value">
                            @if ($card->type === 'money')
                                {{ $formatMoney($card->value) }}
                            @elseif ($card->type === 'percent')
                                {{ $formatPercent($card->value) }}
                            @else
                                {{ number_format((float) $card->value, 0) }}
                            @endif
                        </div>
                        <p class="list-note" style="margin:6px 0 0;">{{ ucfirst($card->status) }}. {{ $card->helper }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="owner-chart-grid">
            <div class="owner-chart">
                <h3>Sales vs Expenses vs Estimated Net Profit Trend</h3>
                <div class="owner-chart-body">
                    @forelse ($dailyRows as $row)
                        <div class="owner-chart-row">
                            <strong>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M') }}</strong>
                            <div class="owner-trend-bars">
                                <div class="owner-bar-track" title="Net sales {{ $formatMoney($row->net_sales) }}"><div class="owner-bar" style="width:{{ min(100, ((float) $row->net_sales / $chartMaxes['trend']) * 100) }}%"></div></div>
                                <div class="owner-bar-track" title="Expenses {{ $formatMoney($row->expenses) }}"><div class="owner-bar expense" style="width:{{ min(100, ((float) $row->expenses / $chartMaxes['trend']) * 100) }}%"></div></div>
                                <div class="owner-bar-track" title="Estimated net profit {{ $formatMoney($row->estimated_net_profit) }}"><div class="owner-bar {{ (float) $row->estimated_net_profit < 0 ? 'loss' : 'profit' }}" style="width:{{ min(100, (abs((float) $row->estimated_net_profit) / $chartMaxes['trend']) * 100) }}%"></div></div>
                            </div>
                            <span>{{ $formatMoney($row->estimated_net_profit) }}</span>
                        </div>
                    @empty
                        <p class="muted">No trend rows matched this period.</p>
                    @endforelse
                    <p class="list-note">Green: net sales. Gold: expenses. Dark green/red: estimated net profit or loss.</p>
                </div>
            </div>

            <div class="owner-chart">
                <h3>Net Sales by Payment Mode</h3>
                <div class="owner-chart-body">
                    @forelse ($paymentRows as $row)
                        <div class="owner-chart-row">
                            <strong>{{ $row->payment_mode }}</strong>
                            <div class="owner-bar-track"><div class="owner-bar" style="width:{{ min(100, ((float) $row->net_amount / $chartMaxes['payment']) * 100) }}%"></div></div>
                            <span>{{ $formatMoney($row->net_amount) }}</span>
                        </div>
                    @empty
                        <p class="muted">No payment-mode sales matched this period.</p>
                    @endforelse
                </div>
            </div>

            <div class="owner-chart">
                <h3>Top 10 Products by Profit</h3>
                <div class="owner-chart-body">
                    @forelse ($topProfitableRows->take(10) as $row)
                        <div class="owner-chart-row">
                            <strong>{{ $row->product_name }} - {{ $row->unit_name }}</strong>
                            <div class="owner-bar-track"><div class="owner-bar profit" style="width:{{ min(100, ((float) $row->profit / $chartMaxes['profit']) * 100) }}%"></div></div>
                            <span>{{ $formatMoney($row->profit) }}</span>
                        </div>
                    @empty
                        <p class="muted">No profitable product rows matched this period.</p>
                    @endforelse
                </div>
            </div>

            <div class="owner-chart">
                <h3>Top 10 Products by Revenue</h3>
                <div class="owner-chart-body">
                    @forelse ($topRevenueRows->take(10) as $row)
                        <div class="owner-chart-row">
                            <strong>{{ $row->product_name }} - {{ $row->unit_name }}</strong>
                            <div class="owner-bar-track"><div class="owner-bar" style="width:{{ min(100, ((float) $row->revenue / $chartMaxes['revenue']) * 100) }}%"></div></div>
                            <span>{{ $formatMoney($row->revenue) }}</span>
                        </div>
                    @empty
                        <p class="muted">No product revenue rows matched this period.</p>
                    @endforelse
                </div>
            </div>

            <div class="owner-chart">
                <h3>Expense Breakdown</h3>
                <div class="owner-chart-body">
                    @forelse ($expenseRows as $row)
                        <div class="owner-chart-row">
                            <strong>{{ $row->category_name }}</strong>
                            <div class="owner-bar-track"><div class="owner-bar expense" style="width:{{ min(100, ((float) $row->amount / $chartMaxes['expenses']) * 100) }}%"></div></div>
                            <span>{{ $formatMoney($row->amount) }}</span>
                        </div>
                    @empty
                        <p class="muted">No expenses matched this period.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Owner Alerts Panel</span><span>Action list</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Severity</th>
                            <th>Issue</th>
                            <th>Business Meaning</th>
                            <th>Amount / Count</th>
                            <th>Suggested Action</th>
                            <th>Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($ownerAlerts as $row)
                            <tr>
                                <td><span class="owner-alert-chip owner-status-{{ $row->severity }}">{{ ucfirst($row->severity) }}</span></td>
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

        <section class="grid-two" style="margin-top:14px;">
            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Daily Trend Table</span><span>Values behind chart</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Net Sales</th>
                                <th>Expenses</th>
                                <th>Estimated Net Profit</th>
                                <th>Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dailyRows as $row)
                                <tr>
                                    <td><a href="{{ route('sales.index', ['date_from' => $row->date, 'date_to' => $row->date]) }}">{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</a></td>
                                    <td class="money">{{ $formatMoney($row->net_sales) }}</td>
                                    <td class="money">{{ $formatMoney($row->expenses) }}</td>
                                    <td class="money">{{ $formatMoney($row->estimated_net_profit) }}</td>
                                    <td>{{ $formatPercent($row->margin_percent) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Purchase Funding Source Summary</span><span>Cash pressure and supplier credit</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Funding Source</th>
                                <th>Purchases</th>
                                <th>Purchase Total</th>
                                <th>Amount Paid</th>
                                <th>Balance / Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($fundingRows as $row)
                                <tr>
                                    <td><a href="{{ route('purchases.index', ['q' => $row->funding_source, 'date_from' => $fromDate, 'date_to' => $toDate]) }}"><strong>{{ $row->funding_source }}</strong></a></td>
                                    <td>{{ number_format($row->purchase_count) }}</td>
                                    <td class="money">{{ $formatMoney($row->purchase_total) }}</td>
                                    <td class="money">{{ $formatMoney($row->amount_paid) }}</td>
                                    <td class="money">{{ $formatMoney($row->balance_due) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No posted purchases matched the selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="grid-two" style="margin-top:14px;">
            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Profit by Product after Returns</span><span>Top rows</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Product / Unit</th>
                                <th>Net Revenue</th>
                                <th>Net Cost</th>
                                <th>Net Profit</th>
                                <th>Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topRevenueRows->take(10) as $row)
                                <tr>
                                    <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->product_name }} - {{ $row->unit_name }}</strong></a></td>
                                    <td class="money">{{ $formatMoney($row->net_revenue) }}</td>
                                    <td class="money">{{ $formatMoney($row->cost) }}</td>
                                    <td class="money">{{ $row->missing_cost ? 'N/A' : $formatMoney($row->profit) }}</td>
                                    <td>{{ $formatPercent($row->margin_percent) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No product performance matched this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Profit by Category after Returns</span><span>Category view</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Net Sales</th>
                                <th>Net Cost</th>
                                <th>Net Profit</th>
                                <th>Margin %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($categoryRows as $row)
                                <tr>
                                    <td><a href="{{ route('products.index', ['category_id' => $row->category_id]) }}"><strong>{{ $row->category_name }}</strong></a></td>
                                    <td class="money">{{ $formatMoney($row->net_sales_revenue) }}</td>
                                    <td class="money">{{ $formatMoney($row->net_estimated_cogs) }}</td>
                                    <td class="money">{{ $formatMoney($row->net_estimated_gross_profit) }}</td>
                                    <td>{{ $formatPercent($row->net_margin_percent) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No category rows matched this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @include('partials.developer_credit')
    </section>
@endsection
