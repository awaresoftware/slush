<div>
    <x-mary-modal wire:model="showModal" title="Re-link Track" box-class="max-w-sm">
        <div class="space-y-4">
            <div>
                <p class="text-xs text-base-content/60 mb-1">{{ __('Current path') }}</p>
                <p class="text-sm font-mono bg-base-200 rounded px-3 py-2 truncate">{{ $oldPath }}</p>
            </div>

            <x-mary-input
                wire:model="newPath"
                :label="__('New file path')"
                type="text"
                placeholder="/path/to/track.mp3"
                autocomplete="off"
            />

            @error('newPath')
                <p class="text-sm text-error">{{ $message }}</p>
            @enderror

            @if ($hashMismatch)
                <div class="alert alert-warning text-sm">
                    <x-mary-icon name="o-exclamation-triangle" class="size-4 shrink-0" />
                    <span>{{ __("File hash doesn't match original. The file content may have changed.") }}</span>
                </div>

                <x-mary-button
                    class="btn-warning btn-sm w-full"
                    wire:click="confirmMismatch"
                    wire:loading.attr="disabled"
                >
                    {{ __('Re-link Anyway') }}
                </x-mary-button>
            @else
                <x-mary-button
                    class="btn-primary w-full"
                    wire:click="relink"
                    wire:loading.attr="disabled"
                >
                    {{ __('Re-link') }}
                </x-mary-button>
            @endif
        </div>
    </x-mary-modal>
</div>
