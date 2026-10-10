<?php

use App\Livewire\CommandPalette;
use Livewire\Attributes\Layout;
use Illuminate\View\View;
use Livewire\Attributes\Url;

/**
 * Full-page search for small screens. A fixed palette over a scrolling page
 * cannot be scrolled reliably on iOS while the keyboard is up, so mobile gets
 * a real page whose results scroll with the document.
 */
new #[Layout('layouts.app')] class extends CommandPalette {
    #[Url(as: 'q', except: '')]
    public string $query = '';

    public function render(): View
    {
        return $this->view();
    }
};
?>

<div class="min-h-screen pb-20">
    <div class="surface-chrome sticky top-0 z-30 flex items-center gap-3 border-b border-separator px-4 py-3">
        <x-mary-icon name="o-magnifying-glass" class="size-5 shrink-0 text-base-content/45" />
        <input
            wire:model.live.debounce.200ms="query"
            x-init="$el.focus()"
            type="search"
            enterkeyhint="search"
            placeholder="{{ __('Search tracks, albums, artists, playlists…') }}"
            class="grow bg-transparent text-body outline-none placeholder:text-base-content/35"
            aria-label="{{ __('Search your library') }}"
            data-test="search-input"
        >
    </div>

    <div class="p-2" data-test="search-results">
        @if (trim($query) === '')
            <p class="px-2 py-8 text-center text-subhead text-base-content/45">{{ __('Search your library') }}</p>
        @elseif (empty($this->results))
            <p class="px-2 py-8 text-center text-subhead text-base-content/45">
                {{ __('Nothing in your library matches ":q".', ['q' => trim($query)]) }}
            </p>
        @else
            @foreach ($this->results as $group => $items)
                <p class="px-2 pt-2 pb-1 text-caption text-base-content/45">{{ $group }}</p>

                @foreach ($items as $item)
                    @if ($item['url'] !== null)
                        <a href="{{ $item['url'] }}" wire:navigate class="flex items-center gap-3 rounded-field px-2 py-2 active:bg-fill">
                            <x-ui.palette-item-body :item="$item" />
                        </a>
                    @elseif ($item['trackId'] !== null)
                        <button
                            type="button"
                            x-on:click="$el.blur(); $dispatch('play-track', { trackIds: [{{ $item['trackId'] }}], index: 0 })"
                            class="flex w-full items-center gap-3 rounded-field px-2 py-2 text-left active:bg-fill"
                        >
                            <x-ui.palette-item-body :item="$item" play />
                        </button>
                    @else
                        <div class="flex items-center gap-3 rounded-field px-2 py-2 opacity-50">
                            <x-ui.palette-item-body :item="$item" />
                        </div>
                    @endif
                @endforeach
            @endforeach
        @endif
    </div>
</div>
