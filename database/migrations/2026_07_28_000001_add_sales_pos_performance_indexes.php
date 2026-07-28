<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_active', 'name'], 'prod_active_name_idx');
            $table->index(['category_id', 'is_active'], 'prod_cat_active_idx');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->index(['is_active', 'product_id'], 'pu_active_product_idx');
            $table->index('unit_name', 'pu_unit_name_idx');
            $table->index('part_number', 'pu_part_no_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->index(['store_id', 'product_id'], 'it_store_product_idx');
            $table->index(['store_id', 'product_unit_id'], 'it_store_unit_idx');
            $table->index('created_at', 'it_created_at_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index(['sale_type', 'status', 'sale_date', 'id'], 'sales_type_status_date_idx');
            $table->index(['customer_id', 'sale_date'], 'sales_customer_date_idx');
            $table->index(['store_id', 'sale_date'], 'sales_store_date_idx');
            $table->index(['payment_mode_id', 'sale_date'], 'sales_payment_date_idx');
            $table->index(['created_by', 'sale_date'], 'sales_user_date_idx');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_type_status_date_idx');
            $table->dropIndex('sales_customer_date_idx');
            $table->dropIndex('sales_store_date_idx');
            $table->dropIndex('sales_payment_date_idx');
            $table->dropIndex('sales_user_date_idx');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropIndex('it_store_product_idx');
            $table->dropIndex('it_store_unit_idx');
            $table->dropIndex('it_created_at_idx');
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropIndex('pu_active_product_idx');
            $table->dropIndex('pu_unit_name_idx');
            $table->dropIndex('pu_part_no_idx');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('prod_active_name_idx');
            $table->dropIndex('prod_cat_active_idx');
        });
    }
};
