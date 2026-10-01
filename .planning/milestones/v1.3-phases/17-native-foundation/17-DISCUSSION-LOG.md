# Phase 17: Native Foundation - Discussion Log

> **Audit trail only.** Do not use as input to planning, research, or execution agents.
> Decisions are captured in CONTEXT.md — this log preserves the alternatives considered.

**Date:** 2026-03-26
**Phase:** 17-native-foundation
**Areas discussed:** Port conflict detection, Setup script scope, Dev runner tweaks

---

## Port Conflict Detection

| Option | Description | Selected |
|--------|-------------|----------|
| Auto-pick and start | Detect conflict, find next free port (8001, 8002...), print a notice, and start automatically | :heavy_check_mark: |
| Suggest and wait | Detect conflict, suggest an alternative port, and exit — user must re-run | |
| Prompt user | Detect conflict, show situation, ask user to confirm alternative before starting | |

**User's choice:** Auto-pick and start
**Notes:** No user interaction needed — just print where it ended up.

### Ports to Check

| Option | Description | Selected |
|--------|-------------|----------|
| Web server only (8000) | Only check artisan serve port. Vite handles its own fallback natively. | |
| Web + Vite (8000, 5173) | Check both ports. More thorough. | :heavy_check_mark: |

**User's choice:** Web + Vite (8000, 5173)
**Notes:** None

### Detection Mechanism

| Option | Description | Selected |
|--------|-------------|----------|
| PHP script | Small PHP script using fsockopen. Cross-platform, no OS-specific tools. | :heavy_check_mark: |
| Shell check in concurrently | Use lsof/netstat before launching. Requires OS-specific commands. | |

**User's choice:** PHP script
**Notes:** None

---

## Setup Script Scope

### Idempotency

| Option | Description | Selected |
|--------|-------------|----------|
| Fully idempotent | Skip completed steps. Safe for first-run AND re-runs. | :heavy_check_mark: |
| First-run only | Optimize for fresh clone. Manual fix on re-run. | |

**User's choice:** Fully idempotent
**Notes:** None

### Makefile Consolidation

| Option | Description | Selected |
|--------|-------------|----------|
| Keep both, sync them | Update composer setup to match Makefile. Keep Makefile as convenience alias. | |
| Remove Makefile | Consolidate into `composer run setup` only. One canonical way to install. | :heavy_check_mark: |
| Keep both, different purpose | Makefile for make-preferring devs, composer for everyone else. | |

**User's choice:** Remove Makefile
**Notes:** Single canonical install command.

### SQLite Auto-create

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, auto-create | Add `touch database/database.sqlite` to setup script | :heavy_check_mark: |
| No, user creates it | Assume user knows or has different DB connection | |

**User's choice:** Yes, auto-create
**Notes:** None

### Success Output

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, with URL | Print success message with next step guidance | |
| Silent | No output beyond what each command prints | :heavy_check_mark: |

**User's choice:** Silent
**Notes:** None

---

## Dev Runner Tweaks

### Port Detection Integration

| Option | Description | Selected |
|--------|-------------|----------|
| Detect before launch | Run PHP port detection first, pass resolved ports to artisan serve and Vite | :heavy_check_mark: |
| Separate command | User runs `composer run check-ports` manually first | |

**User's choice:** Detect before launch
**Notes:** Integrated into `composer run dev` flow.

### Queue Worker Mode

| Option | Description | Selected |
|--------|-------------|----------|
| Keep queue:listen | Current behavior. Auto-restarts on code changes. Better DX. | :heavy_check_mark: |
| Switch to queue:work | More efficient but doesn't pick up code changes without restart. | |

**User's choice:** Keep queue:listen
**Notes:** None

### Startup URL Output

| Option | Description | Selected |
|--------|-------------|----------|
| Yes, print URLs | Print resolved web URL before launching concurrently | :heavy_check_mark: |
| No extra output | Let artisan serve and Vite print their own messages | |

**User's choice:** Yes, print URLs
**Notes:** Especially helpful when port shifted from default.

---

## Claude's Discretion

- PHP port detection script implementation details (argument parsing, scanning logic)
- Edge case handling for no available ports
- `@php` prefix usage in composer scripts

## Deferred Ideas

None — discussion stayed within phase scope
