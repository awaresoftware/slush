# Technology Stack

**Project:** rekodo — Easy Installation (v1.3)
**Researched:** 2026-03-26
**Confidence:** HIGH

---

## Context

This is a SUBSEQUENT MILESTONE for an existing Laravel 12 / Livewire 4 / Mary UI app. The existing validated stack (Laravel 12, PHP 8.4, Livewire 4, Mary UI, Alpine.js, Tailwind CSS v4, Pest 4, getid3) is NOT re-evaluated here. This document covers only the **new capabilities** required for v1.3 Easy Installation.

**Key finding:** No new Composer packages are required. `laravel/sail ^1.41` is already installed as a dev dependency. `concurrently ^9.0.1` is already installed in `package.json` and is already used by `composer run dev`. All new capabilities are satisfied by existing dependencies + configuration files.

---

## What Already Exists (Do Not Re-Install)

| Dependency | Where | Version | Role in v1.3 |
|------------|-------|---------|--------------|
| `laravel/sail` | `composer.json` (require-dev) | `^1.41` | Provides Docker environment and Sail CLI |
| `concurrently` | `package.json` (dependencies) | `^9.0.1` | Already powers `composer run dev` (serve + queue + vite) |
| `QUEUE_CONNECTION=database` | `.env.example` | — | Queue uses DB by default; switch to `redis` in Docker env |

The `composer run dev` script already wires all three processes:

```json
"dev": "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite'"
```

This covers the "native one-command run" requirement — nothing new to install.

---

## New Capabilities Required

### 1. Laravel Sail Docker Setup (MySQL + Redis)

**Use `php artisan sail:install` — no new packages needed.**

Sail 1.41 (already installed) ships stubs for MySQL 8.4 and Redis alpine. Running `sail:install --with=mysql,redis` generates a `compose.yaml` at the project root that includes both services with healthchecks already embedded in the stubs:

**MySQL stub healthcheck (from `vendor/laravel/sail/stubs/mysql.stub`):**
```yaml
mysql:
    image: 'mysql:8.4'
    healthcheck:
        test: ["CMD", "mysqladmin", "ping", "-p${DB_PASSWORD}"]
        retries: 3
        timeout: 5s
```

**Redis stub healthcheck (from `vendor/laravel/sail/stubs/redis.stub`):**
```yaml
redis:
    image: 'redis:alpine'
    healthcheck:
        test: ["CMD", "redis-cli", "ping"]
        retries: 3
        timeout: 5s
```

The `laravel.test` container must add `depends_on` with `condition: service_healthy` to ensure MySQL and Redis are ready before the app starts. This is NOT in the default stub and must be added to the generated `compose.yaml`.

### 2. Queue Worker in Docker

**Use a second service in `compose.yaml` sharing the same image — not supervisord.**

The recommended pattern for a dev Docker setup is a dedicated `queue` service in `compose.yaml` that reuses the `laravel.test` image and overrides the entrypoint to run `php artisan queue:work`. This is cleaner than modifying `supervisord.conf` because:
- The main container's supervisord exits when its sole process exits — adding a queue worker to it requires `sail:publish` to override the Dockerfile and supervisord config, which is more invasive
- A separate service follows the single-process-per-container Docker principle
- Logs for web and queue are cleanly separated in `sail logs`

```yaml
queue:
    image: sail-8.4/app
    depends_on:
        mysql:
            condition: service_healthy
        redis:
            condition: service_healthy
    environment:
        WWWUSER: '${WWWUSER}'
        LARAVEL_SAIL: 1
    volumes:
        - '.:/var/www/html'
    networks:
        - sail
    command: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

This service shares the already-built `sail-8.4/app` image with `laravel.test` — no extra build step.

### 3. Host Volume Mounting for Music Files

**Add a named bind-mount in `compose.yaml` `volumes` — no new tools needed.**

For Docker users who use the local library linking feature (v1.2), their music files live on the host at an arbitrary path. The container must access those files at the same path to avoid breaking stored absolute paths in the database.

The approach: document an optional `MUSIC_PATH` env var and let users add the mount themselves (or provide a commented example in `compose.yaml`). The mount must preserve the same absolute path:

```yaml
# In compose.yaml laravel.test service volumes:
volumes:
    - '.:/var/www/html'
    - '${MUSIC_PATH:-/tmp/music}:${MUSIC_PATH:-/tmp/music}'  # optional: mount host music folder
```

**Critical constraint:** The host path and container path MUST be identical. Because v1.2 stores absolute paths in `tracks.file_path`, if the container mount maps `/home/user/Music` to `/music`, all stored paths break. The bind-mount must use the same path on both sides.

**Windows path constraint:** Docker Desktop on Windows translates `C:\Users\...` paths to `/c/Users/...` (Git Bash style) or `/run/desktop/mnt/host/c/Users/...` (WSL2 style). Windows users cannot use identical host:container paths. This must be documented as a known limitation — Windows Docker users should prefer the upload feature, not local library linking.

### 4. Port Conflict Detection

**Use shell built-ins — no new tools needed.**

For Bash (macOS/Linux):
```bash
check_port() {
    lsof -i :"$1" -sTCP:LISTEN -t >/dev/null 2>&1
}
```

`lsof` is available on macOS by default and on all major Linux distros. `ss` is an alternative on Linux only (`ss -ltn | grep ":$1 "`). The install script should check ports 80 (app), 3306 (MySQL), 6379 (Redis), and 5173 (Vite) and suggest alternatives via `.env` overrides (`APP_PORT`, `FORWARD_DB_PORT`, `FORWARD_REDIS_PORT`, `VITE_PORT`).

For PowerShell (Windows):
```powershell
function Test-PortInUse($port) {
    $result = netstat -ano | Select-String ":$port\s"
    return $result -ne $null
}
```

`netstat` is available on all Windows versions. PowerShell 5+ (built into Windows 10+) also supports `Test-NetConnection -Port $port` but it is slower.

### 5. Cross-Platform Install Scripts

**Bash for macOS/Linux, PowerShell for Windows — no new tools needed.**

**Bash script (`install.sh`) capability targets:**

| Check | Detection method | Confidence |
|-------|-----------------|------------|
| PHP 8.x present | `command -v php && php -r "echo PHP_MAJOR_VERSION;"` | HIGH |
| Composer present | `command -v composer` | HIGH |
| Node.js present | `command -v node` | HIGH |
| npm present | `command -v npm` | HIGH |
| Homebrew (macOS) | `command -v brew` | HIGH |
| Docker + Sail mode | `command -v docker && docker info >/dev/null 2>&1` | HIGH |
| Port availability | `lsof -i :PORT -sTCP:LISTEN -t >/dev/null 2>&1` | HIGH |

Installation suggestion logic:
- macOS without PHP → suggest `brew install php` (Homebrew path: `/opt/homebrew/bin` on Apple Silicon, `/usr/local/bin` on Intel)
- Linux without PHP → suggest `apt install php8.4` (Debian/Ubuntu) or `dnf install php` (Fedora)
- Missing Composer → suggest official installer: `curl -sS https://getcomposer.org/installer | php`
- Missing Node → suggest `brew install node` (macOS) or direct download from nodejs.org

**PowerShell script (`install.ps1`) capability targets:**

| Check | Detection method |
|-------|-----------------|
| PHP present | `Get-Command php -ErrorAction SilentlyContinue` |
| Composer present | `Get-Command composer -ErrorAction SilentlyContinue` |
| Node.js present | `Get-Command node -ErrorAction SilentlyContinue` |
| Docker Desktop | `Get-Command docker -ErrorAction SilentlyContinue` |
| Port availability | `netstat -ano \| Select-String ":PORT\s"` |

PowerShell execution policy is a common blocker on Windows — the script must document how to allow it:
```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
```

**Recommended guided install flow for both scripts:**
1. Detect OS variant
2. Check each dependency; report missing ones with install instructions
3. Abort if critical deps missing (PHP, Composer); warn but continue if optional (Node)
4. Check for port conflicts; suggest `.env` overrides if blocked
5. Run `composer install` if `vendor/` absent
6. Copy `.env.example` to `.env` if `.env` absent
7. Run `php artisan key:generate`
8. Run `php artisan migrate --force`
9. Run `npm install && npm run build` if Node present
10. Print final "ready" message with URL

### 6. Health Check Verification After Install

**Use `php artisan about` + a manual HTTP check — no new tools needed.**

After install steps complete, the script can verify the app responds:

```bash
# Bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:${APP_PORT:-80}/
```

```powershell
# PowerShell
(Invoke-WebRequest -Uri "http://localhost:$AppPort/" -UseBasicParsing).StatusCode
```

A 200 or 302 (redirect to login) indicates the app is healthy. A non-200/302 or connection refused means something failed.

`php artisan about` can also verify the app bootstrap is healthy before starting services.

---

## Recommended Stack for v1.3

### New Files (No New Dependencies)

| File | Purpose | Notes |
|------|---------|-------|
| `compose.yaml` | Docker Compose for Sail | Generated by `sail:install`, then customized |
| `install.sh` | Bash install script for macOS/Linux | Written from scratch |
| `install.ps1` | PowerShell install script for Windows | Written from scratch |
| `docker/supervisord-queue.conf` | Optional: supervisord config if queue-in-same-container approach chosen | Only needed if separate service approach is not used |

### Environment Variables to Add to `.env.example`

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_PORT` | `80` | Host port for web (override if conflict) |
| `FORWARD_DB_PORT` | `3306` | Host port for MySQL |
| `FORWARD_REDIS_PORT` | `6379` | Host port for Redis |
| `VITE_PORT` | `5173` | Host port for Vite HMR |
| `MUSIC_PATH` | _(empty)_ | Host path to mount for local library linking in Docker |

### Docker Queue Worker: Two Valid Approaches

| Approach | Pros | Cons | Recommendation |
|----------|------|------|----------------|
| Separate `queue` service in `compose.yaml` (reuses `sail-8.4/app` image) | Clean separation, easy logs, no Dockerfile changes | Two containers instead of one | **Preferred** |
| Add queue worker to `supervisord.conf` via `sail:publish` | Single container | Requires publishing Dockerfile; supervisord exit = container stop risk | Only if user strongly prefers one container |

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Queue worker in Docker | Separate `queue` service in `compose.yaml` | Modify `supervisord.conf` via `sail:publish` | `sail:publish` copies Dockerfile into project which is harder to keep in sync with upstream Sail |
| Port detection (Bash) | `lsof -i :PORT` | `netstat -an` | `lsof` is macOS-native and more reliable; `netstat` output format differs between macOS and Linux |
| Port detection (PowerShell) | `netstat -ano \| Select-String` | `Test-NetConnection` | `Test-NetConnection` establishes a TCP connection (slow); `netstat` is faster for a presence check |
| Process manager (native) | Already-present `concurrently` via `composer run dev` | `foreman`, `overmind`, `hivemind` | All require separate installation; `concurrently` is already in `package.json` |
| Music volume mount strategy | Identical host:container path bind-mount | Remap path (e.g. `/home/user/Music` → `/music`) | Remapping breaks all stored absolute paths in DB without a migration |
| Windows dependency installation | Document Chocolatey/winget guidance | `mlocati/powershell-phpmanager` | Extra PowerShell module adds complexity; directing users to official PHP Windows installer is simpler |

## What NOT to Add

| Avoid | Why | Use Instead |
|-------|-----|-------------|
| New Composer package for process management | `concurrently` already handles native dev workflow | Existing `composer run dev` script |
| `laravel/horizon` | Overkill for a personal app with simple queue needs; adds Redis dependency for native mode | `php artisan queue:listen` (already in dev script) |
| Modifying `vendor/laravel/sail` files directly | Changes lost on `composer update` | `sail:publish` to copy files into project root, then modify copies |
| Docker multi-stage build for production | This is a dev/personal-use install; production deployment is out of scope for v1.3 | Single-stage Sail dev image |
| `Dockerfile` published to project root | Increases maintenance burden; only needed if custom PHP extensions required | Use Sail's built-in PHP 8.4 runtime which already includes all needed extensions (mysql, redis, gd, zip, etc.) |
| Windows-native music path remapping in Docker | Breaks stored absolute paths in `tracks.file_path` without a data migration | Document as limitation; Windows Docker users should use upload feature |

---

## Installation (v1.3 additions only)

```bash
# Docker path (new)
php artisan sail:install --with=mysql,redis
# Then edit compose.yaml:
# - Add depends_on with service_healthy to laravel.test
# - Add queue service
# - Add MUSIC_PATH volume (optional, commented out)
sail up -d
sail artisan migrate --force
sail artisan storage:link

# Native path (already works via existing composer script)
composer run setup   # installs, copies .env, generates key, migrates, builds
composer run dev     # starts serve + queue:listen + vite concurrently
```

---

## Version Compatibility

| Technology | Version | Notes |
|-----------|---------|-------|
| `laravel/sail` | `^1.41` (already installed) | PHP 8.4 runtime ships with `php8.4-redis` extension — Redis queue driver works out of the box |
| MySQL image | `8.4` (from Sail stub) | Used in `compose.yaml`; healthcheck uses `mysqladmin ping` |
| Redis image | `alpine` (from Sail stub) | Minimal image; healthcheck uses `redis-cli ping` |
| `concurrently` | `^9.0.1` (already installed) | Latest stable 9.2.1; already in `package.json` |
| PHP `lsof` / `netstat` | OS built-in | Available on macOS 10.15+, Ubuntu 20.04+, Windows 10+ |
| PowerShell | 5.1+ (built into Windows 10+) | No installation required; `Get-Command` and `netstat` available in PSv5 |

---

## Sources

- [Laravel Sail documentation (v12)](https://laravel.com/docs/12.x/sail) — `sail:install`, `sail:publish`, service configuration (HIGH confidence)
- `vendor/laravel/sail/stubs/compose.stub` — base `compose.yaml` template, confirmed (HIGH confidence)
- `vendor/laravel/sail/stubs/mysql.stub` — MySQL 8.4 image + healthcheck, confirmed (HIGH confidence)
- `vendor/laravel/sail/stubs/redis.stub` — Redis alpine image + healthcheck, confirmed (HIGH confidence)
- `vendor/laravel/sail/runtimes/8.4/supervisord.conf` — confirms supervisord manages single PHP process in container (HIGH confidence)
- `composer.json` — confirms `laravel/sail ^1.41` is already a dev dependency (HIGH confidence)
- `package.json` — confirms `concurrently ^9.0.1` already installed; `composer run dev` already uses it (HIGH confidence)
- `.env.example` — confirms current `QUEUE_CONNECTION=database`; Redis vars already present but commented (HIGH confidence)
- [GitHub laravel/sail issue #245](https://github.com/laravel/sail/issues/245) — queue worker container pattern discussion and PR #248 (MEDIUM confidence)
- [Docker Compose healthcheck guide](https://last9.io/blog/docker-compose-health-checks/) — `depends_on condition: service_healthy` pattern (MEDIUM confidence)
- [concurrently npm package](https://www.npmjs.com/package/concurrently) — confirmed latest 9.2.1, August 2025 (HIGH confidence)
- [Docker port conflict detection](https://oneuptime.com/blog/post/2026-01-16-docker-port-conflicts/view) — `lsof` and `netstat` approaches (MEDIUM confidence)

---
*Stack research for: v1.3 Easy Installation (rekodo)*
*Researched: 2026-03-26*
