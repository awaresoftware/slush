@props(['theme', 'accent', 'selected' => false])

<label
    class="group block cursor-pointer"
    data-test="theme-card-{{ $theme->value }}"
>
    <input
        type="radio"
        value="{{ $theme->value }}"
        class="peer sr-only"
        {{ $attributes }}
    />

    <span
        @class([
            'relative block overflow-hidden rounded-box border border-separator transition-shadow duration-200 ease-out-ios',
            'peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-primary peer-focus-visible:outline-offset-2',
            'shadow-2 ring-2 ring-primary ring-offset-2 ring-offset-base-200' => $selected,
            'shadow-1 group-hover:shadow-2' => ! $selected,
        ])
    >
        <x-ui.theme-preview :theme="$theme" :accent="$accent" />

        @if ($selected)
            <span class="absolute right-1.5 bottom-1.5 flex size-5 items-center justify-center rounded-full bg-primary text-primary-content shadow-1">
                <x-mary-icon name="s-check" class="size-3.5" />
            </span>
        @endif
    </span>

    <span class="mt-2 flex flex-col">
        <span @class(['text-body-emphasized', 'text-primary' => $selected])>{{ $theme->label() }}</span>
        <span class="text-footnote text-base-content/65">{{ $theme->schemeLabel() }}</span>
    </span>
</label>
