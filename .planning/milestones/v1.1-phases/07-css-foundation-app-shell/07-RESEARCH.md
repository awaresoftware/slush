# Phase 7: CSS Foundation & App Shell - Research

**Researched:** 2026-03-13
**Domain:** Mary UI 2.8, daisyUI v4, Tailwind CSS v4, Livewire 4, Alpine.js — layout shell migration
**Confidence:** HIGH

## Summary

Phase 7 adds Mary UI as the layout system alongside existing Flux UI (which stays until Phase 11). The primary work is: (1) clean Flux-specific CSS selectors and `@source` directives from `app.css` and add a Mary UI `@source`, (2) migrate the sidebar shell from `flux:sidebar` + `flux:main` to `x-mary-main` with named slots plus a fixed bottom nav bar on mobile, (3) rewrite the desktop user menu with `x-mary-dropdown`, and (4) swap all `flux:icon.*` in the audio player for `x-mary-icon` using Heroicon prefix conventions.

Mary UI 2.8.0 is already installed (`robsontenorio/mary: ^2.8`). Its routes are registered (`mary.toogle-sidebar` confirmed via `php artisan route:list`). The `config/mary.php` has NOT been published — the service provider uses the unpublished default (`prefix: ''`) but hardcodes `mary-*` aliases unconditionally. Since the auth pages already use `x-mary-*` throughout, the project convention is `x-mary-` prefix. No config publish is needed. All new components in Phase 7 use `x-mary-` prefix.

The `blade-ui-kit/blade-heroicons` package (v2.6) is installed as a Mary UI dependency. It provides icon SVGs via `o-` (outline), `s-` (solid), `m-` (mini), `c-` (compact) prefixes. All audio player icons (`o-play`, `o-pause`, `o-backward`, `o-forward`, `o-speaker-wave`, `o-speaker-x-mark`, `o-musical-note`) are confirmed present in the vendor SVG directory.

**Primary recommendation:** Use `x-mary-main` with `x-slot:sidebar drawer="main-drawer"` and `x-slot:content` for the layout shell. Use `x-mary-menu activate-by-route` with `x-mary-menu-item` for nav. Use `x-mary-dropdown` with `x-slot:trigger` for the user menu. Swap all `flux:icon.name` to `x-mary-icon name="o-name"`. Replace audio player's custom range CSS with daisyUI `range range-xs range-primary` classes.

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Migration Strategy**
- Keep Flux CSS (`flux.css` import) and `@fluxScripts` until Phase 11 — all Flux components keep working throughout migration
- Mary UI components win on CSS conflicts — if daisyUI and Flux custom properties clash, resolve in favor of Mary UI; Flux components may look slightly off during transition
- Remove Flux-specific CSS rules from app.css in Phase 7 (`[data-flux-field]`, `[data-flux-label]`, `input:focus[data-flux-control]` selectors)
- Remove Flux `@source` directives from app.css in Phase 7 (`flux-pro/stubs`, `flux/stubs`)
- Add Mary UI `@source` directive for Tailwind v4 scanning

**Sidebar Navigation**
- Keep same 3 nav items: Dashboard, Library, Playlists
- Drop the "Platform" group heading — cleaner look without section labels
- Same icons: home, musical-note, queue-list
- Move user menu to top of sidebar (below logo), not bottom

**Mobile Navigation**
- Replace hamburger + slide-in drawer with a fixed bottom nav bar
- Bottom nav bar shows icons for Dashboard, Library, Playlists
- Bottom nav positioned below the audio player (nav at very bottom, player above it)
- Both visible simultaneously when a track is playing

**Audio Player**
- 1:1 icon swap: `flux:icon.name` → `x-icon name="name"` (same Heroicon names)
- Use daisyUI range classes for seek and volume sliders (`range range-xs range-primary` or similar)
- Keep white circle play/pause button (bg-white text-zinc-900), not primary green
- Simplify mobile layout: hide volume control on mobile (mute only via icon tap), show only track info + play controls + seek bar
- Keep overall desktop layout: [track info] [controls + seek] [volume]

**Theme & CSS Cleanup**
- Remove Flux-specific overrides from `@theme` block (accent-content, accent-foreground, zinc palette) — keep only font-sans
- Remove the `.dark` layer overrides that target Flux accent variables
- Remove the `@layer base` border-color reset (Flux-specific)
- Keep daisyUI spotify-dark theme colors as-is (minor tweaks at Claude's discretion)

### Claude's Discretion
- Minor color tweaks to spotify-dark theme if needed for better Mary UI component rendering
- How to handle the `@custom-variant dark` directive (keep if needed, remove if daisyUI handles dark mode)
- Mary UI sidebar component configuration details
- Bottom nav bar icon styling and active state indicator

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| INFRA-01 | Remove Flux CSS import and `@theme` Flux variables from `app.css` | CSS Cleanup section — exact lines identified from reading app.css; keep `flux.css` import (Phase 11); remove zinc palette, accent vars, `@layer theme .dark`, `@layer base` border reset, Flux selectors |
| INFRA-02 | Remove `@fluxScripts` and `@fluxAppearance` directives from layouts | CONTEXT locks `@fluxScripts` to Phase 11; `@fluxAppearance` (in partials/head.blade.php) should be evaluated — removing it may break Flux dark mode; recommendation: keep both until Phase 11, deliver INFRA-02 fully then |
| INFRA-03 | Add Mary UI `@source` directive for Tailwind v4 scanning | Exact path: `@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php"` |
| LAYOUT-01 | Migrate app layout shell from `flux:sidebar` + `flux:main` to Mary `x-main` with `x-slot:sidebar` | Architecture Patterns: Main.php source confirmed, drawer slot API documented |
| LAYOUT-02 | Migrate sidebar navigation items (`flux:sidebar.*`) to Mary UI equivalents | x-mary-menu + x-mary-menu-item with activate-by-route; wire:navigate auto-injected |
| LAYOUT-03 | Migrate header/navbar (`flux:header`, `flux:navbar`) to Mary UI | Decision: no Mary Nav on mobile — replace with fixed bottom nav bar (custom HTML) |
| LAYOUT-04 | Migrate user menu dropdown to Mary `x-dropdown` | x-mary-dropdown with x-slot:trigger; details/summary pattern |
| PLAYER-01 | Replace all `flux:icon.*` in audio player with Mary `x-icon` | All 7 icon names confirmed with `o-` prefix in blade-heroicons vendor directory |
| PLAYER-02 | Restyle seek/volume sliders with daisyUI classes (keep Alpine.js logic) | Use plain `<input type="range">` with `range range-xs range-primary`; do NOT use x-mary-range (wire:model based) |
</phase_requirements>

## Standard Stack

### Core
| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| robsontenorio/mary | 2.8.0 | Layout shell, menu, dropdown, icon components | Already installed; project-selected Flux replacement |
| blade-ui-kit/blade-heroicons | 2.6.0 | SVG icon rendering via `x-svg` (used by x-mary-icon) | Mary UI dependency; already installed |
| daisyUI | v4 (via @plugin) | Component CSS classes (menu, drawer, range) used by Mary components | Already configured with spotify-dark theme |
| Tailwind CSS | v4 | Utility classes, @source scanning | Already in use |
| Alpine.js | Livewire-bundled | Dropdown open/close, bottom nav active state | Already bundled — no separate install |
| Livewire | v4 | `@persist('audio-player')` cross-page persistence | Audio player is Livewire; @persist must be preserved |

### Supporting
| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| livewire/flux | ^2.9.0 | Existing Flux components (stays active) | NOT migrated in Phase 7; stays until Phase 11 |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Fixed bottom nav (custom HTML) | `x-mary-nav` mobile only | Mary Nav requires hamburger pattern; custom nav allows precise z-stacking control with audio player |
| `x-mary-range` for sliders | Raw `<input type="range">` | x-mary-range requires wire:model; Alpine-controlled sliders need raw input with daisyUI classes |

**Installation:** No new packages needed. Mary UI 2.8 is already installed.

## Architecture Patterns

### Files Changed This Phase
```
resources/
├── css/
│   └── app.css                          # Remove Flux selectors; add Mary @source; clean @theme
├── views/
│   └── layouts/
│       ├── app.blade.php                # Replace flux:sidebar shell with x-mary-main
│       └── app/
│           └── sidebar.blade.php        # Replace flux:main wrapper with x-slot:content pattern
│   └── components/
│       ├── app-logo.blade.php           # Remove flux:sidebar.brand / flux:brand usage
│       └── desktop-user-menu.blade.php  # Rewrite with x-mary-dropdown
│   └── livewire/
│       └── audio-player.blade.php       # Icon swap + daisyUI range + mobile simplification
│   └── partials/
│       └── head.blade.php               # Leave @fluxAppearance intact (Phase 11)
```

### Pattern 1: x-mary-main Drawer Layout

**What:** `x-mary-main` renders a daisyUI `drawer` component. A hidden checkbox (`drawer-toggle`) controls mobile sidebar visibility. On `lg:` screens the sidebar is always visible (`drawer-open`). The `drawer` attribute on the sidebar slot sets the checkbox ID that connects to the mobile trigger label.

**Key props (from Main.php source):**
- `full-width` — removes `max-w-screen-2xl` constraint
- `with-nav` — adjusts sidebar height for sticky nav bar above it
- `collapsible` — shows a collapse toggle at the bottom of the sidebar
- `x-slot:sidebar drawer="id"` — required; sets the mobile toggle checkbox ID
- `x-slot:content` — wraps page content; defaults add `p-5 lg:px-10 lg:py-5` padding

**Example (app.blade.php):**
```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Main.php --}}
<body class="min-h-screen font-sans antialiased bg-base-200">

    <x-mary-main full-width>
        <x-slot:sidebar drawer="main-drawer" class="bg-base-100">

            {{-- Logo --}}
            <x-app-logo href="{{ route('dashboard') }}" wire:navigate />

            {{-- User menu (top of sidebar per locked decision) --}}
            <x-desktop-user-menu />

            {{-- Navigation --}}
            <x-mary-menu activate-by-route>
                <x-mary-menu-item title="{{ __('Dashboard') }}" icon="o-home" link="{{ route('dashboard') }}" />
                <x-mary-menu-item title="{{ __('Library') }}" icon="o-musical-note" link="{{ route('library.index') }}" />
                <x-mary-menu-item title="{{ __('Playlists') }}" icon="o-queue-list" link="{{ route('playlists.index') }}" />
            </x-mary-menu>

        </x-slot:sidebar>

        <x-slot:content class="pb-28 lg:pb-28">
            {{ $slot }}
        </x-slot:content>
    </x-mary-main>

    {{-- Fixed bottom nav bar — mobile only, sits below audio player --}}
    <nav class="fixed bottom-0 left-0 right-0 z-40 flex justify-around bg-base-100 border-t border-base-content/10 py-2 lg:hidden">
        <a href="{{ route('dashboard') }}" wire:navigate
           class="flex flex-col items-center gap-1 px-4 {{ request()->routeIs('dashboard') ? 'text-primary' : 'text-base-content/60' }}">
            <x-mary-icon name="o-home" class="size-6" />
            <span class="text-xs">{{ __('Dashboard') }}</span>
        </a>
        {{-- ... library, playlists --}}
    </nav>

    @auth
        @persist('audio-player')
            <livewire:audio-player />
        @endpersist
    @endauth

    @fluxScripts
</body>
```

**Mobile drawer trigger:** The bottom nav bar replaces the hamburger (per locked decision). However, `x-mary-main` still needs a way to close the drawer if it opens (e.g., accidental swipe). The drawer overlay (generated by the component) handles this automatically — clicking outside the sidebar closes it.

**Note on sidebar.blade.php vs app.blade.php split:** The current project has two files:
- `app.blade.php` = outer HTML shell + sidebar shell
- `app/sidebar.blade.php` = the layout blade component (`<x-layouts::app.sidebar>`) that wraps `flux:main`

After migration, `app.blade.php` becomes the full Mary UI layout. `app/sidebar.blade.php` becomes a simple passthrough to `app.blade.php` with `$slot`.

### Pattern 2: x-mary-menu with Route Activation

**What:** `activate-by-route` is passed as a context variable to child `x-mary-menu-item` components via `@aware`. `MenuItem::routeMatches()` uses `Str::startsWith` for prefix matching — so `library.index` correctly activates for `library.*` routes.

**wire:navigate behavior:** Automatically added to all links via `x-mary-menu-item` unless `no-wire-navigate` is explicitly passed. Do NOT add `wire:navigate` manually.

```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Menu.php + MenuItem.php --}}
<x-mary-menu activate-by-route>
    <x-mary-menu-item title="{{ __('Dashboard') }}" icon="o-home" link="{{ route('dashboard') }}" />
    <x-mary-menu-item title="{{ __('Library') }}" icon="o-musical-note" link="{{ route('library.index') }}" />
    <x-mary-menu-item title="{{ __('Playlists') }}" icon="o-queue-list" link="{{ route('playlists.index') }}" />
</x-mary-menu>
```

**Active route logic in MenuItem:** `routeMatches()` checks `Str::startsWith(request()->url(), url($link))` so library nav item activates on `/library/albums/1` correctly. Uses the `link` prop (resolved URL), not the `route` prop. Use `link="{{ route('name') }}"` not `route="name"`.

### Pattern 3: x-mary-dropdown for User Menu

**What:** Uses `<details>`/`<summary>` HTML element with Alpine.js `x-data="{open:false}"` for state. `@click.outside="open = false"` handles dismissal. A custom trigger goes in `x-slot:trigger`.

**Positioning:** By default uses `x-anchor` (Alpine anchor plugin) to position relative to trigger. `right` prop = `bottom-end`, `top` prop = `top-start`. For sidebar user menu at top, default positioning (`bottom-start`) is appropriate.

```blade
{{-- Source: vendor/robsontenorio/mary/src/View/Components/Dropdown.php --}}
<x-mary-dropdown>
    <x-slot:trigger>
        <button class="flex items-center gap-2 w-full px-4 py-2 rounded-lg hover:bg-base-300 transition-colors"
                data-test="sidebar-menu-button">
            <div class="avatar placeholder shrink-0">
                <div class="bg-neutral text-neutral-content rounded-full w-8">
                    <span class="text-xs">{{ auth()->user()->initials() }}</span>
                </div>
            </div>
            <div class="text-left text-sm min-w-0 flex-1">
                <p class="font-medium truncate">{{ auth()->user()->name }}</p>
                <p class="text-xs text-base-content/60 truncate">{{ auth()->user()->email }}</p>
            </div>
        </button>
    </x-slot:trigger>

    <li><a href="{{ route('profile.show', auth()->user()) }}" wire:navigate class="flex items-center gap-2 px-4 py-2">
        <x-mary-icon name="o-user" class="size-4" /> {{ __('Profile') }}
    </a></li>
    <li><a href="{{ route('profile.edit') }}" wire:navigate class="flex items-center gap-2 px-4 py-2">
        <x-mary-icon name="o-cog-6-tooth" class="size-4" /> {{ __('Settings') }}
    </a></li>
    <li class="divider my-0"></li>
    <li>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex items-center gap-2 w-full px-4 py-2" data-test="logout-button">
                <x-mary-icon name="o-arrow-right-start-on-rectangle" class="size-4" />
                {{ __('Log Out') }}
            </button>
        </form>
    </li>
</x-mary-dropdown>
```

### Pattern 4: Icon Name Mapping — Flux to Mary UI

**What:** Mary UI's `x-mary-icon` resolves names via `Icon::icon()` which prefixes `heroicon-` if no dot-notation. Blade heroicons uses prefix convention: `o-` = outline, `s-` = solid, `m-` = mini, `c-` = compact. All audio player icons confirmed present in `vendor/blade-ui-kit/blade-heroicons/resources/svg/`.

**Complete icon mapping for audio player (PLAYER-01):**
| Current Flux | Replace With | Confirmed |
|-------------|-------------|-----------|
| `flux:icon.musical-note class="size-5 text-zinc-500"` | `x-mary-icon name="o-musical-note" class="size-5 text-zinc-500"` | YES |
| `flux:icon.backward class="size-5"` | `x-mary-icon name="o-backward" class="size-5"` | YES |
| `flux:icon.pause class="size-5"` | `x-mary-icon name="o-pause" class="size-5"` | YES |
| `flux:icon.play class="size-5"` | `x-mary-icon name="o-play" class="size-5"` | YES |
| `flux:icon.forward class="size-5"` | `x-mary-icon name="o-forward" class="size-5"` | YES |
| `flux:icon.speaker-x-mark class="size-5"` | `x-mary-icon name="o-speaker-x-mark" class="size-5"` | YES |
| `flux:icon.speaker-wave class="size-5"` | `x-mary-icon name="o-speaker-wave" class="size-5"` | YES |

**Sidebar icon mapping (LAYOUT-02):**
| Flux `icon=` | Mary `icon=` |
|-------------|-------------|
| `"home"` | `"o-home"` |
| `"musical-note"` | `"o-musical-note"` |
| `"queue-list"` | `"o-queue-list"` |

**Usage in Alpine `x-if` templates:**
```blade
<template x-if="isPlaying">
    <x-mary-icon name="o-pause" class="size-5" />
</template>
<template x-if="!isPlaying">
    <x-mary-icon name="o-play" class="size-5" />
</template>
```

### Pattern 5: daisyUI Range Sliders (PLAYER-02)

**What:** Plain `<input type="range">` with daisyUI utility classes. Do NOT use `x-mary-range` (it wraps inputs with `wire:model` for form usage — wrong for Alpine-controlled player).

**daisyUI range classes:**
- `range` — base class
- `range-xs` — smallest size
- `range-primary` — uses `--color-primary` (the Spotify green)

```blade
{{-- Seek bar — replaces h-1.5 appearance-none rounded-full bg-zinc-600 accent-white --}}
<input
    type="range"
    min="0"
    :max="duration || 100"
    :value="currentTime"
    x-on:input="seek($event.target.value)"
    :disabled="!hasTrack || duration === 0"
    class="range range-xs range-primary w-full disabled:opacity-40 disabled:cursor-not-allowed"
    aria-label="Seek slider"
    :aria-valuenow="currentTime"
    :aria-valuemax="duration"
    :aria-valuetext="formatTime(currentTime) + ' of ' + formatTime(duration)"
>

{{-- Volume slider — replaces h-1.5 w-20 appearance-none rounded-full bg-zinc-600 accent-white --}}
<input
    type="range"
    min="0"
    max="100"
    :value="isMuted ? 0 : volume"
    x-on:input="setVolume($event.target.value)"
    class="range range-xs range-primary w-20"
    aria-label="Volume"
    :aria-valuenow="isMuted ? 0 : volume"
    :aria-valuetext="(isMuted ? 0 : volume) + '%'"
>
```

### Pattern 6: Bottom Nav Bar + Audio Player Z-Stacking

**Decision:** Bottom nav is at `z-40`, audio player stays at `z-50`. On mobile: player moves from `fixed bottom-0` to `fixed bottom-14 lg:bottom-0` so it floats above the bottom nav. Content area gets `pb-[calc(theme(spacing.14)+theme(spacing.28))]` on mobile or simpler `pb-48 lg:pb-28`.

```blade
{{-- Mobile bottom nav — `z-40` ensures player (z-50) renders above it --}}
<nav class="fixed bottom-0 left-0 right-0 z-40 flex justify-around items-center
            bg-base-100 border-t border-base-content/10 h-14 lg:hidden">
    <a href="{{ route('dashboard') }}" wire:navigate
       class="flex flex-col items-center gap-0.5 px-4 py-1
              @if(request()->routeIs('dashboard')) text-primary @else text-neutral-content @endif">
        <x-mary-icon name="o-home" class="size-6" />
        <span class="text-[10px]">{{ __('Dashboard') }}</span>
    </a>
    <a href="{{ route('library.index') }}" wire:navigate
       class="flex flex-col items-center gap-0.5 px-4 py-1
              @if(request()->routeIs('library.*')) text-primary @else text-neutral-content @endif">
        <x-mary-icon name="o-musical-note" class="size-6" />
        <span class="text-[10px]">{{ __('Library') }}</span>
    </a>
    <a href="{{ route('playlists.index') }}" wire:navigate
       class="flex flex-col items-center gap-0.5 px-4 py-1
              @if(request()->routeIs('playlists.*')) text-primary @else text-neutral-content @endif">
        <x-mary-icon name="o-queue-list" class="size-6" />
        <span class="text-[10px]">{{ __('Playlists') }}</span>
    </a>
</nav>
```

**Audio player position change:**
```blade
{{-- Change from: class="fixed bottom-0 left-0 right-0 z-50 ..." --}}
{{-- Change to: --}}
class="fixed bottom-14 lg:bottom-0 left-0 right-0 z-50 ..."
```

### Pattern 7: CSS Cleanup in app.css (INFRA-01, INFRA-03)

**Exact lines to REMOVE from current app.css:**

```css
/* Lines 35-36 — REMOVE (Flux @source directives) */
@source '../../vendor/livewire/flux-pro/stubs/**/*.blade.php';
@source '../../vendor/livewire/flux/stubs/**/*.blade.php';

/* Lines 43-57 in @theme — REMOVE these (keep only font-sans) */
--color-zinc-50 through --color-zinc-950  /* 12 zinc palette lines */
--color-accent: var(--color-neutral-800);
--color-accent-content: var(--color-neutral-800);
--color-accent-foreground: var(--color-white);

/* Lines 60-66 — REMOVE entire @layer theme .dark block */
@layer theme {
    .dark {
        --color-accent: var(--color-white);
        --color-accent-content: var(--color-white);
        --color-accent-foreground: var(--color-neutral-800);
    }
}

/* Lines 68-77 — REMOVE @layer base border-color reset */
@layer base {
    *, ::after, ::before, ::backdrop, ::file-selector-button {
        border-color: var(--color-gray-200, currentColor);
    }
}

/* Lines 79-91 — REMOVE Flux-specific selectors */
[data-flux-field]:not(ui-radio, ui-checkbox) { @apply grid gap-2; }
[data-flux-label] { @apply !mb-0 !leading-tight; }
input:focus[data-flux-control], textarea:focus[data-flux-control], select:focus[data-flux-control] { ... }
```

**Line to ADD (after existing `@source` lines):**
```css
@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php";
```

**Lines to KEEP:**
```css
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';  /* Phase 11 removes this */
@plugin "daisyui" { themes: spotify-dark --default; }
@plugin "daisyui/theme" { name: "spotify-dark"; /* all color vars */ }
@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@custom-variant dark (&:where(.dark, .dark *));  /* Keep — needed for remaining Flux components */
@theme { --font-sans: '...'; }  /* Keep only font-sans line */
```

### Anti-Patterns to Avoid

- **Using `x-main` instead of `x-mary-main`:** Project convention is `x-mary-` prefix (matches auth pages). Without the prefix, components won't resolve.
- **Adding `wire:navigate` manually to `x-mary-menu-item`:** It's auto-injected. Manual addition doubles the directive and may cause navigation issues (STATE.md issue #384).
- **Using `route="named.route"` prop on `x-mary-menu-item` for wildcard matching:** The `route` prop does exact `routeIs()` matching. For nested routes (library.*), use `link="{{ route('library.index') }}"` with `activate-by-route` — the URL prefix matching via `Str::startsWith` handles wildcards.
- **Using `x-mary-range` for audio player sliders:** It uses `wire:model` internally. The player sliders are Alpine-controlled — use plain `<input type="range" class="range ...">`.
- **Removing `@fluxScripts` or `@fluxAppearance` in Phase 7:** Both stay until Phase 11. Removal breaks all remaining Flux components.
- **Nesting `@persist('audio-player')` inside `x-slot:content`:** Must remain outside the content slot as a direct `<body>` child for cross-page persistence to work.
- **Forgetting `drawer` attribute on `x-slot:sidebar`:** Without it, `x-mary-main` generates no checkbox ID, and mobile sidebar toggle doesn't work.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Sidebar drawer with overlay + open state | Custom CSS + Alpine x-data | `x-mary-main` + `x-slot:sidebar drawer="id"` | Drawer handles all states, animations, lg:always-open, overlay dismiss |
| SVG icon rendering | Inline SVG or img tags | `x-mary-icon name="o-name"` | blade-heroicons resolves SVG from vendor; consistent sizing via class |
| Nav active state detection | Manual PHP class conditions in each link | `activate-by-route` on `x-mary-menu` | Built-in URL prefix matching via `routeMatches()` |
| Dropdown open/close + dismiss | Alpine x-data toggle + click-outside | `x-mary-dropdown` | Handles click-outside, open state, positioning via x-anchor |
| Range slider theming | Custom CSS `appearance-none` overrides | daisyUI `range range-xs range-primary` | Cross-browser consistent, matches daisyUI theme system |
| Sidebar collapse persistence | Session storage + fetch calls | Built into `x-mary-main` | Uses `mary.toogle-sidebar` route (already registered) |

**Key insight:** Mary UI's components are thin daisyUI wrappers with Laravel-idiomatic APIs. The complexity (drawer, collapse, anchor positioning) is already solved. Use the components as-is rather than rebuilding with raw daisyUI markup.

## Common Pitfalls

### Pitfall 1: Icon Names Missing Style Prefix
**What goes wrong:** `x-mary-icon name="play"` renders nothing — no visible icon in DOM.
**Why it happens:** `blade-heroicons` resolves `heroicon-play` which doesn't exist; must be `heroicon-o-play`.
**How to avoid:** Always use `o-` prefix for outline icons. Verify a new icon name with `ls vendor/blade-ui-kit/blade-heroicons/resources/svg/o-*.svg | grep name`.
**Warning signs:** Empty space in DOM; inspector shows `<svg>` element with no paths.

### Pitfall 2: wire:navigate Auto-Injection Conflict
**What goes wrong:** Navigation triggers twice or causes a Livewire error after layout migration.
**Why it happens:** `x-mary-menu-item` adds `wire:navigate` automatically. Manually adding it creates a duplicate.
**Why it matters:** STATE.md flags a known issue with `wire:navigate` + Mary UI drawer initialization (GitHub issue #384).
**How to avoid:** Never add `wire:navigate` manually to `x-mary-menu-item`. Smoke test all nav links after the layout migration.
**Warning signs:** Double network requests per click; console errors about duplicate navigation.

### Pitfall 3: Drawer ID Mismatch Breaks Mobile Sidebar
**What goes wrong:** Mobile sidebar never opens.
**Why it happens:** The checkbox ID (from `drawer="main-drawer"` on sidebar slot) must match the trigger label's `for` attribute. Mary UI generates `<input id="main-drawer" type="checkbox" class="drawer-toggle">` inside the component.
**How to avoid:** Use consistent ID: `x-slot:sidebar drawer="main-drawer"` and any trigger label must be `<label for="main-drawer">`.
**Warning signs:** Hamburger tap has no effect; no animation on mobile.

### Pitfall 4: Audio Player Z-Index vs Bottom Nav Overlap
**What goes wrong:** Player renders directly on top of bottom nav, hiding nav icons.
**Why it happens:** Both use `fixed bottom-0`. Player has `z-50`; nav needs `z-40` but same bottom position creates visual stack.
**How to avoid:** Move player to `fixed bottom-14 lg:bottom-0` (14 = 56px = nav bar height). Content padding becomes `pb-[calc(88px+56px)] lg:pb-28` on mobile.
**Warning signs:** Nav items invisible; clicking bottom area doesn't trigger nav links.

### Pitfall 5: Flux CSS Custom Property Conflicts
**What goes wrong:** Mary UI / daisyUI components render with wrong colors because Flux's CSS custom properties override daisyUI values.
**Why it happens:** `flux.css` import sets its own `--color-accent`, `--color-zinc-*`, etc. that conflict with daisyUI.
**How to avoid:** INFRA-01 cleanup removes the Flux-specific `@theme` variables from `app.css` that compound the problem. The `spotify-dark` daisyUI theme values on `[data-theme]` take precedence over Flux's `@theme` variables for daisyUI component styling. Mary UI wins on conflicts (locked decision).
**Warning signs:** daisyUI range sliders appear unstyled; menu items have wrong background colors.

### Pitfall 6: Content Area Hidden Behind Player on Mobile
**What goes wrong:** Page content truncated at bottom; last items hidden behind audio player + nav bar stack.
**Why it happens:** Current `pb-28` accounts for player only. With bottom nav added, mobile needs more padding.
**How to avoid:** `x-slot:content` needs `pb-28 lg:pb-28` on desktop (player only) but approximately `pb-36` to `pb-40` on mobile (player ~80px + nav ~56px). Use responsive class: `pb-36 lg:pb-28`.
**Warning signs:** Last card or track in list disappears behind the player/nav stack.

### Pitfall 7: @fluxAppearance Removal Breaking Flux Dark Mode
**What goes wrong:** Existing Flux components lose dark mode styling if `@fluxAppearance` is removed.
**Why it happens:** `@fluxAppearance` injects a `<script>` that adds/removes the `.dark` class on `<html>`. Remaining Flux components depend on this for their `dark:` variants.
**How to avoid:** Do NOT remove `@fluxAppearance` in Phase 7. INFRA-02 scope notes that full removal happens in Phase 11.
**Warning signs:** Flux form components appear light-themed on a dark page.

## Code Examples

### Final app.css After Phase 7 Cleanup
```css
/* Source: current app.css with Flux rules removed, Mary @source added */
@import 'tailwindcss';
@import '../../vendor/livewire/flux/dist/flux.css';

@plugin "daisyui" {
    themes: spotify-dark --default;
}

@plugin "daisyui/theme" {
    name: "spotify-dark";
    default: true;
    prefersdark: true;

    --color-base-100: oklch(12% 0 0);
    --color-base-200: oklch(15% 0 0);
    --color-base-300: oklch(20% 0 0);
    --color-base-content: oklch(100% 0 0);
    --color-primary: oklch(69.5% 0.196 152.5);
    --color-primary-content: oklch(0% 0 0);
    --color-secondary: oklch(40% 0 0);
    --color-secondary-content: oklch(100% 0 0);
    --color-accent: oklch(69.5% 0.196 152.5);
    --color-accent-content: oklch(0% 0 0);
    --color-neutral: oklch(20% 0 0);
    --color-neutral-content: oklch(70% 0 0);
    --color-error: oklch(55% 0.22 29);
    --color-error-content: oklch(100% 0 0);
    --radius-field: 0.375rem;
    --radius-box: 1rem;
    --depth: 0;
    --noise: 0;
}

@source '../views';
@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source "../../vendor/robsontenorio/mary/src/View/Components/**/*.php";

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji';
}
```

### Audio Player Mobile Simplification
```blade
{{-- Volume section: hide slider on mobile (mute icon only) --}}

{{-- Desktop: full volume control --}}
<div class="hidden sm:flex items-center gap-2">
    <button type="button" x-on:click="toggleMute" ...>
        <template x-if="isMuted || volume === 0">
            <x-mary-icon name="o-speaker-x-mark" class="size-5" />
        </template>
        <template x-if="!isMuted && volume > 0">
            <x-mary-icon name="o-speaker-wave" class="size-5" />
        </template>
    </button>
    <input type="range" ... class="range range-xs range-primary w-20" />
</div>

{{-- Mobile: mute toggle only --}}
<div class="flex sm:hidden items-center">
    <button type="button" x-on:click="toggleMute"
            class="flex size-8 items-center justify-center rounded text-zinc-400 hover:text-white"
            :aria-label="isMuted ? 'Unmute' : 'Mute'" :aria-pressed="isMuted">
        <template x-if="isMuted || volume === 0">
            <x-mary-icon name="o-speaker-x-mark" class="size-5" />
        </template>
        <template x-if="!isMuted && volume > 0">
            <x-mary-icon name="o-speaker-wave" class="size-5" />
        </template>
    </button>
</div>
```

### x-mary-main Slots Reference (from Main.php source)
```blade
{{-- All props and slots available on x-mary-main --}}
<x-mary-main
    full-width          {{-- removes max-w-screen-2xl --}}
    with-nav            {{-- adjusts sidebar height when sticky nav is above --}}
    collapsible         {{-- adds collapse toggle button in sidebar --}}
    collapse-text="..." {{-- label for collapse button --}}
    collapse-icon="..." {{-- icon for collapse button --}}
>
    <x-slot:sidebar
        drawer="drawer-id"          {{-- REQUIRED: checkbox ID for mobile toggle --}}
        collapsible                 {{-- enables sidebar collapsing --}}
        right                       {{-- puts sidebar on right side --}}
        right-mobile                {{-- right side on mobile only --}}
        collapse-text="..."
        collapse-icon="..."
        class="bg-base-100"
    >
        {{-- Sidebar content --}}
    </x-slot:sidebar>

    <x-slot:content class="pb-28">
        {{ $slot }}
    </x-slot:content>

    <x-slot:footer>
        {{-- Optional footer --}}
    </x-slot:footer>
</x-mary-main>
```

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Flux `flux:sidebar` + `flux:main` | Mary `x-mary-main` with drawer slots | Phase 7 | Layout no longer depends on Flux |
| Mobile hamburger + slide-in drawer | Fixed bottom nav bar | Phase 7 | App-like feel; z-stacking with player required |
| `flux:icon.name` components | `x-mary-icon name="o-name"` | Phase 7 | All icons via Heroicons with `o-` prefix |
| Custom CSS range (`appearance-none bg-zinc-600 accent-white`) | daisyUI `range range-xs range-primary` | Phase 7 | Themed via daisyUI; no custom overrides |
| Flux `@theme` variables (zinc palette, accent vars) | Removed — daisyUI owns the color system | Phase 7 | Fewer custom property conflicts |
| Flux `@source` directives | Replaced with Mary `@source` | Phase 7 | Tailwind scans correct vendor directory |

**Deprecated/outdated in this phase:**
- `flux:sidebar`, `flux:sidebar.header`, `flux:sidebar.nav`, `flux:sidebar.group`, `flux:sidebar.item`, `flux:sidebar.collapse`, `flux:sidebar.toggle`, `flux:sidebar.profile`
- `flux:main` (replaced by `x-slot:content` inside `x-mary-main`)
- `flux:spacer` (replaced by `flex-1` or structural spacing)
- `flux:header` (removed — bottom nav replaces mobile header)
- `flux:dropdown`, `flux:menu`, `flux:menu.item`, `flux:menu.separator` (in user menu — replaced by `x-mary-dropdown`)
- All `flux:icon.*` in audio player — replaced by `x-mary-icon`

## Open Questions

1. **`@fluxAppearance` Phase 7 scope**
   - What we know: CONTEXT locks `@fluxScripts` to Phase 11. REQUIREMENTS INFRA-02 maps both `@fluxScripts` and `@fluxAppearance` to Phase 7.
   - What's unclear: Would removing `@fluxAppearance` break any remaining Flux components in Phase 7? It injects the `.dark` class which Flux relies on.
   - Recommendation: Keep `@fluxAppearance` in Phase 7. Deliver INFRA-02 fully in Phase 11 when Flux is removed entirely. Flag in plan notes.

2. **Content area mobile padding (player + bottom nav stack)**
   - What we know: Player ~80px tall, nav bar ~56px tall. Current padding is `pb-28` (112px) for player alone.
   - What's unclear: Exact rendered heights depend on font/spacing. Need visual validation.
   - Recommendation: Start with `pb-36 lg:pb-28` on `x-slot:content` (144px mobile, 112px desktop). Adjust visually after build.

3. **`@custom-variant dark` retention**
   - What we know: It targets `.dark` class. `spotify-dark` theme with `prefersdark: true` handles dark mode via daisyUI. The `html` element has `class="dark"` in both layout files.
   - What's unclear: Whether any Mary UI components use `dark:` utilities that need the custom variant.
   - Recommendation: Keep it. It's harmless when the `html` element has `class="dark"`, and enables `dark:` utilities for custom styles.

4. **x-app-logo component update scope**
   - What we know: `resources/views/components/app-logo.blade.php` uses `flux:sidebar.brand` and `flux:brand` (based on the `:sidebar` prop pattern in the current sidebar.blade.php call `<x-app-logo :sidebar="true">`).
   - What's unclear: Whether rewriting x-app-logo is in scope for Phase 7 or if it can be left as a Flux component temporarily.
   - Recommendation: Rewrite to a simple Blade component (anchor + logo icon + app name) — this is necessary since `flux:sidebar.brand` renders inside the Flux sidebar context which is being removed.

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest v4 + PHPUnit v12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=AudioPlayer` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| INFRA-01 | Flux CSS selectors absent from app.css | unit | `php artisan test --compact --filter=CssFoundation` | ❌ Wave 0 |
| INFRA-02 | @fluxScripts retained; @fluxAppearance retained (deferred) | manual-only | Visual check + file inspection | N/A |
| INFRA-03 | Mary @source directive in app.css | unit | `php artisan test --compact --filter=CssFoundation` | ❌ Wave 0 |
| LAYOUT-01 | Authenticated pages load without 500/blade errors | feature | `php artisan test --compact --filter=DashboardTest` | ✅ exists |
| LAYOUT-02 | Sidebar nav links rendered and use wire:navigate | feature | `php artisan test --compact --filter=AppShellTest` | ❌ Wave 0 |
| LAYOUT-03 | Mobile bottom nav renders with three links | feature | `php artisan test --compact --filter=AppShellTest` | ❌ Wave 0 |
| LAYOUT-04 | User menu dropdown contains logout button (data-test) | feature | `php artisan test --compact --filter=AppShellTest` | ❌ Wave 0 |
| PLAYER-01 | Audio player renders with x-mary-icon (no flux:icon) | feature | `php artisan test --compact --filter=AudioPlayer` | ✅ exists (partial) |
| PLAYER-02 | Seek/volume sliders have daisyUI range classes | manual-only | Visual browser check | N/A |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=AudioPlayer`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/AppShellTest.php` — covers LAYOUT-01, LAYOUT-02, LAYOUT-03, LAYOUT-04: sidebar renders, three nav links present, bottom nav present, user menu has `data-test="logout-button"`
- [ ] `tests/Feature/CssFoundationTest.php` — covers INFRA-01, INFRA-03: file_get_contents check that `[data-flux-field]` is absent and `robsontenorio/mary` @source is present in app.css

## Sources

### Primary (HIGH confidence)
- `vendor/robsontenorio/mary/src/View/Components/Main.php` — full slot API, props, drawer pattern
- `vendor/robsontenorio/mary/src/View/Components/Menu.php` — activate-by-route, uuid
- `vendor/robsontenorio/mary/src/View/Components/MenuItem.php` — routeMatches() logic, wire:navigate auto-injection, @aware
- `vendor/robsontenorio/mary/src/View/Components/Dropdown.php` — trigger slot, positioning via x-anchor
- `vendor/robsontenorio/mary/src/View/Components/Icon.php` — icon name resolution, `heroicon-{name}` format
- `vendor/robsontenorio/mary/src/MaryServiceProvider.php` — hardcoded `mary-*` aliases, prefix config
- `vendor/robsontenorio/mary/routes/web.php` — mary.toogle-sidebar route definition
- `vendor/robsontenorio/mary/stubs/app.blade.php` — official layout stub
- `vendor/blade-ui-kit/blade-heroicons/resources/svg/` — confirmed all `o-*` icon names used in audio player
- `resources/css/app.css` — exact content verified; all remove/keep/add changes mapped
- `resources/views/layouts/app.blade.php` — current Flux sidebar structure
- `resources/views/layouts/app/sidebar.blade.php` — current flux:main wrapper
- `resources/views/components/desktop-user-menu.blade.php` — current Flux dropdown
- `resources/views/livewire/audio-player.blade.php` — all 7 flux:icon instances identified
- `resources/views/partials/head.blade.php` — @fluxAppearance location confirmed

### Secondary (MEDIUM confidence)
- `php artisan route:list --name=mary` — confirms mary.toogle-sidebar registered (verified at runtime)
- `composer show robsontenorio/mary` — confirms version 2.8.0 installed 2026-02-26
- `.planning/STATE.md` — wire:navigate + Mary drawer GitHub issue #384 blocker note

### Tertiary (LOW confidence)
- daisyUI `range-primary` color modifier naming — follows same pattern as `btn-primary`, `badge-primary` but not directly inspected from daisyUI source; confirmed working in prior v1.0 code patterns

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — All packages verified at runtime via composer show and file inspection
- Architecture: HIGH — Component PHP sources read directly; slot APIs documented from code
- Icon mapping: HIGH — All icons confirmed present in blade-heroicons vendor SVG directory
- CSS cleanup: HIGH — Every line to remove/add identified from reading app.css directly
- Mobile z-stacking: MEDIUM — Height calculations are estimates; visual validation required after build
- Wave 0 test content: MEDIUM — Test structure inferred from existing patterns

**Research date:** 2026-03-13
**Valid until:** 2026-04-13 (stable packages; Mary ^2.8 pinned)
