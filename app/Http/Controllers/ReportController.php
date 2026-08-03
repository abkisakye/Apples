<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Purchase;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Sale;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\ExcelExportService;
use App\Support\FinancialReportsService;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function stockValuation(Request $request, FinancialReportsService $financialReportsService): View
    {
        $data = $financialReportsService->stockValuation($request);

        return view('reports.stock_valuation', [
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'stores' => Store::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'storeId' => $request->integer('store_id'),
            'categoryId' => $request->integer('category_id'),
            'search' => trim((string) $request->query('q', $request->query('search', ''))),
            'costSource' => (string) $request->query('cost_source', 'all'),
            'includeZeroStock' => $request->boolean('include_zero_stock'),
        ]);
    }

    public function priceMargins(Request $request, FinancialReportsService $financialReportsService): View
    {
        $data = $financialReportsService->priceMargins($request);

        return view('reports.price_margins', [
            'rows' => $data['rows'],
            'summary' => $data['summary'],
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
            'categoryId' => $request->integer('category_id'),
            'search' => trim((string) $request->query('q', $request->query('search', ''))),
            'status' => (string) $request->query('status', 'all'),
        ]);
    }

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

    public function dailySalesSummary(Request $request): View
    {
        $data = $this->dailySalesSummaryData($request);

        return view('reports.daily_sales_summary', $data);
    }

    public function dailySalesSummaryExport(Request $request, ExcelExportService $excelExportService): BinaryFileResponse
    {
        $data = $this->dailySalesSummaryData($request);
        $rows = collect();

        foreach ($data['shopGroups'] as $shopGroup) {
            foreach ($shopGroup['saleGroups'] as $saleGroup) {
                foreach ($saleGroup['rows'] as $index => $row) {
                    $rows->push([
                        $shopGroup['store_name'],
                        $saleGroup['label'],
                        $index + 1,
                        $row->item_label,
                        (float) $row->quantity,
                        round((float) $row->average_rate, 2),
                        round((float) $row->total_amount, 2),
                    ]);
                }

                $rows->push([
                    $shopGroup['store_name'],
                    'Total '.$saleGroup['label'],
                    '',
                    '',
                    '',
                    '',
                    round((float) $saleGroup['total'], 2),
                ]);
            }

            $rows->push([
                'Total '.$shopGroup['store_name'],
                '',
                '',
                '',
                '',
                '',
                round((float) $shopGroup['total'], 2),
            ]);
        }

        $rows->push(['Grand Total', '', '', '', '', '', round((float) $data['grandTotal'], 2)]);

        return $excelExportService->download('daily-sales-summary.xlsx', [
            'Shop',
            'Sale Group',
            'S/N',
            'Item',
            'Qty',
            'Av. rate',
            'Total Amount',
        ], $rows);
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

    private function dailySalesSummaryData(Request $request): array
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request, 'today');

        $storeId = (int) $request->integer('store_id');
        $paymentModeId = (int) $request->integer('payment_mode_id');
        $saleType = trim((string) $request->string('sale_type'));
        $status = trim((string) $request->string('status', 'posted')) ?: 'posted';

        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('stores', 'stores.id', '=', 'sales.store_id')
            ->leftJoin('payment_modes', 'payment_modes.id', '=', 'sales.payment_mode_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('product_units', 'product_units.id', '=', 'sale_items.product_unit_id')
            ->whereDate('sales.sale_date', '>=', $fromDate)
            ->whereDate('sales.sale_date', '<=', $toDate)
            ->whereNotIn('sales.status', ['void', 'voided', 'cancelled', 'canceled'])
            ->when($status !== 'all', fn ($inner) => $inner->where('sales.status', $status))
            ->when($storeId > 0, fn ($inner) => $inner->where('sales.store_id', $storeId))
            ->when($paymentModeId > 0, fn ($inner) => $inner->where('sales.payment_mode_id', $paymentModeId))
            ->when($saleType !== '' && $saleType !== 'all', fn ($inner) => $inner->where('sales.sale_type', $saleType))
            ->selectRaw('
                sales.store_id,
                stores.name as store_name,
                sales.payment_mode_id,
                COALESCE(payment_modes.name, "") as payment_mode_name,
                sales.sale_type,
                sale_items.product_id,
                sale_items.product_unit_id,
                products.name as product_name,
                product_units.unit_name,
                sale_items.unit_price,
                COALESCE(SUM(sale_items.quantity), 0) as quantity,
                COALESCE(SUM(sale_items.line_total), 0) as total_amount
            ')
            ->groupBy(
                'sales.store_id',
                'stores.name',
                'sales.payment_mode_id',
                'payment_modes.name',
                'sales.sale_type',
                'sale_items.product_id',
                'sale_items.product_unit_id',
                'products.name',
                'product_units.unit_name',
                'sale_items.unit_price'
            )
            ->orderBy('stores.name')
            ->orderBy('payment_modes.name')
            ->orderBy('sales.sale_type')
            ->orderBy('products.name')
            ->orderBy('product_units.unit_name');

        $rows = $query->get()->map(function ($row) {
            $quantity = (float) $row->quantity;
            $totalAmount = (float) $row->total_amount;
            $row->item_label = trim($row->product_name.' - '.$row->unit_name);
            $row->sale_group_label = $this->saleGroupLabel((string) $row->sale_type, (string) $row->payment_mode_name);
            $row->average_rate = $quantity != 0.0 ? round($totalAmount / $quantity, 2) : 0.0;

            return $row;
        });

        $shopGroups = $rows
            ->groupBy('store_id')
            ->map(function (Collection $storeRows) {
                $saleGroups = $storeRows
                    ->groupBy('sale_group_label')
                    ->map(fn (Collection $groupRows, string $label) => [
                        'label' => $label,
                        'rows' => $groupRows->values(),
                        'total' => (float) $groupRows->sum('total_amount'),
                    ])
                    ->values();

                return [
                    'store_id' => (int) $storeRows->first()->store_id,
                    'store_name' => $storeRows->first()->store_name,
                    'saleGroups' => $saleGroups,
                    'total' => (float) $storeRows->sum('total_amount'),
                ];
            })
            ->values();

        return [
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'filters' => [
                'store_id' => $storeId,
                'payment_mode_id' => $paymentModeId,
                'sale_type' => $saleType,
                'status' => $status,
            ],
            'stores' => DB::table('stores')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'paymentModes' => DB::table('payment_modes')->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'shopGroups' => $shopGroups,
            'grandTotal' => (float) $rows->sum('total_amount'),
        ];
    }

    private function saleGroupLabel(string $saleType, string $paymentModeName): string
    {
        if ($saleType === 'credit') {
            return 'Credit Sale';
        }

        $mode = trim($paymentModeName);

        return $mode !== '' ? $mode.' Sale' : ucfirst($saleType ?: 'cash').' Sale';
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
