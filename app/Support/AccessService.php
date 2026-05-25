<?php

namespace App\Support;

use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AccessService
{
    /**
     * @return array<string, array<int, string>>
     */
    private function defaultMatrix(): array
    {
        return [
            'admin' => ['*'],
            'manager' => [
                'dashboard.view', 'customers.view', 'customers.statement', 'suppliers.view', 'suppliers.statement',
                'products.view', 'sales.view', 'sales.manage', 'purchases.view', 'purchases.manage',
                'customer_payments.manage', 'supplier_payments.manage', 'capital.view', 'capital.manage',
                'stock.view', 'reports.view', 'follow_ups.manage', 'activity_logs.view',
                'expenses.view', 'expenses.manage', 'cash_shifts.manage', 'sales.override',
            ],
            'cashier' => [
                'dashboard.view', 'customers.view', 'customers.statement', 'products.view',
                'sales.view', 'sales.manage', 'customer_payments.manage', 'follow_ups.manage', 'cash_shifts.manage',
            ],
            'stock_clerk' => [
                'dashboard.view', 'suppliers.view', 'suppliers.statement', 'products.view',
                'purchases.view', 'purchases.manage', 'supplier_payments.manage', 'stock.view', 'stock.manage', 'expenses.view',
            ],
            'guest' => [],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function availablePermissions(): array
    {
        return [
            'dashboard.view',
            'customers.view',
            'customers.statement',
            'suppliers.view',
            'suppliers.statement',
            'products.view',
            'users.manage',
            'follow_ups.manage',
            'activity_logs.view',
            'business.manage',
            'master_data.manage',
            'reports.view',
            'sales.view',
            'sales.manage',
            'purchases.view',
            'purchases.manage',
            'capital.view',
            'capital.manage',
            'stock.view',
            'stock.manage',
            'customer_payments.manage',
            'supplier_payments.manage',
            'expenses.view',
            'expenses.manage',
            'cash_shifts.manage',
            'sales.override',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function matrix(): array
    {
        $defaults = $this->defaultMatrix();

        try {
            if (! Schema::hasTable('roles')) {
                return $defaults;
            }

            $roles = Role::query()->get(['name', 'permissions']);

            if ($roles->isEmpty()) {
                return $defaults;
            }

            $matrix = [];

            foreach ($roles as $role) {
                $matrix[$role->name] = $role->permissionList();
            }

            foreach ($defaults as $roleName => $permissions) {
                if (! array_key_exists($roleName, $matrix) || $matrix[$roleName] === []) {
                    $matrix[$roleName] = $permissions;
                }
            }

            return $matrix;
        } catch (Throwable) {
            return $defaults;
        }
    }

    public function __construct(
        private readonly Request $request
    ) {
    }

    public function currentRole(): string
    {
        $user = Auth::user();

        if ($user) {
            $assignedRoles = $this->userRoles($user);
            $actualRole = $assignedRoles[0] ?? ($user->role?->name ?? 'cashier');

            if (in_array('admin', $assignedRoles, true) && $this->request->session()->has('preview_role')) {
                return $this->request->session()->get('preview_role', 'admin');
            }

            return $actualRole;
        }

        return 'guest';
    }

    public function roles(): array
    {
        return array_values(array_unique(array_merge(array_keys($this->defaultMatrix()), array_keys($this->matrix()))));
    }

    public function can(string $ability): bool
    {
        $abilities = $this->abilitiesForCurrentUser();

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }

    public function hasRole(string $roleName): bool
    {
        $user = Auth::user();

        if (! $user) {
            return $roleName === 'guest';
        }

        return in_array($roleName, $this->userRoles($user), true);
    }

    /**
     * @return array<int, string>
     */
    public function abilitiesForCurrentUser(): array
    {
        $user = Auth::user();

        if (! $user) {
            return $this->matrix()['guest'] ?? [];
        }

        $abilities = collect($this->userRoles($user))
            ->flatMap(fn (string $roleName) => $this->matrix()[$roleName] ?? [])
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($this->hasRole('admin') && $this->request->session()->has('preview_role')) {
            $previewRole = (string) $this->request->session()->get('preview_role', 'admin');
            $previewAbilities = $this->matrix()[$previewRole] ?? [];

            if ($previewAbilities !== []) {
                return $previewAbilities;
            }
        }

        return $abilities;
    }

    /**
     * @return array<int, string>
     */
    private function userRoles($user): array
    {
        $roles = [];

        if ($user->relationLoaded('roles')) {
            $roles = $user->roles->pluck('name')->filter()->values()->all();
        } elseif (method_exists($user, 'roles')) {
            $roles = $user->roles()->pluck('name')->filter()->values()->all();
        }

        $primaryRole = $user->relationLoaded('role')
            ? ($user->role?->name)
            : ($user->role()->value('name'));

        if ($primaryRole && ! in_array($primaryRole, $roles, true)) {
            array_unshift($roles, $primaryRole);
        }

        return array_values(array_unique(array_filter($roles))) ?: ['cashier'];
    }
}
