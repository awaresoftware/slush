# Phase 11: Flux Removal & Cleanup - Research

**Researched:** 2026-03-18
**Domain:** Package removal, blade template cleanup, test inversion
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

- Replace `flux:heading` and `flux:subheading` in `auth-header.blade.php` with Mary UI `x-mary-header` component
- Keep the `auth-header.blade.php` component file — rewrite internals only, same API for callers
- Delete `layouts/app/header.blade.php` entirely — dead file replaced by `sidebar.blade.php` in Phase 7
- Delete the entire `resources/views/flux/` directory (4 custom icons + 1 navlist group override)
- Remove `@fluxScripts` from `layouts/app/sidebar.blade.php` (line 76)
- Remove `@fluxAppearance` from `partials/head.blade.php` (line 14)
- Remove `@import '../../vendor/livewire/flux/dist/flux.css'` from `resources/css/app.css` (line 2)
- Invert CssFoundationTest assertion (line 62): change `toContain` to `not->toContain` for flux.css import
- Remove `livewire/flux (FLUXUI_FREE) - v2` from CLAUDE.md and AGENTS.md package lists
- Remove `fluxui-development` skill references from CLAUDE.md and AGENTS.md skill activation sections
- Run `composer remove livewire/flux` to uninstall the package

### Claude's Discretion

- Order of operations for removal steps (directive removal before or after composer remove)
- Whether to add a comprehensive "Flux-free audit" test that greps all blade files
- Any additional CSS cleanup in app.css after removing the Flux import line

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| CLEAN-01 | Remove `livewire/flux` package via composer | `composer remove livewire/flux` command; must happen after all template references removed |
| CLEAN-02 | Delete published Flux views (`resources/views/flux/`) | 5 files confirmed: 4 custom icons + 1 navlist group override; safe to delete |
| CLEAN-03 | Remove any remaining Flux-specific CSS/classes from views | Confirmed 3 active files with Flux references; auth-header needs rewrite |
| CLEAN-04 | Verify all 30 blade files are Flux-free | Full grep audit required; existing tests partially cover this |
</phase_requirements>

## Summary

Phase 11 is a mechanical cleanup phase — all substantive migrations are complete (Phases 7-10). The work is: remove 6 Flux reference sources, delete 6 dead files/directories, update 1 test assertion, update 2 project instruction files, and run `composer remove`.

The codebase grep reveals exactly 6 locations with remaining Flux references:
1. `resources/views/components/auth-header.blade.php` — `flux:heading`, `flux:subheading` (active, needs rewrite to `x-mary-header`)
2. `resources/views/layouts/app/sidebar.blade.php:76` — `@fluxScripts` (remove directive only)
3. `resources/views/partials/head.blade.php:14` — `@fluxAppearance` (remove directive only)
4. `resources/views/layouts/app/header.blade.php` — ~30 Flux components (DELETE — dead file)
5. `resources/views/flux/` — 5 published Flux views (DELETE — entire directory)
6. `resources/css/app.css:2` — `@import '../../vendor/livewire/flux/dist/flux.css'`

The critical order constraint: remove all template and CSS references BEFORE running `composer remove livewire/flux`. Running composer remove first will cause blade compilation errors if any `flux:` component tags remain.

**Primary recommendation:** Fix templates first (auth-header rewrite, directive removals, file deletions), then remove CSS import, then run `composer remove`, then update tests and project files, then run the full test suite.

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| Mary UI | ^2.8 | Replacement UI component library (already installed) | Already the active UI library post-migration |
| Pest | ^4.4 | Test framework for audit verification | Already the project test framework |
| Composer | N/A | Package removal | Standard PHP dependency management |

### No New Dependencies Required

This phase removes a dependency — it does not add any. All replacement components (Mary UI) are already installed and in use.

## Architecture Patterns

### Recommended Execution Order

```
1. Delete dead files (no Flux references survive in active code)
   ├── Delete resources/views/layouts/app/header.blade.php
   └── Delete resources/views/flux/ (entire directory)

2. Remove active Flux references from blade templates
   ├── Rewrite resources/views/components/auth-header.blade.php
   ├── Remove @fluxScripts from sidebar.blade.php
   └── Remove @fluxAppearance from partials/head.blade.php

3. Remove Flux CSS import
   └── Edit resources/css/app.css line 2

4. Uninstall the package
   └── composer remove livewire/flux

5. Update tests
   └── Invert CssFoundationTest line 62

6. Update project instruction files
   ├── CLAUDE.md — remove package line and skill section
   └── AGENTS.md — remove package line and skill section

7. Full audit + test suite
   └── grep audit + php artisan test --compact
```

### Pattern: auth-header Rewrite

The `auth-header.blade.php` component has a stable external API (`title` and `description` props) used by 3 callers. Only the internals change. The `x-mary-header` component accepts `title` and `subtitle` props.

**Current implementation:**
```blade
@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <flux:subheading>{{ $description }}</flux:subheading>
</div>
```

**Replacement (Mary UI):**
```blade
@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <x-mary-header :title="$title" :subtitle="$description" size="text-xl" class="mb-0" />
</div>
```

Note: `x-mary-header` uses `subtitle` not `description` — the prop name mapping matters. The `auth-header` component's external API keeps `description` as the prop name; the internal mapping to `subtitle` is transparent to callers.

### Pattern: Directive Removal

**sidebar.blade.php** — Remove line 76 (`@fluxScripts`) entirely. No replacement needed — Mary UI does not require a script directive; Livewire handles its own script injection.

**head.blade.php** — Remove line 14 (`@fluxAppearance`) entirely. No replacement needed — the appearance system is now handled by the `data-theme` attribute on `<html>` in sidebar.blade.php, which reads from `auth()->user()->theme`.

### Pattern: CSS Import Removal

**app.css** — Remove line 2 only:
```css
@import '../../vendor/livewire/flux/dist/flux.css';
```

The remaining CSS (daisyUI plugin, theme, @source directives, @theme font) is unaffected. After removal, run `npm run build` to verify the CSS compiles cleanly without the removed import.

### Pattern: Composer Package Removal

```bash
composer remove livewire/flux --no-interaction
```

This removes `livewire/flux` from `composer.json` and `composer.lock`, and deletes `vendor/livewire/flux/`. After this command, the Vite build (`npm run build`) must succeed since the CSS no longer imports from vendor.

### Anti-Patterns to Avoid

- **Removing composer package before template cleanup:** Running `composer remove livewire/flux` before removing `flux:` component tags will cause `BadMethodCallException` when blade tries to resolve the component.
- **Running npm build before CSS import removal:** The flux.css import will fail once the package is removed; remove the import first.
- **Partial test inversion:** CssFoundationTest line 62 asserts the flux.css import EXISTS — this is the only assertion that needs inverting. The other tests in that file already assert Flux artifacts do NOT exist and remain correct.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Heading + subtitle in auth pages | Custom HTML heading structure | `x-mary-header` with `title`/`subtitle` props | Mary UI is already installed, consistent with rest of app |
| Flux-free audit verification | Manual file-by-file review | Shell grep + Pest file content test | Automated, repeatable, enforces the constraint permanently |

**Key insight:** This phase is pure cleanup. No new abstractions are needed. The replacement for `flux:heading`/`flux:subheading` is `x-mary-header`, which is already used elsewhere in the codebase.

## Common Pitfalls

### Pitfall 1: Wrong Order — Composer Remove Before Template Cleanup
**What goes wrong:** `composer remove livewire/flux` removes the blade component resolvers. Any remaining `flux:` tag in a blade file causes `BadMethodCallException: Method Livewire\Flux\FluxServiceProvider::boot() does not exist` or component-not-found errors.
**Why it happens:** Flux registers its components via a service provider; once the package is removed, the components are unresolvable.
**How to avoid:** Audit and clean all blade files, then run composer remove.
**Warning signs:** Any grep match for `flux:` in active blade files before running composer remove.

### Pitfall 2: @fluxAppearance Removal Breaks Theme
**What goes wrong:** Removing `@fluxAppearance` removes the appearance initialization script. If the app relied on it for dark mode, the theme breaks.
**Why it happens:** Flux uses `@fluxAppearance` to inject a script that sets `dark` class on `<html>`.
**How to avoid:** Confirmed this is safe — the project uses `data-theme` attribute set from `auth()->user()->theme` column directly in `sidebar.blade.php`. Dark mode is DaisyUI-driven via `data-theme`, not Flux's class-based system.
**Warning signs:** Theme flicker or incorrect initial theme after removal.

### Pitfall 3: CssFoundationTest Line 62 Still Passes After Removal
**What goes wrong:** The test was written to assert the flux.css import EXISTS (as a "backward compatibility" marker). After CLEAN-01, this assertion is now wrong — the import should be absent.
**Why it happens:** The test was a placeholder written in Phase 7 with the comment "flux.css import for backward compatibility" — deliberately deferred to Phase 11 for inversion.
**How to avoid:** Invert line 62 assertion: `expect($this->css)->not->toContain("@import '../../vendor/livewire/flux/dist/flux.css'")`.
**Warning signs:** `php artisan test --compact --filter=CssFoundation` passes without the inversion — which would mean the CSS import wasn't actually removed.

### Pitfall 4: x-mary-header API Mismatch
**What goes wrong:** `flux:subheading` content maps to `subtitle` in `x-mary-header`, but `auth-header.blade.php` exposes `description` to its callers.
**Why it happens:** Different naming conventions between Flux and Mary UI.
**How to avoid:** The `auth-header` component keeps `description` as the external prop name and maps it to `:subtitle="$description"` internally.
**Warning signs:** The description text not appearing on confirm-password or two-factor-challenge pages.

### Pitfall 5: CLAUDE.md / AGENTS.md Flux Sections Leave Stale Instructions
**What goes wrong:** Leaving `livewire/flux (FLUXUI_FREE) - v2` in the package list and the `fluxui-development` skill activation rules means future AI agents may try to use Flux components.
**Why it happens:** Project instruction files are the source of truth for AI agents.
**How to avoid:** Remove the package entry from the "Foundational Context" package list and remove the `fluxui-development` skill entry from the "Skills Activation" section. Also remove the `=== fluxui-free/core rules ===` section.
**Warning signs:** AI agents generating `flux:*` component usage after Phase 11.

## Code Examples

### auth-header.blade.php Rewrite (Complete)
```blade
{{-- Source: Mary UI Header component (vendor/robsontenorio/mary/src/View/Components/Header.php) --}}
@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <x-mary-header :title="$title" :subtitle="$description" size="text-xl" class="mb-0" />
</div>
```

### CssFoundationTest Line 62 Inversion
```php
// Before (Phase 7 placeholder — backward compatibility):
it('contains flux.css import for backward compatibility', function () {
    expect($this->css)->toContain("@import '../../vendor/livewire/flux/dist/flux.css'");
});

// After (Phase 11 — Flux is removed):
it('does not contain flux.css import', function () {
    expect($this->css)->not->toContain("@import '../../vendor/livewire/flux/dist/flux.css'");
});
```

### Flux-Free Audit Grep Command
```bash
# Zero results = success. Run after all cleanup is done.
grep -r "flux:\|@flux\|@fluxScripts\|@fluxAppearance\|\$flux\." resources/views resources/css
```

### Optional: Pest Audit Test
```php
// Source: project convention from CssFoundationTest.php / SettingsNavigationTest.php
it('all blade files contain no flux references', function () {
    $files = glob(resource_path('views/**/*.blade.php'));
    foreach ($files as $file) {
        $content = file_get_contents($file);
        expect($content)
            ->not->toContain('flux:')
            ->not->toContain('@fluxScripts')
            ->not->toContain('@fluxAppearance')
            ->not->toContain('$flux.');
    }
});
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Flux UI for all components | Mary UI (daisyUI) | Phases 7-10 | All components already migrated |
| `@fluxAppearance` for dark mode | `data-theme` attribute from user DB column | Phase 10 | Theme is DB-driven, no JS injection needed |
| Flux named modals (`flux:modal name="..."`) | Livewire boolean properties | Phase 8 | All modals already migrated |
| `flux:icon.*` for icons | `x-mary-icon` with Heroicons | Phase 7 | All icons already migrated |

**Deprecated/outdated after this phase:**
- `livewire/flux`: Uninstalled — no longer a project dependency
- `@fluxScripts`: Removed from layouts — was injecting Flux's JS bundle
- `@fluxAppearance`: Removed from head partial — was injecting appearance initialization script
- `resources/views/flux/`: Published overrides — dead custom icon/navlist views

## Open Questions

1. **x-mary-header styling in auth context**
   - What we know: `x-mary-header` renders a `div` with `mb-10` margin and flex layout by default
   - What's unclear: Whether the default Mary Header styling will look visually appropriate in the centered auth card context vs. the existing centered `flex-col text-center` wrapper
   - Recommendation: The outer `<div class="flex w-full flex-col text-center">` wrapper in `auth-header.blade.php` provides centering; pass `class="mb-0"` to override the default `mb-10` margin. Verify visually after migration.

2. **glob pattern depth for audit test**
   - What we know: `glob('views/**/*.blade.php')` may not recurse into subdirectories on all PHP versions
   - What's unclear: Whether a simple glob or recursive directory iterator is needed
   - Recommendation: Use `RecursiveDirectoryIterator` or `Symfony\Component\Finder\Finder` if adding a Pest audit test, or rely on the shell grep command for the audit instead.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4.4 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=CssFoundation` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| CLEAN-01 | `livewire/flux` absent from composer.json | unit | `php artisan test --compact --filter=CssFoundation` | ✅ (test needs inversion) |
| CLEAN-02 | `resources/views/flux/` directory deleted | manual | Shell: `ls resources/views/flux/` returns error | N/A — file deletion |
| CLEAN-03 | No `flux:` tags in active blade files | integration | `php artisan test --compact --filter=AppShell` | ✅ existing |
| CLEAN-04 | All routes return 200, no BadMethodCallException | smoke | `php artisan test --compact` | ✅ existing suite |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=CssFoundation`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/CssFoundationTest.php:62` — Invert `toContain` → `not->toContain` for flux.css assertion

*(All other test infrastructure exists — no new test files required unless planner chooses to add the optional Flux-free audit test)*

## Sources

### Primary (HIGH confidence)
- Direct codebase inspection — all 6 Flux reference locations confirmed by grep
- `vendor/robsontenorio/mary/src/View/Components/Header.php` — `x-mary-header` props: `title`, `subtitle`, `size`, `weight`
- `composer.json` — confirmed `livewire/flux: ^2.9.0` in require
- `tests/Feature/CssFoundationTest.php` — confirmed line 62 needs inversion

### Secondary (MEDIUM confidence)
- `tests/Feature/AppShellTest.php` — assertDontSee `flux:sidebar` and `flux:header` confirms test coverage pattern
- `tests/Feature/Settings/SettingsNavigationTest.php` — confirms existing `not->toContain('<flux:')` audit pattern

### Tertiary (LOW confidence)
- None

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — no new dependencies; Mary UI already installed
- Architecture: HIGH — all Flux locations confirmed by direct file inspection
- Pitfalls: HIGH — order-of-operations constraint verified by understanding Flux service provider mechanism

**Research date:** 2026-03-18
**Valid until:** N/A — this is a cleanup phase; findings are based on static file state which is deterministic
