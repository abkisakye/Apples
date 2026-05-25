<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuickCategoryController extends Controller
{
    public function productCategory(Request $request, AuditLogService $auditLogService): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')],
        ], [
            'name.required' => 'Category name is required.',
            'name.unique' => 'That category already exists.',
        ]);

        $category = Category::query()->create([
            'name' => trim($validated['name']),
            'is_active' => true,
        ]);

        $auditLogService->record('categories.quick_created', $category, "Category {$category->name} created from product form.", [
            'category_id' => $category->id,
        ]);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ], 201);
    }

    public function expenseCategory(Request $request, AuditLogService $auditLogService): JsonResponse
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('expense_categories', 'name')],
        ], [
            'name.required' => 'Category name is required.',
            'name.unique' => 'That category already exists.',
        ]);

        $category = ExpenseCategory::query()->create([
            'name' => trim($validated['name']),
            'is_active' => true,
        ]);

        $auditLogService->record('expense-categories.quick_created', $category, "Expense category {$category->name} created from expense form.", [
            'expense_category_id' => $category->id,
        ]);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
        ], 201);
    }
}
