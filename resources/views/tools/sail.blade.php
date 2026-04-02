<x-layout
    :title="$meta['title'] ?? __('ui.tools.sail.title')"
    :description="$meta['description'] ?? ''"
>
    <nav class="article__back" aria-label="{{ __('ui.tools.sail.nav_aria') }}">
        <a href="{{ route('home') }}" class="article__back-link">{{ __('ui.tools.sail.back') }}</a>
    </nav>
    <article class="article">
        <div class="article__content markdown-body">
            {!! $content !!}
        </div>
    </article>
</x-layout>
