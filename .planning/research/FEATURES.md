# Feature Landscape

**Domain:** Cross-platform installer and Docker setup for a self-hosted Laravel music streaming app
**Researched:** 2026-03-26
**Milestone:** v1.3 Easy Installation

---

## Table Stakes

Features users expect from a "one-command install." Missing any makes the project feel unpolished or unusable out-of-the-box.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| `composer run setup` one-liner (native) | Comparable self-hosted projects advertise a single setup command; technical users expect it | Low | Already partially exists — `scripts.setup` in `composer.json` covers install + migrate + build. Missing `storage:link`. Needs audit. |
| `composer run dev` starts web + queue + Vite | Since Laravel 11.28, this is the official multi-process dev pattern. Already present. | Low | Already implemented via `npx concurrently` with artisan serve + queue:listen + vite. Complete. Needs documenting. |
| `.env.example` ships with safe defaults | Users expect to copy-and-run without reading all config. Must work for SQLite path. | Low | Exists. Currently defaults to SQLite + database queue — correct for native. Docker path needs a separate example file. |
| Cross-platform install script (Bash) for macOS/Linux | Technical users on macOS/Linux expect a `./install.sh` entry point | Medium | Needs creating. Must check: PHP ≥8.4, Composer, Node ≥18, npm. On macOS: offer Homebrew detection (`command -v brew`). On Linux: detect apt/dnf. On failure: print exact install command for that platform. |
| PowerShell installer for Windows | Windows users without WSL2 cannot run Bash. A `.ps1` installer is expected for parity. | Medium | Needs creating. Check PHP, Composer, Node in `$env:PATH`. Suggest `winget install PHP.PHP` or `choco install php` on failure. |
| Dependency detection with helpful error messages | Users expect "PHP 8.4 not found — install with `brew install php`", not silent failure | Low-Medium | Pattern: `command -v php`, then `php -r "echo PHP_MAJOR_VERSION;"`. Compare version. Platform-specific install hint on failure. |
| Docker installation via Laravel Sail | Docker users expect a `docker-compose.yml` and `./vendor/bin/sail up -d` entry point | Medium | Sail is already in `require-dev`. Needs `sail:install` to publish compose file with MySQL + Redis services. |
| Sail queue worker runs automatically | When running Sail, the queue worker must start without manual intervention | Medium | Two approaches: (1) separate `worker` service in docker-compose reusing the same app image, (2) Supervisor inside the main container. Approach (1) is preferred — cleaner separation, independently scalable. |
| Host folder volume mounting for local library | v1.2 local library feature only works if the host music folder is readable inside the container | High | Most complex installation feature. Requires a configurable bind mount (e.g. `MUSIC_PATH` env var drives a volume entry). The container sees `/music`; the host path is whatever the user sets. |
| Port conflict detection | Users with Herd, MAMP, XAMPP, local MySQL/Redis already using default ports need a clear error | Medium | `nc -z localhost <port>` or `lsof -i :<port>` in install script. Target ports: 8000 (artisan serve), 3306 (MySQL), 6379 (Redis), 5173 (Vite). Suggest alternative port or `.env` override. |
| Health check after install | User should know the install worked without manually opening a browser | Low-Medium | Laravel 11+ ships `/up` endpoint by default. Install script runs `curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/up` and prints "App is running" or "Health check failed". |
| README with per-OS installation sections | Users land on GitHub first — README is the install UX before any script runs | Low | Needs complete rewrite: macOS native, Linux native, Windows native, Docker (all OS). Short and scannable. Per-OS code blocks. |

---

## Differentiators

Not expected for a personal tool, but meaningfully reduce friction.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| `MUSIC_PATH` env-driven volume mount | User sets one variable — no YAML editing required | Low | `.env.example` gets a commented `MUSIC_PATH=/path/to/music`. Compose file reads it via `${MUSIC_PATH:-./music}`. Docker users set it once. |
| Post-install first-run instructions printed to terminal | After install succeeds: print "Visit http://localhost:8000 — first user becomes admin. Subsequent users need an invite." | Low | Eliminates the "now what?" confusion. Aligns with the existing invite-based admin system. Costs nothing to implement. |
| `.env.docker.example` separate from `.env.example` | Sail users need MySQL host/Redis connection strings — merging them with the SQLite example causes confusion | Low | Common pattern among self-hosted apps (e.g. Immich). `.env.example` = SQLite (native), `.env.docker.example` = MySQL + Redis (Sail). |
| Smart port fallback suggestion | Script detects port 8000 is in use and offers 8080 automatically, writing the override to `.env` | Medium | Reduces first-run friction for users running Herd or other local stacks. Adds complexity to install script; consider for second iteration. |

---

## Anti-Features

Features to explicitly NOT build for this milestone.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| Docker as the only installation path | Excludes Herd users (primary dev environment for this project) and non-technical users | Keep native `composer run setup` + `composer run dev` as primary path; Docker is secondary |
| Fully automated dependency installation without asking | Running `brew install php` silently is hostile to managed environments (corporate Macs, shared servers) | Detect, print the exact install command, ask user to confirm before running |
| Kubernetes / multi-node compose | Massively over-engineered for a personal music streamer serving one household | Single `docker-compose.yml` is correct |
| Production-optimized multi-stage Docker image | Personal self-hosted app, not SaaS. Optimization adds significant maintenance burden. | Sail's published dev image is sufficient for v1.3. Production optimization is v1.4+ territory. |
| Full WSL2 setup automation in install.ps1 | WSL2 requires admin elevation, a reboot, and Microsoft Store interaction — fully automating is hostile | Document WSL2 as the recommended Windows + Docker path; `install.ps1` targets native Windows only |
| Custom Dockerfile replacing Sail's default | Sail's image handles PHP 8.4 + required extensions. Custom image adds maintenance burden. | Override only what's needed (queue worker service) via `docker-compose.override.yml` |
| Pre-building Vite assets inside the Docker container at startup | Vite build takes 10-30 seconds and should run once, not every container restart | Build assets as a step in the install script before `sail up`, or ship pre-built to Docker. |

---

## Feature Dependencies

```
Dependency detection
    └──required-before──> Any install commands run

Port conflict detection
    └──required-before──> Install script writes .env / suggests port changes

Laravel Sail docker-compose.yml (sail:install)
    └──required-before──> Sail queue worker service
    └──required-before──> Host folder volume mount

MUSIC_PATH bind mount (docker-compose.yml)
    └──depends-on──> v1.2 FOLD-01–FOLD-06 features (already shipped)
    └──required-for──> Local library linking to work inside Docker

.env.docker.example
    └──depends-on──> Knowing which services Sail will use (MySQL + Redis)

Health check
    └──requires──> Web server running (last step in setup flow)

README per-OS guides
    └──depends-on──> All installation paths confirmed working (write last)
```

---

## Implementation Notes

### Existing `composer.json` scripts (confirmed present)

```json
"setup": [
    "composer install",
    "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
    "@php artisan key:generate",
    "@php artisan migrate --force",
    "npm install",
    "npm run build"
],
"dev": [
    "Composer\\Config::disableProcessTimeout",
    "npx concurrently -c \"#93c5fd,#c4b5fd,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1\" \"npm run dev\" --names='server,queue,vite'"
]
```

The `setup` script is missing `php artisan storage:link` — required for public disk access (uploaded audio, avatars, cover art). This must be added. The `dev` script is complete (web + queue + Vite concurrent). Neither is documented in a README yet.

### Database defaults

| Path | DB | Queue driver | Notes |
|------|----|-------------|-------|
| Native | SQLite (zero-config) | `database` | Already the `.env.example` default. Works immediately. |
| Docker/Sail | MySQL 8 | `redis` | Needs `.env.docker.example`. Redis preferred over database queue for performance under Sail. |

### Local library linking in Docker context

v1.2 stores absolute host paths (e.g. `/Users/alice/Music`). These paths do not exist inside Docker containers. The solution:

1. User sets `MUSIC_PATH=/Users/alice/Music` in `.env`
2. `docker-compose.yml` mounts it: `${MUSIC_PATH}:/music:ro`
3. The `LinkedFolder` model stores the container-internal path (`/music/Jazz/...`)
4. The `StreamController` reads the file from `/music/...` — works inside the container
5. Limitation: must document that Docker users add folders using the container path (e.g. `/music`) not the host path

The bind mount should be read-only (`:ro`) — the app never writes to the user's music library.

### Port conflict detection targets

| Service | Default Port | Common Conflicts |
|---------|-------------|-----------------|
| PHP artisan serve | 8000 | Other Laravel apps, Python dev servers |
| MySQL (Sail) | 3306 | Local MySQL, MAMP, WAMP, Homebrew MySQL |
| Redis (Sail) | 6379 | Local Redis instances |
| Vite HMR | 5173 | Other Vite dev servers |

### Sail queue worker: recommended approach

Use a separate `worker` service in `docker-compose.yml` that reuses the same `laravel.test` image. This is cleaner than Supervisor inside the main container:

```yaml
worker:
  image: sail-8.4/app  # same image as the web service
  command: php artisan queue:work --tries=3 --timeout=90
  depends_on:
    - mysql
    - redis
  volumes:
    - '.:/var/www/html'
```

This approach: independently restartable, scalable, standard Docker pattern.

### Health check pattern (Docker Compose)

```yaml
mysql:
  healthcheck:
    test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
    interval: 5s
    timeout: 5s
    retries: 5

laravel.test:
  depends_on:
    mysql:
      condition: service_healthy
    redis:
      condition: service_healthy
```

Without `condition: service_healthy`, the app container starts before MySQL is ready and migrations fail.

---

## Comparable Self-Hosted Project Patterns

| Project | Install UX | Key Pattern |
|---------|------------|-------------|
| Navidrome | `docker-compose up -d`, one service, two volumes (data + music) | Trivial — one container, bind mount for music, named volume for data |
| Immich | `wget docker-compose.yml`, edit `.env`, `docker compose up -d` | Recommended over their experimental `curl | bash` installer |
| Standard Laravel app | `composer install && php artisan serve` | Does not include queue worker or Vite — this project improves on this with `composer run dev` |

**Key takeaway:** The simplest self-hosted Docker installs have two steps: (1) get the compose file, (2) edit `.env`, (3) run `docker compose up`. Rekodo should match this pattern.

---

## Sources

- [Laravel Sail Documentation (12.x)](https://laravel.com/docs/12.x/sail) — HIGH confidence
- [Laravel Queue Documentation (12.x)](https://laravel.com/docs/12.x/queues) — HIGH confidence
- [Docker Bind Mounts — Docker Docs](https://docs.docker.com/engine/storage/bind-mounts/) — HIGH confidence
- [Docker Volumes — Docker Docs](https://docs.docker.com/engine/storage/volumes/) — HIGH confidence
- [Docker Compose Health Checks — Last9](https://last9.io/blog/docker-compose-health-checks/) — MEDIUM confidence
- [Dockerizing Laravel queues, workers, and schedulers — Sevalla](https://sevalla.com/blog/dockerizing-laravel-queues/) — MEDIUM confidence
- [How to Use Docker with Laravel (Sail) — OneUptime Blog 2026](https://oneuptime.com/blog/post/2026-02-02-laravel-docker-sail/view) — MEDIUM confidence
- [Laravel + MySQL + Redis + Horizon with Docker Compose — OneUptime 2026](https://oneuptime.com/blog/post/2026-02-08-how-to-set-up-a-laravel-mysql-redis-horizon-stack-with-docker-compose/view) — MEDIUM confidence
- [Self Hosting Navidrome — NucuLabs 2025](https://nuculabs.dev/posts/2025/self-hosting-navidrome/) — MEDIUM confidence (install UX reference)
- [Immich Docker Compose install](https://docs.immich.app/install/docker-compose/) — MEDIUM confidence (install pattern reference)
- [Add composer run dev to existing Laravel app — GitHub Gist](https://gist.github.com/ceilidhboy/491e78ebc08498df7be5ea2884ab4b89) — MEDIUM confidence
- PROJECT.md v1.3 requirements — HIGH confidence (authoritative project spec)
- `composer.json` scripts (read directly) — HIGH confidence
- `.env.example` (read directly) — HIGH confidence
