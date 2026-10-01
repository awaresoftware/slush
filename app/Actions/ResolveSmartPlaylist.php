<?php

namespace App\Actions;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns a smart playlist's stored rules into a track query. Every field,
 * operator and sort is whitelisted here; anything unrecognised is skipped,
 * so stored rules can never reach the query builder unchecked.
 */
class ResolveSmartPlaylist
{
    /** @var array<string, list<string>> operators per field */
    public const FIELDS = [
        'title' => ['contains', 'is', 'is_not', 'starts_with'],
        'artist' => ['contains', 'is', 'is_not', 'starts_with'],
        'genre' => ['contains', 'is', 'is_not', 'starts_with'],
        'year' => ['is', 'gt', 'lt'],
        'duration' => ['gt', 'lt'],
        'play_count' => ['is', 'gt', 'lt'],
        'favorited' => ['is_true', 'is_false'],
        'added_in_last_days' => ['within'],
        'played_in_last_days' => ['within'],
    ];

    public const SORTS = ['title', 'artist', 'year', 'play_count', 'last_played_at', 'recently_added', 'random'];

    public const MAX_LIMIT = 500;

    /**
     * @return Builder<Track>
     */
    public function __invoke(Playlist $playlist): Builder
    {
        $rules = $playlist->rules ?? [];
        $orMode = ($rules['match'] ?? 'all') === 'any';

        $query = Track::query()
            ->where('user_id', $playlist->user_id)
            ->with('album');

        $conditions = collect($rules['rules'] ?? [])
            ->filter(fn ($rule) => $this->isValidRule($rule))
            ->values();

        if ($conditions->isNotEmpty()) {
            $query->where(function (Builder $group) use ($conditions, $orMode) {
                foreach ($conditions as $rule) {
                    $this->apply($group, $rule, $orMode ? 'orWhere' : 'where');
                }
            });
        }

        $this->applySort($query, $rules['sort'] ?? 'title');

        $limit = (int) ($rules['limit'] ?? 0);
        if ($limit > 0) {
            $query->limit(min($limit, self::MAX_LIMIT));
        }

        return $query;
    }

    /**
     * @param  mixed  $rule
     */
    private function isValidRule($rule): bool
    {
        if (! is_array($rule) || ! isset($rule['field'], $rule['op'])) {
            return false;
        }

        $ops = self::FIELDS[$rule['field']] ?? null;

        return $ops !== null && in_array($rule['op'], $ops, true);
    }

    /**
     * @param  Builder<Track>  $group
     * @param  array{field: string, op: string, value?: mixed}  $rule
     */
    private function apply(Builder $group, array $rule, string $where): void
    {
        $value = $rule['value'] ?? null;

        match ($rule['field']) {
            'title', 'artist', 'genre' => $this->applyString($group, $rule['field'], $rule['op'], (string) $value, $where),
            'year', 'duration', 'play_count' => $this->applyNumber($group, $rule['field'], $rule['op'], (int) $value, $where),
            'favorited' => $rule['op'] === 'is_true'
                ? $group->{$where.'NotNull'}('favorited_at')
                : $group->{$where.'Null'}('favorited_at'),
            'added_in_last_days' => $group->{$where}('created_at', '>=', now()->subDays(max(1, (int) $value))),
            'played_in_last_days' => $group->{$where}('last_played_at', '>=', now()->subDays(max(1, (int) $value))),
        };
    }

    /**
     * @param  Builder<Track>  $group
     */
    private function applyString(Builder $group, string $field, string $op, string $value, string $where): void
    {
        // LIKE wildcards in stored values are dropped, matching search behaviour.
        $clean = str_replace(['%', '_'], '', $value);

        match ($op) {
            'contains' => $group->{$where}($field, 'like', '%'.$clean.'%'),
            'is' => $group->{$where}($field, '=', $value),
            'is_not' => $group->{$where}($field, '!=', $value),
            'starts_with' => $group->{$where}($field, 'like', $clean.'%'),
        };
    }

    /**
     * @param  Builder<Track>  $group
     */
    private function applyNumber(Builder $group, string $field, string $op, int $value, string $where): void
    {
        match ($op) {
            'is' => $group->{$where}($field, '=', $value),
            'gt' => $group->{$where}($field, '>', $value),
            'lt' => $group->{$where}($field, '<', $value),
        };
    }

    /**
     * @param  Builder<Track>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match (in_array($sort, self::SORTS, true) ? $sort : 'title') {
            'artist' => $query->orderBy('artist')->orderBy('title'),
            'year' => $query->orderByDesc('year')->orderBy('title'),
            'play_count' => $query->orderByDesc('play_count')->orderBy('title'),
            'last_played_at' => $query->orderByRaw('last_played_at IS NULL')->orderByDesc('last_played_at'),
            'recently_added' => $query->orderByDesc('created_at'),
            'random' => $query->inRandomOrder(),
            default => $query->orderBy('title'),
        };
    }
}
