<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\PaymentMode;
use App\Models\Sale;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\AccessService;
use App\Support\StoreAssignmentService;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CustomerPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $customerId = $request->integer('customer_id');
        $period = trim((string) $request->string('period'));
        [$fromDate, $toDate] = $this->periodRange($period);

        return view('customer_payments.index', [
            'search' => $search,
            'customerId' => $customerId,
            'period' => $period,
            'customers' => Customer::query()
                ->where('is_system', false)
                ->orderBy('name')
                ->get(['id', 'name']),
            'payments' => CustomerPayment::query()
                ->with(['customer:id,name,location', 'sale:id,sale_no', 'store:id,name', 'paymentMode:id,name'])
                ->when($customerId > 0, fn ($query) => $query->where('customer_id', $customerId))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('payment_no', 'like', "%{$search}%")
                            ->orWhere('reference_no', 'like', "%{$search}%")
                            ->orWhere('cheque_number', 'like', "%{$search}%")
                            ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('sale', fn ($saleQuery) => $saleQuery->where('sale_no', 'like', "%{$search}%"));
                    });
                })
                ->when($fromDate && $toDate, fn ($query) => $query->whereBetween('payment_date', [$fromDate, $toDate]))
                ->latest('payment_date')
                ->latest('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View
    {
        $customerId = $request->integer('customer_id');
        $access = app(AccessService::class);
        $defaultStoreId = auth()->user()?->default_store_id;
        $customers = Customer::query()
            ->where('is_system', false)
            ->withSum(['sales as outstanding_credit' => fn ($query) => $query->posted()->where('sale_type', 'credit')], 'balance_due')
            ->withSum(['openingBalancePayments as opening_payments_total' => fn ($query) => $query->posted()], 'amount')
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'opening_balance', 'opening_balance_date']);

        $openingBalanceRows = $customers
            ->map(function (Customer $customer) {
                $openingOutstanding = round(max((float) $customer->opening_balance - (float) ($customer->opening_payments_total ?? 0), 0), 2);

                if ($openingOutstanding <= 0) {
                    return null;
                }

                return (object) [
                    'id' => null,
                    'account_reference_type' => 'opening_balance',
                    'sale_no' => 'Opening Balance',
                    'sale_date' => $customer->opening_balance_date,
                    'customer_id' => $customer->id,
                    'customer' => $customer,
                    'store' => null,
                    'balance_due' => $openingOutstanding,
                    'credit_due_date' => $customer->opening_balance_date,
                ];
            })
            ->filter()
            ->values();

        return view('customer_payments.create', [
            'customers' => $customers,
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'outstandingSales' => Sale::query()
                ->with(['customer:id,name', 'store:id,name'])
                ->posted()
                ->where('balance_due', '>', 0)
                ->when($customerId > 0, fn ($query) => $query->where('customer_id', $customerId))
                ->when($defaultStoreId && ! $access->can('sales.override') && ! $access->hasRole('admin') && ! $access->hasRole('manager'), fn ($query) => $query->where('store_id', $defaultStoreId))
                ->orderBy('sale_date')
                ->get(['id', 'sale_no', 'sale_date', 'customer_id', 'store_id', 'balance_due', 'credit_due_date']),
            'openingBalanceRows' => $customerId > 0
                ? $openingBalanceRows->where('customer_id', $customerId)->values()
                : $openingBalanceRows,
            'recentPayments' => CustomerPayment::query()
                ->with(['customer:id,name', 'sale:id,sale_no'])
                ->latest('payment_date')
                ->latest('id')
                ->limit(12)
                ->get(),
        ]);
    }

    public function show(CustomerPayment $customerPayment): View
    {
        $customerPayment->load([
            'customer:id,name,phone,location,opening_balance,opening_balance_date',
            'sale:id,sale_no,sale_date,sale_type,balance_due,total_amount',
            'store:id,name',
            'paymentMode:id,name',
        ]);

        return view('customer_payments.show', [
            'payment' => $customerPayment,
            'accountSummary' => $this->paymentAccountSummary($customerPayment),
        ]);
    }

    public function print(CustomerPayment $customerPayment): View
    {
        $customerPayment->load([
            'customer:id,name,phone,location,opening_balance,opening_balance_date',
            'sale:id,sale_no,sale_date,sale_type,balance_due,total_amount',
            'store:id,name',
            'paymentMode:id,name',
        ]);

        return view('customer_payments.print', [
            'payment' => $customerPayment,
            'accountSummary' => $this->paymentAccountSummary($customerPayment),
        ]);
    }

    public function store(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'customer_id' => ['required', 'exists:customers,id'],
            'account_reference_type' => ['required', Rule::in(['sale', 'opening_balance'])],
            'sale_id' => ['nullable', 'exists:sales,id'],
            'payment_mode_id' => ['required', 'exists:payment_modes,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        $customer = Customer::query()->findOrFail($validated['customer_id']);
        $sale = null;

        $amount = round((float) $validated['amount'], 2);

        if ($validated['account_reference_type'] === 'sale') {
            if (empty($validated['sale_id'])) {
                throw ValidationException::withMessages([
                    'sale_id' => 'Choose the open sale before recording the payment.',
                ]);
            }

            /** @var Sale $sale */
            $sale = Sale::query()->posted()->findOrFail($validated['sale_id']);

            if ((int) $sale->customer_id !== (int) $validated['customer_id']) {
                throw ValidationException::withMessages([
                    'sale_id' => 'The selected sale does not belong to the selected customer.',
                ]);
            }

            $balanceDue = round((float) $sale->balance_due, 2);

            if ($balanceDue <= 0 || $amount > $balanceDue) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must not exceed the outstanding balance.',
                ]);
            }
        } else {
            $openingOutstanding = $customer->openingBalanceOutstanding();

            if ($openingOutstanding <= 0 || $amount > $openingOutstanding) {
                throw ValidationException::withMessages([
                    'amount' => 'Payment amount must not exceed the opening balance still owed.',
                ]);
            }
        }

        $storeId = $storeAssignmentService->resolveStoreId(
            (int) ($sale?->store_id ?? auth()->user()?->default_store_id),
            $request->user(),
            app(AccessService::class)
        );

        $payment = DB::transaction(function () use ($validated, $sale, $customer, $amount, $documentNumberService, $storeId) {
            $payment = CustomerPayment::create([
                'payment_no' => $documentNumberService->make('customer_payment', $validated['payment_date']),
                'payment_date' => $validated['payment_date'],
                'customer_id' => $validated['customer_id'],
                'sale_id' => $sale?->id,
                'account_reference_type' => $validated['account_reference_type'],
                'store_id' => $storeId,
                'payment_mode_id' => $validated['payment_mode_id'],
                'amount' => $amount,
                'reference_no' => $validated['reference_no'] ?? null,
                'cheque_number' => $validated['cheque_number'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            if ($sale) {
                $sale->update([
                    'amount_paid' => round((float) $sale->amount_paid + $amount, 2),
                    'balance_due' => round((float) $sale->balance_due - $amount, 2),
                ]);
            }

            return $payment;
        });

        $auditLogService->record('customer_payment.posted', $payment, "Customer payment {$payment->payment_no} posted.", [
            'amount' => $payment->amount,
            'sale_id' => $payment->sale_id,
            'account_reference_type' => $payment->account_reference_type,
            'customer_opening_balance_remaining' => $validated['account_reference_type'] === 'opening_balance'
                ? $customer->fresh()->openingBalanceOutstanding()
                : null,
        ]);

        return redirect()
            ->route('customer-payments.show', $payment)
            ->with('status', "Customer payment {$payment->payment_no} posted successfully.")
            ->with('auto_print_receipt', true);
    }

    private function paymentAccountSummary(CustomerPayment $payment): array
    {
        $accountType = $payment->account_reference_type === 'opening_balance' ? 'opening_balance' : 'sale';
        $remainingBalance = $accountType === 'opening_balance'
            ? $payment->customer?->openingBalanceOutstanding() ?? 0
            : (float) ($payment->sale?->balance_due ?? 0);

        return [
            'account_label' => $accountType === 'opening_balance' ? 'Opening Balance' : 'Sale',
            'account_reference' => $accountType === 'opening_balance'
                ? 'Opening Balance'
                : ($payment->sale?->sale_no ?? '-'),
            'account_total' => $accountType === 'opening_balance'
                ? (float) ($payment->customer?->opening_balance ?? 0)
                : (float) ($payment->sale?->total_amount ?? 0),
            'remaining_balance' => (float) $remainingBalance,
        ];
    }

    private function periodRange(string $period): array
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            default => [null, null],
        };
    }
}
