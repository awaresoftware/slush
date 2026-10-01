<div
    x-data="{
        open: false,
        sel: 0,
        show() { this.open = true; this.sel = 0; this.$nextTick(() => this.$refs.input.focus()); },
        hide() { this.open = false; this.$wire.set('query', '', false); },
        items() { return this.$root.querySelectorAll('[data-cmd-index]'); },
        move(delta) {
            const n = this.items().length;
            if (n === 0) return;
            this.sel = (this.sel + delta + n) % n;
            this.items()[this.sel]?.scrollIntoView({ block: 'nearest' });
        },
        choose() { this.items()[this.sel]?.click(); },
    }"
    x-on:keydown.window="if (($event.metaKey || $event.ctrlKey) && $event.key === 'k') { $event.preventDefault(); open ? hide() : show(); }"
    x-on:open-command-palette.window="show()"
    x-on:keydown.escape.window="if (open) hide()"
>
    <div x-show="open" x-cloak class="fixed inset-0 z-[85]" role="dialog" aria-modal="true" aria-label="{{ __('Search') }}">
        <div class="absolute inset-0 bg-overlay" x-show="open" x-transition.opacity x-on:click="hide()"></div>

        <div
            class="surface-chrome absolute inset-x-4 top-[12vh] mx-auto max-w-xl overflow-hidden rounded-box border border-separator shadow-3"
            x-show="open"
            x-transition:enter="transition ease-out-ios duration-200"
            x-transition:enter-start="opacity-0 scale-[0.97]"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-out-ios duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-[0.97]"
            data-test="command-palette"
        >
            {{-- Input --}}
            <div class="flex items-center gap-3 border-b border-separator px-4 py-3">
                <x-mary-icon name="o-magnifying-glass" class="size-5 shrink-0 text-base-content/45" />
                <input
                    x-ref="input"
                    wire:model.live.debounce.200ms="query"
                    x-on:input="sel = 0"
                    x-on:keydown.arrow-down.prevent="move(1)"
                    x-on:keydown.arrow-up.prevent="move(-1)"
                    x-on:keydown.enter.prevent="choose()"
                    type="search"
                    placeholder="{{ __('Search tracks, albums, artists, playlists…') }}"
                    class="grow bg-transparent text-body outline-none placeholder:text-base-content/35"
                    aria-label="{{ __('Search your library') }}"
                    data-test="palette-input"
                >
                <kbd class="hidden rounded-selector bg-base-300 px-1.5 py-0.5 text-footnote text-base-content/45 sm:block">esc</kbd>
            </div>

            {{-- Results --}}
            <div class="max-h-[55vh] overflow-y-auto p-2" data-test="palette-results">
                @php($flat = 0)

                @if (trim($query) === '')
                    <p class="px-2 pt-1 pb-1 text-caption text-base-content/45">{{ __('Go to') }}</p>

                    @foreach ([
                        ['label' => __('Home'), 'icon' => 'o-home', 'url' => route('dashboard')],
                        ['label' => __('Library'), 'icon' => 'o-musical-note', 'url' => route('library.index')],
                        ['label' => __('Playlists'), 'icon' => 'o-queue-list', 'url' => route('playlists.index')],
                        ['label' => __('Appearance'), 'icon' => 'o-swatch', 'url' => route('appearance.edit')],
                        ['label' => __('Settings'), 'icon' => 'o-cog-6-tooth', 'url' => route('profile.edit')],
                    ] as $nav)
                        <a
                            href="{{ $nav['url'] }}"
                            wire:navigate
                            data-cmd-index="{{ $flat }}"
                            x-on:mouseenter="sel = {{ $flat }}"
                            :class="sel === {{ $flat }} && 'bg-fill'"
                            class="flex items-center gap-3 rounded-field px-2 py-2 text-body"
                        >
                            <x-mary-icon :name="$nav['icon']" class="size-4 shrink-0 text-base-content/45" />
                            {{ $nav['label'] }}
                        </a>
                        @php($flat++)
                    @endforeach
                @elseif (empty($this->results))
                    <p class="px-2 py-8 text-center text-subhead text-base-content/45">
                        {{ __('Nothing in your library matches ":q".', ['q' => trim($query)]) }}
                    </p>
                @else
                    @foreach ($this->results as $group => $items)
                        <p class="px-2 pt-2 pb-1 text-caption text-base-content/45">{{ $group }}</p>

                        @foreach ($items as $item)
                            @php($clickable = $item['url'] !== null || $item['trackId'] !== null)

                            @if ($item['url'] !== null)
                                <a
                                    href="{{ $item['url'] }}"
                                    wire:navigate
                                    data-cmd-index="{{ $flat }}"
                                    x-on:mouseenter="sel = {{ $flat }}"
                                    :class="sel === {{ $flat }} && 'bg-fill'"
                                    class="flex items-center gap-3 rounded-field px-2 py-2"
                                >
                                    <x-ui.palette-item-body :item="$item" />
                                </a>
                                @php($flat++)
                            @elseif ($item['trackId'] !== null)
                                <button
                                    type="button"
                                    x-on:click="$dispatch('play-track', { trackIds: [{{ $item['trackId'] }}], index: 0 }); hide()"
                                    data-cmd-index="{{ $flat }}"
                                    x-on:mouseenter="sel = {{ $flat }}"
                                    :class="sel === {{ $flat }} && 'bg-fill'"
                                    class="flex w-full items-center gap-3 rounded-field px-2 py-2 text-left"
                                >
                                    <x-ui.palette-item-body :item="$item" play />
                                </button>
                                @php($flat++)
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
    </div>
</div>
