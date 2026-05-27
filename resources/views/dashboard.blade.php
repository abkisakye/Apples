@extends('layouts.app', ['title' => 'Dashboard'])

@section('content')
    @php($currency = config('business.currency', 'UGX'))
    @php($customerOverdueTotal = $customerAgingTotals['days_1_30'] + $customerAgingTotals['days_31_60'] + $customerAgingTotals['days_61_90'] + $customerAgingTotals['days_90_plus'])
    @php($supplierOverdueTotal = $supplierAgingTotals['days_1_30'] + $supplierAgingTotals['days_31_60'] + $supplierAgingTotals['days_61_90'] + $supplierAgingTotals['days_90_plus'])
    <style>
        .dashboard-summary-card {
            display: block;
            min-height: 116px;
            transition: transform .16s ease, border-color .16s ease, box-shadow .16s ease;
        }

        .dashboard-summary-card:hover {
            transform: translateY(-2px);
            border-color: var(--accent);
            box-shadow: 0 22px 42px rgba(47, 38, 22, 0.12);
        }

        .dashboard-card-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 10px;
            color: var(--brand);
            font-size: .78rem;
            font-weight: 800;
        }
    </style>
    <div class="page-head">
        <div>
            <h2>Business Dashboard</h2>
            <p>Use this dashboard to spot what needs management attention first: sales movement, unpaid credit, low stock, and pending follow-up work.</p>
        </div>
        <div class="actions">
            @if ($access->can('sales.manage'))
                <a href="{{ route('sales.create') }}" class="button-link primary">New Sale</a>
            @endif
            @if ($access->can('purchases.manage'))
                <a href="{{ route('purchases.create') }}" class="button-link">New Purchase</a>
            @endif
            @if ($access->can('stock.view'))
                <a href="{{ route('stock.reorder') }}" class="button-link">Low Stock</a>
            @endif
            @if ($access->can('customer_payments.manage'))
                <a href="{{ route('customer-payments.create') }}" class="button-link">Post Payment</a>
            @endif
            @if ($access->can('cash_shifts.manage'))
                <a href="{{ route('cash-shifts.index') }}" class="button-link">Cash Shifts</a>
            @endif
            @if ($access->can('expenses.view'))
                <a href="{{ route('expenses.index') }}" class="button-link">Expenses</a>
            @endif
            @if ($access->can('reports.view'))
                <a href="{{ route('reports.financial-summary') }}" class="button-link">Reports</a>
            @endif
        </div>
    </div>

    <section class="panel" style="margin-bottom: 16px;">
        <form method="get" class="filters">
            <input type="date" name="date_from" value="{{ $fromDate }}">
            <input type="date" name="date_to" value="{{ $toDate }}">
            <select name="period">
                <option value="">Custom</option>
                <option value="today" @selected($period === 'today')>Today</option>
                <option value="week" @selected($period === 'week')>This week</option>
                <option value="month" @selected($period === 'month')>This month</option>
            </select>
            <button type="submit">Apply</button>
            <a href="{{ route('dashboard') }}" class="button-link">Reset</a>
        </form>
        <p class="list-note" style="margin-top: 12px;">The selected dates drive the trading summary below so management can review today, this week, this month, or a custom range without leaving the dashboard.</p>
    </section>

    <section class="cards" style="margin-bottom: 16px;">
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['sales'] }}"><div class="label">Sales</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['sales_total'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['purchases'] }}"><div class="label">Purchases</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['purchase_total'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['expenses'] }}"><div class="label">Expenses</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['expense_total'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['collections'] }}"><div class="label">Collections</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['collection_total'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['gross_profit'] }}"><div class="label">Gross Profit</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['gross_profit'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['net_profit'] }}"><div class="label">Net Profit</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['net_profit'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
        <a class="card dashboard-summary-card" href="{{ $dashboardCardLinks['returns'] }}"><div class="label">Sales Returns</div><div class="value money">{{ $currency }} {{ number_format($rangeSummary['return_total'], 0) }}</div><div class="dashboard-card-action">View details &rarr;</div></a>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Trading Trend</h3>
            <p class="list-note">Sales, purchases, and expenses inside the selected period.</p>
            <div style="display:grid; gap:10px; margin-top: 14px;">
                @foreach ($rangeTrend as $point)
                    <div style="display:grid; grid-template-columns: 62px minmax(0,1fr) 110px; gap:10px; align-items:center;">
                        <div class="muted">{{ $point['label'] }}</div>
                        <div style="display:grid; gap:6px;">
                            <div style="height: 10px; background:#efe7d8; border-radius:999px; overflow:hidden;">
                                <div style="height: 100%; width: {{ round(($point['sales'] / $rangeTrendMax) * 100, 2) }}%; min-width: {{ $point['sales'] > 0 ? '8px' : '0' }}; background: linear-gradient(90deg, #066838, #0c8f4f); border-radius:999px;"></div>
                            </div>
                            <div style="height: 10px; background:#efe7d8; border-radius:999px; overflow:hidden;">
                                <div style="height: 100%; width: {{ round(($point['purchases'] / $rangeTrendMax) * 100, 2) }}%; min-width: {{ $point['purchases'] > 0 ? '8px' : '0' }}; background: linear-gradient(90deg, #d4af37, #e0c55f); border-radius:999px;"></div>
                            </div>
                            <div style="height: 10px; background:#efe7d8; border-radius:999px; overflow:hidden;">
                                <div style="height: 100%; width: {{ round(($point['expenses'] / $rangeTrendMax) * 100, 2) }}%; min-width: {{ $point['expenses'] > 0 ? '8px' : '0' }}; background: linear-gradient(90deg, #662828, #934141); border-radius:999px;"></div>
                            </div>
                        </div>
                        <div class="muted" style="font-size:.82rem;">
                            S {{ number_format($point['sales'], 0) }}<br>
                            P {{ number_format($point['purchases'], 0) }}<br>
                            E {{ number_format($point['expenses'], 0) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="panel">
            <h3>Top Selling Products</h3>
            <p class="list-note">The strongest-moving items in the selected management period.</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Sales</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($topSellingItems as $item)
                            <tr>
                                <td>
                                    <div class="table-title">{{ $item->product_name }}</div>
                                    <div class="table-meta">{{ $item->unit_name }}</div>
                                </td>
                                <td>{{ number_format((float) $item->quantity_sold, 0) }}</td>
                                <td class="money">{{ $currency }} {{ number_format((float) $item->sales_value, 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="muted">No posted sales were found in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div style="margin-top: 16px;">
                <div class="muted" style="margin-bottom:6px;">Payment Breakdown</div>
                @forelse ($paymentBreakdown as $row)
                    <div style="display:grid; grid-template-columns: 110px minmax(0,1fr) 110px; gap:10px; align-items:center; margin-top:8px;">
                        <div class="muted">{{ $row->mode_name }}</div>
                        <div style="height: 10px; background:#efe7d8; border-radius:999px; overflow:hidden;">
                            <div style="height: 100%; width: {{ round(($row->amount / $paymentBreakdownTotal) * 100, 2) }}%; min-width: {{ $row->amount > 0 ? '8px' : '0' }}; background: linear-gradient(90deg, #066838, #d4af37); border-radius:999px;"></div>
                        </div>
                        <div class="money">{{ number_format((float) $row->amount, 0) }}</div>
                    </div>
                @empty
                    <div class="muted">No payment activity in this period yet.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Management Snapshot</h3>
            <p class="list-note">This top summary is meant for a quick manager check before going deeper into the detailed tables below.</p>
            <div class="form-grid" style="margin-top: 14px;">
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">Sales Posted</div>
                    <div class="value">{{ number_format($stats['sales']) }}</div>
                    <div class="muted" style="margin-top:8px;">Total sales records now in the system.</div>
                </div>
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">Sales Value</div>
                    <div class="value money">{{ $currency }} {{ number_format($stats['sales_total'], 0) }}</div>
                    <div class="muted" style="margin-top:8px;">Combined value of posted sales.</div>
                </div>
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">Customer Overdue</div>
                    <div class="value money">{{ $currency }} {{ number_format($customerOverdueTotal, 0) }}</div>
                    <div class="muted" style="margin-top:8px;">Customer balances already overdue.</div>
                </div>
                <div class="card" style="box-shadow:none; margin:0;">
                    <div class="label">Supplier Overdue</div>
                    <div class="value money">{{ $currency }} {{ number_format($supplierOverdueTotal, 0) }}</div>
                    <div class="muted" style="margin-top:8px;">Supplier balances that need attention.</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <h3>Today&apos;s Watchlist</h3>
            <p class="list-note">A quick way to decide where to focus next without opening multiple pages.</p>
            <table>
                <tbody>
                    <tr>
                        <th style="text-align:left; width: 42%;">Overdue Credit Sales</th>
                        <td>{{ number_format($stats['overdue_credit_count']) }} records</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Overdue Credit Value</th>
                        <td>{{ $currency }} {{ number_format($stats['overdue_credit_value'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Low Stock Items</th>
                        <td>{{ number_format($stats['low_stock_count']) }} items below or at reorder level</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Pending Follow-ups</th>
                        <td>{{ number_format($stats['pending_follow_up_count']) }} active reminders</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Open Cash Shifts</th>
                        <td>{{ number_format($stats['open_shift_count']) }} shifts still open</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Expenses Today</th>
                        <td>{{ $currency }} {{ number_format($stats['expenses_today'], 0) }}</td>
                    </tr>
                    <tr>
                        <th style="text-align:left;">Credit Outstanding</th>
                        <td>{{ $currency }} {{ number_format($stats['credit_outstanding'], 0) }}</td>
                    </tr>
                </tbody>
            </table>
            <div class="actions" style="margin-top:14px;">
                @if ($access->can('sales.view'))
                    <a href="{{ route('sales.index', ['type' => 'credit']) }}" class="button-link">Credit Sales</a>
                @endif
                @if ($access->can('customer_payments.manage'))
                    <a href="{{ route('customer-payments.index', ['period' => 'today']) }}" class="button-link">Today Payments</a>
                @endif
                @if ($access->can('supplier_payments.manage'))
                    <a href="{{ route('supplier-payments.index', ['period' => 'today']) }}" class="button-link">Today Supplier Payments</a>
                @endif
                @if ($access->can('stock.manage'))
                    <a href="{{ route('stock.transfers.create') }}" class="button-link">New Transfer</a>
                    <a href="{{ route('stock.adjustments.create') }}" class="button-link">New Adjustment</a>
                @endif
            </div>
        </div>
    </section>

    @if ($access->can('users.manage'))
        <section class="panel" style="margin-bottom: 16px;">
            <h3>Admin Toolkit</h3>
            <p class="list-note">These shortcuts help with staff setup, client demos, and readiness checks without needing to search through the menu.</p>
            <div class="actions" style="margin-top: 14px;">
                <a href="{{ route('roles.matrix') }}" class="button-link">Permissions Matrix</a>
                <a href="{{ route('admin.uat-center') }}" class="button-link">UAT Center</a>
                <a href="{{ route('admin.demo-center') }}" class="button-link">Demo Center</a>
                <a href="{{ route('admin.readiness') }}" class="button-link">Production Readiness</a>
                <a href="{{ route('reports.financial-summary') }}" class="button-link">Financial Summary</a>
                <a href="{{ route('users.index') }}" class="button-link">Users</a>
            </div>
        </section>
    @endif

    <section class="cards">
        <div class="card"><div class="label">Shop</div><div class="value">{{ config('business.name', 'Apples Of Gold') }}</div></div>
        <div class="card"><div class="label">Customers</div><div class="value">{{ number_format($stats['customers']) }}</div></div>
        <div class="card"><div class="label">Suppliers</div><div class="value">{{ number_format($stats['suppliers']) }}</div></div>
        <div class="card"><div class="label">Products</div><div class="value">{{ number_format($stats['products']) }}</div></div>
        <div class="card"><div class="label">Sales</div><div class="value">{{ number_format($stats['sales']) }}</div></div>
        <div class="card"><div class="label">Purchases</div><div class="value">{{ number_format($stats['purchases']) }}</div></div>
        <div class="card"><div class="label">Sales Value</div><div class="value money">{{ $currency }} {{ number_format($stats['sales_total'], 0) }}</div></div>
        <div class="card"><div class="label">Expenses Today</div><div class="value money">{{ $currency }} {{ number_format($stats['expenses_today'], 0) }}</div></div>
        <div class="card"><div class="label">Credit Outstanding</div><div class="value money">{{ $currency }} {{ number_format($stats['credit_outstanding'], 0) }}</div></div>
        <div class="card"><div class="label">Overdue Credit</div><div class="value">{{ number_format($stats['overdue_credit_count']) }}</div></div>
        <div class="card"><div class="label">Overdue Value</div><div class="value money">{{ $currency }} {{ number_format($stats['overdue_credit_value'], 0) }}</div></div>
        <div class="card"><div class="label">Low Stock Items</div><div class="value">{{ number_format($stats['low_stock_count']) }}</div></div>
        <div class="card"><div class="label">Pending Follow-ups</div><div class="value">{{ number_format($stats['pending_follow_up_count']) }}</div></div>
        <div class="card"><div class="label">Open Shifts</div><div class="value">{{ number_format($stats['open_shift_count']) }}</div></div>
    </section>

    <section class="grid-two" style="margin-bottom: 16px;">
        <div class="panel">
            <h3>Sales Trend (Last 7 Days)</h3>
            <div style="display:grid; gap:10px; margin-top: 14px;">
                @foreach ($salesTrend as $point)
                    <div style="display:grid; grid-template-columns: 56px 1fr 110px; gap:10px; align-items:center;">
                        <div class="muted">{{ $point['label'] }}</div>
                        <div style="height: 12px; background:#efe7d8; border-radius:999px; overflow:hidden;">
                            <div style="height: 100%; width: {{ round(($point['total'] / $salesTrendMax) * 100, 2) }}%; min-width: {{ $point['total'] > 0 ? '8px' : '0' }}; background: linear-gradient(90deg, #155e63, #4b8b84); border-radius:999px;"></div>
                        </div>
                        <div class="money">{{ number_format($point['total'], 0) }}</div>
                    </div>
                @endforeach
            </div>
            <p class="list-note">A simple visual trend for quick manager review without needing a chart library.</p>
        </div>

        <div class="panel">
            <h3>Sales Mix And Credit Health</h3>
            <div style="margin-top: 14px;">
                <div class="muted" style="margin-bottom:6px;">Cash vs Credit Sales Value</div>
                <div style="display:flex; height: 16px; border-radius:999px; overflow:hidden; background:#efe7d8;">
                    <div style="width: {{ round(($salesMix['cash'] / $salesMixTotal) * 100, 2) }}%; background:#155e63;"></div>
                    <div style="width: {{ round(($salesMix['credit'] / $salesMixTotal) * 100, 2) }}%; background:#ab6c2f;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px;" class="muted">
                    <span>Cash: {{ number_format($salesMix['cash'], 0) }}</span>
                    <span>Credit: {{ number_format($salesMix['credit'], 0) }}</span>
                </div>
            </div>

            <div style="margin-top: 18px;">
                <div class="muted" style="margin-bottom:6px;">Current vs Overdue Customer Credit</div>
                <div style="display:flex; height: 16px; border-radius:999px; overflow:hidden; background:#efe7d8;">
                    <div style="width: {{ round(($creditHealth['current'] / $creditHealthTotal) * 100, 2) }}%; background:#1f7a4d;"></div>
                    <div style="width: {{ round(($creditHealth['overdue'] / $creditHealthTotal) * 100, 2) }}%; background:#c05621;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:8px;" class="muted">
                    <span>Current: {{ number_format($creditHealth['current'], 0) }}</span>
                    <span>Overdue: {{ number_format($creditHealth['overdue'], 0) }}</span>
                </div>
            </div>
        </div>
    </section>

    <section class="grid-two">
        <div class="panel">
            <h3>Recent Sales</h3>
            <p class="list-note">This gives management a quick look at the latest activity without leaving the dashboard.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sale No</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Store</th>
                        <th>Type</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recentSales as $sale)
                        <tr>
                            <td>
                                <div class="table-title"><a href="{{ route('sales.show', $sale) }}">{{ $sale->sale_no }}</a></div>
                                <div class="table-meta">{{ $sale->customer?->name ?? 'Walk-in customer' }}</div>
                            </td>
                            <td>{{ optional($sale->sale_date)->format('d M Y') }}</td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in customer' }}</td>
                            <td>{{ $sale->store?->name }}</td>
                            <td><span class="badge {{ $sale->sale_type === 'credit' ? 'credit' : '' }}">{{ ucfirst($sale->sale_type) }}</span></td>
                            <td class="money">
                                {{ $currency }} {{ number_format((float) $sale->total_amount, 0) }}
                                <div class="action-stack" style="margin-top:6px;">
                                    <a href="{{ route('sales.print', $sale) }}" target="_blank" class="action-chip">Print</a>
                                    <a href="{{ route('sales.show', $sale) }}" class="action-chip primary">Open</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>

        <div class="panel">
            <h3>Product Overview</h3>
            <p class="list-note">A quick product count view that helps confirm setup and active selling units.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Units</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($topProducts as $product)
                        <tr>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->units_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    </section>

    <section class="grid-two" style="margin-top: 16px;">
        <div class="panel">
            <h3>Overdue Credit</h3>
            <p class="list-note">These are the sales that most likely need collection effort or a follow-up decision.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sale No</th>
                        <th>Customer</th>
                        <th>Due Date</th>
                        <th>Balance</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($overdueSales as $sale)
                        <tr>
                            <td><div class="table-title"><a href="{{ route('sales.show', $sale) }}">{{ $sale->sale_no }}</a></div></td>
                            <td>{{ $sale->customer?->name ?? 'Walk-in customer' }}</td>
                            <td>{{ optional($sale->credit_due_date)->format('d M Y') }}</td>
                            <td class="money">{{ $currency }} {{ number_format((float) $sale->balance_due, 0) }}</td>
                            <td>
                                <div class="action-stack">
                                    @if ($access->can('customers.statement') && $sale->customer_id)
                                        <a href="{{ route('customers.statement', $sale->customer_id) }}" class="action-chip">Statement</a>
                                    @endif
                                    @if ($access->can('customer_payments.manage') && $sale->customer_id)
                                        <a href="{{ route('customer-payments.create', ['customer_id' => $sale->customer_id]) }}" class="action-chip primary">Post Payment</a>
                                    @endif
                                    @if ($access->can('follow_ups.manage'))
                                        <a href="{{ route('follow-ups.create', ['sale_id' => $sale->id]) }}" class="action-chip">Follow Up</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="muted">No overdue credit sales right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="panel">
            <h3>Low Stock Alert</h3>
            <p class="list-note">Items here are already at or below the reorder level and may need buying attention soon.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Unit</th>
                        <th>Reorder</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($lowStockItems as $item)
                        <tr>
                            <td><div class="table-title">{{ $item->product_name }}</div></td>
                            <td>{{ $item->unit_name }}</td>
                            <td>{{ number_format((float) $item->reorder_level, 0) }}</td>
                            <td>
                                <span class="badge credit">{{ number_format((float) $item->balance_qty, 0) }}</span>
                                <div class="action-stack" style="margin-top:6px;">
                                    <a href="{{ route('stock.history', $item->id) }}" class="action-chip">History</a>
                                    @if ($access->can('purchases.manage'))
                                        <a href="{{ route('purchases.create') }}" class="action-chip primary">Reorder</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No low stock items right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </section>

    <section class="grid-two" style="margin-top: 16px;">
        <div class="panel">
            <h3>Pending Follow-ups</h3>
            <p class="list-note">These reminders are still open and should be reviewed during the day.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Reminder</th>
                        <th>Target</th>
                        <th>Assigned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($pendingFollowUps as $followUp)
                        <tr>
                            <td>{{ optional($followUp->reminder_date)->format('d M Y') }}</td>
                            <td>
                                <div class="table-title">{{ $followUp->sale?->sale_no ?? $followUp->purchase?->purchase_no }}</div>
                                <div class="table-meta">{{ $followUp->customer?->name ?? $followUp->supplier?->name ?? '-' }}</div>
                            </td>
                            <td>{{ $followUp->assignedUser?->name ?? '-' }}</td>
                            <td><span class="badge {{ $followUp->status === 'completed' ? 'success' : 'credit' }}">{{ ucfirst($followUp->status) }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="muted">No pending follow-ups right now.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            </div>
        </div>

        <div class="panel">
            <h3>Customer Aging Totals</h3>
            <p class="list-note">These totals help management see how much money is delayed and where the pressure is building.</p>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bucket</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Current</td><td class="money">{{ number_format($customerAgingTotals['current'], 0) }}</td></tr>
                    <tr><td>1-30 Days</td><td class="money">{{ number_format($customerAgingTotals['days_1_30'], 0) }}</td></tr>
                    <tr><td>31-60 Days</td><td class="money">{{ number_format($customerAgingTotals['days_31_60'], 0) }}</td></tr>
                    <tr><td>61-90 Days</td><td class="money">{{ number_format($customerAgingTotals['days_61_90'], 0) }}</td></tr>
                    <tr><td>90+ Days</td><td class="money">{{ number_format($customerAgingTotals['days_90_plus'], 0) }}</td></tr>
                </tbody>
            </table>
            </div>

            <h3 style="margin-top:16px;">Supplier Aging Totals</h3>
            <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Bucket</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td>Current</td><td class="money">{{ number_format($supplierAgingTotals['current'], 0) }}</td></tr>
                    <tr><td>1-30 Days</td><td class="money">{{ number_format($supplierAgingTotals['days_1_30'], 0) }}</td></tr>
                    <tr><td>31-60 Days</td><td class="money">{{ number_format($supplierAgingTotals['days_31_60'], 0) }}</td></tr>
                    <tr><td>61-90 Days</td><td class="money">{{ number_format($supplierAgingTotals['days_61_90'], 0) }}</td></tr>
                    <tr><td>90+ Days</td><td class="money">{{ number_format($supplierAgingTotals['days_90_plus'], 0) }}</td></tr>
                </tbody>
            </table>
            </div>
        </div>
    </section>
@endsection
