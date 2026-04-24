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

        $this->actingAs($user);

        return $user;
    }
}
