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
                    @php
                        $currentUser = auth()->user();
                        $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $author->id);
                        $showRealDetails = !$author->is_anonymous || $isOwnerOrAdmin;

                        $displayName = $showRealDetails ? $author->name : (app()->getLocale() === 'ru' ? 'Анонимный соискатель' : 'Anonymous Candidate');
                        $displayAvatar = $showRealDetails ? $author->avatarUrl() : 'https://ui-avatars.com/api/?name=A+C&size=256&background=64748b&color=ffffff&bold=true&format=png';
                    @endphp
                    <li class="authors-grid__item">
                        <article class="author-card" itemscope itemtype="https://schema.org/Person">
                            <a
                                href="{{ route('authors.show', ['slug' => $author->slug]) }}"
                                class="author-card__link"
                                aria-label="{{ $displayName }}"
                            >
                                <div class="author-card__avatar-wrap">
                                    <img
                                        src="{{ $displayAvatar }}"
                                        alt="{{ $displayName }}"
                                        class="author-card__avatar"
                                        width="96"
                                        height="96"
                                        loading="lazy"
                                        itemprop="image"
                                    >
                                </div>
                                <div class="author-card__info">
                                    <h2 class="author-card__name" itemprop="name">
                                        {{ $displayName }}
                                        @if ($author->is_anonymous)
                                            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal; margin-left: 0.25rem;">(🔒)</span>
                                        @endif
                                    </h2>
                                    @if ($author->job_title)
                                        <p class="author-card__title" itemprop="jobTitle">{{ $author->job_title }}</p>
                                    @endif
                                    @if ($author->bio)
                                        <p class="author-card__bio">{{ Str::limit(strip_tags($author->bio), 120) }}</p>
                                    @endif
                                    @if ($showRealDetails)
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
                                    @else
                                        <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem; font-style: italic;">
                                            🔒 {{ app()->getLocale() === 'ru' ? 'Контакты скрыты' : 'Contacts hidden' }}
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
