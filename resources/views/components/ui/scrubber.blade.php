{{-- The visible track is drawn by the bars; the native range on top keeps
     keyboard and assistive-tech behaviour. Buffered range sits behind progress. --}}
<div class="group/scrub relative flex h-4 min-w-0 flex-1 items-center">
    <input
        type="range"
        min="0"
        :max="duration || 100"
        :value="currentTime"
        x-on:input="seek($event.target.value)"
        :disabled="!hasTrack || duration === 0"
        class="slider absolute inset-0 z-20 w-full"
        aria-label="{{ __('Seek slider') }}"
        :aria-valuenow="Math.round(currentTime)"
        :aria-valuemax="Math.round(duration)"
        :aria-valuetext="formatTime(currentTime) + ' of ' + formatTime(duration)"
    >

    <div class="slider-track pointer-events-none absolute inset-x-0 z-0 h-1 rounded-full bg-base-content/20 transition-all duration-150 group-hover/scrub:h-1.5"></div>
    <div class="pointer-events-none absolute left-0 z-0 h-1 rounded-full bg-base-content/25 transition-all duration-150 group-hover/scrub:h-1.5" :style="`width: ${bufferedPercent}%`"></div>
    <div class="pointer-events-none absolute left-0 z-0 h-1 rounded-full bg-primary transition-all duration-150 group-hover/scrub:h-1.5" :style="`width: ${progressPercent}%`"></div>
</div>
