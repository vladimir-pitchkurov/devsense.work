<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    og-type="profile"
    :structured-data="$structuredData"
    :breadcrumb-current="$author->name"
>
    <nav class="article__back" aria-label="{{ __('ui.authors_show.nav_aria') }}">
        <a href="{{ route('authors.index') }}" class="article__back-link">
            ← {{ __('ui.authors_show.back') }}
        </a>
    </nav>

    <article class="author-profile" itemscope itemtype="https://schema.org/Person">
        {{-- Avatar + name + title block --}}
        <header class="author-profile__header">
            <div class="author-profile__avatar-wrap">
                <img
                    src="{{ $author->avatarUrl() }}"
                    alt="{{ $author->name }}"
                    class="author-profile__avatar"
                    width="128"
                    height="128"
                    itemprop="image"
                >
            </div>
            <div class="author-profile__meta">
                <h1 class="author-profile__name" itemprop="name">{{ $author->name }}</h1>
                @if ($author->job_title)
                    <p class="author-profile__job-title" itemprop="jobTitle">{{ $author->job_title }}</p>
                @endif

                {{-- Social links --}}
                @php $links = $author->socialLinks(); @endphp
                @if (!empty($links))
                    <ul class="author-profile__socials" aria-label="{{ __('ui.authors_show.socials_label') }}">
                        @foreach ($links as $network => $url)
                            <li>
                                <a
                                    href="{{ $url }}"
                                    class="author-profile__social-link author-profile__social-link--{{ $network }}"
                                    target="_blank"
                                    rel="noopener noreferrer me"
                                    itemprop="sameAs"
                                    aria-label="{{ ucfirst($network) }}"
                                >
                                    @switch($network)
                                        @case('github')
                                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.3 3.438 9.8 8.205 11.387.6.113.82-.263.82-.583 0-.288-.01-1.05-.015-2.06-3.338.725-4.042-1.61-4.042-1.61-.546-1.387-1.333-1.756-1.333-1.756-1.09-.745.082-.73.082-.73 1.205.085 1.84 1.238 1.84 1.238 1.07 1.834 2.809 1.304 3.495.997.108-.775.418-1.305.762-1.605-2.665-.3-5.467-1.332-5.467-5.93 0-1.31.467-2.38 1.235-3.22-.123-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.3 1.23a11.5 11.5 0 013.003-.404c1.02.005 2.046.138 3.003.404 2.29-1.552 3.297-1.23 3.297-1.23.653 1.652.242 2.873.12 3.176.77.84 1.233 1.91 1.233 3.22 0 4.61-2.807 5.625-5.48 5.92.43.37.823 1.102.823 2.222 0 1.606-.015 2.898-.015 3.293 0 .322.216.7.825.58C20.565 21.796 24 17.298 24 12c0-6.63-5.37-12-12-12z"/></svg>
                                            GitHub
                                            @break
                                        @case('linkedin')
                                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                                            LinkedIn
                                            @break
                                        @case('twitter')
                                            <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.748l7.73-8.835L1.254 2.25H8.08l4.259 5.631 5.905-5.631zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                                            X / Twitter
                                            @break
                                        @default
                                            🌐 {{ ucfirst($network) }}
                                    @endswitch
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </header>

        {{-- Biography --}}
        @if ($author->bio)
            <section class="author-profile__bio" aria-label="{{ __('ui.authors_show.bio_label') }}">
                <h2 class="author-profile__section-title">{{ __('ui.authors_show.bio_label') }}</h2>
                <div class="author-profile__bio-text markdown-body" itemprop="description">
                    {!! nl2br(e($author->bio)) !!}
                </div>
            </section>
        @endif

        {{-- Articles List --}}
        <section class="author-profile__articles" aria-label="{{ __('ui.authors_show.articles_label') }}" style="margin-top: 3rem;">
            <h2 class="author-profile__section-title">{{ __('ui.authors_show.articles_label') }}</h2>
            @if ($articles->isEmpty())
                <p class="author-profile__no-articles">{{ __('ui.authors_index.empty') }}</p>
            @else
                <ul class="guides__list" style="margin-top: 1.5rem;">
                    @foreach ($articles as $article)
                        @php
                            $translation = $article->translate();
                        @endphp
                        <li class="guides__item">
                            <article class="card">
                                <header class="card__header">
                                    <h3 class="card__title" style="margin: 0; font-size: 1.25rem; font-family: 'Outfit', sans-serif;">
                                        <a href="{{ $article->url() }}" style="color: inherit; text-decoration: none; display: block;">
                                            {{ $translation?->title ?? 'Untitled' }}
                                        </a>
                                    </h3>
                                    @if ($article->category)
                                        <span class="admin-badge admin-badge--category" style="margin-top: 0.5rem; display: inline-block; font-size: 0.7rem; font-weight: 700; text-transform: uppercase;">
                                            {{ $article->category->slug }}
                                        </span>
                                    @endif
                                </header>
                                <div class="card__body" style="padding: 1rem 0;">
                                    <p class="card__excerpt" style="margin: 0; font-size: 0.95rem; line-height: 1.5;">
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
            @endif
        </section>
    </article>
</x-layout>
