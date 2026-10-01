# Phase 20: PowerShell Install Script - Research

**Researched:** 2026-03-27
**Domain:** PowerShell 5.1 scripting — dependency detection, Chocolatey auto-install, UAC elevation via install.bat, Herd PHP path detection, PATH refresh
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- **D-01:** Auto-install missing dependencies via package manager, mirroring install.sh behavior. Consistent cross-platform experience.
- **D-02:** Detect Laravel Herd's PHP installation even if not on PATH. Check common Herd install paths (e.g. `C:\Users\<user>\.config\herd\bin`). If Herd PHP 8.2+ is found, use it and skip installing PHP via Chocolatey.
- **D-03:** If Herd PHP is found but not on PATH, add Herd's bin directory to the user's persistent PATH via `[Environment]::SetEnvironmentVariable`. Survives terminal restarts.
- **D-04:** If no package manager is available and Herd PHP is not found, offer to install Chocolatey first (similar to how install.sh prompts for Homebrew on macOS). Then retry dependency installation.
- **D-05:** Use Chocolatey as the Windows package manager for auto-installing PHP, Composer, and Node.js.
- **D-06:** `install.bat` auto-elevates to admin via UAC if not already running elevated. Chocolatey requires admin, so this prevents mid-script failures.
- **D-07:** After installing tools via Chocolatey, call `refreshenv` to reload environment variables in the current session. If refreshenv fails, manually read Machine/User PATH from registry and update `$env:PATH`. Script continues without requiring a terminal restart.
- **D-08:** Use PowerShell-native `Write-Host -ForegroundColor` for colored output. Same visual structure as install.sh (status icons like [OK]/[FAIL]/[WARN], section headers) but using PS-native coloring.
- **D-09:** Target PowerShell 5.1 compatibility (ships with every Windows 10/11). No PowerShell 7+ features.
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

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INST-02 | User can run `install.ps1` via `install.bat` wrapper on Windows to detect missing dependencies and get guided installation prompts | Full PowerShell 5.1 stack documented below: Get-Command for detection, Chocolatey for auto-install, UAC elevation pattern for install.bat, Herd PHP path detection, refreshenv/registry fallback for PATH refresh |
</phase_requirements>

---

## Summary

Phase 20 creates two files: `install.ps1` (main PowerShell script) and `install.bat` (entry-point wrapper). The bat file solves the execution policy problem by relaunching PowerShell with `-ExecutionPolicy Bypass` and auto-elevating to admin via UAC using `Start-Process ... -Verb RunAs`. The PS1 script mirrors `install.sh`'s flow: detect PHP/Composer/Node/npm, auto-install missing tools via Chocolatey, run `composer run setup`, and optionally start the dev server.

Two Windows-specific concerns beyond what install.sh handles: (1) Laravel Herd may have installed PHP at `%USERPROFILE%\.config\herd\bin\phpXX\php.exe` but not added it to PATH — the script must probe these version folders and add the path persistently before falling back to Chocolatey; (2) after Chocolatey installs packages, the current PowerShell session won't see the new binaries until PATH is refreshed — the script must call `refreshenv` (if Chocolatey profile is loaded) or manually rebuild `$env:PATH` from the registry.

**Primary recommendation:** Target PowerShell 5.1, use `$ErrorActionPreference = "Stop"` + `Set-StrictMode -Version 2`, `Get-Command -ErrorAction SilentlyContinue` for binary detection, Chocolatey package names `php`, `composer`, `nodejs`, the standard `[Security.Principal.WindowsPrincipal]` UAC check in install.bat, and a registry-based PATH refresh fallback.

---

## Standard Stack

### Core

| Tool | Version | Purpose | Why Standard |
|------|---------|---------|--------------|
| PowerShell | 5.1 (ships with Windows 10/11) | Script interpreter | Universally available on all target Windows machines without install |
| Chocolatey | Community | Windows package manager for PHP/Composer/Node.js | Most mature, widest package coverage; D-05 locked |
| `Get-Command -ErrorAction SilentlyContinue` | PS 5.1 built-in | Binary presence check (equivalent to `command -v`) | Standard PowerShell idiom; returns $null when not found |
| `[Environment]::SetEnvironmentVariable` | .NET built-in | Persistent PATH modification | Writes directly to registry; survives terminal restarts |
| `[Security.Principal.WindowsPrincipal]` | .NET built-in | Admin check for UAC elevation | Standard Windows identity pattern; works PS 2.0+ |

### Supporting

| Tool | Version | Purpose | When to Use |
|------|---------|---------|-------------|
| `Write-Host -ForegroundColor` | PS 5.1 | Colored console output | All status output (D-08); no ANSI escape codes needed |
| `$ErrorActionPreference = "Stop"` | PS 5.1 | Fail-fast on errors (equivalent to `set -e`) | Set at script top; catch specific non-terminating commands with try/catch |
| `Set-StrictMode -Version 2` | PS 5.1 | Catch uninitialized variables | Use Version 2 (not Latest) for deterministic behavior across PS versions |
| `Start-Process powershell -Verb RunAs` | PS 5.1 | UAC elevation from install.bat | Standard self-elevation pattern; triggers UAC prompt |
| `Import-Module "$env:ChocolateyInstall\helpers\chocolateyProfile.psm1"` | Chocolatey | Enable `refreshenv` in PS session | Must be imported before calling refreshenv |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Chocolatey | winget | winget is built into Windows 11 but not Windows 10 1809 or earlier; Chocolatey has broader `php` + `composer` coverage |
| Chocolatey | Scoop | Scoop installs to user home (no admin required) but `composer` package is less reliable; Chocolatey is locked per D-05 |
| `Write-Host -ForegroundColor` | ANSI escape sequences | ANSI requires Windows Terminal or PS 7; PS 5.1 in legacy cmd windows may not render them; Write-Host is the safe PS-native choice |
| `Set-StrictMode -Version Latest` | `-Version 2` | Latest changes meaning across PS versions (unpredictable in PS 7+); Version 2 is stable and sufficient |

**Installation:**

```bat
REM No npm/pip dependencies. Runtime: Chocolatey installs php, composer, nodejs.
REM Chocolatey itself installed via official PowerShell one-liner if absent.
```

---

## Architecture Patterns

### Recommended File Structure

```
install.bat          # Entry point: UAC elevation + ExecutionPolicy Bypass → calls install.ps1
install.ps1          # Main script: dependency detection, Chocolatey install, app setup
.gitattributes       # Add: *.ps1 text eol=crlf  *.bat text eol=crlf
```

### Script Structure (install.ps1)

```
install.ps1
├── $ErrorActionPreference = "Stop"
├── Set-StrictMode -Version 2
├── Output helpers: Write-Ok / Write-Fail / Write-Warn / Write-Header
│   └── Write-Host -ForegroundColor Green/Red/Yellow/White
├── Test-CommandExists helper (Get-Command -ErrorAction SilentlyContinue)
├── Find-HerdPhp: probe %USERPROFILE%\.config\herd\bin\phpXX folders
├── Add-ToUserPath: [Environment]::SetEnvironmentVariable persistent
├── Refresh-EnvPath: Import-Module chocolateyProfile OR registry fallback
├── Install-Chocolatey: official one-liner if choco absent
├── Check-PHP     → if absent: choco install php -y
├── Check-Composer → if absent: choco install composer -y
├── Check-Node    → if absent: choco install nodejs -y
├── Check-Npm     → confirm present after node install
├── Section: "Running app setup..."
├── composer run setup
├── Section: "Setup complete!"
└── Read-Host prompt → composer run dev
```

### Pattern 1: install.bat — ExecutionPolicy Bypass + UAC Elevation

**What:** The batch file checks if it's running as admin. If not, it relaunches itself elevated via `Start-Process`. Once elevated, it changes directory to the script's own folder and invokes `install.ps1` with `-ExecutionPolicy Bypass`.

**When to use:** Entry point for Windows users (double-click or CLI). Locked by D-06 and D-10.

```bat
@echo off
setlocal

:: Check if running as admin
net session >nul 2>&1
if %errorlevel% == 0 goto :ELEVATED

:: Re-launch as admin (UAC prompt will appear)
echo Requesting administrator privileges...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Start-Process cmd -ArgumentList '/c cd /d ""%~dp0"" && powershell -NoProfile -ExecutionPolicy Bypass -File ""%~dp0install.ps1""' -Verb RunAs"
exit /b

:ELEVATED
:: Already admin — run install.ps1 directly
pushd "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
popd
```

**Key detail:** `pushd "%~dp0"` sets the working directory to the script's own folder before invoking PS1. Without this, an elevated process starts in `C:\Windows\System32`.

### Pattern 2: Binary Detection with Get-Command

**What:** `Get-Command` returns the command object if found on PATH, or `$null` with `-ErrorAction SilentlyContinue`.

**When to use:** All four dependency checks (php, composer, node, npm).

```powershell
# Source: PowerShell 5.1 Get-Command documentation
function Test-CommandExists {
    param([string]$Name)
    return $null -ne (Get-Command -Name $Name -ErrorAction SilentlyContinue)
}

if (-not (Test-CommandExists 'php')) {
    Write-Warn "PHP not found — installing..."
    # install logic
}
```

### Pattern 3: Herd PHP Detection and PATH Addition

**What:** Laravel Herd for Windows installs PHP versions into `%USERPROFILE%\.config\herd\bin\phpXX\`. Each version has its own subfolder (e.g., `php84`, `php83`, `php82`). The script probes these in descending order and uses the first one meeting the 8.2 minimum.

**When to use:** Before checking PATH for `php`, per D-02.

```powershell
# Source: https://herd.laravel.com/docs/windows/advanced-usage/directories-and-files
function Find-HerdPhp {
    $herdBin = Join-Path $env:USERPROFILE '.config\herd\bin'
    # Probe in descending version order — pick highest available >= 8.2
    $versionFolders = @('php84', 'php83', 'php82')
    foreach ($folder in $versionFolders) {
        $phpExe = Join-Path $herdBin "$folder\php.exe"
        if (Test-Path $phpExe) {
            return $phpExe
        }
    }
    return $null
}
```

If found, add parent folder to persistent PATH (D-03):

```powershell
function Add-ToUserPath {
    param([string]$Directory)
    $currentUserPath = [Environment]::GetEnvironmentVariable('PATH', 'User')
    if ($currentUserPath -notlike "*$Directory*") {
        [Environment]::SetEnvironmentVariable('PATH', "$currentUserPath;$Directory", 'User')
        # Update current session immediately
        $env:PATH = "$env:PATH;$Directory"
    }
}
```

### Pattern 4: PHP Version Gate (PHP 8.2 minimum)

**What:** After locating PHP (via Herd path, PATH, or after Chocolatey install), verify version using `php -r` with PHP constants — same reliable approach as install.sh.

**When to use:** After PHP is confirmed present (D-11).

```powershell
# Source: PHP CLI built-in constants
function Get-PhpVersion {
    param([string]$PhpBin = 'php')
    return (& $PhpBin -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
}

function Assert-PhpVersion {
    param([string]$PhpBin = 'php')
    $version = Get-PhpVersion $PhpBin
    $parts = $version -split '\.'
    $major = [int]$parts[0]
    $minor = [int]$parts[1]
    if ($major -lt 8 -or ($major -eq 8 -and $minor -lt 2)) {
        Write-Fail "PHP $version is below the minimum required version."
        Write-Host "  Minimum required: PHP 8.2" -ForegroundColor White
        exit 1
    }
    Write-Ok "PHP $version"
}
```

### Pattern 5: Chocolatey Installation (if absent)

**What:** Install Chocolatey using the official PowerShell one-liner. Requires admin (already guaranteed by install.bat UAC elevation, D-06). Sets TLS 1.2 explicitly for compatibility.

**When to use:** When `choco` is absent and Herd PHP not found (D-04).

```powershell
# Source: https://chocolatey.org/install (official)
function Install-Chocolatey {
    Write-Warn "Chocolatey not found — installing..."
    Set-ExecutionPolicy Bypass -Scope Process -Force
    [System.Net.ServicePointManager]::SecurityProtocol = `
        [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
    Invoke-Expression ((New-Object System.Net.WebClient).DownloadString(
        'https://community.chocolatey.org/install.ps1'))
}
```

### Pattern 6: Chocolatey Package Installs

**What:** Use `choco install` with `-y` (yes to all prompts) and `--no-progress` for clean output.

**When to use:** When a dependency is absent.

```powershell
# Confirmed package names from community.chocolatey.org:
#   php       → PHP 8.5.3 (current)
#   composer  → Composer 6.3.0 (depends on php chocolatey package)
#   nodejs    → Node.js LTS
choco install php -y --no-progress
choco install composer -y --no-progress
choco install nodejs -y --no-progress
```

### Pattern 7: PATH Refresh After Chocolatey Install

**What:** After `choco install`, the current session's `$env:PATH` is stale. Refresh via Chocolatey's `Import-Module` + `refreshenv` if available, otherwise rebuild from registry.

**When to use:** After any `choco install` call (D-07).

```powershell
function Refresh-EnvPath {
    # Primary: use Chocolatey's Update-SessionEnvironment
    $chocoProfile = "$env:ChocolateyInstall\helpers\chocolateyProfile.psm1"
    if (Test-Path $chocoProfile) {
        Import-Module $chocoProfile -Force
        Update-SessionEnvironment  # or: refreshenv
        return
    }

    # Fallback: rebuild from registry (D-07)
    # Source: HKLM\SYSTEM\CurrentControlSet\Control\Session Manager\Environment
    $machinePath = (Get-ItemProperty `
        'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager\Environment' `
        -Name Path -ErrorAction SilentlyContinue).Path
    $userPath = (Get-ItemProperty `
        'HKCU:\Environment' -Name Path -ErrorAction SilentlyContinue).Path
    $paths = @($machinePath, $userPath) | Where-Object { $_ }
    $env:PATH = [string]::Join(';', $paths)
}
```

### Pattern 8: Output Helpers (D-08)

**What:** Write-Host with -ForegroundColor for consistent colored output. Mirrors [OK]/[FAIL]/[WARN] structure from install.sh.

```powershell
function Write-Ok   { param([string]$Msg) Write-Host "[OK]   $Msg" -ForegroundColor Green }
function Write-Fail { param([string]$Msg) Write-Host "[FAIL] $Msg" -ForegroundColor Red }
function Write-Warn { param([string]$Msg) Write-Host "[WARN] $Msg" -ForegroundColor Yellow }
function Write-Header {
    param([string]$Title)
    Write-Host ""
    Write-Host $Title -ForegroundColor White
    Write-Host ("-" * $Title.Length) -ForegroundColor White
}
```

### Anti-Patterns to Avoid

- **Using `where.exe` instead of `Get-Command`:** `where dep` is available but returns exit 1 with verbose error output when absent; `Get-Command -ErrorAction SilentlyContinue` returns clean `$null`.
- **Setting `$env:PATH` without also persisting via `[Environment]::SetEnvironmentVariable`:** Session-only change is lost on terminal restart. Always persist to User scope too (D-03).
- **Using `Set-StrictMode -Version Latest`:** Meaning changes across PS versions. Use `-Version 2` for deterministic behavior.
- **Running `php -r ...` immediately after `choco install php`:** PATH has not been refreshed yet. Always call `Refresh-EnvPath` before checking newly installed binaries.
- **Not using `pushd "%~dp0"` in install.bat:** Elevated process starts in `C:\Windows\System32`, making relative paths to `install.ps1` wrong.
- **Directly invoking `refreshenv` without `Import-Module`:** `refreshenv` is a function defined in Chocolatey's profile module; it is not available in PowerShell sessions that haven't imported it.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| App setup sequence | Custom PS1 install steps | `composer run setup` | Already implements idempotent, ordered setup with guards; mirrors install.sh |
| PATH refresh after install | Custom registry reader | Chocolatey's `Update-SessionEnvironment` + registry fallback | Handles expansion, merging, and edge cases correctly |
| PHP version comparison | Custom string split/compare | `php -r 'exit(PHP_MAJOR_VERSION >= 8 ...'` | PHP constants are locale-immune; no PowerShell version parsing needed |
| Windows UAC check | Manual process inspection | `[Security.Principal.WindowsPrincipal]::IsInRole` | .NET built-in; standard across all Windows versions |
| Execution policy handling | Instructing users to change policy | `-ExecutionPolicy Bypass` in install.bat | Per-process bypass; no system policy change required (D-10 locked) |

**Key insight:** The entire app setup is already in `composer run setup`. The PS1 script's sole job is to ensure four prerequisites exist, then hand off — identical in philosophy to install.sh.

---

## Common Pitfalls

### Pitfall 1: Working Directory Wrong After UAC Elevation

**What goes wrong:** install.bat relaunches elevated. The elevated cmd.exe process starts in `C:\Windows\System32`, not the project directory. The subsequent `install.ps1` invocation fails because the path is relative.

**Why it happens:** When Windows elevates via `Start-Process -Verb RunAs`, the working directory defaults to `System32`.

**How to avoid:** Use `%~dp0` (directory of the batch file itself) in all paths inside install.bat. Use `pushd "%~dp0"` before calling powershell. Use `-File "%~dp0install.ps1"` with absolute path.

**Warning signs:** Error like "Cannot find path ... install.ps1" on Windows after UAC prompt.

### Pitfall 2: Chocolatey Not on PATH in Same PowerShell Session

**What goes wrong:** After `choco install php`, the script immediately calls `php -r ...` and gets "php is not recognized as an internal or external command".

**Why it happens:** Chocolatey installs to `C:\ProgramData\chocolatey\bin` (machine PATH) and per-package locations. The current PowerShell process's `$env:PATH` was set at process start and is not automatically refreshed when the registry changes.

**How to avoid:** Always call `Refresh-EnvPath` immediately after any `choco install` command. Test with `Test-CommandExists` only after the refresh.

**Warning signs:** Command not found immediately after successful choco install output.

### Pitfall 3: Herd PHP Found But Composer Missing

**What goes wrong:** Herd's bin directory (`%USERPROFILE%\.config\herd\bin`) contains `composer.bat` or `composer.phar` as part of Herd's bundled tools. The script may skip the Composer install check if it detects the Herd-bundled version is enough.

**Why it happens:** Herd bundles Composer alongside PHP at the same bin path.

**How to avoid:** After adding the Herd bin directory to PATH and calling `Refresh-EnvPath`, check `Test-CommandExists 'composer'` — if Herd's composer is found, use it and print [OK]. No Chocolatey install needed.

**Warning signs:** `choco install composer` fails with "already installed" or installs a second Composer that conflicts.

### Pitfall 4: UAC Prompt Appears in Background Window

**What goes wrong:** On some Windows configurations, the UAC consent dialog opens behind the current terminal window. The user doesn't see it and thinks the script hung.

**Why it happens:** UAC dialogs sometimes render behind focused windows.

**How to avoid:** Print a message to the user before triggering elevation: "Requesting administrator privileges — check for a UAC prompt...". This is a UX note, not a code fix.

**Warning signs:** Script appears to hang after "Requesting administrator privileges..." message.

### Pitfall 5: .ps1 File With LF Line Endings Breaks Script Signatures

**What goes wrong:** If the PS1 file is committed with LF line endings and a developer ever signs the script, the signature check fails because Windows PowerShell expects CRLF in signed files.

**Why it happens:** `* text=auto eol=lf` in `.gitattributes` applies globally including `.ps1` files.

**How to avoid:** Add `*.ps1 text eol=crlf` and `*.bat text eol=crlf` to `.gitattributes`. These files are Windows-native and should use CRLF. The existing `*.sh text eol=lf` rule handles the Unix scripts.

**Warning signs:** Signature validation errors, or `file install.ps1` on a fresh checkout reports "LF line terminators".

### Pitfall 6: `$ErrorActionPreference = "Stop"` Breaks Non-Critical Registry Reads

**What goes wrong:** Reading the user PATH from `HKCU:\Environment` with `Get-ItemProperty` throws a terminating error if the key doesn't exist (e.g., on a fresh system with no user PATH entries).

**Why it happens:** `Stop` turns all errors into terminating exceptions; missing registry keys are non-critical.

**How to avoid:** Always use `-ErrorAction SilentlyContinue` on registry reads that might legitimately not exist. Handle `$null` return values with `Where-Object { $_ }` filtering.

---

## Code Examples

Verified patterns from official sources:

### Admin Check in install.bat

```bat
@echo off
setlocal

:: Check elevation using net session (requires no external tools)
net session >nul 2>&1
if %errorlevel% == 0 goto :ELEVATED

echo Requesting administrator privileges...
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "Start-Process cmd -ArgumentList '/c pushd ""%~dp0"" && powershell -NoProfile -ExecutionPolicy Bypass -File ""%~dp0install.ps1""' -Verb RunAs"
exit /b

:ELEVATED
pushd "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install.ps1"
popd
```

### Error Handling Setup (top of install.ps1)

```powershell
# Source: Microsoft PowerShell 5.1 documentation
$ErrorActionPreference = "Stop"
Set-StrictMode -Version 2
```

### Chocolatey Install (official)

```powershell
# Source: https://chocolatey.org/install
Set-ExecutionPolicy Bypass -Scope Process -Force
[System.Net.ServicePointManager]::SecurityProtocol =
    [System.Net.ServicePointManager]::SecurityProtocol -bor 3072
Invoke-Expression ((New-Object System.Net.WebClient).DownloadString(
    'https://community.chocolatey.org/install.ps1'))
```

### PATH Refresh — Registry Fallback

```powershell
# Source: HKLM registry path documented by Microsoft; pattern derived from
# Chocolatey's RefreshEnv.cmd logic
$machinePath = (Get-ItemProperty `
    'HKLM:\SYSTEM\CurrentControlSet\Control\Session Manager\Environment' `
    -Name Path -ErrorAction SilentlyContinue).Path
$userPath = (Get-ItemProperty `
    'HKCU:\Environment' -Name Path -ErrorAction SilentlyContinue).Path
$env:PATH = (@($machinePath, $userPath) | Where-Object { $_ }) -join ';'
```

### Dev Server Prompt

```powershell
# Mirror install.sh D-13 behavior
$answer = Read-Host "Start dev server? [Y/n]"
if ([string]::IsNullOrWhiteSpace($answer) -or $answer -match '^[Yy]$') {
    composer run dev
}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|-----------------|--------------|--------|
| `where.exe dep` | `Get-Command -ErrorAction SilentlyContinue` | PS 2.0+ | No external binary; clean $null return |
| `Set-ExecutionPolicy RemoteSigned` (system-wide) | `-ExecutionPolicy Bypass` per process | Windows 8+ | No permanent policy change; user-safe |
| `[System.Net.ServicePointManager]::SecurityProtocol = 192` | `-bor 3072` (TLS 1.2) | Windows 8.1 era | Required for downloading from modern HTTPS endpoints |
| Manual install URL construction | `refreshenv` / `Update-SessionEnvironment` | Chocolatey 0.9.9+ | Single call refreshes all env vars from registry |
| `#Requires -RunAsAdministrator` (PS 4.0+) | `IsInRole` check + Start-Process RunAs | PS 2.0+ | `#Requires` terminates without a friendly message; IsInRole allows graceful elevation |

**Deprecated/outdated:**

- `Set-StrictMode -Version Latest`: Avoid — meaning changes across PS versions; use `-Version 2`.
- `where.exe` for binary detection: Replaced by `Get-Command -ErrorAction SilentlyContinue`.
- Instructing users to run `Set-ExecutionPolicy RemoteSigned`: Replaced by `-ExecutionPolicy Bypass` per-process in install.bat.

---

## .gitattributes Changes Required

The existing `.gitattributes` has `* text=auto eol=lf` globally. Two additions are needed:

```gitattributes
*.ps1 text eol=crlf
*.bat text eol=crlf
```

**Rationale:** `.bat` files must have CRLF to be executed by cmd.exe on Windows via file shares and some Windows environments. `.ps1` files with LF line endings can break script signature verification and are non-standard on Windows. Both are Windows-native formats and should be CRLF while cross-platform scripts (`.sh`) remain LF.

**Source:** https://rehansaeed.com/gitattributes-best-practices/ (bat files); LF issue #25246 in PowerShell/PowerShell repo (ps1 files).

---

## Herd PHP Path Reference

Laravel Herd for Windows stores PHP binaries at:

```
%USERPROFILE%\.config\herd\bin\php84\php.exe    (PHP 8.4)
%USERPROFILE%\.config\herd\bin\php83\php.exe    (PHP 8.3)
%USERPROFILE%\.config\herd\bin\php82\php.exe    (PHP 8.2)
%USERPROFILE%\.config\herd\bin\composer.bat     (bundled Composer)
```

**Source:** https://herd.laravel.com/docs/windows/advanced-usage/directories-and-files — "You can find all binaries of Herd in this directory."

The script should probe `php84` → `php83` → `php82` in descending order and use the first match. If found, add the parent folder of the matching php.exe to the user's persistent PATH so subsequent `php` calls work.

---

## Open Questions

1. **Does Herd bundle Composer at the same bin path?**
   - What we know: Herd's bin directory contains "binaries like composer or expose that ship with Herd" per official docs.
   - What's unclear: Whether `composer.bat` or `composer` is the exact filename and whether it's always present.
   - Recommendation: After adding Herd bin to PATH, check `Test-CommandExists 'composer'` — if found, skip Chocolatey Composer install. The script flow handles this naturally if detection runs after PATH refresh.

2. **Does `choco install composer` require `choco install php` to have run first?**
   - What we know: Community Chocolatey page notes composer "has a dependency on the Chocolatey PHP package". If PHP was provided by Herd rather than Chocolatey, `choco install composer` might still install Chocolatey's PHP alongside it.
   - What's unclear: Whether Chocolatey's dependency check is satisfiable by a non-Chocolatey PHP on PATH.
   - Recommendation: If Herd PHP is already on PATH, install Composer via Chocolatey last (after node) to give `choco install composer` the best chance of detecting existing PHP. If it installs a duplicate PHP, that's acceptable — the Herd PHP will remain highest in PATH if added to User scope.

3. **TLS 1.2 requirement for Chocolatey install on older Windows 10**
   - What we know: `[System.Net.ServicePointManager]::SecurityProtocol -bor 3072` forces TLS 1.2 which is required by chocolatey.org.
   - What's unclear: Whether Windows 10 1607 (the minimum for our target) has TLS 1.2 enabled by default.
   - Recommendation: Include the `SecurityProtocol` line unconditionally — it's a no-op if TLS 1.2 is already default and a fix when it isn't. Flag for live Windows testing.

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Pest 4.x |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=PowerShellInstallScriptTest` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INST-02 | `install.ps1` exists at project root | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.ps1` uses `$ErrorActionPreference = "Stop"` | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.ps1` checks for php, composer, node, npm | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.ps1` enforces PHP 8.2 minimum | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.ps1` uses Chocolatey for installs | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.ps1` delegates to `composer run setup` | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.ps1` probes Herd PHP paths | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.bat` exists at project root | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `install.bat` invokes ps1 with `-ExecutionPolicy Bypass` | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `.gitattributes` has `*.ps1 text eol=crlf` | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |
| INST-02 | `.gitattributes` has `*.bat text eol=crlf` | unit | `php artisan test --compact --filter=PowerShellInstallScriptTest` | ❌ Wave 0 |

These follow the `BashInstallScriptTest.php` pattern exactly: file content assertions using `file_get_contents` + Pest `describe` blocks, using a `psTestProjectRoot()` helper function for `__DIR__`-relative path resolution.

### Sampling Rate

- **Per task commit:** `php artisan test --compact --filter=PowerShellInstallScriptTest`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps

- [ ] `tests/Feature/PowerShellInstallScriptTest.php` — covers INST-02 (file content assertions following `BashInstallScriptTest` pattern)

*(No framework gaps — Pest 4.x and test infrastructure already installed.)*

---

## Sources

### Primary (HIGH confidence)

- https://herd.laravel.com/docs/windows/advanced-usage/directories-and-files — Herd bin directory path `%USERPROFILE%\.config\herd\bin`; version subfolder structure
- https://community.chocolatey.org/packages/php — confirmed package name `php`, current version 8.5.3
- https://community.chocolatey.org/packages/composer — confirmed package name `composer`, version 6.3.0
- https://community.chocolatey.org/packages/nodejs — confirmed package name `nodejs`, version 25.8.x
- Microsoft PowerShell 5.1 docs — `Get-Command`, `$ErrorActionPreference`, `Set-StrictMode`, `[Environment]::SetEnvironmentVariable`, `[Security.Principal.WindowsPrincipal]`
- https://chocolatey.org/install — official Chocolatey PS install one-liner with `SecurityProtocol -bor 3072`

### Secondary (MEDIUM confidence)

- https://steviecoaster.dev/blog/refreshenv/ — `Update-SessionEnvironment` usage; `Import-Module chocolateyProfile.psm1` requirement
- https://rehansaeed.com/gitattributes-best-practices/ — `*.bat text eol=crlf` recommendation
- https://github.com/chocolatey/choco/blob/master/src/chocolatey.resources/redirects/RefreshEnv.cmd — confirmed HKLM + HKCU registry PATH merge logic for manual fallback
- https://ss64.com/ps/syntax-elevate.html — UAC elevation pattern `Start-Process ... -Verb RunAs`

### Tertiary (LOW confidence — requires live Windows testing)

- Herd PHP presence at `.config\herd\bin\phpXX` — documentation confirms the directory; exact version subfolder names (`php84` vs `php8.4`) confirmed via bug reports but not official docs
- TLS 1.2 availability on Windows 10 1607 — assumed enabled; `SecurityProtocol -bor 3072` guards against this
- Phase 20 risk (from STATE.md): PowerShell Windows detection is MEDIUM confidence — flag for live Windows testing

---

## Metadata

**Confidence breakdown:**

- Standard stack: HIGH — Chocolatey package names verified against community.chocolatey.org; PS 5.1 patterns verified against official Microsoft docs
- Architecture: HIGH — UAC elevation pattern, Get-Command idiom, and registry PATH refresh are well-documented PS 5.1 patterns
- Herd PHP paths: MEDIUM — directory path confirmed by official docs; version subfolder naming (`php84`) confirmed by community bug reports but not official docs
- PATH refresh: HIGH — registry locations and merge logic confirmed by Chocolatey source and Microsoft docs

**Research date:** 2026-03-27
**Valid until:** 2026-09-27 (Chocolatey package names and Herd paths are stable; PS 5.1 is frozen)
