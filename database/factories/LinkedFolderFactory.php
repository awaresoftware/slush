<?php

namespace Database\Factories;

use App\Models\LinkedFolder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LinkedFolder>
 */
class LinkedFolderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $path = '/Users/fake/Music/'.fake()->word();

        return [
            'user_id' => User::factory(),
            'path' => $path,
            'name' => basename($path),
            'status' => 'active',
            'track_count' => 0,
            'scanned_count' => 0,
            'total_count' => 0,
            'last_scanned_at' => null,
        ];
    }
}
