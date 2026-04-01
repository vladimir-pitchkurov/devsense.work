<x-layout title="Что нового в PHP 8.0 - DevSense">
    <article class="article">
        <header class="article__header">
            <h1 class="article__title">PHP 8.0: Главные нововведения</h1>
            <p class="article__meta">Архитектурный разбор и живые примеры</p>
        </header>

        <div class="article__content content">
            <section class="content__section">
                <h2 class="content__heading">Named Arguments (Именованные аргументы)</h2>
                <p class="content__text">Передача аргументов в функцию по имени избавляет от необходимости помнить их порядок и позволяет пропускать необязательные параметры.</p>
                <div class="content__code-block code-block">
                    <pre class="code-block__pre"><code class="code-block__code">
// Позже мы добавим сюда реальное выполнение PHP-кода
setcookie(
    name: 'test',
    expires: time() + 60 * 60 * 2,
);
                    </code></pre>
                </div>
            </section>

            <section class="content__section">
                <h2 class="content__heading">Match Expression</h2>
                <p class="content__text">Более строгая и лаконичная альтернатива оператору switch. Возвращает значение и использует строгое сравнение (===).</p>

                <live-terminal></live-terminal>

                <div class="content__live-example">
                    <h3>Живой результат выполнения:</h3>
                    <p>Мы передали статус-код: <strong>{{ $examples['match_expression']['input'] }}</strong></p>
                    <p>Оператор match вернул: <strong>{{ $examples['match_expression']['result'] }}</strong></p>
                </div>

                <div class="content__code-block code-block">
                    <pre class="code-block__pre">
                        <code class="code-block__code">
$statusCode = 200;

$statusMessage = match ($statusCode) {
    200, 300 => 'Успех или Редирект',
    400, 404 => 'Ошибка клиента',
    500 => 'Ошибка сервера',
    default => 'Неизвестный статус',
};
                    </code></pre>
                </div>
            </section>
        </div>
    </article>
</x-layout>
