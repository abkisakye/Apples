<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndex('products', 'prod_active_name_idx', fn (Blueprint $table) => $table->index(['is_active', 'name'], 'prod_active_name_idx'));
        $this->createIndex('products', 'prod_cat_active_idx', fn (Blueprint $table) => $table->index(['category_id', 'is_active'], 'prod_cat_active_idx'));

        $this->createIndex('product_units', 'pu_active_product_idx', fn (Blueprint $table) => $table->index(['is_active', 'product_id'], 'pu_active_product_idx'));
        $this->createIndex('product_units', 'pu_unit_name_idx', fn (Blueprint $table) => $table->index('unit_name', 'pu_unit_name_idx'));
        $this->createIndex('product_units', 'pu_part_no_idx', fn (Blueprint $table) => $table->index('part_number', 'pu_part_no_idx'));

        $this->createIndex('inventory_transactions', 'it_store_product_idx', fn (Blueprint $table) => $table->index(['store_id', 'product_id'], 'it_store_product_idx'));
        $this->createIndex('inventory_transactions', 'it_store_unit_idx', fn (Blueprint $table) => $table->index(['store_id', 'product_unit_id'], 'it_store_unit_idx'));
        $this->createIndex('inventory_transactions', 'it_created_at_idx', fn (Blueprint $table) => $table->index('created_at', 'it_created_at_idx'));

        $this->createSalesTypeStatusDateIndex();
        $this->createIndex('sales', 'sales_customer_date_idx', fn (Blueprint $table) => $table->index(['customer_id', 'sale_date'], 'sales_customer_date_idx'));
        $this->createIndex('sales', 'sales_store_date_idx', fn (Blueprint $table) => $table->index(['store_id', 'sale_date'], 'sales_store_date_idx'));
        $this->createIndex('sales', 'sales_payment_date_idx', fn (Blueprint $table) => $table->index(['payment_mode_id', 'sale_date'], 'sales_payment_date_idx'));
        $this->createIndex('sales', 'sales_user_date_idx', fn (Blueprint $table) => $table->index(['created_by', 'sale_date'], 'sales_user_date_idx'));
    }

    public function down(): void
    {
        $this->dropIndexIfExists('sales', 'sales_type_status_date_idx');
        $this->dropIndexIfExists('sales', 'sales_customer_date_idx');
        $this->dropIndexIfExists('sales', 'sales_store_date_idx');
        $this->dropIndexIfExists('sales', 'sales_payment_date_idx');
        $this->dropIndexIfExists('sales', 'sales_user_date_idx');

        $this->dropIndexIfExists('inventory_transactions', 'it_store_product_idx');
        $this->dropIndexIfExists('inventory_transactions', 'it_store_unit_idx');
        $this->dropIndexIfExists('inventory_transactions', 'it_created_at_idx');

        $this->dropIndexIfExists('product_units', 'pu_active_product_idx');
        $this->dropIndexIfExists('product_units', 'pu_unit_name_idx');
        $this->dropIndexIfExists('product_units', 'pu_part_no_idx');

        $this->dropIndexIfExists('products', 'prod_active_name_idx');
        $this->dropIndexIfExists('products', 'prod_cat_active_idx');
    }

    private function createSalesTypeStatusDateIndex(): void
    {
        if ($this->indexExists('sales', 'sales_type_status_date_idx')) {
            return;
        }

        if ($this->driverName() === 'mysql') {
            DB::statement('CREATE INDEX sales_type_status_date_idx ON sales (sale_type(40), status(40), sale_date, id)');

            return;
        }

        $this->createIndex('sales', 'sales_type_status_date_idx', fn (Blueprint $table) => $table->index(['sale_type', 'status', 'sale_date', 'id'], 'sales_type_status_date_idx'));
    }

    private function createIndex(string $tableName, string $indexName, callable $definition): void
    {
        if ($this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        return match ($this->driverName()) {
            'mysql' => ! empty(DB::select(
                'SHOW INDEX FROM '.$this->wrapIdentifier($tableName).' WHERE Key_name = ?',
                [$indexName]
            )),
            'sqlite' => collect(DB::select("PRAGMA index_list('".$this->escapeSqliteName($tableName)."')"))
                ->contains(fn ($index) => (string) ($index->name ?? '') === $indexName),
            default => false,
        };
    }

    private function driverName(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    private function wrapIdentifier(string $identifier): string
    {
        return '`'.str_replace('`', '``', $identifier).'`';
    }

    private function escapeSqliteName(string $identifier): string
    {
        return str_replace("'", "''", $identifier);
    }
};
