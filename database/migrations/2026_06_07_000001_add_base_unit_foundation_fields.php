<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('base_product_unit_id')->nullable()->constrained('product_units')->nullOnDelete();
            $table->string('base_unit_label')->nullable();
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->boolean('allow_fractional_quantity')->default(false)->index();
            $table->unsignedTinyInteger('quantity_precision')->default(0);
            $table->boolean('is_base_unit')->default(false)->index();
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->decimal('base_quantity_in', 18, 3)->default(0);
            $table->decimal('base_quantity_out', 18, 3)->default(0);
            $table->decimal('conversion_factor_snapshot', 18, 6)->nullable();
        });

        foreach (['sale_items', 'purchase_items', 'sale_return_items', 'purchase_return_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('base_quantity', 18, 3)->nullable();
                $table->decimal('conversion_factor_snapshot', 18, 6)->nullable();
            });
        }

        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->decimal('system_base_qty', 18, 3)->nullable();
            $table->decimal('physical_base_qty', 18, 3)->nullable();
            $table->decimal('variance_base_qty', 18, 3)->nullable();
        });

        $this->markLikelyBaseUnits();
        $this->backfillInventoryTransactions();
        $this->backfillLineItems('sale_items');
        $this->backfillLineItems('purchase_items');
        $this->backfillLineItems('sale_return_items');
        $this->backfillLineItems('purchase_return_items');
        $this->backfillStockCounts();
    }

    public function down(): void
    {
        Schema::table('stock_count_items', function (Blueprint $table) {
            $table->dropColumn(['system_base_qty', 'physical_base_qty', 'variance_base_qty']);
        });

        foreach (['sale_items', 'purchase_items', 'sale_return_items', 'purchase_return_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['base_quantity', 'conversion_factor_snapshot']);
            });
        }

        Schema::table('inventory_transactions', function (Blueprint $table) {
            $table->dropColumn(['base_quantity_in', 'base_quantity_out', 'conversion_factor_snapshot']);
        });

        Schema::table('product_units', function (Blueprint $table) {
            $table->dropColumn(['allow_fractional_quantity', 'quantity_precision', 'is_base_unit']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('base_product_unit_id');
            $table->dropColumn('base_unit_label');
        });
    }

    private function markLikelyBaseUnits(): void
    {
        DB::table('products')
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(200, function ($products): void {
                foreach ($products as $product) {
                    $baseUnit = DB::table('product_units')
                        ->where('product_id', $product->id)
                        ->where('conversion_factor', 1)
                        ->orderByDesc('is_pos_unit')
                        ->orderBy('id')
                        ->first(['id', 'unit_name']);

                    if (! $baseUnit) {
                        continue;
                    }

                    DB::table('product_units')
                        ->where('id', $baseUnit->id)
                        ->update(['is_base_unit' => true]);

                    DB::table('products')
                        ->where('id', $product->id)
                        ->update([
                            'base_product_unit_id' => $baseUnit->id,
                            'base_unit_label' => $baseUnit->unit_name,
                        ]);
                }
            });
    }

    private function backfillInventoryTransactions(): void
    {
        DB::table('inventory_transactions')
            ->select(['id', 'product_unit_id', 'quantity_in', 'quantity_out'])
            ->orderBy('id')
            ->chunkById(200, function ($transactions): void {
                $factors = $this->factorsFor($transactions->pluck('product_unit_id')->all());

                foreach ($transactions as $transaction) {
                    $factor = $factors[(int) $transaction->product_unit_id] ?? 1.0;

                    DB::table('inventory_transactions')
                        ->where('id', $transaction->id)
                        ->update([
                            'base_quantity_in' => round((float) $transaction->quantity_in * $factor, 3),
                            'base_quantity_out' => round((float) $transaction->quantity_out * $factor, 3),
                            'conversion_factor_snapshot' => $factor,
                        ]);
                }
            });
    }

    private function backfillLineItems(string $tableName): void
    {
        DB::table($tableName)
            ->select(['id', 'product_unit_id', 'quantity'])
            ->orderBy('id')
            ->chunkById(200, function ($items) use ($tableName): void {
                $factors = $this->factorsFor($items->pluck('product_unit_id')->all());

                foreach ($items as $item) {
                    $factor = $factors[(int) $item->product_unit_id] ?? 1.0;

                    DB::table($tableName)
                        ->where('id', $item->id)
                        ->update([
                            'base_quantity' => round((float) $item->quantity * $factor, 3),
                            'conversion_factor_snapshot' => $factor,
                        ]);
                }
            });
    }

    private function backfillStockCounts(): void
    {
        DB::table('stock_count_items')
            ->select(['id', 'product_unit_id', 'system_qty', 'physical_qty', 'variance_qty'])
            ->orderBy('id')
            ->chunkById(200, function ($items): void {
                $factors = $this->factorsFor($items->pluck('product_unit_id')->all());

                foreach ($items as $item) {
                    $factor = $factors[(int) $item->product_unit_id] ?? 1.0;

                    DB::table('stock_count_items')
                        ->where('id', $item->id)
                        ->update([
                            'system_base_qty' => round((float) $item->system_qty * $factor, 3),
                            'physical_base_qty' => round((float) $item->physical_qty * $factor, 3),
                            'variance_base_qty' => round((float) $item->variance_qty * $factor, 3),
                        ]);
                }
            });
    }

    /**
     * Missing, null, or non-positive conversion factors are treated as 1 for
     * this foundation backfill so existing pilot data remains usable.
     *
     * @param  array<int, int|string|null>  $unitIds
     * @return array<int, float>
     */
    private function factorsFor(array $unitIds): array
    {
        return DB::table('product_units')
            ->whereIn('id', collect($unitIds)->filter()->unique()->values()->all())
            ->pluck('conversion_factor', 'id')
            ->map(fn ($factor) => (float) $factor > 0 ? (float) $factor : 1.0)
            ->all();
    }
};
