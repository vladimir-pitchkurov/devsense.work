<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    breadcrumb-current="{{ __('ui.authors_index.breadcrumb') }}"
>
    <section class="hero">
        <h1 class="hero__title">{{ __('ui.authors_index.hero_title') }}</h1>
        <p class="hero__description">{{ __('ui.authors_index.hero_lead') }}</p>
    </section>

    <section class="authors-grid" aria-label="{{ __('ui.authors_index.hero_title') }}">
        @if ($authors->isEmpty())
            <div class="empty-state">
                <p>{{ __('ui.authors_index.empty') }}</p>
            </div>
        @else
            <ul class="authors-grid__list">
                @foreach ($authors as $author)
                    <li class="authors-grid__item">
                        <article class="author-card" itemscope itemtype="https://schema.org/Person">
                            <a
                                href="{{ route('authors.show', ['slug' => $author->slug]) }}"
                                class="author-card__link"
                                aria-label="{{ $author->name }}"
                            >
                                <div class="author-card__avatar-wrap">
                                    <img
                                        src="{{ $author->avatarUrl() }}"
                                        alt="{{ $author->name }}"
                                        class="author-card__avatar"
                                        width="96"
                                        height="96"
                                        loading="lazy"
                                        itemprop="image"
                                    >
                                </div>
                                <div class="author-card__info">
                                    <h2 class="author-card__name" itemprop="name">{{ $author->name }}</h2>
                                    @if ($author->job_title)
                                        <p class="author-card__title" itemprop="jobTitle">{{ $author->job_title }}</p>
                                    @endif
                                    @if ($author->bio)
                                        <p class="author-card__bio">{{ Str::limit(strip_tags($author->bio), 120) }}</p>
                                    @endif
                                    @php $links = $author->socialLinks(); @endphp
                                    @if (!empty($links))
                                        <div class="author-card__socials">
                                            @foreach ($links as $network => $url)
                                                <span class="author-card__social-badge author-card__social-badge--{{ $network }}">
                                                    {{ ucfirst($network) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </a>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layout>
