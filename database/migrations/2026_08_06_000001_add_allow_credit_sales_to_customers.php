<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('customers', 'allow_credit_sales')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->boolean('allow_credit_sales')->default(false)->after('credit_limit')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customers', 'allow_credit_sales')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('allow_credit_sales');
            });
        }
    }
};
