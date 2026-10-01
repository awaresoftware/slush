---
phase: 01-authentication-system
plan: 02
subsystem: auth
tags: [daisyui, maryui, blade, tailwindcss, fortify, dark-theme]

# Dependency graph
requires:
  - phase: 01-authentication-system
    provides: "Fortify-based auth routes and Blade view files (login, register, forgot-password, reset-password)"
provides:
  - "DaisyUI v5 + MaryUI installed and configured with custom spotify-dark theme"
  - "Dark Spotify-themed guest layout (bg-[#121212], card bg-[#282828])"
  - "All four auth views rewritten with MaryUI components (x-mary-input, x-mary-button)"
  - "Login page conditionally shows 'Create account' link only when User::count() === 0"
  - "No logo or app name on any auth page"
affects:
  - 01-authentication-system
  - 02-user-profiles

# Tech tracking
tech-stack:
  added:
    - "daisyui@5.5.19 (npm dev dependency, Tailwind v4 plugin)"
    - "robsontenorio/mary (composer, MaryUI Blade component library)"
  patterns:
    - "DaisyUI @plugin syntax for Tailwind v4 (not require() config)"
    - "Custom DaisyUI theme via @plugin 'daisyui/theme' with oklch color values"
    - "MaryUI x-mary-input with errorField prop for validation errors"
    - "Auth views extend layouts.guest via @extends('layouts.guest')"
    - "data-theme='spotify-dark' on <html> element to activate DaisyUI theme"

key-files:
  created:
    - "resources/views/layouts/guest.blade.php (rewritten as dark Spotify layout)"
  modified:
    - "resources/css/app.css (DaisyUI plugin + spotify-dark custom theme)"
    - "package.json / package-lock.json (daisyui npm dependency)"
    - "composer.json / composer.lock (robsontenorio/mary)"
    - "resources/views/pages/auth/login.blade.php"
    - "resources/views/pages/auth/register.blade.php"
    - "resources/views/pages/auth/forgot-password.blade.php"
    - "resources/views/pages/auth/reset-password.blade.php"

key-decisions:
  - "DaisyUI v5 uses @plugin 'daisyui' + @plugin 'daisyui/theme' syntax for Tailwind v4 — not require() in tailwind.config.js"
  - "Theme colors expressed as oklch() values (not hex) per DaisyUI v5 requirements"
  - "MaryUI errorField prop used for per-field validation error display (built-in MaryUI feature)"
  - "All auth views use @extends('layouts.guest') / @section('content') pattern — consistent with existing register.blade.php baseline"
  - "Login 'Create account' link gated on User::count() === 0 — disappears once first user exists"

patterns-established:
  - "Guest layout pattern: @extends('layouts.guest') + @section('content') for all non-authenticated pages"
  - "MaryUI form components: x-mary-input with errorField, x-mary-button with class='btn-primary'"
  - "No branding on auth pages: zero logo, zero app name, form-only minimalism"

requirements-completed: [AUTH-01, AUTH-03]

# Metrics
duration: 3min
completed: 2026-03-04
---

# Phase 1 Plan 02: Auth Views Summary

**Dark Spotify-themed auth UI using DaisyUI v5 + MaryUI Blade components — login, register, forgot-password, reset-password all rewritten with dark backgrounds, green accent, and no app branding**

## Performance

- **Duration:** 3 min
- **Started:** 2026-03-04T12:12:44Z
- **Completed:** 2026-03-04T12:15:21Z
- **Tasks:** 2 (plus human-verify checkpoint)
- **Files modified:** 9

## Accomplishments

- Installed DaisyUI v5 as npm dev dependency and configured a custom `spotify-dark` theme (dark background, Spotify green accent) via Tailwind v4 `@plugin` syntax with oklch color values
- Installed MaryUI (robsontenorio/mary) via composer — Blade components (x-mary-input, x-mary-button) now available globally
- Rewrote guest layout and all four auth views with dark Spotify theme: bg-[#121212] background, bg-[#282828] card, green (#1DB954) buttons — no logo or app name anywhere
- Login page dynamically hides "Create account" link once any user exists (User::count() === 0 gate)
- All 22 auth feature tests pass (4 skipped for disabled email verification)

## Task Commits

Each task was committed atomically:

1. **Task 1: Install DaisyUI and MaryUI, configure dark Spotify theme** - `1544d1b` (chore)
2. **Task 2: Rewrite guest layout and all auth views** - `58af448` (feat)

## Files Created/Modified

- `resources/css/app.css` - Added DaisyUI @plugin with custom spotify-dark theme (oklch colors)
- `package.json` / `package-lock.json` - daisyui@5.5.19 dev dependency
- `composer.json` / `composer.lock` - robsontenorio/mary package
- `resources/views/layouts/guest.blade.php` - Rewritten: dark minimal layout, data-theme, @yield('content')
- `resources/views/pages/auth/login.blade.php` - MaryUI inputs/button, remember me, conditional create account link
- `resources/views/pages/auth/register.blade.php` - MaryUI inputs/button, back to login link
- `resources/views/pages/auth/forgot-password.blade.php` - MaryUI input/button, session status, back to login
- `resources/views/pages/auth/reset-password.blade.php` - Hidden token field, MaryUI inputs/button

## Decisions Made

- **DaisyUI v5 syntax:** Uses `@plugin "daisyui"` and `@plugin "daisyui/theme"` for Tailwind v4 — the plan noted this may differ from older syntax; DaisyUI v5 requires oklch color values not hex.
- **oklch colors:** All DaisyUI theme colors expressed in oklch() notation (e.g., `oklch(69.5% 0.196 152.5)` for Spotify green) as required by DaisyUI v5.
- **MaryUI errorField prop:** Used `errorField="fieldname"` attribute on x-mary-input components to wire per-field validation error display.
- **@extends approach:** All auth views use `@extends('layouts.guest')` + `@section('content')` — consistent with the existing register.blade.php pattern called out in the plan.
- **User::count() gate:** Login page checks `\App\Models\User::count() === 0` inline — no helper method needed for a one-time gate.

## Deviations from Plan

None - plan executed exactly as written. The DaisyUI v4 vs v5 syntax note in the plan was handled correctly by using the installed v5 syntax from the start.

## Issues Encountered

None - build succeeded, all tests passed.

## User Setup Required

None - no external service configuration required. Frontend compiled successfully with `npm run build`.

## Next Phase Readiness

- Dark Spotify auth theme is complete and verified
- DaisyUI and MaryUI are available for any future auth-related views (invite flow, password-set)
- All four Fortify auth routes have styled views: login, register, forgot-password, reset-password
- Human visual checkpoint (Task 3) required before proceeding to plan 01-03

---
*Phase: 01-authentication-system*
*Completed: 2026-03-04*

## Self-Check: PASSED

- FOUND: .planning/phases/01-authentication-system/01-02-SUMMARY.md
- FOUND: resources/views/layouts/guest.blade.php
- FOUND: resources/views/pages/auth/login.blade.php
- FOUND: vendor/robsontenorio/mary (MaryUI installed)
- FOUND: node_modules/daisyui (DaisyUI installed)
- FOUND: commit 1544d1b (chore: install DaisyUI v5 and MaryUI)
- FOUND: commit 58af448 (feat: rewrite auth views with dark Spotify theme)
