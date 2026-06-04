<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CategoriesController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index()
    {
        Gate::authorize('manage-users');

        $categories = Category::with('translations')->orderBy('slug')->paginate(15);
        return view('admin.categories.index', compact('categories'));
    }

    /**
     * Show the form for creating a new category.
     */
    public function create()
    {
        Gate::authorize('manage-users');

        return view('admin.categories.create');
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        Gate::authorize('manage-users');

        $request->validate([
            'slug' => ['required', 'string', 'unique:categories,slug', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'translations' => ['required', 'array'],
            'translations.*.name' => ['required', 'string', 'max:255'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. php-runtimes).',
        ]);

        $category = Category::create([
            'slug' => Str::slug($request->slug),
        ]);

        foreach ($request->translations as $locale => $data) {
            CategoryTranslation::create([
                'category_id' => $category->id,
                'locale' => $locale,
                'name' => $data['name'],
            ]);
        }

        return redirect()
            ->route('admin.categories.index', ['locale' => app()->getLocale()])
            ->with('success', 'Category created successfully.');
    }

    /**
     * Show the form for editing the specified category.
     */
    public function edit(Category $category)
    {
        Gate::authorize('manage-users');

        $category->load('translations');
        return view('admin.categories.edit', compact('category'));
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category)
    {
        Gate::authorize('manage-users');

        $request->validate([
            'slug' => ['required', 'string', 'unique:categories,slug,' . $category->id, 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'translations' => ['required', 'array'],
            'translations.*.name' => ['required', 'string', 'max:255'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. php-runtimes).',
        ]);

        $category->update([
            'slug' => Str::slug($request->slug),
        ]);

        foreach ($request->translations as $locale => $data) {
            CategoryTranslation::updateOrCreate([
                'category_id' => $category->id,
                'locale' => $locale,
            ], [
                'name' => $data['name'],
            ]);
        }

        return redirect()
            ->route('admin.categories.index', ['locale' => app()->getLocale()])
            ->with('success', 'Category updated successfully.');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category)
    {
        Gate::authorize('manage-users');

        // Check if there are articles in this category before deleting
        if ($category->articles()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete category that contains articles. Please reassign articles first.']);
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index', ['locale' => app()->getLocale()])
            ->with('success', 'Category deleted successfully.');
    }
}
