@if(isset($article) && $article->author)
    @php
        $author = $article->author;
        $isPublicProfile = !$author->is_blocked && $author->is_approved && $author->is_public;
        $displayName = $isPublicProfile ? $author->name : __('ui.article.anonymous_author');
        $displayAvatar = $isPublicProfile ? $author->avatarUrl() : null;
    @endphp
    <div class="article__author-meta" style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);">
        @if($displayAvatar)
            <img src="{{ $displayAvatar }}" alt="{{ $displayName }}" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid var(--border-color);">
        @else
            <div class="author-avatar-placeholder" style="width: 40px; height: 40px; border-radius: 50%; background: rgba(255,255,255,0.05); border: 1px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-weight: bold; color: var(--text-muted); font-size: 0.9rem;">
                {{ substr($displayName, 0, 1) }}
            </div>
        @endif
        <div style="display: flex; flex-direction: column; gap: 0.15rem;">
            <span style="font-size: 0.8rem; color: var(--text-muted);">{{ __('ui.article.author_label') }}</span>
            @if($isPublicProfile)
                <a href="{{ route('authors.show', ['locale' => app()->getLocale(), 'slug' => $author->slug]) }}" style="font-weight: 600; color: var(--primary-color); text-decoration: none; font-size: 0.9rem;" class="article__author-link">
                    {{ $displayName }}
                </a>
            @else
                <span style="font-weight: 600; color: var(--text-color); font-size: 0.9rem;">
                    {{ $displayName }}
                </span>
            @endif
        </div>
        @if($article->published_at)
            <div style="margin-left: auto; display: flex; flex-direction: column; gap: 0.15rem; align-items: flex-end;">
                <span style="font-size: 0.8rem; color: var(--text-muted);">{{ __('ui.article.published_label') }}</span>
                <span style="font-weight: 500; font-size: 0.9rem; color: var(--text-color);">
                    {{ $article->published_at->translatedFormat('j M Y') }}
                </span>
            </div>
        @endif
    </div>
@endif
