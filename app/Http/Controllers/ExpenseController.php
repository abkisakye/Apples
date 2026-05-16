<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\PaymentMode;
use App\Models\Store;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\AccessService;
use App\Support\StoreAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $category = trim((string) $request->string('category'));
        $storeId = $request->integer('store_id');
        $period = trim((string) $request->string('period'));
        [$fromDate, $toDate] = $this->periodRange($period);

        $expensesQuery = Expense::query()
            ->with(['store:id,name', 'paymentMode:id,name', 'creator:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('expense_no', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%")
                        ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->when($fromDate && $toDate, fn ($query) => $query->whereBetween('expense_date', [$fromDate, $toDate]))
            ->latest('expense_date')
            ->latest('id');

        return view('expenses.index', [
            'search' => $search,
            'category' => $category,
            'storeId' => $storeId,
            'period' => $period,
            'expenses' => (clone $expensesQuery)->paginate(20)->withQueryString(),
            'summary' => [
                'count' => (clone $expensesQuery)->count(),
                'amount' => (float) (clone $expensesQuery)->sum('amount'),
                'today' => (float) (clone $expensesQuery)->whereDate('expense_date', now()->toDateString())->sum('amount'),
            ],
            'categories' => Expense::query()->select('category')->distinct()->orderBy('category')->pluck('category'),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('expenses.create', [
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultStoreId' => auth()->user()?->default_store_id,
        ]);
    }

    public function show(Expense $expense): View
    {
        $expense->load(['store:id,name', 'paymentMode:id,name', 'creator:id,name']);

        return view('expenses.show', compact('expense'));
    }

    public function print(Expense $expense): View
    {
        $expense->load(['store:id,name', 'paymentMode:id,name', 'creator:id,name']);

        return view('expenses.print', compact('expense'));
    }

    public function store(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService
    ): RedirectResponse
    {
        $validated = $request->validate([
            'expense_date' => ['required', 'date'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'payment_mode_id' => ['required', 'exists:payment_modes,id'],
            'category' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
        $storeId = $storeAssignmentService->resolveStoreId((int) ($validated['store_id'] ?? auth()->user()?->default_store_id), $request->user(), app(AccessService::class));

        $expense = Expense::query()->create([
            'expense_no' => $documentNumberService->make('expense', $validated['expense_date']),
            'expense_date' => $validated['expense_date'],
            'store_id' => $storeId,
            'payment_mode_id' => $validated['payment_mode_id'],
            'category' => trim($validated['category']),
            'amount' => round((float) $validated['amount'], 2),
            'reference_no' => $validated['reference_no'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);

        $auditLogService->record('expense.posted', $expense, "Expense {$expense->expense_no} posted.", [
            'amount' => $expense->amount,
            'category' => $expense->category,
        ]);

        return redirect()
            ->route('expenses.show', $expense)
            ->with('status', "Expense {$expense->expense_no} recorded successfully.");
    }

    private function periodRange(string $period): array
    {
        $today = Carbon::today();

        return match ($period) {
            'today' => [$today->toDateString(), $today->toDateString()],
            'week' => [$today->copy()->startOfWeek()->toDateString(), $today->copy()->endOfWeek()->toDateString()],
            'month' => [$today->copy()->startOfMonth()->toDateString(), $today->copy()->endOfMonth()->toDateString()],
            default => [null, null],
        };
    }
}
