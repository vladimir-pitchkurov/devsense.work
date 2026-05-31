<x-layout :title="__('ui.card_jobs_title') ?? 'Careers'" :description="__('ui.card_jobs_excerpt') ?? 'Browse open roles'">
    <section class="hero">
        <h1 class="hero__title">{{ __('ui.welcome.card_jobs_title') }}</h1>
        <p class="hero__description">{{ __('ui.welcome.card_jobs_excerpt') }}</p>
    </section>

    <section class="guides" aria-label="{{ __('ui.welcome.card_jobs_title') }}">
        @if (count($cards) === 0)
            <div class="empty-state">
                <p>No job postings available at this moment. Check back soon!</p>
            </div>
        @else
            <ul class="guides__list">
                @foreach ($cards as $card)
                    <li class="guides__item">
                        <article class="card">
                            <header class="card__header">
                                <h2 class="card__title">{{ $card['title'] }}</h2>
                                <div class="card__metadata" style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                                    <span>🏢 {{ $card['company'] }}</span>
                                    <span>📍 {{ $card['location'] }}</span>
                                    @if ($card['salary'])
                                        <span>💰 {{ $card['salary'] }}</span>
                                    @endif
                                    <span>⏰ {{ $card['employment_type'] }}</span>
                                </div>
                            </header>
                            <div class="card__body">
                                <p class="card__excerpt">{{ $card['excerpt'] }}</p>
                            </div>
                            <footer class="card__footer">
                                <a
                                    href="{{ route('jobs.show', ['slug' => $card['slug']]) }}"
                                    class="card__link"
                                    aria-label="View opening for {{ $card['title'] }}"
                                >
                                    {{ __('ui.php_index.cta') ?? 'View Details' }}
                                </a>
                            </footer>
                        </article>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</x-layout>
