<!DOCTYPE html>
<html @include('partials.html-attributes')>
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-base-200 font-sans antialiased">

        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-field focus:bg-base-100 focus:px-3 focus:py-2 focus:shadow-2">
            {{ __('Skip to content') }}
        </a>

        {{-- Sidebar (desktop) --}}
        <aside
            class="surface-chrome fixed inset-y-0 left-0 z-40 hidden w-[240px] flex-col border-r border-separator lg:flex"
            aria-label="{{ __('Sidebar') }}"
        >
            <div class="px-4 py-4">
                <x-ui.wordmark :href="route('dashboard')" />
            </div>

            <nav class="min-h-0 flex-1 overflow-y-auto px-2 pb-2" aria-label="{{ __('Main') }}">
                <button
                    type="button"
                    x-data
                    x-on:click="$dispatch('open-command-palette')"
                    class="mb-1 flex w-full items-center gap-2.5 rounded-field px-2.5 py-2 text-base-content/75 transition-colors duration-150 ease-out-ios hover:bg-fill hover:text-base-content"
                    data-test="sidebar-search"
                >
                    <x-mary-icon name="o-magnifying-glass" class="size-4 shrink-0" />
                    <span class="flex-1 truncate text-left text-subhead">{{ __('Search') }}</span>
                    <kbd class="rounded-selector bg-base-300 px-1.5 py-0.5 text-[0.625rem] text-base-content/45">⌘K</kbd>
                </button>

                <x-ui.nav-item :href="route('dashboard')" icon="home" :active="request()->routeIs('dashboard')">
                    {{ __('Home') }}
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('library.index')" icon="musical-note" :active="request()->routeIs('library.*')">
                    {{ __('Library') }}
                </x-ui.nav-item>
                <x-ui.nav-item :href="route('playlists.index')" icon="queue-list" :active="request()->routeIs('playlists.index')">
                    {{ __('Playlists') }}
                </x-ui.nav-item>

                <x-ui.nav-item
                    :href="route('playlists.create')"
                    icon="plus-circle"
                    :active="request()->routeIs('playlists.create')"
                    data-test="sidebar-new-playlist"
                >
                    {{ __('New Playlist') }}
                </x-ui.nav-item>

                {{-- Deep links into the library's own filters. Each maps to a
                     property on pages::library.index that is URL-bound, so the
                     back button walks the filter history. --}}
                @php
                    $libraryShortcuts = [
                        ['label' => __('Favourites'), 'icon' => 'heart', 'query' => ['sourceFilter' => 'favorites']],
                        ['label' => __('Recently Added'), 'icon' => 'plus-circle', 'query' => ['sort' => 'recent']],
                        ['label' => __('Recently Played'), 'icon' => 'clock', 'query' => ['sort' => 'recently_played']],
                    ];
                @endphp

                <div class="mt-6">
                    <h2 class="px-2.5 pb-1 text-caption text-base-content/45">{{ __('Library') }}</h2>

                    @foreach ($libraryShortcuts as $shortcut)
                        <x-ui.nav-item
                            :href="route('library.index', $shortcut['query'])"
                            :icon="$shortcut['icon']"
                            :active="request()->routeIs('library.*')
                                && collect($shortcut['query'])->every(fn ($v, $k) => request($k) === $v)"
                        >
                            {{ $shortcut['label'] }}
                        </x-ui.nav-item>
                    @endforeach
                </div>

                <x-ui.sidebar-playlists />
            </nav>

            <div class="py-4 px-4 !max-h-[83px]">
                <x-ui.user-menu class="w-full"/>
            </div>
        </aside>

        {{-- Content. The id is what the queue dock reserves its gutter against. --}}
        <div id="app-shell" class="lg:pl-[240px]">
            <main id="main" class="mb-44 lg:mb-32">
                {{ $slot }}
            </main>
        </div>

        {{-- Tab bar (mobile) --}}
        <nav
            class="surface-chrome fixed inset-x-0 bottom-0 z-50 flex h-[calc(3.5rem+env(safe-area-inset-bottom))] border-t border-separator pb-[env(safe-area-inset-bottom)] lg:hidden"
            data-test="bottom-nav"
            aria-label="{{ __('Main') }}"
        >
            @php
                $tabs = [
                    ['route' => 'dashboard', 'active' => request()->routeIs('dashboard'), 'icon' => 'home', 'label' => __('Home')],
                    ['route' => 'library.index', 'active' => request()->routeIs('library.*'), 'icon' => 'musical-note', 'label' => __('Library')],
                    ['route' => 'search', 'active' => request()->routeIs('search'), 'icon' => 'magnifying-glass', 'label' => __('Search')],
                    ['route' => 'playlists.index', 'active' => request()->routeIs('playlists.*'), 'icon' => 'queue-list', 'label' => __('Playlists')],
                    ['route' => 'profile.edit', 'active' => request()->is('settings*'), 'icon' => 'cog-6-tooth', 'label' => __('Settings')],
                ];
            @endphp

            @foreach ($tabs as $tab)
                @php($active = $tab['active'])
                <a
                    href="{{ route($tab['route']) }}"
                    wire:navigate
                    @if ($active) aria-current="page" @endif
                    @class([
                        'flex flex-1 flex-col items-center gap-0.5 py-2',
                        'text-primary' => $active,
                        'text-base-content/65' => ! $active,
                    ])
                >
                    <x-mary-icon :name="($active ? 's-' : 'o-') . $tab['icon']" class="size-6" />
                    <span class="text-[0.625rem] leading-none">{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </nav>

        @auth
            @persist('audio-player')
                <livewire:audio-player />
            @endpersist
            <livewire:add-to-playlist-modal />
            <livewire:relink-track-modal />
            <livewire:upload-modal />
            <livewire:command-palette />
        @endauth

        <x-mary-toast position="toast-top toast-center" />

    </body>
</html>
