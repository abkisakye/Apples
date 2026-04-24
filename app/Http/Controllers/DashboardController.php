<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\FollowUpAction;
use App\Models\Product;
use App\Models\SaleItem;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        [$fromDate, $toDate, $period] = $this->resolveDateRange($request, 'week');
        $salesQuery = Sale::query()->posted();
        $purchasesQuery = Purchase::query()->posted();
        $today = now()->toDateString();
        $lowStockBaseQuery = DB::table('product_units')
            ->join('products', 'products.id', '=', 'product_units.product_id')
            ->leftJoin('inventory_transactions', 'inventory_transactions.product_unit_id', '=', 'product_units.id')
            ->selectRaw('
                product_units.id,
                products.name as product_name,
                product_units.unit_name,
                products.reorder_level,
                COALESCE(SUM(inventory_transactions.quantity_in), 0) - COALESCE(SUM(inventory_transactions.quantity_out), 0) as balance_qty
            ')
            ->where('product_units.is_active', true)
            ->groupBy('product_units.id', 'products.name', 'product_units.unit_name', 'products.reorder_level')
            ->havingRaw('balance_qty <= reorder_level')
            ->havingRaw('reorder_level > 0');
        $lowStockItems = collect((clone $lowStockBaseQuery)->orderBy('products.name')->limit(8)->get());
        $overdueSales = Sale::query()
            ->with(['customer:id,name', 'store:id,name'])
            ->posted()
            ->where('sale_type', 'credit')
            ->where('balance_due', '>', 0)
            ->whereDate('credit_due_date', '<', $today)
            ->orderBy('credit_due_date')
            ->limit(8)
            ->get();
        $pendingFollowUps = FollowUpAction::query()
            ->with(['customer:id,name', 'supplier:id,name', 'sale:id,sale_no', 'purchase:id,purchase_no', 'assignedUser:id,name'])
            ->whereIn('status', ['pending', 'sent'])
            ->orderBy('reminder_date')
            ->limit(8)
            ->get();
        $customerAgingTotals = $this->agingTotals(
            Sale::query()->posted()->where('sale_type', 'credit')->where('balance_due', '>', 0)->get(['credit_due_date', 'balance_due']),
            'credit_due_date',
            'balance_due'
        );
        $supplierAgingTotals = $this->agingTotals(
            Purchase::query()->posted()->where('purchase_type', 'credit')->where('balance_due', '>', 0)->get(['credit_due_date', 'balance_due']),
            'credit_due_date',
            'balance_due'
        );
        $salesTrend = collect(range(6, 0))->map(function (int $daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();

            return [
                'label' => now()->subDays($daysAgo)->format('D'),
                'date' => $date,
                'total' => round((float) Sale::query()->posted()->whereDate('sale_date', $date)->sum('total_amount'), 2),
            ];
        });
        $salesMix = [
            'cash' => (float) Sale::query()->posted()->where('sale_type', 'cash')->sum('total_amount'),
            'credit' => (float) Sale::query()->posted()->where('sale_type', 'credit')->sum('total_amount'),
        ];
        $creditHealth = [
            'current' => $customerAgingTotals['current'],
            'overdue' => $customerAgingTotals['days_1_30'] + $customerAgingTotals['days_31_60'] + $customerAgingTotals['days_61_90'] + $customerAgingTotals['days_90_plus'],
        ];
        $rangeSalesQuery = Sale::query()->posted()->whereBetween('sale_date', [$fromDate, $toDate]);
        $rangePurchasesQuery = Purchase::query()->posted()->whereBetween('purchase_date', [$fromDate, $toDate]);
        $rangeExpensesQuery = Expense::query()->posted()->whereBetween('expense_date', [$fromDate, $toDate]);
        $rangeReturnsQuery = \App\Models\SaleReturn::query()->where('status', 'posted')->whereBetween('return_date', [$fromDate, $toDate]);
        $rangeSalesTotal = (float) (clone $rangeSalesQuery)->sum('total_amount');
        $rangePurchaseTotal = (float) (clone $rangePurchasesQuery)->sum('total_amount');
        $rangeExpenseTotal = (float) (clone $rangeExpensesQuery)->sum('amount');
        $rangeReturnTotal = (float) (clone $rangeReturnsQuery)->sum('returned_total');
        $rangeCollectionTotal = (float) (clone $rangeSalesQuery)->sum('amount_paid');
        $rangeCogs = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'posted')
            ->whereBetween('sales.sale_date', [$fromDate, $toDate])
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.cost_price_snapshot), 0) as cogs')
            ->value('cogs');
        $rangeGrossProfit = round($rangeSalesTotal - $rangeCogs, 2);
        $rangeNetProfit = round($rangeGrossProfit - $rangeExpenseTotal, 2);
        $rangeTrend = collect(Carbon::parse($fromDate)->daysUntil(Carbon::parse($toDate)->addDay()))
            ->map(function (Carbon $date) use ($rangeSalesQuery, $rangePurchasesQuery, $rangeExpensesQuery) {
                $dateString = $date->toDateString();

                return [
                    'label' => $date->format('d M'),
                    'sales' => (float) (clone $rangeSalesQuery)->whereDate('sale_date', $dateString)->sum('total_amount'),
                    'purchases' => (float) (clone $rangePurchasesQuery)->whereDate('purchase_date', $dateString)->sum('total_amount'),
                    'expenses' => (float) (clone $rangeExpensesQuery)->whereDate('expense_date', $dateString)->sum('amount'),
                ];
            })->values();
        $topSellingItems = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('product_units', 'product_units.id', '=', 'sale_items.product_unit_id')
            ->where('sales.status', 'posted')
            ->whereBetween('sales.sale_date', [$fromDate, $toDate])
            ->selectRaw('products.name as product_name, product_units.unit_name, SUM(sale_items.quantity) as quantity_sold, SUM(sale_items.line_total) as sales_value')
            ->groupBy('products.name', 'product_units.unit_name')
            ->orderByDesc('quantity_sold')
            ->orderByDesc('sales_value')
            ->limit(8)
            ->get();
        $paymentBreakdown = Sale::query()
            ->posted()
            ->join('payment_modes', 'payment_modes.id', '=', 'sales.payment_mode_id')
            ->whereBetween('sale_date', [$fromDate, $toDate])
            ->selectRaw('payment_modes.name as mode_name, COALESCE(SUM(sales.amount_paid), 0) as amount')
            ->groupBy('payment_modes.name')
            ->orderByDesc('amount')
            ->get();

        return view('dashboard', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'stats' => [
                'stores' => Store::count(),
                'customers' => Customer::query()->where('is_system', false)->count(),
                'suppliers' => Supplier::count(),
                'products' => Product::count(),
                'sales' => $salesQuery->count(),
                'purchases' => $purchasesQuery->count(),
                'sales_total' => (float) $salesQuery->sum('total_amount'),
                'credit_outstanding' => (float) Sale::query()->posted()->sum('balance_due'),
                'overdue_credit_count' => Sale::query()
                    ->posted()
                    ->where('sale_type', 'credit')
                    ->where('balance_due', '>', 0)
                    ->whereDate('credit_due_date', '<', $today)
                    ->count(),
                'overdue_credit_value' => (float) Sale::query()
                    ->posted()
                    ->where('sale_type', 'credit')
                    ->where('balance_due', '>', 0)
                    ->whereDate('credit_due_date', '<', $today)
                    ->sum('balance_due'),
                'low_stock_count' => (clone $lowStockBaseQuery)->get()->count(),
                'pending_follow_up_count' => FollowUpAction::query()->whereIn('status', ['pending', 'sent'])->count(),
                'expenses_today' => (float) Expense::query()->posted()->whereDate('expense_date', $today)->sum('amount'),
                'open_shift_count' => CashShift::query()->open()->count(),
            ],
            'recentSales' => Sale::query()
                ->posted()
                ->with(['customer:id,name', 'store:id,name'])
                ->latest('sale_date')
                ->latest('id')
                ->limit(8)
                ->get(),
            'topProducts' => Product::query()
                ->withCount('units')
                ->orderBy('name')
                ->limit(8)
                ->get(),
            'overdueSales' => $overdueSales,
            'lowStockItems' => $lowStockItems,
            'pendingFollowUps' => $pendingFollowUps,
            'customerAgingTotals' => $customerAgingTotals,
            'supplierAgingTotals' => $supplierAgingTotals,
            'salesTrend' => $salesTrend,
            'salesTrendMax' => max(1, (float) $salesTrend->max('total')),
            'salesMix' => $salesMix,
            'salesMixTotal' => max(1, $salesMix['cash'] + $salesMix['credit']),
            'creditHealth' => $creditHealth,
            'creditHealthTotal' => max(1, $creditHealth['current'] + $creditHealth['overdue']),
            'rangeSummary' => [
                'sales_total' => $rangeSalesTotal,
                'purchase_total' => $rangePurchaseTotal,
                'expense_total' => $rangeExpenseTotal,
                'return_total' => $rangeReturnTotal,
                'collection_total' => $rangeCollectionTotal,
                'gross_profit' => $rangeGrossProfit,
                'net_profit' => $rangeNetProfit,
            ],
            'rangeTrend' => $rangeTrend,
            'rangeTrendMax' => max(1, (float) $rangeTrend->max(fn (array $row) => max($row['sales'], $row['purchases'], $row['expenses']))),
            'topSellingItems' => $topSellingItems,
            'paymentBreakdown' => $paymentBreakdown,
            'paymentBreakdownTotal' => max(1, (float) $paymentBreakdown->sum('amount')),
        ]);
    }

    private function agingTotals(iterable $documents, string $dueDateField, string $amountField): array
    {
        $today = now()->startOfDay();
        $totals = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
        ];

        foreach ($documents as $document) {
            $dueDate = $document->{$dueDateField};
            $daysLate = $dueDate ? $today->diffInDays($dueDate, false) * -1 : 0;
            $amount = (float) $document->{$amountField};

            if ($daysLate <= 0) {
                $totals['current'] += $amount;
            } elseif ($daysLate <= 30) {
                $totals['days_1_30'] += $amount;
            } elseif ($daysLate <= 60) {
                $totals['days_31_60'] += $amount;
            } elseif ($daysLate <= 90) {
                $totals['days_61_90'] += $amount;
            } else {
                $totals['days_90_plus'] += $amount;
            }
        }

        return array_map(fn ($value) => round($value, 2), $totals);
    }

    private function resolveDateRange(Request $request, string $defaultPeriod = 'week'): array
    {
        $period = trim((string) $request->string('period'));
        $today = Carbon::today();

        [$defaultFrom, $defaultTo] = match ($period !== '' ? $period : $defaultPeriod) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            default => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
        };

        $fromDate = Carbon::parse((string) $request->input('from', $defaultFrom))->toDateString();
        $toDate = Carbon::parse((string) $request->input('to', $defaultTo))->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate, $period];
    }
}
