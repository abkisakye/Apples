<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');
        $adminPassword = env('INITIAL_ADMIN_PASSWORD');

        if (! $adminPassword) {
            $adminPassword = app()->environment(['local', 'testing'])
                ? 'password'
                : Str::random(32);
        }

        User::updateOrCreate(
            ['email' => 'admin@apples.local'],
            [
                'role_id' => $adminRoleId,
                'name' => 'Apples Admin',
                'username' => 'admin',
                'password' => $adminPassword,
                'is_active' => true,
            ]
        );
    }
}
