<?php

use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\RequireNoUsers;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Honour X-Forwarded-* from the sapolski nginx proxy that terminates
        // TLS for rekodo.loncarek.net; without this the app builds http:// URLs
        // for requests that arrived over https.
        $middleware->trustProxies(at: [
            '192.168.13.126',
        ]);

        $middleware->appendToGroup('web', [
            RequireNoUsers::class,
            ForcePasswordChange::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
