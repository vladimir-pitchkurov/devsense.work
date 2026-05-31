<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    og-type="article"
    :og-image="$ogImage ?? null"
    :structured-data="$structuredData"
    :breadcrumb-current="$breadcrumbCurrent"
>
    <div class="article__nav-wrapper no-print">
        <nav class="article__back" aria-label="{{ __('ui.architecture_show.nav_aria') }}">
            <a href="{{ route('architecture.index') }}" class="article__back-link">{{ __('ui.architecture_show.back') }}</a>
        </nav>
        <button onclick="window.print()" class="btn-print" aria-label="{{ __('ui.search.print_pdf') }}">
            <svg class="icon-print-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            <span>{{ __('ui.search.print_pdf') }}</span>
        </button>
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

            <div class="article__content markdown-body">
                {!! $content !!}
            </div>
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
