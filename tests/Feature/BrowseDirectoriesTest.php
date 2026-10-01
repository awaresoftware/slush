<?php

use App\Models\User;

function deleteDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.'/'.$item;

        if (is_dir($path)) {
            deleteDir($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir().'/browse_test_'.uniqid();
    mkdir($this->tempDir, 0755, true);
    mkdir($this->tempDir.'/Albums', 0755, true);
    mkdir($this->tempDir.'/Singles', 0755, true);
    mkdir($this->tempDir.'/Podcasts', 0755, true);
    mkdir($this->tempDir.'/.hidden', 0755, true);
    file_put_contents($this->tempDir.'/cover.jpg', 'fake image');
});

afterEach(function () {
    deleteDir($this->tempDir);
});

it('redirects unauthenticated requests to login', function () {
    $this->get(route('browse-directories'))
        ->assertRedirect(route('login'));
});

it('returns json with valid structure when no path is given', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories'));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'current_path',
            'parent_path',
            'directories',
        ]);

    expect($response->json('directories'))->toBeArray();
});

it('returns directories for a given path', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $this->tempDir]));

    $response->assertSuccessful();

    $names = array_column($response->json('directories'), 'name');
    expect($names)->toContain('Albums')
        ->toContain('Singles')
        ->toContain('Podcasts');

    expect($response->json('current_path'))->toBe($this->tempDir);
});

it('does not include files in the directory listing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $this->tempDir]));

    $response->assertSuccessful();

    $names = array_column($response->json('directories'), 'name');
    expect($names)->not->toContain('cover.jpg');
});

it('excludes hidden directories from the listing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $this->tempDir]));

    $response->assertSuccessful();

    $names = array_column($response->json('directories'), 'name');
    expect($names)->not->toContain('.hidden');
});

it('returns directories sorted alphabetically', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $this->tempDir]));

    $response->assertSuccessful();

    $names = array_column($response->json('directories'), 'name');
    $sorted = $names;
    usort($sorted, 'strcasecmp');
    expect($names)->toBe($sorted);
});

it('returns each directory entry with name and path keys', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $this->tempDir]));

    $response->assertSuccessful();

    $directories = $response->json('directories');
    expect($directories)->not->toBeEmpty();

    foreach ($directories as $dir) {
        expect($dir)->toHaveKeys(['name', 'path']);
    }
});

it('includes the correct parent path in the response', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $this->tempDir]));

    $response->assertSuccessful();

    expect($response->json('parent_path'))->toBe(dirname($this->tempDir));
});

it('returns null for parent_path when at filesystem root', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('browse-directories', ['path' => '/']));

    $response->assertSuccessful();

    expect($response->json('parent_path'))->toBeNull();
});

it('returns 422 for a non-existent path', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('browse-directories', ['path' => '/nonexistent/path/that/does/not/exist']))
        ->assertUnprocessable();
});

it('returns 422 for a path that is not a directory', function () {
    $user = User::factory()->create();
    $filePath = $this->tempDir.'/cover.jpg';

    $this->actingAs($user)
        ->get(route('browse-directories', ['path' => $filePath]))
        ->assertUnprocessable();
});
