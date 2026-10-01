@props([
    'title',
    'subtitle' => null,
    'href' => null,
    'cover' => null,
    'trackIds' => [],
    'size' => 'grid',
])

@php
    $playable = ! empty($trackIds);
@endphp

{{-- Bare artwork on the canvas, Apple Music style: no card box. Hover reveals
     the play affordance rather than lifting the tile. --}}
<div {{ $attributes->class(['group', 'w-40 shrink-0 snap-start sm:w-44' => $size === 'shelf']) }}>
    <div class="artwork relative aspect-square overflow-hidden rounded-art-lg bg-base-300 shadow-1">
        <a href="{{ $href }}" wire:navigate class="block size-full" tabindex="-1" aria-hidden="true">
            {{-- The glyph is always in the DOM and the image is laid over it, so a
                 404 or undecodable file reveals the placeholder instead of the
                 browser's broken-image icon. --}}
            <span class="flex size-full items-center justify-center">
                <x-mary-icon name="o-musical-note" class="size-10 text-base-content/25" />
            </span>

            @if ($cover)
                <img src="{{ $cover }}" alt="" loading="lazy" onerror="this.remove()" class="absolute inset-0 size-full object-cover">
            @endif
        </a>

        @if ($playable)
            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-overlay to-transparent opacity-0 transition-opacity duration-200 ease-out-ios group-hover:opacity-100"></div>

            {{-- The tile itself is a link, so this button is the only way to start an
                 album from a card. It stays visible on touch, where there is no hover
                 to reveal it. The scrim above stays hover-only: the chip carries its own
                 surface and reads fine on any artwork without it. --}}
            <button
                type="button"
                x-on:click.stop="$dispatch('play-track', { trackIds: {{ \Illuminate\Support\Js::from($trackIds) }}, index: 0 })"
                class="surface-chrome absolute bottom-2 left-2 flex size-9 items-center justify-center rounded-full text-primary opacity-0 shadow-2 transition-opacity duration-200 ease-out-ios group-hover:opacity-100 focus-visible:opacity-100 max-sm:opacity-100"
                aria-label="Play {{ $title }}"
                data-test="album-card-play"
            >
                <x-mary-icon name="s-play" class="size-4 translate-x-px" />
            </button>
        @endif
    </div>

    <a href="{{ $href }}" wire:navigate class="mt-2 block">
        <span class="block truncate text-body-emphasized">{{ $title }}</span>
        @if ($subtitle)
            <span class="block truncate text-subhead text-base-content/65">{{ $subtitle }}</span>
        @endif
    </a>
</div>
