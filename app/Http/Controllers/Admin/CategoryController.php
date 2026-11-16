<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryUpsertRequest;
use App\Models\AuditLog;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        return store_page('Admin/Categories/Index', [
            'categories' => Category::withCount('products')->orderBy('position')->paginate(20),
        ]);
    }

    public function create()
    {
        return store_page('Admin/Categories/Form', ['category' => null]);
    }

    public function store(CategoryUpsertRequest $request)
    {
        $category = Category::create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);
        $this->audit('category.created', $category);

        return redirect()->route('admin.categories.index')->with('success', 'Category created.');
    }

    public function show(Category $category)
    {
        return redirect()->route('admin.categories.edit', $category);
    }

    public function edit(Category $category)
    {
        return store_page('Admin/Categories/Form', ['category' => $category]);
    }

    public function update(CategoryUpsertRequest $request, Category $category)
    {
        $category->update($request->validated() + ['is_active' => $request->boolean('is_active')]);
        $this->audit('category.updated', $category);

        return redirect()->route('admin.categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->withErrors(['category' => 'Move or archive the category products before deleting it.']);
        }
        $category->delete();
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'category.deleted',
            'subject_type' => Category::class,
            'subject_id' => $category->id,
            'metadata' => ['slug' => $category->slug],
        ]);

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted.');
    }

    private function audit(string $action, Category $category): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => Category::class,
            'subject_id' => $category->id,
            'metadata' => ['slug' => $category->slug, 'is_active' => $category->is_active],
        ]);
    }
}
