@php
    use App\Livewire\AudioPlayer;

    $repeatOn = $this->repeat !== AudioPlayer::REPEAT_OFF;
@endphp

<div
    x-data="audioPlayer(@js($volume), @js(route('stream.track', ['track' => '__track__'])))"
    x-init="init()"
    @keydown.space.window="togglePlayPause($event)"
    @keydown.arrow-right.window="$wire.nextTrack()"
    @keydown.arrow-left.window="$wire.previousTrack()"
    @player:load.window="onLoad($event.detail)"
    @player:clear.window="onClear()"
    @play-track.window="onPlayTrack($event.detail)"
    @add-to-queue.window="onAddToQueue($event.detail)"
    @toggle-favorite.window="$wire.toggleFavorite($event.detail.trackId)"
    @favorite-changed.window="onFavoriteChanged($event.detail)"
    x-show="hasTrack"
    x-cloak
    x-effect="document.documentElement.dataset.queue = queueOpen ? 'open' : 'closed'"
    role="region"
    aria-label="{{ __('Audio Player') }}"
>
    <audio
        x-ref="audio"
        x-on:timeupdate="onTimeUpdate"
        x-on:progress="onProgress"
        x-on:ended="onEnded"
        x-on:error="onAudioError"
        x-on:loadedmetadata="onLoadedMetadata"
        x-on:volumechange="onVolumeChange"
        preload="none"
    ></audio>

    {{-- ── Desktop bar ─────────────────────────────────────────────────── --}}
    {{-- The bar spans the content column, not the sidebar: a truly full-width bar
         would sit on top of the sidebar's user menu. --}}
    <div
        class="surface-chrome fixed right-0 bottom-0 left-0 z-40 hidden items-center gap-4 border-t border-separator px-4 py-2 lg:left-[240px] lg:flex"
        data-test="player-bar"
    >
        {{-- Now playing --}}
        <div class="flex min-w-0 flex-1 items-center gap-3">
            {{-- Opens the expanded view, mirroring the mobile mini-player. --}}
            <button
                type="button"
                x-on:click="nowPlayingOpen = true"
                class="flex min-w-0 flex-1 items-center gap-3 text-left"
                aria-label="{{ __('Open now playing') }}"
                data-test="expand-now-playing"
            >
                <div class="artwork relative size-12 shrink-0 overflow-hidden rounded-art bg-base-300">
                    {{-- Outside both x-if branches: the glyph has to already be in the
                         DOM for the image's onerror removal to reveal it. --}}
                    <span class="flex size-full items-center justify-center">
                        <x-mary-icon name="o-musical-note" class="size-5 text-base-content/35" />
                    </span>

                    <template x-if="coverArtUrl">
                        <img :src="coverArtUrl" :alt="trackTitle" onerror="this.remove()" class="absolute inset-0 size-full object-cover">
                    </template>
                </div>

                <div class="min-w-0">
                    <p class="truncate text-body-emphasized" x-text="trackTitle" data-test="player-title"></p>
                    <p class="truncate text-subhead text-base-content/65" x-text="trackArtist"></p>
                </div>
            </button>

            <button
                type="button"
                x-on:click="toggleFavoriteCurrent"
                :aria-pressed="favorited"
                aria-label="{{ __('Favourite') }}"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full transition-colors duration-150 ease-out-ios hover:bg-fill"
                data-test="player-favorite"
            >
                <template x-if="favorited">
                    <x-mary-icon name="s-heart" class="size-6 text-primary" />
                </template>
                <template x-if="!favorited">
                    <x-mary-icon name="o-heart" class="size-6 text-base-content/45" />
                </template>
            </button>
        </div>

        {{-- Transport + scrubber --}}
        <div class="flex w-[42%] max-w-xl shrink-0 flex-col items-center gap-1">
            <div class="flex items-center gap-2" role="group" aria-label="{{ __('Playback controls') }}">
                <x-ui.icon-button
                    icon="arrows-right-left"
                    size="sm"
                    :label="__('Toggle shuffle')"
                    :active="$this->isShuffled"
                    :aria-pressed="$this->isShuffled ? 'true' : 'false'"
                    wire:click="toggleShuffle"
                    data-test="shuffle"
                />

                <x-ui.icon-button
                    icon="backward"
                    :label="__('Previous track')"
                    wire:click="previousTrack"
                    ::disabled="!hasPrevious"
                />

                <button
                    type="button"
                    x-on:click="togglePlayPause"
                    :disabled="!hasTrack"
                    class="flex size-11 items-center justify-center rounded-full bg-primary text-primary-content transition-transform duration-150 ease-out-ios hover:opacity-90 active:scale-95 disabled:opacity-40"
                    :aria-label="isPlaying ? '{{ __('Pause') }}' : '{{ __('Play') }}'"
                    :aria-pressed="isPlaying"
                    data-test="play-pause"
                >
                    <template x-if="isPlaying">
                        <x-mary-icon name="s-pause" class="size-6" />
                    </template>
                    <template x-if="!isPlaying">
                        <x-mary-icon name="s-play" class="size-6 translate-x-px" />
                    </template>
                </button>

                <x-ui.icon-button
                    icon="forward"
                    :label="__('Next track')"
                    wire:click="nextTrack"
                    ::disabled="!hasNext && $wire.repeat !== 'all'"
                />

                <div class="relative">
                    <x-ui.icon-button
                        icon="arrow-path"
                        size="sm"
                        :label="__('Repeat')"
                        :active="$repeatOn"
                        :aria-pressed="$repeatOn ? 'true' : 'false'"
                        wire:click="cycleRepeat"
                        data-test="repeat"
                    />
                    @if ($this->repeat === AudioPlayer::REPEAT_ONE)
                        <span class="pointer-events-none absolute -top-0.5 -right-0.5 flex size-3.5 items-center justify-center rounded-full bg-primary text-[0.5rem] font-bold text-primary-content">1</span>
                    @endif
                </div>
            </div>

            <div class="flex w-full items-center gap-2" role="group" aria-label="{{ __('Seek') }}">
                <time class="w-10 shrink-0 text-right text-footnote text-base-content/65" x-text="formatTime(currentTime)"></time>
                <x-ui.scrubber />
                <time class="w-10 shrink-0 text-footnote text-base-content/65" x-text="formatTime(duration)"></time>
            </div>
        </div>

        {{-- Volume + queue --}}
        <div class="flex flex-1 items-center justify-end gap-2">
            <button
                type="button"
                x-on:click="toggleMute"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full text-base-content/65 transition-colors duration-150 ease-out-ios hover:bg-fill hover:text-base-content"
                :aria-label="isMuted || volume === 0 ? '{{ __('Unmute') }}' : '{{ __('Mute') }}'"
                :aria-pressed="isMuted"
                data-test="mute"
            >
                <template x-if="isMuted || volume === 0">
                    <x-mary-icon name="o-speaker-x-mark" class="size-5" />
                </template>
                <template x-if="!isMuted && volume > 0 && volume <= 50">
                    <x-mary-icon name="o-speaker-wave" class="size-5" />
                </template>
                <template x-if="!isMuted && volume > 50">
                    <x-mary-icon name="s-speaker-wave" class="size-5" />
                </template>
            </button>

            <div class="group/vol relative flex h-4 w-24 items-center">
                <input
                    type="range" min="0" max="100"
                    :value="isMuted ? 0 : volume"
                    x-on:input="setVolume($event.target.value)"
                    class="slider absolute inset-0 z-20 w-full"
                    aria-label="{{ __('Volume') }}"
                    :aria-valuenow="isMuted ? 0 : volume"
                    :aria-valuetext="(isMuted ? 0 : volume) + '%'"
                >
                <div class="slider-track pointer-events-none absolute inset-x-0 z-0 h-1 rounded-full bg-base-content/20 transition-all duration-150 group-hover/vol:h-1.5"></div>
                <div class="pointer-events-none absolute left-0 z-0 h-1 rounded-full bg-primary transition-all duration-150 group-hover/vol:h-1.5" :style="`width: ${isMuted ? 0 : volume}%`"></div>
            </div>

            <button
                type="button"
                x-on:click="lyricsOpen = !lyricsOpen; if (lyricsOpen) { queueOpen = false; activeLyric = -1; updateActiveLyric() }"
                :disabled="!hasLyrics"
                class="inline-flex size-8 shrink-0 items-center justify-center rounded-full transition-colors duration-150 ease-out-ios hover:bg-fill disabled:pointer-events-none disabled:opacity-30"
                :class="lyricsOpen ? 'text-primary' : 'text-base-content/65 hover:text-base-content'"
                :aria-expanded="lyricsOpen"
                aria-label="{{ __('Lyrics') }}"
                data-test="lyrics-toggle"
            >
                <x-mary-icon name="o-microphone" class="size-5" />
            </button>

            {{-- Hidden when the current track is the only one queued. An empty
                 queue is not the case worth testing: the player is hidden before
                 playback anyway, and the queue always holds the playing track. --}}
            @if ($this->hasUpcoming)
                <button
                    type="button"
                    x-on:click="queueOpen = !queueOpen; if (queueOpen) lyricsOpen = false"
                    class="inline-flex size-8 shrink-0 items-center justify-center rounded-full transition-colors duration-150 ease-out-ios hover:bg-fill"
                    :class="queueOpen ? 'text-primary' : 'text-base-content/65 hover:text-base-content'"
                    :aria-expanded="queueOpen"
                    aria-label="{{ __('Playing Next') }}"
                    data-test="queue-toggle"
                >
                    <x-mary-icon name="o-queue-list" class="size-5" />
                </button>
            @endif
        </div>
    </div>

    {{-- ── Mobile mini-player ──────────────────────────────────────────── --}}
    <div
        class="surface-chrome fixed inset-x-2 bottom-[calc(3.5rem+env(safe-area-inset-bottom)+0.5rem)] z-40 flex items-center gap-3 rounded-box border border-separator px-3 py-2 shadow-2 lg:hidden"
        data-test="mini-player"
    >
        <button
            type="button"
            x-on:click="nowPlayingOpen = true"
            class="flex min-w-0 flex-1 items-center gap-3 text-left"
            aria-label="{{ __('Open now playing') }}"
        >
            <div class="artwork size-10 shrink-0 overflow-hidden rounded-art bg-base-300">
                <template x-if="coverArtUrl">
                    <img :src="coverArtUrl" :alt="trackTitle" class="size-full object-cover">
                </template>
                <template x-if="!coverArtUrl">
                    <span class="flex size-full items-center justify-center">
                        <x-mary-icon name="o-musical-note" class="size-4 text-base-content/35" />
                    </span>
                </template>
            </div>
            <div class="min-w-0">
                <p class="truncate text-subhead font-medium" x-text="trackTitle"></p>
                <p class="truncate text-footnote text-base-content/65" x-text="trackArtist"></p>
            </div>
        </button>

        <button
            type="button"
            x-on:click="togglePlayPause"
            class="flex size-9 shrink-0 items-center justify-center rounded-full text-base-content"
            :aria-label="isPlaying ? '{{ __('Pause') }}' : '{{ __('Play') }}'"
        >
            <template x-if="isPlaying"><x-mary-icon name="s-pause" class="size-6" /></template>
            <template x-if="!isPlaying"><x-mary-icon name="s-play" class="size-6" /></template>
        </button>

        <x-ui.icon-button
            icon="forward"
            size="sm"
            :label="__('Next track')"
            wire:click="nextTrack"
            ::disabled="!hasNext && $wire.repeat !== 'all'"
        />
    </div>

    {{-- ── Now playing sheet (mobile) / expanded card (desktop) ──────────────── --}}
    <div
        x-show="nowPlayingOpen"
        x-cloak
        class="fixed inset-0 z-[60]"
        role="dialog"
        aria-modal="true"
        aria-label="{{ __('Now playing') }}"
        x-on:keydown.escape.window="nowPlayingOpen = false"
    >
        <div
            {{-- Mobile is a full-bleed sheet, so the layer stays a plain block. On
                 desktop the card drops to its own height and the layer centres it on
                 both axes — without this it hugs the top of the viewport. --}}
            class="absolute inset-0 bg-base-100 lg:flex lg:items-center lg:justify-center lg:bg-overlay lg:backdrop-blur-sm"
            x-on:click="if (event.target === $el) nowPlayingOpen = false"
            x-transition:enter="transition ease-sheet duration-300"
            x-transition:enter-start="translate-y-full"
            x-transition:enter-end="translate-y-0"
            x-transition:leave="transition ease-sheet duration-200"
            x-transition:leave-start="translate-y-0"
            x-transition:leave-end="translate-y-full"
        >
            {{-- Blurred artwork backdrop. Mobile only: on desktop the card floats
                 on a plain scrim, and a second blurred layer just muddies it. --}}
            <template x-if="coverArtUrl">
                <div class="pointer-events-none absolute inset-0 overflow-hidden lg:hidden">
                    <img :src="coverArtUrl" alt="" class="size-full scale-125 object-cover opacity-20 blur-3xl">
                </div>
            </template>

            <div class="relative mx-auto flex h-full w-full max-w-lg flex-col px-4 pt-3 pb-[calc(1.5rem+env(safe-area-inset-bottom))] lg:h-auto lg:rounded-box lg:bg-base-100 lg:px-6 lg:pt-5 lg:pb-6 lg:shadow-3">
                {{-- Mobile drags the sheet down by its handle; the desktop card has
                     no handle to grab, so it needs an explicit dismiss. --}}
                <div class="my-4 relative flex items-center lg:hidden">
                    <!-- <button class="mx-auto h-1 w-9 rounded-full bg-base-content/20" x-on:click="nowPlayingOpen = false"></button> -->
                </div>

                <x-ui.icon-button
                    icon="x-mark"
                    size="md"
                    :label="__('Close now playing')"
                    x-on:click="nowPlayingOpen = false"
                    class="absolute top-3 right-3 hidden lg:inline-flex mb-2"
                    data-test="collapse-now-playing"
                />

                <div class="artwork relative mx-auto mt-2 aspect-square w-full max-w-lg overflow-hidden rounded-2xl bg-base-300 shadow-3 lg:max-w-[280px] lg:rounded-art-lg">
                    {{-- Outside both x-if branches so the glyph survives an onerror
                         removal of the image. --}}
                    <span class="flex size-full items-center justify-center">
                        <x-mary-icon name="o-musical-note" class="size-16 text-base-content/25" />
                    </span>

                    <template x-if="coverArtUrl">
                        <img :src="coverArtUrl" :alt="trackTitle" onerror="this.remove()" class="absolute inset-0 size-full object-cover">
                    </template>
                </div>

                {{-- TODO: Move below artwork --}}
                <div class="flex justify-center gap-2 pt-6">
                    <x-mary-button
                        x-show="hasLyrics"
                        x-on:click="lyricsOpen = true; activeLyric = -1; updateActiveLyric()"
                        class="btn-ghost btn-sm bg-base-300 text-base-content"
                    >
                        <x-mary-icon name="o-microphone" class="size-4" />
                        {{ __('Lyrics') }}
                    </x-mary-button>

                    @if ($this->hasUpcoming)
                        <x-mary-button
                            x-on:click="queueOpen = true"
                            class="btn-ghost btn-sm bg-base-300 text-base-content"
                        >
                            <x-mary-icon name="o-queue-list" class="size-4" />
                            {{ __('Playing Next') }}
                        </x-mary-button>
                    @endif
                </div>

                <div class="mx-4 mt-auto flex items-center gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-title-2" x-text="trackTitle"></p>
                        <p class="truncate text-body text-base-content/65" x-text="trackArtist"></p>
                    </div>

                    <button
                        type="button"
                        x-on:click="toggleFavoriteCurrent"
                        :aria-pressed="favorited"
                        aria-label="{{ __('Favourite') }}"
                        class="inline-flex size-10 shrink-0 items-center justify-center rounded-full transition-colors duration-150 ease-out-ios hover:bg-fill"
                    >
                        <template x-if="favorited">
                            <x-mary-icon name="s-heart" class="size-6 text-primary" />
                        </template>
                        <template x-if="!favorited">
                            <x-mary-icon name="o-heart" class="size-6 text-base-content/45" />
                        </template>
                    </button>
                </div>

                {{-- The waveform gets the full card width and the times sit on their
                     own row beneath it, elapsed at the left and total at the right.
                     Sharing one row cost the shape 5rem of width and left the times
                     to be aligned against bars that grow up from a baseline. The plain
                     scrubber in the bar above is narrow and centred, so its times stay
                     on the same line as it.

                     mx-4 matches the title-and-heart row above, so the elapsed time
                     lines up under the title and the total lines up under the heart's
                     trailing edge. The waveform itself keeps the full width: it is the
                     only element wide enough to be worth that inset. --}}
                <div class="mt-6">
                    <x-ui.waveform />

                    <div class="mx-4 mt-2 flex items-baseline justify-between">
                        <time class="text-footnote text-base-content/65 tabular-nums" x-text="formatTime(currentTime)"></time>
                        <time class="pr-2 text-footnote text-base-content/65 tabular-nums" x-text="formatTime(duration)"></time>
                    </div>
                </div>

                <div class="mt-4 mb-2 flex items-center justify-center gap-4">
                    <x-ui.icon-button
                        icon="arrows-right-left"
                        :label="__('Toggle shuffle')"
                        :active="$this->isShuffled"
                        wire:click="toggleShuffle"
                    />
                    <x-ui.icon-button icon="backward" size="lg" :label="__('Previous track')" wire:click="previousTrack" ::disabled="!hasPrevious" />

                    <button
                        type="button"
                        x-on:click="togglePlayPause"
                        class="flex size-16 items-center justify-center rounded-full bg-primary text-primary-content active:scale-95"
                        :aria-label="isPlaying ? '{{ __('Pause') }}' : '{{ __('Play') }}'"
                    >
                        <template x-if="isPlaying"><x-mary-icon name="s-pause" class="size-8" /></template>
                        <template x-if="!isPlaying"><x-mary-icon name="s-play" class="size-8 translate-x-0.5" /></template>
                    </button>

                    <x-ui.icon-button icon="forward" size="lg" :label="__('Next track')" wire:click="nextTrack" ::disabled="!hasNext && $wire.repeat !== 'all'" />
                    <x-ui.icon-button
                        icon="arrow-path"
                        :label="__('Repeat')"
                        :active="$repeatOn"
                        wire:click="cycleRepeat"
                    />
                </div>
            </div>
        </div>
    </div>

    {{-- ── Queue panel ─────────────────────────────────────────────────── --}}
    <div x-show="queueOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="{{ __('Playing Next') }}">
        {{-- The sheet dims the page on mobile; on desktop the slide-over is a
             companion panel, so it only catches outside clicks. --}}
        <div
            class="absolute inset-0 bg-overlay sm:bg-transparent"
            x-show="queueOpen"
            x-transition.opacity
            x-on:click="queueOpen = false"
        ></div>

        <div
            class="surface-chrome absolute right-0 bottom-0 flex w-full flex-col border-separator shadow-3 max-sm:h-[72vh] max-sm:rounded-t-box max-sm:border-t sm:top-0 sm:w-[360px] sm:border-l lg:bottom-[72px] xl:bottom-0"
            x-on:keydown.escape.window="queueOpen = false"
            x-transition:enter="transition ease-sheet duration-300"
            x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
            x-transition:enter-end="translate-y-0 sm:translate-x-0"
            x-transition:leave="transition ease-sheet duration-200"
            x-transition:leave-start="translate-y-0 sm:translate-x-0"
            x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full"
        >
            <header class="flex items-center justify-between border-b border-separator px-4 py-3">
                <h2 class="text-title-3">{{ __('Playing Next') }}</h2>
                <div class="flex items-center gap-1">
                    @if (! empty($this->queue))
                        <button
                            type="button"
                            wire:click="clearQueue"
                            class="rounded-field px-2 py-1 text-subhead text-base-content/65 transition-colors hover:bg-fill hover:text-base-content"
                            data-test="queue-clear"
                        >{{ __('Clear') }}</button>
                    @endif
                    <x-ui.icon-button icon="x-mark" size="sm" :label="__('Close')" x-on:click="queueOpen = false" />
                </div>
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto p-2" data-test="queue-list">
                @php($queueCount = count($this->queue))
                @foreach ($this->queueTracks as $track)
                    @php($isCurrent = $track->id === $this->currentTrackId)
                    <div
                        wire:key="queue-{{ $track->id }}-{{ $track->queue_index }}"
                        draggable="true"
                        x-on:dragstart="dragIdx = {{ $track->queue_index }}"
                        x-on:dragover.prevent
                        x-on:drop.prevent="if (dragIdx !== null) { $wire.moveInQueue(dragIdx, {{ $track->queue_index }}); dragIdx = null }"
                        x-on:dragend="dragIdx = null"
                        @class([
                            'group flex items-center gap-3 rounded-field px-2 py-2 transition-colors sm:cursor-grab sm:active:cursor-grabbing',
                            'bg-primary/12' => $isCurrent,
                            'hover:bg-fill' => ! $isCurrent,
                        ])
                        :class="dragIdx === {{ $track->queue_index }} && 'opacity-50'"
                    >
                        <button
                            type="button"
                            wire:click="playQueueIndex({{ $track->queue_index }})"
                            class="flex min-w-0 flex-1 items-center gap-3 text-left"
                            aria-label="{{ __('Play') }} {{ $track->title }}"
                        >
                            <span class="artwork relative flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-art bg-base-300">
                                <x-mary-icon name="o-musical-note" class="size-4 text-base-content/35" />

                                @if ($track->album?->coverArtUrl())
                                    <img src="{{ $track->album->coverArtUrl() }}" alt="" onerror="this.remove()" class="absolute inset-0 size-full object-cover">
                                @endif

                                @if ($isCurrent)
                                    <span class="absolute inset-0 flex items-end justify-center gap-[2px] bg-overlay pb-2" aria-hidden="true">
                                        <span class="eq-bar h-2.5 w-[2px] rounded-full bg-on-overlay"></span>
                                        <span class="eq-bar h-2.5 w-[2px] rounded-full bg-on-overlay" style="animation-delay: .15s"></span>
                                        <span class="eq-bar h-2.5 w-[2px] rounded-full bg-on-overlay" style="animation-delay: .3s"></span>
                                    </span>
                                @endif
                            </span>

                            <span class="min-w-0 flex-1">
                                <span @class(['block truncate text-subhead', 'font-medium text-primary' => $isCurrent])>{{ $track->title }}</span>
                                <span class="block truncate text-footnote text-base-content/65">{{ $track->artist ?? __('Unknown Artist') }}</span>
                            </span>
                        </button>

                        <time class="shrink-0 text-footnote text-base-content/65">
                            @if ($track->duration === null)
                                &mdash;
                            @else
                                {{ intdiv($track->duration, 60) }}:{{ str_pad($track->duration % 60, 2, '0', STR_PAD_LEFT) }}
                            @endif
                        </time>

                        <span class="flex shrink-0 items-center opacity-0 transition-opacity duration-150 group-hover:opacity-100 focus-within:opacity-100 max-sm:opacity-100">
                            <x-ui.icon-button
                                icon="chevron-up"
                                size="sm"
                                :label="__('Move up') . ' ' . $track->title"
                                wire:click="moveInQueue({{ $track->queue_index }}, {{ $track->queue_index - 1 }})"
                                :disabled="$track->queue_index === 0"
                                data-test="queue-up"
                            />
                            <x-ui.icon-button
                                icon="chevron-down"
                                size="sm"
                                :label="__('Move down') . ' ' . $track->title"
                                wire:click="moveInQueue({{ $track->queue_index }}, {{ $track->queue_index + 1 }})"
                                :disabled="$track->queue_index === $queueCount - 1"
                                data-test="queue-down"
                            />
                            <x-ui.icon-button
                                icon="x-mark"
                                size="sm"
                                :label="__('Remove from queue') . ' ' . $track->title"
                                wire:click="removeFromQueue({{ $track->queue_index }})"
                                data-test="queue-remove"
                            />
                        </span>
                    </div>
                @endforeach

                @if ($this->queueOverflowCount > 0)
                    <p class="px-2 py-3 text-center text-footnote text-base-content/45">
                        {{ trans_choice('+:count more track|+:count more tracks', $this->queueOverflowCount, ['count' => $this->queueOverflowCount]) }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Lyrics panel ────────────────────────────────────────────────── --}}
    <div x-show="lyricsOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true" aria-label="{{ __('Lyrics') }}">
        <div
            class="absolute inset-0 bg-overlay sm:bg-transparent"
            x-show="lyricsOpen"
            x-transition.opacity
            x-on:click="lyricsOpen = false"
        ></div>

        <div
            class="surface-chrome absolute right-0 bottom-0 flex w-full flex-col border-separator shadow-3 max-sm:h-[72vh] max-sm:rounded-t-box max-sm:border-t sm:top-0 sm:w-[360px] sm:border-l lg:bottom-[72px] xl:bottom-0"
            x-on:keydown.escape.window="lyricsOpen = false"
            x-transition:enter="transition ease-sheet duration-300"
            x-transition:enter-start="translate-y-full sm:translate-y-0 sm:translate-x-full"
            x-transition:enter-end="translate-y-0 sm:translate-x-0"
            x-transition:leave="transition ease-sheet duration-200"
            x-transition:leave-start="translate-y-0 sm:translate-x-0"
            x-transition:leave-end="translate-y-full sm:translate-y-0 sm:translate-x-full"
        >
            <header class="flex items-center justify-between border-b border-separator px-4 py-3">
                <div class="min-w-0">
                    <h2 class="text-title-3">{{ __('Lyrics') }}</h2>
                    <p class="truncate text-footnote text-base-content/65" x-text="trackTitle"></p>
                </div>
                <x-ui.icon-button icon="x-mark" size="sm" :label="__('Close')" x-on:click="lyricsOpen = false" />
            </header>

            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-6" data-test="lyrics-list">
                <template x-for="(line, i) in lyrics" :key="i">
                    <p
                        :data-lyric-index="i"
                        x-text="line.text"
                        x-on:click="if (lyricsSynced && line.t !== null) seek(line.t)"
                        class="py-1.5 transition-all duration-200 ease-out-ios"
                        :class="lyricsSynced
                            ? (i === activeLyric
                                ? 'text-title-3 text-base-content'
                                : 'cursor-pointer text-body text-base-content/35 hover:text-base-content/65')
                            : 'text-body text-base-content/80'"
                    ></p>
                </template>

                <p x-show="lyricsSynced" class="pt-4 text-footnote text-base-content/35">
                    {{ __('Tap a line to jump there.') }}
                </p>
            </div>
        </div>
    </div>

    {{-- ── Replace queue confirmation ──────────────────────────────────── --}}
    <div
        x-show="showReplaceModal"
        x-cloak
        class="modal fixed inset-0 z-[80] flex items-center justify-center p-4"
        :class="{ 'modal-open !animate-none': showReplaceModal }"
        role="dialog"
        aria-modal="true"
        aria-labelledby="replace-queue-title"
        x-on:keydown.escape.window="cancelReplace()"
    >
        <div class="modal-backdrop absolute inset-0" x-on:click="cancelReplace()"></div>

        <div
            class="modal-box relative w-full max-w-sm bg-base-100 p-6"
            x-transition:enter="transition ease-out-ios duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
        >
            <h3 id="replace-queue-title" class="text-title-3">{{ __('Replace current queue?') }}</h3>
            <p class="mt-2 text-body text-base-content/65">
                {{ __('Music is currently playing. Do you want to replace the queue with this album?') }}
            </p>
            <div class="mt-6 flex justify-end gap-2">
                <button type="button" x-on:click="cancelReplace()" class="btn btn-capsule btn-sm bg-base-300 text-base-content">{{ __('Cancel') }}</button>
                <button type="button" x-on:click="confirmReplace()" class="btn btn-capsule btn-sm btn-primary">{{ __('Replace') }}</button>
            </div>
        </div>
    </div>
</div>
