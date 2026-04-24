<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_shifts', function (Blueprint $table): void {
            $table->id();
            $table->string('shift_no')->unique();
            $table->foreignId('store_id')->nullable()->constrained('stores')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('opened_at')->index();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->dateTime('closed_at')->nullable()->index();
            $table->decimal('cash_sales_total', 18, 2)->default(0);
            $table->decimal('cash_customer_payments_total', 18, 2)->default(0);
            $table->decimal('cash_expenses_total', 18, 2)->default(0);
            $table->decimal('expected_cash', 18, 2)->default(0);
            $table->decimal('counted_cash', 18, 2)->nullable();
            $table->decimal('shortage_overage', 18, 2)->nullable();
            $table->string('status')->default('open')->index();
            $table->text('closing_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_shifts');
    }
};
