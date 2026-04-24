<?php

namespace App\Http\Controllers;

use App\Models\FollowUpAction;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ReminderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class FollowUpController extends Controller
{
    public function index(): View
    {
        return view('follow_ups.index', [
            'followUps' => FollowUpAction::query()
                ->with(['sale:id,sale_no', 'purchase:id,purchase_no', 'customer:id,name', 'supplier:id,name', 'assignedUser:id,name'])
                ->latest('reminder_date')
                ->latest('id')
                ->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('follow_ups.create', [
            'sales' => Sale::query()->with('customer:id,name')->where('balance_due', '>', 0)->where('sale_type', 'credit')->orderBy('credit_due_date')->get(['id', 'sale_no', 'customer_id', 'credit_due_date', 'balance_due']),
            'purchases' => Purchase::query()->with('supplier:id,name')->where('balance_due', '>', 0)->where('purchase_type', 'credit')->orderBy('credit_due_date')->get(['id', 'purchase_no', 'supplier_id', 'credit_due_date', 'balance_due']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedSaleId' => $request->integer('sale_id'),
            'selectedPurchaseId' => $request->integer('purchase_id'),
        ]);
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'sale_id' => ['nullable', 'exists:sales,id'],
            'purchase_id' => ['nullable', 'exists:purchases,id'],
            'assigned_user_id' => ['nullable', 'exists:users,id'],
            'reminder_date' => ['required', 'date'],
            'channel' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if (blank($validated['sale_id'] ?? null) === blank($validated['purchase_id'] ?? null)) {
            throw ValidationException::withMessages([
                'sale_id' => 'Choose either a sale or a purchase for the follow-up action.',
            ]);
        }

        $sale = ! empty($validated['sale_id']) ? Sale::query()->find($validated['sale_id']) : null;
        $purchase = ! empty($validated['purchase_id']) ? Purchase::query()->find($validated['purchase_id']) : null;

        $followUp = FollowUpAction::create([
            'sale_id' => $sale?->id,
            'purchase_id' => $purchase?->id,
            'customer_id' => $sale?->customer_id,
            'supplier_id' => $purchase?->supplier_id,
            'assigned_user_id' => $validated['assigned_user_id'] ?? auth()->id(),
            'reminder_date' => $validated['reminder_date'],
            'channel' => $validated['channel'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => 'pending',
        ]);

        $auditLogService->record('follow_up.created', $followUp, 'Follow-up action created.');

        return redirect()->route('follow-ups.index')->with('status', 'Follow-up action created successfully.');
    }

    public function complete(FollowUpAction $followUp, AuditLogService $auditLogService): RedirectResponse
    {
        $followUp->update([
            'status' => 'completed',
            'follow_up_date' => now()->toDateString(),
        ]);

        $auditLogService->record('follow_up.completed', $followUp, 'Follow-up action completed.');

        return back()->with('status', 'Follow-up marked as completed.');
    }

    public function send(FollowUpAction $followUp, Request $request, ReminderService $reminderService): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => ['required', 'in:email,sms'],
        ]);

        try {
            $reminderService->send($followUp->load(['sale.customer', 'purchase.supplier', 'customer', 'supplier']), $validated['channel']);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['channel' => $exception->getMessage()]);
        }

        return back()->with('status', 'Reminder sent successfully.');
    }
}
