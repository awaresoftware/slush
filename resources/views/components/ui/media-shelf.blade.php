@props(['title', 'href' => null])

{{-- Padding lives on the header and the scroller, never on the section, so the
     edge fades and chevrons can be positioned against the scroller's own box.
     data-shelf lets a test target one shelf by title, which the sidebar links
     make impossible from the rendered string alone. --}}
<section {{ $attributes->class('min-w-0') }} data-test="media-shelf" data-shelf="{{ $title }}">
    <div class="flex items-baseline justify-between gap-4 px-4 sm:px-6">
        <h2 class="text-title-2">{{ $title }}</h2>

        @if ($href)
            <a href="{{ $href }}" wire:navigate class="shrink-0 text-subhead text-primary hover:underline">
                {{ __('See all') }}
            </a>
        @endif
    </div>

    {{-- Scroll-snap shelf. The scrollbar is hidden, so scroll position has to be
         signalled another way: a fade at whichever edge still has content. The
         shelf is swipe- and trackpad-scrollable, and a shelf is never long
         enough to need step buttons alongside that.

         scroll-pl must match pl. A scroll container's own padding-left does not
         move its snap points, so with mandatory snapping the browser forces a
         snap to the first tile — 24px in — leaving the shelf permanently nudged
         left with the first tile clipped and the "at start" fade showing on load.
         scroll-padding insets the snapport instead, which puts scrollLeft = 0
         back on a snap point and aligns the first tile with the title. --}}
    <div class="relative mt-3" x-data="mediaShelf">
        <div
            x-ref="scroller"
            class="flex snap-x snap-mandatory gap-4 overflow-x-auto pl-4 pr-16 pb-2 scroll-pl-4 sm:pl-6 sm:pr-20 sm:scroll-pl-6 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            x-on:scroll="sync()"
        >
            {{ $slot }}
        </div>

        <div
            class="pointer-events-none absolute inset-y-0 left-0 w-14 bg-gradient-to-r from-base-200 to-transparent transition-opacity duration-200 ease-out-ios"
            :class="atStart ? 'opacity-0' : 'opacity-100'"
            aria-hidden="true"
        ></div>

        <div
            class="pointer-events-none absolute inset-y-0 right-0 w-14 bg-gradient-to-l from-base-200 to-transparent transition-opacity duration-200 ease-out-ios"
            :class="atEnd ? 'opacity-0' : 'opacity-100'"
            aria-hidden="true"
        ></div>
    </div>
</section>
