<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuditLogService;
use App\Services\ExcelExportService;
use App\Services\PdfExportService;
use Illuminate\Support\Collection;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class CustomerController extends Controller
{
    public function create(): View
    {
        return view('customers.create', [
            'customer' => new Customer(['is_active' => true]),
        ]);
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $accountType = trim((string) $request->string('type'));
        $status = trim((string) $request->string('status'));

        $customersQuery = Customer::query()
            ->withCount(['sales as sales_count' => fn ($query) => $query->posted(), 'payments'])
            ->withSum(['creditSales as credit_sales_total' => fn ($query) => $query->posted()], 'total_amount')
            ->withSum(['creditSales as outstanding_balance' => fn ($query) => $query->posted()->where('balance_due', '>', 0)], 'balance_due')
            ->withSum('payments as payments_total', 'amount')
            ->withSum(['saleReturns as returns_total' => fn ($query) => $query->where('status', 'posted')], 'returned_total')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($accountType === 'walk_in', fn ($query) => $query->where('is_walk_in', true))
            ->when($accountType === 'credit', fn ($query) => $query->where('credit_limit', '>', 0))
            ->when($accountType === 'regular', fn ($query) => $query->where('is_walk_in', false)->where('is_system', false))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderByDesc('is_walk_in')
            ->orderBy('name');

        $customers = (clone $customersQuery)
            ->paginate(20)
            ->withQueryString();

        $summaryBase = Customer::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($accountType === 'walk_in', fn ($query) => $query->where('is_walk_in', true))
            ->when($accountType === 'credit', fn ($query) => $query->where('credit_limit', '>', 0))
            ->when($accountType === 'regular', fn ($query) => $query->where('is_walk_in', false)->where('is_system', false))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false));

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'accountType' => $accountType,
            'statusFilter' => $status,
            'customerSummary' => [
                'total' => (clone $summaryBase)->count(),
                'walk_in' => (clone $summaryBase)->where('is_walk_in', true)->count(),
                'with_phone' => (clone $summaryBase)->whereNotNull('phone')->count(),
                'credit_accounts' => (clone $summaryBase)->where('credit_limit', '>', 0)->count(),
                'opening_balance' => (float) (clone $summaryBase)->sum('opening_balance'),
            ],
        ]);
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $customer = Customer::query()->create($this->validateCustomer($request));

        $auditLogService->record('customer.created', $customer, "Customer {$customer->name} created.", [
            'customer_id' => $customer->id,
        ]);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "Customer {$customer->name} saved successfully.");
    }

    public function show(Customer $customer): View
    {
        $customer->load([
            'sales.store:id,name',
            'payments.sale:id,sale_no',
            'payments.paymentMode:id,name',
            'saleReturns' => fn ($query) => $query->where('status', 'posted')->with(['sale:id,sale_no', 'paymentMode:id,name']),
        ]);

        [$entries, $summary] = $this->statementData($customer);

        $recentSales = $customer->sales()
            ->posted()
            ->with('store:id,name')
            ->latest('sale_date')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'sale_no', 'sale_date', 'store_id', 'sale_type', 'total_amount', 'balance_due']);

        $recentPayments = $customer->payments()
            ->with(['sale:id,sale_no', 'paymentMode:id,name'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'payment_no', 'payment_date', 'sale_id', 'payment_mode_id', 'amount']);

        $followUps = DB::table('follow_up_actions')
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'sent'])
            ->count();

        return view('customers.show', [
            'customer' => $customer,
            'entries' => $entries->sortByDesc(fn (array $entry) => optional($entry['date'])?->timestamp ?? 0)->take(10)->values(),
            'summary' => $summary,
            'recentSales' => $recentSales,
            'recentPayments' => $recentPayments,
            'followUps' => $followUps,
        ]);
    }

    public function edit(Customer $customer): View
    {
        return view('customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer, AuditLogService $auditLogService): RedirectResponse
    {
        $customer->update($this->validateCustomer($request, $customer));

        $auditLogService->record('customer.updated', $customer, "Customer {$customer->name} updated.", [
            'customer_id' => $customer->id,
        ]);

        return redirect()
            ->route('customers.show', $customer)
            ->with('status', "Customer {$customer->name} updated successfully.");
    }

    public function updateStatus(Request $request, Customer $customer, AuditLogService $auditLogService): RedirectResponse
    {
        abort_if($customer->is_system, 422, 'System customers cannot be archived.');

        $customer->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        $auditLogService->record('customer.status_updated', $customer, "Customer {$customer->name} status updated.", [
            'customer_id' => $customer->id,
            'is_active' => $customer->is_active,
        ]);

        return redirect()
            ->route('customers.index')
            ->with('status', "Customer {$customer->name} marked as ".($customer->is_active ? 'active' : 'inactive').'.');
    }

    public function statement(Customer $customer): View
    {
        [$entries, $summary] = $this->statementData($customer);

        return view('customers.statement', compact('customer', 'entries', 'summary'));
    }

    public function printStatement(Customer $customer): View
    {
        [$entries, $summary] = $this->statementData($customer);

        return view('customers.statement_print', compact('customer', 'entries', 'summary'));
    }

    public function pdfStatement(Customer $customer, PdfExportService $pdfExportService): Response
    {
        [$entries, $summary] = $this->statementData($customer);

        return $pdfExportService->download(
            'customers.statement_print',
            compact('customer', 'entries', 'summary'),
            "customer-statement-{$customer->id}.pdf"
        );
    }

    public function exportStatement(Customer $customer, ExcelExportService $excelExportService): BinaryFileResponse
    {
        [$entries] = $this->statementData($customer);

        return $excelExportService->download("customer-statement-{$customer->id}.xlsx", [
            'Date', 'Type', 'Reference', 'Details', 'Debit', 'Credit', 'Running Balance',
        ], $entries->map(fn (array $entry) => [
            optional($entry['date'])->format('Y-m-d'),
            $entry['type'],
            $entry['reference'],
            $entry['details'],
            $entry['debit'],
            $entry['credit'],
            $entry['running_balance'],
        ]));
    }

    public function quickStore(Request $request, AuditLogService $auditLogService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::query()->create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            'location' => $validated['location'] ?? null,
            'is_walk_in' => false,
            'is_system' => false,
            'is_active' => true,
        ]);

        $auditLogService->record('customer.quick_created', $customer, "Customer {$customer->name} added during sale entry.", [
            'customer_id' => $customer->id,
        ]);

        return response()->json([
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'location' => $customer->location,
        ], 201);
    }

    private function validateCustomer(Request $request, ?Customer $customer = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('customers', 'name')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'fax' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'customer_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['opening_balance'] = round((float) ($validated['opening_balance'] ?? 0), 2);
        $validated['credit_limit'] = round((float) ($validated['credit_limit'] ?? 0), 2);
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function statementData(Customer $customer): array
    {
        $customer->load([
            'creditSales.store:id,name',
            'creditSales.paymentMode:id,name',
            'payments.sale:id,sale_no',
            'payments.paymentMode:id,name',
            'saleReturns' => fn ($query) => $query->where('status', 'posted')->with(['sale:id,sale_no', 'paymentMode:id,name']),
        ]);

        $entries = new Collection();

        foreach ($customer->creditSales->sortBy('sale_date') as $sale) {
            $entries->push([
                'date' => $sale->sale_date,
                'type' => 'Credit Sale',
                'reference' => $sale->sale_no,
                'details' => $sale->store?->name ?? 'No Store',
                'debit' => (float) $sale->total_amount,
                'credit' => 0.0,
            ]);

            if ((float) $sale->amount_paid > 0) {
                $entries->push([
                    'date' => $sale->sale_date,
                    'type' => 'Paid at Sale',
                    'reference' => $sale->sale_no.'-PAID',
                    'details' => $sale->paymentMode?->name ?? 'Sale payment',
                    'debit' => 0.0,
                    'credit' => (float) $sale->amount_paid,
                ]);
            }
        }

        foreach ($customer->payments->sortBy('payment_date') as $payment) {
            $entries->push([
                'date' => $payment->payment_date,
                'type' => 'Payment',
                'reference' => $payment->payment_no,
                'details' => $payment->sale?->sale_no ?? ($payment->paymentMode?->name ?? 'Payment'),
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);
        }

        foreach ($customer->saleReturns->sortBy('return_date') as $return) {
            $entries->push([
                'date' => $return->return_date,
                'type' => 'Sale Return',
                'reference' => $return->return_no,
                'details' => $return->sale?->sale_no ?? ($return->paymentMode?->name ?? 'Return'),
                'debit' => 0.0,
                'credit' => (float) $return->returned_total,
            ]);
        }

        $entries = $entries->sortBy([
            ['date', 'asc'],
            ['reference', 'asc'],
        ])->values();

        $balance = (float) $customer->opening_balance;

        $entries = $entries->map(function (array $entry) use (&$balance) {
            $balance += $entry['debit'];
            $balance -= $entry['credit'];
            $entry['running_balance'] = round($balance, 2);

            return $entry;
        });

        $summary = [
            'opening_balance' => (float) $customer->opening_balance,
            'total_sales' => (float) $customer->creditSales->sum('total_amount'),
            'total_payments' => (float) $customer->payments->sum('amount')
                + (float) $customer->creditSales->sum('amount_paid'),
            'total_returns' => (float) $customer->saleReturns->sum('returned_total'),
            'closing_balance' => round($balance, 2),
        ];

        return [$entries, $summary];
    }
}
