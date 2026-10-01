@props(['item', 'play' => false])

@if ($item['cover'])
    <img src="{{ $item['cover'] }}" alt="" class="artwork size-8 shrink-0 rounded-art object-cover">
@else
    <span class="artwork flex size-8 shrink-0 items-center justify-center rounded-art bg-base-300">
        <x-mary-icon :name="$item['icon']" class="size-4 text-base-content/45" />
    </span>
@endif

<span class="min-w-0 flex-1">
    <span class="block truncate text-body">{{ $item['label'] }}</span>
    <span class="block truncate text-footnote text-base-content/65">{{ $item['sub'] }}</span>
</span>

@if ($play)
    <x-mary-icon name="s-play" class="size-3.5 shrink-0 text-base-content/35" />
@endif
