@props([
    'href',
    'icon' => null,
    'active' => false,
])

<a
    href="{{ $href }}"
    wire:navigate
    @if ($active) aria-current="page" @endif
    {{ $attributes->class([
        'flex items-center gap-2.5 rounded-field px-2.5 py-2 transition-colors duration-150 ease-out-ios',
        'bg-primary/12 text-primary' => $active,
        'text-base-content/75 hover:bg-fill hover:text-base-content' => ! $active,
    ]) }}
>
    @if ($icon)
        <x-mary-icon :name="($active ? 's-' : 'o-') . $icon" class="size-4 shrink-0" />
    @endif

    <span @class(['truncate text-subhead', 'font-medium' => $active])>{{ $slot }}</span>
</a>
