<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminRoleId = Role::query()->where('name', 'admin')->value('id');

        User::updateOrCreate(
            ['email' => 'admin@apples.local'],
            [
                'role_id' => $adminRoleId,
                'name' => 'Apples Admin',
                'username' => 'admin',
                'password' => 'password',
                'is_active' => true,
            ]
        );
    }
}
