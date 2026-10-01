<div>
    <x-mary-modal wire:model="showModal" title="Upload Music" box-class="max-w-lg">
        @if ($uploadComplete)
            <div class="flex flex-col items-center py-4 text-center gap-4">
                <x-mary-icon name="o-check-circle" class="size-16 text-success" />
                <h3 class="text-lg font-semibold">Upload complete!</h3>

                @if (count($duplicateWarnings) > 0)
                    <div class="w-full space-y-2">
                        @foreach ($duplicateWarnings as $filename)
                            <div role="alert" class="alert alert-warning">
                                <x-mary-icon name="o-exclamation-triangle" class="size-5" />
                                <span>Duplicate detected: <strong>{{ $filename }}</strong> has already been uploaded.</span>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="flex gap-3 mt-2">
                    <a href="{{ route('library.upload.summary') }}" wire:navigate class="btn btn-primary">
                        View Uploads
                    </a>
                    <button type="button" wire:click="closeModal" class="btn btn-ghost">
                        Close
                    </button>
                </div>
            </div>
        @else
            <form wire:submit="save" class="space-y-4">
                <div
                    x-data="{ progress: 0, uploading: false, dragging: false }"
                    x-on:livewire-upload-start="uploading = true"
                    x-on:livewire-upload-finish="uploading = false; progress = 0"
                    x-on:livewire-upload-error="uploading = false"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                >
                    {{-- Dashed well that tints with the accent while a file hovers over it. --}}
                    <label
                        x-on:dragover.prevent="dragging = true"
                        x-on:dragleave.prevent="dragging = false"
                        x-on:drop="dragging = false"
                        class="flex cursor-pointer flex-col items-center justify-center rounded-box border border-dashed px-6 py-10 text-center transition-colors duration-150 ease-out-ios"
                        :class="dragging ? 'border-primary bg-primary/12' : 'border-separator bg-base-200 hover:bg-base-300'"
                    >
                        <x-mary-icon name="o-arrow-up-tray" class="size-7 text-base-content/45" />
                        <span class="mt-3 text-body-emphasized">{{ __('Choose files or drag them here') }}</span>
                        <span class="mt-1 text-footnote text-base-content/65">MP3, WAV, FLAC, M4A, AAC, OGG</span>

                        <input
                            type="file"
                            wire:model="audioFiles"
                            multiple
                            accept=".mp3,.wav,.flac,.m4a,.aac,.ogg"
                            class="sr-only"
                        >
                    </label>

                    @if (count($audioFiles) > 0)
                        <ul class="mt-3 divide-y divide-separator rounded-box bg-base-200">
                            @foreach ($audioFiles as $file)
                                <li class="flex items-center gap-3 px-3 py-2">
                                    <x-mary-icon name="o-musical-note" class="size-4 shrink-0 text-base-content/45" />
                                    <span class="min-w-0 flex-1 truncate text-subhead">{{ $file->getClientOriginalName() }}</span>
                                    <span class="shrink-0 text-footnote text-base-content/65 tabular-nums">
                                        {{ number_format($file->getSize() / 1048576, 1) }} MB
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div x-show="uploading" class="mt-3 space-y-1">
                        <p class="text-footnote text-base-content/65">{{ __('Uploading...') }} <span x-text="progress + '%'"></span></p>
                        <progress class="progress progress-primary w-full" :value="progress" max="100"></progress>
                    </div>
                </div>

                @error('audioFiles.*')
                    <p class="text-footnote text-error">{{ $message }}</p>
                @enderror

                <x-mary-choices-offline
                    label="Album (optional)"
                    wire:model="selectedAlbumId"
                    :options="$this->userAlbums"
                    option-value="id"
                    option-label="title"
                    searchable
                    single
                    placeholder="No album"
                />

                <div class="flex justify-end">
                    <x-mary-button
                        type="submit"
                        class="btn-primary"
                        label="Upload"
                        wire:loading.attr="disabled"
                        wire:target="save"
                    />
                </div>
            </form>
        @endif
    </x-mary-modal>
</div>
