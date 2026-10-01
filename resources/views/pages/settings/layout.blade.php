@php
    $items = array_filter([
        ['route' => 'profile.edit', 'active' => 'profile.edit', 'icon' => 'o-user', 'label' => __('Profile')],
        ['route' => 'user-password.edit', 'active' => 'user-password.edit', 'icon' => 'o-lock-closed', 'label' => __('Password')],
        \Laravel\Fortify\Features::canManageTwoFactorAuthentication()
            ? ['route' => 'two-factor.show', 'active' => 'two-factor.show', 'icon' => 'o-shield-check', 'label' => __('Two-Factor Auth')]
            : null,
        ['route' => 'appearance.edit', 'active' => 'appearance.edit', 'icon' => 'o-swatch', 'label' => __('Appearance')],
        ['route' => 'settings.linked-folders', 'active' => 'settings.linked-folders', 'icon' => 'o-folder', 'label' => __('Linked Folders')],
        auth()->user()->is_admin
            ? ['route' => 'settings.users', 'active' => 'settings.users', 'icon' => 'o-users', 'label' => __('Users')]
            : null,
    ]);
@endphp

<div class="flex flex-col gap-8 lg:flex-row">
    <nav class="w-full shrink-0 lg:w-56" aria-label="{{ __('Settings') }}">
        <ul class="flex gap-1 overflow-x-auto lg:flex-col lg:overflow-visible">
            @foreach ($items as $item)
                @php($active = request()->routeIs($item['active']))
                <li class="shrink-0">
                    <a
                        href="{{ route($item['route']) }}"
                        wire:navigate
                        @if ($active) aria-current="page" @endif
                        @class([
                            'flex items-center gap-2.5 rounded-field px-3 py-2 transition-colors duration-150 ease-out-ios',
                            'bg-primary/12 text-primary' => $active,
                            'text-base-content/75 hover:bg-fill hover:text-base-content' => ! $active,
                        ])
                    >
                        <x-mary-icon :name="$item['icon']" class="size-4 shrink-0" />
                        <span @class(['text-subhead whitespace-nowrap', 'font-medium' => $active])>{{ $item['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="min-w-0 flex-1">
        @if (($heading ?? null) || ($subheading ?? null))
            <div class="mb-6">
                @if ($heading ?? null)
                    <h2 class="text-title-2">{{ $heading }}</h2>
                @endif
                @if ($subheading ?? null)
                    <p class="mt-1 text-subhead text-base-content/65">{{ $subheading }}</p>
                @endif
            </div>
        @endif

        <div class="w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</div>
