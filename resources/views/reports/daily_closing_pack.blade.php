@extends('layouts.app', ['title' => 'Daily Closing Pack'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($formatQty = fn ($value) => rtrim(rtrim(number_format((float) $value, 3, '.', ','), '0'), '.') ?: '0')
    @php($formatMargin = fn ($value) => $value === null ? 'N/A' : number_format((float) $value, 2).'%')
    @include('reports.partials.owner_print_styles')

    <div class="page-head">
        <div>
            <h2>Daily Closing Pack</h2>
            <p>Owner daily report combining sales, returns, expenses, profit, cashier accountability, and purchase funding.</p>
        </div>
        <div class="owner-report-actions">
            <button type="button" class="button-link" onclick="window.print()">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="button-link">Export CSV</a>
            <a href="{{ route('reports.financial-summary') }}" class="button-link">Back to Financial Summary</a>
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
            <h3>Daily Owner Closing Pack / Cashier Accountability Report</h3>
            <p class="owner-report-meta">Period: {{ \Illuminate\Support\Carbon::parse($fromDate)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($toDate)->format('d M Y') }}</p>
        </div>

        <section class="cards" style="box-shadow:none;">
            <div class="card"><div class="label">Gross Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['gross_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Returned Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['returned_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Net Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['net_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Cash Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['cash_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Mobile Money</div><div class="value money">{{ $currency }} {{ number_format($summary['mobile_money_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Bank / Card</div><div class="value money">{{ $currency }} {{ number_format($summary['bank_card_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Credit Sales</div><div class="value money">{{ $currency }} {{ number_format($summary['credit_sales'], 0) }}</div></div>
            <div class="card"><div class="label">Expenses</div><div class="value money">{{ $currency }} {{ number_format($summary['expenses'], 0) }}</div></div>
            <div class="card"><div class="label">Estimated Gross Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_gross_profit'], 0) }}</div></div>
            <div class="card"><div class="label">Estimated Net Profit</div><div class="value money">{{ $currency }} {{ number_format($summary['estimated_net_profit'], 0) }}</div></div>
            <div class="card"><div class="label">Expected Cash</div><div class="value money">{{ $currency }} {{ number_format($summary['expected_cash'], 0) }}</div></div>
            <div class="card"><div class="label">Difference</div><div class="value money">{{ $currency }} {{ number_format($summary['cash_difference'], 0) }}</div></div>
        </section>

        <div class="owner-report-section">
            <div class="owner-report-section-title">
                <span>Cashier / User Summary</span>
                <span>{{ $summary['cash_handover_available'] ? 'Cash handover data available' : 'Cash handover data not available.' }}</span>
            </div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Cashier</th>
                            <th>Sales</th>
                            <th>Cash Sales</th>
                            <th>Mobile Money</th>
                            <th>Credit Sales</th>
                            <th>Returns</th>
                            <th>Expenses</th>
                            <th>Expected Cash</th>
                            <th>Handover</th>
                            <th>Difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($cashierRows as $row)
                            <tr>
                                <td><a href="{{ route('sales.index', ['q' => $row->cashier, 'date_from' => $fromDate, 'date_to' => $toDate]) }}"><strong>{{ $row->cashier }}</strong></a></td>
                                <td>{{ number_format($row->sales_count) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->cash_sales, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->mobile_money_sales, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->credit_sales, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->returns, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->expenses, 0) }}</td>
                                <td class="money">{{ $row->handover_available ? $currency.' '.number_format($row->expected_cash, 0) : '-' }}</td>
                                <td class="money">{{ $row->handover_available ? $currency.' '.number_format($row->handover_amount, 0) : '-' }}</td>
                                <td class="money">{{ $row->handover_available ? $currency.' '.number_format($row->difference, 0) : '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="muted">No cashier activity matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Payment Mode Summary</span><span>Sales less returns</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Payment Mode</th>
                            <th>Transactions</th>
                            <th>Total Amount</th>
                            <th>Returned / Refunded</th>
                            <th>Net Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($paymentRows as $row)
                            <tr>
                                <td><a href="{{ route('sales.index', ['q' => $row->payment_mode, 'date_from' => $fromDate, 'date_to' => $toDate]) }}"><strong>{{ $row->payment_mode }}</strong></a></td>
                                <td>{{ number_format($row->transaction_count) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->total_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->returned_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->net_amount, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No payment mode activity matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <section class="grid-two" style="margin-top:14px;">
            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Income and Expenditure Summary</span><span>B/F not available in this system yet. Showing net movement only.</span></div>
                <table>
                    <tbody>
                        <tr><th>Sales Income</th><td class="money">{{ $currency }} {{ number_format($summary['net_sales'], 0) }}</td></tr>
                        <tr><th>Expenses</th><td class="money">{{ $currency }} {{ number_format($summary['expenses'], 0) }}</td></tr>
                        <tr class="owner-total-row"><th>Net Movement</th><td class="money">{{ $currency }} {{ number_format($summary['net_movement'], 0) }}</td></tr>
                    </tbody>
                </table>
            </div>

            <div class="owner-report-section" style="margin-top:0;">
                <div class="owner-report-section-title"><span>Profit Summary</span><span>Estimated where cost snapshots are missing</span></div>
                <table>
                    <tbody>
                        <tr><th>Net Sales</th><td class="money">{{ $currency }} {{ number_format($summary['net_sales'], 0) }}</td></tr>
                        <tr><th>Net Estimated COGS</th><td class="money">{{ $currency }} {{ number_format($summary['net_estimated_cogs'], 0) }}</td></tr>
                        <tr><th>Net Estimated Gross Profit</th><td class="money">{{ $currency }} {{ number_format($summary['estimated_gross_profit'], 0) }}</td></tr>
                        <tr><th>Expenses</th><td class="money">{{ $currency }} {{ number_format($summary['expenses'], 0) }}</td></tr>
                        <tr class="owner-total-row"><th>Estimated Net Profit</th><td class="money">{{ $currency }} {{ number_format($summary['estimated_net_profit'], 0) }}</td></tr>
                        <tr><th>Margin %</th><td>{{ $formatMargin($summary['margin_percent']) }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Top Items Sold</span><span>Best sellers by amount</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Average Rate</th>
                            <th>Total Amount</th>
                            <th>Estimated Gross Profit</th>
                            <th>Margin %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topItems as $row)
                            <tr>
                                <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->item }}</strong></a></td>
                                <td class="money">{{ $formatQty($row->quantity) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->average_rate, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->sales_amount, 0) }}</td>
                                <td class="money">{{ $row->estimated_gross_profit === null ? 'N/A' : $currency.' '.number_format($row->estimated_gross_profit, 0) }}</td>
                                <td>{{ $formatMargin($row->margin_percent) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="muted">No item sales matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Low Margin / Warning Items</span><span>Missing cost, below cost, or under 5% margin</span></div>
            <div style="overflow:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Sales Amount</th>
                            <th>Cost Amount</th>
                            <th>Profit</th>
                            <th>Margin %</th>
                            <th>Warning</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($warningItems as $row)
                            <tr>
                                <td><a href="{{ route('products.edit', ['product' => $row->product_id, 'focus' => 'units']) }}"><strong>{{ $row->item }}</strong></a></td>
                                <td class="money">{{ $formatQty($row->quantity) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->sales_amount, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->cost_amount, 0) }}</td>
                                <td class="money">{{ $row->estimated_gross_profit === null ? 'N/A' : $currency.' '.number_format($row->estimated_gross_profit, 0) }}</td>
                                <td>{{ $formatMargin($row->margin_percent) }}</td>
                                <td><span class="badge credit">{{ $row->warning_label }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="muted">No low-margin or missing-cost items matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="owner-report-section">
            <div class="owner-report-section-title"><span>Purchases Funding Summary</span><span>Posted purchases in the selected period</span></div>
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
                                <td class="money">{{ $currency }} {{ number_format($row->purchase_total, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->amount_paid, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format($row->balance_due, 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="muted">No posted purchases matched the selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @include('partials.developer_credit')
    </section>
@endsection
