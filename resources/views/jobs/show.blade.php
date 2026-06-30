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

        @if(isset($meta['faq']) && is_array($meta['faq']))
            <section class="job-faq" style="margin-top: 3rem; padding-top: 2rem; border-top: 1px solid var(--border-color);">
                <h2 style="font-family: 'Outfit', sans-serif; font-size: 1.75rem; margin-bottom: 1.5rem; color: var(--text-color);">
                    {{ __('ui.search.faq') }}
                </h2>
                <div class="faq-list" style="display: flex; flex-direction: column; gap: 1.25rem;">
                    @foreach($meta['faq'] as $item)
                        <div class="faq-item" style="background: rgba(255, 255, 255, 0.02); border: 1px solid var(--border-color); border-radius: 0.75rem; padding: 1.25rem; transition: border-color 0.2s;">
                            <h3 class="faq-question" style="font-size: 1.1rem; font-weight: 600; margin: 0 0 0.5rem; color: var(--text-color); font-family: 'Inter', sans-serif; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--primary-color);">Q:</span> {{ $item['question'] }}
                            </h3>
                            <p class="faq-answer" style="font-size: 0.95rem; margin: 0; color: var(--text-muted); line-height: 1.6;">
                                {{ $item['answer'] }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </article>
</x-layout>
