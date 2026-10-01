<?php

use App\Models\Track;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Upload Complete')] class extends Component {
    /** @var Collection<int, Track> */
    public Collection $uploadedTracks;

    /** @var array<int, string> */
    public array $duplicateWarnings = [];

    public function mount(): void
    {
        $trackIds = session()->pull('uploaded_track_ids', []);
        $this->duplicateWarnings = session()->pull('duplicate_warnings', []);

        $this->uploadedTracks = Track::whereIn('id', $trackIds)
            ->where('user_id', Auth::id())
            ->with('album')
            ->orderBy('title')
            ->get();
    }
}; ?>

<div>
    <x-ui.page-header
        :title="__('Upload Complete')"
        :subtitle="__('Your files have been saved. Metadata is being extracted in the background.')"
        :back="route('library.index')"
        :back-label="__('Back to Library')"
    />

    <div class="px-4 pb-8 sm:px-6">
        @foreach ($duplicateWarnings as $filename)
            <div role="alert" class="alert alert-warning mb-4 rounded-box">
                <x-mary-icon name="o-exclamation-triangle" class="size-5 shrink-0" />
                <div>
                    <p class="text-body-emphasized">{{ __('Duplicate detected') }}</p>
                    <p class="text-subhead">{{ $filename }} was already in your library. A copy has been uploaded.</p>
                </div>
            </div>
        @endforeach

        @if ($uploadedTracks->isEmpty())
            <div role="alert" class="alert rounded-box">{{ __('No recent uploads') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-separator text-left">
                            <th class="pb-2 pr-4 text-caption text-base-content/45">{{ __('Title') }}</th>
                            <th class="hidden pb-2 pr-4 text-caption text-base-content/45 sm:table-cell">{{ __('Artist') }}</th>
                            <th class="hidden pb-2 pr-4 text-caption text-base-content/45 lg:table-cell">{{ __('Album') }}</th>
                            <th class="pb-2 text-right text-caption text-base-content/45">{{ __('Duration') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($uploadedTracks as $track)
                            <tr wire:key="{{ $track->id }}" class="border-b border-separator transition-colors duration-150 hover:bg-fill">
                                <td class="py-2 pr-4 text-body-emphasized">{{ $track->title }}</td>
                                <td class="hidden py-2 pr-4 text-body text-base-content/65 sm:table-cell">{{ $track->artist ?? 'Unknown' }}</td>
                                <td class="hidden py-2 pr-4 text-body text-base-content/65 lg:table-cell">{{ $track->album?->title ?? 'No album' }}</td>
                                <td class="py-2 text-right">
                                    @if ($track->duration === null)
                                        <x-mary-badge value="Processing..." class="badge-warning badge-sm" />
                                    @else
                                        <time class="text-footnote text-base-content/65 tabular-nums">
                                            {{ intdiv($track->duration, 60) }}:{{ str_pad($track->duration % 60, 2, '0', STR_PAD_LEFT) }}
                                        </time>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="mt-8">
            <x-mary-button label="{{ __('Go to Library') }}" link="{{ route('library.index') }}" class="btn-primary btn-capsule" wire:navigate />
        </div>
    </div>
</div>
