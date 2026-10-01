# Phase 20: PowerShell Install Script - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md -- this log preserves the alternatives considered.

**Date:** 2026-03-27
**Phase:** 20-powershell-install-script
**Areas discussed:** Installation approach, Windows package manager, PATH refresh handling, Output style

---

## Installation Approach

| Option | Description | Selected |
|--------|-------------|----------|
| Auto-install (Recommended) | Mirror install.sh behavior -- auto-install missing deps via a Windows package manager (Chocolatey or Winget). Consistent experience across platforms. | ✓ |
| Detect and guide | Only detect what's missing, then print specific install commands or download URLs. User runs them manually. Safer on Windows where package managers need admin elevation. | |
| Hybrid | Try auto-install if a supported package manager is present, otherwise fall back to printing guidance with download links. | |

**User's choice:** Auto-install (Recommended)
**Notes:** None

### Follow-up: Herd PHP Detection

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, detect Herd PHP (Recommended) | Check common Herd install paths. If Herd PHP 8.2+ is found, use it and skip installing PHP via package manager. | ✓ |
| No, PATH only | Only check if php is on PATH. Simpler logic, avoids Herd-specific coupling. | |

**User's choice:** Yes, detect Herd PHP (Recommended)
**Notes:** None

### Follow-up: Herd PATH Handling

| Option | Description | Selected |
|--------|-------------|----------|
| Add to session PATH | Temporarily add Herd's PHP to current session PATH. Doesn't persist. | |
| Add to user PATH permanently (Recommended) | Add Herd's bin directory to persistent PATH via [Environment]::SetEnvironmentVariable. | ✓ |
| Just use the full path | Don't modify PATH. Invoke PHP using absolute path throughout script. | |

**User's choice:** Add to user PATH permanently (Recommended)
**Notes:** None

### Follow-up: Fallback When No Package Manager

| Option | Description | Selected |
|--------|-------------|----------|
| Print download links and exit (Recommended) | Show direct download URLs with brief instructions. Clean exit. | |
| Offer to install Chocolatey first | If no package manager found, offer to install Chocolatey, then retry. Like install.sh prompts for Homebrew. | ✓ |

**User's choice:** Offer to install Chocolatey first
**Notes:** None

---

## Windows Package Manager

| Option | Description | Selected |
|--------|-------------|----------|
| Chocolatey (Recommended) | Most established Windows package manager. Has php, composer, nodejs packages. Requires admin elevation. | ✓ |
| Winget | Microsoft's built-in package manager. No extra install needed on modern Windows. Growing catalog but Composer availability limited. | |
| Try Winget first, Chocolatey fallback | Prefer Winget since built-in. Fall back to Chocolatey if needed. More complex logic. | |

**User's choice:** Chocolatey (Recommended)
**Notes:** None

### Follow-up: Admin Elevation

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, auto-elevate (Recommended) | install.bat uses UAC elevation trick to relaunch as admin. Prevents mid-script failures. | ✓ |
| No, fail with message | Print message telling user to right-click > Run as Administrator. | |

**User's choice:** Yes, auto-elevate (Recommended)
**Notes:** None

---

## PATH Refresh Handling

| Option | Description | Selected |
|--------|-------------|----------|
| refreshenv + session PATH (Recommended) | Call Chocolatey's refreshenv to reload env vars. If fails, manually read PATH from registry. Script continues without restart. | ✓ |
| Restart prompt | Tell user to close and reopen terminal, then re-run install.bat. | |
| You decide | Let Claude pick best approach. | |

**User's choice:** refreshenv + session PATH (Recommended)
**Notes:** None

---

## Output Style

| Option | Description | Selected |
|--------|-------------|----------|
| PowerShell Write-Host (Recommended) | Use Write-Host -ForegroundColor. Native PS approach. Same visual structure as install.sh. | ✓ |
| ANSI escape codes | Same escape code approach as install.sh. Works in Windows Terminal and PS 7+ but may fail in PS 5.1. | |
| You decide | Let Claude pick most compatible approach. | |

**User's choice:** PowerShell Write-Host (Recommended)
**Notes:** None

### Follow-up: PowerShell Version Target

| Option | Description | Selected |
|--------|-------------|----------|
| PowerShell 5.1 (Recommended) | Ships with every Windows 10/11. Maximum compatibility. | ✓ |
| PowerShell 7+ | Modern PS with better features but requires separate install. | |

**User's choice:** PowerShell 5.1 (Recommended)
**Notes:** None

---

## Claude's Discretion

- Exact PowerShell function structure and error handling patterns
- Herd PHP detection paths (exact directory structure)
- Chocolatey install command details and package names
- UAC auto-elevation implementation in install.bat
- `Set-StrictMode` / `$ErrorActionPreference` patterns
- Exact refreshenv implementation/fallback logic

## Deferred Ideas

None -- discussion stayed within phase scope
