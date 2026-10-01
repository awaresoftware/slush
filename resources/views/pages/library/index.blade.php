<?php

use App\Models\Album;
use App\Models\Track;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] #[Title('My Library')] class extends Component
{
    use WithPagination;

    public string $search = '';

    /**
     * Both are URL-bound so the sidebar can deep-link into a filtered library
     * (?sourceFilter=favorites, ?sort=recent) and the back button walks the
     * filter history rather than leaving the page.
     */
    #[Url(history: true)]
    public string $sourceFilter = 'all';

    #[Url(history: true)]
    public string $sort = 'title';

    #[Url(history: true)]
    public string $activeTab = 'tracks';

    /**
     * Each list is paginated on its own page name so the tracks, artists and
     * albums tabs keep independent positions across filter changes.
     */
    public int $perPage = 15;

    public int $albumsPerPage = 12;

    public int $artistsPerPage = 12;

    /**
     * The user's tracks for the current search, source filter and sort.
     */
    #[Computed]
    public function tracks(): LengthAwarePaginator
    {
        return Track::query()
            ->where('user_id', Auth::id())
            ->with('album')
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('artist', 'like', "%{$this->search}%")
                    ->orWhereHas('album', fn ($q) => $q->where('title', 'like', "%{$this->search}%"));
            }))
            ->when($this->sourceFilter === 'uploaded', fn ($q) => $q->uploaded())
            ->when($this->sourceFilter === 'local', fn ($q) => $q->local())
            ->when($this->sourceFilter === 'unavailable', fn ($q) => $q->unavailable())
            ->when($this->sourceFilter === 'favorites', fn ($q) => $q->favorited())
            ->tap(fn ($q) => $this->applySort($q))
            ->paginate($this->perPage, ['*'], 'page');
    }

    /**
     * The user's albums for the current search, ordered by title.
     */
    #[Computed]
    public function albums(): LengthAwarePaginator
    {
        return Album::query()
            ->where('user_id', Auth::id())
            ->when($this->search, fn ($q) => $q->where(function ($q) {
                $q->where('title', 'like', "%{$this->search}%")
                    ->orWhere('artist', 'like', "%{$this->search}%");
            }))
            ->orderBy('title')
            ->paginate($this->albumsPerPage, ['*'], 'albums');
    }

    /**
     * Distinct artists for the current search, each carrying its track count and
     * the albums it appears on.
     */
    #[Computed]
    public function artists(): LengthAwarePaginator
    {
        $artists = Track::query()
            ->where('user_id', Auth::id())
            ->whereNotNull('artist')
            ->where('artist', '!=', '')
            ->when($this->search, fn ($q) => $q->where('artist', 'like', "%{$this->search}%"))
            ->select('artist')
            ->selectRaw('COUNT(*) as track_count')
            ->groupBy('artist')
            ->orderBy('artist')
            ->paginate($this->artistsPerPage, ['*'], 'artists');

        $artists->getCollection()->each(function ($artist) {
            $artist->albums = Album::query()
                ->where('user_id', Auth::id())
                ->where('artist', $artist->artist)
                ->orderBy('title')
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'cover_art_url' => $a->coverArtUrl(),
                ])->all();
        });

        return $artists;
    }

    /**
     * Order the track query by the selected sort, always with a stable
     * alphabetical tiebreak. Unknown values fall back to A-Z.
     */
    private function applySort(Builder $query): void
    {
        match ($this->sort) {
            'recent' => $query->orderByDesc('created_at')->orderBy('title'),
            'most_played' => $query->orderByDesc('play_count')->orderBy('title'),
            'recently_played' => $query->orderByRaw('last_played_at IS NULL')->orderByDesc('last_played_at')->orderBy('title'),
            default => $query->orderBy('title'),
        };
    }

    /**
     * Return every list to its first page when search changes.
     */
    public function updatedSearch(): void
    {
        $this->resetAllPages();
    }

    /**
     * Discard URL values that name no real view state.
     *
     * All three properties are URL-bound, and #[Url] assigns them straight to the
     * property during mount() without firing updated* hooks — so the first load
     * needs booted(), while later edits arrive through updated*. Both funnel here.
     * Without this a hand-edited ?activeTab= renders an empty tab body, because
     * the panel chain has no else branch.
     */
    private function normaliseViewState(): void
    {
        if (! in_array($this->sort, ['title', 'recent', 'most_played', 'recently_played'], true)) {
            $this->sort = 'title';
        }

        if (! in_array($this->sourceFilter, ['all', 'favorites', 'uploaded', 'local', 'unavailable'], true)) {
            $this->sourceFilter = 'all';
        }

        if (! in_array($this->activeTab, ['tracks', 'artists', 'albums'], true)) {
            $this->activeTab = 'tracks';
        }
    }

    /**
     * Normalise the filters once the URL has been applied to them.
     */
    public function booted(): void
    {
        $this->normaliseViewState();
    }

    /**
     * Return the track list to its first page when the sort changes.
     */
    public function updatedSort(): void
    {
        $this->normaliseViewState();
        $this->resetAllPages();
    }

    /**
     * Return every list to its first page when the source filter changes.
     */
    public function updatedSourceFilter(): void
    {
        $this->normaliseViewState();
        $this->resetAllPages();
    }

    /**
     * Normalise an unrecognised tab.
     */
    public function updatedActiveTab(): void
    {
        $this->normaliseViewState();
    }

    /**
     * Reset all three paginators.
     */
    private function resetAllPages(): void
    {
        $this->resetPage('page');
        $this->resetPage('albums');
        $this->resetPage('artists');
    }

    /**
     * Whether the library has nothing to show for the current filters.
     */
    #[Computed]
    public function isEmpty(): bool
    {
        return $this->tracks->isEmpty() && $this->albums->isEmpty();
    }
}; ?>

<div class="min-h-screen pb-20">
    <x-ui.page-header :title="__('My Library')">
        <x-slot:actions>
            <x-mary-button label="{{ __('Upload') }}" class="btn-primary btn-capsule btn-sm" x-on:click="$dispatch('open-upload-modal')" />
        </x-slot:actions>

        <x-slot:hero>
            <div class="mt-5 flex flex-wrap items-center gap-3">
                <label class="input flex w-full max-w-md items-center gap-2 rounded-full">
                    <x-mary-icon name="o-magnifying-glass" class="size-4 shrink-0 text-base-content/45" />
                    <input
                        wire:model.live.debounce.300ms="search"
                        type="search"
                        placeholder="{{ __('Search your library...') }}"
                        class="grow bg-transparent outline-none"
                        aria-label="{{ __('Search your library') }}"
                    >
                </label>

                <select wire:model.live="sourceFilter" class="select select-sm w-auto rounded-full" aria-label="{{ __('Source') }}">
                    <option value="all">{{ __('All Sources') }}</option>
                    <option value="favorites">{{ __('Favourites') }}</option>
                    <option value="uploaded">{{ __('Uploaded') }}</option>
                    <option value="local">{{ __('Local') }}</option>
                    <option value="unavailable">{{ __('Unavailable') }}</option>
                </select>

                <select wire:model.live="sort" class="select select-sm w-auto rounded-full" aria-label="{{ __('Sort tracks') }}" data-test="library-sort">
                    <option value="title">{{ __('A to Z') }}</option>
                    <option value="recent">{{ __('Recently Added') }}</option>
                    <option value="most_played">{{ __('Most Played') }}</option>
                    <option value="recently_played">{{ __('Recently Played') }}</option>
                </select>
            </div>
        </x-slot:hero>
    </x-ui.page-header>

    {{-- scroll-mt keeps the first row clear of the sticky page toolbar when a
         page change scrolls the list back into view. --}}
    <div id="library-lists" class="scroll-mt-16 px-4 sm:px-6">
        @if (strlen($this->search) > 0)
            {{-- Search results reuse the same components as the tabs. --}}
            <div class="space-y-10">
                <section>
                    <h2 class="mb-3 text-title-2">{{ __('Songs') }}</h2>

                    @if ($this->tracks->isEmpty())
                        <p class="text-body text-base-content/65">{{ __('No songs found.') }}</p>
                    @else
                        <x-ui.track-table
                            :tracks="$this->tracks"
                            :track-ids="$this->tracks->pluck('id')->all()"
                        />

                        <div class="mt-4">{{ $this->tracks->onEachSide(1)->links(data: ['scrollTo' => '#library-lists']) }}</div>
                    @endif
                </section>

                <section>
                    <h2 class="mb-3 text-title-2">{{ __('Albums') }}</h2>

                    @if ($this->albums->isEmpty())
                        <p class="text-body text-base-content/65">{{ __('No albums found.') }}</p>
                    @else
                        <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                            @foreach ($this->albums as $album)
                                <x-ui.album-card
                                    wire:key="album-{{ $album->id }}"
                                    :title="$album->title"
                                    :subtitle="$album->artist ?? __('Unknown Artist')"
                                    :href="route('library.album.show', $album->id)"
                                    :cover="$album->coverArtUrl()"
                                />
                            @endforeach
                        </div>

                        <div class="mt-6">{{ $this->albums->onEachSide(1)->links(data: ['scrollTo' => '#library-lists']) }}</div>
                    @endif
                </section>
            </div>
        @elseif ($this->isEmpty)
            @if ($this->sourceFilter === 'favorites')
                <x-empty-state
                    :title="__('No favourites yet')"
                    :description="__('Hover a track and tap the heart to keep it here.')"
                    icon="o-heart"
                />
            @elseif ($this->sourceFilter === 'local')
                <x-empty-state
                    :title="__('No local tracks found')"
                    :description="__('Link a folder in Settings > Linked Folders to add local tracks.')"
                    icon="o-folder"
                />
            @elseif ($this->sourceFilter === 'uploaded')
                <x-empty-state :title="__('No uploaded tracks found')" :description="__('Upload your first track to get started')">
                    <x-slot:action>
                        <x-mary-button label="{{ __('Upload Music') }}" class="btn-primary btn-capsule" x-on:click="$dispatch('open-upload-modal')" />
                    </x-slot:action>
                </x-empty-state>
            @else
                <x-empty-state :title="__('Your library is empty')" :description="__('Upload your first track to get started')">
                    <x-slot:action>
                        <x-mary-button label="{{ __('Upload Music') }}" class="btn-primary btn-capsule" x-on:click="$dispatch('open-upload-modal')" />
                    </x-slot:action>
                </x-empty-state>
            @endif
        @else
            <div>
                <x-ui.segmented-control
                    :options="['tracks' => __('Tracks'), 'artists' => __('Artists'), 'albums' => __('Albums')]"
                    :current="$this->activeTab"
                    :label="__('Library view')"
                />

                <div class="!my-5 pb-20">
                    @if ($this->activeTab === 'tracks')
                        <div>
                            @if ($this->tracks->isEmpty())
                                <div class="py-12 text-center">
                                    @if ($this->sourceFilter === 'local')
                                        <p class="text-body text-base-content/65">{{ __('No local tracks found. Link a folder in Settings > Linked Folders.') }}</p>
                                    @elseif ($this->sourceFilter === 'uploaded')
                                        <p class="text-body text-base-content/65">{{ __('No uploaded tracks found.') }}</p>
                                    @else
                                        <p class="text-body text-base-content/65">{{ __('No tracks yet.') }}</p>
                                    @endif
                                </div>
                            @else
                                <x-ui.track-table
                                    :tracks="$this->tracks"
                                    :track-ids="$this->tracks->pluck('id')->all()"
                                />

                                <div class="my-4">{{ $this->tracks->onEachSide(1)->links(data: ['scrollTo' => '#library-lists']) }}</div>
                            @endif
                        </div>
                    @elseif ($this->activeTab === 'artists')
                        <div>
                            @if ($this->artists->isEmpty())
                                <div class="py-12 text-center">
                                    <p class="text-body text-base-content/65">{{ __('No artists found.') }}</p>
                                </div>
                            @else
                                <div class="divide-y divide-separator">
                                    @foreach ($this->artists as $artist)
                                        <div wire:key="artist-{{ $artist->artist }}" class="flex items-center gap-4 py-3">
                                            <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-primary/12 text-subhead font-semibold text-primary">
                                                {{ \Illuminate\Support\Str::of($artist->artist)->substr(0, 1)->upper() }}
                                            </span>

                                            <div class="min-w-0 flex-1">
                                                <a
                                                    href="{{ route('library.artist.show', ['artist' => $artist->artist]) }}"
                                                    wire:navigate
                                                    class="block truncate text-body-emphasized hover:underline"
                                                >{{ $artist->artist }}</a>
                                                <p class="text-footnote text-base-content/65">
                                                    {{ $artist->track_count }} {{ Str::plural('track', $artist->track_count) }}
                                                </p>
                                            </div>

                                            @if (! empty($artist->albums))
                                                <div class="hidden shrink-0 gap-2 sm:flex">
                                                    @foreach (array_slice($artist->albums, 0, 5) as $album)
                                                        <a href="{{ route('library.album.show', $album['id']) }}" wire:navigate
                                                           class="artwork block size-11 shrink-0 overflow-hidden rounded-art bg-base-300"
                                                           title="{{ $album['title'] }}">
                                                            @if ($album['cover_art_url'])
                                                                <img src="{{ $album['cover_art_url'] }}" alt="{{ $album['title'] }}" loading="lazy" class="size-full object-cover">
                                                            @else
                                                                <span class="flex size-full items-center justify-center">
                                                                    <x-mary-icon name="o-musical-note" class="size-4 text-base-content/35" />
                                                                </span>
                                                            @endif
                                                        </a>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                <div class="mt-6">{{ $this->artists->onEachSide(1)->links(data: ['scrollTo' => '#library-lists']) }}</div>
                            @endif
                        </div>
                    @elseif ($this->activeTab === 'albums')
                        <div>
                            @if ($this->albums->isEmpty())
                                <div class="py-12 text-center">
                                    <p class="text-body text-base-content/65">{{ __('No albums yet.') }}</p>
                                </div>
                            @else
                                <div class="grid grid-cols-2 gap-x-4 gap-y-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                                    @foreach ($this->albums as $album)
                                        <x-ui.album-card
                                            wire:key="album-{{ $album->id }}"
                                            :title="$album->title"
                                            :subtitle="$album->artist ?? __('Unknown Artist')"
                                            :href="route('library.album.show', $album->id)"
                                            :cover="$album->coverArtUrl()"
                                        />
                                    @endforeach
                                </div>

                                <div class="mt-6">{{ $this->albums->onEachSide(1)->links(data: ['scrollTo' => '#library-lists']) }}</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
