<x-layouts::app :title="'Edit: ' . $playlist->name">
    <div>
        <x-ui.page-header :title="__('Edit Playlist')" :subtitle="__('Update your playlist details')" :back="route('playlists.show', $playlist)" :back-label="$playlist->name" />

        <div class="max-w-xl px-4 pb-8 sm:px-6">
            <form method="POST" action="{{ route('playlists.update', $playlist) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <x-mary-input label="{{ __('Name') }}" name="name" value="{{ old('name', $playlist->name) }}" placeholder="{{ __('My Playlist') }}" autofocus />

                <x-mary-textarea label="{{ __('Description (optional)') }}" name="description" placeholder="{{ __('Describe your playlist...') }}" rows="3">{{ old('description', $playlist->description) }}</x-mary-textarea>

                <div class="flex items-center gap-2 pt-1">
                    <x-mary-button type="submit" label="{{ __('Save Changes') }}" class="btn-primary btn-capsule" />
                    <x-mary-button label="{{ __('Cancel') }}" class="btn-capsule bg-base-300 text-base-content" link="{{ route('playlists.show', $playlist) }}" wire:navigate />
                </div>
            </form>

            <div class="mt-10 rounded-box border border-error/30 bg-error/5 p-5">
                <h2 class="text-title-3 text-error">{{ __('Danger Zone') }}</h2>
                <p class="mt-1 text-subhead text-base-content/65">{{ __('Deleting a playlist is permanent and cannot be undone.') }}</p>

                <form method="POST" action="{{ route('playlists.destroy', $playlist) }}" class="mt-4">
                    @csrf
                    @method('DELETE')
                    <x-mary-button type="submit" label="{{ __('Delete Playlist') }}" class="btn-error btn-capsule btn-sm" onclick="return confirm('{{ __('Are you sure you want to delete this playlist?') }}')" />
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>
