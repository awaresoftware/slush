<?php

use App\Models\Playlist;
use App\Models\User;

/**
 * App Shell Tests
 *
 * Verifies the bespoke Apple-style shell: sidebar navigation, user menu
 * popover, mobile tab bar, and the sidebar playlist list.
 */
describe('App Shell Layout', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
        $this->response = $this->get(route('dashboard'));
    });

    it('renders the dashboard without errors', function () {
        $this->response->assertOk();
    });

    it('contains sidebar nav links for Home, Library, and Playlists', function () {
        $this->response
            ->assertSee(route('dashboard'), false)
            ->assertSee(route('library.index'), false)
            ->assertSee(route('playlists.index'), false);
    });

    it('contains the logout button with data-test attribute', function () {
        $this->response->assertSee('data-test="logout-button"', false);
    });

    it('contains the user menu trigger with data-test attribute', function () {
        $this->response->assertSee('data-test="sidebar-menu-button"', false);
    });

    it('contains the bottom nav bar with data-test attribute', function () {
        $this->response->assertSee('data-test="bottom-nav"', false);
    });

    it('renders icons as inline SVGs alongside the nav labels', function () {
        $this->response->assertSee('<svg', false);
        $this->response->assertSee('Home', false);
        $this->response->assertSee('Library', false);
        $this->response->assertSee('Playlists', false);
    });

    it('marks the current section with aria-current', function () {
        $this->response->assertSee('aria-current="page"', false);
    });

    it('uses the translucent chrome material for the sidebar and tab bar', function () {
        $this->response->assertSee('surface-chrome', false);
    });

    it('no longer renders the Mary layout shell components', function () {
        $contents = file_get_contents(base_path('resources/views/layouts/app/sidebar.blade.php'));

        expect($contents)
            ->not->toContain('x-mary-main')
            ->not->toContain('x-mary-nav');
    });

    it('keeps the audio player persisted across navigation', function () {
        $contents = file_get_contents(base_path('resources/views/layouts/app/sidebar.blade.php'));

        expect($contents)->toContain("@persist('audio-player')");
    });

    it('does not contain flux:sidebar markup', function () {
        $this->response->assertDontSee('flux:sidebar', false);
    });

    it('does not contain flux:header markup', function () {
        $this->response->assertDontSee('flux:header', false);
    });
});

describe('Sidebar playlists', function () {
    it('lists the user playlists in the sidebar', function () {
        $user = User::factory()->create();
        Playlist::factory()->create(['user_id' => $user->id, 'name' => 'Late Night Jazz']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('Late Night Jazz');
    });

    it('does not list another user playlists', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Playlist::factory()->create(['user_id' => $other->id, 'name' => 'Not Mine']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertDontSee('Not Mine');
    });

    it('offers a see all link once the cap is exceeded', function () {
        $user = User::factory()->create();
        Playlist::factory()->count(11)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSee('See all');
    });

    it('omits the see all link at or below the cap', function () {
        $user = User::factory()->create();
        Playlist::factory()->count(10)->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertDontSee('See all');
    });
});
