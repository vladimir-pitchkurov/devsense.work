<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    :structured-data="$structuredData"
    breadcrumb-current="#{{ $tag->translate()?->name ?? $tag->slug }}"
>
    <nav class="article__back" aria-label="{{ __('ui.tags_show.back') }}">
        <a href="{{ route('tags.index') }}" class="article__back-link">
            ← {{ __('ui.tags_show.back') }}
        </a>
    </nav>

    <section class="hero">
        <h1 class="hero__title">
            <span style="opacity: 0.5;">#</span>{{ $tag->translate()?->name ?? $tag->slug }}
        </h1>
        <p class="hero__description">{{ __('ui.tags_show.hero_title') }} <strong>#{{ $tag->translate()?->name ?? $tag->slug }}</strong></p>
    </section>

    <section class="guides" aria-label="{{ __('ui.tags_show.hero_title') }}">
        @if ($articles->isEmpty())
            <div class="empty-state">
                <p>{{ __('ui.tags_show.empty') }}</p>
            </div>
        @else
            <ul class="guides__list">
                @foreach ($articles as $article)
                    @php
                        $translation = $article->translate();
                    @endphp
                    <li class="guides__item">
                        <article class="card">
                            <header class="card__header">
                                <h2 class="card__title" style="font-family: 'Outfit', sans-serif;">
                                    <a href="{{ $article->url() }}" style="color: inherit; text-decoration: none;">
                                        {{ $translation?->title ?? 'Untitled' }}
                                    </a>
                                </h2>
                                @if ($article->category)
                                    <span class="admin-badge admin-badge--category" style="margin-top: 0.5rem; display: inline-block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                                        {{ $article->category->slug }}
                                    </span>
                                @endif
                            </header>
                            <div class="card__body" style="padding: 1rem 0;">
                                <p class="card__excerpt" style="font-size: 0.95rem; line-height: 1.5; margin: 0;">
                                    {{ $translation?->description ?? '' }}
                                </p>
                            </div>
                            <footer class="card__footer" style="padding: 0;">
                                <a href="{{ $article->url() }}" class="card__link" style="font-weight: 600; font-size: 0.9rem;">
                                    Read Guide →
                                </a>
                            </footer>
                        </article>
                    </li>
                @endforeach
            </ul>

            <div class="pagination-container" style="margin-top: 2rem; display: flex; justify-content: center;">
                {{ $articles->links() }}
            </div>
        @endif
    </section>

    <style>
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.01);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        color: var(--text-muted);
    }
    
    .admin-badge--category {
        background-color: rgba(var(--primary-color-rgb), 0.1);
        color: var(--primary-color);
        border: 1px solid rgba(var(--primary-color-rgb), 0.2);
        padding: 0.25rem 0.5rem;
        border-radius: 0.25rem;
    }
    </style>
</x-layout>
