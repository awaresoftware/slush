@props(['title', 'description', 'icon' => 'o-musical-note'])

<div class="flex flex-col items-center justify-center px-6 py-20 text-center">
    <span class="mb-5 flex size-14 items-center justify-center rounded-full bg-primary/12">
        <x-mary-icon :name="$icon" class="size-7 text-primary" />
    </span>

    <h3 class="text-title-3">{{ $title }}</h3>
    <p class="mt-1 max-w-sm text-subhead text-base-content/65">{{ $description }}</p>

    @isset($action)
        <div class="mt-6">{{ $action }}</div>
    @endisset
</div>
