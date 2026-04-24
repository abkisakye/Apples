<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('corrected_from_sale_id')->nullable()->after('customer_id')->constrained('sales')->nullOnDelete();
            $table->foreignId('replaced_by_sale_id')->nullable()->after('corrected_from_sale_id')->constrained('sales')->nullOnDelete();
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreignId('corrected_from_purchase_id')->nullable()->after('supplier_id')->constrained('purchases')->nullOnDelete();
            $table->foreignId('replaced_by_purchase_id')->nullable()->after('corrected_from_purchase_id')->constrained('purchases')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_purchase_id');
            $table->dropConstrainedForeignId('corrected_from_purchase_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_sale_id');
            $table->dropConstrainedForeignId('corrected_from_sale_id');
        });
    }
};
