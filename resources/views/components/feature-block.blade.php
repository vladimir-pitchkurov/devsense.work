@props(['title', 'description' => null])

<section class="content__section">
    <h2 class="content__heading">{{ $title }}</h2>

    @if($description)
        <p class="content__text">{{ $description }}</p>
    @endif

    {{ $slot }}
</section>
