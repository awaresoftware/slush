<?php

use App\Models\Album;
use App\Models\Track;
use App\Models\TrackPlay;
use App\Models\User;

it('shows Jump Back In only when something has been played', function () {
    $user = User::factory()->create();
    Album::factory()->create(['user_id' => $user->id, 'title' => 'Unplayed Album']);
    Track::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Jump Back In')
        ->assertDontSee('Heavy Rotation');
});

it('surfaces recently played and most played albums on Home', function () {
    $user = User::factory()->create();

    $recent = Album::factory()->create(['user_id' => $user->id, 'title' => 'Fresh Spins']);
    Track::factory()->create(['user_id' => $user->id, 'album_id' => $recent->id, 'last_played_at' => now(), 'play_count' => 1]);

    $heavy = Album::factory()->create(['user_id' => $user->id, 'title' => 'Worn Grooves']);
    Track::factory()->create(['user_id' => $user->id, 'album_id' => $heavy->id, 'last_played_at' => now()->subWeek(), 'play_count' => 40]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Jump Back In')
        ->assertSee('Heavy Rotation')
        ->assertSee('Fresh Spins')
        ->assertSee('Worn Grooves');

    // Jump Back In is ordered by recency, Heavy Rotation by volume.
    $html = $response->getContent();
    $jump = strpos($html, 'Jump Back In');
    $heavyPos = strpos($html, 'Heavy Rotation');

    expect(strpos($html, 'Fresh Spins', $jump))->toBeLessThan(strpos($html, 'Worn Grooves', $jump))
        ->and(strpos($html, 'Worn Grooves', $heavyPos))->toBeLessThan(strpos($html, 'Fresh Spins', $heavyPos));
});

it('counts plays in the Home stat line', function () {
    $user = User::factory()->create();
    $track = Track::factory()->create(['user_id' => $user->id]);
    TrackPlay::factory()->count(3)->create(['user_id' => $user->id, 'track_id' => $track->id]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSee('3 plays');
});
