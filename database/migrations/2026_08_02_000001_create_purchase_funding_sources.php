<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_funding_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
        });

        $now = now();
        $sources = [
            ['name' => 'Business Cash / Shop Cash', 'description' => 'Money paid from normal shop cash.', 'sort_order' => 10],
            ['name' => 'Mobile Money', 'description' => 'Money paid from business mobile money.', 'sort_order' => 20],
            ['name' => 'Bank Account', 'description' => 'Money paid from a bank account.', 'sort_order' => 30],
            ['name' => 'Safe Custody', 'description' => 'Money paid from safe custody cash.', 'sort_order' => 40],
            ['name' => "Owner's Money / Owner Contribution", 'description' => 'Money added by the owner for this purchase.', 'sort_order' => 50],
            ['name' => 'Loan / Borrowed Money', 'description' => 'Money borrowed to pay for this purchase.', 'sort_order' => 60],
            ['name' => 'Outside Business Money', 'description' => 'Money paid from outside the normal business cash flow.', 'sort_order' => 70],
            ['name' => 'Supplier Credit / Not Paid Yet', 'description' => 'No money paid now; supplier balance remains outstanding.', 'sort_order' => 80],
            ['name' => 'Other', 'description' => 'Other funding source.', 'sort_order' => 90],
        ];

        foreach ($sources as $source) {
            DB::table('purchase_funding_sources')->insert(array_merge($source, [
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        Schema::table('purchases', function (Blueprint $table): void {
            $table->foreignId('purchase_funding_source_id')
                ->nullable()
                ->after('payment_mode_id')
                ->constrained('purchase_funding_sources')
                ->nullOnDelete();
        });

        $businessCashId = DB::table('purchase_funding_sources')->where('name', 'Business Cash / Shop Cash')->value('id');
        $supplierCreditId = DB::table('purchase_funding_sources')->where('name', 'Supplier Credit / Not Paid Yet')->value('id');

        DB::table('purchases')
            ->where(function ($query): void {
                $query->where('purchase_type', 'credit')
                    ->orWhere('balance_due', '>', 0);
            })
            ->update(['purchase_funding_source_id' => $supplierCreditId]);

        DB::table('purchases')
            ->whereNull('purchase_funding_source_id')
            ->where('amount_paid', '>', 0)
            ->update(['purchase_funding_source_id' => $businessCashId]);
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('purchase_funding_source_id');
        });

        Schema::dropIfExists('purchase_funding_sources');
    }
};
