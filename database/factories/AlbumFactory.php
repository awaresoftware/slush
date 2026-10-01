<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Album>
 */
class AlbumFactory extends Factory
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
            'title' => fake()->words(3, true),
            'artist' => fake()->name(),
            'year' => (int) fake()->year(),
            'genre' => fake()->randomElement(['Rock', 'Pop', 'Jazz', 'Classical', 'Electronic']),
            'cover_art_path' => null,
        ];
    }
}
