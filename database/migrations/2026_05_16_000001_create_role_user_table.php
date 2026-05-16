<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        $legacyAssignments = DB::table('users')
            ->whereNotNull('role_id')
            ->select(['id as user_id', 'role_id'])
            ->get();

        foreach ($legacyAssignments as $assignment) {
            DB::table('role_user')->updateOrInsert(
                [
                    'role_id' => $assignment->role_id,
                    'user_id' => $assignment->user_id,
                ],
                [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
