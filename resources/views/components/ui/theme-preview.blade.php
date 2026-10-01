@props(['theme', 'accent'])

<span class="relative block aspect-[4/3] w-full overflow-hidden">
    @if ($theme->isSystem())
        {{-- Auto resolves at render time to Porcelain or Graphite, so show both,
             split on the diagonal the way macOS does. --}}
        <x-ui.theme-preview-body
            :theme-value="\App\Enums\Theme::Porcelain->value"
            :accent="$accent"
            class="absolute inset-0"
            style="clip-path: polygon(0 0, 100% 0, 0 100%)"
        />
        <x-ui.theme-preview-body
            :theme-value="\App\Enums\Theme::Graphite->value"
            :accent="$accent"
            class="absolute inset-0"
            style="clip-path: polygon(100% 0, 100% 100%, 0 100%)"
        />
        <span
            class="pointer-events-none absolute inset-0"
            style="background: linear-gradient(to bottom left, transparent calc(50% - 0.5px), var(--color-separator) calc(50% - 0.5px), var(--color-separator) calc(50% + 0.5px), transparent calc(50% + 0.5px))"
        ></span>
    @else
        <x-ui.theme-preview-body :theme-value="$theme->value" :accent="$accent" class="absolute inset-0" />
    @endif
</span>
