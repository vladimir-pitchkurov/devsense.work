@php
    $currentUser = auth()->user();
    $isOwnerOrAdmin = $currentUser && ($currentUser->isAdmin() || $currentUser->id === $author->id);
    $showRealDetails = !$author->is_anonymous || $isOwnerOrAdmin;

    $displayName = $showRealDetails ? $author->name : (app()->getLocale() === 'ru' ? 'Анонимный соискатель' : 'Anonymous Candidate');
    $displayAvatar = $showRealDetails ? $author->avatarUrl() : 'https://ui-avatars.com/api/?name=A+C&size=256&background=64748b&color=ffffff&bold=true&format=png';
@endphp

<x-layout
    :title="$displayName . ' — DevSense'"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    og-type="profile"
    :structured-data="$structuredData"
    :breadcrumb-current="$displayName"
>
    <nav class="article__back" aria-label="{{ __('ui.authors_show.nav_aria') }}">
        <a href="{{ route('authors.index') }}" class="article__back-link">
            ← {{ __('ui.authors_show.back') }}
        </a>
    </nav>

    <article class="author-profile" itemscope itemtype="https://schema.org/Person">
        {{-- Anonymous Alert Tag --}}
        @if ($author->is_anonymous)
            <div style="background: rgba(99, 102, 241, 0.08); border: 1px solid rgba(99, 102, 241, 0.2); padding: 0.75rem 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; font-size: 0.9rem; color: var(--text-color);">
                🔒 <strong>{{ app()->getLocale() === 'ru' ? 'Анонимный профиль' : 'Anonymous Profile' }}</strong>
                @if ($isOwnerOrAdmin)
                    <span style="color: var(--primary-color);">({{ app()->getLocale() === 'ru' ? 'Вы видите полные данные, так как являетесь владельцем или администратором' : 'You see full details because you are the owner or admin' }})</span>
                @endif
            </div>
        @endif

        {{-- Avatar + name + title block --}}
        <header class="author-profile__header">
            <div class="author-profile__avatar-wrap">
                <img
                    src="{{ $displayAvatar }}"
                    alt="{{ $displayName }}"
                    class="author-profile__avatar"
                    width="128"
                    height="128"
                    itemprop="image"
                >
            </div>
            <div class="author-profile__meta">
                <h1 class="author-profile__name" itemprop="name">{{ $displayName }}</h1>
                @if ($author->job_title)
                    <p class="author-profile__job-title" itemprop="jobTitle">{{ $author->job_title }}</p>
                @endif

                @if ($author->job_status)
                    <div style="margin-top: 0.5rem;">
                        @switch($author->job_status)
                            @case('seeking')
                                <span style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">
                                    💼 {{ app()->getLocale() === 'ru' ? 'Активно ищу работу' : 'Seeking Work' }}
                                </span>
                                @break
                            @case('passively_seeking')
                                <span style="background: rgba(245, 158, 11L, 0.15); color: #f59e0b; border: 1px solid rgba(245, 158, 11L, 0.2); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">
                                    🔍 {{ app()->getLocale() === 'ru' ? 'Рассматриваю предложения' : 'Passively Seeking' }}
                                </span>
                                @break
                            @case('not_looking')
                                <span style="background: rgba(100, 116, 139, 0.15); color: #64748b; border: 1px solid rgba(100, 116, 139, 0.2); padding: 0.25rem 0.6rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700;">
                                    ☕ {{ app()->getLocale() === 'ru' ? 'Не ищу работу' : 'Not Looking' }}
                                </span>
                                @break
                        @endswitch
                    </div>
                @endif

                <button onclick="openReportModal('user', {{ $author->id }})" class="btn-report" style="margin-top: 0.5rem; margin-bottom: 0.5rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); border-radius: 0.375rem; padding: 0.4rem 0.7rem; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 0.4rem; cursor: pointer; transition: border-color 0.2s, color 0.2s; width: fit-content; font-family: inherit;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                        <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                        <line x1="4" y1="22" x2="4" y2="15"></line>
                    </svg>
                    <span>Report Profile</span>
                </button>

                {{-- Social links --}}
                @if ($showRealDetails)
                    @php $links = $author->socialLinks(); @endphp
                    @if (!empty($links) || $author->email)
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
                            @if ($author->email)
                                <li>
                                    <a
                                        href="mailto:{{ $author->email }}"
                                        class="author-profile__social-link"
                                        aria-label="Email"
                                        style="display: inline-flex; align-items: center;"
                                    >
                                        <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                        Email
                                    </a>
                                </li>
                            @endif
                        </ul>
                    @endif
                @else
                    <div style="margin-top: 0.75rem; font-size: 0.85rem; color: var(--text-muted); background: rgba(0,0,0,0.1); border: 1px solid var(--border-color); padding: 0.5rem 0.75rem; border-radius: 0.5rem;">
                        🔒 {{ app()->getLocale() === 'ru' ? 'Контактные данные скрыты соискателем. Связаться можно через личные сообщения.' : 'Contact links are hidden by the candidate. You can contact them via personal messages.' }}
                    </div>
                @endif
            </div>
        </header>

        {{-- Intro Section --}}
        @if ($author->intro)
            <section class="author-profile__intro" style="margin-top: 2rem; border-left: 3px solid var(--primary-color); padding-left: 1rem;">
                <p style="font-size: 1.1rem; font-style: italic; color: var(--text-color); margin: 0; line-height: 1.6;">
                    "{{ $author->intro }}"
                </p>
            </section>
        @endif

        {{-- Biography --}}
        @if ($author->bio)
            <section class="author-profile__bio" aria-label="{{ __('ui.authors_show.bio_label') }}">
                <h2 class="author-profile__section-title">{{ __('ui.authors_show.bio_label') }}</h2>
                <div class="author-profile__bio-text markdown-body" itemprop="description">
                    {!! nl2br(e($author->bio)) !!}
                </div>
            </section>
        @endif

        {{-- Experience Section --}}
        @if ($author->experience)
            <section class="author-profile__experience" style="margin-top: 3rem;">
                <h2 class="author-profile__section-title">{{ app()->getLocale() === 'ru' ? 'Опыт работы' : 'Work Experience' }}</h2>
                <div style="font-size: 0.95rem; color: var(--text-color); line-height: 1.6; white-space: pre-line; background: rgba(0,0,0,0.08); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 1rem; margin-top: 1rem;">
                    {{ $author->experience }}
                </div>
            </section>
        @endif

        {{-- Portfolio Section --}}
        @if (!empty($author->portfolio))
            <section class="author-profile__portfolio" style="margin-top: 3rem;">
                <h2 class="author-profile__section-title">{{ app()->getLocale() === 'ru' ? 'Портфолио проектов' : 'Portfolio Projects' }}</h2>
                <div style="display: flex; flex-direction: column; gap: 2rem; margin-top: 1.5rem;">
                    @foreach($author->portfolio as $project)
                        <div style="border: 1px solid var(--border-color); padding: 1.5rem; border-radius: 1rem; background: var(--card-bg);">
                            <h3 style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 700; margin-top: 0; margin-bottom: 0.5rem; color: var(--text-color);">
                                {{ $project['title'] }}
                            </h3>
                            <p style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1rem; white-space: pre-line;">
                                {{ $project['description'] }}
                            </p>
                            @if(!empty($project['images']))
                                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                    @foreach($project['images'] as $img)
                                        <a href="{{ $author->getPortfolioImageUrl($img) }}" target="_blank" style="display: block; width: 150px; height: 100px; border-radius: 0.5rem; overflow: hidden; border: 1px solid var(--border-color);">
                                            <img src="{{ $author->getPortfolioImageUrl($img) }}" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
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
