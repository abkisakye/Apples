<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('legacy_item_id')->nullable()->unique();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_name');
            $table->decimal('conversion_factor', 18, 3)->default(1);
            $table->decimal('selling_price', 18, 2)->default(0);
            $table->decimal('cost_price', 18, 2)->default(0);
            $table->decimal('opening_stock_qty', 18, 3)->default(0);
            $table->string('barcode')->nullable()->index();
            $table->string('part_number')->nullable();
            $table->boolean('is_pos_unit')->default(true)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['product_id', 'unit_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
