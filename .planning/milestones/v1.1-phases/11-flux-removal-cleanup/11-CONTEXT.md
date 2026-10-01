# Phase 11: Flux Removal & Cleanup - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Remove every remaining Flux reference from the codebase and uninstall the `livewire/flux` package. No new features — this is a cleanup-only phase that validates the v1.1 migration is complete.

</domain>

<decisions>
## Implementation Decisions

### auth-header component
- Replace `flux:heading` and `flux:subheading` with Mary UI `x-mary-header` component
- Keep the `auth-header.blade.php` component file — rewrite internals only, same API for callers

### Dead file handling
- Delete `layouts/app/header.blade.php` entirely — it's the old Flux layout replaced by `sidebar.blade.php` in Phase 7
- Delete the entire `resources/views/flux/` directory (4 custom icons + 1 navlist group override) — all Flux-specific, no longer needed

### Directive removal
- Remove `@fluxScripts` from `layouts/app/sidebar.blade.php` (line 76)
- Remove `@fluxAppearance` from `partials/head.blade.php` (line 14)
- Remove `@import '../../vendor/livewire/flux/dist/flux.css'` from `resources/css/app.css` (line 2)

### Test updates
- Invert CssFoundationTest assertion (line 62): change `toContain` to `not->toContain` for flux.css import — test now verifies Flux CSS is gone

### Project file housekeeping
- Remove `livewire/flux (FLUXUI_FREE) - v2` from CLAUDE.md and AGENTS.md package lists
- Remove `fluxui-development` skill references from CLAUDE.md and AGENTS.md skill activation sections
- Run `composer remove livewire/flux` to uninstall the package

### Claude's Discretion
- Order of operations for removal steps (directive removal before or after composer remove)
- Whether to add a comprehensive "Flux-free audit" test that greps all blade files
- Any additional CSS cleanup in app.css after removing the Flux import line

</decisions>

<specifics>
## Specific Ideas

No specific requirements — this is a mechanical cleanup phase with clear success criteria from the roadmap.

</specifics>

<code_context>
## Existing Code Insights

### Remaining Flux References (6 files)
- `resources/views/layouts/app/sidebar.blade.php:76` — `@fluxScripts` (active layout)
- `resources/views/partials/head.blade.php:14` — `@fluxAppearance` (active partial)
- `resources/views/components/auth-header.blade.php` — `flux:heading`, `flux:subheading` (active component)
- `resources/views/layouts/app/header.blade.php` — ~30 Flux components (DEAD — delete)
- `resources/views/flux/` — 5 published Flux view overrides (DEAD — delete directory)
- `resources/css/app.css:2` — Flux CSS import

### Package References
- `composer.json:18` — `"livewire/flux": "^2.9.0"`
- `CLAUDE.md` / `AGENTS.md` — Package list and skill references

### Test to Update
- `tests/Feature/CssFoundationTest.php:62` — Currently asserts Flux CSS import EXISTS; needs inversion

### Established Patterns
- Mary UI components use `x-mary-` prefix (configured in `config/mary.php`)
- Auth pages use `simple.blade.php` layout with daisyUI card styling
- Modals use Livewire boolean properties (Phase 8 pattern)

### Integration Points
- `sidebar.blade.php` is the main app layout — removing `@fluxScripts` here
- `head.blade.php` is included in all layouts — removing `@fluxAppearance` here

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 11-flux-removal-cleanup*
*Context gathered: 2026-03-17*
