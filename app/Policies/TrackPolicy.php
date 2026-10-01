<?php

namespace App\Policies;

use App\Models\Track;
use App\Models\User;

class TrackPolicy
{
    /**
     * Determine whether the user can stream the track.
     */
    public function stream(User $user, Track $track): bool
    {
        return $user->id === $track->user_id;
    }
}
