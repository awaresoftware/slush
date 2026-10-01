<?php

namespace Database\Factories;

use App\Models\Playlist;
use App\Models\Track;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlaylistTrack>
 */
class PlaylistTrackFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'playlist_id' => Playlist::factory(),
            'track_id' => Track::factory(),
            'position' => fake()->numberBetween(0, 100),
        ];
    }
}
