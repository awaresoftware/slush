<?php

namespace App\Models;

use Database\Factories\PlaylistFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Playlist extends Model
{
    /** @use HasFactory<PlaylistFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'rules',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }

    /**
     * A playlist with rules resolves its tracks at read time.
     */
    public function isSmart(): bool
    {
        return $this->rules !== null;
    }

    /**
     * Get the user that owns the playlist.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add a track to the end of this playlist, ignoring tracks that are already in it.
     */
    public function addTrack(Track $track): void
    {
        if ($this->tracks()->where('track_id', $track->id)->exists()) {
            return;
        }

        $maxPosition = PlaylistTrack::where('playlist_id', $this->id)->max('position') ?? -1;

        $this->tracks()->attach($track->id, ['position' => $maxPosition + 1]);
    }

    /**
     * Get the tracks in this playlist, ordered by position.
     */
    public function tracks(): BelongsToMany
    {
        return $this->belongsToMany(Track::class, 'playlist_tracks')
            ->withPivot('position')
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
