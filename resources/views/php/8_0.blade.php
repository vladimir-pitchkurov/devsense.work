<x-layout :title="__('php80.page_title') . ' - DevSense'">
    <article class="article">
        <header class="article__header">
            <h1 class="article__title">{{ __('php80.hero_title') }}</h1>
            <p class="article__meta">{{ __('php80.hero_subtitle') }}</p>
        </header>

        <div class="article__content content">

            <x-feature-block
                :title="__('php80.named_arguments.title')"
                :description="__('php80.named_arguments.desc')"
            >
                <div class="content__code-block code-block">
                    <pre class="code-block__pre"><code class="code-block__code">
setcookie(
    name: 'test',
    expires: time() + 60 * 60 * 2,
);
                    </code></pre>
                </div>
            </x-feature-block>

            <x-feature-block
                :title="__('php80.match_expression.title')"
                :description="__('php80.match_expression.desc')"
            >
                <live-terminal></live-terminal>

                <div class="content__live-example">
                    <h3>{{ __('php80.match_expression.live_result') }}</h3>
                    <p>{{ __('php80.match_expression.status_input') }} <strong>{{ $examples['match_expression']['input'] }}</strong></p>
                    <p>{{ __('php80.match_expression.match_returned') }} <strong>{{ $examples['match_expression']['result'] }}</strong></p>
                </div>

                <div class="content__code-block code-block">
                    <pre class="code-block__pre"><code class="code-block__code">
$statusCode = 200;

$statusMessage = match ($statusCode) {
    200, 300 => 'Success or redirect',
    400, 404 => 'Client error',
    500 => 'Server error',
    default => 'unknown status',
};
                    </code></pre>
                </div>
            </x-feature-block>

        </div>
    </article>
</x-layout>
