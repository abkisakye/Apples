<?php

namespace App\Http\Controllers;

use App\Models\CashShift;
use App\Models\CustomerPayment;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use App\Support\AccessService;
use App\Support\StoreAssignmentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashShiftController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->string('status'));
        $userId = $request->integer('user_id');
        $period = trim((string) $request->string('period'));
        [$fromDate, $toDate] = $this->periodRange($period);

        $shiftsQuery = CashShift::query()
            ->with(['store:id,name', 'user:id,name'])
            ->when(in_array($status, ['open', 'closed'], true), fn ($query) => $query->where('status', $status))
            ->when($userId > 0, fn ($query) => $query->where('user_id', $userId))
            ->when($fromDate && $toDate, fn ($query) => $query->whereBetween(DB::raw('date(opened_at)'), [$fromDate, $toDate]))
            ->latest('opened_at')
            ->latest('id');

        return view('cash_shifts.index', [
            'status' => $status,
            'userId' => $userId,
            'period' => $period,
            'shifts' => (clone $shiftsQuery)->paginate(20)->withQueryString(),
            'summary' => [
                'open_count' => CashShift::query()->open()->count(),
                'today_cash' => (float) CashShift::query()->whereDate('opened_at', now()->toDateString())->sum('expected_cash'),
                'today_difference' => (float) CashShift::query()->whereDate('opened_at', now()->toDateString())->whereNotNull('shortage_overage')->sum('shortage_overage'),
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'activeShift' => CashShift::query()->open()->where('user_id', auth()->id())->latest('opened_at')->first(),
        ]);
    }

    public function create(): View
    {
        return view('cash_shifts.create', [
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'defaultStoreId' => auth()->user()?->default_store_id,
            'activeShift' => CashShift::query()->open()->where('user_id', auth()->id())->first(),
        ]);
    }

    public function store(
        Request $request,
        DocumentNumberService $documentNumberService,
        AuditLogService $auditLogService,
        StoreAssignmentService $storeAssignmentService
    ): RedirectResponse
    {
        if (CashShift::query()->open()->where('user_id', auth()->id())->exists()) {
            throw ValidationException::withMessages([
                'opening_balance' => 'Close the current open shift before opening another one.',
            ]);
        }

        $validated = $request->validate([
            'store_id' => ['nullable', 'exists:stores,id'],
            'opened_at' => ['required', 'date'],
            'opening_balance' => ['required', 'numeric', 'min:0'],
            'opening_notes' => ['nullable', 'string'],
        ]);

        $openedAt = Carbon::parse($validated['opened_at']);
        $storeId = $storeAssignmentService->resolveStoreId((int) ($validated['store_id'] ?? auth()->user()?->default_store_id), $request->user(), app(AccessService::class));

        $shift = CashShift::query()->create([
            'shift_no' => $documentNumberService->make('cash_shift', $openedAt->toDateString()),
            'store_id' => $storeId,
            'user_id' => auth()->id(),
            'opened_at' => $openedAt,
            'opening_balance' => round((float) $validated['opening_balance'], 2),
            'opening_notes' => $validated['opening_notes'] ?? null,
            'status' => 'open',
        ]);

        $auditLogService->record('cash_shift.opened', $shift, "Cash shift {$shift->shift_no} opened.", [
            'opening_balance' => $shift->opening_balance,
        ]);

        return redirect()
            ->route('cash-shifts.show', $shift)
            ->with('status', "Cash shift {$shift->shift_no} opened successfully.");
    }

    public function show(CashShift $cashShift): View
    {
        $cashShift->load(['store:id,name', 'user:id,name']);

        return view('cash_shifts.show', [
            'cashShift' => $cashShift,
            'summary' => $this->buildShiftSummary($cashShift),
        ]);
    }

    public function closeForm(CashShift $cashShift): View
    {
        $this->guardClosable($cashShift);

        return view('cash_shifts.close', [
            'cashShift' => $cashShift->load(['store:id,name', 'user:id,name']),
            'summary' => $this->buildShiftSummary($cashShift),
        ]);
    }

    public function close(Request $request, CashShift $cashShift, AuditLogService $auditLogService): RedirectResponse
    {
        $this->guardClosable($cashShift);

        $validated = $request->validate([
            'counted_cash' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string'],
        ]);

        $summary = $this->buildShiftSummary($cashShift);
        $countedCash = round((float) $validated['counted_cash'], 2);
        $shortageOverage = round($countedCash - $summary['expected_cash'], 2);

        $cashShift->update([
            'closed_at' => now(),
            'cash_sales_total' => $summary['cash_sales_total'],
            'cash_customer_payments_total' => $summary['cash_customer_payments_total'],
            'cash_expenses_total' => $summary['cash_expenses_total'],
            'expected_cash' => $summary['expected_cash'],
            'counted_cash' => $countedCash,
            'shortage_overage' => $shortageOverage,
            'closing_notes' => $validated['closing_notes'] ?? null,
            'status' => 'closed',
        ]);

        $auditLogService->record('cash_shift.closed', $cashShift, "Cash shift {$cashShift->shift_no} closed.", [
            'expected_cash' => $cashShift->expected_cash,
            'counted_cash' => $cashShift->counted_cash,
            'shortage_overage' => $cashShift->shortage_overage,
        ]);

        return redirect()
            ->route('cash-shifts.show', $cashShift)
            ->with('status', "Cash shift {$cashShift->shift_no} closed successfully.");
    }

    private function guardClosable(CashShift $cashShift): void
    {
        if ($cashShift->status !== 'open') {
            throw ValidationException::withMessages([
                'counted_cash' => 'Only open shifts can be closed.',
            ]);
        }

        if ((int) $cashShift->user_id !== (int) auth()->id() && ! app(AccessService::class)->can('sales.override')) {
            throw ValidationException::withMessages([
                'counted_cash' => 'You can only close your own shift unless you have override access.',
            ]);
        }
    }

    private function buildShiftSummary(CashShift $cashShift): array
    {
        $openedAt = $cashShift->opened_at;
        $closedAt = $cashShift->closed_at ?? now();

        $cashSales = (float) Sale::query()
            ->posted()
            ->where('created_by', $cashShift->user_id)
            ->when($cashShift->store_id, fn ($query) => $query->where('store_id', $cashShift->store_id))
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('paymentMode', fn ($modeQuery) => $modeQuery->whereRaw('UPPER(name) = ?', ['CASH']))
            ->sum('amount_paid');

        $cashCustomerPayments = (float) CustomerPayment::query()
            ->where('created_by', $cashShift->user_id)
            ->when($cashShift->store_id, fn ($query) => $query->where('store_id', $cashShift->store_id))
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('paymentMode', fn ($modeQuery) => $modeQuery->whereRaw('UPPER(name) = ?', ['CASH']))
            ->sum('amount');

        $cashRefunds = (float) SaleReturn::query()
            ->where('created_by', $cashShift->user_id)
            ->when($cashShift->store_id, fn ($query) => $query->where('store_id', $cashShift->store_id))
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->where('status', 'posted')
            ->where('refund_amount', '>', 0)
            ->whereHas('paymentMode', fn ($modeQuery) => $modeQuery->whereRaw('UPPER(name) = ?', ['CASH']))
            ->sum('refund_amount');

        $cashExpenses = (float) Expense::query()
            ->posted()
            ->where('created_by', $cashShift->user_id)
            ->when($cashShift->store_id, fn ($query) => $query->where('store_id', $cashShift->store_id))
            ->whereBetween('created_at', [$openedAt, $closedAt])
            ->whereHas('paymentMode', fn ($modeQuery) => $modeQuery->whereRaw('UPPER(name) = ?', ['CASH']))
            ->sum('amount');

        return [
            'cash_sales_total' => round($cashSales, 2),
            'cash_customer_payments_total' => round($cashCustomerPayments, 2),
            'cash_refunds_total' => round($cashRefunds, 2),
            'cash_expenses_total' => round($cashExpenses, 2),
            'expected_cash' => round((float) $cashShift->opening_balance + $cashSales + $cashCustomerPayments - $cashRefunds - $cashExpenses, 2),
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
