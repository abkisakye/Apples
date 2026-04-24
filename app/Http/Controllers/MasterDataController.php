<?php

namespace App\Http\Controllers;

use App\Models\CapitalSource;
use App\Models\Category;
use App\Models\PaymentMode;
use App\Models\Store;
use App\Services\AuditLogService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class MasterDataController extends Controller
{
    public function index(Request $request, string $resource): View
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        $search = trim((string) $request->string('q'));
        $statusFilter = trim((string) $request->string('status'));
        $editId = $request->integer('edit');

        $records = $modelClass::query()
            ->when($search !== '', function ($query) use ($config, $search) {
                $query->where(function ($inner) use ($config, $search) {
                    foreach ($config['search_columns'] as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $inner->{$method}($column, 'like', "%{$search}%");
                    }
                });
            })
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy($config['order_by'])
            ->paginate(20)
            ->withQueryString();

        /** @var Model|null $editing */
        $editing = null;

        if ($editId > 0) {
            $editing = $modelClass::query()->find($editId);
        }

        if (! $editing) {
            $editing = new $modelClass();
            $editing->is_active = true;
        }

        return view('master_data.index', [
            'resource' => $resource,
            'config' => $config,
            'records' => $records,
            'editing' => $editing,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }

    public function store(Request $request, string $resource, AuditLogService $auditLogService): RedirectResponse
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        /** @var Model $record */
        $record = $modelClass::query()->create($this->validateRecord($request, $resource));

        $auditLogService->record("{$resource}.created", $record, "{$config['single']} created.", [
            'resource' => $resource,
            'record_id' => $record->getKey(),
        ]);

        return redirect()
            ->route('master-data.index', ['resource' => $resource])
            ->with('status', "{$config['single']} saved successfully.");
    }

    public function update(Request $request, string $resource, int $record, AuditLogService $auditLogService): RedirectResponse
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        /** @var Model $entry */
        $entry = $modelClass::query()->findOrFail($record);
        $entry->update($this->validateRecord($request, $resource, $entry));

        $auditLogService->record("{$resource}.updated", $entry, "{$config['single']} updated.", [
            'resource' => $resource,
            'record_id' => $entry->getKey(),
        ]);

        return redirect()
            ->route('master-data.index', ['resource' => $resource])
            ->with('status', "{$config['single']} updated successfully.");
    }

    public function updateStatus(Request $request, string $resource, int $record, AuditLogService $auditLogService): RedirectResponse
    {
        $config = $this->resourceConfig($resource);
        $modelClass = $config['model'];
        /** @var Model $entry */
        $entry = $modelClass::query()->findOrFail($record);
        $makeActive = $request->boolean('is_active');

        $entry->update(['is_active' => $makeActive]);

        $auditLogService->record("{$resource}.status_updated", $entry, "{$config['single']} status updated.", [
            'resource' => $resource,
            'record_id' => $entry->getKey(),
            'is_active' => $makeActive,
        ]);

        return redirect()
            ->route('master-data.index', ['resource' => $resource])
            ->with('status', "{$config['single']} marked as ".($makeActive ? 'active' : 'inactive').'.');
    }

    private function validateRecord(Request $request, string $resource, ?Model $record = null): array
    {
        $validated = match ($resource) {
            'stores' => $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('stores', 'name')->ignore($record?->getKey())],
                'code' => ['nullable', 'string', 'max:255', Rule::unique('stores', 'code')->ignore($record?->getKey())],
                'location' => ['nullable', 'string', 'max:255'],
                'in_charge_name' => ['nullable', 'string', 'max:255'],
                'is_active' => ['nullable', 'boolean'],
            ]),
            'categories' => $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($record?->getKey())],
                'description' => ['nullable', 'string', 'max:255'],
                'is_active' => ['nullable', 'boolean'],
            ]),
            'payment-modes' => $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('payment_modes', 'name')->ignore($record?->getKey())],
                'account_no' => ['nullable', 'string', 'max:255'],
                'is_active' => ['nullable', 'boolean'],
            ]),
            'capital-sources' => $request->validate([
                'name' => ['required', 'string', 'max:255', Rule::unique('capital_sources', 'name')->ignore($record?->getKey())],
                'source_type' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:255'],
                'is_active' => ['nullable', 'boolean'],
            ]),
            default => [],
        };

        $validated['is_active'] = $request->boolean('is_active', true);

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function resourceConfig(string $resource): array
    {
        $resources = [
            'stores' => [
                'model' => Store::class,
                'title' => 'Stores',
                'single' => 'Store',
                'description' => 'Maintain branch names, store codes, locations, and the person in charge from one browser page.',
                'order_by' => 'name',
                'search_columns' => ['name', 'code', 'location', 'in_charge_name'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Store Name', 'type' => 'text', 'required' => true],
                    ['name' => 'code', 'label' => 'Code', 'type' => 'text'],
                    ['name' => 'location', 'label' => 'Location', 'type' => 'text'],
                    ['name' => 'in_charge_name', 'label' => 'In Charge', 'type' => 'text'],
                    ['name' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
                'columns' => [
                    ['label' => 'Store', 'value' => 'name', 'meta' => 'code'],
                    ['label' => 'Location', 'value' => 'location'],
                    ['label' => 'In Charge', 'value' => 'in_charge_name'],
                    ['label' => 'Status', 'value' => 'is_active', 'type' => 'status'],
                ],
            ],
            'categories' => [
                'model' => Category::class,
                'title' => 'Categories',
                'single' => 'Category',
                'description' => 'Keep your product grouping simple so reporting and product setup stay clean for staff.',
                'order_by' => 'name',
                'search_columns' => ['name', 'description'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Category Name', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'text'],
                    ['name' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
                'columns' => [
                    ['label' => 'Category', 'value' => 'name'],
                    ['label' => 'Description', 'value' => 'description'],
                    ['label' => 'Status', 'value' => 'is_active', 'type' => 'status'],
                ],
            ],
            'payment-modes' => [
                'model' => PaymentMode::class,
                'title' => 'Payment Modes',
                'single' => 'Payment Mode',
                'description' => 'Manage the payment methods staff can use when recording sales, purchases, and account payments.',
                'order_by' => 'name',
                'search_columns' => ['name', 'account_no'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Payment Mode', 'type' => 'text', 'required' => true],
                    ['name' => 'account_no', 'label' => 'Account / Reference', 'type' => 'text'],
                    ['name' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
                'columns' => [
                    ['label' => 'Payment Mode', 'value' => 'name'],
                    ['label' => 'Account / Reference', 'value' => 'account_no'],
                    ['label' => 'Status', 'value' => 'is_active', 'type' => 'status'],
                ],
            ],
            'capital-sources' => [
                'model' => CapitalSource::class,
                'title' => 'Capital Sources',
                'single' => 'Capital Source',
                'description' => 'Track where business capital comes from, whether it is from the owner, the business itself, or another outside source.',
                'order_by' => 'name',
                'search_columns' => ['name', 'source_type', 'description'],
                'fields' => [
                    ['name' => 'name', 'label' => 'Source Name', 'type' => 'text', 'required' => true],
                    ['name' => 'source_type', 'label' => 'Source Type', 'type' => 'text', 'required' => true],
                    ['name' => 'description', 'label' => 'Description', 'type' => 'text'],
                    ['name' => 'is_active', 'label' => 'Status', 'type' => 'status'],
                ],
                'columns' => [
                    ['label' => 'Source', 'value' => 'name', 'meta' => 'source_type'],
                    ['label' => 'Description', 'value' => 'description'],
                    ['label' => 'Status', 'value' => 'is_active', 'type' => 'status'],
                ],
            ],
        ];

        return Arr::get($resources, $resource) ?? abort(404);
    }
}
