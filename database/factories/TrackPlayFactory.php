<?php

namespace Database\Factories;

use App\Models\Track;
use App\Models\TrackPlay;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrackPlay>
 */
class TrackPlayFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'track_id' => Track::factory(),
            'played_at' => now(),
        ];
    }
}
