@props(['title', 'description' => null])

<div class="flex w-full flex-col text-center">
    <h1 class="text-title-2">{{ $title }}</h1>
    @if ($description)
        <p class="mt-1 text-subhead text-base-content/65">{{ $description }}</p>
    @endif
</div>
