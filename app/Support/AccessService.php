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
            $actualRole = $user->relationLoaded('role') ? ($user->role?->name ?? 'cashier') : ($user->role()->value('name') ?? 'cashier');

            if ($actualRole === 'admin' && $this->request->session()->has('preview_role')) {
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
        $role = $this->currentRole();
        $abilities = $this->matrix()[$role] ?? [];

        return in_array('*', $abilities, true) || in_array($ability, $abilities, true);
    }
}
