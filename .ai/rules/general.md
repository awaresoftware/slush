---
paths:
  - '**/*.php'
---

# General

## Never detect Livewire requests by URL path — use the X-Livewire header
Livewire 4 serves its update endpoint from a build-hashed prefix — `livewire-<hash>/update` (route name `default-livewire.update`), verified with `php artisan route:list`. So `str_contains($request->path(), 'livewire/update')` NEVER matches and any middleware using it silently 302-redirects every Livewire XHR. The client then gets HTML where it expects JSON, Livewire throws "SyntaxError: JSON Parse error: Unrecognized token '<'", and the action is dropped with no visible error. Detect Livewire requests with `$request->hasHeader('X-Livewire')` — Livewire stamps every one of its own requests with it (see HandleRequests.php). Corollary: Livewire's Pest harness calls component methods in-process and never runs the middleware stack over the update endpoint, so it cannot catch middleware bugs on that path; a real `$this->postJson(route-path, [...])` test is required.
