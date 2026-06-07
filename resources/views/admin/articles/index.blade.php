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
