<x-layout
    :title="$meta['title'] ?? 'PHP ' . $version . ' - DevSense'"
    :description="$meta['description'] ?? ''"
>
    <article class="article">
        <div class="article__content markdown-body">
            {!! $content !!}
        </div>
    </article>
</x-layout>
