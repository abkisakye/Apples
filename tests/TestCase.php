<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function signInAsRole(string $roleName = 'admin'): User
    {
        $role = Role::query()->firstOrCreate(['name' => $roleName], ['description' => ucfirst(str_replace('_', ' ', $roleName))]);
        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_active' => true,
        ]);
        if (method_exists($user, 'roles')) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $this->actingAs($user);

        return $user;
    }

    protected function signInWithRoles(array $roleNames): User
    {
        $primaryRoleName = $roleNames[0] ?? 'cashier';
        $primaryRole = Role::query()->firstOrCreate(
            ['name' => $primaryRoleName],
            ['description' => ucfirst(str_replace('_', ' ', $primaryRoleName))]
        );

        $user = User::factory()->create([
            'role_id' => $primaryRole->id,
            'is_active' => true,
        ]);

        $roleIds = collect($roleNames)
            ->map(fn (string $roleName) => Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => ucfirst(str_replace('_', ' ', $roleName))]
            )->id)
            ->all();

        if (method_exists($user, 'roles')) {
            $user->roles()->sync($roleIds);
        }

        $this->actingAs($user);

        return $user;
    }
}
