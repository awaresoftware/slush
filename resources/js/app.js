/**
 * Shared Alpine components.
 *
 * Registered on `alpine:init` so they exist before Alpine walks the DOM,
 * which inline <script> tags inside Livewire component views cannot guarantee.
 */
document.addEventListener('alpine:init', () => {
    /**
     * Horizontal media shelf. The scrollbar is hidden, so overflow position drives
     * the only cue the reader gets: a fade at whichever edge still has content.
     */
    Alpine.data('mediaShelf', () => ({
        atStart: true,
        atEnd: false,
        _onResize: null,

        init() {
            this.sync();

            // Tiles stream in after hydration, so re-measure once they land and
            // again on resize, or a fade claims an edge that is not the real one.
            this.$nextTick(() => this.sync());

            this._onResize = () => this.sync();
            window.addEventListener('resize', this._onResize, { passive: true });
        },

        destroy() {
            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
            }
        },

        sync() {
            const el = this.$refs.scroller;

            if (! el) {
                return;
            }

            this.atStart = el.scrollLeft <= 1;
            this.atEnd = el.scrollLeft + el.clientWidth >= el.scrollWidth - 1;
        },
    }));

    Alpine.data('audioPlayer', (initialVolume, streamUrlTemplate) => ({
        // Playback
        isPlaying: false,
        hasTrack: false,
        currentTime: 0,
        duration: 0,
        bufferedPercent: 0,

        // Current track
        trackId: null,
        trackTitle: '',
        trackArtist: '',
        trackAlbum: '',
        coverArtUrl: null,
        favorited: false,

        // Play counting (Last.fm rule: half the track or 30s, whichever first)
        _playedSeconds: 0,
        _lastTime: 0,
        _playCounted: false,

        // Set while onEnded's early start waits for nextTrack()'s player:load.
        _advancing: false,

        // Volume
        volume: initialVolume,
        isMuted: false,

        // Queue navigation, mirrored from Livewire
        hasPrevious: false,
        hasNext: false,

        // Surfaces
        queueOpen: false,
        nowPlayingOpen: false,
        showReplaceModal: false,
        pendingPlayDetail: null,

        // Queue drag-reorder
        dragIdx: null,

        // Bound in init(), torn down in destroy(). The audio player is
        // @persist-ed, so this listener must not accumulate across navigation.
        _onResize: null,

        // Lyrics
        lyrics: [],
        lyricsSynced: false,
        hasLyrics: false,
        activeLyric: -1,
        lyricsOpen: false,

        // Waveform envelope, 0-100 per bucket. Empty when the track has none.
        peaks: [],

        get progressPercent() {
            return this.duration ? (this.currentTime / this.duration) * 100 : 0;
        },

        init() {
            this.$refs.audio.volume = this.volume / 100;

            this.$wire.$watch('queue', () => this.syncQueueState());
            this.$wire.$watch('currentTrackId', () => this.syncQueueState());

            this.registerMediaSession();

            // The sheet is hidden until it opens, so the canvas has no size to
            // paint into until then; re-measure whenever the window changes.
            this._onResize = () => this.paintWaveforms();
            window.addEventListener('resize', this._onResize, { passive: true });
        },

        destroy() {
            if (this._onResize) {
                window.removeEventListener('resize', this._onResize);
            }
        },

        syncQueueState() {
            const queue = this.$wire.queue;
            const index = queue.indexOf(this.$wire.currentTrackId);

            this.hasPrevious = index > 0;
            this.hasNext = index >= 0 && index < queue.length - 1;
        },

        /**
         * Hand playback control to the OS: media keys, lock screen, and the
         * now-playing widget all drive the same audio element.
         */
        registerMediaSession() {
            if (!('mediaSession' in navigator)) {
                return;
            }

            const audio = this.$refs.audio;

            navigator.mediaSession.setActionHandler('play', () => this.togglePlayPause());
            navigator.mediaSession.setActionHandler('pause', () => this.togglePlayPause());
            navigator.mediaSession.setActionHandler('nexttrack', () => this.$wire.nextTrack());
            navigator.mediaSession.setActionHandler('previoustrack', () => this.$wire.previousTrack());
            navigator.mediaSession.setActionHandler('seekto', (details) => {
                if (details.seekTime != null) this.seek(details.seekTime);
            });
            navigator.mediaSession.setActionHandler('seekbackward', (d) => {
                this.seek(Math.max(0, audio.currentTime - (d.seekOffset || 10)));
            });
            navigator.mediaSession.setActionHandler('seekforward', (d) => {
                this.seek(Math.min(this.duration, audio.currentTime + (d.seekOffset || 10)));
            });
        },

        /**
         * The MIME type that matches a cover URL's file extension. Everything is
         * WebP after the artwork backfill, but covers written before it are not,
         * and a wrong Media Session type is rejected by some OS media pickers.
         */
        artworkMimeType(url) {
            const extension = (url.split('?')[0].split('.').pop() || '').toLowerCase();

            return { webp: 'image/webp', png: 'image/png', gif: 'image/gif' }[extension] || 'image/jpeg';
        },

        updateMediaMetadata() {
            if (!('mediaSession' in navigator) || !window.MediaMetadata) {
                return;
            }

            const artwork = this.coverArtUrl
                ? [{
                    src: new URL(this.coverArtUrl, location.origin).href,
                    sizes: '512x512',
                    type: this.artworkMimeType(this.coverArtUrl),
                }]
                : [];

            navigator.mediaSession.metadata = new MediaMetadata({
                title: this.trackTitle || '',
                artist: this.trackArtist || '',
                album: this.trackAlbum || '',
                artwork,
            });
        },

        onPlayTrack(detail) {
            if (this.isPlaying) {
                this.pendingPlayDetail = detail;
                this.showReplaceModal = true;
                return;
            }

            this._executePlayTrack(detail);
        },

        _executePlayTrack(detail) {
            if (detail.trackIds && detail.trackIds.length > 0) {
                this.$wire.loadQueue(detail.trackIds, detail.index ?? 0);
            } else if (detail.trackId) {
                this.$wire.loadTrack(detail.trackId);
            }
        },

        confirmReplace() {
            this.showReplaceModal = false;
            this._executePlayTrack(this.pendingPlayDetail);
            this.pendingPlayDetail = null;
        },

        cancelReplace() {
            this.showReplaceModal = false;
            this.pendingPlayDetail = null;
        },

        onAddToQueue(detail) {
            if (!detail.trackId) {
                return;
            }

            const isCurrent = this.$wire.currentTrackId === detail.trackId;
            const alreadyQueued = this.$wire.queue.includes(detail.trackId);

            if (isCurrent || alreadyQueued) {
                this.toast(this.$wire.messages.alreadyQueued, 'alert-info');
                return;
            }

            this.$wire.extendQueue([detail.trackId]);
            this.toast(this.$wire.messages.addedToQueue, 'alert-success');
        },

        toast(title, css) {
            this.$dispatch('mary-toast', { toast: { title, css, timeout: 2500, noProgress: true } });
        },

        onLoad(detail) {
            const audio = this.$refs.audio;

            this.trackId = detail.trackId ?? null;
            this.trackTitle = detail.title || '';
            this.trackArtist = detail.artist || '';
            this.trackAlbum = detail.album || '';
            this.coverArtUrl = detail.coverArtUrl || null;
            this.favorited = !!detail.favorited;
            this.hasTrack = true;
            this.currentTime = 0;
            this.duration = 0;
            this.bufferedPercent = 0;
            this._playedSeconds = 0;
            this._lastTime = 0;
            this._playCounted = false;

            // Cleared before the new envelope lands so a track without one can
            // never show the previous track's waveform for a frame.
            this.peaks = [];

            // onEnded may already have started this stream to skip the server
            // round-trip; reassigning src would restart it and reopen the gap.
            // An early start that failed is retried so onAudioError can report it.
            const alreadyLoaded = this._advancing && !audio.error && audio.src === new URL(detail.url, location.href).href;
            this._advancing = false;

            if (alreadyLoaded) {
                this.duration = audio.duration || 0;
            } else {
                audio.src = detail.url;
            }

            audio.volume = this.isMuted ? 0 : this.volume / 100;

            if (detail.autoplay !== false && !(alreadyLoaded && !audio.paused)) {
                audio.play().then(() => {
                    this.isPlaying = true;
                }).catch(() => {
                    // Autoplay blocked by browser policy - user must click play
                    this.isPlaying = false;
                });
            }

            this.parseLyrics(detail.lyrics || null);
            this.setPeaks(detail.peaks);
            this.updateMediaMetadata();
            this.syncQueueState();
        },

        /*
         * Waveform
         *
         * The envelope is a fixed-length list of 0-100 magnitudes, computed once
         * server-side when the track's metadata was extracted. Painted on demand
         * rather than on an animation frame: timeupdate and progress are the only
         * things that change it, and both fire far slower than the display.
         */

        setPeaks(peaks) {
            this.peaks = Array.isArray(peaks) ? peaks.map(Number) : [];

            // The canvas has no size until the sheet is laid out, so the first
            // paint has to wait for it to exist and be measurable.
            this.$nextTick(() => this.paintWaveforms());
        },

        get hasPeaks() {
            return this.peaks.length > 1;
        },

        paintWaveforms() {
            if (! this.hasPeaks) {
                return;
            }

            // Every waveform surface in the document, so adding a second one
            // (a track row, a queue entry) needs no change here.
            this.$root.querySelectorAll('[data-waveform]').forEach((canvas) => {
                this.paintWaveform(canvas);
            });
        },

        paintWaveform(canvas) {
            const box = canvas.getBoundingClientRect();

            // Not laid out yet, or the sheet is closed.
            if (! box.width || ! box.height) {
                return;
            }

            const ratio = window.devicePixelRatio || 1;

            // Only resize when the device pixel count actually moved;
            // assigning width/height clears the bitmap even at the same size.
            const width = Math.round(box.width * ratio);
            const height = Math.round(box.height * ratio);

            if (canvas.width !== width || canvas.height !== height) {
                canvas.width = width;
                canvas.height = height;
            }

            const context = canvas.getContext('2d');

            if (! context) {
                return;
            }

            context.setTransform(ratio, 0, 0, ratio, 0, 0);
            context.clearRect(0, 0, box.width, box.height);

            const style = getComputedStyle(canvas);
            const idle = style.getPropertyValue('--wf-idle').trim();
            const buffered = style.getPropertyValue('--wf-buffered').trim();
            const played = style.getPropertyValue('--wf-played').trim();

            // One set of bars, painted three times behind clips rather than one
            // set of marks per tone, so the played and buffered regions are
            // exactly the same bars as the idle ones and cannot drift apart.
            const bars = this.waveformBars(box.width, box.height);

            this.paintBars(context, bars, idle, Infinity);
            this.paintBars(context, bars, buffered, (this.bufferedPercent / 100) * box.width);
            this.paintBars(context, bars, played, (this.progressPercent / 100) * box.width);
        },

        /**
         * The envelope as fat, bottom-anchored bars.
         *
         * Deliberately low fidelity. The peaks are aggregated down to one bar per
         * fixed slot of screen space, so a wide waveform simply gets more bars
         * instead of finer detail, and each bar reports the loudest moment in the
         * stretch it covers rather than an average that would flatten the
         * dynamics. The floor keeps near-silence as a short dash sitting on the
         * baseline, so the row reads as continuous.
         */
        waveformBars(width, height) {
            const peaks = this.peaks;
            const slot = 11;
            const count = Math.max(1, Math.floor(width / slot));
            const floor = 2;
            const reach = height - 2;
            const bars = [];

            for (let i = 0; i < count; i++) {
                const from = Math.floor((i * peaks.length) / count);
                const to = Math.max(from + 1, Math.floor(((i + 1) * peaks.length) / count));

                let peak = 0;

                for (let j = from; j < to && j < peaks.length; j++) {
                    if (peaks[j] > peak) {
                        peak = peaks[j];
                    }
                }

                const barHeight = Math.max(floor, Math.pow(peak / 100, 1.25) * reach);

                bars.push({
                    x: i * slot,
                    width: slot * 0.58,
                    top: height - barHeight,
                    height: barHeight,
                });
            }

            return bars;
        },

        /**
         * Paint bars up to an x offset, so one pass covers a progress boundary.
         */
        paintBars(context, bars, color, limit) {
            if (! color) {
                return;
            }

            context.fillStyle = color;

            for (const bar of bars) {
                if (bar.x >= limit) {
                    break;
                }

                const radius = Math.min(bar.width / 2, bar.height / 2);

                if (typeof context.roundRect === 'function') {
                    context.beginPath();
                    context.roundRect(bar.x, bar.top, bar.width, bar.height, radius);
                    context.fill();
                } else {
                    context.fillRect(bar.x, bar.top, bar.width, bar.height);
                }
            }
        },

        onClear() {
            const audio = this.$refs.audio;

            audio.pause();
            audio.removeAttribute('src');
            audio.load();

            this.isPlaying = false;
            this.hasTrack = false;
            this.currentTime = 0;
            this.duration = 0;
            this.bufferedPercent = 0;
            this.trackId = null;
            this.favorited = false;
            this._playedSeconds = 0;
            this._lastTime = 0;
            this._playCounted = false;
            this.lyrics = [];
            this.hasLyrics = false;
            this.lyricsSynced = false;
            this.activeLyric = -1;
            this.lyricsOpen = false;
            this.trackTitle = '';
            this.trackArtist = '';
            this.trackAlbum = '';
            this.coverArtUrl = null;
            this.queueOpen = false;
            this.nowPlayingOpen = false;

            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = null;
            }
        },

        togglePlayPause(event) {
            // Leave the spacebar alone while the user is typing.
            if (event && event.target) {
                const tag = event.target.tagName.toLowerCase();
                if (tag === 'input' || tag === 'textarea' || tag === 'select') {
                    return;
                }
            }

            if (!this.hasTrack) {
                return;
            }

            const audio = this.$refs.audio;

            if (this.isPlaying) {
                audio.pause();
                this.isPlaying = false;
            } else {
                audio.play().then(() => {
                    this.isPlaying = true;
                });
            }

            if ('mediaSession' in navigator) {
                navigator.mediaSession.playbackState = this.isPlaying ? 'playing' : 'paused';
            }
        },

        onTimeUpdate() {
            const t = this.$refs.audio.currentTime;
            this.currentTime = t;

            // Accumulate real listening time; capping the delta ignores seeks.
            const delta = t - this._lastTime;
            if (delta > 0 && delta < 2) {
                this._playedSeconds += delta;
            }
            this._lastTime = t;

            if (!this._playCounted && this.trackId && this.duration > 0) {
                const threshold = Math.min(30, this.duration / 2);
                if (this._playedSeconds >= threshold) {
                    this._playCounted = true;
                    this.$wire.recordPlay(this.trackId);
                }
            }

            this.updateActiveLyric();
            this.paintWaveforms();
        },

        toggleFavoriteCurrent() {
            if (this.trackId) {
                this.$wire.toggleFavorite(this.trackId);
            }
        },

        /**
         * Parse raw lyrics. Lines with [mm:ss.xx] stamps become a synced
         * transcript; anything else is shown as plain text. LRC metadata
         * headers ([ar:], [ti:], …) are dropped.
         */
        parseLyrics(raw) {
            this.lyrics = [];
            this.lyricsSynced = false;
            this.activeLyric = -1;

            if (raw) {
                const synced = [];
                const plain = [];

                for (const line of raw.split(/\r?\n/)) {
                    const stamps = [...line.matchAll(/\[(\d+):(\d{1,2}(?:\.\d+)?)\]/g)];
                    const text = line.replace(/\[[^\]]*\]/g, '').trim();

                    if (stamps.length > 0) {
                        if (text !== '') {
                            for (const s of stamps) {
                                synced.push({ t: Number(s[1]) * 60 + parseFloat(s[2]), text });
                            }
                        }
                    } else if (text !== '' && !/^\[(ar|ti|al|by|offset|length|re|ve):/i.test(line.trim())) {
                        plain.push({ t: null, text });
                    }
                }

                if (synced.length > 0) {
                    synced.sort((a, b) => a.t - b.t);
                    this.lyrics = synced;
                    this.lyricsSynced = true;
                } else {
                    this.lyrics = plain;
                }
            }

            this.hasLyrics = this.lyrics.length > 0;
            if (!this.hasLyrics) {
                this.lyricsOpen = false;
            }
        },

        updateActiveLyric() {
            if (!this.lyricsSynced || !this.lyricsOpen) {
                return;
            }

            let idx = -1;
            for (let i = 0; i < this.lyrics.length; i++) {
                if (this.lyrics[i].t <= this.currentTime + 0.05) idx = i;
                else break;
            }

            if (idx !== this.activeLyric) {
                this.activeLyric = idx;
                this.$nextTick(() => {
                    const el = this.$root.querySelector(`[data-lyric-index="${idx}"]`);
                    el?.scrollIntoView({
                        block: 'center',
                        behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth',
                    });
                });
            }
        },

        onFavoriteChanged(detail) {
            if (detail.trackId === this.trackId) {
                this.favorited = detail.favorited;
            }
        },

        onLoadedMetadata() {
            this.duration = this.$refs.audio.duration || 0;
        },

        onProgress() {
            const audio = this.$refs.audio;

            if (audio.buffered.length === 0 || !audio.duration) {
                return;
            }

            this.bufferedPercent = (audio.buffered.end(audio.buffered.length - 1) / audio.duration) * 100;
            this.paintWaveforms();
        },

        onVolumeChange() {
            const audio = this.$refs.audio;

            this.isMuted = audio.muted;

            if (!audio.muted) {
                this.volume = Math.round(audio.volume * 100);
            }
        },

        onEnded() {
            const audio = this.$refs.audio;

            // Repeat one replays without touching the queue.
            if (this.$wire.repeat === 'one') {
                audio.currentTime = 0;
                audio.play().then(() => { this.isPlaying = true; });
                return;
            }

            this.isPlaying = false;
            this.currentTime = 0;

            // Belt-and-suspenders: also dispatch queue-running-low from client side.
            const queue = this.$wire.queue;
            const idx = queue.indexOf(this.$wire.currentTrackId);
            if (idx >= queue.length - 3) {
                this.$dispatch('queue-running-low');
            }

            const nextId = this.nextTrackId();

            if (nextId === null) {
                return;
            }

            // Nothing from the next stream may count as a play of this track,
            // and an error before player:load lands is left for onLoad's retry.
            this._playCounted = true;
            this._advancing = true;

            // Start the next stream now, on the same element so iOS keeps
            // playing with the screen locked, rather than after nextTrack()'s
            // round-trip. Its player:load then only fills in the metadata.
            audio.src = streamUrlTemplate.replace('__track__', nextId);
            audio.play().then(() => { this.isPlaying = true; }).catch(() => {});

            this.$wire.nextTrack();
        },

        /**
         * The track nextTrack() will pick, mirroring its server-side rule.
         */
        nextTrackId() {
            const queue = this.$wire.queue;
            const idx = queue.indexOf(this.$wire.currentTrackId);

            if (idx < 0) {
                return null;
            }

            if (idx < queue.length - 1) {
                return queue[idx + 1];
            }

            return this.$wire.repeat === 'all' ? queue[0] : null;
        },

        onAudioError() {
            if (this._advancing) {
                return;
            }

            this.isPlaying = false;

            if (this.trackTitle) {
                this.$dispatch('mary-toast', {
                    toast: {
                        title: 'Skipped "' + this.trackTitle + '" — file unavailable',
                        css: 'alert-warning',
                        timeout: 4000,
                        noProgress: false,
                    },
                });
            }

            if (this.hasNext) {
                this.$wire.nextTrack();
            }
        },

        seek(value) {
            const audio = this.$refs.audio;

            audio.currentTime = parseFloat(value);
            this.currentTime = audio.currentTime;
        },

        setVolume(value) {
            const vol = parseInt(value, 10);

            this.volume = vol;
            this.isMuted = vol === 0;

            const audio = this.$refs.audio;
            audio.muted = this.isMuted;
            audio.volume = vol / 100;

            clearTimeout(this._volumeTimer);
            this._volumeTimer = setTimeout(() => {
                this.$wire.setVolume(vol);
            }, 400);
        },

        toggleMute() {
            const audio = this.$refs.audio;

            if (this.isMuted) {
                this.isMuted = false;
                audio.muted = false;
                audio.volume = this.volume / 100;
            } else {
                this.isMuted = true;
                audio.muted = true;
            }
        },

        formatTime(seconds) {
            if (!seconds || isNaN(seconds) || seconds < 0) {
                return '0:00';
            }

            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);

            return `${mins}:${secs.toString().padStart(2, '0')}`;
        },
    }));

    /**
     * Appearance settings: paint the chosen theme immediately, ahead of the
     * save round-trip. Auto is the absence of data-theme, letting the pure-CSS
     * light/dark default take over.
     */
    Alpine.data('appearanceSettings', () => ({
        apply() {
            this.paint(
                this.$root.querySelector('input[name="theme"]:checked')?.value,
                this.$root.querySelector('input[name="accent"]:checked')?.value,
            );
        },

        paint(theme, accent) {
            const html = document.documentElement;

            if (theme === 'system') {
                html.removeAttribute('data-theme');
            } else if (theme) {
                html.setAttribute('data-theme', theme);
            }

            if (accent) {
                html.setAttribute('data-accent', accent);
            }
        },

        revert() {
            this.$nextTick(() => {
                this.paint(this.$wire.theme, this.$wire.accent);

                this.$dispatch('mary-toast', {
                    toast: {
                        title: 'That appearance option is not available.',
                        css: 'alert-error',
                        timeout: 4000,
                        noProgress: false,
                    },
                });
            });
        },
    }));
});
