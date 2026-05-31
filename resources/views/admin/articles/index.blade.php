<x-layout title="Admin - Articles | DevSense" description="Manage articles and documentation guides">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Manage Articles</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            <a href="{{ route('admin.categories.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Categories
            </a>
            <a href="{{ route('admin.tags.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Tags
            </a>
            <a href="{{ route('admin.profile.edit', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Edit Profile
            </a>
            <a href="{{ route('admin.articles.create', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--primary">
                Create New Article
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="admin-alert admin-alert--success">
            {{ session('success') }}
        </div>
    @endif

    <div class="admin-card">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title (Current Locale)</th>
                        <th>Slug</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Locales</th>
                        <th>Status</th>
                        <th>Published At</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($articles as $article)
                        @php
                            $currentTranslation = $article->translate(app()->getLocale());
                        @endphp
                        <tr>
                            <td>
                                <div class="article-title-cell">
                                    <span class="article-title-text">
                                        {{ $currentTranslation?->title ?? 'Untitled (' . app()->getLocale() . ')' }}
                                    </span>
                                </div>
                            </td>
                            <td><code>{{ $article->slug }}</code></td>
                            <td>
                                @if($article->category)
                                    <span class="admin-badge admin-badge--category">{{ $article->category->slug }}</span>
                                @else
                                    <span class="admin-badge admin-badge--none">None</span>
                                @endif
                            </td>
                            <td>{{ $article->author?->name ?? 'Unknown' }}</td>
                            <td>
                                <div class="locale-badges">
                                    @foreach(['en', 'ru', 'ua', 'bg'] as $loc)
                                        @if($article->translate($loc))
                                            <span class="locale-badge locale-badge--active" title="{{ strtoupper($loc) }} Translation exists">{{ strtoupper($loc) }}</span>
                                        @else
                                            <span class="locale-badge locale-badge--missing" title="{{ strtoupper($loc) }} Translation missing">{{ strtoupper($loc) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($article->is_published)
                                    <span class="admin-badge admin-badge--published">Published</span>
                                @else
                                    <span class="admin-badge admin-badge--draft">Draft</span>
                                @endif
                            </td>
                            <td>
                                {{ $article->published_at ? $article->published_at->format('M d, Y H:i') : '-' }}
                            </td>
                            <td class="text-right actions-cell">
                                <div class="action-buttons">
                                    @if($article->is_published && $article->category)
                                        <a href="{{ route($article->category->slug . '.show', ['locale' => app()->getLocale(), 'slug' => $article->slug]) }}" 
                                           target="_blank" 
                                           class="admin-btn admin-btn--icon" 
                                           title="View on site">
                                            👁️
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.articles.edit', ['locale' => app()->getLocale(), 'article' => $article->id]) }}" 
                                       class="admin-btn admin-btn--secondary" 
                                       title="Edit article">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.articles.destroy', ['locale' => app()->getLocale(), 'article' => $article->id]) }}" 
                                          method="POST" 
                                          class="inline-form"
                                          onsubmit="return confirm('Are you sure you want to delete this article?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn--danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No articles found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            {{ $articles->links() }}
        </div>
    </div>
</div>

<style>
.admin-container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1rem;
}

.admin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.admin-title {
    font-family: 'Outfit', sans-serif;
    font-size: 2.25rem;
    font-weight: 800;
    color: var(--text-color);
}

.admin-card {
    background-color: var(--card-bg, rgba(255, 255, 255, 0.02));
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
}

.admin-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem 1rem;
    border-radius: 0.5rem;
    font-family: inherit;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    cursor: pointer;
    transition: transform 0.2s, background-color 0.2s, opacity 0.2s;
    border: 1px solid transparent;
}

.admin-btn--primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
    color: #fff;
}

.admin-btn--secondary {
    background-color: transparent;
    border-color: var(--border-color);
    color: var(--text-color);
}

.admin-btn--secondary:hover {
    background-color: rgba(var(--border-color-rgb), 0.1);
}

.admin-btn--danger {
    background-color: rgba(239, 68, 68, 0.1);
    border-color: rgba(239, 68, 68, 0.2);
    color: #ef4444;
}

.admin-btn--danger:hover {
    background-color: #ef4444;
    color: #fff;
}

.admin-btn--icon {
    padding: 0.5rem;
    border-color: var(--border-color);
    color: var(--text-color);
    font-size: 1rem;
}

.admin-btn:hover {
    transform: translateY(-1px);
}

.admin-alert {
    padding: 1rem;
    border-radius: 0.5rem;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.admin-alert--success {
    background-color: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

.table-responsive {
    overflow-x: auto;
}

.admin-table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.admin-table th, .admin-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.admin-table th {
    font-weight: 700;
    color: var(--text-muted);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.article-title-cell {
    display: flex;
    flex-direction: column;
}

.article-title-text {
    font-weight: 600;
    color: var(--text-color);
}

.admin-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 0.25rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
}

.admin-badge--category {
    background-color: rgba(var(--primary-color-rgb), 0.1);
    color: var(--primary-color);
    border: 1px solid rgba(var(--primary-color-rgb), 0.2);
}

.admin-badge--published {
    background-color: rgba(16, 185, 129, 0.1);
    color: #10b981;
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.admin-badge--draft {
    background-color: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.admin-badge--none {
    background-color: rgba(156, 163, 175, 0.1);
    color: #9ca3af;
}

.locale-badges {
    display: flex;
    gap: 0.25rem;
}

.locale-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.35rem;
    border-radius: 0.25rem;
}

.locale-badge--active {
    background-color: var(--primary-color);
    color: #fff;
}

.locale-badge--missing {
    background-color: rgba(156, 163, 175, 0.15);
    color: var(--text-muted);
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
    align-items: center;
}

.inline-form {
    display: inline;
}

.text-right {
    text-align: right;
}

.admin-pagination {
    margin-top: 1.5rem;
    display: flex;
    justify-content: center;
}
</style>
</x-layout>
