@extends('layouts.app', ['title' => 'Estimated Gross Profit'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0')
    @php($formatMargin = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%')
    <div class="page-head">
        <div>
            <h2>Estimated Gross Profit</h2>
            <p>Compare posted sales revenue against estimated cost of goods by product, category, and date.</p>
        </div>
        <div class="actions">
            <a href="{{ route('reports.financial-summary', ['date_from' => $fromDate, 'date_to' => $toDate]) }}" class="button-link">Financial Summary</a>
            <a href="{{ route('reports.daily-sales-summary', ['date_from' => $fromDate, 'date_to' => $toDate]) }}" class="button-link">Daily Sales Summary</a>
            <a href="{{ route('reports.price-margins') }}" class="button-link">Cost vs Selling Price</a>
        </div>
    </div>

    <section class="panel" style="margin-bottom:16px;">
        <p class="list-note"><strong>Estimated report.</strong> These figures are estimated where sale cost snapshots are not available. Review missing cost prices for more accurate profit reporting.</p>
        <form method="get" class="filters" style="margin-top:12px;">
            <input type="date" name="date_from" value="{{ $fromDate }}">
            <input type="date" name="date_to" value="{{ $toDate }}">
            <select name="period">
                <option value="">Custom</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
                <option value="month" @selected($period === 'month')>This month</option>
            </select>
            <select name="store_id">
                <option value="">All stores</option>
                @foreach ($stores as $store)
                    <option value="{{ $store->id }}" @selected((int) $filters['store_id'] === $store->id)>{{ $store->name }}</option>
                @endforeach
            </select>
            <select name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) $filters['category_id'] === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            <input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Search product, code, category, or unit">
            <select name="cost_status">
                <option value="all" @selected($filters['cost_status'] === 'all')>All cost statuses</option>
                <option value="has_cost" @selected($filters['cost_status'] === 'has_cost')>Has cost</option>
                <option value="missing_cost" @selected($filters['cost_status'] === 'missing_cost')>Missing cost</option>
                <option value="estimated_cost" @selected($filters['cost_status'] === 'estimated_cost')>Estimated cost</option>
            </select>
            <button type="submit">Apply</button>
        </form>
    </section>

    <section class="cards">
        <div class="card"><div class="label">Gross Sales Revenue</div><div class="value money">{{ $currency }} {{ number_format($summary['sales_revenue'], 0) }}</div></div>
        <div class="card"><div class="label">Returned / Refunded Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['returned_revenue'], 0) }}</div></div>
        <div class="card"><div class="label">Net Sales Revenue</div><div class="value money">{{ $currency }} {{ number_format($summary['net_sales_revenue'], 0) }}</div></div>
        <div class="card"><div class="label">Gross Estimated COGS</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_cogs'], 0) }}</div></div>
        <div class="card"><div class="label">Estimated Returned COGS</div><div class="value money">{{ $currency }} {{ number_format($summary['returned_cogs'], 0) }}</div></div>
        <div class="card"><div class="label">Net Estimated COGS</div><div class="value money">{{ $currency }} {{ number_format($summary['net_estimated_cogs'], 0) }}</div></div>
        <div class="card"><div class="label">Net Estimated Gross Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['net_estimated_gross_profit'], 0) }}</div></div>
        <div class="card"><div class="label">Net Margin %</div><div class="value">{{ $formatMargin($summary['net_margin_percent']) }}</div></div>
        <div class="card"><div class="label">Expenses</div><div class="value money">{{ $currency }} {{ number_format($summary['expense_total'], 0) }}</div></div>
        <div class="card"><div class="label">Estimated Net Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_net_profit'], 0) }}</div></div>
        <div class="card"><div class="label">Number Of Sales</div><div class="value">{{ number_format($summary['sales_count']) }}</div></div>
        <div class="card"><div class="label">Qty Sold / Returned</div><div class="value">{{ $formatQty($summary['quantity_sold']) }} / {{ $formatQty($summary['quantity_returned']) }}</div></div>
        <div class="card"><div class="label">Lines Missing Cost</div><div class="value">{{ number_format($summary['missing_cost_lines']) }}</div></div>
        <div class="card"><div class="label">Revenue Affected By Missing Cost</div><div class="value money">{{ $currency }} {{ number_format($summary['missing_cost_revenue'], 0) }}</div></div>
        <div class="card"><div class="label">Top Profit Product</div><div class="value" style="font-size:18px;">{{ $summary['top_profit_product'] }}</div></div>
        <div class="card"><div class="label">Top Profit Category</div><div class="value" style="font-size:18px;">{{ $summary['top_profit_category'] }}</div></div>
    </section>

    <section class="panel" style="margin-bottom:16px;">
        <h3>Sales Revenue vs Returns vs Cost Of Goods</h3>
        <div style="overflow:auto; margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Date Range / Summary</th>
                        <th>Gross Sales</th>
                        <th>Returns</th>
                        <th>Net Sales</th>
                        <th>Gross COGS</th>
                        <th>Returned COGS</th>
                        <th>Net COGS</th>
                        <th>Net Gross Profit</th>
                        <th>Net Margin %</th>
                        <th>Missing-Cost Lines</th>
                        <th>Warning / Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summaryRows as $row)
                        <tr>
                            <td>{{ $row->label }}</td>
                            <td>{{ $currency }} {{ number_format($row->sales_revenue, 0) }}</td>
                            <td>{{ $currency }} {{ number_format($row->returned_revenue, 0) }}</td>
                            <td><strong>{{ $currency }} {{ number_format($row->net_sales_revenue, 0) }}</strong></td>
                            <td>{{ $currency }} {{ number_format($row->estimated_cogs, 0) }}</td>
                            <td>{{ $currency }} {{ number_format($row->returned_cogs, 0) }}</td>
                            <td>{{ $currency }} {{ number_format($row->net_estimated_cogs, 0) }}</td>
                            <td><strong>{{ $currency }} {{ number_format($row->net_estimated_gross_profit, 0) }}</strong></td>
                            <td>{{ $formatMargin($row->estimated_margin_percent) }}</td>
                            <td>{{ number_format($row->missing_cost_lines) }}</td>
                            <td><span class="badge {{ $row->warning_label === 'OK' ? 'success' : 'credit' }}">{{ $row->warning_label }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel" style="margin-bottom:16px;">
        <h3>Profit By Product After Returns</h3>
        <div style="overflow:auto; margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Quantity Sold</th>
                        <th>Returned Qty</th>
                        <th>Gross Sales</th>
                        <th>Returns</th>
                        <th>Net Sales</th>
                        <th>Net Estimated COGS</th>
                        <th>Net Estimated Gross Profit</th>
                        <th>Net Margin %</th>
                        <th>Missing-Cost Lines</th>
                        <th>Warning / Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($productRows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row->product_name }}</strong>
                                <div class="muted">{{ $row->product_code ?: 'No code' }}</div>
                            </td>
                            <td>{{ $row->category_name }}</td>
                            <td>{{ $formatQty($row->quantity_sold) }}</td>
                            <td>{{ $formatQty($row->quantity_returned) }}</td>
                            <td>{{ $currency }} {{ number_format($row->sales_revenue, 0) }}</td>
                            <td>{{ $currency }} {{ number_format($row->returned_revenue, 0) }}</td>
                            <td><strong>{{ $currency }} {{ number_format($row->net_sales_revenue, 0) }}</strong></td>
                            <td>{{ $currency }} {{ number_format($row->net_estimated_cogs, 0) }}</td>
                            <td><strong>{{ $currency }} {{ number_format($row->net_estimated_gross_profit, 0) }}</strong></td>
                            <td>{{ $formatMargin($row->net_margin_percent) }}</td>
                            <td>{{ number_format($row->missing_cost_lines) }}</td>
                            <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}" class="badge {{ $row->has_reliable_margin ? 'success' : 'credit' }}" title="Open product unit setup">{{ $row->warning_label }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="muted">No product profit rows matched the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Profit By Category After Returns</h3>
            <div style="overflow:auto; margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Category</th>
                            <th>Products Sold</th>
                            <th>Quantity Sold</th>
                            <th>Returned Qty</th>
                            <th>Gross Sales</th>
                            <th>Returns</th>
                            <th>Net Sales</th>
                            <th>Net COGS</th>
                            <th>Net Gross Profit</th>
                            <th>Net Margin %</th>
                            <th>Missing-Cost Lines</th>
                            <th>Warning / Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categoryRows as $row)
                            <tr>
                                <td><strong>{{ $row->category_name }}</strong></td>
                                <td>{{ number_format($row->products_sold) }}</td>
                                <td>{{ $formatQty($row->quantity_sold) }}</td>
                                <td>{{ $formatQty($row->quantity_returned) }}</td>
                                <td>{{ $currency }} {{ number_format($row->sales_revenue, 0) }}</td>
                                <td>{{ $currency }} {{ number_format($row->returned_revenue, 0) }}</td>
                                <td><strong>{{ $currency }} {{ number_format($row->net_sales_revenue, 0) }}</strong></td>
                                <td>{{ $currency }} {{ number_format($row->net_estimated_cogs, 0) }}</td>
                                <td><strong>{{ $currency }} {{ number_format($row->net_estimated_gross_profit, 0) }}</strong></td>
                                <td>{{ $formatMargin($row->net_margin_percent) }}</td>
                                <td>{{ number_format($row->missing_cost_lines) }}</td>
                                <td><span class="badge {{ $row->has_reliable_margin ? 'success' : 'credit' }}">{{ $row->warning_label }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="muted">No category profit rows matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Daily Profit After Returns</h3>
            <div style="overflow:auto; margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Number Of Sales</th>
                            <th>Gross Sales</th>
                            <th>Returns</th>
                            <th>Net Sales</th>
                            <th>Net COGS</th>
                            <th>Net Gross Profit</th>
                            <th>Net Margin %</th>
                            <th>Missing-Cost Lines</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($dailyRows as $row)
                            <tr>
                                <td>{{ \Illuminate\Support\Carbon::parse($row->date)->format('d M Y') }}</td>
                                <td>{{ number_format($row->sales_count) }}</td>
                                <td>{{ $currency }} {{ number_format($row->sales_revenue, 0) }}</td>
                                <td>{{ $currency }} {{ number_format($row->returned_revenue, 0) }}</td>
                                <td><strong>{{ $currency }} {{ number_format($row->net_sales_revenue, 0) }}</strong></td>
                                <td>{{ $currency }} {{ number_format($row->net_estimated_cogs, 0) }}</td>
                                <td><strong>{{ $currency }} {{ number_format($row->net_estimated_gross_profit, 0) }}</strong></td>
                                <td>{{ $formatMargin($row->net_margin_percent) }}</td>
                                <td>{{ number_format($row->missing_cost_lines) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="muted">No daily profit rows matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="grid-two" style="margin-top:16px;">
        <div class="panel">
            <h3>Expenses Vs Gross Profit</h3>
            <p class="list-note">Estimated net profit subtracts posted expenses in the selected date/store range from net estimated gross profit.</p>
            <div style="overflow:auto; margin-top:12px;">
                <table>
                    <thead>
                        <tr>
                            <th>Expense Category</th>
                            <th>Entries</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenseRows as $row)
                            <tr>
                                <td><strong>{{ $row->category_name }}</strong></td>
                                <td>{{ number_format($row->expense_count) }}</td>
                                <td>{{ $currency }} {{ number_format($row->amount, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="muted">No posted expenses matched the selected date/store range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="panel">
            <h3>Purchase Funding Source Summary</h3>
            <p class="list-note">Posted purchases grouped by where the purchase money came from.</p>
            <div style="overflow:auto; margin-top:12px;">
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
                                <td><strong>{{ $row->funding_source }}</strong></td>
                                <td>{{ number_format($row->purchase_count) }}</td>
                                <td>{{ $currency }} {{ number_format($row->purchase_total, 0) }}</td>
                                <td>{{ $currency }} {{ number_format($row->amount_paid, 0) }}</td>
                                <td>{{ $currency }} {{ number_format($row->balance_due, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No posted purchases matched the selected date/store range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="panel" style="margin-top:16px;">
        <h3>Missing Cost Sales</h3>
        <p class="list-note">These sale lines need cost review before their profit can be trusted.</p>
        <div style="overflow:auto; margin-top:12px;">
            <table>
                <thead>
                    <tr>
                        <th>Sale No</th>
                        <th>Sale Date</th>
                        <th>Product</th>
                        <th>Unit / Pack</th>
                        <th>Quantity Sold</th>
                        <th>Sales Revenue</th>
                        <th>Current Cost Price</th>
                        <th>Warning</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($missingCostRows as $row)
                        <tr>
                            <td>{{ $row->sale_no }}</td>
                            <td>{{ $row->sale_date }}</td>
                            <td><strong>{{ $row->product_name }}</strong></td>
                            <td>{{ $row->unit_name }}</td>
                            <td>{{ $formatQty($row->quantity) }}</td>
                            <td>{{ $currency }} {{ number_format($row->sales_revenue, 0) }}</td>
                            <td>{{ $currency }} {{ number_format($row->current_cost_price, 0) }}</td>
                            <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}" class="badge credit" title="Open product unit setup">Missing cost</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="muted">No missing-cost sale lines matched the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @include('partials.developer_credit')
@endsection
