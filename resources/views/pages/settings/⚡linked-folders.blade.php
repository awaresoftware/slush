<?php

use App\Enums\FolderStatus;
use App\Jobs\ScanLinkedFolder;
use App\Models\Album;
use App\Models\LinkedFolder;
use App\Models\PlaylistTrack;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Mary\Traits\Toast;

new class extends Component {
    use Toast;

    public string $path = '';

    public string $name = '';

    public bool $showRemoveModal = false;

    public ?int $removingFolderId = null;

    public bool $showUpdatePathModal = false;

    public ?int $updatingFolderId = null;

    public string $newFolderPath = '';

    public string $oldFolderPath = '';

    /** @var list<int> */
    public array $scanningFolderIds = [];

    /**
     * Get all linked folders for the current user.
     */
    #[Computed]
    public function folders(): \Illuminate\Database\Eloquent\Collection
    {
        return LinkedFolder::where('user_id', auth()->id())
            ->withCount('tracks')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get the folder being removed (with track count).
     */
    #[Computed]
    public function folderBeingRemoved(): ?LinkedFolder
    {
        if (! $this->removingFolderId) {
            return null;
        }

        return LinkedFolder::where('user_id', auth()->id())
            ->withCount('tracks')
            ->find($this->removingFolderId);
    }

    /**
     * Count the distinct playlists affected by removing the current folder.
     */
    #[Computed]
    public function affectedPlaylistCount(): int
    {
        if (! $this->removingFolderId) {
            return 0;
        }

        return PlaylistTrack::query()
            ->whereHas('track', fn ($q) => $q->where('linked_folder_id', $this->removingFolderId))
            ->distinct('playlist_id')
            ->count('playlist_id');
    }

    /**
     * Link a new folder to the user's library.
     */
    public function linkFolder(): void
    {
        $this->validate([
            'path' => ['required', 'string'],
        ]);

        $normalisedPath = rtrim(str_replace('\\', '/', $this->path), '/');

        if (! is_dir($normalisedPath) || ! is_readable($normalisedPath)) {
            $this->addError('path', 'The path must be a readable directory.');

            return;
        }

        $folder = LinkedFolder::create([
            'user_id' => auth()->id(),
            'path' => $normalisedPath,
            'name' => $this->name ?: basename($normalisedPath),
            'status' => FolderStatus::Active,
        ]);

        ScanLinkedFolder::dispatch($folder);

        $this->reset('path', 'name');
        unset($this->folders);

        $this->success(__('Folder linked and scan queued.'));
    }

    /**
     * Rescan an existing linked folder.
     */
    public function rescan(int $folderId): void
    {
        $folder = LinkedFolder::where('user_id', auth()->id())->findOrFail($folderId);

        ScanLinkedFolder::dispatch($folder);

        $this->scanningFolderIds[] = $folderId;

        $this->info(__('Rescan queued.'));
    }

    /**
     * Rename a linked folder inline.
     */
    public function editName(int $folderId, string $newName): void
    {
        $folder = LinkedFolder::where('user_id', auth()->id())->findOrFail($folderId);
        $folder->update(['name' => $newName]);

        unset($this->folders);

        $this->success(__('Folder name updated.'));
    }

    /**
     * Open the remove confirmation modal.
     */
    public function confirmRemove(int $folderId): void
    {
        $this->removingFolderId = $folderId;
        $this->showRemoveModal = true;
    }

    /**
     * Remove the folder after confirming it is not currently scanning.
     */
    public function removeFolder(): void
    {
        $folder = LinkedFolder::where('user_id', auth()->id())->find($this->removingFolderId);

        if (! $folder || $folder->status === FolderStatus::Scanning) {
            return;
        }

        $trackCount = $folder->tracks()->count();
        $folder->delete();

        Album::where('user_id', auth()->id())
            ->whereDoesntHave('tracks')
            ->delete();

        $this->showRemoveModal = false;
        $this->removingFolderId = null;
        unset($this->folders);

        $this->success(__(':count track(s) removed along with the folder.', ['count' => $trackCount]));
    }

    /**
     * Open the update path modal for a folder.
     */
    public function openUpdatePathModal(int $folderId): void
    {
        $folder = LinkedFolder::where('user_id', auth()->id())->findOrFail($folderId);

        $this->updatingFolderId = $folderId;
        $this->oldFolderPath = $folder->path;
        $this->newFolderPath = '';
        $this->showUpdatePathModal = true;
    }

    /**
     * Bulk-update all track paths in a folder by replacing the old path prefix.
     */
    public function updateFolderPath(): void
    {
        $this->validate([
            'newFolderPath' => ['required', 'string'],
        ]);

        $normalisedNew = rtrim(str_replace('\\', '/', $this->newFolderPath), '/');

        if (! is_dir($normalisedNew)) {
            $this->addError('newFolderPath', 'The path must be an existing directory.');

            return;
        }

        $folder = LinkedFolder::where('user_id', auth()->id())->findOrFail($this->updatingFolderId);

        $oldPath = rtrim($folder->path, '/');
        $oldPathLength = strlen($oldPath);

        foreach ($folder->tracks()->get() as $track) {
            $newTrackPath = $normalisedNew.substr($track->file_path, $oldPathLength);
            $track->update([
                'file_path' => $newTrackPath,
                'is_available' => file_exists($newTrackPath),
            ]);
        }

        $folder->update(['path' => $normalisedNew]);

        ScanLinkedFolder::dispatch($folder);

        $this->showUpdatePathModal = false;
        $this->updatingFolderId = null;
        unset($this->folders);

        $this->success(__('Folder path updated and rescan queued.'));
    }

    /**
     * Poll for completed scans and show completion toast.
     */
    public function pollFolders(): void
    {
        if (empty($this->scanningFolderIds)) {
            return;
        }

        $completedIds = [];

        foreach ($this->scanningFolderIds as $folderId) {
            $folder = LinkedFolder::where('user_id', auth()->id())
                ->where('id', $folderId)
                ->first();

            if (! $folder || $folder->status !== FolderStatus::Scanning) {
                $completedIds[] = $folderId;

                if ($folder) {
                    $message = __('Folder scanned: :count tracks found, :unavailable unavailable', [
                        'count' => $folder->track_count,
                        'unavailable' => $folder->unavailable_count,
                    ]);

                    $this->success($message);
                }
            }
        }

        $this->scanningFolderIds = array_values(array_diff($this->scanningFolderIds, $completedIds));
        unset($this->folders);
    }
}; ?>

<section class="w-full px-4 pt-3 sm:px-6">
    @include('partials.settings-heading')

    <x-pages::settings.layout
        :heading="__('Linked Folders')"
        :subheading="__('Link local folders to your music library')"
    >
        <div
            x-data="{
                browseUrl: '{{ route('browse-directories') }}',
                browserOpen: false,
                browserTarget: 'path',
                currentPath: '',
                parentPath: null,
                directories: [],
                loading: false,
                error: null,

                openBrowser(target) {
                    this.browserTarget = target;
                    this.directories = [];
                    this.currentPath = '';
                    this.parentPath = null;
                    this.error = null;
                    this.browserOpen = true;
                    this.fetchDirectories(null);
                },

                async fetchDirectories(path) {
                    this.loading = true;
                    this.error = null;

                    try {
                        const url = new URL(this.browseUrl);
                        if (path) {
                            url.searchParams.set('path', path);
                        }

                        const response = await fetch(url.toString(), {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });

                        if (! response.ok) {
                            const data = await response.json();
                            this.error = data.message ?? '{{ __('Failed to load directory.') }}';
                        } else {
                            const data = await response.json();
                            this.currentPath = data.current_path;
                            this.parentPath = data.parent_path;
                            this.directories = data.directories;
                        }
                    } catch (e) {
                        this.error = '{{ __('Failed to load directory.') }}';
                    } finally {
                        this.loading = false;
                    }
                },

                navigateTo(path) {
                    this.fetchDirectories(path);
                },

                goUp() {
                    if (this.parentPath) {
                        this.fetchDirectories(this.parentPath);
                    }
                },

                selectFolder() {
                    $wire.set(this.browserTarget, this.currentPath);
                    this.browserOpen = false;
                }
            }"
        >
            {{-- Add folder form --}}
            <form wire:submit="linkFolder" class="mt-6 space-y-4">
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <x-mary-input
                            wire:model="path"
                            :label="__('Folder path')"
                            type="text"
                            placeholder="/Users/you/Music"
                            autocomplete="off"
                        />
                    </div>

                    <x-mary-button
                        class="btn-ghost"
                        type="button"
                        @click="openBrowser('path')"
                    >
                        {{ __('Browse') }}
                    </x-mary-button>
                </div>

                @error('path')
                    <p class="text-sm text-error">{{ $message }}</p>
                @enderror

                <x-mary-input
                    wire:model="name"
                    :label="__('Display name (optional)')"
                    type="text"
                    placeholder="{{ __('Leave blank to use folder name') }}"
                    autocomplete="off"
                />

                <x-mary-button
                    class="btn-primary"
                    type="submit"
                    wire:loading.attr="disabled"
                >
                    {{ __('Link Folder') }}
                </x-mary-button>
            </form>

            {{-- Folder list --}}
            <div class="mt-8">
                @forelse ($this->folders as $folder)
                    <div
                        class="flex items-center justify-between py-3 border-b border-base-content/10"
                        wire:key="folder-{{ $folder->id }}"
                    >
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate">{{ $folder->name }}</p>
                            <p class="text-xs text-base-content/60 truncate">{{ $folder->path }}</p>

                            <div class="mt-1 flex items-center gap-2">
                                @if ($folder->status === FolderStatus::Scanning)
                                    <x-mary-badge class="badge-warning badge-sm">
                                        <x-mary-loading class="loading-spinner loading-xs mr-1" />
                                        @if ($folder->total_count > 0)
                                            {{ __('Scanning: :scanned / :total', ['scanned' => number_format($folder->scanned_count), 'total' => number_format($folder->total_count)]) }}
                                        @else
                                            {{ __('Scanning...') }}
                                        @endif
                                    </x-mary-badge>
                                @elseif ($folder->status === FolderStatus::Error)
                                    <x-mary-badge value="{{ __('Error') }}" class="px-2 badge-error badge-sm" />
                                @else
                                    <x-mary-badge value="{{ __('Active') }}" class="px-2 badge-success badge-sm" />
                                @endif

                                <span class="text-xs text-base-content/60">
                                    {{ $folder->tracks_count }} {{ __('tracks') }}
                                </span>

                                @if ($folder->unavailable_count > 0)
                                    <x-mary-badge value="{{ $folder->unavailable_count }} {{ __('unavailable') }}" class="badge-warning badge-sm" />
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2 ml-4 shrink-0">
                            <x-mary-button
                                class="btn-ghost btn-sm"
                                wire:click="openUpdatePathModal({{ $folder->id }})"
                            >
                                {{ __('Update Path') }}
                            </x-mary-button>

                            <x-mary-button
                                class="btn-ghost btn-sm"
                                wire:click="rescan({{ $folder->id }})"
                            >
                                {{ __('Rescan') }}
                            </x-mary-button>

                            <x-mary-button
                                class="btn-error btn-sm"
                                wire:click="confirmRemove({{ $folder->id }})"
                                :disabled="$folder->status === \App\Enums\FolderStatus::Scanning"
                            >
                                {{ __('Remove') }}
                            </x-mary-button>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-base-content/60">
                        <p class="text-sm font-medium">{{ __('No folders linked') }}</p>
                        <p class="text-xs mt-1">{{ __('Link a local folder above to start scanning your music.') }}</p>
                    </div>
                @endforelse
            </div>

            {{-- Poll while any folder is scanning --}}
            @if ($this->folders->contains('status', FolderStatus::Scanning) || !empty($scanningFolderIds))
                <div wire:poll.3s="pollFolders"></div>
            @endif

            {{-- Remove confirmation modal --}}
            <x-mary-modal wire:model="showRemoveModal" :title="__('Remove Folder')">
                @if ($this->folderBeingRemoved)
                    <p class="text-sm">
                        {{ __('Are you sure you want to remove') }}
                        <strong>{{ $this->folderBeingRemoved->name }}</strong>?
                    </p>

                    <p class="mt-2 text-sm text-base-content/70">
                        {{ __('This will remove') }}
                        <strong>{{ $this->folderBeingRemoved->tracks_count }}</strong>
                        {{ __('track(s) from your library.') }}
                        @if ($this->affectedPlaylistCount > 0)
                            {{ __('It will also affect') }}
                            <strong>{{ $this->affectedPlaylistCount }}</strong>
                            {{ __('playlist(s).') }}
                        @endif
                    </p>
                @endif

                <x-slot:actions>
                    <x-mary-button
                        wire:click="$set('showRemoveModal', false)"
                    >
                        {{ __('Cancel') }}
                    </x-mary-button>

                    <x-mary-button
                        class="btn-error"
                        wire:click="removeFolder"
                        :disabled="$this->folderBeingRemoved?->status === \App\Enums\FolderStatus::Scanning"
                    >
                        {{ __('Remove') }}
                    </x-mary-button>
                </x-slot:actions>
            </x-mary-modal>

            {{-- Update folder path modal --}}
            <x-mary-modal wire:model="showUpdatePathModal" :title="__('Update Folder Path')">
                <div class="space-y-4">
                    <div>
                        <p class="text-xs text-base-content/60 mb-1">{{ __('Current path') }}</p>
                        <p class="text-sm font-mono bg-base-200 rounded px-3 py-2 truncate">{{ $oldFolderPath }}</p>
                    </div>

                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <x-mary-input
                                wire:model="newFolderPath"
                                :label="__('New folder path')"
                                type="text"
                                placeholder="/new/path/to/music"
                                autocomplete="off"
                            />
                        </div>

                        <x-mary-button
                            class="btn-ghost"
                            type="button"
                            @click="openBrowser('newFolderPath')"
                        >
                            {{ __('Browse') }}
                        </x-mary-button>
                    </div>

                    @error('newFolderPath')
                        <p class="text-sm text-error">{{ $message }}</p>
                    @enderror

                    <p class="text-xs text-base-content/60">
                        {{ __('All track paths in this folder will be updated to use the new path prefix.') }}
                    </p>
                </div>

                <x-slot:actions>
                    <x-mary-button
                        wire:click="$set('showUpdatePathModal', false)"
                    >
                        {{ __('Cancel') }}
                    </x-mary-button>

                    <x-mary-button
                        class="btn-primary"
                        wire:click="updateFolderPath"
                        wire:loading.attr="disabled"
                    >
                        {{ __('Update Path') }}
                    </x-mary-button>
                </x-slot:actions>
            </x-mary-modal>

            {{-- Directory browser modal --}}
            <div
                x-show="browserOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center"
                @keydown.escape.window="browserOpen = false"
            >
                {{-- Backdrop --}}
                <div
                    class="absolute inset-0 bg-overlay"
                    @click="browserOpen = false"
                ></div>

                {{-- Modal panel --}}
                <div
                    x-show="browserOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative z-10 w-full max-w-md mx-4 bg-base-100 rounded-box shadow-xl"
                >
                    {{-- Modal header --}}
                    <div class="p-4 border-b border-base-content/10">
                        <h3 class="text-base font-semibold mb-2">{{ __('Select a folder') }}</h3>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="btn btn-ghost btn-sm"
                                :disabled="! parentPath"
                                @click="goUp()"
                                :title="'{{ __('Go to parent folder') }}'"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                                {{ __('Up') }}
                            </button>

                            <p
                                class="flex-1 text-sm font-mono bg-base-200 rounded px-3 py-1.5 truncate text-base-content/80"
                                x-text="currentPath || '...'"
                            ></p>
                        </div>
                    </div>

                    {{-- Directory list --}}
                    <div class="max-h-64 overflow-y-auto">
                        <div x-show="loading" class="flex items-center justify-center py-8">
                            <x-mary-loading class="loading-spinner loading-md text-primary" />
                        </div>

                        <div x-show="error && ! loading" class="px-4 py-3">
                            <p class="text-sm text-error" x-text="error"></p>
                        </div>

                        <div x-show="! loading && ! error && directories.length === 0" class="px-4 py-6 text-center text-base-content/50">
                            <p class="text-sm">{{ __('No subdirectories found.') }}</p>
                        </div>

                        <template x-if="! loading && ! error">
                            <div>
                                <template x-for="dir in directories" :key="dir.path">
                                    <button
                                        type="button"
                                        class="flex items-center gap-3 w-full px-4 py-2.5 text-left hover:bg-base-200 cursor-pointer transition-colors"
                                        @click="navigateTo(dir.path)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 shrink-0 text-base-content/60" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M10 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/>
                                        </svg>
                                        <span class="text-sm truncate" x-text="dir.name"></span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Modal footer --}}
                    <div class="p-4 border-t border-base-content/10 flex items-center justify-end gap-2">
                        <x-mary-button
                            class="btn-ghost btn-sm"
                            type="button"
                            @click="browserOpen = false"
                        >
                            {{ __('Cancel') }}
                        </x-mary-button>

                        <button
                            type="button"
                            class="btn btn-primary btn-sm"
                            @click="selectFolder()"
                            x-bind:disabled="! currentPath || loading"
                        >
                            {{ __('Select this folder') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </x-pages::settings.layout>
</section>
