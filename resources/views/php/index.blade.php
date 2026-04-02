<x-layout :title="__('ui.php_index.title')" :description="__('ui.php_index.description')">
    <section class="hero">
        <h1 class="hero__title">{{ __('ui.php_index.hero_title') }}</h1>
        <p class="hero__description">{{ __('ui.php_index.hero_lead') }}</p>
    </section>

    <section class="guides" aria-label="{{ __('ui.php_index.hero_title') }}">
        <ul class="guides__list">
            @foreach ($cards as $card)
                <li class="guides__item">
                    <article class="card">
                        <header class="card__header">
                            <h2 class="card__title">{{ $card['title'] }}</h2>
                        </header>
                        <div class="card__body">
                            <p class="card__excerpt">{{ $card['excerpt'] }}</p>
                        </div>
                        <footer class="card__footer">
                            <a
                                href="{{ route('php.show', ['version' => $card['version']]) }}"
                                class="card__link"
                                aria-label="{{ __('ui.php_index.cta') }} — PHP {{ $card['version'] }}"
                            >
                                {{ __('ui.php_index.cta') }}
                            </a>
                        </footer>
                    </article>
                </li>
            @endforeach
        </ul>
    </section>
</x-layout>
