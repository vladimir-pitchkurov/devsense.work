<x-layout :title="__('ui.welcome.title')" :description="__('ui.welcome.description')">
    <section class="hero">
        <h1 class="hero__title">{{ __('ui.welcome.hero_title') }}</h1>
        <p class="hero__description">{{ __('ui.welcome.hero_lead') }}</p>
    </section>

    <section class="guides" aria-label="{{ __('ui.welcome.section_aria') }}">
        <ul class="guides__list">
            <li class="guides__item">
                <article class="card">
                    <header class="card__header">
                        <h2 class="card__title">{{ __('ui.welcome.card_php_title') }}</h2>
                    </header>
                    <div class="card__body">
                        <p class="card__excerpt">{{ __('ui.welcome.card_php_excerpt') }}</p>
                    </div>
                    <footer class="card__footer">
                        <a
                            href="{{ route('php.index') }}"
                            class="card__link"
                            aria-label="{{ __('ui.welcome.card_php_cta') }}"
                        >
                            {{ __('ui.welcome.card_php_cta') }}
                        </a>
                    </footer>
                </article>
            </li>
            <li class="guides__item">
                <article class="card">
                    <header class="card__header">
                        <h2 class="card__title">{{ __('ui.welcome.card_tools_title') }}</h2>
                    </header>
                    <div class="card__body">
                        <p class="card__excerpt">{{ __('ui.welcome.card_tools_excerpt') }}</p>
                    </div>
                    <footer class="card__footer">
                        <a
                            href="{{ route('tools.index') }}"
                            class="card__link"
                            aria-label="{{ __('ui.welcome.card_tools_cta') }}"
                        >
                            {{ __('ui.welcome.card_tools_cta') }}
                        </a>
                    </footer>
                </article>
            </li>
            <li class="guides__item">
                <article class="card">
                    <header class="card__header">
                        <h2 class="card__title">{{ __('ui.welcome.card_microservices_title') }}</h2>
                    </header>
                    <div class="card__body">
                        <p class="card__excerpt">{{ __('ui.welcome.card_microservices_excerpt') }}</p>
                    </div>
                    <footer class="card__footer">
                        <a
                            href="{{ route('microservices.index') }}"
                            class="card__link"
                            aria-label="{{ __('ui.welcome.card_microservices_cta') }}"
                        >
                            {{ __('ui.welcome.card_microservices_cta') }}
                        </a>
                    </footer>
                </article>
            </li>
        </ul>
    </section>
</x-layout>
