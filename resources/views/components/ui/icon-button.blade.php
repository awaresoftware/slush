@props([
    'icon',
    'label',
    'size' => 'md',
    'active' => false,
    'tag' => 'button',
])

@php
    $box = match ($size) {
        'sm' => 'size-8',
        'lg' => 'size-11',
        default => 'size-9',
    };

    $glyph = match ($size) {
        'sm' => 'size-4',
        'lg' => 'size-6',
        default => 'size-5',
    };
@endphp

<{{ $tag }}
    @if ($tag === 'button' && ! $attributes->has('type')) type="button" @endif
    aria-label="{{ $label }}"
    {{ $attributes->class([
        'inline-flex shrink-0 items-center justify-center rounded-full transition-colors duration-150 ease-out-ios',
        'hover:bg-fill disabled:pointer-events-none disabled:opacity-40',
        $box,
        'text-primary' => $active,
        'text-base-content/65 hover:text-base-content' => ! $active,
    ]) }}
>
    <x-mary-icon :name="($active ? 's-' : 'o-') . $icon" class="{{ $glyph }}" />
</{{ $tag }}>
