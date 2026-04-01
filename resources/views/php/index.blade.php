<x-layout title="Эволюция PHP - DevSense">
    <section class="hero">
        <h1 class="hero__title">Гайды по версиям PHP</h1>
        <p class="hero__description">Изучаем эволюцию синтаксиса и возможностей языка от версии 8.0 до актуальных релизов на живых примерах.</p>
    </section>

    <section class="guides">
        <ul class="guides__list">
            <li class="guides__item">
                <article class="card">
                    <header class="card__header">
                        <h2 class="card__title">PHP 8.0: Фундаментальный сдвиг</h2>
                    </header>
                    <div class="card__body">
                        <p class="card__excerpt">Именованные аргументы, Match Expression, Nullsafe Operator и другие критические изменения.</p>
                    </div>
                    <footer class="card__footer">
                        <a href="{{ route('php.show', '8.0') }}" class="card__link">Изучить фичи</a>
                    </footer>
                </article>
            </li>
        </ul>
    </section>
</x-layout>
