# Phase 10: Settings Migration - Context

**Gathered:** 2026-03-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Migrate the settings sub-layout, all settings pages (profile, password, appearance, two-factor, recovery codes, users, delete account), and three remaining auth pages (confirm-password, two-factor-challenge, verify-email) from Flux UI to Mary UI. No Flux components remain in any settings or auth view after this phase.

</domain>

<decisions>
## Implementation Decisions

### Appearance/Theme Toggle
- Support two modes: Dark and Light (no System mode)
- Dark is default (`spotify-dark` daisyUI theme); Light theme needs a daisyUI light theme configured
- Theme preference persisted in database (user model) — requires a migration to add theme column
- Toggle UI: simple toggle switch (not segmented buttons or dropdown)
- Remove all `$flux.appearance` and `$flux.dark` references — replaced by database-backed theme state
- QR code in 2FA setup always renders on white background — no theme-conditional inversion

### Settings Navigation
- Replace `flux:navlist` with `x-mary-menu` vertical menu on the left side
- Text-only nav items — no icons alongside labels
- Same layout structure: nav on left (220px), content on right; stacks on mobile
- Active state indicated by Mary UI menu's built-in active styling
- Settings page heading partial (`settings-heading.blade.php`) uses `x-mary-header` component

### OTP/PIN Code Input
- Use `x-mary-pin` component if available in Mary UI
- Fallback: single `x-mary-input` with `maxlength="6"` and `inputmode="numeric"`
- Same approach used consistently in both 2FA setup modal (settings) and 2FA challenge (auth page)

### Delete Account Modal
- Migrate from Flux name-based trigger (`flux:modal.trigger name="..."`) to Livewire boolean pattern (Phase 8)
- Use `wire:model` boolean + `closeModal()` method with state reset
- Password input inside modal uses `x-mary-input`

### Auth Pages (confirm-password, two-factor-challenge, verify-email)
- 1:1 component swaps following Phase 9 mapping — no layout changes
- `flux:input` → `x-mary-input`, `flux:button` → `x-mary-button`, `flux:text` → plain HTML
- `flux:otp` → same OTP decision (x-mary-pin or single text input fallback)
- Auth layout (`simple.blade.php`) already migrated in Phase 8 — no changes needed

### Component Mapping (Settings-specific)
- `flux:navlist` → `x-mary-menu` with `x-mary-menu-item`
- `flux:heading` / `flux:subheading` → `x-mary-header` or plain HTML headings
- `flux:badge` → daisyUI badge classes
- `flux:text` → plain `<p>` or `<span>` with Tailwind classes
- `flux:separator` → daisyUI divider or `<hr>` with Tailwind
- `flux:callout` → daisyUI alert div
- `flux:radio.group` (appearance) → simple toggle switch with Alpine.js
- `flux:modal` → `x-mary-modal` with `wire:model` boolean
- `flux:otp` → `x-mary-pin` or single text input

### Claude's Discretion
- Light theme daisyUI configuration details
- Toggle switch implementation (Alpine.js + daisyUI toggle or custom)
- Database migration column name and type for theme preference
- Exact `x-mary-menu` configuration and active state binding
- Recovery codes section styling with Mary UI
- How to wire theme change to `data-theme` attribute on `<html>` element

</decisions>

<specifics>
## Specific Ideas

- Theme toggle should be a simple on/off switch — dark is the identity of the app, light is an option
- QR code always on white for reliability — no dark mode inversion complexity
- Settings nav keeps current vertical layout — familiar, no need to change what works

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `x-mary-modal` with `wire:model` boolean: Pattern from Phase 8 (AddToPlaylistModal) — reuse for delete-user modal
- `x-mary-input`, `x-mary-button`, `x-mary-textarea`, `x-mary-select`: Available via `x-mary-` prefix
- `x-mary-toast`: Already in app.blade.php — available for success/error messages
- `x-mary-header`: Used for page headings (replaces flux:heading + flux:subheading pattern)
- `spotify-dark` daisyUI theme: Defined in app.css

### Established Patterns
- Modal pattern: Livewire boolean + `wire:model` + `closeModal()` (Phase 8)
- Component mapping: flux → Mary UI equivalents established in Phase 9
- `data-test` attributes on interactive elements — preserve throughout
- `wire:navigate` on all internal navigation links
- Flash messages → toast notifications via `x-mary-toast`

### Integration Points
- `resources/views/pages/settings/layout.blade.php` — settings sub-layout with flux:navlist
- `resources/views/partials/settings-heading.blade.php` — shared heading partial
- `resources/views/pages/settings/*.blade.php` — all 6 settings page components
- `resources/views/pages/settings/two-factor/recovery-codes.blade.php` — recovery codes sub-component
- `resources/views/pages/auth/confirm-password.blade.php` — auth page
- `resources/views/pages/auth/two-factor-challenge.blade.php` — auth page with OTP
- `resources/views/pages/auth/verify-email.blade.php` — auth page

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 10-settings-migration*
*Context gathered: 2026-03-17*
