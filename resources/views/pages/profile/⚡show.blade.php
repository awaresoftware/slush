<?php

use App\Models\User;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public User $user;

    public function mount(User $user): void
    {
        $this->user = User::withCount(['tracks', 'albums', 'playlists'])->findOrFail($user->id);
    }
}; ?>

<div>
    <x-ui.page-header :title="$user->name" :hero-title="false" />

    <div class="px-4 pb-8 sm:px-6">
        <div class="flex items-center gap-5">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="artwork size-24 rounded-full object-cover">
            @else
                <span class="flex size-24 items-center justify-center rounded-full bg-primary/12 text-title-1 font-semibold text-primary">
                    {{ $user->initials() }}
                </span>
            @endif

            <div class="min-w-0">
                <h1 class="text-title-1">{{ $user->name }}</h1>
                <p class="mt-0.5 text-footnote text-base-content/65">
                    {{ __('Joined') }} {{ $user->created_at->isoFormat('MMMM YYYY') }}
                </p>
            </div>
        </div>

        @if ($user->bio)
            <p class="mt-5 max-w-prose text-body text-base-content/80">{{ $user->bio }}</p>
        @endif

        <div class="mt-8 flex flex-wrap gap-x-8 gap-y-2 text-subhead text-base-content/65">
            <span><span class="text-body-emphasized text-base-content tabular-nums">{{ $user->tracks_count }}</span> {{ Str::plural('track', $user->tracks_count) }}</span>
            <span><span class="text-body-emphasized text-base-content tabular-nums">{{ $user->albums_count }}</span> {{ Str::plural('album', $user->albums_count) }}</span>
            <span><span class="text-body-emphasized text-base-content tabular-nums">{{ $user->playlists_count }}</span> {{ Str::plural('playlist', $user->playlists_count) }}</span>
        </div>
    </div>
</div>
