# Foonk — agent notes

Self-hosted music streaming server. Laravel 13 + Livewire 4 + Mary UI (daisyUI) + Tailwind 4, Pest 4, SQLite natively / MySQL+Redis under Docker. README covers install and user-facing config; this file covers what an agent gets wrong.

## This file is partly generated

The `laravel-boost-guidelines` block at the bottom of this file is written by `php artisan boost:update` (also run automatically by `composer post-update-cmd`). It is **replaced wholesale** — never edit inside it, and never mention its delimiters anywhere above it either (Boost's writer matches the first opening delimiter it finds). Put project notes above the block.

## Run commands natively, not through Sail

The generated Boost guidelines say every command must be prefixed with `vendor/bin/sail`. That is stale for this checkout: Docker is not running, `boost.json` still has `"sail": true`, and the app is developed natively (`composer run dev`, Herd at `https://foonk.test`, SQLite). Use:

```bash
php artisan <cmd>            # not vendor/bin/sail artisan
vendor/bin/pest              # or php artisan test --compact
npm run build                # or npm run dev
```

## Commands

```bash
composer run setup        # install deps, .env, sqlite file, key, migrate, npm install+build, storage:link
composer run dev          # bin/detect-port.php → artisan serve + queue:listen + vite, all on auto-picked free ports
php artisan test --compact                                   # ~25s, ~765 tests
php artisan test --compact tests/Feature/Library/TrackUploadTest.php
php artisan test --compact --filter=testName
vendor/bin/pint --dirty                                       # format only what you touched
curl http://localhost:8000/healthz                            # or https://foonk.test/healthz
```

`composer run test` (and `composer run test:lint`) currently **fails on a clean checkout**: pint 1.30.5 wants `fully_qualified_strict_types` / `single_blank_line_at_eof` / `not_operator_with_successor_space` on ~20 committed files. Not your change. Use `vendor/bin/pint --dirty` and `php artisan test`.

`tests/Feature/Artwork/ArtworkSearchTest.php > a failed search` also fails on `main` (test calls the now-private `ArtworkSearch::cacheKey()`). Pre-existing; don't chase it.

Tests that render `@vite` need `public/build/manifest.json`. If it is missing, run `npm run build` or you get `ViteManifestNotFoundException`.

## Where things live

| Concern | Location |
|---|---|
| Full-page Livewire components | `resources/views/pages/**`, routed `Route::livewire('path', 'pages::name')` in `routes/web.php` + `routes/settings.php` |
| Class-based components (player, modals, palette) | `app/Livewire/*.php` + view in `resources/views/livewire/*.blade.php`, used as `<livewire:audio-player />` |
| Anonymous UI components | `resources/views/components/ui/*.blade.php`, used as `<x-ui.page-header>` |
| Fortify auth pages | `resources/views/pages/auth/*.blade.php` — plain Blade, no component class |
| Theme + accent CSS (4 custom daisyUI themes) | `resources/css/app.css` |
| App config | `config/foonk.php` |
| Custom console commands | `routes/console.php` (`albums:fetch-missing-artwork`, `albums:backfill-artwork`, `tracks:recompute-peaks`) |

- The `⚡` prefix on page filenames is stripped by Livewire's finder, so `⚡index.blade.php` and `index.blade.php` resolve to the same `pages::<dir>.<name>`. Both spellings exist in the repo; either works.
- `config/livewire.php` limits component lookup to `resources/views/components` and `resources/views/livewire`, with `layouts` and `pages` namespaces. A component outside those paths will not resolve.
- Adding a blade or vendor component dir means adding a matching `@source` line to `resources/css/app.css`, or Tailwind 4 will not generate its classes.

## Domain invariants

- **Two track sources.** `SourceType::Uploaded` → relative path on the `public` disk (`music/{userId}/...`); `SourceType::Local` → an absolute path that must sit inside the owning `LinkedFolder`. `Track::absolutePath()` is a path *resolver*, not authorization. Anything serving the file (`StreamController`) must verify containment itself; the `stream` policy gate does not cover it.
- `config/foonk.php` → `audio_extensions` is the single source of truth for what a folder scan imports. `.m4p` is deliberately excluded (DRM'd iTunes files). Change it there, not in the job.
- Jobs (`ScanLinkedFolder`, `ExtractAudioMetadata`, `FetchAlbumArtwork`) need `php artisan queue:listen` running or uploads will sit forever with no metadata. `composer run dev` starts it. Tests use `QUEUE_CONNECTION=sync`.
- Waveform peaks need `ffmpeg`/`ffprobe` on PATH or `FFMPEG_PATH`/`FFPROBE_PATH`. When absent, extraction is skipped and the player falls back to a plain scrubber — nothing errors, nothing is half-written.
- First registered user becomes admin; `/register` 404s once any user exists (`RequireNoUsers` middleware).
- `ForcePasswordChange` middleware must detect Livewire via the `X-Livewire` header, never a URL path — see `.ai/rules/general.md` for the full reasoning and the required test shape.

## Tests

- `tests/Pest.php` applies `RefreshDatabase` to all of `tests/Feature`; `tests/Unit` gets nothing.
- `Storage::fake('public')` roots at `storage/framework/testing/disks/public` — `albums:backfill-artwork` allowlists that path explicitly so it stays testable.
- Several suites assert against **real repo files** using `dirname(__DIR__, 2)` instead of `base_path()` (worktree-safe): `AppShellTest`, `AuthLayoutTest`, `PlayerSurfaceTest`, `BashInstallScriptTest`, `PowerShellInstallScriptTest`, `DockerConfigurationTest`, `CssFoundationTest`. Editing `install.sh`, `install.ps1`, `compose.yaml`, or `app.css` means updating the matching test.
- Livewire's Pest harness calls component methods in-process and never runs the middleware stack over the update endpoint, so middleware bugs on that path need a real `$this->postJson(...)` test.
- CI (`.github/workflows/tests.yml`) runs PHP 8.4 **and** 8.5: `cp .env.example .env`, `key:generate`, `npm run build`, then `./vendor/bin/pest`. Lint CI runs `composer lint` (`pint --parallel`), which only checks bare `.php` — pint silently skips `*.blade.php`.

## Read before editing

`.ai/rules/index.md` maps file globs to rule files (currently `general.md` for `**/*.php`, `views.md` for blade). Read the matching ones first — the blade one explains why passing a `.blade.php` path to pint causes unreviewable churn. Record new durable traps with Boost's `record-rule` so they land in `.ai/rules`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.4. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `vendor/bin/sail npm run build`, `vendor/bin/sail npm run dev`, or `vendor/bin/sail composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `vendor/bin/sail artisan route:list`). Use `vendor/bin/sail artisan list` to discover available commands and `vendor/bin/sail artisan [command] --help` to check parameters.
- Inspect routes with `vendor/bin/sail artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `vendor/bin/sail artisan config:show app.name`, `vendor/bin/sail artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `vendor/bin/sail artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `vendor/bin/sail artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== sail rules ===

# Laravel Sail

- This project runs inside Laravel Sail's Docker containers. You MUST execute all commands through Sail.
- Start services using `vendor/bin/sail up -d` and stop them with `vendor/bin/sail stop`.
- Open the application in the browser by running `vendor/bin/sail open`.
- Always prefix PHP, Artisan, Composer, and Node commands with `vendor/bin/sail`. Examples:
    - Run Artisan Commands: `vendor/bin/sail artisan migrate`
    - Install Composer packages: `vendor/bin/sail composer install`
    - Execute Node commands: `vendor/bin/sail npm run dev`
    - Execute PHP scripts: `vendor/bin/sail php [script]`
- View all available Sail commands by running `vendor/bin/sail` without arguments.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `vendor/bin/sail artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `vendor/bin/sail artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `vendor/bin/sail artisan list` and check their parameters with `vendor/bin/sail artisan [command] --help`.
- If you're creating a generic PHP class, use `vendor/bin/sail artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `vendor/bin/sail artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `vendor/bin/sail artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `vendor/bin/sail npm run build` or ask the user to run `vendor/bin/sail npm run dev` or `vendor/bin/sail composer run dev`.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/sail bin pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/sail bin pint --test --format agent`, simply run `vendor/bin/sail bin pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `vendor/bin/sail artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `vendor/bin/sail artisan make:test --pest SomeFeatureTest` instead of `vendor/bin/sail artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `vendor/bin/sail artisan test --compact` or filter: `vendor/bin/sail artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
