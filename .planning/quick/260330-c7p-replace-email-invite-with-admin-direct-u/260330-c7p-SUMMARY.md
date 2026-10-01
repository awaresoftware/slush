---
phase: quick
plan: 260330-c7p
subsystem: auth
tags: [auth, users, middleware, invitation, livewire, pest]
dependency_graph:
  requires: []
  provides: [force-password-change-middleware, admin-direct-user-creation]
  affects: [auth, user-management, settings]
tech_stack:
  added: []
  patterns: [ForcePasswordChange middleware, factory state, Livewire form action]
key_files:
  created:
    - app/Http/Middleware/ForcePasswordChange.php
    - database/migrations/2026_03_30_065327_add_force_password_change_to_users_table.php
    - database/migrations/2026_03_30_065513_drop_user_invitations_table.php
    - tests/Feature/Auth/ForcePasswordChangeTest.php
  modified:
    - app/Models/User.php
    - database/factories/UserFactory.php
    - bootstrap/app.php
    - resources/views/pages/settings/⚡password.blade.php
    - resources/views/pages/settings/⚡users.blade.php
    - routes/web.php
    - tests/Feature/Auth/UserManagementTest.php
  deleted:
    - app/Models/UserInvitation.php
    - app/Notifications/UserInvitedNotification.php
    - database/factories/UserInvitationFactory.php
    - resources/views/pages/auth/accept-invite.blade.php
    - tests/Feature/Auth/InviteTest.php
    - tests/Feature/Auth/UserInvitationTest.php
decisions:
  - "ForcePasswordChange middleware allows livewire/update paths through to avoid breaking Livewire component requests during forced redirect"
  - "Password warning banner uses existing alert/alert-warning daisyUI classes consistent with project style"
  - "Middleware appended to web group (not auth group) so it runs early in the stack"
metrics:
  duration_seconds: 224
  completed_date: "2026-03-30"
  tasks_completed: 3
  files_changed: 13
---

# Quick Task 260330-c7p: Replace Email Invite with Admin Direct User Creation

**One-liner:** Eliminated email invitation system; admin now creates users directly with name/email/password, and new users must change their password on first login via ForcePasswordChange middleware.

## What Was Built

Admin can create user accounts directly from `/settings/users` without email delivery. The created user receives a `force_password_change=true` flag that redirects them to the password settings page until they update their password.

## Tasks Completed

| # | Task | Commit | Key Changes |
|---|------|--------|-------------|
| 1 | Add force_password_change column, middleware, password page update | 7bb4bbe | migration, User model, ForcePasswordChange middleware, bootstrap/app.php, password blade |
| 2 | Replace invite form with direct user creation, remove invitation system | 246d540 | users blade rewrite, routes cleanup, 6 files deleted, drop migration |
| 3 | Write tests for direct user creation and force-password-change flow | 91035b5 | UserManagementTest rewrite, ForcePasswordChangeTest (6 new tests) |

## Test Results

- 16 tests pass across UserManagementTest and ForcePasswordChangeTest (28 assertions)
- 1 pre-existing failure in AuthenticationTest (login screen redirects when no users exist — pre-existing RequireNoUsers behavior documented in STATE.md)

## Deviations from Plan

None — plan executed exactly as written.

## Self-Check

- [x] `app/Http/Middleware/ForcePasswordChange.php` — created
- [x] `database/migrations/2026_03_30_065327_add_force_password_change_to_users_table.php` — created and ran
- [x] `database/migrations/2026_03_30_065513_drop_user_invitations_table.php` — created and ran
- [x] `tests/Feature/Auth/ForcePasswordChangeTest.php` — created
- [x] Commits 7bb4bbe, 246d540, 91035b5 exist
- [x] No UserInvitation references remain in codebase

## Self-Check: PASSED
