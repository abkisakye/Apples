<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'expense_category_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->foreignId('expense_category_id')->nullable()->after('category')->constrained('expense_categories')->nullOnDelete();
            });
        }

        if (Schema::hasTable('expenses')) {
            DB::table('expenses')
                ->select('category')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->each(function (string $category): void {
                    $now = now();
                    DB::table('expense_categories')->updateOrInsert(
                        ['name' => trim($category)],
                        ['is_active' => true, 'updated_at' => $now, 'created_at' => $now]
                    );
                });

            DB::table('expenses')
                ->whereNull('expense_category_id')
                ->whereNotNull('category')
                ->where('category', '<>', '')
                ->orderBy('id')
                ->get(['id', 'category'])
                ->each(function ($expense): void {
                    $categoryId = DB::table('expense_categories')
                        ->where('name', trim((string) $expense->category))
                        ->value('id');

                    if ($categoryId) {
                        DB::table('expenses')->where('id', $expense->id)->update([
                            'expense_category_id' => $categoryId,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('expenses') && Schema::hasColumn('expenses', 'expense_category_id')) {
            Schema::table('expenses', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('expense_category_id');
            });
        }

        Schema::dropIfExists('expense_categories');
    }
};
