# Phase 19: Bash Install Script - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-26
**Phase:** 19-bash-install-script
**Areas discussed:** Installation assistance, Dependency scope, Post-check behavior, Output style

---

## Installation Assistance

| Option | Description | Selected |
|--------|-------------|----------|
| Offer to install | Detect missing, show command, ask Y/n, run if confirmed | |
| Print guidance only | Detect missing, print commands, user runs manually | |
| Auto-install silently | Detect and install missing deps automatically without asking | ✓ |

**User's choice:** Auto-install silently
**Notes:** No confirmation prompts for dependency installation. Fast and automatic.

### Follow-up: Homebrew on macOS

| Option | Description | Selected |
|--------|-------------|----------|
| Auto-install Homebrew too | Install Homebrew automatically if missing | |
| Stop and prompt for Homebrew | Auto-install deps via brew, but prompt if brew itself is missing | ✓ |
| Print Homebrew instructions | Print install command and exit if brew missing | |

**User's choice:** Stop and prompt for Homebrew
**Notes:** Homebrew is a big system change — don't auto-install a package manager, but do explain why it's needed.

### Follow-up: Linux package managers

| Option | Description | Selected |
|--------|-------------|----------|
| apt + dnf + pacman | Cover Ubuntu/Debian, Fedora/RHEL, and Arch (~95% of Linux users) | ✓ |
| apt only | Ubuntu/Debian only, generic instructions for others | |
| apt + dnf | Two biggest distro families, skip Arch | |

**User's choice:** apt + dnf + pacman
**Notes:** None

---

## Dependency Scope

| Option | Description | Selected |
|--------|-------------|----------|
| Just the core four | PHP 8.x, Composer, Node, npm. Git assumed, SQLite bundled. | ✓ |
| Add Git check | Also verify Git is installed | |
| Add Git + PHP extensions | Check Git plus PHP extensions (sqlite3, mbstring, openssl) | |

**User's choice:** Just the core four
**Notes:** None

### Follow-up: PHP version enforcement

| Option | Description | Selected |
|--------|-------------|----------|
| Enforce PHP 8.2+ | Warn/fail below 8.2, let Composer catch exact mismatches | ✓ |
| Any PHP is fine | Just check php exists | |
| Enforce exactly PHP 8.4 | Strict 8.4.x check | |

**User's choice:** Enforce PHP 8.2+
**Notes:** None

---

## Post-Check Behavior

| Option | Description | Selected |
|--------|-------------|----------|
| Auto-run setup + offer dev | Run composer run setup automatically, then ask to start dev server | ✓ |
| Auto-run setup only | Run setup, print dev instructions | |
| Confirm before setup | Ask Y/n before running setup | |

**User's choice:** Auto-run setup + offer dev
**Notes:** None

### Follow-up: Docker path detection

| Option | Description | Selected |
|--------|-------------|----------|
| No — native only | Script is for native install. Docker has its own flow. | ✓ |
| Mention Docker at the end | Print informational note about Docker alternative | |

**User's choice:** No — native only
**Notes:** None

---

## Output Style

| Option | Description | Selected |
|--------|-------------|----------|
| Clean with status icons | ✓/✗/→ icons, ANSI colors, section headers, no emoji | ✓ |
| Minimal — text only | Plain text, no colors, works everywhere | |
| Friendly with emoji | Emoji-heavy, casual tone | |

**User's choice:** Clean with status icons
**Notes:** None

### Follow-up: Color fallback

| Option | Description | Selected |
|--------|-------------|----------|
| Auto-detect | Check TTY, use colors when interactive, plain when piped | ✓ |
| Always use colors | Always emit ANSI codes | |

**User's choice:** Auto-detect
**Notes:** None

### Follow-up: Error handling

| Option | Description | Selected |
|--------|-------------|----------|
| Fail fast with clear message | Stop immediately on critical failure with guidance | ✓ |
| Continue and summarize | Try all steps, collect and report failures at end | |

**User's choice:** Fail fast with clear message
**Notes:** None

---

## Claude's Discretion

- Exact bash functions and script structure
- OS/distro detection approach
- Specific package names per distro
- Node.js installation method on Linux
- Whether to use `set -euo pipefail` or manual error checking

## Deferred Ideas

None — discussion stayed within phase scope
