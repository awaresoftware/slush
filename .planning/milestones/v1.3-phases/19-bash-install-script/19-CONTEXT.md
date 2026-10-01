# Phase 19: Bash Install Script - Context

**Gathered:** 2026-03-26
**Status:** Ready for planning

<domain>
## Phase Boundary

macOS and Linux install script (`install.sh`) that detects missing dependencies, auto-installs them, runs the app setup, and optionally starts the dev server. This phase also ensures shell scripts use LF line endings on all platforms.

</domain>

<decisions>
## Implementation Decisions

### Installation Assistance
- **D-01:** Auto-install missing dependencies silently without asking. Detect what's missing and install it immediately.
- **D-02:** Exception: Homebrew on macOS. If Homebrew itself is missing, stop and prompt the user to install it (explain why it's needed). Don't auto-install a package manager.
- **D-03:** Support three Linux package managers: apt (Ubuntu/Debian), dnf (Fedora/RHEL), pacman (Arch). Auto-detect which is available and use it.

### Dependency Scope
- **D-04:** Check four core dependencies only: PHP 8.2+, Composer, Node.js, npm. No Git check (user cloned the repo), no PHP extension checks (bundled by default).
- **D-05:** Enforce minimum PHP 8.2+ version. Fail on PHP 5.x/7.x/8.0/8.1. Let Composer handle exact version compatibility beyond that.

### Post-Check Behavior
- **D-06:** After all deps pass, automatically run `composer run setup` (no confirmation prompt — consistent with the silent auto-install approach).
- **D-07:** After setup completes, ask "Start dev server? [Y/n]" and run `composer run dev` if confirmed.
- **D-08:** Native install path only. No Docker detection or alternative path suggestions. Docker has its own flow (Phase 18).

### Output Style
- **D-09:** Clean professional output with status icons (checkmark/cross/arrow). ANSI colors: green=pass, red=fail, yellow=warn. Section headers. No emoji.
- **D-10:** Auto-detect TTY for color support. Use colors when interactive, plain text when piped or redirected (standard Unix pattern).
- **D-11:** Fail fast on critical errors. Stop immediately with a clear error message and guidance on what to do next. No partial-state accumulation.

### CRLF Protection
- **D-12:** `.gitattributes` already has `* text=auto eol=lf` which forces LF on all text files including shell scripts. Verify this satisfies INST-04 — may need explicit `*.sh text eol=lf` line for clarity.

### Claude's Discretion
- Exact bash functions and script structure (helper functions, error traps, etc.)
- How to detect the OS (macOS vs Linux) and distro family
- Specific package names per distro (e.g., `php` vs `php8.4` vs `php-cli`)
- Node.js installation method on Linux (NodeSource, distro packages, or nvm)
- Whether to use `set -euo pipefail` or manual error checking

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing Scripts
- `composer.json` (lines 44-58) — The `setup` and `dev` script definitions that `install.sh` will invoke
- `bin/detect-port.php` — Port detection script reference for how the project handles PHP CLI scripts
- `docker/start.sh` — Existing bash script in the repo; reference for script conventions

### Configuration
- `.gitattributes` — Already has `* text=auto eol=lf`; verify if shell-script-specific rules needed for INST-04

### Prior Phase Context
- `.planning/phases/17-native-foundation/17-CONTEXT.md` — Native install decisions (setup is idempotent, `composer run setup` is canonical)

### Requirements
- `.planning/REQUIREMENTS.md` — INST-01, INST-03, INST-04 requirements for this phase

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `composer.json` setup script: Complete install sequence (composer install, .env copy, sqlite touch, key:generate, migrate, npm install/build, storage:link) — `install.sh` delegates to this
- `composer.json` dev script: Calls `bin/detect-port.php` which handles multi-process startup via concurrently
- `.gitattributes`: Already enforces LF line endings globally

### Established Patterns
- PHP CLI scripts in `bin/` directory (detect-port.php)
- Bash scripts in `docker/` directory (start.sh)
- Composer scripts as the canonical interface for setup/dev operations

### Integration Points
- `install.sh` invokes `composer run setup` after deps are satisfied
- `install.sh` optionally invokes `composer run dev` after setup
- PowerShell script (Phase 20) will mirror the same flow for Windows
- README (Phase 22) will reference `./install.sh` as the macOS/Linux install command

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

*Phase: 19-bash-install-script*
*Context gathered: 2026-03-26*
