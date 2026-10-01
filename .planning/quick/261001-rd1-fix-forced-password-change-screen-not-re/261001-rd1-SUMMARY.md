---
phase: quick
plan: 261001-rd1
type: execute
status: complete
started: 2026-10-01T17:47:26Z
completed: 2026-10-01T17:49:16Z
subsystem: auth
tags: [password, livewire, redirect, force-password-change, quick-task]
requirements: [QUICK-261001-rd1]
commit: 2192ee6
estimate:
  tokens: 20000
  raw_tokens: 12000
  tasks: 3
  confidence: low
actuals:
  tokens: 792
  tasks: 3
  commits: 1
---

# Quick Task 261001-rd1: Forced password change screen never releases the user

Conditional dashboard redirect in `updatePassword()`, gated on the already-existing `force_password_change` flag, so the forced flow escapes the middleware trap and the voluntary settings flow keeps its inline "Saved." confirmation.

## What was done

The `ForcePasswordChange` middleware traps any authenticated user with `force_password_change = true` onto `/settings/password` and bounces every other authenticated route back there. `updatePassword()` already cleared the flag and wrote the new password — but it ended with `dispatch('password-updated')` and no navigation. The user was released by the database write but not by the screen: the password changed, "Saved." flashed, and the page just sat there.

The fix is a branch on the flag the method already owned.

```php
$wasForcedToChangePassword = Auth::user()->force_password_change;

if ($wasForcedToChangePassword) {
    Auth::user()->update(['force_password_change' => false]);
}

$this->reset('current_password', 'password', 'password_confirmation');

if ($wasForcedToChangePassword) {
    $this->redirectRoute('dashboard', navigate: true);

    return;
}

$this->dispatch('password-updated');
```

The flag is read into a named local immediately *before* the existing guard clears it, and that same local gates the redirect. The existing `update()` call, guard, and `reset()` are untouched.

### Ordering is the fix

The flag write must already have committed before the redirect is issued. The redirect is a client-side effect, so the browser's next `GET /dashboard` is a fresh request that re-runs `ForcePasswordChange` against the stored row. Reverse the two statements and the middleware finds the flag still true and bounces straight back to `user-password.edit` — a loop that presents as the original bug. This is threat `T-261001rd1-03`, and the new forced-flow test asserts the redirect target *and* the cleared flag in the same test so the ordering cannot regress silently.

### Why `redirectRoute`, not `redirectIntended`

`settings/⚡profile.blade.php:95` uses `redirectIntended`, but that would be a silent dependency on session residue: `ForcePasswordChange` issues `redirect()->route('user-password.edit')` with a flashed status and never writes `url.intended`, so there is no intended URL for it to read. `redirectRoute('dashboard', navigate: true)` names the destination plainly and deterministically. `navigate: true` matches all three existing redirects in the app and the `wire:navigate` links in the settings layout.

## Tests

TDD order was RED then GREEN, so the failure was observed rather than assumed.

**RED** — the forced case failed with exactly the predicted message before any implementation change:

```
FAILED  Tests\Feature\Auth\ForcePasswordChangeTest > forced password chan…
  Component did not perform a redirect.
Failed asserting that an array has the key 'redirect'.
  ➜ 23 vendor/livewire/livewire/src/Features/SupportRedirects/TestsRedirects.php
```

The voluntary case already passed at RED, which is the point — it describes behaviour that is already correct, so it fails the moment someone hoists the redirect out of the conditional. It is a live regression guard, not a vacuous test.

**GREEN** — 8 passing. Two new cases, purely additive; the 6 pre-existing cases were not modified or removed (verified: zero deletion lines in the test file's diff).

| Case | Asserts |
|---|---|
| `forced password change redirects to the dashboard` | `assertHasNoErrors()` + `assertRedirectToRoute('dashboard')` + `force_password_change` is `false` |
| `voluntary password change does not redirect` | `assertHasNoErrors()` + `assertNoRedirect()` |

`tests/Feature/Settings/PasswordUpdateTest.php` was run alongside as the regression net for the validation branch (it already covers a wrong `current_password` raising an error and writing nothing) — 10 passing across both files.

## Deviations from Plan

None. All 7 plan success criteria met as written.

## Known Stubs

None.

## Deferred Issues

None. No pre-existing unrelated failures were touched — the full suite was deliberately not run; `LibraryViewTest`, `AuthPagesTest` and `WaveformPeaksTest` carry-over failures recorded in STATE.md are out of scope.

## Verification

- `php artisan test --compact tests/Feature/Auth/ForcePasswordChangeTest.php` → **8 passed** (16 assertions); 6 at baseline
- `php artisan test --compact tests/Feature/Auth/ForcePasswordChangeTest.php tests/Feature/Settings/PasswordUpdateTest.php` → **10 passed** (21 assertions)
- RED reproduced the reported bug rather than assuming it (see above)
- `vendor/bin/pint` scoped to the two paths → fixed `class_attributes_separation`, `braces_position`; test re-run green, confirming the SFC still compiles after formatting
- Dirty-file count 37 → 39, i.e. exactly the two files this fix touched; Pint swept nothing else
- `git show --stat HEAD` lists **exactly 2 files**; no deletions; the 37 unrelated working-tree modifications remain unstaged and untouched
- `ForcePasswordChange`, `settings/layout.blade.php` and `routes/web.php` confirmed unchanged (anchored-path check)
- `app/Http/Requests/PlaylistRequest.php` (untracked) is pre-existing and unrelated — not created or modified by this task

### Not proven by the suite

`assertRedirectToRoute` inspects the Livewire redirect effect and never issues the follow-up request, so no automated test exercises the `ForcePasswordChange` re-evaluation on the browser's GET to `/dashboard`. That re-evaluation is precisely why the flag-write ordering matters. Needs a human in the browser.

## Human check (outstanding)

Sign in as a user with `force_password_change` set, submit the password form, and confirm the dashboard renders in **one** SPA navigation (no full-page flash) rather than the password page reappearing. This is the only step in the plan that the test suite cannot approximate.

## Threat Surface

No new surface. The destination is the hardcoded named route `dashboard`, never a user-supplied URL — no open-redirect surface. No new write path: the redirect reuses the write that was already behind `$this->validate()`. No new named route, config entry, cast, middleware change, or dependency. The settings nav tabs were deliberately left alone — the layout is shared by six settings pages and the user now leaves on the first submit, so hiding tabs would widen the blast radius for no reachable behavioural gain.

## Files

| File | Change |
|---|---|
| `resources/views/pages/settings/⚡password.blade.php` | +21/-2 — named local + conditional redirect (Pint also normalised pre-existing brace position and property spacing) |
| `tests/Feature/Auth/ForcePasswordChangeTest.php` | +32/-0 — two additive cases |

## Commits

| Hash | Message |
|---|---|
| `2192ee6` | `fix(password): redirect to dashboard after forced password change` |

## Self-Check: PASSED

- Both declared files exist and carry the change (committed, tree clean for them)
- Commit `2192ee6` present in `git log --all`
- Commit contains exactly the 2 declared files
- 8 tests passing; 6 pre-existing cases unmodified
