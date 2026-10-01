@props(['accent', 'selected' => false])

<label
    class="group relative inline-flex cursor-pointer items-center justify-center"
    data-test="accent-dot-{{ $accent->value }}"
>
    <input
        type="radio"
        value="{{ $accent->value }}"
        class="peer sr-only"
        aria-label="{{ $accent->label() }}"
        {{ $attributes }}
    />

    <span
        data-accent="{{ $accent->value }}"
        @class([
            'accent-dot flex size-7 items-center justify-center rounded-full transition-transform duration-150 ease-out-ios',
            'peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-primary peer-focus-visible:outline-offset-2',
            'ring-2 ring-base-content/40 ring-offset-2 ring-offset-base-100' => $selected,
            'group-hover:scale-110' => ! $selected,
        ])
    >
        @if ($selected)
            <x-mary-icon name="s-check" class="size-4 text-primary-content" />
        @endif
    </span>
</label>
