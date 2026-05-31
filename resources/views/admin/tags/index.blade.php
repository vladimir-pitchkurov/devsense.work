<x-layout title="Admin - Tags | DevSense" description="Manage article tags">
<div class="admin-container">
    <div class="admin-header">
        <h1 class="admin-title">Manage Tags</h1>
        <div class="header-actions" style="display: flex; gap: 0.5rem;">
            <a href="{{ route('admin.dashboard', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Dashboard
            </a>
            <a href="{{ route('admin.articles.index', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--secondary">
                Back to Articles
            </a>
            <a href="{{ route('admin.tags.create', ['locale' => app()->getLocale()]) }}" class="admin-btn admin-btn--primary">
                Create New Tag
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
                    @forelse ($tags as $tag)
                        @php
                            $currentTranslation = $tag->translate(app()->getLocale());
                        @endphp
                        <tr>
                            <td><code>#{{ $tag->slug }}</code></td>
                            <td>
                                <strong class="article-title-text">
                                    {{ $currentTranslation?->name ?? 'Untitled (' . app()->getLocale() . ')' }}
                                </strong>
                            </td>
                            <td>
                                <div class="locale-badges">
                                    @foreach(['en', 'ru', 'ua', 'bg'] as $loc)
                                        @if($tag->translate($loc))
                                            <span class="locale-badge locale-badge--active" title="{{ strtoupper($loc) }} Translation exists">{{ strtoupper($loc) }}</span>
                                        @else
                                            <span class="locale-badge locale-badge--missing" title="{{ strtoupper($loc) }} Translation missing">{{ strtoupper($loc) }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </td>
                            <td class="text-right actions-cell">
                                <div class="action-buttons">
                                    <a href="{{ route('admin.tags.edit', ['locale' => app()->getLocale(), 'tag' => $tag->id]) }}" 
                                       class="admin-btn admin-btn--secondary" 
                                       title="Edit Tag">
                                        Edit
                                    </a>
                                    <form action="{{ route('admin.tags.destroy', ['locale' => app()->getLocale(), 'tag' => $tag->id]) }}" 
                                          method="POST" 
                                          class="inline-form"
                                          onsubmit="return confirm('Are you sure you want to delete this tag?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="admin-btn admin-btn--danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center">No tags found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            {{ $tags->links() }}
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

.admin-alert--danger {
    background-color: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
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

.article-title-text {
    font-weight: 600;
    color: var(--text-color);
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
