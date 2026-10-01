---
phase: 10-settings-migration
plan: 02
subsystem: ui
tags: [mary-ui, daisyui, livewire, flux-migration, theme, two-factor, appearance]

# Dependency graph
requires:
  - phase: 10-00
    provides: RED test stubs for AppearanceSettingsTest and TwoFactorAuthenticationTest
  - phase: 10-01
    provides: Settings layout, heading partial, Mary UI migration patterns established
provides:
  - Theme column on users table with default 'spotify-dark'
  - Appearance page with x-mary-toggle persisting theme to DB and updating data-theme on HTML
  - Light daisyUI theme configured in app.css
  - Two-factor setup modal using x-mary-modal + x-mary-pin OTP
  - Recovery codes page with Mary UI buttons and daisyUI styling
affects: [10-03, 10-04, phase-11]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "updatedIsDarkMode() Livewire lifecycle hook auto-fires on wire:model.live change"
    - "Alpine x-effect syncs isDark to document.documentElement data-theme attribute"
    - "x-mary-pin requires :size=6 (colon prefix) not size=6 — Pin.php constructor requires int"
    - "QR code always on white bg (bg-white p-3 rounded) — no $flux.appearance conditional"

key-files:
  created:
    - database/migrations/2026_03_17_000000_add_theme_to_users_table.php
  modified:
    - app/Models/User.php
    - resources/css/app.css
    - resources/views/layouts/app/sidebar.blade.php
    - resources/views/pages/settings/⚡appearance.blade.php
    - resources/views/pages/settings/⚡two-factor.blade.php
    - resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php

key-decisions:
  - "updatedIsDarkMode() lifecycle hook used instead of separate updateTheme() + wire:change — cleaner Livewire pattern"
  - "Theme persists as string ('spotify-dark' | 'light') in users.theme column, defaults to 'spotify-dark'"
  - "Only Dark and Light modes supported — no System mode per user decision in CONTEXT.md"
  - "x-mary-modal @close='closeModal' handles both backdrop clicks and ESC key"

patterns-established:
  - "Pattern: Livewire lifecycle hooks (updatedPropertyName) preferred over explicit wire:change handlers"
  - "Pattern: Alpine x-effect for reactive DOM attribute updates tied to Livewire state via @entangle"

requirements-completed: [SETTINGS-04, SETTINGS-05]

# Metrics
duration: 45min
completed: 2026-03-17
---

# Phase 10 Plan 02: Appearance and 2FA Settings Migration Summary

**Theme toggle with DB persistence via updatedIsDarkMode() hook + Alpine x-effect on data-theme, 2FA modal migrated from Flux to x-mary-modal/x-mary-pin, recovery codes with Mary UI buttons**

## Performance

- **Duration:** ~45 min
- **Started:** 2026-03-17T15:00:00Z
- **Completed:** 2026-03-17T16:00:00Z
- **Tasks:** 3 (Task 1 completed in prior session)
- **Files modified:** 6

## Accomplishments

- Theme column added to users table with default 'spotify-dark'; User model updated with fillable 'theme'
- Appearance page replaced flux:radio.group approach with x-mary-toggle persisting to database via updatedIsDarkMode() lifecycle hook; Alpine x-effect updates HTML data-theme attribute in real-time
- Two-factor setup page fully migrated: x-mary-modal replaces flux:modal, x-mary-pin (:size=6 :numeric=true) replaces flux:otp, QR code always on white background
- Recovery codes page migrated with x-mary-button throughout and daisyUI alert-error for errors
- Zero flux: references remain in any of the three settings pages
- AppearanceSettingsTest (5 tests, 8 assertions) and TwoFactorAuthenticationTest (4 tests, 8 assertions) all pass

## Task Commits

Each task was committed atomically:

1. **Task 1: Create theme migration, update User model and CSS, wire data-theme on HTML** - `1f84aac` (feat)
2. **Task 2: Migrate appearance page with database-backed theme toggle** - `90205ee` (feat)
3. **Task 3: Migrate two-factor and recovery codes pages** - `4830aed` (feat)

## Files Created/Modified

- `database/migrations/2026_03_17_000000_add_theme_to_users_table.php` - Migration adding theme varchar column to users table, default 'spotify-dark'
- `app/Models/User.php` - Added 'theme' to $fillable array
- `resources/css/app.css` - Added 'light' to daisyUI themes list alongside 'spotify-dark --default'
- `resources/views/layouts/app/sidebar.blade.php` - Added data-theme="{{ auth()->user()->theme ?? 'spotify-dark' }}" on html tag to prevent flash
- `resources/views/pages/settings/⚡appearance.blade.php` - Full rewrite: x-mary-toggle + updatedIsDarkMode() + Alpine x-effect
- `resources/views/pages/settings/⚡two-factor.blade.php` - x-mary-modal, x-mary-pin OTP, daisyUI badges, Mary icons
- `resources/views/pages/settings/two-factor/⚡recovery-codes.blade.php` - x-mary-button throughout, daisyUI alert-error

## Decisions Made

- **updatedIsDarkMode() lifecycle hook** used instead of `wire:change="updateTheme"` — Livewire's property update hooks fire automatically when `wire:model.live` changes, resulting in cleaner code with one fewer explicit event binding
- **Only Dark and Light modes** — No System mode per user decision captured in CONTEXT.md; the app defaults to 'spotify-dark' for all new users
- **@close="closeModal"** added to x-mary-modal — ensures backdrop click and ESC key both trigger the closeModal() Livewire method to reset state
- **QR code unconditional white background** — The $flux.appearance conditional style was removed entirely; QR codes always render on white (required for QR scanner readability regardless of theme)

## Deviations from Plan

None - plan executed exactly as written. All component mappings from the plan's interfaces section were applied verbatim.

## Issues Encountered

None - all tests passed on first run. The x-mary-pin `:size="6"` integer colon-binding (documented as critical in the plan) was already correctly applied in the pre-existing file state.

## User Setup Required

None - no external service configuration required. Theme column migration runs via `php artisan migrate`.

## Next Phase Readiness

- Appearance and 2FA settings fully migrated to Mary UI
- AppearanceSettingsTest and TwoFactorAuthenticationTest pass GREEN — RED stubs from Plan 10-00 are now resolved
- Phase 10 is complete — all settings pages migrated from Flux to Mary UI
- Plan 10-04 (if it exists) or Phase 11 can proceed

---
*Phase: 10-settings-migration*
*Completed: 2026-03-17*
