@props(['href' => null])

@php
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-base-content']) }}
>
    {{-- A record: two concentric rings and a spindle hole. --}}
    <svg viewBox="0 0 20 20" fill="none" aria-hidden="true" class="size-5 shrink-0 text-primary">
        <circle cx="10" cy="10" r="8.25" stroke="currentColor" stroke-width="1.5" />
        <circle cx="10" cy="10" r="3.75" stroke="currentColor" stroke-width="1.25" opacity="0.55" />
        <circle cx="10" cy="10" r="1.15" fill="currentColor" />
    </svg>

    <span class="text-title-3">{{ config('app.name') }}</span>
</{{ $tag }}>
