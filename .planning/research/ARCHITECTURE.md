# Architecture Patterns — v1.3 Easy Installation

**Domain:** Cross-platform installation, Docker/Sail setup for personal music streaming app
**Researched:** 2026-03-26
**Confidence:** HIGH (based on direct codebase inspection + official Laravel Sail docs)

---

## Context: Existing Architecture Being Extended

This is a subsequent milestone. The existing system is not redesigned — new components are layered on top. Before reading further, the v1.2 architecture established:

- SQLite database, queue connection via `database` driver (both stored in `database/database.sqlite`)
- Queue workers run via `composer run dev` which uses `concurrently` to start `php artisan serve`, `php artisan queue:listen`, and `npm run dev` together
- Makefile has `install`, `dev`, `test`, `lint`, `fresh`, `seed` targets
- `StreamController` serves local-linked audio by reading absolute filesystem paths (`$track->file_path`) and calling `response()->file($absolutePath)`
- `ScanLinkedFolder` job receives a `LinkedFolder` model whose `path` column is an absolute host filesystem path (e.g. `/Users/alice/Music` or `C:/Music`)
- `ExtractAudioMetadata` job also reads directly from `$track->file_path` as an absolute path

The central architectural question for v1.3: **how do Docker containers access host filesystem paths that exist outside the project directory?**

---

## System Overview: Native vs Docker

```
NATIVE INSTALL (macOS / Linux / Windows)
─────────────────────────────────────────────────────────
 install.sh / install.ps1
     └─ Checks: PHP 8.4, Composer, Node/npm, SQLite
     └─ Runs: composer install, php artisan migrate, npm run build
     └─ Verifies: health check (GET /), port conflict detection

 Makefile: make dev
     └─ composer run dev
         └─ concurrently: php artisan serve + queue:listen + npm run dev

 Storage: database/database.sqlite (project-local)
 Music:   /Users/alice/Music (host path — no container)


DOCKER INSTALL (via Laravel Sail)
─────────────────────────────────────────────────────────
 sail up -d
     ├── laravel.test  (PHP 8.4 + app code at /var/www/html)
     │     └─ nginx serves HTTP on port 80
     ├── mysql         (MySQL 8.0 — replaces SQLite for Docker)
     ├── redis         (replaces database queue driver for Docker)
     └── worker        (second laravel.test-based container running queue:work)

 Volume mounts in compose.yaml:
     ├── .:/var/www/html                (app code)
     ├── MUSIC_LIBRARY_PATH:/music      (host music folder — Docker's linked folder)
     └── storage/:/var/www/html/storage (uploaded files, SQLite excluded in Docker)

 .env for Docker uses:
     DB_CONNECTION=mysql
     DB_HOST=mysql
     QUEUE_CONNECTION=redis
     REDIS_HOST=redis
     MUSIC_LIBRARY_PATH=/music         (container-side mount point)
```

---

## Integration Points

### 1. SQLite vs MySQL: The Two-Environment Split

The app currently defaults to SQLite (`DB_CONNECTION=sqlite`). This works for native installs where a file at `database/database.sqlite` is accessible from the PHP process. Inside Docker (Sail), SQLite is technically possible but creates a shared-write contention risk between the web container and the worker container — both containers would write to the same file via a bind mount.

**Decision: SQLite for native, MySQL for Docker.** The existing `config/database.php` already has both drivers configured. No migration changes are needed — Laravel's migrations are SQL-dialect agnostic for the schema this project uses (no raw SQL, no dialect-specific column types). The switch is purely `.env`-driven.

| Variable | Native | Docker |
|----------|--------|--------|
| `DB_CONNECTION` | `sqlite` | `mysql` |
| `DB_DATABASE` | *(default: database/database.sqlite)* | `foonk` |
| `DB_HOST` | *(not set)* | `mysql` |
| `QUEUE_CONNECTION` | `database` | `redis` |
| `REDIS_HOST` | *(not set)* | `redis` |

The install script for Docker must generate an `.env` that sets all five of these correctly. The native install script keeps the existing SQLite defaults.

**Known risk:** Laravel's `SESSION_DRIVER=database` and `CACHE_STORE=database` store state in the jobs/sessions/cache tables. In Docker with MySQL, these tables need to exist before Sail starts accepting traffic — the worker container's startup sequence must account for this (run `artisan migrate` before `queue:work`).

---

### 2. Volume Mounting for Linked Folders

This is the most architecturally novel part of v1.3. The v1.2 `LinkedFolder.path` column stores a host absolute path (e.g. `/Users/alice/Music`). Inside a Docker container, that host path does not exist — only paths within mounted volumes are accessible.

**Pattern: single configurable music volume mount.**

The `compose.yaml` adds one additional volume to the `laravel.test` service:

```yaml
volumes:
  - '.:/var/www/html'
  - '${MUSIC_LIBRARY_PATH:-/tmp/music}:/music'
```

The host path comes from `.env` via the `MUSIC_LIBRARY_PATH` variable. The container-side mount point is always `/music` — this is a fixed contract. Users who link Docker-mode folders must enter `/music/subfolder` rather than their native host path.

**Implication for LinkedFolder:** When users add a linked folder in Docker mode, the folder path they enter must begin with `/music`. The existing `BrowseDirectoriesController` and folder path validation in Livewire components will work as-is against the container filesystem — no code changes needed. The only difference is that the user must be told: "in Docker, your music is mounted at `/music`."

**Worker container shares the same volume.** The `worker` service in `compose.yaml` must mount the same `MUSIC_LIBRARY_PATH` volume — the `ScanLinkedFolder` job and `ExtractAudioMetadata` job run in the worker container and must access `/music/...` paths. This is handled by giving the worker service the same `volumes` block as `laravel.test`.

**Multiple music folders.** If a user has music in multiple locations, they have two options:
1. Point `MUSIC_LIBRARY_PATH` at a common parent directory (e.g. `~/`) — suboptimal security surface
2. Use multiple bind mounts in `compose.yaml` (e.g. `/music1`, `/music2`) — requires editing `compose.yaml` after install

The README must explain option 2 for power users.

---

### 3. StreamController File Access in Containers

`StreamController.__invoke()` reads `$track->file_path` and calls `response()->file($absolutePath)`. For local-linked tracks in Docker, `file_path` stores `/music/subfolder/track.mp3` (the container path). This path is accessible because the worker and web container share the same volume mount.

**No code change required.** The controller already validates that `file_path` starts with the linked folder's `path` column value — both will consistently store the container path (`/music/...`) in Docker mode. The security guarantee is maintained.

**Uploaded tracks are unaffected.** Uploaded tracks use `Storage::disk('public')->path($relative)` which resolves to `/var/www/html/storage/app/public/...` — inside the project volume mount. These work correctly in both native and Docker modes.

---

### 4. Queue Worker Process Management

**Native install: `concurrently` via `composer run dev`.**

The existing `composer.json` `"dev"` script already runs three processes together:

```
npx concurrently "php artisan serve" "php artisan queue:listen --tries=1" "npm run dev"
```

This is fine for development but is a foreground process — stopping the terminal stops everything. For users who want a background mode, the Makefile's `dev` target can be extended with a detached option. However, for a personal app serving one user, the foreground `make dev` workflow is entirely appropriate and should not be over-engineered.

**The `--tries=1` flag on `queue:listen` is important** for development: jobs that fail surface errors immediately rather than being retried silently. Keep this in the native dev command.

**Docker: dedicated worker container.**

The worker container approach (separate service running `php artisan queue:work`) provides:
- Independent restart without web server reload
- Shared volume access to music files
- Container isolation for job crashes

The `compose.yaml` worker service reuses the same image as `laravel.test`:

```yaml
worker:
  image: sail-8.4/app
  build:
    context: ./vendor/laravel/sail/runtimes/8.4
    dockerfile: Dockerfile
  depends_on:
    - mysql
    - redis
  environment:
    WWWUSER: '${WWWUSER}'
    LARAVEL_SAIL: 1
  volumes:
    - '.:/var/www/html'
    - '${MUSIC_LIBRARY_PATH:-/tmp/music}:/music'
  networks:
    - sail
  command: 'php artisan queue:work redis --sleep=3 --tries=3 --timeout=600'
```

The `--timeout=600` matches the `ScanLinkedFolder` job's `public int $timeout = 600` property.

**Note:** The worker container needs to wait for MySQL to be ready before running jobs that insert records. Docker's `depends_on` handles startup ordering, but the application-level database migration must be run before the worker starts processing. The Makefile's `sail-install` target must run `sail artisan migrate --force` after `sail up -d`.

---

### 5. Makefile Enhancement vs Replacement

**Keep the Makefile, add Docker targets.** The existing Makefile targets work well for native installs. New targets are additive:

| Target | Existing? | Purpose |
|--------|-----------|---------|
| `install` | Yes | Native install (unchanged) |
| `dev` | Yes | Native dev server (unchanged) |
| `test` | Yes | Run Pest tests (unchanged) |
| `lint` | Yes | Pint formatting (unchanged) |
| `fresh` | Yes | Reset DB and seed (unchanged) |
| `seed` | Yes | Run seeders (unchanged) |
| `sail-install` | New | Docker first-run setup |
| `sail-up` | New | Start Docker containers |
| `sail-down` | New | Stop Docker containers |
| `sail-fresh` | New | Reset Docker DB and seed |

The `sail-install` target does:
```
sail-install:
    cp -n .env.docker.example .env || true
    vendor/bin/sail up -d
    vendor/bin/sail artisan key:generate
    vendor/bin/sail artisan migrate --force
    vendor/bin/sail artisan storage:link
    vendor/bin/sail npm install
    vendor/bin/sail npm run build
```

A separate `.env.docker.example` file pre-fills `DB_CONNECTION=mysql`, `DB_HOST=mysql`, `QUEUE_CONNECTION=redis`, `REDIS_HOST=redis`, and `APP_URL=http://localhost` so Docker users do not need to manually edit variables.

---

### 6. Install Scripts: New Components

Two new files are needed:

**`install.sh`** (macOS / Linux):
- Checks `php --version` ≥ 8.4 (exit with instructions if missing)
- Checks `composer --version` (exit with Homebrew/apt instructions if missing)
- Checks `node --version` ≥ 18 (exit with nvm instructions if missing)
- Checks if port 8000 is occupied (`lsof -i :8000`) and suggests `--port` override
- Runs: `cp -n .env.example .env`, `composer install`, `php artisan key:generate`, `touch database/database.sqlite`, `php artisan migrate --force`, `npm install`, `npm run build`, `php artisan storage:link`
- Emits: "Installation complete. Run `make dev` to start."

**`install.ps1`** (Windows PowerShell):
- Checks `php -v` ≥ 8.4 (with Herd/Laragon download link if missing)
- Checks `composer -V`
- Checks `node --version`
- Checks `netstat -an | findstr :8000` for port conflicts
- Same install sequence as `install.sh` using PowerShell path syntax

Both scripts are wrappers around the existing Makefile `install` target's commands — they add only the dependency checks and user feedback. They do not replace the Makefile.

---

### 7. Health Check Verification

After `install.sh`/`install.ps1` completes, a health check step:

```bash
# Wait up to 10 seconds for the app to respond
for i in {1..10}; do
  if curl -s -o /dev/null -w "%{http_code}" http://localhost:8000 | grep -qE "200|302"; then
    echo "App is up at http://localhost:8000"
    break
  fi
  sleep 1
done
```

This requires the web server to already be running. The install script itself should NOT start the server — the health check is optional and only runs if the user passes a `--verify` flag or similar. The primary install script should end with a clear "next steps" message, not silently start a background server process.

For Docker, health check is built into `compose.yaml` via Docker's native health check directive on the `laravel.test` service.

---

## Component Boundaries

| Component | New or Modified | Communicates With |
|-----------|----------------|-------------------|
| `install.sh` | New | OS package managers, Makefile targets (calls same commands) |
| `install.ps1` | New | Windows package detection, same install sequence |
| `compose.yaml` | New | Sail runtime, mysql, redis, worker service |
| `.env.docker.example` | New | Referenced by `compose.yaml`, `sail-install` Makefile target |
| Makefile | Modified | Adds `sail-install`, `sail-up`, `sail-down`, `sail-fresh` targets |
| `StreamController` | Unchanged | Works correctly with container paths `/music/...` |
| `ScanLinkedFolder` job | Unchanged | Workers in Docker see `/music/...` paths via shared volume |
| `ExtractAudioMetadata` job | Unchanged | Same shared volume access |
| `LinkedFolder` model | Unchanged | Path validation works against container filesystem |
| `BrowseDirectoriesController` | Unchanged | Browses container filesystem in Docker mode |

---

## Data Flow Changes

### Native: No Changes

The existing data flow is unchanged for native installs. `database/database.sqlite` stores all data. Absolute paths in `linked_folders.path` are native host paths.

### Docker: Two New Data Flows

**Database connection via MySQL:**
```
App container → DB_HOST=mysql → MySQL container (sail-mysql volume)
```

**Music file access via volume mount:**
```
Host: /Users/alice/Music  →  (bind mount)  →  Container: /music
linked_folders.path = '/music'  (stored in MySQL)
ScanLinkedFolder reads RecursiveDirectoryIterator('/music')  ← same path
StreamController reads response()->file('/music/Artist/track.flac')  ← same path
```

---

## Build Order for v1.3

Dependencies drive this sequence:

1. **`.env.docker.example`** — must exist before `compose.yaml` references its variables. Simplest file to create.

2. **`compose.yaml`** — defines the Docker service topology. Must exist before any Sail commands work. Requires `php artisan sail:install` to scaffold, then customized to add the `worker` service and `MUSIC_LIBRARY_PATH` volume.

3. **Makefile Docker targets** (`sail-install`, `sail-up`, `sail-down`, `sail-fresh`) — use `compose.yaml`. Validate that `sail artisan migrate --force` runs correctly against MySQL before adding worker target.

4. **`install.sh`** (macOS/Linux) — standalone; no code dependencies. Start with dependency detection, then call the same install commands as the existing Makefile `install` target.

5. **`install.ps1`** (Windows) — parallel to `install.sh`; implement after `install.sh` is validated.

6. **README rewrite** — final deliverable; summarizes all three install paths (native macOS/Linux, native Windows, Docker). Written last so instructions accurately reflect what the scripts actually do.

**Why this order:**
- Docker setup first because it requires the most structural work (`compose.yaml`, `.env.docker.example`, Makefile targets)
- Native scripts second because they are simpler wrappers and can be validated quickly
- README last because it documents the final state

---

## Anti-Patterns to Avoid

### Anti-Pattern 1: SQLite in Docker with Multiple Writers

**What:** Using `DB_CONNECTION=sqlite` inside Docker where both `laravel.test` and `worker` containers write to the same bind-mounted `database.sqlite` file.

**Why bad:** SQLite's write locking is per-process. Concurrent writes from two containers to the same file over a bind mount cause `SQLITE_BUSY` errors and silent job failures.

**Instead:** Use MySQL for Docker. The `compose.yaml` already includes a MySQL service. Set `DB_CONNECTION=mysql` in `.env.docker.example`.

---

### Anti-Pattern 2: Hardcoded Music Path in compose.yaml

**What:** Putting an absolute host path directly in `compose.yaml`:

```yaml
volumes:
  - '/Users/alice/Music:/music'
```

**Why bad:** The file is committed to git. The path only works on one developer's machine.

**Instead:** Use an environment variable: `'${MUSIC_LIBRARY_PATH:-/tmp/music}:/music'`. Users set `MUSIC_LIBRARY_PATH` in their `.env` file, which is gitignored. The `:-/tmp/music` default prevents a Docker Compose error when the variable is unset.

---

### Anti-Pattern 3: Starting Background Processes from Install Scripts

**What:** Having `install.sh` run `php artisan serve &` to start the server in the background at end of install.

**Why bad:** Background processes started by shell scripts are orphaned when the script exits on many systems. The user has no feedback that the server is running, no easy way to stop it, and no log output. Port conflicts silently prevent startup.

**Instead:** Install scripts end with "Installation complete — run `make dev` to start." Starting processes is the user's deliberate action, not a side effect of installation.

---

### Anti-Pattern 4: Duplicating env Variables Between .env.example and .env.docker.example

**What:** Keeping two large `.env.example` files in sync manually.

**Why bad:** They diverge. Users copy the wrong one.

**Instead:** `.env.docker.example` contains only the variables that differ from `.env.example` (the five listed in the SQLite vs MySQL table above). A comment at the top reads: "Copy .env.example first, then overlay these Docker-specific values." The install Makefile target `sail-install` handles this automatically with a `cp` + `sed` or two-step copy.

Alternatively, `.env.docker.example` is a complete file that mirrors `.env.example` except for the five Docker-specific variables. This is simpler for users even if slightly more maintenance. Use the complete-file approach.

---

### Anti-Pattern 5: queue:listen vs queue:work in Docker

**What:** Using `php artisan queue:listen` in the Docker worker container.

**Why bad:** `queue:listen` forks a new process for each job and re-requires the application — it is a development convenience that has higher overhead. Inside Docker, where Sail already provides process isolation, `queue:work` is correct. Additionally, `queue:listen` does not respect `--timeout` in all versions.

**Instead:** Use `php artisan queue:work redis --sleep=3 --tries=3 --timeout=600` in the worker service's `command`. Use `queue:listen --tries=1` only in the native development `composer run dev` script (existing behavior).

---

## Confidence Assessment

| Area | Confidence | Reason |
|------|------------|--------|
| SQLite/MySQL split | HIGH | Existing `config/database.php` confirms both drivers configured; Laravel docs confirm `.env`-driven switching |
| Volume mounting for linked folders | HIGH | Docker bind mount with env variable is well-documented standard practice; tested pattern from Docker Compose docs |
| StreamController in Docker | HIGH | Direct codebase inspection — controller reads absolute path from `$track->file_path`; container path `/music/...` is accessible if volume is mounted |
| Worker container setup | MEDIUM | Pattern is widely documented; exact `command` syntax verified against Sail docs and queue docs |
| Makefile targets | HIGH | All `vendor/bin/sail` commands are standard Sail usage per official docs |
| Install scripts | MEDIUM | Dependency detection logic is platform-specific; PowerShell detection patterns need Windows validation |

---

## Open Questions (Phase-Level Research Candidates)

1. **Multiple music directories in Docker.** If a user wants to link folders from two different host locations (e.g. an external drive AND a local folder), the current single-volume approach requires editing `compose.yaml`. A phase implementing this should decide: document the manual compose edit, or provide an interactive setup wizard that generates the volumes block.

2. **`sail:install` scaffolding vs manual compose.yaml.** Running `php artisan sail:install` generates a `compose.yaml` that includes only the services selected interactively. For this project, the worker service is custom and not generated by `sail:install`. The phase plan should clarify whether to: (a) run `sail:install` and then add the worker service manually, or (b) write `compose.yaml` from scratch. Option (a) is less error-prone.

3. **Health check endpoint.** The current app has no dedicated `/health` route — the root `/` redirects based on auth state and user count. A simple `Route::get('/health', fn () => response()->json(['status' => 'ok']))` added to `routes/web.php` (outside auth middleware) is the correct solution. This is a one-line addition but needs a phase plan decision.

4. **Windows native install with Herd already present.** Many Windows users will already have Laravel Herd installed (this app was developed with it). The `install.ps1` script should detect Herd's PHP and Composer rather than checking `PATH`-registered binaries, since Herd manages PHP versions separately. This is a validation step for the Windows script phase.

---

## Sources

- [Laravel Sail 12.x Documentation](https://laravel.com/docs/12.x/sail) — compose.yaml structure, services, volumes, `sail:install` command (HIGH confidence)
- [Docker Bind Mounts Documentation](https://docs.docker.com/engine/storage/bind-mounts/) — bind mount syntax, environment variable path interpolation (HIGH confidence)
- [Dockerizing Laravel Queues — Sevalla Blog](https://sevalla.com/blog/dockerizing-laravel-queues/) — dedicated worker container approach (MEDIUM confidence)
- Direct codebase inspection: `app/Http/Controllers/StreamController.php` — absolute path file serving (HIGH confidence)
- Direct codebase inspection: `app/Jobs/ScanLinkedFolder.php` — `RecursiveDirectoryIterator($this->folder->path)` reads path directly (HIGH confidence)
- Direct codebase inspection: `composer.json` `"dev"` script — existing `concurrently` + `queue:listen` pattern (HIGH confidence)
- Direct codebase inspection: `Makefile` — existing install target commands (HIGH confidence)
- Direct codebase inspection: `.env.example` — current `DB_CONNECTION=sqlite`, `QUEUE_CONNECTION=database` defaults (HIGH confidence)
- Direct codebase inspection: `config/database.php` — both `sqlite` and `mysql` connection configs present (HIGH confidence)
- [Adding Laravel Sail to Existing Projects — Vincent Schmalbach](https://www.vincentschmalbach.com/adding-laravel-sail-to-existing-projects-solving-the-database-driver-chicken-and-egg-problem/) — MySQL driver availability in Sail containers (MEDIUM confidence)

---

*Architecture research for: v1.3 Easy Installation — Foonk*
*Researched: 2026-03-26*
