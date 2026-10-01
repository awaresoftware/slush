<?php

use App\Enums\Accent;
use App\Enums\Theme;
use App\Models\User;

it('auth layout delegates the html attributes to the shared partial', function () {
    $contents = file_get_contents(base_path('resources/views/layouts/auth/simple.blade.php'));

    expect($contents)->toContain("@include('partials.html-attributes')");
});

it('auth layout does not hardcode a theme', function () {
    $contents = file_get_contents(base_path('resources/views/layouts/auth/simple.blade.php'));

    expect($contents)->not->toContain('data-theme="catppuccin-frappe"');
});

it('auth layout does not contain @fluxScripts directive', function () {
    $contents = file_get_contents(base_path('resources/views/layouts/auth/simple.blade.php'));

    expect($contents)->not->toContain('@fluxScripts');
});

it('auth layout does not contain dark gradient class', function () {
    $contents = file_get_contents(base_path('resources/views/layouts/auth/simple.blade.php'));

    expect($contents)->not->toContain('dark:bg-linear-to-b');
});

it('auth layout does not contain class="dark" on html element', function () {
    $contents = file_get_contents(base_path('resources/views/layouts/auth/simple.blade.php'));

    expect($contents)->not->toContain('class="dark"');
});

it('auth layout body uses bg-base-200 class', function () {
    $contents = file_get_contents(base_path('resources/views/layouts/auth/simple.blade.php'));

    expect($contents)->toContain('bg-base-200');
});

it('renders no data-theme attribute for a guest with no appearance cookie, who follows the system theme', function () {
    User::factory()->create(['theme' => Theme::Midnight->value]);

    $html = $this->get(route('login'))->getContent();

    expect($html)
        ->not->toContain('data-theme=')
        ->toContain('data-accent="'.Accent::MusicRed->value.'"');
});

it('renders the appearance cookie of a guest on the login page', function () {
    // RequireNoUsers redirects login to register while the database has no
    // users, so a user has to exist for the login page to be reachable.
    User::factory()->create();

    $html = $this
        ->withCookie(Theme::COOKIE, Theme::Graphite->value)
        ->withCookie(Accent::COOKIE, Accent::Green->value)
        ->get(route('login'))
        ->getContent();

    expect($html)
        ->toContain('data-theme="'.Theme::Graphite->value.'"')
        ->toContain('data-accent="'.Accent::Green->value.'"')
        ->toContain('<meta name="theme-color" content="'.Theme::Graphite->canvas().'">');
});

it('renders the appearance cookie of a guest on the forgot password page', function () {
    $html = $this
        ->withCookie(Theme::COOKIE, Theme::Midnight->value)
        ->withCookie(Accent::COOKIE, Accent::Purple->value)
        ->get(route('password.request'))
        ->getContent();

    expect($html)
        ->toContain('data-theme="'.Theme::Midnight->value.'"')
        ->toContain('data-accent="'.Accent::Purple->value.'"');
});

it('prefers the stored theme and accent over the appearance cookie', function () {
    $user = User::factory()->create([
        'theme' => Theme::Graphite->value,
        'accent' => Accent::Blue->value,
    ]);

    $html = $this
        ->withCookie(Theme::COOKIE, Theme::Midnight->value)
        ->withCookie(Accent::COOKIE, Accent::Green->value)
        ->actingAs($user)
        ->get(route('dashboard'))
        ->getContent();

    expect($html)
        ->toContain('data-theme="'.Theme::Graphite->value.'"')
        ->toContain('data-accent="'.Accent::Blue->value.'"')
        ->not->toContain('data-theme="'.Theme::Midnight->value.'"')
        ->not->toContain('data-accent="'.Accent::Green->value.'"');
});

it('omits data-theme for a guest whose appearance cookie is the system theme', function () {
    User::factory()->create();

    $html = $this
        ->withCookie(Theme::COOKIE, Theme::System->value)
        ->get(route('login'))
        ->getContent();

    expect($html)
        ->not->toContain('data-theme=')
        ->toContain('data-accent="'.Accent::MusicRed->value.'"');
});

it('ignores a tampered theme cookie for a guest', function () {
    User::factory()->create();

    $html = $this
        ->withCookie(Theme::COOKIE, 'spotify-dark')
        ->get(route('login'))
        ->getContent();

    expect($html)
        ->not->toContain('data-theme=')
        ->not->toContain('spotify-dark')
        ->toContain('data-accent="'.Accent::MusicRed->value.'"');
});

it('ignores a tampered accent cookie for a guest', function () {
    User::factory()->create();

    $html = $this
        ->withCookie(Accent::COOKIE, 'chartreuse')
        ->get(route('login'))
        ->getContent();

    expect($html)
        ->toContain('data-accent="'.Accent::MusicRed->value.'"')
        ->not->toContain('chartreuse');
});

it('renders the stored theme and accent for an authenticated user', function () {
    $user = User::factory()->create([
        'theme' => Theme::Midnight->value,
        'accent' => Accent::Green->value,
    ]);

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($html)
        ->toContain('data-theme="'.Theme::Midnight->value.'"')
        ->toContain('data-accent="'.Accent::Green->value.'"');
});

it('omits data-theme for an authenticated user on the system theme', function () {
    $user = User::factory()->create(['theme' => Theme::System->value]);

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($html)->not->toContain('data-theme=');
});

it('emits a single theme-color meta for an explicit theme', function () {
    $user = User::factory()->create(['theme' => Theme::Graphite->value]);

    $html = $this->actingAs($user)->get(route('dashboard'))->getContent();

    expect($html)
        ->toContain('<meta name="theme-color" content="'.Theme::Graphite->canvas().'">')
        ->not->toContain('prefers-color-scheme: light');
});

it('emits paired theme-color metas for the system theme', function () {
    User::factory()->create();

    $html = $this->get(route('login'))->getContent();

    expect($html)
        ->toContain('media="(prefers-color-scheme: light)"')
        ->toContain('media="(prefers-color-scheme: dark)"');
});
