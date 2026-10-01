# Phase 20: PowerShell Install Script - Context

**Gathered:** 2026-03-27
**Status:** Ready for planning

<domain>
## Phase Boundary

Windows install script (`install.ps1`) with `install.bat` wrapper that detects missing dependencies (PHP 8.2+, Composer, Node.js, npm), auto-installs them via Chocolatey, and runs the app setup. Mirrors the same flow as `install.sh` (Phase 19) but for Windows users.

</domain>

<decisions>
## Implementation Decisions

### Installation Approach
- **D-01:** Auto-install missing dependencies via package manager, mirroring install.sh behavior. Consistent cross-platform experience.
- **D-02:** Detect Laravel Herd's PHP installation even if not on PATH. Check common Herd install paths (e.g. `C:\Users\<user>\.config\herd\bin`). If Herd PHP 8.2+ is found, use it and skip installing PHP via Chocolatey.
- **D-03:** If Herd PHP is found but not on PATH, add Herd's bin directory to the user's persistent PATH via `[Environment]::SetEnvironmentVariable`. Survives terminal restarts.
- **D-04:** If no package manager is available and Herd PHP is not found, offer to install Chocolatey first (similar to how install.sh prompts for Homebrew on macOS). Then retry dependency installation.

### Windows Package Manager
- **D-05:** Use Chocolatey as the Windows package manager for auto-installing PHP, Composer, and Node.js.
- **D-06:** `install.bat` auto-elevates to admin via UAC if not already running elevated. Chocolatey requires admin, so this prevents mid-script failures.

### PATH Refresh Handling
- **D-07:** After installing tools via Chocolatey, call `refreshenv` to reload environment variables in the current session. If refreshenv fails, manually read Machine/User PATH from registry and update `$env:PATH`. Script continues without requiring a terminal restart.

### Output Style
- **D-08:** Use PowerShell-native `Write-Host -ForegroundColor` for colored output. Same visual structure as install.sh (status icons like [OK]/[FAIL]/[WARN], section headers) but using PS-native coloring.
- **D-09:** Target PowerShell 5.1 compatibility (ships with every Windows 10/11). No PowerShell 7+ features.

### Carried Forward (locked from prior phases)
- **D-10:** `install.bat` invokes `install.ps1` with `-ExecutionPolicy Bypass` — no manual policy change required (STATE.md).
- **D-11:** Same 4 dependencies as install.sh: PHP 8.2+, Composer, Node.js, npm (Phase 19 D-04/D-05).
- **D-12:** After all deps pass, automatically run `composer run setup` (Phase 19 D-06).
- **D-13:** After setup completes, ask "Start dev server? [Y/n]" and run `composer run dev` if confirmed (Phase 19 D-07).
- **D-14:** `.gitattributes` already enforces LF line endings for shell scripts (Phase 19 D-12).

### Claude's Discretion
- Exact PowerShell function structure and error handling patterns
- Herd PHP detection paths (exact directory structure to check)
- Chocolatey install command details and package names
- How to implement the UAC auto-elevation in install.bat
- Whether to use `Set-StrictMode` or `$ErrorActionPreference` patterns
- Exact refreshenv implementation/fallback logic

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Existing Scripts (mirror targets)
- `install.sh` -- The bash install script this phase mirrors for Windows. Read for flow structure, output format, and dependency check order.
- `composer.json` (scripts section) -- The `setup` and `dev` script definitions that install.ps1 will invoke after deps pass.

### Configuration
- `.gitattributes` -- Already enforces LF line endings. May need `*.ps1` and `*.bat` line ending rules added.

### Prior Phase Context
- `.planning/phases/19-bash-install-script/19-CONTEXT.md` -- Bash install script decisions that this phase mirrors
- `.planning/phases/17-native-foundation/17-CONTEXT.md` -- Native install decisions (setup is idempotent, `composer run setup` is canonical)

### Requirements
- `.planning/REQUIREMENTS.md` -- INST-02 requirement for this phase

### State
- `.planning/STATE.md` -- Contains install.bat execution policy bypass decision and Phase 20 risk flag

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `install.sh`: Complete reference implementation with same flow (detect OS, check deps, auto-install, run setup, offer dev server). PowerShell script mirrors this structure.
- `composer.json` setup/dev scripts: Same entry points used by both install.sh and install.ps1
- `.gitattributes`: Already has global LF enforcement; needs `*.ps1` and `*.bat` rules added

### Established Patterns
- Status output format: `[OK]`, `[FAIL]`, `[WARN]` prefixes with colored text (install.sh lines 26-28)
- Section headers with bold text and separator lines (install.sh lines 29-32)
- Dependency check order: PHP -> Composer -> Node.js -> npm (install.sh lines 241-244)
- Fail-fast on critical errors with guidance message (install.sh exit pattern)

### Integration Points
- `install.bat` is the user-facing entry point (double-click or CLI) that invokes `install.ps1`
- `install.ps1` delegates to `composer run setup` after deps pass (same as install.sh)
- README (Phase 22) will reference `install.bat` as the Windows install command
- `.gitattributes` may need `*.ps1 text eol=crlf` and `*.bat text eol=crlf` for Windows-native line endings

</code_context>

<specifics>
## Specific Ideas

No specific requirements -- open to standard approaches

</specifics>

<deferred>
## Deferred Ideas

None -- discussion stayed within phase scope

</deferred>

---

*Phase: 20-powershell-install-script*
*Context gathered: 2026-03-27*
