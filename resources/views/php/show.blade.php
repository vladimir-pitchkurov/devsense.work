<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    og-type="article"
    :og-image="$ogImage ?? null"
    :structured-data="$structuredData"
    :breadcrumb-current="$version === 'runtimes' ? __('ui.php_runtime.breadcrumb') : 'PHP '.$version"
>
    <div class="article__nav-wrapper no-print">
        <nav class="article__back" aria-label="{{ __('ui.php_show.nav_aria') }}">
            <a href="{{ route('php.index') }}" class="article__back-link">{{ __('ui.php_show.back_to_guides') }}</a>
        </nav>
        <button onclick="window.print()" class="btn-print" aria-label="{{ __('ui.search.print_pdf') }}">
            <svg class="icon-print-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            <span>{{ __('ui.search.print_pdf') }}</span>
        </button>
        @if (isset($article) && $article)
            <button onclick="openReportModal('article', {{ $article->id }})" class="btn-report" aria-label="Report content" style="background: transparent; border: 1px solid var(--border-color); color: var(--text-muted); border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; cursor: pointer; transition: border-color 0.2s, color 0.2s; font-family: inherit;">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"></path>
                    <line x1="4" y1="22" x2="4" y2="15"></line>
                </svg>
                <span>Report</span>
            </button>
        @endif
    </div>
    <div class="article-layout">
        <article class="article article-layout__main">
            @if (isset($tags) && $tags->isNotEmpty())
                <div class="article__tags">
                    @foreach ($tags as $t)
                        @php
                            $tagName = $t->translate()?->name ?? $t->slug;
                        @endphp
                        <a href="{{ route('tags.show', ['slug' => $t->slug]) }}" class="article__tag-badge">
                            #{{ $tagName }}
                        </a>
                    @endforeach
                </div>
            @endif

            <!-- Mobile Table of Contents Accordion -->
            <details class="mobile-toc">
                <summary class="mobile-toc__summary">
                    <span>{{ __('ui.search.on_this_page') }}</span>
                    <span class="mobile-toc__icon">▼</span>
                </summary>
                <div class="mobile-toc__content" id="article-toc-mobile"></div>
            </details>

            @include('partials.article_author_meta')

            <div class="article__content markdown-body">
                {!! $content !!}
            </div>

            <!-- Like & Dislike Section -->
            @if(isset($article) && $article)
                <div class="article-reactions" style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); display: flex; align-items: center; gap: 1rem; padding-bottom: 1.5rem;">
                    @php
                        $userReaction = auth()->check() ? auth()->user()->likes()->where('likeable_type', \App\Models\Article::class)->where('likeable_id', $article->id)->first() : null;
                        $hasLiked = $userReaction && !$userReaction->is_dislike;
                        $hasDisliked = $userReaction && $userReaction->is_dislike;
                    @endphp
                    <button onclick="toggleReaction({{ $article->id }}, false)" 
                            id="like-btn"
                            class="btn-reaction {{ $hasLiked ? 'active' : '' }}" 
                            style="display: inline-flex; align-items: center; gap: 0.5rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; padding: 0.5rem 1rem; cursor: pointer; transition: background-color 0.2s, border-color 0.2s; font-family: inherit;">
                        <svg viewBox="0 0 24 24" fill="{{ $hasLiked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path>
                        </svg>
                        <span id="likes-count">{{ $article->likesCount() }}</span>
                    </button>
                    
                    <button onclick="toggleReaction({{ $article->id }}, true)" 
                            id="dislike-btn"
                            class="btn-reaction {{ $hasDisliked ? 'active' : '' }}" 
                            style="display: inline-flex; align-items: center; gap: 0.5rem; background: transparent; border: 1px solid var(--border-color); color: var(--text-color); border-radius: 0.375rem; padding: 0.5rem 1rem; cursor: pointer; transition: background-color 0.2s, border-color 0.2s; font-family: inherit;">
                        <svg viewBox="0 0 24 24" fill="{{ $hasDisliked ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" width="16" height="16">
                            <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h3a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2h-3"></path>
                        </svg>
                        @if(auth()->check() && auth()->user()->isAdmin())
                            <span id="dislikes-count">{{ $article->dislikesCount() }}</span>
                        @endif
                    </button>
                </div>

                <script>
                function toggleReaction(articleId, isDislike) {
                    if (!{{ auth()->check() ? 'true' : 'false' }}) {
                        window.location.href = "{{ route('login', ['locale' => app()->getLocale()]) }}";
                        return;
                    }

                    fetch("{{ route('likes.toggle', ['locale' => app()->getLocale()]) }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            likeable_id: articleId,
                            likeable_type: 'article',
                            is_dislike: isDislike ? 1 : 0
                        })
                    })
                    .then(response => {
                        if (response.status === 401) {
                            window.location.href = "{{ route('login', ['locale' => app()->getLocale()]) }}";
                            return;
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data && data.success) {
                            document.getElementById('likes-count').innerText = data.likes_count;
                            
                            const dislikesEl = document.getElementById('dislikes-count');
                            if (dislikesEl && data.dislikes_count !== null) {
                                dislikesEl.innerText = data.dislikes_count;
                            }

                            const likeBtn = document.getElementById('like-btn');
                            const dislikeBtn = document.getElementById('dislike-btn');
                            
                            if (data.status === 'removed') {
                                likeBtn.classList.remove('active');
                                likeBtn.querySelector('svg').setAttribute('fill', 'none');
                                dislikeBtn.classList.remove('active');
                                dislikeBtn.querySelector('svg').setAttribute('fill', 'none');
                            } else if (data.status === 'added' || data.status === 'switched') {
                                if (isDislike) {
                                    dislikeBtn.classList.add('active');
                                    dislikeBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                                    likeBtn.classList.remove('active');
                                    likeBtn.querySelector('svg').setAttribute('fill', 'none');
                                } else {
                                    likeBtn.classList.add('active');
                                    likeBtn.querySelector('svg').setAttribute('fill', 'currentColor');
                                    dislikeBtn.classList.remove('active');
                                    dislikeBtn.querySelector('svg').setAttribute('fill', 'none');
                                }
                            }
                        }
                    })
                    .catch(err => console.error('Error toggling reaction:', err));
                }
                </script>

                <style>
                .btn-reaction.active {
                    background-color: var(--primary-color) !important;
                    border-color: var(--primary-color) !important;
                    color: #fff !important;
                }
                </style>

                @include('partials.article-suggestions', [
                    'suggestions' => $article->suggestions()
                        ->with(['user', 'votes', 'comments.user'])
                        ->get()
                        ->sortByDesc(fn($s) => $s->votes->count())
                ])
            @endif
        </article>

        <!-- Desktop Sticky Table of Contents Sidebar -->
        <aside class="article-layout__sidebar" aria-label="{{ __('ui.search.on_this_page') }}">
            <div class="sticky-toc" id="article-toc">
                <div class="sticky-toc__title">{{ __('ui.search.on_this_page') }}</div>
                <!-- ToC content populated by JS -->
            </div>
        </aside>
    </div>
</x-layout>
