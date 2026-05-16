<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->string('q'));
        $roleId = $request->integer('role_id');
        $status = trim((string) $request->string('status'));

        return view('users.index', [
            'users' => User::query()
                ->with(['role:id,name', 'roles:id,name', 'defaultStore:id,name'])
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
                })
                ->when($roleId > 0, fn ($query) => $query->where(function ($inner) use ($roleId) {
                    $inner->where('role_id', $roleId)
                        ->orWhereHas('roles', fn ($roleQuery) => $roleQuery->where('roles.id', $roleId));
                }))
                ->when($status !== '', fn ($query) => $query->where('is_active', $status === 'active'))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'search' => $search,
            'roleId' => $roleId,
            'statusFilter' => $status,
        ]);
    }

    public function create(): View
    {
        return view('users.create', $this->formData(new User()));
    }

    public function store(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $this->validateUser($request);
        $roleIds = $this->normalizeRoleIds($request, $validated);
        $validated['role_id'] = (int) $roleIds[0];
        unset($validated['role_ids']);

        $user = User::create($validated);
        $user->roles()->sync($roleIds);
        $auditLogService->record('user.created', $user, 'User account created.', ['email' => $user->email, 'username' => $user->username]);

        return redirect()->route('users.index')->with('status', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', $this->formData($user));
    }

    public function editRole(User $user): View
    {
        return view('users.role', [
            'user' => $user->load(['role:id,name', 'roles:id,name', 'defaultStore:id,name']),
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, User $user, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $this->validateUser($request, $user);

        if (blank($validated['password'] ?? null)) {
            unset($validated['password']);
        }

        $roleIds = $this->normalizeRoleIds($request, $validated);
        $validated['role_id'] = (int) $roleIds[0];
        unset($validated['role_ids']);

        $user->update($validated);
        $user->roles()->sync($roleIds);
        $auditLogService->record('user.updated', $user, 'User account updated.', ['email' => $user->email, 'username' => $user->username]);

        return redirect()->route('users.index')->with('status', 'User updated successfully.');
    }

    public function updateRole(Request $request, User $user, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
            'role_ids' => ['nullable', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
        ]);

        $roleIds = $this->normalizeRoleIds($request, $validated);

        $user->update([
            'role_id' => $roleIds[0],
        ]);
        $user->roles()->sync($roleIds);

        $auditLogService->record('user.role_updated', $user, 'User role updated.', [
            'role_ids' => $roleIds,
        ]);

        return redirect()
            ->route('users.index')
            ->with('status', 'User role updated successfully.');
    }

    private function validateUser(Request $request, ?User $user = null): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['nullable', 'exists:roles,id'],
            'role_ids' => ['nullable', 'array', 'min:1'],
            'role_ids.*' => ['exists:roles,id'],
            'default_store_id' => ['nullable', 'exists:stores,id'],
            'password' => [$user ? 'nullable' : 'required', 'string', 'min:8'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['role_ids'] = $this->normalizeRoleIds($request, $validated);
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function normalizeRoleIds(Request $request, array $validated): array
    {
        $roleIds = collect($validated['role_ids'] ?? [])
            ->merge($validated['role_id'] ?? null)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($roleIds === []) {
            throw ValidationException::withMessages([
                'role_ids' => 'At least one role must be assigned to the user.',
            ]);
        }

        return $roleIds;
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::query()->orderBy('name')->get(['id', 'name']),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
