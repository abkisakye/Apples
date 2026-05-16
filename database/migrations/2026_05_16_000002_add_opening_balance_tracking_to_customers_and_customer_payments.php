<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->date('opening_balance_date')->nullable()->after('opening_balance');
        });

        Schema::table('customer_payments', function (Blueprint $table) {
            $table->string('account_reference_type')->default('sale')->after('sale_id')->index();
        });

        DB::table('customers')
            ->where('opening_balance', '>', 0)
            ->whereNull('opening_balance_date')
            ->update([
                'opening_balance_date' => DB::raw('DATE(created_at)'),
            ]);

        DB::table('customer_payments')
            ->whereNull('sale_id')
            ->update(['account_reference_type' => 'opening_balance']);
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->dropColumn('account_reference_type');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('opening_balance_date');
        });
    }
};
