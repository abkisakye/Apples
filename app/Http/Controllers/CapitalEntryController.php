<?php

namespace App\Http\Controllers;

use App\Models\CapitalEntry;
use App\Models\CapitalSource;
use App\Models\PaymentMode;
use App\Models\Store;
use App\Services\AuditLogService;
use App\Services\DocumentNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CapitalEntryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $origin = trim((string) $request->string('origin'));
        $storeId = $request->integer('store_id');

        $capitalEntriesQuery = CapitalEntry::query()
            ->with(['source:id,name,source_type', 'store:id,name', 'paymentMode:id,name'])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('entry_no', 'like', "%{$search}%")
                        ->orWhere('reference_no', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%")
                        ->orWhereHas('source', fn ($sourceQuery) => $sourceQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when($origin !== '', fn ($query) => $query->where('source_origin', $origin))
            ->when($storeId > 0, fn ($query) => $query->where('store_id', $storeId))
            ->latest('entry_date')
            ->latest('id');

        $capitalEntries = (clone $capitalEntriesQuery)
            ->latest('entry_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $capitalSummary = [
            'total_entries' => (clone $capitalEntriesQuery)->count(),
            'total_amount' => (float) (clone $capitalEntriesQuery)->sum('amount'),
            'business_generated' => (float) (clone $capitalEntriesQuery)->where('source_origin', 'business_generated')->sum('amount'),
            'external' => (float) (clone $capitalEntriesQuery)->where('source_origin', 'external')->sum('amount'),
        ];

        $capitalSources = CapitalSource::query()
            ->withCount('entries')
            ->orderBy('name')
            ->get();

        return view('capital.index', [
            'capitalEntries' => $capitalEntries,
            'capitalSummary' => $capitalSummary,
            'capitalSources' => $capitalSources,
            'origin' => $origin,
            'search' => $search,
            'storeId' => $storeId,
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('capital.create', [
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
            'paymentModes' => PaymentMode::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'capitalSources' => CapitalSource::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'source_type']),
        ]);
    }

    public function store(Request $request, DocumentNumberService $documentNumberService, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'store_id' => ['nullable', 'exists:stores,id'],
            'capital_source_id' => ['required', 'exists:capital_sources,id'],
            'payment_mode_id' => ['nullable', 'exists:payment_modes,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'source_origin' => ['required', 'in:business_generated,external'],
            'notes' => ['nullable', 'string'],
        ]);

        $entry = CapitalEntry::create([
            'entry_no' => $documentNumberService->make('capital_entry', $validated['entry_date']),
            'entry_date' => $validated['entry_date'],
            'store_id' => $validated['store_id'] ?? null,
            'capital_source_id' => $validated['capital_source_id'],
            'payment_mode_id' => $validated['payment_mode_id'] ?? null,
            'amount' => round((float) $validated['amount'], 2),
            'reference_no' => $validated['reference_no'] ?? null,
            'source_origin' => $validated['source_origin'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'posted',
        ]);

        $auditLogService->record('capital.posted', $entry, "Capital entry {$entry->entry_no} recorded.", [
            'amount' => $entry->amount,
            'source_origin' => $entry->source_origin,
        ]);

        return redirect()
            ->route('capital.index')
            ->with('status', "Capital entry {$entry->entry_no} recorded successfully.");
    }
}
