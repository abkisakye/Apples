@extends('layouts.app', ['title' => 'Monthly Management Pack'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0')
    @php($formatMargin = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%')
    @include('reports.partials.owner_print_styles')

    <div class="page-head">
        <div>
            <h2>Monthly Management Pack</h2>
            <p>Business Health, Profit Direction, stock alerts, payment collection, expenses, and purchase funding in one owner report.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button-link">Export CSV</a>
            <a href="{{ route('reports.financial-summary', ['date_from' => $fromDate, 'date_to' => $toDate]) }}" class="button-link">Back to Financial Summary</a>
            <a href="{{ route('reports.product-unit-fix-workbench') }}" class="button-link">Fix Cost & Conversion Issues</a>
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
            <h3>Monthly Management Pack</h3>
            <p class="owner-report-meta">Period: {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Executive Summary</span><span>Business Health</span></div>
            <section class="cards" style="box-shadow:none; margin:10px;">
                <div class="card"><div class="label">Gross Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['gross_sales'], 0) }}</div></div>
                <div class="card"><div class="label">Returns / Refunds</div><div class="value money">{{ $currency }} {{ number_format($summary['returns'], 0) }}</div></div>
                <div class="card"><div class="label">Net Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['net_sales'], 0) }}</div></div>
                <div class="card"><div class="label">Estimated COGS</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_cogs'], 0) }}</div></div>
                <div class="card"><div class="label">Estimated Gross Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_gross_profit'], 0) }}</div></div>
                <div class="card"><div class="label">Expenses</div><div class="value money">{{ $currency }} {{ number_format($summary['expenses'], 0) }}</div></div>
                <div class="card"><div class="label">Estimated Net Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_net_profit'], 0) }}</div></div>
                <div class="card"><div class="label">Overall Margin %</div><div class="value">{{ $formatMargin($summary['overall_margin_percent']) }}</div></div>
                <div class="card"><div class="label">Total Purchases</div><div class="value money">{{ $currency }} {{ number_format($summary['total_purchases'], 0) }}</div></div>
                <div class="card"><div class="label">Total Stock Value</div><div class="value money">{{ $currency }} {{ number_format($summary['total_stock_value'], 0) }}</div></div>
                <div class="card"><div class="label">Missing Cost Count</div><div class="value">{{ number_format($summary['missing_cost_count']) }}</div></div>
                <div class="card"><div class="label">Conversion Review Count</div><div class="value">{{ number_format($summary['conversion_review_count']) }}</div></div>
            </section>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Daily Trend Table</span><span>Profit Direction</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sales</th>
                            <th>Returns</th>
                            <th>Net Sales</th>
                            <th>Estimated Gross Profit</th>
                            <th>Expenses</th>
                            <th>Estimated Net Profit</th>
                            <th>Margin %</th>
                            <th>Number Of Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dailyRows as $row)
                            <tr>
                                <td><a href="{{ route('sales.index', ['date_from' => $row->date, 'date_to' => $row->date]) }}"><strong>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</strong></a></td>
                                <td class="money">{{ $currency }} {{ number_format($row->sales, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->returns, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->net_sales, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->estimated_gross_profit, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->expenses, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->estimated_net_profit, 0) }}</td>
                                <td>{{ $formatMargin($row->margin_percent) }}</td>
                                <td>{{ number_format($row->sales_count) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @php($performanceTables = [
            'Top Profitable Products' => $topProfitableRows,
            'Top Selling Products By Revenue' => $topRevenueRows,
            'Top Selling Products By Quantity' => $topQuantityRows,
            'Low Margin Products' => $lowMarginRows,
            'Missing Cost Products Sold' => $missingCostRows,
        ])
        @foreach ($performanceTables as $title => $rows)
            <div class="owner-report-section">
                <div class="owner-report-section-title"><span>{{ $title }}</span><span>Products Needing Attention</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Product / Unit</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                                <th>Cost</th>
                                <th>Profit</th>
                                <th>Margin %</th>
                                <th>Warning / Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($rows as $row)
                                <tr>
                                    <td>
                                        <a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->product_name }} - {{ $row->unit_name }}</strong></a>
                                        <div class="muted">{{ $row->category_name }}</div>
                                    </td>
                                    <td>{{ $formatQty($row->quantity_sold) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->revenue, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->cost, 0) }}</td>
                                    <td class="money">{{ $row->missing_cost ? 'N/A' : $currency.' '.number_format($row->profit, 0) }}</td>
                                    <td>{{ $formatMargin($row->margin_percent) }}</td>
                                    <td><span class="badge {{ $row->status === 'OK' ? 'success' : 'credit' }}">{{ $row->status }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="muted">No rows matched this section.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Category Performance</span><span>Expenses by category are not directly linked to product categories.</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Net Sales</th>
                            <th>Estimated COGS</th>
                            <th>Estimated Gross Profit</th>
                            <th>Expenses</th>
                            <th>Margin %</th>
                            <th>Products Sold</th>
                            <th>Warning / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categoryRows as $row)
                            <tr>
                                <td><a href="{{ route('products.index', ['category_id' => $row->category_id]) }}"><strong>{{ $row->category_name }}</strong></a></td>
                                <td class="money">{{ $currency }} {{ number_format($row->net_sales_revenue, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->net_estimated_cogs, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->net_estimated_gross_profit, 0) }}</td>
                                <td>Not available</td>
                                <td>{{ $formatMargin($row->net_margin_percent) }}</td>
                                <td>{{ number_format($row->products_sold) }}</td>
                                <td><span class="badge {{ $row->has_reliable_margin ? 'success' : 'credit' }}">{{ $row->warning_label }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="muted">No category performance matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <section class="grid-two" style="margin-top:14px;">
            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Expense Intelligence</span><span>Expense-to-sales: {{ $formatMargin($summary['expense_to_sales_percent']) }}</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Expense Category</th>
                                <th>Count</th>
                                <th>Total Amount</th>
                                <th>% Of Expenses</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($expenseRows as $row)
                                <tr>
                                    <td><a href="{{ route('expenses.index', ['q' => $row->category_name, 'date_from' => $fromDate, 'date_to' => $toDate]) }}"><strong>{{ $row->category_name }}</strong></a></td>
                                    <td>{{ number_format($row->expense_count) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->amount, 0) }}</td>
                                    <td>{{ $formatMargin($row->expense_percent) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">No expenses matched the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Largest Expenses</span><span>Review unusual spend</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Expense</th>
                                <th>Category</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($largestExpenses as $row)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($row->expense_date)->format('d M Y') }}</td>
                                    <td><a href="{{ route('expenses.show', $row->expense_id) }}"><strong>{{ $row->expense_no }}</strong></a></td>
                                    <td>{{ $row->category_name }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->amount, 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="muted">No expenses matched the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="grid-two" style="margin-top:14px;">
            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Payment Collection Summary</span><span>Cash, mobile, bank/card, credit and other modes</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Payment Mode</th>
                                <th>Number Of Sales</th>
                                <th>Gross Amount</th>
                                <th>Returns</th>
                                <th>Net Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paymentRows as $row)
                                <tr>
                                    <td><a href="{{ route('sales.index', ['q' => $row->payment_mode, 'date_from' => $fromDate, 'date_to' => $toDate]) }}"><strong>{{ $row->payment_mode }}</strong></a></td>
                                    <td>{{ number_format($row->transaction_count) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->gross_amount, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->returns_amount, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->net_amount, 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No payment activity matched the selected filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Funding Source Summary</span><span>Purchases And Funding</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Funding Source</th>
                                <th>Purchase Count</th>
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
                                    <td class="money">{{ $currency }} {{ number_format($row->purchase_total, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->amount_paid, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->balance_due, 0) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No posted purchases matched the selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <table>
                    <tbody>
                        <tr><th>Supplier Credit Increase</th><td class="money">{{ $currency }} {{ number_format($summary['supplier_credit_increase'], 0) }}</td></tr>
                        <tr><th>Owner Money Used</th><td class="money">{{ $currency }} {{ number_format($summary['owner_money_used'], 0) }}</td></tr>
                        <tr><th>Loan / Borrowed Money Used</th><td class="money">{{ $currency }} {{ number_format($summary['loan_money_used'], 0) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Stock & Margin Alerts</span><span>Worklist-style warnings</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Unit</th>
                            <th>Issue</th>
                            <th>Suggested Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alertRows as $row)
                            <tr>
                                <td><a href="{{ route('reports.product-unit-fix-workbench', ['q' => $row->product_name, 'unit_name' => $row->unit_name]) }}"><strong>{{ $row->product_name }}</strong></a></td>
                                <td>{{ $row->unit_name }}</td>
                                <td><span class="badge credit">{{ $row->issue }}</span></td>
                                <td>{{ $row->suggested_action }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="muted">No stock or margin alerts matched the current data.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <section class="grid-two" style="margin-top:14px;">
            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Fast Moving</span><span>Watch stock / reorder soon</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                                <th>Suggested Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($fastMovingRows as $row)
                                <tr>
                                    <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->product_name }}</strong></a></td>
                                    <td>{{ $row->unit_name }}</td>
                                    <td>{{ $formatQty($row->quantity_sold) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->revenue, 0) }}</td>
                                    <td>{{ $row->suggested_action }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="muted">No fast-moving rows matched the selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Slow Moving</span><span>Review price / promotion / stock level</span></div>
                <div style="overflow:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Unit / Base Unit</th>
                                <th>Current Stock</th>
                                <th>Qty Sold</th>
                                <th>Revenue</th>
                                <th>Estimated Stock Value</th>
                                <th>Suggested Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($slowMovingRows as $row)
                                <tr>
                                    <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->product_name }}</strong></a></td>
                                    <td>{{ $row->unit_name }}</td>
                                    <td>{{ $row->current_stock }}</td>
                                    <td>{{ $formatQty($row->quantity_sold) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->revenue, 0) }}</td>
                                    <td class="money">{{ $currency }} {{ number_format($row->estimated_stock_value, 0) }}</td>
                                    <td>{{ $row->suggested_action }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="muted">No slow-moving rows matched the selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        @include('partials.developer_credit')
    </section>
@endsection
