<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_source_table')->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->string('sale_no')->unique();
            $table->date('sale_date')->index();
            $table->time('sale_time')->nullable();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('sale_type')->index();
            $table->foreignId('payment_mode_id')->nullable()->constrained('payment_modes')->nullOnDelete();
            $table->decimal('vat_percent', 8, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->unsignedInteger('credit_period_days')->nullable();
            $table->date('credit_due_date')->nullable();
            $table->decimal('cash_tendered', 18, 2)->nullable();
            $table->decimal('change_given', 18, 2)->default(0);
            $table->string('status')->default('posted')->index();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['legacy_source_table', 'legacy_source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
