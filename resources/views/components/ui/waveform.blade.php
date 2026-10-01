{{-- Waveform scrubber.

     The canvas is presentation only: the native range input sits above it and
     receives every pointer event, so click-drag-to-seek, keyboard seeking and
     the aria value text all keep working while the waveform is what you see.

     Bars are deliberately fat, rounded and low resolution — one per fixed slot
     of screen space rather than one per measured peak — so it reads as a
     suggestion of the track's shape instead of a map of it. Progress is the same
     bars in the accent colour, so there is no separate playhead to draw.

     Without an envelope — an unscanned track, a file ffmpeg could not read, or
     no ffmpeg at all — the canvas stays hidden and the two-tone bar shows
     instead, so the control is never blank. --}}
<div class="group/scrub relative flex h-12 min-w-0 flex-1 items-center">
    <canvas
        data-waveform
        data-test="waveform-canvas"
        class="absolute inset-0 z-0 size-full"
        aria-hidden="true"
        x-show="hasPeaks"
        x-cloak
    ></canvas>

    <div
        class="pointer-events-none absolute inset-0 z-0 flex items-center"
        aria-hidden="true"
        x-show="! hasPeaks"
    >
        <div class="h-1 w-full rounded-full bg-base-content/20 transition-all duration-150 group-hover/scrub:h-1.5"></div>
        <div
            class="absolute left-0 h-1 rounded-full bg-base-content/25 transition-all duration-150 group-hover/scrub:h-1.5"
            :style="`width: ${bufferedPercent}%`"
        ></div>
        <div
            class="absolute left-0 h-1 rounded-full bg-primary transition-all duration-150 group-hover/scrub:h-1.5"
            :style="`width: ${progressPercent}%`"
        ></div>
    </div>

    <input
        type="range"
        min="0"
        :max="duration || 100"
        :value="currentTime"
        x-on:input="seek($event.target.value)"
        :disabled="!hasTrack || duration === 0"
        class="slider slider-waveform absolute inset-0 z-20 w-full"
        aria-label="{{ __('Seek slider') }}"
        :aria-valuenow="Math.round(currentTime)"
        :aria-valuemax="Math.round(duration)"
        :aria-valuetext="formatTime(currentTime) + ' of ' + formatTime(duration)"
        data-test="waveform-seek"
    >
</div>
