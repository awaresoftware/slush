---
phase: 01-authentication-system
plan: 04
subsystem: auth
tags: [livewire, admin, user-management, fortify, invitations, settings]

# Dependency graph
requires:
  - phase: 01-authentication-system
    provides: "01-01 through 01-03 — User model with is_admin, UserInvitation model, UserInvitedNotification, invite routes"
provides:
  - Admin-only settings/users Livewire component with invite, delete, and password reset actions
  - Settings nav conditional Users link (admins only)
  - Route /settings/users protected by auth + abort(403) for non-admins
  - Updated registration tests covering first-user-is-admin and registration-locked behaviors
affects: [04-audio-player, 05-playlists]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "View-based Livewire (Volt) component with @php state/action/computed blocks"
    - "Admin gate enforced inside component with abort(403) rather than route middleware"
    - "Password::sendResetLink() used to trigger Fortify reset emails for managed users"

key-files:
  created:
    - resources/views/pages/settings/⚡users.blade.php
    - tests/Feature/Auth/UserManagementTest.php
  modified:
    - routes/settings.php
    - resources/views/pages/settings/layout.blade.php
    - tests/Feature/Auth/RegistrationTest.php

key-decisions:
  - "Admin gate enforced inside the Livewire component with abort(403) — route stays in auth group without a separate gate middleware"
  - "Admin cannot delete themselves — self-delete guard in deleteUser action (no button rendered for own row)"
  - "Password reset uses Password::sendResetLink() with user email — delegates to Fortify's configured broker"

patterns-established:
  - "Settings pages follow view-based Volt pattern: ⚡name.blade.php with @php state/action/computed at top"
  - "Admin-only actions abort(403) server-side; nav link also gated with @if(auth()->user()->is_admin)"

requirements-completed: [AUTH-01, AUTH-02, AUTH-03, AUTH-04]

# Metrics
duration: 15min
completed: 2026-03-09
---

# Phase 1 Plan 4: Admin user management settings page with invite, delete, and password reset

**Admin-only Livewire settings page at /settings/users: invite new users by email (UserInvitedNotification), delete non-admin users, and trigger Fortify password reset emails, with conditional Users nav link and full test coverage**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-09T12:00:00Z
- **Completed:** 2026-03-09T12:23:47Z
- **Tasks:** 3 (2 code + 1 human-verify checkpoint)
- **Files modified:** 5

## Accomplishments
- Admin settings/users Livewire component with invite form, user list, delete action, and password reset action
- Settings nav updated to show Users link only to admin users
- Full test suite in UserManagementTest covering all access control, invite, delete, and password reset flows
- Registration tests extended with first-user-is-admin and registration-locked-after-first-user assertions
- Human checkpoint approved — full authentication system verified end-to-end

## Task Commits

Each task was committed atomically:

1. **Task 1: Admin users settings page (Livewire component + route)** - `1bd4d46` (feat) + `371040a` (test)
2. **Task 2: Settings nav update and existing test fixes** - `2121057` (feat)
3. **Task 3: Checkpoint human-verify** - no commit (human verification)

## Files Created/Modified
- `resources/views/pages/settings/⚡users.blade.php` - Volt Livewire component: invite form, user list, delete/reset actions, admin gate
- `routes/settings.php` - Added Route::livewire for settings/users
- `resources/views/pages/settings/layout.blade.php` - Conditional Users nav link for admins
- `tests/Feature/Auth/UserManagementTest.php` - Full coverage: access control, invite, delete, reset password
- `tests/Feature/Auth/RegistrationTest.php` - Added first-user-admin and registration-locked tests

## Decisions Made
- Admin gate enforced inside the Livewire component via abort(403) — keeps the route in the standard auth group without requiring custom gate middleware
- Self-delete prevention in the deleteUser action (user->id === auth()->id() guard); button also not rendered for the admin's own row
- Password reset delegates to Fortify's configured Password broker via Password::sendResetLink()

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Complete authentication system delivered and human-verified: registration lock, invite flow, admin user management, password reset, settings nav
- Phase 04 (audio player) can begin — auth system is fully operational
- All auth tests passing

---
## Self-Check: PASSED

- FOUND: resources/views/pages/settings/⚡users.blade.php
- FOUND: routes/settings.php
- FOUND: resources/views/pages/settings/layout.blade.php
- FOUND: tests/Feature/Auth/UserManagementTest.php
- FOUND: .planning/phases/01-authentication-system/01-04-SUMMARY.md
- FOUND commit: 1bd4d46
- FOUND commit: 2121057

*Phase: 01-authentication-system*
*Completed: 2026-03-09*
