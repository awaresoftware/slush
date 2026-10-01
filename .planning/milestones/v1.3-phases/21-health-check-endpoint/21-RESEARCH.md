# Phase 21: Health Check Endpoint - Research

**Researched:** 2026-03-27
**Domain:** Laravel HTTP endpoint, database connectivity check, Docker healthcheck, shell/PowerShell scripting
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- **D-01:** Detailed JSON response including `status`, `database` (with status and latency_ms), `version`, and `php` version
- **D-02:** App version sourced from `config('app.version')` — add a `version` key to `config/app.php`
- **D-03:** Return HTTP 503 Service Unavailable when database is unreachable (not 500)
- **D-04:** On failure, response body still includes full JSON structure with `"status": "error"` and `"database.latency_ms": null`
- **D-05:** 3-second timeout on database connectivity check to prevent hanging on dead DB
- **D-06:** Install scripts (install.sh, install.ps1) should call `/healthz` after `composer run setup` completes to verify app + DB are working, printing clear success/failure message
- **D-07:** Docker compose should add a healthcheck to the web container that curls `/healthz`, validating the full stack (PHP + DB)

### Claude's Discretion
- Database connectivity method (SELECT 1, connection ping, etc.)
- Exact placement of health verification in install scripts (after which step)
- Docker compose healthcheck timing parameters (interval, retries, start_period)

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| HLTH-01 | `/healthz` endpoint verifies app and database connectivity, used by install scripts to confirm successful setup | Invokable controller pattern, `DB::connection()->getPdo()` for connectivity, 503 on failure, JSON response structure, script curl patterns |
</phase_requirements>

---

## Summary

This phase adds a single `/healthz` GET endpoint outside the auth middleware that returns detailed JSON about application and database health. The endpoint must return HTTP 200 on success and HTTP 503 when the database is unreachable. All three delivery surfaces must be updated: the Laravel route/controller, install scripts (Bash and PowerShell), and Docker Compose.

The implementation is straightforward. Laravel's `DB::connection()->getPdo()` wrapped in a try/catch with a 3-second socket timeout is the standard connectivity probe. The invokable controller pattern used by `StreamController` and `BrowseDirectoriesController` is the correct shape. The Docker healthcheck follows the identical block format already used by the MySQL and Redis services in `compose.yaml`.

The install script integrations require care: `install.sh` runs under `set -euo pipefail`, so the health check curl call must NOT exit the script on failure — it should report a warning, not abort, since the user may still want to start the dev server and diagnose from there.

**Primary recommendation:** Invokable controller at `App\Http\Controllers\HealthCheckController`, route registered before all middleware groups in `routes/web.php`, database probe via `DB::connection()->getPdo()` in a try/catch, latency measured with `microtime(true)`.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Laravel HTTP layer | v12 | `response()->json()` with HTTP status code | Built-in, no external dep needed |
| `Illuminate\Support\Facades\DB` | v12 | Database connectivity probe | Standard Laravel DB facade |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `microtime(true)` | PHP built-in | Measure DB latency in milliseconds | Precision timing before/after PDO call |
| `PHP_VERSION` constant | PHP 8.4 | Include PHP version in response | No config lookup needed |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `DB::connection()->getPdo()` | `DB::select('SELECT 1')` | `getPdo()` is a lower-level connection test — no query execution overhead, just establishes the connection. `SELECT 1` also works but is slightly heavier. |
| Native PHP socket timeout via `PDO` options | Laravel `DB_TIMEOUT` env | PDO socket timeout can be set per-connection in the try block; simpler than a config change for a one-off probe |

**Installation:** No new packages required.

---

## Architecture Patterns

### Recommended Project Structure

```
app/
└── Http/
    └── Controllers/
        └── HealthCheckController.php   # New invokable controller
routes/
└── web.php                             # New Route::get('/healthz', ...) before auth group
config/
└── app.php                             # Add 'version' key
tests/
└── Feature/
    └── HealthCheckTest.php             # New feature test
```

### Pattern 1: Invokable Controller (matches existing codebase)

**What:** Single `__invoke()` method on a controller class, referenced directly in route registration.
**When to use:** Single-action endpoints — this is the established pattern for `StreamController` and `BrowseDirectoriesController`.

```php
// Source: existing app/Http/Controllers/BrowseDirectoriesController.php pattern
namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $start = microtime(true);
        $dbStatus = 'ok';
        $dbLatency = null;

        try {
            DB::connection()->getPdo();
            $dbLatency = (int) round((microtime(true) - $start) * 1000);
        } catch (\Throwable $e) {
            $dbStatus = 'error';
        }

        $status = $dbStatus === 'ok' ? 'ok' : 'error';
        $httpCode = $status === 'ok' ? 200 : 503;

        return response()->json([
            'status'   => $status,
            'version'  => config('app.version'),
            'php'      => PHP_VERSION,
            'database' => [
                'status'     => $dbStatus,
                'latency_ms' => $dbLatency,
            ],
        ], $httpCode);
    }
}
```

### Pattern 2: Route Registration Outside Auth Middleware

**What:** Register the `/healthz` route before the `Route::middleware(['auth'])->group()` block in `routes/web.php`, with no middleware wrapper.
**When to use:** Public endpoints that must be reachable without authentication.

```php
// Source: routes/web.php existing structure
use App\Http\Controllers\HealthCheckController;

Route::get('/healthz', HealthCheckController::class)->name('healthz');

// ... existing auth middleware group below
Route::middleware(['auth'])->group(function () {
    // ...
});
```

### Pattern 3: Docker Healthcheck Block (matches existing MySQL/Redis format)

**What:** Add a `healthcheck` block to the `laravel.test` service in `compose.yaml` using `curl` against `/healthz`.
**When to use:** Web service healthcheck — identical format to MySQL and Redis already in the file.

```yaml
# Source: compose.yaml existing healthcheck blocks
healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost/healthz"]
    retries: 5
    timeout: 5s
    interval: 10s
    start_period: 60s
```

`start_period: 60s` is appropriate for the web container because it depends on MySQL being healthy first (30s start_period on MySQL), then runs migrations via `docker/start.sh`. 60s gives the full chain room to complete before healthcheck failures count.

### Pattern 4: Bash Health Verification (install.sh)

**What:** After `composer run setup`, curl the `/healthz` endpoint, parse the HTTP status, and print success/failure. Must NOT use `set -e` exit behaviour on failure — use `|| true` or a conditional to handle gracefully.
**When to use:** After setup completes, before prompting to start dev server.

```bash
# Source: install.sh existing print_ok/print_fail/print_warn helper pattern
verify_health() {
    print_header "Verifying installation..."

    # APP_URL may not be set; default to localhost:8000 (composer run dev port)
    local app_url
    app_url="${APP_URL:-http://localhost:8000}"

    local http_code
    http_code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 "${app_url}/healthz" 2>/dev/null) || http_code="000"

    if [ "$http_code" = "200" ]; then
        print_ok "App and database are healthy."
    else
        print_warn "Health check returned HTTP $http_code. The app may still be starting."
        print_warn "Run: curl ${app_url}/healthz  — to check manually once the server is running."
    fi
}
```

Note: The health check in install scripts runs BEFORE the dev server is started (`exec composer run dev` is the last step). The server is not running yet at verification time. The planner must decide whether to skip health check in scripts entirely, or only run it if a background server is already running. **Recommended approach:** Print a deferred note rather than performing a live curl — the dev server isn't running at the point `composer run setup` completes in the install scripts. Alternatively, the health check step can be documented as a post-start verification hint rather than a blocking check.

### Pattern 5: PowerShell Health Verification (install.ps1)

**What:** After `composer run setup`, invoke the health endpoint using `Invoke-WebRequest` or `curl.exe`.
**When to use:** Same placement as Bash version.

```powershell
# Source: install.ps1 existing Write-Ok/Write-Fail/Write-Warn pattern
function Verify-Health {
    Write-Header "Verifying installation..."
    $appUrl = if ($env:APP_URL) { $env:APP_URL } else { "http://localhost:8000" }

    try {
        $response = Invoke-WebRequest -Uri "$appUrl/healthz" -TimeoutSec 5 -UseBasicParsing -ErrorAction Stop
        if ($response.StatusCode -eq 200) {
            Write-Ok "App and database are healthy."
        } else {
            Write-Warn "Health check returned HTTP $($response.StatusCode). Check manually once the server is running."
        }
    } catch {
        Write-Warn "Health check could not reach the server. Check manually once the server is running."
        Write-Host "  Run: curl $appUrl/healthz" -ForegroundColor Yellow
    }
}
```

### Anti-Patterns to Avoid

- **Blocking install script on health check failure:** The dev server is not running at setup time. A failed curl should warn, never `exit 1`.
- **Returning 500 instead of 503:** Decision D-03 is firm — 503 is the correct semantics for "service unavailable" (database down), not 500 (application error).
- **Using `middleware('api')` for the route:** The endpoint should be in `routes/web.php` without any throttle or auth middleware, matching the existing pattern for invite routes.
- **Running DB queries through Eloquent:** `DB::connection()->getPdo()` is a connection-level check; no model or query is needed.
- **Using `socket_timeout` config globally:** Only override the timeout locally in the health check, not application-wide.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Database timeout | Custom socket wrapper | PDO `ATTR_TIMEOUT` option or `socket_timeout` ini set temporarily | PHP's PDO supports connection timeouts natively |
| JSON response | Manual `json_encode` + headers | `response()->json($data, $code)` | Laravel handles content-type, encoding, status |
| Latency measurement | External profiling library | `microtime(true)` before/after PDO call | Sub-millisecond precision, zero overhead |

**Key insight:** This is a thin controller. Resist adding caching, middleware, or health aggregation complexity — the spec is a direct database ping and immediate JSON response.

---

## Common Pitfalls

### Pitfall 1: Install Script Curl Runs Before Server Is Started

**What goes wrong:** `install.sh` and `install.ps1` call `composer run setup` and then immediately curl `/healthz` — but the web server has not started yet. The curl returns connection refused (HTTP 000), making the install look broken even on a successful setup.
**Why it happens:** Decision D-06 says "after `composer run setup` completes" but `composer run setup` does not start the server.
**How to avoid:** Make the health check in install scripts advisory only (warn, don't fail). Print the URL for manual verification once the user starts the dev server. Or: check if APP_URL resolves at all before attempting the curl.
**Warning signs:** HTTP 000 / connection refused in the curl output during install.

### Pitfall 2: Database Timeout Hangs the Request

**What goes wrong:** If the database is unreachable, the default MySQL/SQLite connection attempt can hang for 30+ seconds before timing out.
**Why it happens:** No connection timeout is configured by default in Laravel's database config.
**How to avoid:** Wrap the `getPdo()` call in a block that temporarily sets `PDO::ATTR_TIMEOUT` or uses `ini_set('default_socket_timeout', 3)` before the call and resets it after. D-05 mandates a 3-second timeout.
**Warning signs:** `/healthz` requests timing out at the web server level instead of returning a fast 503.

### Pitfall 3: 503 Causes curl `-f` to Exit Non-Zero

**What goes wrong:** The Docker healthcheck uses `curl -f http://localhost/healthz`. With `-f`, curl exits non-zero on 4xx/5xx responses, which is the correct behavior — a 503 should cause the health check to fail. However, a developer testing locally may confuse this with a curl error.
**Why it happens:** `-f` is "fail silently" but still returns exit code 22 on HTTP error status codes.
**How to avoid:** This is intentional behavior — document it. The Docker healthcheck is supposed to fail when the DB is down.

### Pitfall 4: `config('app.version')` Returns null

**What goes wrong:** If `config/app.php` does not have a `version` key, the response contains `"version": null`.
**Why it happens:** D-02 adds the key to `config/app.php`, but it's easy to miss.
**How to avoid:** Add `'version' => env('APP_VERSION', '1.0.0')` to `config/app.php`. The fallback string ensures a non-null value even when the env var is absent.
**Warning signs:** JSON response has `"version": null`.

### Pitfall 5: `set -euo pipefail` Aborts on Curl Failure

**What goes wrong:** In `install.sh`, `curl` returning a non-zero exit code (connection refused, timeout) causes the entire script to abort due to `set -e`.
**Why it happens:** The script uses strict error handling (confirmed in line 2 of install.sh).
**How to avoid:** Use `|| true` after the curl call, or capture the exit code explicitly with `http_code=$(curl ...) || http_code="000"`.

---

## Code Examples

Verified patterns from existing codebase and Laravel 12 conventions:

### DB Connectivity Probe with Latency

```php
// Pattern: DB::connection()->getPdo() with microtime measurement
$start = microtime(true);
try {
    DB::connection()->getPdo();
    $latency = (int) round((microtime(true) - $start) * 1000);
    $dbStatus = 'ok';
} catch (\Throwable $e) {
    $latency = null;
    $dbStatus = 'error';
}
```

### JSON Response with Conditional Status Code

```php
// Source: Laravel 12 response()->json() pattern
return response()->json([
    'status'   => $status,
    'version'  => config('app.version'),
    'php'      => PHP_VERSION,
    'database' => [
        'status'     => $dbStatus,
        'latency_ms' => $latency,
    ],
], $status === 'ok' ? 200 : 503);
```

### Route Registration (outside auth group)

```php
// Source: routes/web.php — place before Route::middleware(['auth'])->group()
Route::get('/healthz', HealthCheckController::class)->name('healthz');
```

### Docker Healthcheck Block Format

```yaml
# Source: compose.yaml — matches existing mysql/redis healthcheck format
healthcheck:
    test: ["CMD", "curl", "-f", "http://localhost/healthz"]
    retries: 5
    timeout: 5s
    interval: 10s
    start_period: 60s
```

### Artisan Command to Create Controller

```bash
php artisan make:controller HealthCheckController --invokable --no-interaction
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Laravel Telescope health endpoint | Custom lightweight endpoint | Always custom for `/healthz` | No external dep needed |
| `DB::select('SELECT 1')` probe | `DB::connection()->getPdo()` | Best practice | No query execution; pure connection test |

**Deprecated/outdated:**
- `set -e` health check calls that exit scripts: Use `|| true` pattern in strict-mode bash.

---

## Open Questions

1. **Install script timing — live curl vs. deferred note**
   - What we know: `composer run setup` does not start the web server; the server only starts when the user answers "Y" to the "Start dev server?" prompt
   - What's unclear: Whether D-06 intends a live HTTP verification (impossible pre-server) or a post-start verification hint
   - Recommendation: Print a deferred hint after setup (`"Run curl http://localhost:8000/healthz after starting the server to verify"`) rather than attempting a live curl that will always fail at that point. The planner should confirm this interpretation.

2. **Docker `start_period` for `laravel.test`**
   - What we know: MySQL has `start_period: 30s`, `laravel.test` runs migrations via `docker/start.sh` after MySQL is healthy
   - What's unclear: Exact migration duration in practice
   - Recommendation: Use `start_period: 60s` to give migrations and npm build time to complete before healthcheck failures count against retries.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4 / PHPUnit 12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=HealthCheck` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| HLTH-01 | `GET /healthz` returns 200 with JSON body when DB is reachable | Feature | `php artisan test --compact --filter=HealthCheck` | ❌ Wave 0 |
| HLTH-01 | Response body contains `status`, `database.status`, `database.latency_ms`, `version`, `php` keys | Feature | `php artisan test --compact --filter=HealthCheck` | ❌ Wave 0 |
| HLTH-01 | Returns HTTP 503 when database is unreachable | Feature | `php artisan test --compact --filter=HealthCheck` | ❌ Wave 0 |
| HLTH-01 | Endpoint is accessible without authentication | Feature | `php artisan test --compact --filter=HealthCheck` | ❌ Wave 0 |
| HLTH-01 | Docker compose `laravel.test` has healthcheck block | Feature (config) | `php artisan test --compact --filter=DockerConfiguration` | ✅ (extend existing) |
| HLTH-01 | Install scripts include health check verification | Feature (script) | `php artisan test --compact --filter=BashInstallScript` | ✅ (extend existing) |

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=HealthCheck`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/HealthCheckTest.php` — covers HLTH-01 (all behaviors above)

*(Existing `DockerConfigurationTest.php` and `BashInstallScriptTest.php` will be extended in-place, not created from scratch.)*

---

## Sources

### Primary (HIGH confidence)

- Existing codebase — `app/Http/Controllers/StreamController.php`, `BrowseDirectoriesController.php` — invokable controller pattern
- Existing codebase — `routes/web.php` — route structure and auth middleware grouping
- Existing codebase — `compose.yaml` — Docker healthcheck block format (MySQL, Redis examples)
- Existing codebase — `install.sh`, `install.ps1` — script structure and print helper conventions
- Existing codebase — `tests/Feature/DockerConfigurationTest.php` — YAML-based config test pattern

### Secondary (MEDIUM confidence)

- Laravel 12 docs convention — `response()->json($data, $statusCode)` — standard JSON response
- Laravel 12 docs convention — `DB::connection()->getPdo()` — connection-level health probe
- Docker Compose docs — `healthcheck.start_period` semantics — time before failures count

### Tertiary (LOW confidence)

- Recommendation for `start_period: 60s` on `laravel.test` — estimated based on MySQL `start_period: 30s` plus migration time; should be validated with actual Docker boot timing

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new libraries; all existing Laravel/PHP primitives
- Architecture: HIGH — directly mirrors established controller and route patterns in codebase
- Script integration: MEDIUM — install script timing concern (open question 1) needs planner decision
- Docker healthcheck: HIGH — format is identical to existing MySQL/Redis blocks
- Pitfalls: HIGH — derived from direct code inspection of `install.sh` (`set -euo pipefail`) and Docker compose timing

**Research date:** 2026-03-27
**Valid until:** 2026-04-27 (stable domain — Laravel 12, Docker Compose v2 conventions change slowly)
