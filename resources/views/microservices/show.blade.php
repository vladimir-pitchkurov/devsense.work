<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    og-type="article"
    :structured-data="$structuredData"
    :breadcrumb-current="$breadcrumbCurrent"
>
    <nav class="article__back" aria-label="{{ __('ui.microservices_show.nav_aria') }}">
        <a href="{{ route('microservices.index') }}" class="article__back-link">{{ __('ui.microservices_show.back') }}</a>
    </nav>
    <article class="article">
        <div class="article__content markdown-body">
            {!! $content !!}
        </div>
    </article>
</x-layout>
