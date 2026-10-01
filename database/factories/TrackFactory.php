<?php

namespace Database\Factories;

use App\Models\Album;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Track>
 */
class TrackFactory extends Factory
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
            'album_id' => null,
            'title' => fake()->words(rand(2, 5), true),
            'artist' => fake()->name(),
            'file_path' => 'music/1/fake-song.mp3',
            'file_hash' => fake()->sha256(),
            'duration' => fake()->numberBetween(60, 600),
            'year' => fake()->year(),
            'genre' => fake()->randomElement(['Rock', 'Pop', 'Jazz', 'Classical', 'Electronic']),
            'source_type' => 'uploaded',
        ];
    }

    /**
     * State: track associated with an album owned by the same user.
     */
    public function withAlbum(): static
    {
        return $this->state(fn (array $attributes) => [
            'album_id' => Album::factory()->state(['user_id' => $attributes['user_id']]),
        ]);
    }
}
