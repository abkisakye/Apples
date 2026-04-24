<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('name');
            $table->unsignedInteger('legacy_login_id')->nullable()->after('default_store_id');
            $table->unsignedInteger('legacy_user_id')->nullable()->after('legacy_login_id');
            $table->unsignedInteger('legacy_department_id')->nullable()->after('legacy_user_id');
            $table->unsignedInteger('legacy_owner_user_id')->nullable()->after('legacy_department_id');
            $table->string('legacy_kind')->nullable()->after('legacy_owner_user_id');
            $table->boolean('can_open')->default(false)->after('is_active');
            $table->boolean('can_add')->default(false)->after('can_open');
            $table->boolean('can_edit')->default(false)->after('can_add');
            $table->boolean('can_delete')->default(false)->after('can_edit');
            $table->boolean('is_legacy_user')->default(false)->after('can_delete');

            $table->unique('username');
            $table->unique('legacy_login_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropUnique(['legacy_login_id']);
            $table->dropColumn([
                'username',
                'legacy_login_id',
                'legacy_user_id',
                'legacy_department_id',
                'legacy_owner_user_id',
                'legacy_kind',
                'can_open',
                'can_add',
                'can_edit',
                'can_delete',
                'is_legacy_user',
            ]);
        });
    }
};
