---
phase: 01-authentication-system
plan: 03
subsystem: auth
tags: [invite, notifications, user-invitation, token, fortify]

# Dependency graph
requires:
  - phase: 01-01
    provides: is_admin column on users table, registration gate middleware
  - phase: 01-02
    provides: DaisyUI dark Spotify guest layout pattern (layouts.guest + x-mary-input)
provides:
  - user_invitations table with email, token, accepted_at, expires_at
  - UserInvitation model with isExpired(), isUsed(), scopeValid()
  - UserInvitationFactory with expired and used states
  - UserInvitedNotification sending mail with invite link
  - GET /invite/{token} and POST /invite/{token} routes (invite.accept, invite.store)
  - accept-invite view using dark Spotify theme
affects: [04-audio-player, 05-playlists, 01-04-settings]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Invite token lifecycle: Str::random(64), 72h expiry, accepted_at marks used, abort(404) for invalid
    - Route closures in web.php for invite flow (no dedicated controller needed)
    - UserInvitation::scopeValid() builder scope chains whereNull + where expires_at future

key-files:
  created:
    - database/migrations/2026_03_08_230033_create_user_invitations_table.php
    - app/Models/UserInvitation.php
    - database/factories/UserInvitationFactory.php
    - app/Notifications/UserInvitedNotification.php
    - resources/views/pages/auth/accept-invite.blade.php
    - tests/Feature/Auth/UserInvitationTest.php
    - tests/Feature/Auth/InviteTest.php
  modified:
    - routes/web.php

key-decisions:
  - "Invite route closures placed in web.php outside any auth middleware group — unauthenticated users must be able to access invite links"
  - "POST /invite/{token} validation returns redirect-with-errors (web form behavior), not 422 JSON — test updated to assertSessionHasErrors"
  - "User name derived from email prefix (explode @ email) since invited users have no name yet — settings page can update it later"

patterns-established:
  - "Token-based invite flow: generate token, store in user_invitations, email link, validate on access, mark accepted_at on use"
  - "TDD: RED tests drive interface discovery (scopeValid, factory states), GREEN implements minimum code to pass"

requirements-completed: [AUTH-02]

# Metrics
duration: 10min
completed: 2026-03-09
---

# Phase 01 Plan 03: Invite-Based User Creation Summary

**UserInvitation model with 72-hour expiry tokens, mail notification, and accept-invite route+view completing the admin-invites-user flow**

## Performance

- **Duration:** 10 min
- **Started:** 2026-03-08T23:00:17Z
- **Completed:** 2026-03-08T23:10:00Z
- **Tasks:** 2
- **Files modified:** 8

## Accomplishments
- `user_invitations` table and Eloquent model with `isExpired()`, `isUsed()`, `scopeValid()` methods and factory with `expired`/`used` states
- `UserInvitedNotification` sends a mail message with subject "You've been invited to join {app.name}" and the accept-invite URL
- `GET /invite/{token}` shows the password-set form; `POST /invite/{token}` creates the user, marks the invite used, logs them in, and redirects to dashboard
- Accept-invite view matches the dark Spotify DaisyUI theme using `@extends('layouts.guest')` and `x-mary-input` components

## Task Commits

Each task was committed atomically:

1. **Task 1: UserInvitation model, migration, and factory** - `5cef543` (feat)
2. **Task 2: Invite notification, accept-invite routes and view** - `fa33434` (feat)

**Plan metadata:** _(docs commit hash — see final commit)_

_Note: TDD tasks followed RED → GREEN → format → commit flow_

## Files Created/Modified
- `database/migrations/2026_03_08_230033_create_user_invitations_table.php` - Creates user_invitations table
- `app/Models/UserInvitation.php` - Model with isExpired(), isUsed(), scopeValid()
- `database/factories/UserInvitationFactory.php` - Factory with expired and used states
- `app/Notifications/UserInvitedNotification.php` - Mail notification with invite link
- `resources/views/pages/auth/accept-invite.blade.php` - Password-set form, dark Spotify theme
- `routes/web.php` - Added GET/POST /invite/{token} routes
- `tests/Feature/Auth/UserInvitationTest.php` - 7 model method tests
- `tests/Feature/Auth/InviteTest.php` - 10 route and notification tests

## Decisions Made
- Invite routes placed outside all auth middleware groups so unauthenticated users can access invite links
- Web form validation redirects back with errors (assertSessionHasErrors) rather than 422 JSON
- User name derived from email prefix until settings page allows editing

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test assertion corrected from assertStatus(422) to assertSessionHasErrors for web form validation**
- **Found during:** Task 2 (InviteTest GREEN phase — password mismatch test returned 302 not 422)
- **Issue:** Plan specified "returns 422 with validation error" but web form validation in Laravel redirects back with errors flashed to session, not a 422 JSON response
- **Fix:** Changed `assertStatus(422)` to `assertSessionHasErrors('password')` in InviteTest
- **Files modified:** `tests/Feature/Auth/InviteTest.php`
- **Verification:** All 10 InviteTest tests pass
- **Committed in:** `fa33434` (Task 2 commit)

---

**Total deviations:** 1 auto-fixed (1 bug in test assertion)
**Impact on plan:** Minor — assertion method corrected to match actual Laravel web validation behavior. No scope creep.

## Issues Encountered
- `php artisan make:test` created test files in a nested path (`tests/Feature/tests/Feature/Auth/`) rather than directly in `tests/Feature/Auth/`. Files were moved manually before writing content.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- User invite flow complete: admin can send invite, user accepts via link, creates password, logged in
- `invite.accept` and `invite.store` route names available for use in settings page (Plan 04)
- `UserInvitedNotification` ready to dispatch from settings invite form
- All auth tests: 39 passed, 4 skipped (email verification — expected)

## Self-Check: PASSED
