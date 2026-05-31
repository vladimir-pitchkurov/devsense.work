<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    og-type="article"
    :structured-data="$structuredData"
    :breadcrumb-current="$breadcrumbCurrent"
>
    <nav class="article__back" aria-label="Jobs Navigation">
        <a href="{{ route('jobs.index') }}" class="article__back-link">
            &larr; {{ __('ui.tools_show.back') ?? 'All Positions' }}
        </a>
    </nav>
    <article class="article">
        <div class="article__content markdown-body">
            {!! $content !!}
        </div>
    </article>
</x-layout>
