<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * Redirects authenticated users with force_password_change=true to the password change page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check() || ! auth()->user()->force_password_change) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName === 'user-password.edit') {
            return $next($request);
        }

        if ($routeName === 'logout') {
            return $next($request);
        }

        // Livewire requests must pass through untouched. Identifying them by URL
        // path is a trap: the endpoint is a hashed prefix (livewire-<hash>/update),
        // so a 'livewire/update' substring never matches and the client receives
        // this redirect's HTML instead of JSON, failing the update silently.
        // Livewire stamps every one of its own requests with X-Livewire.
        if ($request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        return redirect()->route('user-password.edit')
            ->with('status', __('You must change your password before continuing.'));
    }
}
