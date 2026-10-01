# Phase 10: Settings Migration - Research

**Researched:** 2026-03-17
**Domain:** Mary UI / daisyUI component migration — settings, appearance, 2FA, auth pages
**Confidence:** HIGH

## Summary

Phase 10 migrates 8 settings views and 3 auth views from Flux UI to Mary UI. Prior phases (7-9) established the complete component mapping and modal pattern, so this phase is largely mechanical: replace every `flux:*` component with the appropriate Mary UI or plain HTML equivalent. The two novel decisions for this phase are the appearance toggle (database-backed theme preference instead of `$flux.appearance`) and the OTP input (`x-mary-pin`).

Both novel concerns are now resolved. `x-mary-pin` exists in the installed Mary UI vendor code (`Pin.php`) with a `size` parameter, `numeric` flag, and `wire:model` entanglement — it is a confirmed drop-in for `flux:otp`. The `x-mary-theme-toggle` component also exists but uses `$persist` (localStorage) rather than database storage; it CANNOT be used as-is for the database-backed requirement. The appearance toggle must be a database-backed Livewire boolean toggling `data-theme` on `<html>` via Alpine.js.

**Primary recommendation:** Follow the established Phase 8/9 component mapping strictly. Use `x-mary-pin :size="6" :numeric="true" wire:model="code"` for all OTP inputs. Build the appearance toggle as a Livewire boolean property saved to the `users.theme` column (new migration required), with an Alpine.js `x-init` that reads the server value and sets `document.documentElement.setAttribute('data-theme', ...)`.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Support two modes: Dark and Light (no System mode)
- Dark is default (`spotify-dark` daisyUI theme); Light theme needs a daisyUI light theme configured
- Theme preference persisted in database (user model) — requires a migration to add theme column
- Toggle UI: simple toggle switch (not segmented buttons or dropdown)
- Remove all `$flux.appearance` and `$flux.dark` references — replaced by database-backed theme state
- QR code in 2FA setup always renders on white background — no theme-conditional inversion
- Replace `flux:navlist` with `x-mary-menu` vertical menu on the left side
- Text-only nav items — no icons alongside labels
- Same layout structure: nav on left (220px), content on right; stacks on mobile
- Active state indicated by Mary UI menu's built-in active styling
- Settings page heading partial (`settings-heading.blade.php`) uses `x-mary-header` component
- Use `x-mary-pin` component for OTP inputs (confirmed available — see research below)
- Fallback if pin fails: single `x-mary-input` with `maxlength="6"` and `inputmode="numeric"`
- Delete account: Livewire boolean pattern (Phase 8) with `wire:model` boolean + `closeModal()` method
- Auth pages: 1:1 component swaps, no layout changes; auth layout (`simple.blade.php`) already migrated

### Claude's Discretion
- Light theme daisyUI configuration details
- Toggle switch implementation (Alpine.js + daisyUI toggle or custom)
- Database migration column name and type for theme preference
- Exact `x-mary-menu` configuration and active state binding
- Recovery codes section styling with Mary UI
- How to wire theme change to `data-theme` attribute on `<html>` element

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SETTINGS-01 | Migrate settings layout and navigation (`flux:navlist`) to Mary UI | `x-mary-menu` with `activate-by-route` prop auto-highlights active item; use `x-mary-menu-item` with `link` + `wire:navigate` |
| SETTINGS-02 | Migrate settings profile page to Mary UI | Replace `flux:input`, `flux:button`, `flux:textarea`, `flux:text`, `flux:link`, `flux:label` with Mary/HTML equivalents; keep `x-action-message` (custom component, not Flux) |
| SETTINGS-03 | Migrate settings password page to Mary UI | Replace `flux:input`, `flux:button` with `x-mary-input`, `x-mary-button`; keep `x-action-message` |
| SETTINGS-04 | Migrate settings appearance page — rebuild theme toggle without `$flux.appearance` | Database-backed `theme` column on users table; Livewire `isDarkMode` boolean; Alpine.js sets `data-theme` on `<html>`; `x-mary-toggle` for the switch UI |
| SETTINGS-05 | Migrate settings two-factor and recovery codes pages to Mary UI | `flux:otp` → `x-mary-pin :size="6" :numeric="true"`; `flux:badge` → daisyUI `badge` classes; `flux:callout` → daisyUI `alert`; `flux:modal` → `x-mary-modal` with `wire:model` |
| SETTINGS-06 | Migrate settings users (admin) page to Mary UI | Replace `flux:input`, `flux:button`, `flux:text`, `flux:heading` with Mary/HTML equivalents |
| SETTINGS-07 | Migrate settings delete account page to Mary UI | Replace `flux:modal.trigger`/`flux:modal` Flux name-based pattern with `wire:model` boolean + `x-mary-modal`; `flux:input` → `x-mary-input` |
| SETTINGS-08 | Migrate auth pages (confirm-password, two-factor-challenge, verify-email) to Mary UI | `flux:input` → `x-mary-input`; `flux:button` → `x-mary-button`; `flux:otp` → `x-mary-pin`; `flux:text` → plain HTML |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| `robsontenorio/mary` | installed | Mary UI components (`x-mary-*`) | Project standard since Phase 7 |
| daisyUI | v5 (via app.css @plugin) | CSS token system for all styling | Theme tokens, badge/alert/toggle classes |
| Livewire 4 | v4 | Reactive PHP components | All settings pages are Livewire volt files |
| Alpine.js | bundled with Livewire | Client-side interactivity | Theme toggle, show/hide states |
| `x-mary-pin` | part of Mary UI | OTP/PIN code input (6-cell) | **CONFIRMED available** — `Pin.php` exists in vendor |
| `x-mary-toggle` | part of Mary UI | Checkbox toggle switch | Renders daisyUI `toggle` class input |
| `x-mary-theme-toggle` | part of Mary UI | Auto dark/light toggle | Uses `$persist` (localStorage) — NOT used for DB-backed theme |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `x-mary-menu` | part of Mary UI | Vertical nav menu | Settings sidebar navigation |
| `x-mary-menu-item` | part of Mary UI | Nav item with active state | Each settings nav link |
| `x-mary-modal` | part of Mary UI | `wire:model` modal | Delete account, 2FA setup |
| `x-mary-header` | part of Mary UI | Page title + subtitle | Settings heading partial replacement |
| `x-action-message` | custom component (not Flux) | Inline "Saved." message | Profile, password forms — keep as-is |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `x-mary-pin` | `x-mary-input maxlength="6"` | Pin is a better UX (6 separate cells), confirmed available |
| `x-mary-toggle` | plain daisyUI `<input type="checkbox" class="toggle">` | Toggle wraps it with label; either works |
| `x-mary-theme-toggle` | Custom Livewire toggle | ThemeToggle uses localStorage, NOT DB — must build custom for DB-backed theme |

**Installation:** No new packages — all required components are already in installed Mary UI.

## Architecture Patterns

### Recommended Project Structure
```
resources/views/pages/settings/
├── layout.blade.php          # settings sub-layout with x-mary-menu nav
├── ⚡appearance.blade.php   # rebuilt with Livewire isDarkMode + x-mary-toggle
├── ⚡profile.blade.php      # form with x-mary-input/button/textarea
├── ⚡password.blade.php     # form with x-mary-input/button
├── ⚡two-factor.blade.php   # x-mary-modal + x-mary-pin + daisyUI badge
├── ⚡users.blade.php        # invite form + user list
├── ⚡delete-user-form.blade.php  # wire:model boolean + x-mary-modal
└── two-factor/
    └── ⚡recovery-codes.blade.php  # Alpine show/hide + daisyUI styling
resources/views/pages/auth/
├── confirm-password.blade.php    # x-mary-input + x-mary-button
├── two-factor-challenge.blade.php  # x-mary-pin + x-mary-input + x-mary-button
└── verify-email.blade.php        # plain HTML text + x-mary-button
database/migrations/
└── YYYY_MM_DD_add_theme_to_users_table.php  # new migration for theme column
```

### Pattern 1: Settings Nav with x-mary-menu
**What:** Replace `flux:navlist` + `flux:navlist.item` with `x-mary-menu` + `x-mary-menu-item`. The `activate-by-route` prop on `x-mary-menu` causes `x-mary-menu-item` to auto-highlight based on current URL.
**When to use:** Settings layout sidebar nav

```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Menu.php + MenuItem.php --}}
<x-mary-menu activate-by-route>
    <x-mary-menu-item title="{{ __('Profile') }}" link="{{ route('profile.edit') }}" />
    <x-mary-menu-item title="{{ __('Password') }}" link="{{ route('user-password.edit') }}" />
    @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
        <x-mary-menu-item title="{{ __('Two-Factor Auth') }}" link="{{ route('two-factor.show') }}" />
    @endif
    <x-mary-menu-item title="{{ __('Appearance') }}" link="{{ route('appearance.edit') }}" />
    @if (auth()->user()->is_admin)
        <x-mary-menu-item title="{{ __('Users') }}" link="{{ route('settings.users') }}" />
    @endif
</x-mary-menu>
```

**Key detail:** `x-mary-menu-item` adds `wire:navigate` automatically unless `no-wire-navigate` is set. Text-only (no `icon` prop) per user decision.

### Pattern 2: Database-Backed Theme Toggle
**What:** Livewire boolean property (`$isDarkMode`) synced to `users.theme` column. Alpine.js reads the PHP-rendered value on mount and sets `data-theme` on `<html>`.
**When to use:** Appearance settings page only

```blade
{{-- PHP class --}}
new class extends Component {
    public bool $isDarkMode = true;

    public function mount(): void
    {
        $this->isDarkMode = Auth::user()->theme === 'spotify-dark';
    }

    public function updateTheme(): void
    {
        $theme = $this->isDarkMode ? 'spotify-dark' : 'light';
        Auth::user()->update(['theme' => $theme]);
    }
};

{{-- Blade template --}}
<div
    x-data="{ isDark: @entangle('isDarkMode') }"
    x-init="document.documentElement.setAttribute('data-theme', isDark ? 'spotify-dark' : 'light')"
    x-effect="document.documentElement.setAttribute('data-theme', isDark ? 'spotify-dark' : 'light')"
>
    <x-mary-toggle
        label="{{ __('Dark Mode') }}"
        wire:model.live="isDarkMode"
        wire:change="updateTheme"
    />
</div>
```

**Migration needed:** `users.theme` column — `string`, nullable, default `'spotify-dark'`.

### Pattern 3: OTP Input with x-mary-pin
**What:** 6-cell PIN input that entangles with a Livewire `wire:model` property, dispatches `completed` event when all 6 cells are filled.
**When to use:** Two-factor setup verification code input AND two-factor-challenge auth page

```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Pin.php --}}
<x-mary-pin
    :size="6"
    :numeric="true"
    wire:model="code"
    class="mx-auto"
    error-field="code"
/>
```

**Key details from Pin.php:**
- `size` (int, required): number of cells — use `6` for TOTP
- `numeric` (bool): adds `inputmode="numeric"` and `x-mask="9"` per cell
- Dispatches Alpine `completed` event when all cells filled — can use `@completed="$wire.confirmTwoFactor()"` to auto-submit
- Copy-paste support built in
- `error-field="code"` shows validation errors below

### Pattern 4: Delete Account Modal (Wire:model Boolean)
**What:** Phase 8 pattern — Livewire boolean property, `x-mary-modal` with `wire:model`, `closeModal()` method resets state.
**When to use:** Delete account form (and any modal that needs cleanup on close)

```blade
{{-- PHP class --}}
public bool $showDeleteModal = false;

public function deleteUser(Logout $logout): void
{
    $this->validate(['password' => $this->currentPasswordRules()]);
    tap(Auth::user(), $logout(...))->delete();
    $this->redirect('/', navigate: true);
}

public function closeModal(): void
{
    $this->reset('password', 'showDeleteModal');
    $this->resetErrorBag();
}

{{-- Blade template --}}
<x-mary-button
    variant="danger"
    wire:click="$set('showDeleteModal', true)"
    data-test="delete-user-button"
>
    {{ __('Delete account') }}
</x-mary-button>

<x-mary-modal wire:model="showDeleteModal" @close="closeModal">
    <form wire:submit="deleteUser" class="space-y-6">
        <x-mary-input wire:model="password" :label="__('Password')" type="password" />
        <div class="flex justify-end space-x-2">
            <x-mary-button wire:click="closeModal" type="button">{{ __('Cancel') }}</x-mary-button>
            <x-mary-button variant="danger" type="submit" data-test="confirm-delete-user-button">
                {{ __('Delete account') }}
            </x-mary-button>
        </div>
    </form>
</x-mary-modal>
```

### Pattern 5: Settings Heading Partial Replacement
**What:** Replace `flux:heading`/`flux:subheading`/`flux:separator` in `settings-heading.blade.php` with `x-mary-header`.

```blade
{{-- settings-heading.blade.php --}}
<x-mary-header title="{{ __('Settings') }}" subtitle="{{ __('Manage your profile and account settings') }}" separator />
```

**Note:** The `layout.blade.php` component also uses `flux:heading` and `flux:subheading` for per-page headings — migrate to `x-mary-header` there too (or plain `<h2>` + `<p>` if lighter weight is preferred, following project convention from profile/library pages).

### Pattern 6: Flux Component Replacements (Settings-specific)
| Flux Component | Mary UI / HTML Replacement |
|----------------|---------------------------|
| `flux:navlist` | `x-mary-menu activate-by-route` |
| `flux:navlist.item` | `x-mary-menu-item title="..." link="..."` |
| `flux:heading` | `<h2 class="text-xl font-bold">` or `x-mary-header title="..."` |
| `flux:subheading` | `<p class="text-base-content/60 text-sm">` |
| `flux:separator` | `<hr class="border-t border-base-content/10 my-4">` or daisyUI `divider` |
| `flux:badge color="green"` | `<span class="badge badge-success">` |
| `flux:badge color="red"` | `<span class="badge badge-error">` |
| `flux:text` | `<p class="text-sm">` or `<span class="text-sm">` |
| `flux:text variant="subtle"` | `<p class="text-sm text-base-content/60">` |
| `flux:input` | `x-mary-input` |
| `flux:textarea` | `x-mary-textarea` |
| `flux:button variant="primary"` | `x-mary-button class="btn-primary"` |
| `flux:button variant="danger"` | `x-mary-button class="btn-error"` |
| `flux:button variant="ghost"` | `x-mary-button class="btn-ghost"` |
| `flux:button variant="filled"` | `x-mary-button class="btn-neutral"` |
| `flux:button variant="outline"` | `x-mary-button class="btn-outline"` |
| `flux:callout variant="danger"` | `<div class="alert alert-error">` |
| `flux:otp` | `x-mary-pin :size="6" :numeric="true"` |
| `flux:modal` (named) | `x-mary-modal wire:model="showModal"` |
| `flux:modal.trigger` | `x-mary-button wire:click="$set('showModal', true)"` |
| `flux:modal.close` | `x-mary-button wire:click="closeModal"` |
| `flux:radio.group` (appearance) | `x-mary-toggle wire:model.live="isDarkMode"` |
| `flux:link` | `<a href="..." class="link">` or `<a wire:click="...">` |
| `flux:icon.*` | `x-mary-icon name="o-..."` (Heroicons outline format) |
| `flux:icon.loading` | `<span class="loading loading-spinner">` |

### Anti-Patterns to Avoid
- **Using `x-mary-theme-toggle` for the appearance page:** This component uses `$persist` (localStorage) and does NOT talk to the database. It cannot be used when theme is persisted server-side.
- **Keeping `$flux.appearance`/`$flux.dark` references:** These are Flux-specific Alpine stores — they do not exist after Flux is removed. Remove entirely and replace with the `data-theme` attribute approach.
- **Using Flux named modal pattern:** `flux:modal name="..."` + `$dispatch('open-modal', '...')` — this is a Flux-only mechanism. Always use `wire:model` boolean pattern.
- **Adding icons to settings nav items:** User decision is text-only nav items.
- **`wire:navigate` on the two-factor-challenge form:** This is a POST form to a non-Livewire route — `wire:navigate` does not apply.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| OTP 6-cell input | Custom Alpine.js auto-advance logic | `x-mary-pin :size="6" :numeric="true"` | Pin.php handles focus advance, backspace, copy-paste, `completed` event |
| Toggle switch | `<input type="checkbox" class="toggle">` inline | `x-mary-toggle` | Includes label rendering, error display, fieldset structure |
| Settings nav active state | `request()->routeIs()` checks + manual active classes | `x-mary-menu activate-by-route` | MenuItem.php routeMatches() handles full URL + route matching |
| Modal with close behavior | Custom `@click.outside` logic | `x-mary-modal` with `@close="closeModal"` | Mary Modal fires `close` event on backdrop/ESC; closeModal() resets state |

## Common Pitfalls

### Pitfall 1: `x-mary-pin` requires `size` as integer, not string
**What goes wrong:** `<x-mary-pin size="6">` passes string `"6"`, causing PHP type error since `Pin::__construct(public int $size)` is strictly typed.
**Why it happens:** Blade passes all attributes as strings by default.
**How to avoid:** Use `:size="6"` (colon prefix to pass PHP integer) — `<x-mary-pin :size="6" :numeric="true" wire:model="code">`.
**Warning signs:** PHP TypeError: `Argument #1 ($size) must be of type int, string given`.

### Pitfall 2: `x-mary-menu-item` adds `wire:navigate` automatically
**What goes wrong:** Expecting to manually add `wire:navigate` to all nav items.
**Why it happens:** MenuItem.php renders `wire:navigate` automatically on all links unless `no-wire-navigate` attribute is set.
**How to avoid:** Do NOT add `wire:navigate` attribute to `x-mary-menu-item` — it is already handled. Only suppress with `:no-wire-navigate="true"` if needed.
**Warning signs:** Double navigate behavior or "wire:navigate already applied" warnings.

### Pitfall 3: Theme toggle data-theme not applied on page load
**What goes wrong:** Page loads with default `spotify-dark` even if user prefers light — the database value is not applied until Livewire boots.
**Why it happens:** `simple.blade.php` auth layout hardcodes `data-theme="spotify-dark"`. The app layout has no `data-theme` attribute.
**How to avoid:** On the appearance Livewire component mount, emit the theme to the `<html>` element via `x-init`. For full-page load: output `data-theme="{{ auth()->user()->theme ?? 'spotify-dark' }}"` on `<html>` in the app layout (`sidebar.blade.php`).
**Warning signs:** Flash of wrong theme on load, or toggle works but only after first Livewire interaction.

### Pitfall 4: `flux:heading class="sr-only"` removing the hidden heading
**What goes wrong:** Each settings page currently renders a `<flux:heading class="sr-only">` for accessibility. After migration, this must be replaced with a plain `<h2 class="sr-only">` tag.
**Why it happens:** Easy to overlook small Flux tags.
**How to avoid:** Check each file for `flux:heading` / `flux:subheading` at every level — not just the obvious form inputs.
**Warning signs:** Remaining `flux:` tags caught by Phase 11 CLEAN-04 verification.

### Pitfall 5: `$flux.appearance` Alpine store removed
**What goes wrong:** `two-factor.blade.php` has `:style="($flux.appearance === 'dark' || ...)  ? 'filter: invert(1)...' : ''"` on the QR code. After Flux is gone, `$flux` is undefined.
**Why it happens:** The old 2FA component used Flux's Alpine store for dark mode detection on the QR code.
**How to avoid:** Per user decision, QR code always renders on white background — simply remove the conditional style entirely. Just use `<div class="bg-white p-3 rounded">` without `:style` binding.
**Warning signs:** Alpine.js console error: `$flux is not defined`.

### Pitfall 6: `wire:confirm` on flux:button — check if available in x-mary-button
**What goes wrong:** The users page uses `wire:confirm="..."` on `flux:button`. This is a Livewire directive, not Flux-specific — it works on `x-mary-button` too. No issue, but worth noting.
**Why it happens:** Possible confusion about whether `wire:confirm` is Flux-only.
**How to avoid:** Keep `wire:confirm` as-is on `x-mary-button` — it's a Livewire 4 feature, not component-specific.

### Pitfall 7: Light theme not configured in app.css
**What goes wrong:** Toggling to "light" renders the daisyUI default light theme (which may clash with the app's design).
**Why it happens:** `app.css` currently only defines `spotify-dark` in the `@plugin "daisyui"` themes list.
**How to avoid:** Either (a) add a `spotify-light` custom theme definition, or (b) use the built-in daisyUI `light` theme. Add whichever theme name is chosen to the `themes` list in `@plugin "daisyui"`. This is in Claude's discretion per CONTEXT.md.
**Warning signs:** Light mode shows garish default colors instead of a coherent light theme.

## Code Examples

Verified from vendor source code inspection:

### x-mary-pin for TOTP Code Entry
```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Pin.php --}}
<x-mary-pin
    :size="6"
    :numeric="true"
    wire:model="code"
    error-field="code"
    class="mx-auto"
/>
```

### x-mary-menu Settings Navigation
```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Menu.php + MenuItem.php --}}
<x-mary-menu activate-by-route>
    <x-mary-menu-item title="{{ __('Profile') }}" link="{{ route('profile.edit') }}" />
    <x-mary-menu-item title="{{ __('Password') }}" link="{{ route('user-password.edit') }}" />
    @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
        <x-mary-menu-item title="{{ __('Two-Factor Auth') }}" link="{{ route('two-factor.show') }}" />
    @endif
    <x-mary-menu-item title="{{ __('Appearance') }}" link="{{ route('appearance.edit') }}" />
    @if (auth()->user()->is_admin)
        <x-mary-menu-item title="{{ __('Users') }}" link="{{ route('settings.users') }}" />
    @endif
</x-mary-menu>
```

### x-mary-toggle for Appearance
```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Toggle.php --}}
<x-mary-toggle
    label="{{ __('Dark Mode') }}"
    wire:model.live="isDarkMode"
/>
```

### daisyUI Badge Replacements
```blade
{{-- For flux:badge color="green" --}}
<span class="badge badge-success">{{ __('Enabled') }}</span>

{{-- For flux:badge color="red" --}}
<span class="badge badge-error">{{ __('Disabled') }}</span>
```

### daisyUI Alert (replaces flux:callout)
```blade
{{-- For flux:callout variant="danger" icon="x-circle" heading="..." --}}
<div class="alert alert-error">
    <x-mary-icon name="o-x-circle" class="size-5" />
    <span>{{ $message }}</span>
</div>
```

### x-mary-modal with wire:model boolean
```blade
{{-- Source: confirmed pattern from Phase 8, add-to-playlist-modal.blade.php --}}
<x-mary-modal wire:model="showModal" title="Modal Title" box-class="max-w-md">
    {{-- content --}}
</x-mary-modal>
```

### Settings layout.blade.php skeleton (after migration)
```blade
<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px]">
        <x-mary-menu activate-by-route aria-label="{{ __('Settings') }}">
            <x-mary-menu-item title="{{ __('Profile') }}" link="{{ route('profile.edit') }}" />
            <x-mary-menu-item title="{{ __('Password') }}" link="{{ route('user-password.edit') }}" />
            @if (Laravel\Fortify\Features::canManageTwoFactorAuthentication())
                <x-mary-menu-item title="{{ __('Two-Factor Auth') }}" link="{{ route('two-factor.show') }}" />
            @endif
            <x-mary-menu-item title="{{ __('Appearance') }}" link="{{ route('appearance.edit') }}" />
            @if (auth()->user()->is_admin)
                <x-mary-menu-item title="{{ __('Users') }}" link="{{ route('settings.users') }}" />
            @endif
        </x-mary-menu>
    </div>

    <hr class="md:hidden border-t border-base-content/10 w-full my-4">

    <div class="flex-1 self-stretch max-md:pt-6">
        <x-mary-header :title="$heading ?? ''" :subtitle="$subheading ?? ''" />

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| `$flux.appearance` Alpine store | `data-theme` attribute on `<html>`, set by Livewire/Alpine | Phase 10 (this phase) | Remove all `$flux.*` references |
| `flux:navlist` + `flux:navlist.item` | `x-mary-menu activate-by-route` + `x-mary-menu-item` | Phase 10 | Menu auto-highlights active route |
| `flux:otp` (6 cells) | `x-mary-pin :size="6" :numeric="true"` | Phase 10 | Same UX, now Mary UI |
| `flux:modal name="..."` + `$dispatch('open-modal', ...)` | `x-mary-modal wire:model="showModal"` | Phase 8 (established) | Boolean property pattern |
| `flux:badge color="..."` | daisyUI `badge badge-success` / `badge badge-error` classes | Phase 10 | Inline HTML, no component |
| `flux:radio.group` for appearance | `x-mary-toggle` | Phase 10 | Simple on/off (no System mode) |

**Deprecated/outdated:**
- `$flux.appearance` store: Does not exist without Flux — remove entirely
- `$flux.dark` store: Remove entirely
- `flux:modal.trigger` / `flux:modal.close` pattern: Flux-only — replace with boolean + `closeModal()`
- QR code conditional dark inversion (`:style="($flux.appearance === 'dark')..."`) — remove, QR always on white

## Open Questions

1. **Light theme definition**
   - What we know: `app.css` only has `spotify-dark`; a second daisyUI theme entry is needed for light
   - What's unclear: Whether to use stock daisyUI `light` or define a custom `spotify-light`
   - Recommendation: Use stock daisyUI `light` theme first (add `themes: spotify-dark, light --default: spotify-dark`); if it looks wrong, define a `spotify-light` custom theme. This is in Claude's discretion.

2. **Where to apply initial `data-theme` for logged-in users**
   - What we know: `sidebar.blade.php` has `<html lang="..." class="dark">` — no `data-theme` attribute
   - What's unclear: Whether to modify `sidebar.blade.php` to output `data-theme="{{ auth()->user()->theme ?? 'spotify-dark' }}"`, or rely on JS hydration
   - Recommendation: Add `data-theme="{{ auth()->user()->theme ?? 'spotify-dark' }}"` to the `<html>` tag in `sidebar.blade.php` to avoid flash of wrong theme on load. The appearance component's Alpine `x-effect` then handles live switching.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 + PHPUnit 12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=Settings` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| SETTINGS-01 | Settings nav renders without Flux components | smoke | `php artisan test --compact --filter="settings layout"` | ❌ Wave 0 |
| SETTINGS-02 | Profile update still works (name, email, bio, avatar) | unit (Livewire) | `php artisan test --compact --filter=ProfileUpdateTest` | ✅ `tests/Feature/Settings/ProfileUpdateTest.php` |
| SETTINGS-03 | Password update still works | unit (Livewire) | `php artisan test --compact --filter=PasswordUpdateTest` | ✅ `tests/Feature/Settings/PasswordUpdateTest.php` |
| SETTINGS-04 | Theme toggle updates user.theme in DB | unit (Livewire) | `php artisan test --compact --filter=AppearanceTest` | ❌ Wave 0 |
| SETTINGS-05 | 2FA pages render; enable/disable flow works | unit (Livewire) | `php artisan test --compact --filter=TwoFactorAuthenticationTest` | ✅ `tests/Feature/Settings/TwoFactorAuthenticationTest.php` |
| SETTINGS-06 | Users page renders; invite/delete still work | unit (Livewire) | `php artisan test --compact --filter=UsersTest` | ❌ Wave 0 |
| SETTINGS-07 | Delete account modal opens; account deletion works | unit (Livewire) | `php artisan test --compact --filter=ProfileUpdateTest` | ✅ (covered in ProfileUpdateTest: `user can delete their account`) |
| SETTINGS-08 | Auth pages render without Flux components | smoke | `php artisan test --compact --filter=AuthTest` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=Settings`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Settings/AppearanceTest.php` — covers SETTINGS-04 (theme toggle saves to DB, HTML updates)
- [ ] `tests/Feature/Settings/UsersTest.php` — covers SETTINGS-06 (invite form, user list render)
- No new test framework needed — Pest 4 + Livewire test helpers already installed

*(Existing tests for SETTINGS-02, 03, 05, 07 cover most of the Livewire logic; the Wave 0 gaps are for new behavior that has no coverage.)*

## Sources

### Primary (HIGH confidence)
- `vendor/robsontenorio/mary/src/View/Components/Pin.php` — x-mary-pin API, parameters, behavior
- `vendor/robsontenorio/mary/src/View/Components/Menu.php` — x-mary-menu props, activate-by-route
- `vendor/robsontenorio/mary/src/View/Components/MenuItem.php` — wire:navigate auto-add, routeMatches()
- `vendor/robsontenorio/mary/src/View/Components/ThemeToggle.php` — confirmed uses $persist not DB
- `vendor/robsontenorio/mary/src/View/Components/Toggle.php` — x-mary-toggle, daisyUI toggle
- `vendor/robsontenorio/mary/src/View/Components/Header.php` — x-mary-header, title/subtitle props
- `resources/views/livewire/add-to-playlist-modal.blade.php` — canonical x-mary-modal + wire:model pattern (Phase 8)
- `resources/views/layouts/app/sidebar.blade.php` — x-mary-menu usage in production (Phase 7)
- All source settings `.blade.php` files — current Flux components to replace

### Secondary (MEDIUM confidence)
- `resources/css/app.css` — current daisyUI theme configuration; `spotify-dark` is the only defined theme
- `app/Models/User.php` — current model structure; `theme` column does not yet exist
- Existing test files — confirmed test framework and assertion patterns

### Tertiary (LOW confidence)
- None — all critical claims verified from vendor source code inspection

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all Mary UI components verified by reading vendor PHP source
- Architecture: HIGH — x-mary-pin, x-mary-menu, x-mary-modal all confirmed; ThemeToggle NOT suitable
- Pitfalls: HIGH — `$flux.appearance`, `size` type hint, `wire:navigate` auto-add all confirmed from source

**Research date:** 2026-03-17
**Valid until:** 2026-04-17 (stable — vendor code changes only on package update)
