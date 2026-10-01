@props(['title', 'subtitle' => null])

<div class="mb-6 text-center">
    <h1 class="text-title-2">{{ $title }}</h1>
    @if ($subtitle)
        <p class="mt-1 text-subhead text-base-content/65">{{ $subtitle }}</p>
    @endif
</div>
