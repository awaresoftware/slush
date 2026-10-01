@props(['themeValue', 'accent'])

{{-- A miniature of the app shell, drawn in the palette it is previewing.
     data-theme on this element makes every base/primary token below resolve
     to that theme, so the preview can never drift from the real one. --}}
<span
    @if ($themeValue) data-theme="{{ $themeValue }}" @endif
    data-accent="{{ $accent }}"
    {{ $attributes->merge(['class' => 'flex size-full flex-col bg-base-200']) }}
>
    <span class="flex min-h-0 flex-1">
        {{-- Sidebar --}}
        <span class="flex w-[32%] flex-col gap-[3px] bg-base-100 p-[5px]">
            <span class="mb-[3px] h-[3px] w-3/5 rounded-full bg-base-content/30"></span>
            <span class="h-[5px] w-full rounded-[2px] bg-primary"></span>
            <span class="h-[3px] w-4/5 rounded-full bg-base-content/20"></span>
            <span class="h-[3px] w-3/5 rounded-full bg-base-content/20"></span>
        </span>

        {{-- Content --}}
        <span class="flex flex-1 flex-col gap-[5px] p-[5px]">
            <span class="h-[5px] w-1/2 rounded-full bg-base-content/35"></span>
            <span class="flex gap-[4px]">
                <span class="aspect-square w-1/3 rounded-[3px] bg-base-300"></span>
                <span class="aspect-square w-1/3 rounded-[3px] bg-base-300"></span>
                <span class="aspect-square w-1/3 rounded-[3px] bg-base-300"></span>
            </span>
        </span>
    </span>

    {{-- Player bar --}}
    <span class="flex items-center gap-[4px] border-t border-separator bg-base-100 px-[5px] py-[4px]">
        <span class="size-[7px] shrink-0 rounded-full bg-primary"></span>
        <span class="h-[2px] flex-1 rounded-full bg-base-content/20"></span>
    </span>
</span>
