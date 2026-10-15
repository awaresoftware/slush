# Domain Pitfalls

**Domain:** Cross-platform installation automation and Docker support for a Laravel music streaming app
**Researched:** 2026-03-26
**Confidence:** HIGH

> This supersedes the v1.2 pitfalls file (local filesystem library linking).
> v1.2 pitfalls remain valid code-level concerns; this file addresses the v1.3
> installation and containerisation layer added on top of that codebase.

---

## Critical Pitfalls

Mistakes that cause rewrites or major issues.

---

### Pitfall 1: Docker Container Paths Are Not Host Paths — Stored Absolute Paths Break After Containerisation

**What goes wrong:**
The v1.2 linked-folder feature stores absolute host paths in `linked_folders.path` and `tracks.file_path` (e.g., `/Users/alice/Music` on macOS or `C:/Users/alice/Music` on Windows). Inside a Docker container those paths do not exist. The `ScanLinkedFolder` job calls `new RecursiveDirectoryIterator($this->folder->path)` and `file_exists($track->file_path)` against these host paths — both fail silently or throw inside the container, producing an error status and marking every track unavailable.

**Why it happens:**
The feature was built and tested on a native Herd setup. Host paths and container paths were the same thing. Adding Docker without a mount-and-remap strategy means the DB stores host paths while the container filesystem has an entirely different tree.

**Consequences:**
- Every linked folder shows "error" status after `sail up`
- All local-linked tracks become unavailable
- `StreamController` returns 404 for every local track
- The Docker install path is effectively non-functional for local library linking

**Prevention:**
- Docker Compose volume mounts must map the user's host music directory into the container at a predictable container path (e.g., `/music`).
- The install script must prompt for the music library path and emit the correct `volumes:` entry in `docker-compose.override.yml`.
- The linked-folder UI must tell Docker users to enter the **container-side** path (e.g., `/music/Rock`) rather than their host path.
- Document this prominently in the README; it is the single biggest "it broke in Docker" complaint vector.

**Detection:**
- All linked folders enter "error" state after first `sail up`
- `php artisan tinker` inside the container: `file_exists('/Users/alice/Music')` returns `false`
- `ScanLinkedFolder` job fails with `DirectoryIterator: Failed to open dir: No such file or directory`

**Phase to address:** Docker Sail setup phase, before any testing of linked-folder functionality in Docker.

---

### Pitfall 2: UID/GID Mismatch Between Sail Container and Host — Permission Denied on Audio Files

**What goes wrong:**
Laravel Sail runs PHP-FPM as the `sail` user (UID 1000 by default). If the user's host music directory is owned by a different UID (common on macOS where the default user UID is also 501, or on Linux systems where it may be 1001+), the container process gets `Permission denied` when trying to read audio files via `fopen()` or `file_get_contents()`. The `ScanLinkedFolder` job succeeds for some files and silently skips others, producing an incomplete library with no visible error.

**Why it happens:**
Docker bind mounts do not remap ownership — the container sees the raw inode UID/GID from the host. Sail's image uses UID 1000, but most macOS users are UID 501. Linux users vary. The mismatch is invisible until runtime.

**Consequences:**
- Partial scans with unexplained missing tracks
- `StreamController` returns 403/404 for files it cannot open
- The `WWWUSER` / `WWWGROUP` Sail env vars are known but rarely set by new users

**Prevention:**
- The install script must detect the host UID/GID (`id -u` / `id -g` on Bash; `[System.Security.Principal.WindowsIdentity]::GetCurrent()` equivalent is not relevant for Docker on Windows — WSL2 handles it differently).
- Write those values to `.env` as `WWWUSER` and `WWWGROUP`.
- Sail's `docker-compose.yml` already passes these through to match permissions at build time — the install script just needs to populate them.
- On macOS with Docker Desktop, the virtual filesystem layer translates permissions automatically; on Linux with native Docker this is the primary failure mode.

**Detection:**
- `sail exec laravel.test ls -la /music` shows files owned by UID 501 or 1001
- Scan completes but track count is lower than expected
- `docker-compose logs laravel.test` shows PHP warnings about unreadable files

**Phase to address:** Docker volume mount configuration phase, in the install script.

---

### Pitfall 3: CRLF Line Endings in Bash Scripts Cloned on Windows — `bad interpreter` Error

**What goes wrong:**
Git on Windows defaults to `core.autocrlf=true`, converting LF to CRLF in all checked-out text files. The Bash install script (`install.sh`) gets CRLF line endings on Windows checkout. When a macOS or Linux user (or Docker container entrypoint) runs the script, bash reads the shebang line as `#!/bin/bash\r` — the `\r` is treated as part of the interpreter path, producing: `/bin/bash^M: bad interpreter: No such file or directory`. The script fails to run at all.

**Why it happens:**
The `.gitattributes` file does not enforce `eol=lf` for shell scripts. This is the single most common "works on the developer's Mac, fails everywhere else" shell script bug.

**Consequences:**
- Install script is broken on every Linux/Docker system for users who cloned on Windows
- Error message is cryptic; non-technical users give up

**Prevention:**
- Add `.gitattributes` to the repo root with:
  ```
  *.sh   text eol=lf
  *.bash text eol=lf
  ```
- This must be committed **before** the install scripts so the first clone gets correct endings.
- The install script itself should add a `dos2unix` call or `sed -i 's/\r//'` guard as a belt-and-suspenders check.

**Detection:**
- `file install.sh` reports "CRLF line terminators" on a file cloned on Windows
- The exact error `/bin/bash^M: bad interpreter` in the terminal

**Phase to address:** The phase that creates `install.sh` — the `.gitattributes` entry must be committed in the same phase, not as an afterthought.

---

### Pitfall 4: PowerShell Execution Policy Blocks the Install Script — Silent Failure for Non-Technical Users

**What goes wrong:**
Windows defaults to `Restricted` or `AllSigned` execution policy. Running `.\install.ps1` produces: "install.ps1 cannot be loaded because running scripts is disabled on this system." Non-technical users see this error, don't know what it means, and conclude the app is broken.

**Why it happens:**
PowerShell execution policy is not a security control (it has 15 bypass methods) but it is the default on clean Windows installs. Scripts downloaded from the internet are additionally blocked by the NTFS Alternate Data Stream Mark-of-the-Web (`Zone.Identifier = 3`) even with `RemoteSigned` policy — the user clones the repo, the files are marked as internet-origin, and the script is blocked.

**Consequences:**
- Windows users cannot run the install script at all
- The error message does not tell them how to fix it

**Prevention:**
- The README Windows section must include the exact `Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser` command to run first.
- Alternatively, ship a `install.bat` wrapper that invokes PowerShell with `-ExecutionPolicy Bypass` inline:
  ```bat
  powershell.exe -ExecutionPolicy Bypass -File "%~dp0install.ps1"
  ```
- The `.ps1` script should `Unblock-File` itself at the top if running from a downloaded archive.

**Detection:**
- "cannot be loaded because running scripts is disabled" error on first run
- `Get-ExecutionPolicy` returns `Restricted` or `AllSigned`

**Phase to address:** Windows install script phase, with a corresponding README entry in the docs phase.

---

### Pitfall 5: SQLite-to-MySQL Migration — Silent Type Coercion Differences Break Queries

**What goes wrong:**
The current `.env.example` defaults to `DB_CONNECTION=sqlite`. Switching to MySQL (the Sail default) exposes SQL that works in SQLite but fails in MySQL:

- SQLite allows inserting a string into an integer column; MySQL rejects it with a strict-mode error
- SQLite `boolean` columns accept `true`/`false` as strings; MySQL `TINYINT(1)` does not
- SQLite has no `json` column type and stores JSON as `TEXT`; some MySQL JSON functions behave differently
- SQLite allows `GROUP BY` on non-aggregated columns without listing them; MySQL strict mode rejects this
- String comparison in SQLite is case-insensitive by default; MySQL `utf8mb4_unicode_ci` collation is case-insensitive but `utf8mb4_bin` is case-sensitive — path comparisons for linked-folder deduplication may silently behave differently

**Why it happens:**
CI and development have always run on SQLite. MySQL is only exercised for the first time during the Docker install path. The discrepancies are not visible in tests because tests also use SQLite.

**Consequences:**
- `php artisan migrate` succeeds but application queries fail at runtime in Docker
- Path-based deduplication in `ScanLinkedFolder` may match differently under MySQL collation
- Data loaded during development via SQLite may fail to import into MySQL

**Prevention:**
- The Docker setup phase must include running the full Pest test suite against MySQL (not SQLite) as a gate.
- Add `DB_CONNECTION=mysql` to the `.env.testing.docker` example so CI can validate against MySQL too.
- The `file_path` column uses `string` (VARCHAR 255); confirm paths longer than 255 chars are handled — MySQL enforces this strictly, SQLite does not.

**Detection:**
- `SQLSTATE[HY000]: General error: 1364` (field doesn't have a default) — stricter MySQL NULL handling
- `SQLSTATE[42000]: Syntax error` for SQLite-specific functions used in raw queries
- Tests pass on SQLite CI but queries fail in Docker

**Phase to address:** Docker Sail configuration phase — MySQL compatibility must be validated before shipping.

---

### Pitfall 6: MySQL Container Not Ready When Laravel Boots — SQLSTATE[HY000] [2002] Connection Refused

**What goes wrong:**
`sail up -d` starts all containers simultaneously. Laravel's `php artisan migrate` (called by the install script immediately after `sail up`) runs before MySQL has finished initialising. The migration fails with `SQLSTATE[HY000] [2002] Connection refused`. The install script reports success if it does not check the exit code, leaving the app in a broken state.

**Why it happens:**
Docker Compose `depends_on` only waits for the container to start, not for MySQL to be ready to accept connections. MySQL takes 10–30 seconds on first boot to initialise its data directory.

**Consequences:**
- Fresh installs fail on the migration step
- Install script either errors out confusingly or silently proceeds with no database schema

**Prevention:**
- The install script must poll for MySQL readiness before running `migrate`:
  ```bash
  until sail exec mysql mysqladmin ping -h"127.0.0.1" --silent; do
    echo "Waiting for MySQL..."
    sleep 2
  done
  ```
- Alternatively, configure a `healthcheck` on the MySQL service in `docker-compose.override.yml` and use `depends_on: condition: service_healthy` for the app service.
- Set a maximum retry count (15 attempts) and fail with a clear error if MySQL never becomes ready.

**Detection:**
- `SQLSTATE[HY000] [2002] Connection refused` in the install output
- `docker-compose ps` shows MySQL as "starting" while the migrate command runs

**Phase to address:** Docker install script phase — the readiness poll must be in the script before the first `php artisan migrate` call.

---

## Moderate Pitfalls

---

### Pitfall 7: Port 80/3306/6379 Already in Use — Sail Fails to Start With No Guidance

**What goes wrong:**
`sail up` fails with `Bind for 0.0.0.0:80 failed: port is already allocated` if any other service on the host uses port 80 (Herd, nginx, Apache), 3306 (local MySQL), or 6379 (local Redis). The error is logged in Docker's output but the install script may not surface it clearly, and the user does not know how to fix it.

**Why it happens:**
Sail's default ports (80, 3306, 6379) are the standard ports for their services. Developers who have Herd installed (as this project's primary development environment) have PHP/nginx already on port 80.

**Prevention:**
- The install script must check port availability before `sail up`:
  ```bash
  # Bash
  for port in 80 3306 6379; do
    if lsof -i :"$port" -sTCP:LISTEN -t >/dev/null 2>&1; then
      echo "Port $port is in use. Set APP_PORT / FORWARD_DB_PORT / FORWARD_REDIS_PORT in .env."
      exit 1
    fi
  done
  ```
- Default the Sail ports to non-standard values (e.g., `APP_PORT=8080`) in `.env.example` to avoid conflicts with Herd.
- The README must explain how to change `APP_PORT`, `FORWARD_DB_PORT`, and `FORWARD_REDIS_PORT`.

**Detection:**
- `Bind for 0.0.0.0:80 failed: port is already allocated` in `docker-compose up` output
- `docker ps` shows no containers started despite `sail up` returning

**Phase to address:** Docker install script phase.

---

### Pitfall 8: Queue Worker Not Running in Native Mode — Scans Never Complete

**What goes wrong:**
The "native one-command run" path (non-Docker) starts `php artisan serve` and Vite but omits `php artisan queue:work`. The user links a folder, triggers a scan, sees the progress spinner, and it never moves. The `jobs` table accumulates pending jobs. No error is shown.

**Why it happens:**
`php artisan serve` only handles HTTP. Queue jobs are a separate process. Developers forget this because in development they often run `queue:work` manually in a separate terminal and never notice it is not part of the one-command setup.

**Prevention:**
- The native install/run script must start three processes in parallel and forward all output to the terminal:
  - `php artisan serve`
  - `npm run dev` (or Vite)
  - `php artisan queue:work --sleep=3 --tries=3`
- Use a `Procfile` with Foreman/Overmind (macOS/Linux) or PowerShell `Start-Job` (Windows).
- Validate in the health check step that the queue worker is processing: dispatch a trivial test job and confirm it is consumed within 10 seconds.

**Detection:**
- Scan spinner never advances
- `select count(*) from jobs;` in the DB shows growing count after linking a folder
- No queue worker process visible in `ps aux | grep queue`

**Phase to address:** Native install script phase — the process manager configuration must be part of the one-command setup.

---

### Pitfall 9: Homebrew / apt / Chocolatey PATH Not Updated in Current Shell — Dependency Check False Negative

**What goes wrong:**
The install script checks `which php`, `which composer`, `which node`, etc. to verify dependencies. If Homebrew was just installed in the same terminal session, its `/opt/homebrew/bin` is not yet in `$PATH` (Homebrew prints `eval "$(brew shellenv)"` but the user has not run it). The script reports "PHP not found" and fails, even though PHP was just installed one step earlier.

**Why it happens:**
Homebrew modifies `~/.zprofile` (not `~/.zshrc`), which only takes effect on new login shells. Running `brew install php` in the same shell session does not update `$PATH`. The same issue occurs with Chocolatey on Windows in the same cmd session (it adds to system PATH, not the current process environment).

**Prevention:**
- After any `brew install` call, explicitly `eval "$(brew shellenv)"` before the next dependency check.
- After any Chocolatey `choco install`, use the `refreshenv` command (available from Chocolatey's profile script) before re-checking.
- Re-source `$PATH` after each package manager call; do not assume PATH is updated.
- The script should print the full path of each found binary (`which php && php --version`) so the user can confirm it is from the expected location.

**Detection:**
- `which php` returns empty in the same terminal where `brew install php` just ran
- Script reports dependency missing immediately after installing it

**Phase to address:** Dependency detection phase of the install script.

---

### Pitfall 10: Health Check Returns HTTP 200 Before Laravel Is Actually Ready — False "Install Succeeded"

**What goes wrong:**
The install script issues a `curl -s http://localhost/` after starting the server. It gets a 200 response from the web server's default page (nginx welcome page, Sail's nginx config serving a static asset) before Laravel has booted, run migrations, or warmed up the config cache. The install script prints "Health check passed" when the app is actually broken.

**Why it happens:**
A simple HTTP 200 check validates the web server, not the application. Any static file, error page, or redirect can return 200.

**Prevention:**
- Health check must hit a Laravel-specific endpoint that exercises the database: add a dedicated `/healthz` route that returns `{"status":"ok","db":"connected"}` only after successfully querying the DB.
- The check should verify all of: HTTP status 200, JSON response, `db` field equals `connected`.
- Add `--start-period=30s` to Docker health checks to prevent false "unhealthy" during initial boot.
- For the native path, wait for `php artisan db:show` to succeed (exit code 0) before declaring readiness.

**Detection:**
- `curl http://localhost/` returns 200 but `curl http://localhost/dashboard` redirects to `/login` with an error about the database
- Health check passes but `php artisan migrate:status` fails with connection error

**Phase to address:** Health check implementation phase.

---

### Pitfall 11: WSL2 Path Translation — Windows Users Cannot Use Their Native Music Library Path

**What goes wrong:**
A Windows user's music library is at `C:\Users\alice\Music`. Docker Desktop on Windows uses WSL2 as its backend. The correct Docker bind-mount source path is `/mnt/c/Users/alice/Music` (WSL2 format) or `C:/Users/alice/Music` (Docker Desktop translates Windows drive paths with forward slashes). If the user enters `C:\Users\alice\Music` in the Foonk linked-folder UI (which is the natural Windows path), the database stores it, the container cannot find it, and scanning fails.

**Why it happens:**
The path normalisation in `LinkedFolder::path()` already converts backslashes to forward slashes (`C:/Users/alice/Music`), but `C:/Users/alice/Music` is still a Windows path — it does not map to `/mnt/c/Users/alice/Music` inside the container. There is a two-layer translation problem: path separator normalisation is solved, but drive-letter-to-mount-point translation is not.

**Prevention:**
- The Docker install guide for Windows must clearly state: "Inside the app, use the Linux path `/music` (the container-side mount point), not your Windows path."
- The volume mount in `docker-compose.override.yml` handles the host→container translation; the user only ever needs to know the container path.
- The linked-folder UI should display the container path (`/music`) as the suggested starting path when running in Docker, not the host path.
- Consider storing a `MUSIC_LIBRARY_PATH` env var (set during Docker install) that pre-populates the linked-folder path field.

**Detection:**
- Linked folder scan fails in Docker for Windows users with paths starting with `C:`
- `file_exists('C:/Users/alice/Music')` returns `false` inside the container

**Phase to address:** Docker install script + Docker volume configuration phase.

---

## Minor Pitfalls

---

### Pitfall 12: `storage:link` Fails Inside Docker Container on Re-run — "symlink already exists"

**What goes wrong:**
`php artisan storage:link` is part of the install script. Running it a second time (e.g., after `sail down && sail up`) produces: "The 'public/storage' link already exists." If the script treats all non-zero exit codes as failures, it aborts unnecessarily.

**Prevention:**
- Run `php artisan storage:link --relative` with a check: if exit code is non-zero and the symlink already exists, treat as success.
- Or use `--force` flag (available in Laravel 12) to overwrite without error.

**Phase to address:** Install script phase.

---

### Pitfall 13: `php artisan key:generate` Run Multiple Times — Different APP_KEY Invalidates Existing Sessions

**What goes wrong:**
The install script runs `php artisan key:generate` unconditionally. If a user re-runs the install script on an existing install, the APP_KEY changes, all encrypted values (sessions, remember tokens, encrypted columns) become invalid, and every logged-in user is logged out immediately.

**Prevention:**
- Only run `key:generate` if `APP_KEY` is empty in `.env`:
  ```bash
  if ! grep -q "^APP_KEY=base64:" .env; then
    php artisan key:generate
  fi
  ```

**Phase to address:** Install script phase.

---

### Pitfall 14: `npm install` During Docker Build Produces Different Node Modules Than Host — Vite Asset Mismatch

**What goes wrong:**
The install script runs `npm install` on the host, then mounts the project directory (including `node_modules`) into the container. `node_modules` built on macOS may include native binaries that fail to run on the Linux container. Alternatively, if `npm install` is run inside the container, the host `node_modules` is overwritten with Linux binaries, breaking native dev tooling.

**Prevention:**
- For Docker installs, Vite compilation should run inside the container: `sail run --rm laravel.test npm run build`.
- Do not mount `node_modules` as a host volume; add it to `.dockerignore` and install inside the container.
- The install script should document this distinction: `npm install` for native, `sail npm install` for Docker.

**Phase to address:** Docker Sail configuration phase.

---

### Pitfall 15: `composer install` Produces Optimised Autoloader — Config Cache Locks in Wrong DB Connection

**What goes wrong:**
Running `php artisan config:cache` during install writes the current `DB_CONNECTION=sqlite` value into the cache. When the user later switches to Docker with MySQL and changes `.env`, the cached config still references SQLite. Queries use the wrong driver. The error is: "InvalidArgumentException: Database connection [sqlite] not configured."

**Prevention:**
- Never run `config:cache` or `route:cache` during install scripts for development environments. These are production optimisations.
- If the install script runs them for performance, it must also handle cache invalidation on `.env` change.
- Document: "If you change `DB_CONNECTION`, run `php artisan config:clear`."

**Phase to address:** Install script phase.

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|---------------|------------|
| Docker Sail setup | MySQL not ready on first boot | Poll `mysqladmin ping` before `php artisan migrate` |
| Docker volume mounts | Host paths ≠ container paths for linked folders | Document container-side paths; prompt user in install script |
| Docker volume mounts | UID/GID mismatch on Linux hosts | Detect and write `WWWUSER`/`WWWGROUP` to `.env` |
| Bash install script | CRLF line endings from Windows Git clone | `.gitattributes` with `eol=lf` for all `.sh` files |
| PowerShell install script | Execution policy blocks the script | Ship a `.bat` wrapper with `-ExecutionPolicy Bypass` |
| Dependency detection | PATH not updated after Homebrew/Choco install | Re-eval PATH after each package manager call |
| One-command native run | Queue worker not started | Include `queue:work` in the Procfile / start script |
| Health check | False positive on web server default page | Hit a DB-exercising `/healthz` endpoint, not just `/` |
| Port conflict detection | Ports 80/3306/6379 in use by Herd or local services | Pre-flight port check in install script; default to 8080 |
| SQLite → MySQL | Type coercion differences fail at runtime | Run full test suite against MySQL in Docker before shipping |
| Windows Docker | Drive letter paths invalid inside container | Instruct users to use container mount path (`/music`) |
| Config cache | `config:cache` locks in SQLite, breaks MySQL switch | Do not run `config:cache` in dev install scripts |

---

## Sources

- [Laravel Sail permission issues — laravel/sail GitHub issue #457](https://github.com/laravel/sail/issues/457)
- [Laravel Sail permission issues — laravel/sail GitHub issue #459](https://github.com/laravel/sail/issues/459)
- [Docker Compose UID/GID permission mismatch — codegenes.net](https://www.codegenes.net/blog/docker-compose-and-named-volume-permission-denied/)
- [Fixing permissions issues with Docker Compose and PHP — Andrew Schmelyun](https://aschmelyun.com/blog/fixing-permissions-issues-with-docker-compose-and-php/)
- [CRLF line endings in bash scripts — techblog.dorogin.com](https://techblog.dorogin.com/case-of-windows-line-ending-in-bash-script-7236f056abe)
- [Bash scripts with CRLF in Docker containers — codestudy.net](https://www.codestudy.net/blog/bash-seamlessly-run-scripts-with-crlf-line-endings/)
- [CRLF vs LF normalising line endings in Git — aleksandrhovhannisyan.com](https://www.aleksandrhovhannisyan.com/blog/crlf-vs-lf-normalizing-line-endings-in-git/)
- [PowerShell execution policy — Microsoft Learn](https://learn.microsoft.com/en-us/powershell/module/microsoft.powershell.security/set-executionpolicy?view=powershell-7.5)
- [15 ways to bypass PowerShell execution policy — NetSPI](https://www.netspi.com/blog/technical-blog/network-pentesting/15-ways-to-bypass-the-powershell-execution-policy/)
- [Laravel Sail MySQL SQLSTATE 2002 connection refused — tutorialpedia.org](https://www.tutorialpedia.org/blog/laravel-sail-database-connection-refused-error/)
- [Mastering Laravel Sail — 10 tips for common challenges — Level Up Coding](https://levelup.gitconnected.com/mastering-laravel-sail-docker-10-tips-to-overcome-common-challenges-and-quirks-f45640941ec2)
- [Dockerizing Laravel queues, workers, and schedulers — sevalla.com](https://sevalla.com/blog/dockerizing-laravel-queues/)
- [Docker bind mounts — Docker official docs](https://docs.docker.com/engine/storage/bind-mounts/)
- [Docker Desktop WSL2 backend on Windows — Docker official docs](https://docs.docker.com/desktop/features/wsl/)
- [Absolute Linux paths with Docker Desktop for Windows — Docker Forums](https://forums.docker.com/t/absolute-linux-paths-with-docker-desktop-for-windows/130670)
- [SQLite to MySQL migration issues — Laracasts](https://laracasts.com/discuss/channels/testing/migration-issues-mysql-vs-sqlite)
- [Docker health checks — paulsblog.dev](https://www.paulsblog.dev/how-to-successfully-implement-a-healthcheck-in-docker-compose/)
- [Homebrew PATH not updated in current shell — brew.sh shellenv docs](https://docs.brew.sh/FAQ)
- Project codebase: `app/Jobs/ScanLinkedFolder.php` — uses `RecursiveDirectoryIterator($this->folder->path)` and `file_exists()` against stored absolute paths
- Project codebase: `app/Http/Controllers/StreamController.php` — serves `$track->file_path` directly, which is a host absolute path
- Project codebase: `app/Models/LinkedFolder.php` — normalises backslashes to forward slashes but does not translate drive letters

---

*Pitfalls research for: Easy Installation (cross-platform scripts + Docker support) — foonk v1.3*
*Researched: 2026-03-26*
