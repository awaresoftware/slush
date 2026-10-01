@props(['covers' => [], 'title' => null])

@php
    $covers = collect($covers)->filter()->values();
@endphp

{{-- One cover fills the tile; several form a 2x2 mosaic; none falls back to a
     glyph. Playlists have no artwork of their own. --}}
<div {{ $attributes->class(['artwork overflow-hidden rounded-art-lg bg-base-300 shadow-1']) }}>
    @if ($covers->count() >= 4)
        <div class="grid size-full grid-cols-2 grid-rows-2">
            @foreach ($covers->take(4) as $cover)
                <img src="{{ $cover }}" alt="" loading="lazy" class="size-full object-cover">
            @endforeach
        </div>
    @elseif ($covers->count() >= 1)
        <img src="{{ $covers->first() }}" alt="" loading="lazy" class="size-full object-cover">
    @else
        <span class="flex size-full items-center justify-center">
            <x-mary-icon name="o-queue-list" class="size-1/3 text-base-content/25" />
        </span>
    @endif
</div>
