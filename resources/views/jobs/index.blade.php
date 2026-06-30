<x-layout :title="__('ui.jobs.index_title')" :description="__('ui.jobs.index_desc')">
    <div class="quizzes-container" style="max-width: 1000px; margin: 2rem auto; padding: 0 1.5rem;">
        <!-- Hero Header -->
        <section class="quizzes-hero" style="margin-bottom: 2rem; position: relative; text-align: center; padding: 4rem 2rem; background: var(--hero-gradient); border: 1px solid var(--border-color); border-radius: 1.5rem; overflow: hidden; box-shadow: 0 4px 20px var(--shadow-color);">
            <div class="hero-glow" style="position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(circle, var(--primary-glow) 0%, transparent 50%); pointer-events: none; z-index: 0; animation: spin-glow 30s linear infinite;"></div>
            <h1 class="hero-title" style="font-family: 'Outfit', sans-serif; font-size: 2.75rem; font-weight: 800; margin: 0 0 1rem; color: var(--text-color); position: relative; z-index: 1;">
                {{ __('ui.welcome.card_jobs_title') }}
            </h1>
            <p class="hero-lead" style="font-size: 1.15rem; color: var(--text-muted); max-width: 600px; margin: 0 auto; line-height: 1.6; position: relative; z-index: 1;">
                {{ __('ui.welcome.card_jobs_excerpt') }}
            </p>
        </section>

        <!-- Jobs List -->
        <section class="quizzes-grid-section">
            @if (count($cards) === 0)
                <div class="empty-state" style="text-align: center; padding: 3rem; background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 1rem; color: var(--text-muted);">
                    <p>No job postings available at this moment. Check back soon!</p>
                </div>
            @else
                <div class="quizzes-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 2rem;">
                    @foreach ($cards as $card)
                        <article class="card glass-card" style="background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 1rem; padding: 1.5rem; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, border-color 0.2s; box-shadow: 0 4px 15px var(--shadow-color);">
                            <div>
                                <header class="card__header" style="display: flex; gap: 1rem; align-items: flex-start; margin-bottom: 1rem;">
                                    <div class="company-logo" style="flex-shrink: 0; width: 44px; height: 44px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.3rem; color: #ffffff; font-family: 'Outfit', sans-serif; box-shadow: 0 4px 10px var(--primary-glow);">
                                        {{ substr($card['company'], 0, 1) }}
                                    </div>
                                    <div>
                                        <h2 class="card__title" style="font-size: 1.25rem; font-weight: 700; margin: 0; font-family: 'Outfit', sans-serif; line-height: 1.3;">
                                            <a href="{{ route('jobs.show', ['slug' => $card['slug']]) }}" style="color: inherit; text-decoration: none;">
                                                {{ $card['title'] }}
                                            </a>
                                        </h2>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                            <span>🏢 {{ $card['company'] }}</span>
                                            <span>&bull;</span>
                                            <span>📍 {{ $card['location'] }}</span>
                                        </div>
                                    </div>
                                </header>
                                <div class="card__body" style="margin-bottom: 1.5rem;">
                                    <p class="card__excerpt" style="font-size: 0.95rem; color: var(--text-muted); line-height: 1.5; margin: 0;">
                                        {{ $card['excerpt'] }}
                                    </p>
                                    <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem; font-size: 0.8rem;">
                                        @if ($card['salary'])
                                            <span style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 600;">
                                                💰 {{ $card['salary'] }}
                                            </span>
                                        @endif
                                        <span style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 0.25rem 0.6rem; border-radius: 6px; font-weight: 600;">
                                            ⏰ {{ $card['employment_type'] }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <footer class="card__footer" style="padding: 0; margin-top: auto;">
                                <a
                                    href="{{ route('jobs.show', ['slug' => $card['slug']]) }}"
                                    class="card__link"
                                    aria-label="View opening for {{ $card['title'] }}"
                                    style="font-weight: 600; font-size: 0.9rem;"
                                >
                                    {{ __('ui.php_index.cta') ?? 'View Details' }}
                                </a>
                            </footer>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</x-layout>
