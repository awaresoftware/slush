<?php

use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;

/**
 * Boot the provider against a specific request the way PHP-FPM does for each
 * real request, then dispatch that same request so the rendered page reflects
 * the asset decision made for it.
 *
 * @param  string|null  $hotUrl  Dev server URL to record, or null for no hot file
 */
function renderLoginFor(string $requestHost, ?string $hotUrl, string $clientIp = '203.0.113.10'): string
{
    $hotFile = tempnam(sys_get_temp_dir(), 'vite-hot-');

    if ($hotUrl === null) {
        unlink($hotFile);
    } else {
        file_put_contents($hotFile, $hotUrl);
    }

    $request = Request::create("https://{$requestHost}/login");
    $request->server->set('REMOTE_ADDR', $clientIp);

    app()->instance('request', $request);
    Vite::useHotFile($hotFile);
    (new AppServiceProvider(app()))->boot();

    $response = app(Kernel::class)->handle($request);

    if (is_file($hotFile)) {
        unlink($hotFile);
    }

    return $response->getContent();
}

afterEach(function (): void {
    User::query()->delete();
});

test('a request from another host falls back to the built assets', function () {
    User::factory()->create();

    $html = renderLoginFor('rekodo.aware.hr', 'https://erik.rekodo.test:5174');

    expect($html)->toContain('/build/assets/')
        ->and($html)->not->toContain('erik.rekodo.test:5174');
});

test('a request from the dev server host keeps hot reloading', function () {
    User::factory()->create();

    $html = renderLoginFor('erik.rekodo.test', 'https://erik.rekodo.test:5174');

    expect($html)->toContain('erik.rekodo.test:5174/@vite/client')
        ->and($html)->toContain('erik.rekodo.test:5174/resources/css/app.css');
});

test('a loopback client on a different host still falls back to built assets', function () {
    User::factory()->create();

    // A tunnel or port forward on this machine also arrives from 127.0.0.1,
    // so a loopback client proves nothing about whether the dev host resolves.
    $html = renderLoginFor('localhost:8000', 'https://erik.rekodo.test:5174', '127.0.0.1');

    expect($html)->toContain('/build/assets/')
        ->and($html)->not->toContain('erik.rekodo.test:5174');
});

test('an unparseable hot file falls back to the built assets', function () {
    User::factory()->create();

    $html = renderLoginFor('erik.rekodo.test', 'not-a-url');

    expect($html)->toContain('/build/assets/')
        ->and($html)->not->toContain('not-a-url');
});

test('an absent hot file uses the built assets', function () {
    User::factory()->create();

    $html = renderLoginFor('erik.rekodo.test', null);

    expect($html)->toContain('/build/assets/');
});

test('asset tags never point at a host other than the one serving the request', function () {
    User::factory()->create();

    // The ambient public/hot on a developer machine points at the Herd domain.
    // Whichever host serves the request, assets must resolve against that host.
    $html = renderLoginFor('rekodo.aware.hr', 'https://erik.rekodo.test:5174');

    preg_match_all('#(?:href|src)="(https?://[^/"]+)#', $html, $matches);

    foreach (array_unique($matches[1]) as $origin) {
        expect(parse_url($origin, PHP_URL_HOST))->toBe('rekodo.aware.hr');
    }
});
