<x-layout title="Admin - Articles | DevSense" description="Manage articles and documentation guides">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Manage Articles</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            @can('manage-users')
                <a href="{{ route('admin.categories.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Categories
                </a>
            @endcan
            @can('manage-tags')
                <a href="{{ route('admin.tags.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Tags
                </a>
            @endcan
            <a href="{{ route('admin.profile.edit', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Edit Profile
            </a>
            @can('manage-users')
                <a href="{{ route('admin.users.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Users
                </a>
                <a href="{{ route('admin.tickets.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                    Tickets
                </a>
            @endcan
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
        <!-- Filters panel -->
        <div class="admin-filters-card" style="margin-bottom: 1.5rem; background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); padding: 1.25rem; border-radius: 0.75rem;">
            <form action="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" method="GET" style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                <div style="flex: 1 1 200px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Search</label>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Title, slug, content..." class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Category</label>
                    <select name="category" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="">All Categories</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>{{ $cat->slug }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Status</label>
                    <select name="status" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="">All Statuses</option>
                        <option value="published" {{ request('status') === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending_approval" {{ request('status') === 'pending_approval' ? 'selected' : '' }}>In Review</option>
                        <option value="pending_update" {{ request('status') === 'pending_update' ? 'selected' : '' }}>Update in Review</option>
                    </select>
                </div>
                @if(Auth::user()->isAdmin() && !empty($authors))
                    <div style="flex: 1 1 150px;">
                        <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Author</label>
                        <select name="author" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                            <option value="">All Authors</option>
                            @foreach($authors as $author)
                                <option value="{{ $author->id }}" {{ request('author') == $author->id ? 'selected' : '' }}>{{ $author->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div style="flex: 1 1 150px;">
                    <label class="form-label" style="display: block; margin-bottom: 0.5rem; font-size: 0.75rem;">Sort By</label>
                    <select name="sort_by" class="form-input" style="padding: 0.5rem 0.75rem; font-size: 0.875rem;">
                        <option value="created_at_desc" {{ request('sort_by') === 'created_at_desc' ? 'selected' : '' }}>Newest Created</option>
                        <option value="created_at_asc" {{ request('sort_by') === 'created_at_asc' ? 'selected' : '' }}>Oldest Created</option>
                        <option value="published_at_desc" {{ request('sort_by') === 'published_at_desc' ? 'selected' : '' }}>Newest Published</option>
                        <option value="published_at_asc" {{ request('sort_by') === 'published_at_asc' ? 'selected' : '' }}>Oldest Published</option>
                        <option value="slug_asc" {{ request('sort_by') === 'slug_asc' ? 'selected' : '' }}>Slug (A-Z)</option>
                        <option value="slug_desc" {{ request('sort_by') === 'slug_desc' ? 'selected' : '' }}>Slug (Z-A)</option>
                    </select>
                </div>
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="admin-btn admin-btn--primary" style="padding: 0.5rem 1rem;">Filter</button>
                    <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary" style="padding: 0.5rem 1rem;">Reset</a>
                </div>
            </form>
        </div>

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
                                    @foreach(\App\Http\Middleware\SetLocale::SUPPORTED_LOCALES as $loc)
                                        @if($article->translate($loc))
                                            <span class="locale-badge locale-badge--active" title="{{ strtoupper($loc) }} Translation exists">{{ strtoupper($loc) }}</span>
                                        @else
                                            <span class="locale-badge locale-badge--missing" title="{{ strtoupper($loc) }} Translation missing">{{ strtoupper($loc) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if(!$article->is_approved)
                                    <span class="admin-badge admin-badge--draft" title="Awaiting moderator approval">In Review</span>
                                @elseif($article->pendingTranslations->isNotEmpty())
                                    @if($article->is_published)
                                        <span class="admin-badge admin-badge--published">Published</span>
                                        <span class="admin-badge admin-badge--draft" style="display: block; margin-top: 0.25rem; font-size: 0.65rem;" title="Has content updates awaiting approval">Update in Review</span>
                                    @else
                                        <span class="admin-badge admin-badge--none">Draft</span>
                                        <span class="admin-badge admin-badge--draft" style="display: block; margin-top: 0.25rem; font-size: 0.65rem;" title="Has content updates awaiting approval">Update in Review</span>
                                    @endif
                                @else
                                    @if($article->is_published)
                                        <span class="admin-badge admin-badge--published">Published</span>
                                    @else
                                        <span class="admin-badge admin-badge--none">Draft</span>
                                    @endif
                                @endif
                            </td>
                            <td>
                                {{ $article->published_at ? $article->published_at->format('M d, Y H:i') : '-' }}
                            </td>
                            <td class="text-right actions-cell">
                                <div class="action-buttons">
                                    @if($article->category && $article->url() !== '#')
                                        <a href="{{ $article->url() }}" 
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


</x-layout>
