<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    :structured-data="$structuredData"
    breadcrumb-current="#{{ $tag->translate()?->name ?? $tag->slug }}"
>
    <div class="tag-show-container" style="max-width: 1000px; margin: 2rem auto; padding: 0 1.5rem;">
        <nav class="article__back" aria-label="{{ __('ui.tags_show.back') }}" style="margin-bottom: 2rem;">
            <a href="{{ route('tags.index') }}" class="article__back-link">
                ← {{ __('ui.tags_show.back') }}
            </a>
        </nav>

        <section class="hero" style="margin-bottom: 3rem;">
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
                            $categoryName = $article->category?->translate()?->name ?? $article->category?->slug;
                        @endphp
                        <li class="guides__item">
                            <article class="card">
                                <header class="card__header">
                                    <div class="card__meta-top">
                                        @if ($article->category)
                                            <span class="card__category card__category--{{ $article->category->slug }}">
                                                {{ $categoryName }}
                                            </span>
                                        @endif
                                        <span class="card__date">
                                            {{ $article->published_at ? $article->published_at->translatedFormat('j M Y') : '' }}
                                        </span>
                                    </div>
                                    <h2 class="card__title">
                                        <a href="{{ $article->url() }}" class="card__title-link">
                                            {{ $translation?->title ?? $article->slug }}
                                        </a>
                                    </h2>
                                </header>
                                <div class="card__body">
                                    <p class="card__excerpt">
                                        {{ $translation?->description ?? '' }}
                                    </p>
                                </div>
                                <footer class="card__footer">
                                    @if($article->author)
                                        <div class="card__author">
                                            <div class="card__author-avatar" title="{{ $article->author->name }}">
                                                {{ substr($article->author->name, 0, 1) }}
                                            </div>
                                            <span class="card__author-name">{{ $article->author->name }}</span>
                                        </div>
                                    @endif
                                    <a href="{{ $article->url() }}" class="card__link" aria-label="{{ $translation?->title }}">
                                        {{ __('ui.search.read_more') }}
                                    </a>
                                </footer>
                            </article>
                        </li>
                    @endforeach
                </ul>

                <div class="search-pagination">
                    {{ $articles->links('partials.pagination') }}
                </div>
            @endif
        </section>
    </div>

    <style>
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.01);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        color: var(--text-muted);
    }
    </style>
</x-layout>
