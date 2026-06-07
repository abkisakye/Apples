<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_counts', function (Blueprint $table) {
            $table->decimal('total_variance_base_qty', 18, 3)->default(0)->after('total_variance_qty');
        });

        Schema::create('stock_count_unit_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stock_count_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_unit_id')->constrained()->cascadeOnDelete();
            $table->decimal('entered_quantity', 18, 3);
            $table->decimal('conversion_factor_snapshot', 18, 6);
            $table->decimal('base_quantity', 18, 3);
            $table->timestamps();

            $table->index(['stock_count_id', 'product_id']);
            $table->index(['stock_count_item_id', 'product_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_unit_entries');

        Schema::table('stock_counts', function (Blueprint $table) {
            $table->dropColumn('total_variance_base_qty');
        });
    }
};
