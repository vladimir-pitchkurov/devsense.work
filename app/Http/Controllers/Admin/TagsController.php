<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagsController extends Controller
{
    /**
     * Display a listing of the tags.
     */
    public function index()
    {
        $tags = Tag::with('translations')->orderBy('slug')->paginate(15);
        return view('admin.tags.index', compact('tags'));
    }

    /**
     * Show the form for creating a new tag.
     */
    public function create()
    {
        return view('admin.tags.create');
    }

    /**
     * Store a newly created tag in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'slug' => ['required', 'string', 'unique:tags,slug', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'translations' => ['required', 'array'],
            'translations.*.name' => ['required', 'string', 'max:255'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. oop).',
        ]);

        $tag = Tag::create([
            'slug' => Str::slug($request->slug),
        ]);

        foreach ($request->translations as $locale => $data) {
            TagTranslation::create([
                'tag_id' => $tag->id,
                'locale' => $locale,
                'name' => $data['name'],
            ]);
        }

        return redirect()
            ->route('admin.tags.index', ['locale' => app()->getLocale()])
            ->with('success', 'Tag created successfully.');
    }

    /**
     * Show the form for editing the specified tag.
     */
    public function edit(Tag $tag)
    {
        $tag->load('translations');
        return view('admin.tags.edit', compact('tag'));
    }

    /**
     * Update the specified tag in storage.
     */
    public function update(Request $request, Tag $tag)
    {
        $request->validate([
            'slug' => ['required', 'string', 'unique:tags,slug,' . $tag->id, 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'translations' => ['required', 'array'],
            'translations.*.name' => ['required', 'string', 'max:255'],
        ], [
            'slug.regex' => 'The slug must be a valid URL-friendly string (e.g. oop).',
        ]);

        $tag->update([
            'slug' => Str::slug($request->slug),
        ]);

        foreach ($request->translations as $locale => $data) {
            TagTranslation::updateOrCreate([
                'tag_id' => $tag->id,
                'locale' => $locale,
            ], [
                'name' => $data['name'],
            ]);
        }

        return redirect()
            ->route('admin.tags.index', ['locale' => app()->getLocale()])
            ->with('success', 'Tag updated successfully.');
    }

    /**
     * Remove the specified tag from storage.
     */
    public function destroy(Tag $tag)
    {
        // Detach from all articles first
        $tag->articles()->detach();
        $tag->delete();

        return redirect()
            ->route('admin.tags.index', ['locale' => app()->getLocale()])
            ->with('success', 'Tag deleted successfully.');
    }
}
