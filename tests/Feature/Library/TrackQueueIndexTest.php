<?php

use App\Livewire\AudioPlayer;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

/*
 * loadQueue() drops unavailable tracks before it indexes the queue, so a row
 * dispatching its raw loop position would start the wrong track as soon as
 * anything in the list is missing. These cover the position the table actually
 * dispatches, and that it still lines up with the queue loadQueue builds.
 */

/**
 * Map every rendered track row to the queue index it dispatches, in the order
 * the page rendered them. Rows that dispatch nothing map to null.
 *
 * @return array<int, int|null>
 */
function dispatchedQueueIndexes(string $html): array
{
    preg_match_all('/wire:key="track-(\d+)"/', $html, $anchors, PREG_OFFSET_CAPTURE);

    $indexes = [];

    foreach ($anchors[1] as $position => [$trackId, $offset]) {
        $row = substr($html, $offset, 1500);

        // Blade escapes the quotes inside the x-on:click attribute.
        preg_match('/play-track&#039;, \{ trackIds: [^}]*index: (\d+) \}\)/', $row, $matches);

        $indexes[(int) $trackId] = isset($matches[1]) ? (int) $matches[1] : null;
    }

    return $indexes;
}

it('numbers queue positions by available rows, not raw loop position', function () {
    $user = User::factory()->create();

    $available = [
        Track::factory()->create(['user_id' => $user->id, 'is_available' => true]),
        Track::factory()->create(['user_id' => $user->id, 'is_available' => true]),
    ];
    $missing = Track::factory()->create(['user_id' => $user->id, 'is_available' => false]);

    $indexes = dispatchedQueueIndexes(
        Livewire::actingAs($user)->test('pages::library.index')->html()
    );

    // The unavailable row carries no play dispatch at all.
    expect($indexes)->toHaveKey($missing->id)
        ->and($indexes[$missing->id])->toBeNull();

    // The two available rows take queue positions 0 and 1, in render order.
    // Before the fix the second one carried its raw loop position, which is 2
    // whenever the unavailable row precedes it.
    $availableIndexes = array_map(
        fn (int $id) => $indexes[$id],
        array_values(array_filter(array_keys($indexes), fn (int $id) => in_array($id, array_column($available, 'id'), true)))
    );

    expect($availableIndexes)->toBe([0, 1]);
});

it('dispatched queue position resolves to the clicked track', function () {
    $user = User::factory()->create();

    $first = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);
    $missing = Track::factory()->create(['user_id' => $user->id, 'is_available' => false]);
    $last = Track::factory()->create(['user_id' => $user->id, 'is_available' => true]);

    $html = Livewire::actingAs($user)->test('pages::library.index')->html();
    $indexes = dispatchedQueueIndexes($html);

    // Whatever position the page rendered the last available track at, playing
    // it must start on that track once the unavailable one is filtered out.
    $targetId = $last->id;
    $index = $indexes[$targetId];

    expect($index)->toBe(1);

    Livewire::actingAs($user)
        ->test(AudioPlayer::class)
        ->call('loadQueue', [$first->id, $missing->id, $last->id], $index)
        ->assertSet('currentTrackId', $last->id)
        ->assertSet('queue', [$first->id, $last->id]);
});
