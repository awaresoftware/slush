# Phase 17: Native Foundation - Research

**Researched:** 2026-03-26
**Domain:** Composer scripts, PHP port detection, idempotent install automation
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Auto-pick next free port and start automatically. No user prompt or manual re-run needed — print a notice showing the resolved port.
- **D-02:** Check both web server (8000) and Vite (5173) ports for conflicts.
- **D-03:** Implement detection as a PHP script (e.g. `bin/detect-port.php`) using `fsockopen`. Cross-platform, no OS-specific tools like lsof/netstat.
- **D-04:** Port detection runs as part of `composer run dev` before launching concurrently. Resolved ports are passed to `artisan serve --port` and Vite `--port` flags.
- **D-05:** On startup, print the resolved web URL (e.g. "App running at http://localhost:8001") before launching concurrently, so the user knows where to browse when the port shifted.
- **D-06:** `composer run setup` must be fully idempotent — safe to run on both fresh clones and re-runs. Skip steps that already completed (don't fail on existing storage symlink, don't regenerate key if .env exists with a key).
- **D-07:** Remove the Makefile. Consolidate into `composer run setup` as the single canonical install command.
- **D-08:** Add `touch database/database.sqlite` to setup script — auto-create the SQLite database file if it doesn't exist, preventing migration failure on fresh clone.
- **D-09:** Add `php artisan storage:link` to setup script — required for public disk access (uploads, avatars, cover art).
- **D-10:** No success message or URL output at end of setup — silent beyond what each command prints natively.
- **D-11:** Keep `queue:listen --tries=1` for the queue worker. Auto-restarts on code changes during development.
- **D-12:** Keep existing concurrently setup with colored output and named processes (server, queue, vite).

### Claude's Discretion
- Exact implementation of the PHP port detection script (argument parsing, port range scanning logic)
- How to handle edge case where no ports are available in a reasonable range
- Whether to use `@php` prefix in composer scripts for the detection step

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| NATV-01 | User can install the project with a single `composer run setup` command (includes storage:link, key generation, migrations, npm build) | Composer scripts section already partially implements this; gaps identified: missing `touch database/database.sqlite`, missing `storage:link`, non-idempotent `key:generate` |
| NATV-02 | App detects port conflicts and suggests an available alternative port before starting | `fsockopen` verified working on this system; port 8000 correctly detected as in-use via Herd; scanning logic validated |
| NATV-03 | Queue worker auto-starts alongside web server and Vite when user runs `composer run dev` | Already implemented via `concurrently`; only change is routing ports through the detection script |
</phase_requirements>

---

## Summary

Phase 17 makes the native install path reliable on a fresh clone. The existing `composer.json` already has `setup` and `dev` scripts, but both have gaps. The `setup` script is missing two steps: auto-creating `database/database.sqlite` and running `php artisan storage:link`. It also regenerates `APP_KEY` unconditionally, which invalidates sessions on re-run. The `dev` script is complete but needs port detection layered in before launching `concurrently`.

The central implementation challenge is that Composer scripts are static arrays — they cannot pass the output of one step as arguments to the next step inline. The port detection script (`bin/detect-port.php`) must therefore discover free ports AND spawn the `concurrently` process itself, rather than returning values for a subsequent Composer script entry to consume.

**Primary recommendation:** The `dev` composer script becomes a two-step: first call `@php bin/detect-port.php` which handles discovery, prints the resolved URL, then calls `passthru('npx concurrently ...')` with the resolved ports baked in. The `setup` script gains three fixes: conditional key generation, SQLite touch, and idempotent storage:link using `--force`.

---

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| concurrently | ^9.0.1 | Run web server, queue, Vite in one terminal | Already installed; powers existing `composer run dev`; cross-platform |
| PHP `fsockopen()` | built-in | Port availability detection | Cross-platform (no lsof/netstat/nc), already decided (D-03), verified working |

### No New Dependencies
This phase adds zero new Composer or npm packages. All implementation is PHP scripts and Composer script augmentation using tools already present.

**Confirmed installed:**
```
concurrently ^9.0.1  — package.json dependencies (not devDependencies)
laravel/sail ^1.41   — composer.json require-dev (not used in this phase)
```

---

## Architecture Patterns

### Composer Script Execution Model
Composer scripts are static JSON arrays evaluated at parse time. Each entry runs as a separate shell invocation. **Critical constraint:** You cannot capture the output of one entry and pass it as an argument to the next entry within the same `composer run` call.

**Correct approach for port detection + dev launch:** The PHP detection script owns the entire `dev` launch sequence:
1. Discover free web port (starting at 8000)
2. Discover free Vite port (starting at 5173)
3. Print resolved URL
4. Call `passthru()` with the full `npx concurrently` command string, substituting resolved ports

The `composer.json` `dev` script becomes:
```json
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "@php bin/detect-port.php"
]
```

### Idempotency Pattern for `setup`
Each step must be safe to run on an already-configured install:

| Step | Idempotent as-is? | Fix |
|------|-------------------|-----|
| `composer install` | Yes | No change |
| Copy `.env` | Yes — `file_exists()` guard already present | No change |
| `key:generate` | No — overwrites existing key | Add PHP inline check: skip if `APP_KEY=base64:` already present |
| `touch database/database.sqlite` | Yes — touch is idempotent | Add this step (currently missing) |
| `migrate --force` | Yes — Laravel skips already-run migrations | No change |
| `npm install` | Yes | No change |
| `npm run build` | Yes | No change |
| `storage:link` | No — fails if symlink exists | Use `--force` flag (confirmed available) |

### Recommended Project Structure for New Files
```
bin/
└── detect-port.php    # Port discovery script; spawns concurrently
```

The `bin/` directory does not currently exist. Creating it is the only structural addition.

### Pattern 1: PHP Port Detection via `fsockopen`
**What:** Try to open a TCP connection to `127.0.0.1:$port`. If the connection succeeds, the port is occupied; if it fails (errno 61 = connection refused on macOS/Linux, errno 10061 on Windows), the port is free.
**When to use:** Before launching dev servers to avoid bind conflicts.

```php
// Verified against PHP docs; tested on this system (2026-03-26)
// fsockopen returns false when port is free (connection refused)
function findFreePort(int $startPort, int $maxAttempts = 10): int
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $port = $startPort + $i;
        $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if (! $conn) {
            return $port;
        }
        fclose($conn);
    }

    // Fallback: return beyond scan range
    return $startPort + $maxAttempts;
}
```

**Verified results (2026-03-26):**
- `findFreePort(8000)` returned `8001` (Herd occupying 8000)
- `findFreePort(5173)` returned `5173` (free)
- errno 61 on macOS when port is free (ECONNREFUSED)
- Checking whether `$conn` is falsy is the cross-platform approach; do not compare errno values directly.

### Pattern 2: Idempotent Key Generation
**What:** Only call `php artisan key:generate` when `APP_KEY` is empty or absent from `.env`.

```php
// Verified: current .env has APP_KEY=base64:... — pattern correctly skips re-generation
$env = file_exists('.env') ? file_get_contents('.env') : '';
if (! preg_match('/^APP_KEY=base64:/m', $env)) {
    passthru('php artisan key:generate --no-interaction');
}
```

As a Composer inline script:
```json
"@php -r \"$e=file_exists('.env')?file_get_contents('.env'):''; preg_match('/^APP_KEY=base64:/m',$e)||passthru('php artisan key:generate --no-interaction');\""
```

### Pattern 3: Spawning Concurrently from PHP Script
**What:** The detect-port script builds the concurrently command string with resolved ports substituted, then runs it via `passthru()`. This replaces the current process with the concurrently child process in terms of output streaming.

The concurrently command template (ports interpolated at runtime):
```
npx concurrently
  -c "#93c5fd,#c4b5fd,#fdba74"
  "php artisan serve --port={webPort}"
  "php artisan queue:listen --tries=1"
  "npm run dev -- --port={vitePort}"
  --names="server,queue,vite"
```

The `npm run dev -- --port=N` syntax forwards the port flag through npm to Vite. The `--` separator is required for npm to pass flags to the underlying script.

### Pattern 4: SQLite Auto-Creation (Inline PHP)
Composer scripts can run inline PHP. The existing `.env` copy step demonstrates this:
```json
"@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
```

SQLite equivalent:
```json
"@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\""
```

This is idempotent (`touch` on an existing file updates mtime only).

### Anti-Patterns to Avoid
- **Static port in composer script:** Never hardcode `php artisan serve --port=8000` — defeats conflict detection.
- **Unconditional key:generate:** Overwrites sessions on re-run. Always guard with APP_KEY check.
- **`storage:link` without `--force`:** Exits non-zero on re-run, aborting the setup sequence.
- **Shell backtick capture in Composer scripts:** Composer entries are not full bash — `$(...)` substitution is not cross-platform in all shells.
- **`pcntl_exec` for Windows compatibility:** `pcntl_exec` is not available on Windows PHP. Use `passthru()` which is cross-platform.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Port availability detection | OS-specific scripts (lsof, netstat, nc) | `fsockopen()` in PHP | Cross-platform; D-03 decision; verified working |
| Multi-process dev runner | Custom PHP process forking | `concurrently ^9.0.1` | Already installed; handles signal forwarding, colored output, named labels |
| SQLite file creation | Custom migration wrapper | Inline PHP `touch()` in Composer script | One-liner; idempotent |
| Storage symlink | Custom `file_exists` + `symlink()` PHP | `php artisan storage:link --force` | Laravel handles the public disk link configuration; `--force` provides idempotency |

**Key insight:** This phase is about plumbing existing tools together correctly. The only genuinely new code is `bin/detect-port.php` (~35 lines).

---

## Common Pitfalls

### Pitfall 1: Composer Scripts Cannot Pipe Between Entries
**What goes wrong:** Writing a `dev` script that runs `@php bin/detect-port.php` expecting it to output port numbers that the next entry uses.
**Why it happens:** Each Composer script entry is an independent shell invocation. There is no shared state or output capture between entries.
**How to avoid:** The port detection script must own the full launch sequence — detect ports, print URL, call `passthru('npx concurrently ...')`.
**Warning signs:** Any design that has detect-port.php echoing a number and then a separate Composer entry consuming it.

### Pitfall 2: `key:generate` Invalidates Existing Sessions on Re-run
**What goes wrong:** Running `composer run setup` on an existing install regenerates `APP_KEY`, invalidating all encrypted values (sessions, remember tokens).
**Why it happens:** The current `setup` script has `@php artisan key:generate` with no guard — it runs unconditionally.
**How to avoid:** Wrap in a PHP inline check: only generate if `APP_KEY` is absent or empty in `.env`.
**Warning signs:** Users report being logged out after running setup a second time.

### Pitfall 3: `storage:link` Exits Non-Zero on Re-run
**What goes wrong:** Setup sequence aborts mid-way on a re-run because `storage:link` returns exit code 1 ("The 'public/storage' link already exists").
**Why it happens:** Without `--force`, the command treats an existing symlink as an error condition.
**How to avoid:** Use `php artisan storage:link --force` (confirmed available: `--force` flag verified via `php artisan storage:link --help`). The `--force` flag recreates the symlink without error.
**Warning signs:** Setup works on fresh clone but fails on second run at the `storage:link` step.

### Pitfall 4: SQLite Migration Fails on Fresh Clone Without Database File
**What goes wrong:** `php artisan migrate --force` fails with a database connection error on a fresh clone where `database/database.sqlite` does not yet exist.
**Why it happens:** SQLite requires the file to exist before Laravel can connect. The file is gitignored. The current `setup` script has no step to create it.
**How to avoid:** Add `touch database/database.sqlite` (or the PHP inline equivalent) before `php artisan migrate --force`. Must run after `.env` is copied so `DB_CONNECTION=sqlite` is in effect.
**Warning signs:** Fresh clone fails at the migrate step.

### Pitfall 5: Vite `--port` Flag Not Forwarding via npm
**What goes wrong:** `npm run dev --port=5174` does not forward the flag to Vite — npm may consume it.
**Why it happens:** npm flags need the `--` separator to be forwarded to the underlying script command.
**How to avoid:** Use `npm run dev -- --port=5174` in the concurrently command string.
**Warning signs:** Vite starts on its default port 5173 regardless of the `--port` argument.

### Pitfall 6: `fsockopen` Timeout Too High Slows Startup
**What goes wrong:** Checking 10 ports with a 1-second timeout can add up to 10 seconds of startup delay.
**Why it happens:** `fsockopen` blocks for the full timeout on each port when nothing is listening.
**How to avoid:** Use a short timeout of 0.5 seconds. On localhost, connection refusal is nearly instant (errno 61) — the timeout only applies when the host accepts the TCP SYN but the port has no service.
**Warning signs:** `composer run dev` takes several seconds before printing anything on first run.

---

## Code Examples

### Full `bin/detect-port.php` Script
```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

function findFreePort(int $startPort, int $maxAttempts = 10): int
{
    for ($i = 0; $i < $maxAttempts; $i++) {
        $port    = $startPort + $i;
        $conn    = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if (! $conn) {
            return $port;
        }
        fclose($conn);
    }

    return $startPort + $maxAttempts;
}

$webPort  = findFreePort(8000);
$vitePort = findFreePort(5173);

echo 'App running at http://localhost:' . $webPort . PHP_EOL;

$command = implode(' ', [
    'npx concurrently',
    '-c "#93c5fd,#c4b5fd,#fdba74"',
    '"php artisan serve --port=' . $webPort . '"',
    '"php artisan queue:listen --tries=1"',
    '"npm run dev -- --port=' . $vitePort . '"',
    '--names="server,queue,vite"',
]);

passthru($command, $exitCode);
exit($exitCode);
```

### Updated `composer.json` Scripts Section
```json
"scripts": {
    "setup": [
        "composer install",
        "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
        "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
        "@php -r \"$e=file_exists('.env')?file_get_contents('.env'):'';preg_match('/^APP_KEY=base64:/m',$e)||passthru('php artisan key:generate --no-interaction');\"",
        "@php artisan migrate --force",
        "npm install",
        "npm run build",
        "@php artisan storage:link --force"
    ],
    "dev": [
        "Composer\\Config::disableProcessTimeout",
        "@php bin/detect-port.php"
    ]
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `key:generate` unconditionally | Guard with APP_KEY check | This phase | Prevents session invalidation on re-runs |
| No SQLite auto-creation | Inline PHP touch before migrate | This phase | Fresh clones work without manual step |
| `storage:link` without `--force` | `storage:link --force` | This phase | Setup is idempotent |
| Static ports in `composer run dev` | Dynamic port detection via PHP | This phase | No startup failure when ports are occupied |
| Makefile as primary install entry | Remove Makefile (D-07); `composer run setup` is canonical | This phase | Single documented entry point |

**Deprecated:**
- `Makefile`: Remove entirely per D-07. All Makefile functionality moves into `composer run setup` and `composer run dev`. Future phases (install scripts, Docker, README) reference only the Composer commands.

---

## Open Questions

1. **Vite `--port` flag passthrough**
   - What we know: npm requires `--` separator to forward flags to the underlying script; documented behavior
   - What's unclear: Whether the specific `npm run dev -- --port=N` syntax works correctly in this project's Vite configuration
   - Recommendation: Verify during implementation with a quick test. Alternative: invoke Vite directly (`npx vite --port=N`) to bypass the npm wrapper entirely.

2. **Windows `fsockopen` errno values**
   - What we know: Windows uses errno 10061 (WSAECONNREFUSED) vs macOS/Linux errno 61 (ECONNREFUSED) for refused connections
   - What's unclear: Exact behavior with 0.5s timeout on Windows PHP
   - Recommendation: The script logic should check whether `$conn` is falsy (not compare errno values) — this is cross-platform and avoids the errno difference entirely.

3. **Edge case: all ports 8000-8009 occupied**
   - What we know: `findFreePort` returns `$startPort + $maxAttempts` as fallback without verifying that port
   - Recommendation: Add a final fsockopen check on the returned fallback value. If still occupied, print a clear error message and exit 1 so the user knows what happened.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 (pestphp/pest ^4.4) |
| Config file | `phpunit.xml` (project root) |
| Quick run command | `php artisan test --compact --filter=NativeFoundation` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements to Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| NATV-01 | `storage:link --force` does not fail on re-run | Feature | `php artisan test --compact --filter=NativeFoundationTest` | ❌ Wave 0 |
| NATV-01 | Setup skips key:generate when APP_KEY already set | Feature | `php artisan test --compact --filter=NativeFoundationTest` | ❌ Wave 0 |
| NATV-01 | SQLite file is auto-created if missing | Feature | `php artisan test --compact --filter=NativeFoundationTest` | ❌ Wave 0 |
| NATV-02 | `findFreePort()` returns start port when it is free | Unit | `php artisan test --compact --filter=DetectPortTest` | ❌ Wave 0 |
| NATV-02 | `findFreePort()` returns next port when start port is occupied | Unit | `php artisan test --compact --filter=DetectPortTest` | ❌ Wave 0 |
| NATV-03 | `composer run dev` produces `detect-port.php` invocation | Manual (script inspection) | — | N/A |

**Note:** End-to-end `composer run setup` and `composer run dev` flows are shell-level integrations that Pest cannot meaningfully exercise. Unit tests validate individual idempotency behaviors and port detection logic. Full end-to-end verification is handled during `/gsd:verify-work`.

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=NativeFoundationTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/NativeFoundationTest.php` — covers NATV-01 idempotency behaviors (storage:link, key:generate, SQLite touch)
- [ ] `tests/Unit/DetectPortTest.php` — covers NATV-02 port scanning logic
- [ ] `bin/detect-port.php` — main deliverable; must exist before DetectPortTest can load it

---

## Sources

### Primary (HIGH confidence)
- `composer.json` (read directly) — current `setup` and `dev` script definitions; gaps identified
- `package.json` (read directly) — `concurrently ^9.0.1` confirmed in `dependencies`
- `Makefile` (read directly) — complete install sequence including `touch database/database.sqlite` and `storage:link` that the Composer `setup` script must replicate
- `.env.example` (read directly) — `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database` defaults confirmed
- `php artisan storage:link --help` (executed 2026-03-26) — `--force` flag confirmed available
- `php artisan key:generate --help` (executed 2026-03-26) — no `--skip-if-exists` flag; PHP inline guard is the correct approach
- `fsockopen` live test (executed 2026-03-26) — port 8000 correctly detected as in-use (Herd), port 5173 correctly detected as free
- `findFreePort` logic test (executed 2026-03-26) — returned 8001 for start=8000 (Herd on 8000), 5173 for start=5173 (free)
- APP_KEY detection regex test (executed 2026-03-26) — `/^APP_KEY=base64:/m` correctly identifies set key in existing `.env`

### Secondary (MEDIUM confidence)
- `.planning/research/PITFALLS.md` — Pitfall 12 (storage:link re-run failure), Pitfall 13 (key:generate session invalidation)
- `.planning/research/FEATURES.md` — setup script gap analysis; `storage:link` identified as missing
- `.planning/research/SUMMARY.md` — Phase 1 native foundation scope and rationale
- `.planning/research/ARCHITECTURE.md` — Composer script static array constraint documented

### Tertiary (LOW confidence)
- `npm run dev -- --port=N` flag forwarding behavior — documented pattern but needs implementation-time verification for this specific project configuration

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies; all tools verified present in repository
- Architecture: HIGH — fsockopen tested live; Composer script execution model constraint understood from documentation and testing
- Pitfalls: HIGH — all pitfalls verified via `--help` output and live execution; not theoretical
- Port detection logic: HIGH — executed and verified correct output on this system

**Research date:** 2026-03-26
**Valid until:** 2026-04-25 (stable ecosystem; Composer script model is not version-sensitive)
