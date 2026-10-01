@php
    $user = auth()->user();
@endphp

<x-mary-dropdown top right class="!w-full">
    <x-slot:trigger class="!w-full">
        <button
            type="button"
            class="flex !w-full items-center gap-2.5 rounded-field px-2 py-2 text-left transition-colors duration-150 ease-out-ios hover:bg-fill"
            data-test="sidebar-menu-button"
        >
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="" class="artwork size-8 shrink-0 rounded-full object-cover">
            @else
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/12 text-footnote font-semibold text-primary">
                    {{ $user->initials() }}
                </span>
            @endif

            <span class="min-w-0 flex-1">
                <span class="block truncate text-subhead font-medium">{{ $user->name }}</span>
                <span class="block truncate text-footnote text-base-content/65">{{ $user->email }}</span>
            </span>

            <x-mary-icon name="o-chevron-up-down" class="size-4 shrink-0 text-base-content/35" />
        </button>
    </x-slot:trigger>

    <x-mary-menu-item title="{{ __('Profile') }}" icon="o-user" link="{{ route('profile.show', $user) }}" />
    <x-mary-menu-item title="{{ __('Settings') }}" icon="o-cog-6-tooth" link="{{ route('profile.edit') }}" />

    <li class="divider my-0"></li>

    <li>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="w-full text-left" data-test="logout-button">
                {{ __('Log Out') }}
            </button>
        </form>
    </li>
</x-mary-dropdown>
