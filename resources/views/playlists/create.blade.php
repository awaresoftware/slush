<x-layouts::app title="New Playlist">
    <div>
        <x-ui.page-header :title="__('New Playlist')" :subtitle="__('Create a new playlist to organize your music')" :back="route('playlists.index')" :back-label="__('My Playlists')" />

        <div class="max-w-xl px-4 pb-8 sm:px-6">
            <form method="POST" action="{{ route('playlists.store') }}" class="space-y-5">
                @csrf

                <x-mary-input label="{{ __('Name') }}" name="name" value="{{ old('name') }}" placeholder="{{ __('My Playlist') }}" autofocus />

                <x-mary-textarea label="{{ __('Description (optional)') }}" name="description" placeholder="{{ __('Describe your playlist...') }}" rows="3">{{ old('description') }}</x-mary-textarea>

                <div class="flex items-center gap-2 pt-1">
                    <x-mary-button type="submit" label="{{ __('Create Playlist') }}" class="btn-primary btn-capsule" />
                    <x-mary-button label="{{ __('Cancel') }}" class="btn-capsule bg-base-300 text-base-content" link="{{ route('playlists.index') }}" wire:navigate />
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
