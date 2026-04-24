<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->string('legacy_source_table')->nullable();
            $table->unsignedBigInteger('legacy_source_id')->nullable();
            $table->string('purchase_no')->unique();
            $table->date('purchase_date')->index();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('store_id')->constrained('stores')->restrictOnDelete();
            $table->string('purchase_type')->index();
            $table->foreignId('payment_mode_id')->nullable()->constrained('payment_modes')->nullOnDelete();
            $table->string('supplier_invoice_no')->nullable();
            $table->decimal('vat_percent', 8, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('vat_amount', 18, 2)->default(0);
            $table->decimal('total_amount', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('balance_due', 18, 2)->default(0);
            $table->unsignedInteger('credit_period_days')->nullable();
            $table->date('credit_due_date')->nullable();
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
        Schema::dropIfExists('purchases');
    }
};
