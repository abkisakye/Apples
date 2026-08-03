<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('product_units', 'minimum_wholesale_quantity')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->decimal('minimum_wholesale_quantity', 18, 3)->nullable()->after('quantity_precision');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('product_units', 'minimum_wholesale_quantity')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->dropColumn('minimum_wholesale_quantity');
            });
        }
    }
};
