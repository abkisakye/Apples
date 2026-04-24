<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\AuditLogService;
use App\Support\AccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoleManagementController extends Controller
{
    public function __construct(
        private readonly AccessService $access
    ) {
    }

    public function index(): View
    {
        return view('roles.index', [
            'roles' => Role::query()->withCount('users')->orderBy('name')->get(),
            'availablePermissions' => $this->access->availablePermissions(),
        ]);
    }

    public function matrix(): View
    {
        $roles = Role::query()->withCount('users')->orderBy('name')->get();

        return view('roles.matrix', [
            'roles' => $roles,
            'availablePermissions' => $this->access->availablePermissions(),
        ]);
    }

    public function edit(Role $role): View
    {
        return view('roles.edit', [
            'role' => $role,
            'availablePermissions' => $this->access->availablePermissions(),
            'selectedPermissions' => $role->permissionList(),
        ]);
    }

    public function update(Request $request, Role $role, AuditLogService $auditLogService): RedirectResponse
    {
        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
        ]);

        $selected = collect($validated['permissions'] ?? [])
            ->filter(fn ($permission) => in_array($permission, $this->access->availablePermissions(), true) || $permission === '*')
            ->unique()
            ->values()
            ->all();

        if ($role->name === 'admin') {
            $selected = ['*'];
        }

        $role->update([
            'description' => $validated['description'] ?? $role->description,
            'permissions' => $selected,
        ]);

        $auditLogService->record('role.updated', $role, 'Role permissions updated.', [
            'role' => $role->name,
            'permissions' => $selected,
        ]);

        return redirect()
            ->route('roles.index')
            ->with('status', 'Role permissions updated successfully.');
    }

    public function updateMatrix(Request $request, AuditLogService $auditLogService): RedirectResponse
    {
        $roles = Role::query()->orderBy('name')->get();
        $availablePermissions = $this->access->availablePermissions();
        $matrix = $request->input('matrix', []);

        foreach ($roles as $role) {
            if ($role->name === 'admin') {
                $role->update(['permissions' => ['*']]);
                continue;
            }

            $selected = collect($availablePermissions)
                ->filter(function ($permission) use ($matrix, $role): bool {
                    $roleMatrix = (array) ($matrix[$role->id] ?? []);

                    return array_key_exists($permission, $roleMatrix)
                        || array_key_exists(str_replace('.', '_', $permission), $roleMatrix);
                })
                ->values()
                ->all();

            $role->update(['permissions' => $selected]);
        }

        $auditLogService->record('permissions.matrix_updated', null, 'Permissions matrix updated.', [
            'roles_updated' => $roles->pluck('name')->all(),
        ]);

        return redirect()
            ->route('roles.matrix')
            ->with('status', 'Permissions matrix updated successfully.');
    }
}
