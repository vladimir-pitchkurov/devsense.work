<x-layout
    :title="$meta['title'] ?? 'PHP ' . $version . ' - DevSense'"
    :description="$meta['description'] ?? ''"
>
    <nav class="article__back" aria-label="{{ __('ui.php_show.nav_aria') }}">
        <a href="{{ route('php.index') }}" class="article__back-link">{{ __('ui.php_show.back_to_guides') }}</a>
    </nav>
    <article class="article">
        <div class="article__content markdown-body">
            {!! $content !!}
        </div>
    </article>
</x-layout>
