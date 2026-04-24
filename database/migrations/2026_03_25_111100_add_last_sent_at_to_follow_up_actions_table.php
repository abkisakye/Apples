<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('follow_up_actions', function (Blueprint $table) {
            $table->timestamp('last_sent_at')->nullable()->after('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::table('follow_up_actions', function (Blueprint $table) {
            $table->dropColumn('last_sent_at');
        });
    }
};
