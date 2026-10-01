<?php

use App\Actions\ResolveSmartPlaylist;
use App\Models\Playlist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts.app')] class extends Component {
    public ?Playlist $playlist = null;

    public string $name = '';

    public string $description = '';

    public string $matchMode = 'all';

    /** @var list<array{field: string, op: string, value: string}> */
    public array $rules = [];

    public string $sort = 'title';

    public ?int $limit = null;

    public function mount(?Playlist $playlist = null): void
    {
        if ($playlist === null || ! $playlist->exists) {
            $this->rules = [['field' => 'artist', 'op' => 'contains', 'value' => '']];

            return;
        }

        abort_unless($playlist->user_id === Auth::id(), 403);
        abort_unless($playlist->isSmart(), 404);

        $this->playlist = $playlist;
        $this->name = $playlist->name;
        $this->description = $playlist->description ?? '';

        $stored = $playlist->rules;
        $this->matchMode = ($stored['match'] ?? 'all') === 'any' ? 'any' : 'all';
        $this->sort = $stored['sort'] ?? 'title';
        $this->limit = isset($stored['limit']) && (int) $stored['limit'] > 0 ? (int) $stored['limit'] : null;
        $this->rules = collect($stored['rules'] ?? [])
            ->map(fn ($rule) => [
                'field' => (string) ($rule['field'] ?? 'artist'),
                'op' => (string) ($rule['op'] ?? 'contains'),
                'value' => (string) ($rule['value'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function addRule(): void
    {
        $this->rules[] = ['field' => 'artist', 'op' => 'contains', 'value' => ''];
    }

    public function removeRule(int $index): void
    {
        unset($this->rules[$index]);
        $this->rules = array_values($this->rules);
    }

    /**
     * Keep each rule's operator legal for its field when the field changes.
     */
    public function updatedRules(): void
    {
        foreach ($this->rules as $i => $rule) {
            $ops = ResolveSmartPlaylist::FIELDS[$rule['field']] ?? ['contains'];

            if (! in_array($rule['op'], $ops, true)) {
                $this->rules[$i]['op'] = $ops[0];
            }
        }
    }

    /**
     * The rules payload as it would be stored.
     *
     * @return array{match: string, rules: list<array{field: string, op: string, value: string}>, sort: string, limit: int|null}
     */
    private function payload(): array
    {
        return [
            'match' => $this->matchMode === 'any' ? 'any' : 'all',
            'rules' => array_values($this->rules),
            'sort' => $this->sort,
            'limit' => $this->limit,
        ];
    }

    /**
     * Live preview of what the rules currently match.
     *
     * @return array{count: int, sample: Collection}
     */
    #[Computed]
    public function preview(): array
    {
        $probe = new Playlist(['user_id' => Auth::id()]);
        $probe->rules = $this->payload();

        $query = app(ResolveSmartPlaylist::class)($probe);

        return [
            'count' => (clone $query)->toBase()->getCountForPagination(),
            'sample' => $query->limit(8)->get(),
        ];
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'matchMode' => ['required', 'in:all,any'],
            'sort' => ['required', 'in:'.implode(',', ResolveSmartPlaylist::SORTS)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.ResolveSmartPlaylist::MAX_LIMIT],
            'rules' => ['required', 'array', 'min:1'],
            'rules.*.field' => ['required', 'in:'.implode(',', array_keys(ResolveSmartPlaylist::FIELDS))],
            'rules.*.op' => ['required', 'string'],
            'rules.*.value' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
            'rules' => $this->payload(),
        ];

        if ($this->playlist !== null) {
            $this->playlist->update($attributes);
            $playlist = $this->playlist;
        } else {
            $playlist = Playlist::create(['user_id' => Auth::id(), ...$attributes]);
        }

        $this->redirect(route('playlists.show', $playlist), navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function fieldLabels(): array
    {
        return [
            'title' => __('Title'),
            'artist' => __('Artist'),
            'genre' => __('Genre'),
            'year' => __('Year'),
            'duration' => __('Duration (seconds)'),
            'play_count' => __('Play count'),
            'favorited' => __('Favourite'),
            'added_in_last_days' => __('Added in the last… (days)'),
            'played_in_last_days' => __('Played in the last… (days)'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function opLabels(): array
    {
        return [
            'contains' => __('contains'),
            'is' => __('is'),
            'is_not' => __('is not'),
            'starts_with' => __('starts with'),
            'gt' => __('is more than'),
            'lt' => __('is less than'),
            'is_true' => __('is on'),
            'is_false' => __('is off'),
            'within' => __('within'),
        ];
    }
}; ?>

<div>
    <x-ui.page-header
        :title="$playlist ? __('Edit Smart Playlist') : __('New Smart Playlist')"
        :subtitle="__('Rules keep this playlist up to date by themselves.')"
        :back="$playlist ? route('playlists.show', $playlist) : route('playlists.index')"
        :back-label="__('Playlists')"
    />

    <div class="flex flex-col gap-8 px-4 pb-8 sm:px-6 lg:flex-row">
        <div class="min-w-0 max-w-xl flex-1 space-y-6">
            <x-mary-input wire:model="name" :label="__('Name')" placeholder="{{ __('Heavy rotation') }}" />
            @error('name') <p class="-mt-4 text-footnote text-error">{{ $message }}</p> @enderror

            <x-mary-textarea wire:model="description" :label="__('Description (optional)')" rows="2" />

            <fieldset class="rounded-box border border-separator p-4">
                <legend class="px-1 text-caption text-base-content/45">{{ __('Rules') }}</legend>

                <label class="flex items-center gap-2 text-subhead">
                    {{ __('Match') }}
                    <select wire:model.live="matchMode" class="select select-sm w-auto rounded-full" data-test="smart-match">
                        <option value="all">{{ __('all rules') }}</option>
                        <option value="any">{{ __('any rule') }}</option>
                    </select>
                </label>

                <div class="mt-4 space-y-2">
                    @foreach ($rules as $i => $rule)
                        @php($ops = \App\Actions\ResolveSmartPlaylist::FIELDS[$rule['field']] ?? [])
                        @php($needsValue = ! in_array($rule['op'], ['is_true', 'is_false'], true))
                        @php($numeric = in_array($rule['field'], ['year', 'duration', 'play_count', 'added_in_last_days', 'played_in_last_days'], true))

                        <div class="flex flex-wrap items-center gap-2" wire:key="rule-{{ $i }}" data-test="smart-rule">
                            <select wire:model.live="rules.{{ $i }}.field" class="select select-sm w-auto" aria-label="{{ __('Field') }}">
                                @foreach ($this->fieldLabels() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>

                            <select wire:model.live="rules.{{ $i }}.op" class="select select-sm w-auto" aria-label="{{ __('Condition') }}">
                                @foreach ($ops as $op)
                                    <option value="{{ $op }}">{{ $this->opLabels()[$op] ?? $op }}</option>
                                @endforeach
                            </select>

                            @if ($needsValue)
                                <input
                                    wire:model.live.debounce.400ms="rules.{{ $i }}.value"
                                    type="{{ $numeric ? 'number' : 'text' }}"
                                    class="input input-sm h-9 w-36 flex-1"
                                    aria-label="{{ __('Value') }}"
                                >
                            @endif

                            <x-ui.icon-button
                                icon="x-mark"
                                size="sm"
                                :label="__('Remove rule')"
                                wire:click="removeRule({{ $i }})"
                            />
                        </div>
                    @endforeach
                </div>
                @error('rules') <p class="mt-2 text-footnote text-error">{{ $message }}</p> @enderror

                <button type="button" wire:click="addRule" class="mt-3 inline-flex items-center gap-1.5 text-subhead text-primary hover:underline" data-test="smart-add-rule">
                    <x-mary-icon name="o-plus" class="size-4" />
                    {{ __('Add rule') }}
                </button>
            </fieldset>

            <div class="flex flex-wrap items-end gap-4">
                <label class="flex flex-col gap-1 text-subhead">
                    {{ __('Sort by') }}
                    <select wire:model.live="sort" class="select select-sm w-auto rounded-full">
                        <option value="title">{{ __('Title') }}</option>
                        <option value="artist">{{ __('Artist') }}</option>
                        <option value="year">{{ __('Year (newest)') }}</option>
                        <option value="play_count">{{ __('Most played') }}</option>
                        <option value="last_played_at">{{ __('Recently played') }}</option>
                        <option value="recently_added">{{ __('Recently added') }}</option>
                        <option value="random">{{ __('Random') }}</option>
                    </select>
                </label>

                <label class="flex flex-col gap-1 text-subhead">
                    {{ __('Limit (optional)') }}
                    <input wire:model.live.debounce.400ms="limit" type="number" min="1" max="500" class="input input-sm h-9 w-28" placeholder="∞">
                </label>
            </div>
            @error('limit') <p class="text-footnote text-error">{{ $message }}</p> @enderror

            <div class="flex items-center gap-2 pt-2">
                <x-mary-button wire:click="save" spinner label="{{ $playlist ? __('Save Changes') : __('Create Smart Playlist') }}" class="btn-primary btn-capsule" data-test="smart-save" />

                @if ($playlist)
                    <form method="POST" action="{{ route('playlists.destroy', $playlist) }}">
                        @csrf
                        @method('DELETE')
                        <x-mary-button type="submit" label="{{ __('Delete') }}" class="btn-capsule btn-sm btn-ghost text-error" onclick="return confirm('{{ __('Delete this smart playlist?') }}')" />
                    </form>
                @endif
            </div>
        </div>

        {{-- Live preview --}}
        <aside class="w-full shrink-0 lg:w-80">
            <div class="rounded-box border border-separator bg-base-100 p-4 shadow-1">
                <h2 class="text-title-3">{{ __('Preview') }}</h2>
                <p class="mt-0.5 text-footnote text-base-content/65" data-test="smart-preview-count">
                    {{ trans_choice(':count matching track|:count matching tracks', $this->preview['count'], ['count' => $this->preview['count']]) }}
                </p>

                <ul class="mt-3 space-y-1">
                    @forelse ($this->preview['sample'] as $track)
                        <li class="flex items-center gap-2 text-subhead" wire:key="preview-{{ $track->id }}">
                            <x-mary-icon name="o-musical-note" class="size-3.5 shrink-0 text-base-content/35" />
                            <span class="min-w-0 flex-1 truncate">{{ $track->title }}</span>
                            <span class="shrink-0 text-footnote text-base-content/45">{{ $track->artist }}</span>
                        </li>
                    @empty
                        <li class="py-4 text-center text-footnote text-base-content/45">{{ __('Nothing matches yet.') }}</li>
                    @endforelse
                </ul>
            </div>
        </aside>
    </div>
</div>
