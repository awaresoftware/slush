<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Sentinel hot file used to force the built manifest when the Vite dev
     * server cannot be reached from the host the request arrived on.
     */
    protected const UNREACHABLE_HOT_FILE = 'hot.unreachable';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->useBuiltAssetsWhenDevServerIsUnreachable();
    }

    /**
     * The Vite dev server writes its public URL to public/hot, and @vite emits
     * asset tags pointing at that absolute URL. That URL is only reachable on
     * the machine serving the app, so every other entry point (a Cloudflare
     * tunnel, a LAN IP, a second device) gets a stylesheet request that never
     * resolves and renders unstyled pages.
     *
     * When the hot file points at a different host than the one serving the
     * request, the dev server is unreachable by definition, so fall back to the
     * built manifest, which the app origin always serves.
     */
    protected function useBuiltAssetsWhenDevServerIsUnreachable(): void
    {
        if (! Vite::isRunningHot()) {
            return;
        }

        $hotUrl = trim((string) file_get_contents(Vite::hotFile()));

        if ($this->devServerIsReachable($hotUrl)) {
            return;
        }

        Vite::useHotFile(public_path(static::UNREACHABLE_HOT_FILE));
    }

    /**
     * Determine whether the browser can load assets from the dev server URL.
     *
     * The dev server URL must match the host that served the page, otherwise
     * the browser resolves it against a name that only exists on the machine
     * running Vite. That holds for every entry point that is not literally the
     * dev host itself: a Cloudflare tunnel, a LAN IP, or an SSH forward all
     * reach this app under a different name than the hot file records.
     *
     * Note the client IP cannot be used as a signal. A tunnel or port forward
     * on the same machine also connects from loopback, so a loopback client is
     * not evidence that the dev host resolves for it.
     */
    protected function devServerIsReachable(string $hotUrl): bool
    {
        $devServerHost = parse_url($hotUrl, PHP_URL_HOST);

        if (! is_string($devServerHost) || $devServerHost === '') {
            return false;
        }

        return strcasecmp($devServerHost, request()->getHost()) === 0;
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
