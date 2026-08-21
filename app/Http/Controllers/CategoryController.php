<?php

namespace App\Http\Controllers;

use App\Enums\CategoryType;
use App\Http\Requests\Categories\StoreCategoryRequest;
use App\Http\Requests\Categories\UpdateCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    private const ICON_CHOICES = [
        'tag', 'banknotes', 'wallet', 'home', 'cog', 'flag', 'document-chart-bar',
        'arrow-path', 'arrows-right-left', 'adjustments', 'exclamation-triangle',
        'arrow-down-circle', 'arrow-up-circle', 'arrow-trending-up', 'calendar',
    ];

    public function index(): Response
    {
        $categories = Category::orderBy('name')->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'type' => $category->type->value,
                'icon' => $category->icon,
                'color' => $category->color,
                'is_system' => $category->is_system,
                'editable' => $category->isEditable() && $category->user_id === Auth::id(),
            ]);

        return Inertia::render('Categories/Index', [
            'expenseCategories' => $categories->where('type', CategoryType::Expense->value)->values(),
            'incomeCategories' => $categories->where('type', CategoryType::Income->value)->values(),
            'iconChoices' => self::ICON_CHOICES,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Auth::user()->categories()->create($request->validated());

        return back()->with('success', __('categories.saved'));
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', __('categories.saved'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        if ($category->transactions()->exists()) {
            return back()->with('error', __('categories.has_transactions'));
        }

        $category->delete();

        return back()->with('success', __('categories.deleted'));
    }
}
