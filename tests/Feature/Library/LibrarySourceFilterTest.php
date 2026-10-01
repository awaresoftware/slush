<?php

use App\Enums\SourceType;
use App\Models\LinkedFolder;
use App\Models\Track;
use App\Models\User;
use Livewire\Livewire;

it('default all filter shows both uploaded and local tracks', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Uploaded Track',
        'source_type' => SourceType::Uploaded,
    ]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Local Track',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/local.mp3',
    ]);

    $response = Livewire::actingAs($user)->test('pages::library.index');

    $response->assertSee('My Uploaded Track')
        ->assertSee('My Local Track');
});

it('uploaded filter shows only uploaded tracks', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Uploaded Track',
        'source_type' => SourceType::Uploaded,
    ]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Local Track',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/local.mp3',
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'uploaded')
        ->assertSee('My Uploaded Track')
        ->assertDontSee('My Local Track');
});

it('local filter shows only local tracks', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Uploaded Track',
        'source_type' => SourceType::Uploaded,
    ]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Local Track',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/local.mp3',
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'local')
        ->assertSee('My Local Track')
        ->assertDontSee('My Uploaded Track');
});

it('shows contextual empty state message when local filter yields no results', function () {
    $user = User::factory()->create();

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Uploaded Track',
        'source_type' => SourceType::Uploaded,
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'local')
        ->assertSee('No local tracks found')
        ->assertDontSee('Your library is empty');
});

it('shows contextual empty state message when uploaded filter yields no results', function () {
    $user = User::factory()->create();
    $folder = LinkedFolder::factory()->create(['user_id' => $user->id]);

    Track::factory()->create([
        'user_id' => $user->id,
        'title' => 'My Local Track',
        'source_type' => SourceType::Local,
        'linked_folder_id' => $folder->id,
        'file_path' => '/fake/path/local.mp3',
    ]);

    Livewire::actingAs($user)
        ->test('pages::library.index')
        ->set('sourceFilter', 'uploaded')
        ->assertSee('No uploaded tracks found')
        ->assertDontSee('Your library is empty');
});

it('source filter dropdown is visible on the library page', function () {
    $user = User::factory()->create();

    Track::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user)
        ->get(route('library.index'))
        ->assertSee('All Sources');
});
