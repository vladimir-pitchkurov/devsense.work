<x-layout
    :title="$pageTitle"
    :description="$pageDescription"
    :canonical="$canonicalUrl"
    breadcrumb-current="{{ __('ui.tags_index.breadcrumb') }}"
>
    <section class="hero">
        <h1 class="hero__title">{{ __('ui.tags_index.hero_title') }}</h1>
        <p class="hero__description">{{ __('ui.tags_index.hero_lead') }}</p>
    </section>

    <section class="tags-cloud-container" aria-label="{{ __('ui.tags_index.hero_title') }}">
        @if ($tags->isEmpty())
            <div class="empty-state">
                <p>{{ __('ui.tags_index.empty') }}</p>
            </div>
        @else
            <div class="tags-grid">
                @foreach ($tags as $tag)
                    @php
                        $translation = $tag->translate();
                        $name = $translation?->name ?? $tag->slug;
                    @endphp
                    <a
                        href="{{ route('tags.show', ['slug' => $tag->slug]) }}"
                        class="tag-item-card"
                        title="Guides marked with {{ $name }}"
                    >
                        <span class="tag-item-card__hash">#</span>
                        <span class="tag-item-card__name">{{ $name }}</span>
                        <span class="tag-item-card__count">{{ $tag->articles_count }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <style>
    .tags-cloud-container {
        margin: 3rem 0;
    }

    .tags-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
        justify-content: center;
    }

    .tag-item-card {
        display: inline-flex;
        align-items: center;
        padding: 0.75rem 1.25rem;
        background: var(--card-bg, rgba(255, 255, 255, 0.03));
        border: 1px solid var(--border-color);
        border-radius: 50px;
        text-decoration: none;
        color: var(--text-color);
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .tag-item-card:hover {
        transform: translateY(-2px);
        background: var(--primary-color);
        color: #fff;
        border-color: var(--primary-color);
        box-shadow: 0 8px 20px rgba(var(--primary-color-rgb), 0.2);
    }

    .tag-item-card__hash {
        opacity: 0.5;
        margin-right: 0.15rem;
        transition: opacity 0.3s;
    }

    .tag-item-card:hover .tag-item-card__hash {
        opacity: 0.9;
    }

    .tag-item-card__name {
        margin-right: 0.75rem;
    }

    .tag-item-card__count {
        font-size: 0.75rem;
        background: rgba(var(--text-color-rgb), 0.1);
        padding: 0.15rem 0.5rem;
        border-radius: 20px;
        color: var(--text-muted);
        transition: background 0.3s, color 0.3s;
    }

    .tag-item-card:hover .tag-item-card__count {
        background: rgba(255, 255, 255, 0.2);
        color: #fff;
    }

    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
        background: rgba(255, 255, 255, 0.01);
        border: 1px solid var(--border-color);
        border-radius: 1rem;
        color: var(--text-muted);
    }
    </style>
</x-layout>
