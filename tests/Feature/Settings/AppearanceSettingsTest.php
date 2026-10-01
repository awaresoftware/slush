<?php

use App\Enums\Accent;
use App\Enums\Theme;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;

test('appearance page loads successfully', function () {
    $this->actingAs(User::factory()->create());
    $this->get(route('appearance.edit'))->assertOk();
});

test('appearance page does not contain flux components', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('appearance.edit'));
    $html = $response->getContent();
    expect($html)->not->toContain('flux:');
    expect($html)->not->toContain('$flux');
});

test('appearance page component mounts without errors', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test('pages::settings.appearance')
        ->assertHasNoErrors();
});

test('appearance page renders a card for every theme', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('appearance.edit'));

    foreach (Theme::cases() as $theme) {
        $response->assertSee('data-test="theme-card-'.$theme->value.'"', false);
        $response->assertSee($theme->label(), false);
    }
});

test('appearance page renders a dot for every accent', function () {
    $this->actingAs(User::factory()->create());
    $response = $this->get(route('appearance.edit'));

    foreach (Accent::cases() as $accent) {
        $response->assertSee('data-test="accent-dot-'.$accent->value.'"', false);
    }
});

test('appearance page mounts with the saved preferences selected', function () {
    $user = User::factory()->create([
        'theme' => Theme::Midnight->value,
        'accent' => Accent::Purple->value,
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->assertSet('theme', Theme::Midnight->value)
        ->assertSet('accent', Accent::Purple->value)
        ->assertSee(Accent::Purple->label());
});

test('choosing a theme persists it to the user', function () {
    $user = User::factory()->create(['theme' => Theme::System->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('theme', Theme::CatppuccinFrappe->value)
        ->assertHasNoErrors();

    expect($user->fresh()->theme)->toBe(Theme::CatppuccinFrappe->value);
});

test('choosing an accent persists it to the user', function () {
    $user = User::factory()->create(['accent' => Accent::MusicRed->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('accent', Accent::Green->value)
        ->assertHasNoErrors();

    expect($user->fresh()->accent)->toBe(Accent::Green->value);
});

test('an unknown theme is rejected and the saved value restored', function () {
    $user = User::factory()->create(['theme' => Theme::Graphite->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('theme', 'spotify-dark')
        ->assertDispatched('appearance-failed')
        ->assertSet('theme', Theme::Graphite->value);

    expect($user->fresh()->theme)->toBe(Theme::Graphite->value);
});

test('an unknown accent is rejected and the saved value restored', function () {
    $user = User::factory()->create(['accent' => Accent::Blue->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('accent', 'chartreuse')
        ->assertDispatched('appearance-failed')
        ->assertSet('accent', Accent::Blue->value);

    expect($user->fresh()->accent)->toBe(Accent::Blue->value);
});

test('saving a theme queues the appearance theme cookie', function () {
    $user = User::factory()->create(['theme' => Theme::System->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('theme', Theme::Graphite->value)
        ->assertHasNoErrors();

    // getQueuedCookies() flattens the jar, so the cookie names are not the keys.
    $queued = collect(Cookie::getQueuedCookies())->keyBy(fn ($cookie) => $cookie->getName());

    $cookie = $queued->get(Theme::COOKIE);

    expect($cookie->getValue())->toBe(Theme::Graphite->value)
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addYears(4)->getTimestamp());
});

test('saving an accent queues the appearance accent cookie', function () {
    $user = User::factory()->create(['accent' => Accent::MusicRed->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('accent', Accent::Green->value)
        ->assertHasNoErrors();

    $queued = collect(Cookie::getQueuedCookies())->keyBy(fn ($cookie) => $cookie->getName());

    expect($queued->get(Accent::COOKIE)->getValue())->toBe(Accent::Green->value);
});

test('a rejected theme queues no appearance cookie', function () {
    $user = User::factory()->create(['theme' => Theme::Graphite->value]);

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('theme', 'spotify-dark')
        ->assertDispatched('appearance-failed');

    expect(Cookie::getQueuedCookies())->toBeEmpty();
});

test('the chosen theme survives a reload', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.appearance')
        ->set('theme', Theme::Porcelain->value);

    $html = $this->actingAs($user->fresh())->get(route('dashboard'))->getContent();

    expect($html)->toContain('data-theme="'.Theme::Porcelain->value.'"');
});
