@props([
    'title',
    'subtitle' => null,
    'back' => null,
    'backLabel' => null,
    // Album and playlist pages carry their own title beside the artwork, so the
    // hero title would only repeat it. The condensed toolbar still shows it.
    'heroTitle' => true,
])

{{-- A large title that condenses into a sticky chrome toolbar once the hero
     scrolls away. On small screens the toolbar always carries the title. --}}
<div x-data="{ condensed: false }" x-on:scroll.window="condensed = window.scrollY > 40">
    <div
        class="sticky top-0 z-30 flex h-14 items-center gap-2 border-b px-4 transition-colors duration-200 ease-out-ios sm:px-6"
        :class="condensed ? 'surface-chrome border-separator' : 'border-transparent'"
    >
        @if ($back)
            <x-ui.icon-button
                tag="a"
                href="{{ $back }}"
                wire:navigate
                icon="chevron-left"
                size="sm"
                :label="$backLabel ?? __('Back')"
                class="-ms-2"
            />
        @endif

        <span
            class="min-w-0 flex-1 truncate text-title-3 transition-opacity duration-200 ease-out-ios max-lg:opacity-100"
            :class="condensed ? 'opacity-100' : 'lg:opacity-0'"
        >{{ $title }}</span>

        @isset($actions)
            <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>

    @if ($heroTitle || $subtitle || isset($hero))
        <div class="px-4 pt-3 pb-6 sm:px-6">
            @if ($heroTitle)
                <h1 class="text-large-title max-lg:sr-only">{{ $title }}</h1>
            @endif

            @if ($subtitle)
                <p class="mt-1 text-subhead text-base-content/65">{{ $subtitle }}</p>
            @endif

            @isset($hero)
                {{ $hero }}
            @endisset
        </div>
    @endif
</div>
