---
phase: 02-user-profiles
plan: 02
subsystem: ui
tags: [livewire, intervention-image, file-uploads, avatar, bio, webp, storage]

# Dependency graph
requires:
  - phase: 02-01
    provides: avatar_path and bio columns on users table, User::avatarUrl() method, fillable fields

provides:
  - Bio textarea with 160-char limit on /settings/profile
  - Avatar upload with GD-powered 400x400 WebP resize via Intervention Image v3
  - Avatar removal (deletes file, clears avatar_path)
  - public/storage symlink for serving avatars via URL
  - ProfileValidationRules extended with bioRules() and avatarRules()

affects: [03-music-library, 05-playlists, any UI showing user avatars or bios]

# Tech tracking
tech-stack:
  added: [intervention/image ^3.11, WithFileUploads Livewire trait]
  patterns: [TDD red-green, validateOnly for avatar before image processing, Storage::disk('public') for avatar management]

key-files:
  created: []
  modified:
    - resources/views/pages/settings/⚡profile.blade.php
    - app/Concerns/ProfileValidationRules.php
    - tests/Feature/Settings/ProfileUpdateTest.php

key-decisions:
  - "validateOnly('avatar') called before image processing to ensure validation errors prevent Intervention Image decode attempts"
  - "Avatar stored as WebP at avatars/{userId}.webp for consistent naming and format regardless of upload format"
  - "File input uses standard HTML input[type=file] styled with Tailwind (not flux: component) per plan requirement"

patterns-established:
  - "Avatar management pattern: validate -> delete old -> resize/store new -> update avatar_path"
  - "WithFileUploads + #[Rule] attribute for per-property validation, supplemented by validateOnly() in action"

requirements-completed: [PROF-01, PROF-02, PROF-03]

# Metrics
duration: 9min
completed: 2026-03-04
---

# Phase 02 Plan 02: User Profile UI Summary

**Bio textarea (160 chars) and avatar upload with Intervention Image v3 WebP resize on /settings/profile, backed by 12 passing tests**

## Performance

- **Duration:** 9 min
- **Started:** 2026-03-04T12:25:39Z
- **Completed:** 2026-03-04T12:34:00Z
- **Tasks:** 2
- **Files modified:** 3

## Accomplishments

- Installed intervention/image v3 and created public/storage symlink for avatar serving
- Extended profile Livewire component with bio editing (max 160 chars, real-time counter) and avatar upload (2MB limit, image/* only, resized to 400x400 WebP)
- Avatar management: upload replaces old file, remove button deletes file and clears avatar_path
- Added 7 new tests covering bio validation, avatar upload, old file deletion, removeAvatar, and validation errors (12 total, all passing)

## Task Commits

Each task was committed atomically:

1. **Task 1: Install Intervention Image and run storage:link** - `cb32258` (chore)
2. **Task 2: Add failing tests (TDD RED)** - `416fe37` (test)
3. **Task 2: Extend profile Livewire component (TDD GREEN)** - `23801db` (feat)

_Note: TDD task 2 has two commits (test RED then feat GREEN)_

## Files Created/Modified

- `resources/views/pages/settings/⚡profile.blade.php` - Added WithFileUploads, bio property, avatar property with #[Rule], mount() bio load, updateProfileInformation() with avatar processing, removeAvatar() method, bio textarea + character counter + avatar section in Blade template
- `app/Concerns/ProfileValidationRules.php` - Added bioRules() and avatarRules() methods, updated profileRules() to include bio
- `tests/Feature/Settings/ProfileUpdateTest.php` - Added 7 new bio and avatar tests

## Decisions Made

- Used `validateOnly('avatar')` before Intervention Image processing to prevent `DecoderException` on invalid files — the `#[Rule]` attribute validates on property set but not on explicit `call()` in Livewire tests
- Avatar format standardized to WebP regardless of upload type for consistent storage naming
- Standard HTML `<input type="file">` used for avatar (not flux:) per plan requirement; MaryUI not used as no x-mary-file-upload suited the inline pattern

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Added validateOnly('avatar') before image processing**
- **Found during:** Task 2 (TDD GREEN — tests for over-2MB and non-image files failing)
- **Issue:** `#[Rule]` attribute validates on `wire:model` set but tests calling `updateProfileInformation()` directly bypassed validation, causing Intervention Image to attempt decoding invalid/oversized files
- **Fix:** Added `$this->validateOnly('avatar')` at the start of the avatar processing block in `updateProfileInformation()`
- **Files modified:** `resources/views/pages/settings/⚡profile.blade.php`
- **Verification:** `php artisan test --compact --filter=ProfileUpdateTest` — 12/12 pass
- **Committed in:** 23801db (Task 2 implementation commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - bug)
**Impact on plan:** Required fix for correct validation behavior. No scope creep.

## Issues Encountered

None beyond the auto-fixed deviation above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Profile bio and avatar fully functional with test coverage
- User::avatarUrl() established as URL source of truth (from 02-01) — template uses it correctly
- Ready for 02-03 (remaining profile features if any) or phase 03 (music library)

---
*Phase: 02-user-profiles*
*Completed: 2026-03-04*
