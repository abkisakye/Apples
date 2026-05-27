<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ExcelExportService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function financialSummary(Request $request): View
    {
        [$fromDate, $toDate, $period] = $this->resolveDateRange($request, 'month');

        $salesQuery = Sale::query()->posted()->whereDate('sale_date', '>=', $fromDate)->whereDate('sale_date', '<=', $toDate);
        $purchasesQuery = Purchase::query()->posted()->whereDate('purchase_date', '>=', $fromDate)->whereDate('purchase_date', '<=', $toDate);
        $expensesQuery = Expense::query()->posted()->whereDate('expense_date', '>=', $fromDate)->whereDate('expense_date', '<=', $toDate);
        $returnsQuery = SaleReturn::query()->where('status', 'posted')->whereDate('return_date', '>=', $fromDate)->whereDate('return_date', '<=', $toDate);

        $salesTotal = (float) (clone $salesQuery)->sum('total_amount');
        $discountTotal = (float) (clone $salesQuery)->sum('discount_amount');
        $purchaseTotal = (float) (clone $purchasesQuery)->sum('total_amount');
        $expenseTotal = (float) (clone $expensesQuery)->sum('amount');
        $returnTotal = (float) (clone $returnsQuery)->sum('returned_total');
        $refundTotal = (float) (clone $returnsQuery)->sum('refund_amount');
        $cogs = (float) SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'posted')
            ->whereDate('sales.sale_date', '>=', $fromDate)
            ->whereDate('sales.sale_date', '<=', $toDate)
            ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.cost_price_snapshot), 0) as cogs')
            ->value('cogs');
        $grossProfit = round($salesTotal - $cogs, 2);
        $netProfit = round($grossProfit - $expenseTotal - $refundTotal, 2);
        $collectionTotal = $this->collectionTotal($fromDate, $toDate);

        $daily = collect(Carbon::parse($fromDate)->daysUntil(Carbon::parse($toDate)->addDay()))
            ->map(function (Carbon $date) use ($salesQuery, $expensesQuery) {
                $dateString = $date->toDateString();

                return [
                    'label' => $date->format('d M'),
                    'sales' => (float) (clone $salesQuery)->whereDate('sale_date', $dateString)->sum('total_amount'),
                    'expenses' => (float) (clone $expensesQuery)->whereDate('expense_date', $dateString)->sum('amount'),
                ];
            });

        return view('reports.financial_summary', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'summary' => [
                'sales_total' => $salesTotal,
                'discount_total' => $discountTotal,
                'purchase_total' => $purchaseTotal,
                'expense_total' => $expenseTotal,
                'return_total' => $returnTotal,
                'refund_total' => $refundTotal,
                'cogs' => $cogs,
                'gross_profit' => $grossProfit,
                'net_profit' => $netProfit,
                'collection_total' => $collectionTotal,
            ],
            'daily' => $daily,
            'dailyMax' => max(1, (float) $daily->max(fn (array $row) => max($row['sales'], $row['expenses']))),
        ]);
    }

    public function paymentMethods(Request $request): View
    {
        [$fromDate, $toDate, $period] = $this->resolveDateRange($request, 'month');

        $salesRows = Sale::query()
            ->posted()
            ->whereDate('sale_date', '>=', $fromDate)
            ->whereDate('sale_date', '<=', $toDate)
            ->selectRaw('payment_mode_id, COALESCE(SUM(amount_paid), 0) as amount')
            ->groupBy('payment_mode_id')
            ->pluck('amount', 'payment_mode_id');

        $customerPaymentRows = CustomerPayment::query()
            ->posted()
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->selectRaw('payment_mode_id, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('payment_mode_id')
            ->pluck('amount', 'payment_mode_id');

        $supplierPaymentRows = SupplierPayment::query()
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->selectRaw('payment_mode_id, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('payment_mode_id')
            ->pluck('amount', 'payment_mode_id');

        $refundRows = SaleReturn::query()
            ->where('status', 'posted')
            ->where('refund_amount', '>', 0)
            ->whereDate('return_date', '>=', $fromDate)
            ->whereDate('return_date', '<=', $toDate)
            ->selectRaw('payment_mode_id, COALESCE(SUM(refund_amount), 0) as amount')
            ->groupBy('payment_mode_id')
            ->pluck('amount', 'payment_mode_id');

        $expenseRows = Expense::query()
            ->posted()
            ->whereDate('expense_date', '>=', $fromDate)
            ->whereDate('expense_date', '<=', $toDate)
            ->selectRaw('payment_mode_id, COALESCE(SUM(amount), 0) as amount')
            ->groupBy('payment_mode_id')
            ->pluck('amount', 'payment_mode_id');

        $modeIds = collect()
            ->merge($salesRows->keys())
            ->merge($customerPaymentRows->keys())
            ->merge($supplierPaymentRows->keys())
            ->merge($refundRows->keys())
            ->merge($expenseRows->keys())
            ->filter()
            ->unique()
            ->values();

        $modeNames = DB::table('payment_modes')->whereIn('id', $modeIds)->pluck('name', 'id');

        $rows = $modeIds->map(function ($modeId) use ($modeNames, $salesRows, $customerPaymentRows, $supplierPaymentRows, $refundRows, $expenseRows) {
            $sales = (float) ($salesRows[$modeId] ?? 0);
            $customerPayments = (float) ($customerPaymentRows[$modeId] ?? 0);
            $supplierPayments = (float) ($supplierPaymentRows[$modeId] ?? 0);
            $refunds = (float) ($refundRows[$modeId] ?? 0);
            $expenses = (float) ($expenseRows[$modeId] ?? 0);

            return (object) [
                'name' => $modeNames[$modeId] ?? 'Unassigned',
                'sales_in' => $sales,
                'customer_in' => $customerPayments,
                'supplier_out' => $supplierPayments,
                'refund_out' => $refunds,
                'expense_out' => $expenses,
                'net_total' => round(($sales + $customerPayments) - ($supplierPayments + $refunds + $expenses), 2),
            ];
        })->sortByDesc('net_total')->values();

        return view('reports.payment_methods', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'rows' => $rows,
        ]);
    }

    public function cashierPerformance(Request $request): View
    {
        [$fromDate, $toDate, $period] = $this->resolveDateRange($request, 'month');

        $rows = User::query()
            ->with('role:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($fromDate, $toDate) {
                $salesCount = Sale::query()->posted()->where('created_by', $user->id)->whereDate('sale_date', '>=', $fromDate)->whereDate('sale_date', '<=', $toDate)->count();
                $salesTotal = (float) Sale::query()->posted()->where('created_by', $user->id)->whereDate('sale_date', '>=', $fromDate)->whereDate('sale_date', '<=', $toDate)->sum('total_amount');
                $discountTotal = (float) Sale::query()->posted()->where('created_by', $user->id)->whereDate('sale_date', '>=', $fromDate)->whereDate('sale_date', '<=', $toDate)->sum('discount_amount');
                $creditIssued = (float) Sale::query()->posted()->where('created_by', $user->id)->whereDate('sale_date', '>=', $fromDate)->whereDate('sale_date', '<=', $toDate)->where('balance_due', '>', 0)->sum('balance_due');
                $customerPayments = (float) CustomerPayment::query()->posted()->where('created_by', $user->id)->whereDate('payment_date', '>=', $fromDate)->whereDate('payment_date', '<=', $toDate)->sum('amount');
                $shiftCount = CashShift::query()->where('user_id', $user->id)->whereBetween(DB::raw('date(opened_at)'), [$fromDate, $toDate])->count();
                $shiftDifference = (float) CashShift::query()->where('user_id', $user->id)->whereBetween(DB::raw('date(opened_at)'), [$fromDate, $toDate])->sum('shortage_overage');

                return (object) [
                    'user' => $user,
                    'sales_count' => $salesCount,
                    'sales_total' => $salesTotal,
                    'average_basket' => $salesCount > 0 ? round($salesTotal / $salesCount, 2) : 0,
                    'discount_total' => $discountTotal,
                    'credit_issued' => $creditIssued,
                    'customer_payments' => $customerPayments,
                    'shift_count' => $shiftCount,
                    'shift_difference' => $shiftDifference,
                ];
            })
            ->filter(fn ($row) => $row->sales_count > 0 || $row->customer_payments > 0 || $row->shift_count > 0)
            ->values();

        return view('reports.cashier_performance', [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'period' => $period,
            'rows' => $rows,
        ]);
    }

    public function dailyClosing(Request $request): View
    {
        $date = Carbon::parse((string) $request->input('date', now()->toDateString()))->toDateString();

        $salesTotal = (float) Sale::query()->posted()->whereDate('sale_date', $date)->sum('total_amount');
        $discountTotal = (float) Sale::query()->posted()->whereDate('sale_date', $date)->sum('discount_amount');
        $creditIssued = (float) Sale::query()->posted()->whereDate('sale_date', $date)->where('sale_type', 'credit')->sum('balance_due');
        $returnTotal = (float) SaleReturn::query()->where('status', 'posted')->whereDate('return_date', $date)->sum('returned_total');
        $refundTotal = (float) SaleReturn::query()->where('status', 'posted')->whereDate('return_date', $date)->sum('refund_amount');
        $customerPaymentTotal = (float) CustomerPayment::query()->posted()->whereDate('payment_date', $date)->sum('amount');
        $expenseTotal = (float) Expense::query()->posted()->whereDate('expense_date', $date)->sum('amount');

        $shiftRows = CashShift::query()
            ->with(['user:id,name', 'store:id,name'])
            ->whereDate('opened_at', $date)
            ->orderBy('opened_at')
            ->get();

        $cashExpected = (float) $shiftRows->sum('expected_cash');
        $cashCounted = (float) $shiftRows->sum(fn (CashShift $shift) => (float) ($shift->counted_cash ?? 0));
        $cashDifference = (float) $shiftRows->sum(fn (CashShift $shift) => (float) ($shift->shortage_overage ?? 0));

        $salesPaymentRows = Sale::query()
            ->posted()
            ->whereDate('sale_date', $date)
            ->join('payment_modes', 'payment_modes.id', '=', 'sales.payment_mode_id')
            ->selectRaw('payment_modes.name as mode_name, COALESCE(SUM(sales.amount_paid), 0) as sales_amount, 0 as customer_payment_amount, 0 as refund_amount')
            ->groupBy('payment_modes.name');

        $customerPaymentRows = CustomerPayment::query()
            ->posted()
            ->whereDate('payment_date', $date)
            ->join('payment_modes', 'payment_modes.id', '=', 'customer_payments.payment_mode_id')
            ->selectRaw('payment_modes.name as mode_name, 0 as sales_amount, COALESCE(SUM(customer_payments.amount), 0) as customer_payment_amount, 0 as refund_amount')
            ->groupBy('payment_modes.name');

        $refundPaymentRows = SaleReturn::query()
            ->where('status', 'posted')
            ->where('refund_amount', '>', 0)
            ->whereDate('return_date', $date)
            ->join('payment_modes', 'payment_modes.id', '=', 'sale_returns.payment_mode_id')
            ->selectRaw('payment_modes.name as mode_name, 0 as sales_amount, 0 as customer_payment_amount, COALESCE(SUM(sale_returns.refund_amount), 0) as refund_amount')
            ->groupBy('payment_modes.name');

        $paymentModeRows = DB::query()
            ->fromSub(
                $salesPaymentRows->unionAll($customerPaymentRows)->unionAll($refundPaymentRows),
                'payment_breakdown'
            )
            ->selectRaw('mode_name, SUM(sales_amount) as sales_amount, SUM(customer_payment_amount) as customer_payment_amount, SUM(refund_amount) as refund_amount, SUM(sales_amount + customer_payment_amount - refund_amount) as amount')
            ->groupBy('mode_name')
            ->orderByDesc('amount')
            ->get();

        return view('reports.daily_closing', [
            'date' => $date,
            'summary' => [
                'sales_total' => $salesTotal,
                'discount_total' => $discountTotal,
                'credit_issued' => $creditIssued,
                'return_total' => $returnTotal,
                'refund_total' => $refundTotal,
                'customer_payment_total' => $customerPaymentTotal,
                'expense_total' => $expenseTotal,
                'cash_expected' => $cashExpected,
                'cash_counted' => $cashCounted,
                'cash_difference' => $cashDifference,
            ],
            'paymentModeRows' => $paymentModeRows,
            'shiftRows' => $shiftRows,
        ]);
    }

    public function customerAging(): View
    {
        $today = now()->startOfDay();
        $rows = Customer::query()
            ->where('is_system', false)
            ->with([
                'creditSales' => fn ($query) => $query->posted()->where('balance_due', '>', 0),
                'openingBalancePayments' => fn ($query) => $query->posted(),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Customer $customer) use ($today) {
                $documents = $customer->creditSales->values();
                $openingOutstanding = $customer->openingBalanceOutstanding();

                if ($openingOutstanding > 0) {
                    $documents = $documents->push((object) [
                        'credit_due_date' => $customer->opening_balance_date ?? optional($customer->created_at)?->toDateString(),
                        'balance_due' => $openingOutstanding,
                    ]);
                }

                $buckets = $this->agingBuckets($documents, $today, 'credit_due_date', 'balance_due');

                return (object) [
                    'customer' => $customer,
                    'current' => $buckets['current'],
                    'days_1_30' => $buckets['days_1_30'],
                    'days_31_60' => $buckets['days_31_60'],
                    'days_61_90' => $buckets['days_61_90'],
                    'days_90_plus' => $buckets['days_90_plus'],
                    'total' => array_sum($buckets),
                ];
            })
            ->filter(fn ($row) => $row->total > 0)
            ->values();

        return view('reports.customer_aging', compact('rows'));
    }

    public function supplierAging(): View
    {
        $today = now()->startOfDay();
        $rows = Supplier::query()
            ->where('is_system', false)
            ->with(['purchases' => fn ($query) => $query->posted()->where('balance_due', '>', 0)])
            ->orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) use ($today) {
                $buckets = $this->agingBuckets($supplier->purchases, $today, 'credit_due_date', 'balance_due');

                return (object) [
                    'supplier' => $supplier,
                    'current' => $buckets['current'],
                    'days_1_30' => $buckets['days_1_30'],
                    'days_31_60' => $buckets['days_31_60'],
                    'days_61_90' => $buckets['days_61_90'],
                    'days_90_plus' => $buckets['days_90_plus'],
                    'total' => array_sum($buckets),
                ];
            })
            ->filter(fn ($row) => $row->total > 0)
            ->values();

        return view('reports.supplier_aging', compact('rows'));
    }

    public function customerAgingExport(ExcelExportService $excelExportService): BinaryFileResponse
    {
        $rows = $this->customerAging()->getData()['rows'];

        return $excelExportService->download('customer-aging.xlsx', [
            'Customer', 'Current', '1-30', '31-60', '61-90', '90+', 'Total',
        ], collect($rows)->map(fn ($row) => [
            $row->customer->name,
            $row->current,
            $row->days_1_30,
            $row->days_31_60,
            $row->days_61_90,
            $row->days_90_plus,
            $row->total,
        ]));
    }

    public function supplierAgingExport(ExcelExportService $excelExportService): BinaryFileResponse
    {
        $rows = $this->supplierAging()->getData()['rows'];

        return $excelExportService->download('supplier-aging.xlsx', [
            'Supplier', 'Current', '1-30', '31-60', '61-90', '90+', 'Total',
        ], collect($rows)->map(fn ($row) => [
            $row->supplier->name,
            $row->current,
            $row->days_1_30,
            $row->days_31_60,
            $row->days_61_90,
            $row->days_90_plus,
            $row->total,
        ]));
    }

    private function agingBuckets(iterable $documents, $today, string $dueDateField, string $amountField): array
    {
        $buckets = [
            'current' => 0.0,
            'days_1_30' => 0.0,
            'days_31_60' => 0.0,
            'days_61_90' => 0.0,
            'days_90_plus' => 0.0,
        ];

        foreach ($documents as $document) {
            $dueDate = $document->{$dueDateField} ?? $document->sale_date ?? $document->purchase_date;
            $daysLate = $dueDate ? $today->diffInDays($dueDate, false) * -1 : 0;
            $amount = (float) $document->{$amountField};

            if ($daysLate <= 0) {
                $buckets['current'] += $amount;
            } elseif ($daysLate <= 30) {
                $buckets['days_1_30'] += $amount;
            } elseif ($daysLate <= 60) {
                $buckets['days_31_60'] += $amount;
            } elseif ($daysLate <= 90) {
                $buckets['days_61_90'] += $amount;
            } else {
                $buckets['days_90_plus'] += $amount;
            }
        }

        return array_map(fn ($value) => round($value, 2), $buckets);
    }

    private function resolveDateRange(Request $request, string $defaultPeriod = 'month'): array
    {
        $period = trim((string) $request->string('period'));
        $today = Carbon::today();

        [$defaultFrom, $defaultTo] = match ($period !== '' ? $period : $defaultPeriod) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            default => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
        };

        $fromDate = Carbon::parse((string) $request->input('date_from', $request->input('from', $defaultFrom)))->toDateString();
        $toDate = Carbon::parse((string) $request->input('date_to', $request->input('to', $defaultTo)))->toDateString();

        if ($fromDate > $toDate) {
            [$fromDate, $toDate] = [$toDate, $fromDate];
        }

        return [$fromDate, $toDate, $period];
    }

    private function collectionTotal(string $fromDate, string $toDate): float
    {
        $saleTimeCollections = DB::query()
            ->fromSub(
                Sale::query()
                    ->where('sales.status', 'posted')
                    ->leftJoin('customer_payments', function ($join) {
                        $join->on('customer_payments.sale_id', '=', 'sales.id')
                            ->where('customer_payments.status', '=', 'posted');
                    })
                    ->whereDate('sales.sale_date', '>=', $fromDate)
                    ->whereDate('sales.sale_date', '<=', $toDate)
                    ->groupBy('sales.id', 'sales.amount_paid')
                    ->selectRaw('CASE WHEN sales.amount_paid - COALESCE(SUM(customer_payments.amount), 0) > 0 THEN sales.amount_paid - COALESCE(SUM(customer_payments.amount), 0) ELSE 0 END as amount'),
                'sale_time_collections'
            )
            ->sum('amount');

        $customerPayments = CustomerPayment::query()
            ->posted()
            ->whereDate('payment_date', '>=', $fromDate)
            ->whereDate('payment_date', '<=', $toDate)
            ->sum('amount');

        return round((float) $saleTimeCollections + (float) $customerPayments, 2);
    }
}
