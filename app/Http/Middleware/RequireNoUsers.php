<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireNoUsers
{
    /**
     * Handle an incoming request.
     *
     * Redirects login to register when no users exist.
     * Aborts with 404 if any users exist and the request targets a registration route.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();
        $hasUsers = User::query()->exists();

        if (! $hasUsers && in_array($routeName, ['login', 'login.store'], true)) {
            return redirect()->route('register');
        }

        if ($hasUsers && in_array($routeName, ['register', 'register.store'], true)) {
            abort(404);
        }

        return $next($request);
    }
}
