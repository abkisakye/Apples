<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\AuditLogService;
use App\Services\ExcelExportService;
use App\Services\PdfExportService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SupplierController extends Controller
{
    public function create(): View
    {
        return view('suppliers.create', [
            'supplier' => new Supplier(['is_active' => true]),
        ]);
    }

    public function index(Request $request): View
    {
        $search = trim((string) ($request->query('q', $request->query('search', ''))));
        $status = trim((string) $request->string('status'));

        $suppliersQuery = Supplier::query()
            ->withCount([
                'purchases as purchases_count' => fn ($query) => $query->posted(),
                'payments',
            ])
            ->withSum(['purchases as purchases_total' => fn ($query) => $query->posted()], 'total_amount')
            ->withSum(['purchases as outstanding_balance' => fn ($query) => $query->posted()->where('balance_due', '>', 0)], 'balance_due')
            ->withSum('payments as payments_total', 'amount')
            ->withSum(['purchaseReturns as returns_total' => fn ($query) => $query->where('status', 'posted')], 'returned_total')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('tin', 'like', "%{$search}%")
                        ->orWhere('supplier_type', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name');

        $suppliers = (clone $suppliersQuery)
            ->paginate(20)
            ->withQueryString();

        $summaryBase = Supplier::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('country', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('tin', 'like', "%{$search}%")
                        ->orWhere('supplier_type', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%");
                });
            })
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false));

        $viewData = [
            'suppliers' => $suppliers,
            'search' => $search,
            'statusFilter' => $status,
            'supplierSummary' => [
                'total' => (clone $summaryBase)->count(),
                'active' => (clone $summaryBase)->where('is_active', true)->count(),
                'with_phone' => (clone $summaryBase)->whereNotNull('phone')->count(),
                'with_tin' => (clone $summaryBase)->whereNotNull('tin')->count(),
                'opening_balance' => (float) (clone $summaryBase)->sum('opening_balance'),
            ],
        ];

        if ($request->ajax()) {
            return view('suppliers.partials.index_results', $viewData);
        }

        return view('suppliers.index', $viewData);
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $supplier = Supplier::query()->create($this->validateSupplier($request));

        $auditLogService->record('supplier.created', $supplier, "Supplier {$supplier->name} created.", [
            'supplier_id' => $supplier->id,
        ]);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('status', "Supplier {$supplier->name} saved successfully.");
    }

    public function show(Supplier $supplier): View
    {
        $supplier->load([
            'purchases.store:id,name',
            'payments.purchase:id,purchase_no',
            'payments.paymentMode:id,name',
            'purchaseReturns.purchase:id,purchase_no',
            'purchaseReturns.paymentMode:id,name',
        ]);

        [$entries, $summary] = $this->statementData($supplier);

        $recentPurchases = $supplier->purchases()
            ->posted()
            ->with('store:id,name')
            ->latest('purchase_date')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'purchase_no', 'purchase_date', 'store_id', 'purchase_type', 'total_amount', 'balance_due']);

        $recentPayments = $supplier->payments()
            ->with(['purchase:id,purchase_no', 'paymentMode:id,name'])
            ->latest('payment_date')
            ->latest('id')
            ->limit(8)
            ->get(['id', 'payment_no', 'payment_date', 'purchase_id', 'payment_mode_id', 'amount']);

        return view('suppliers.show', [
            'supplier' => $supplier,
            'entries' => $entries->sortByDesc(fn (array $entry) => optional($entry['date'])?->timestamp ?? 0)->take(10)->values(),
            'summary' => $summary,
            'recentPurchases' => $recentPurchases,
            'recentPayments' => $recentPayments,
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.edit', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier, AuditLogService $auditLogService): RedirectResponse
    {
        $supplier->update($this->validateSupplier($request, $supplier));

        $auditLogService->record('supplier.updated', $supplier, "Supplier {$supplier->name} updated.", [
            'supplier_id' => $supplier->id,
        ]);

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('status', "Supplier {$supplier->name} updated successfully.");
    }

    public function updateStatus(Request $request, Supplier $supplier, AuditLogService $auditLogService): RedirectResponse
    {
        abort_if($supplier->is_system, 422, 'System suppliers cannot be archived.');

        $supplier->update([
            'is_active' => $request->boolean('is_active'),
        ]);

        $auditLogService->record('supplier.status_updated', $supplier, "Supplier {$supplier->name} status updated.", [
            'supplier_id' => $supplier->id,
            'is_active' => $supplier->is_active,
        ]);

        return redirect()
            ->route('suppliers.index')
            ->with('status', "Supplier {$supplier->name} marked as ".($supplier->is_active ? 'active' : 'inactive').'.');
    }

    public function statement(Supplier $supplier): View
    {
        [$entries, $summary] = $this->statementData($supplier);

        return view('suppliers.statement', compact('supplier', 'entries', 'summary'));
    }

    public function printStatement(Supplier $supplier): View
    {
        [$entries, $summary] = $this->statementData($supplier);

        return view('suppliers.statement_print', compact('supplier', 'entries', 'summary'));
    }

    public function pdfStatement(Supplier $supplier, PdfExportService $pdfExportService): Response
    {
        [$entries, $summary] = $this->statementData($supplier);

        return $pdfExportService->download(
            'suppliers.statement_print',
            compact('supplier', 'entries', 'summary'),
            "supplier-statement-{$supplier->id}.pdf"
        );
    }

    public function exportStatement(Supplier $supplier, ExcelExportService $excelExportService): BinaryFileResponse
    {
        [$entries] = $this->statementData($supplier);

        return $excelExportService->download("supplier-statement-{$supplier->id}.xlsx", [
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

    private function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'tin' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'supplier_type' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['opening_balance'] = round((float) ($validated['opening_balance'] ?? 0), 2);
        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    private function statementData(Supplier $supplier): array
    {
        $supplier->load([
            'purchases' => fn ($query) => $query->posted()->with(['store:id,name', 'paymentMode:id,name']),
            'payments.purchase:id,purchase_no',
            'payments.paymentMode:id,name',
            'purchaseReturns' => fn ($query) => $query->where('status', 'posted')->with(['purchase:id,purchase_no', 'paymentMode:id,name']),
        ]);

        $entries = new Collection();

        foreach ($supplier->purchases->sortBy('purchase_date') as $purchase) {
            $entries->push([
                'date' => $purchase->purchase_date,
                'type' => 'Purchase',
                'reference' => $purchase->purchase_no,
                'details' => $purchase->store?->name ?? 'No Store',
                'debit' => (float) $purchase->total_amount,
                'credit' => 0.0,
            ]);

            if ((float) $purchase->amount_paid > 0) {
                $entries->push([
                    'date' => $purchase->purchase_date,
                    'type' => 'Paid on Purchase',
                    'reference' => $purchase->purchase_no.'-PAID',
                    'details' => $purchase->paymentMode?->name ?? 'Purchase payment',
                    'debit' => 0.0,
                    'credit' => (float) $purchase->amount_paid,
                ]);
            }
        }

        foreach ($supplier->payments->sortBy('payment_date') as $payment) {
            $entries->push([
                'date' => $payment->payment_date,
                'type' => 'Payment',
                'reference' => $payment->payment_no,
                'details' => $payment->purchase?->purchase_no ?? ($payment->paymentMode?->name ?? 'Payment'),
                'debit' => 0.0,
                'credit' => (float) $payment->amount,
            ]);
        }

        foreach ($supplier->purchaseReturns->sortBy('return_date') as $return) {
            $entries->push([
                'date' => $return->return_date,
                'type' => 'Supplier Return',
                'reference' => $return->return_no,
                'details' => $return->purchase?->purchase_no ?? ($return->paymentMode?->name ?? 'Return'),
                'debit' => 0.0,
                'credit' => (float) $return->returned_total,
            ]);
        }

        $entries = $entries->sortBy([
            ['date', 'asc'],
            ['reference', 'asc'],
        ])->values();

        $balance = (float) $supplier->opening_balance;

        $entries = $entries->map(function (array $entry) use (&$balance) {
            $balance += $entry['debit'];
            $balance -= $entry['credit'];
            $entry['running_balance'] = round($balance, 2);

            return $entry;
        });

        $summary = [
            'opening_balance' => (float) $supplier->opening_balance,
            'total_purchases' => (float) $supplier->purchases->sum('total_amount'),
            'total_payments' => (float) $supplier->payments->sum('amount')
                + (float) $supplier->purchases->sum('amount_paid'),
            'total_returns' => (float) $supplier->purchaseReturns->sum('returned_total'),
            'closing_balance' => round($balance, 2),
        ];

        return [$entries, $summary];
    }
}
