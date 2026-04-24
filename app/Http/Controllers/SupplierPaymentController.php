<?php

namespace App\Http\Controllers;

use App\Models\PaymentMode;
use App\Models\Purchase;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SupplierPaymentController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $supplierId = $request->integer('supplier_id');
        $period = trim((string) $request->string('period'));
        [$fromDate, $toDate] = $this->periodRange($period);

        return view('supplier_payments.index', [
            'search' => $search,
            'supplierId' => $supplierId,
            'period' => $period,
            'suppliers' => Supplier::query()
                ->where('is_system', false)
                ->orderBy('name')
                ->get(['id', 'name']),
            'payments' => SupplierPayment::query()
                ->with(['supplier:id,name,country', 'purchase:id,purchase_no', 'store:id,name', 'paymentMode:id,name'])
                ->when($supplierId > 0, fn ($query) => $query->where('supplier_id', $supplierId))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('payment_no', 'like', "%{$search}%")
                            ->orWhere('supplier_invoice_no', 'like', "%{$search}%")
                            ->orWhere('reference_no', 'like', "%{$search}%")
                            ->orWhere('cheque_number', 'like', "%{$search}%")
                            ->orWhereHas('supplier', fn ($supplierQuery) => $supplierQuery->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('purchase', fn ($purchaseQuery) => $purchaseQuery->where('purchase_no', 'like', "%{$search}%"));
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
        $supplierId = $request->integer('supplier_id');

        return view('supplier_payments.create', [
            'suppliers' => Supplier::query()
                ->where('is_system', false)
                ->withSum(['purchases as outstanding_credit' => fn ($query) => $query->posted()], 'balance_due')
                ->orderBy('name')
                ->get(['id', 'name', 'country']),
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'outstandingPurchases' => Purchase::query()
                ->with(['supplier:id,name', 'store:id,name'])
                ->posted()
                ->where('balance_due', '>', 0)
                ->when($supplierId > 0, fn ($query) => $query->where('supplier_id', $supplierId))
                ->orderBy('purchase_date')
                ->get(['id', 'purchase_no', 'purchase_date', 'supplier_id', 'store_id', 'balance_due', 'credit_due_date']),
            'recentPayments' => SupplierPayment::query()
                ->with(['supplier:id,name', 'purchase:id,purchase_no'])
                ->latest('payment_date')
                ->latest('id')
                ->limit(12)
                ->get(),
        ]);
    }

    public function show(SupplierPayment $supplierPayment): View
    {
        $supplierPayment->load([
            'supplier:id,name,phone,country,address',
            'purchase:id,purchase_no,purchase_date,purchase_type,balance_due,total_amount,supplier_invoice_no',
            'store:id,name',
            'paymentMode:id,name',
        ]);

        return view('supplier_payments.show', ['payment' => $supplierPayment]);
    }

    public function print(SupplierPayment $supplierPayment): View
    {
        $supplierPayment->load([
            'supplier:id,name,phone,country,address',
            'purchase:id,purchase_no,purchase_date,purchase_type,balance_due,total_amount,supplier_invoice_no',
            'store:id,name',
            'paymentMode:id,name',
        ]);

        return view('supplier_payments.print', ['payment' => $supplierPayment]);
    }

    public function store(Request $request, DocumentNumberService $documentNumberService, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'purchase_id' => ['required', 'exists:purchases,id'],
            'payment_mode_id' => ['nullable', 'exists:payment_modes,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ]);

        /** @var Purchase $purchase */
        $purchase = Purchase::query()->posted()->findOrFail($validated['purchase_id']);

        if ((int) $purchase->supplier_id !== (int) $validated['supplier_id']) {
            throw ValidationException::withMessages([
                'purchase_id' => 'The selected purchase does not belong to the selected supplier.',
            ]);
        }

        $amount = round((float) $validated['amount'], 2);
        $balanceDue = round((float) $purchase->balance_due, 2);

        if ($balanceDue <= 0 || $amount > $balanceDue) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must not exceed the outstanding supplier balance.',
            ]);
        }

        $payment = DB::transaction(function () use ($validated, $purchase, $amount, $documentNumberService) {
            $payment = SupplierPayment::create([
                'payment_no' => $documentNumberService->make('supplier_payment', $validated['payment_date']),
                'payment_date' => $validated['payment_date'],
                'supplier_id' => $validated['supplier_id'],
                'purchase_id' => $purchase->id,
                'store_id' => $purchase->store_id,
                'payment_mode_id' => $validated['payment_mode_id'] ?? null,
                'amount' => $amount,
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'cheque_number' => $validated['cheque_number'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $purchase->update([
                'amount_paid' => round((float) $purchase->amount_paid + $amount, 2),
                'balance_due' => round((float) $purchase->balance_due - $amount, 2),
            ]);

            return $payment;
        });

        $auditLogService->record('supplier_payment.posted', $payment, "Supplier payment {$payment->payment_no} posted.", [
            'amount' => $payment->amount,
            'purchase_id' => $payment->purchase_id,
        ]);

        return redirect()
            ->route('supplier-payments.show', $payment)
            ->with('status', "Supplier payment {$payment->payment_no} posted successfully.")
            ->with('auto_print_receipt', true);
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
