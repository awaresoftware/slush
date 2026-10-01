# Phase 21: Health Check Endpoint - Context

**Gathered:** 2026-03-27
**Status:** Ready for planning

<domain>
## Phase Boundary

A single `/healthz` endpoint that verifies both the application and database are operational, enabling install scripts and Docker to confirm successful setup. This phase also integrates the endpoint into existing install scripts (install.sh, install.ps1) and Docker compose.

</domain>

<decisions>
## Implementation Decisions

### Response Shape
- **D-01:** Detailed JSON response including `status`, `database` (with status and latency_ms), `version`, and `php` version
- **D-02:** App version sourced from `config('app.version')` — add a `version` key to `config/app.php`

### Failure Behavior
- **D-03:** Return HTTP 503 Service Unavailable when database is unreachable (not 500)
- **D-04:** On failure, response body still includes full JSON structure with `"status": "error"` and `"database.latency_ms": null`
- **D-05:** 3-second timeout on database connectivity check to prevent hanging on dead DB

### Script Integration
- **D-06:** Install scripts (install.sh, install.ps1) should call `/healthz` after `composer run setup` completes to verify app + DB are working, printing clear success/failure message
- **D-07:** Docker compose should add a healthcheck to the web container that curls `/healthz`, validating the full stack (PHP + DB)

### Claude's Discretion
- Database connectivity method (SELECT 1, connection ping, etc.)
- Exact placement of health verification in install scripts (after which step)
- Docker compose healthcheck timing parameters (interval, retries, start_period)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Routes & Controllers
- `routes/web.php` — Existing route structure; `/healthz` must be registered outside the auth middleware group
- `app/Http/Controllers/` — Invokable controller pattern used throughout (e.g., StreamController)

### Install Scripts
- `install.sh` — Bash install script for macOS/Linux; needs health verification call added
- `install.ps1` — PowerShell install script for Windows; needs health verification call added

### Docker
- `compose.yaml` — Docker Compose config; web container needs `/healthz` healthcheck added

### Configuration
- `config/app.php` — Needs `version` key added for health endpoint response

### Prior Phase Context
- `.planning/phases/17-native-foundation/17-CONTEXT.md` — Decision that `/healthz` sits outside auth middleware and exercises the database
- `.planning/phases/18-docker-configuration/18-CONTEXT.md` — Docker compose structure and service dependencies

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- Invokable controller pattern (`StreamController`, `BrowseDirectoriesController`) — health check controller should follow same pattern
- Existing route structure in `routes/web.php` with auth middleware grouping — `/healthz` goes outside this group

### Established Patterns
- Controllers are invokable single-action classes
- Routes use `Route::get()` with controller class reference
- Docker compose uses `healthcheck` blocks on MySQL and Redis services (test + retries + timeout format)

### Integration Points
- `routes/web.php` — New route registration (outside auth middleware)
- `config/app.php` — New `version` key
- `install.sh` — Health verification step after setup
- `install.ps1` — Health verification step after setup
- `compose.yaml` — Web container healthcheck block

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 21-health-check-endpoint*
*Context gathered: 2026-03-27*
