<x-layout title="Admin - Categories | DevSense" description="Manage article categories">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Manage Categories</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Back to Articles
            </a>
            <a href="{{ route('admin.categories.create', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--primary">
                Create New Category
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-alert admin-alert--success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="admin-alert admin-alert--danger">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="admin-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Slug</th>
                        <th>Name (Current Locale)</th>
                        <th>Locales</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        @php
                            $currentTranslation = $category->translate(app()->getLocale());
                        @endphp
                        <tr>
                            <td><code>{{ $category->slug }}</code></td>
                            <td>
                                <strong class="article-title-text">
                                    {{ $currentTranslation?->name ?? 'Untitled (' . app()->getLocale() . ')' }}
                                </strong>
                            </td>
                            <td>
                                <div class="locale-badges">
                                    @foreach(\App\Http\Middleware\SetLocale::SUPPORTED_LOCALES as $loc)
                                        @if($category->translate($loc))
                                            <span class="locale-badge locale-badge--active" title="{{ strtoupper($loc) }} Translation exists">{{ strtoupper($loc) }}</span>
                                        @else
                                            <span class="locale-badge locale-badge--missing" title="{{ strtoupper($loc) }} Translation missing">{{ strtoupper($loc) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-right actions-cell">
                                <div class="action-buttons">
                                    <a href="{{ route('admin.categories.edit', ['locale' => app()->getLocale(), 'category' => $category->id]) }}" 
                                       class="admin-btn admin-btn--secondary" 
                                       title="Edit Category">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.categories.destroy', ['locale' => app()->getLocale(), 'category' => $category->id]) }}" 
                                          method="POST" 
                                          class="inline-form"
                                          onsubmit="return confirm('Are you sure you want to delete this category?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn--danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No categories found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            {{ $categories->links() }}
        </div>
    </div>
</div>
</x-layout>
