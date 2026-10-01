<!-- How to use: start a fresh Claude Code session with Opus 4.8 in this repo and paste this file
     (or say "execute DESIGN_REWORK_PROMPT.md"). Strongly recommended: `git init && git add -A && git commit`
     first so every phase can be committed and reverted. -->

# Rekodo Design Rework — Execution Prompt

You are rebuilding the entire visual layer of **Rekodo**, a self-hosted personal music streaming app (Laravel 12 + Livewire 4 + Tailwind CSS v4 + daisyUI 5 + Mary UI + Alpine.js). The current UI is a deliberate "terminal" aesthetic: JetBrains Mono everywhere, 13px root font, zero border-radius, `box-shadow: none` globally, a single hardcoded Catppuccin Frappé theme, and `$`/`[ERR]` prefixes on form labels. You are replacing that aesthetic completely.

## 1. Mission

Rework Rekodo's design into a sophisticated, Apple-grade interface modeled on Apple's Human Interface Guidelines and the Apple Music app (macOS + iOS):

1. **A real design system** — semantic tokens, a disciplined type ramp, materials (translucency/vibrancy), hairline separators, soft elevation, and restrained motion. No hardcoded palette utilities in views.
2. **User-switchable themes** — five theme options plus accent colors, selectable in Settings → Appearance, persisted per user, applied with zero flash-of-wrong-theme.
3. **Every surface rebuilt** — shell, player, library, album, playlists, home, settings, auth, modals, empty/loading/toast states.

This is a design rework, not a feature milestone: keep the backend (streaming, scanning, uploads, auth, playlists) untouched except where this document explicitly allows. Rekodo will later grow toward Navidrome-class capability (global search, play history, Subsonic API, etc.) — build tokens and components so new surfaces can adopt them without rework, but do **not** build those features now.

Work autonomously. Make reasonable calls within this spec; where it is silent, choose what Apple would ship.

## 2. Read these first

Ground yourself in the actual code before changing anything:

- `resources/css/app.css` — current theme block, tokens, base styles (the file you will largely rewrite)
- `resources/views/partials/head.blade.php` — Google Fonts links to remove
- `resources/views/layouts/app/sidebar.blade.php` — app shell (`data-theme` from `auth()->user()->theme`, hardcoded `class="dark"`, mobile bottom nav)
- `resources/views/layouts/auth/simple.blade.php` and `resources/views/layouts/guest.blade.php` — hardcode `data-theme="catppuccin-frappe"`
- `resources/views/livewire/audio-player.blade.php` + `app/Livewire/AudioPlayer.php` — player bar (Alpine + HTML5 audio; note the unused `queueTracks()` computed)
- `resources/views/pages/library/index.blade.php` — library page (the track table is duplicated inside it)
- `resources/views/pages/library/album-show.blade.php`, `resources/views/playlists/show.blade.php` — two more near-copies of the same track table
- `resources/views/pages/settings/layout.blade.php` + the `settings/` pages — settings shell
- `resources/views/dashboard.blade.php` — still the untouched Laravel starter-kit placeholder
- `resources/views/livewire/upload-modal.blade.php`, `add-to-playlist-modal.blade.php`, `relink-track-modal.blade.php`
- `routes/web.php`, `routes/settings.php`, `app/Models/User.php`, `database/migrations/*theme*`
- `AGENTS.md` — project conventions (Pest, Pint, Livewire/Tailwind skill activation, Boost MCP tools). Follow it.

Filesystem quirk: Livewire single-file pages use a ⚡ prefix which is stored as literal `#U26a1` bytes in filenames here (e.g. `pages/settings/#U26a1appearance.blade.php`). It resolves correctly. **Do not rename these files**; quote paths in shell commands; follow the same naming for any new page component.

Data available to design with: tracks have `title, artist, album, track_number, duration, year, genre, source_type (uploaded|local), is_available, created_at`; albums have `title, artist, coverArtUrl()`; playlists have `name, description`; users have `name, email, avatarUrl(), initials(), bio, is_admin, theme`. There is **no play-history model** — do not design around data that doesn't exist.

## 3. Known design debt to eliminate

- Hardcoded `zinc-*`, `text-white`, `bg-white`, `bg-black` utilities throughout views — they ignore the theme system entirely (the player bar stays dark zinc even in a light theme).
- `resources/views/pages/library/album-show.blade.php` row hover is `hover:bg-white` on a dark theme — white-on-white text.
- The same track-table markup exists in four places (library search results, library Tracks tab, album detail, playlist detail). Extract one component.
- Dashboard is placeholder patterns from the starter kit.
- `class="dark"` hardcoded on `<html>` plus a `@custom-variant dark` and scattered `dark:` utilities — remove the whole mechanism; semantic tokens make it unnecessary.
- Appearance settings page is static text ("Rekodo uses Catppuccin Frappé…") with no control, despite `users.theme` existing in the DB.
- Google Fonts (JetBrains Mono, IBM Plex Mono) loaded from fonts.googleapis.com — remove; use the system font stack.
- Focus ring, ::selection, scrollbar colors hardcoded to Frappé hexes in `app.css`.
- Player volume icon has two identical `<template>` branches for 1–50 and 51–100.
- Queue exists in state (`AudioPlayer::$queue`, `queueTracks()`) but has no UI.
- 3 pre-existing test failures (LibraryViewTest stale sort assertion, AuthPagesTest verify-email route disabled). Leave them; introduce no new failures.

## 4. Design language — "Rekodo HIG"

### 4.1 Principles

- **Deference**: chrome recedes; artwork and typography carry the interface. Color comes from album art and the accent, not from decorated containers.
- **Clarity**: one type ramp, consistent optical sizes, generous whitespace, 44px minimum touch targets.
- **Depth**: translucent materials for chrome (sidebar, bars, sheets), hairline separators, soft shadows only where something floats.
- **Consistency**: every control looks like it came from the same hand. If you style something twice, extract a component.
- **Restraint**: no gradients-for-decoration, no glassmorphism-everywhere, no scale-up hover "lifts" on cards. Apple Music hovers reveal affordances (a play button materializes over artwork); they don't bounce.

### 4.2 Typography

Remove the mono fonts and the Google Fonts `<link>`. Use the native stack so Apple devices render SF Pro and everything else stays clean:

```css
--font-sans: -apple-system, BlinkMacSystemFont, ui-sans-serif, "SF Pro Text", "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
--font-mono: ui-monospace, "SF Mono", Menlo, Consolas, monospace; /* used only where §6.8 allows */
```

Restore `html { font-size: 16px }`. Type ramp (define as utilities/tokens and use exclusively — no ad-hoc `text-2xl font-bold`):

| Token | Size/weight | Use |
|---|---|---|
| `large-title` | 32px / 700 / -0.02em | Page heroes (Library, playlist name) |
| `title-1` | 26px / 700 / -0.015em | Section heroes (album title) |
| `title-2` | 21px / 600 | Card group headers ("Recently Added") |
| `title-3` | 17px / 600 | Modal titles, settings section headers |
| `body` | 15px / 400 / 1.45 | Default text, table cells |
| `body-emphasized` | 15px / 590 (use 600) | Row titles, buttons |
| `subhead` | 13px / 400 | Secondary metadata, sidebar items |
| `footnote` | 12px / 400 | Timestamps, durations, helper text |
| `caption` | 11px / 500 / +0.06em, uppercase | Sidebar section labels, column headers |

All numerals in durations, times, counts, and track numbers: `font-variant-numeric: tabular-nums`. Never letter-space body text; only caption labels.

### 4.3 Color — semantic tokens only

Views may only use daisyUI semantic utilities (`bg-base-100`, `text-base-content`, `text-base-content/60`, `bg-primary`, …) plus CSS-variable utilities for the extra tokens (check Tailwind v4's current arbitrary-value syntax via `search-docs` before assuming). Opacity steps for label hierarchy, Apple-style: primary label = `base-content`, secondary = `/60`, tertiary = `/35`, quaternary = `/20`.

Role mapping (keep it consistent app-wide — this matches how `bg-base-200` is already used as the body canvas):

| Role | daisyUI slot | Used for |
|---|---|---|
| Canvas | `base-200` | Page/body background |
| Surface | `base-100` | Sidebar, cards, player bar, modals |
| Raised fill | `base-300` | Hover fills, wells, input backgrounds, segmented-control track |
| Label | `base-content` (+ opacity steps) | All text |
| Accent | `primary` | Buttons, active states, focus, selection, scrubber fill |

Add per-theme custom properties beyond daisyUI's set: `--color-separator` (hairlines), `--color-chrome` (translucent bar tint, used with backdrop-filter). If the `@plugin "daisyui/theme"` block doesn't emit arbitrary properties, add a companion `[data-theme="…"] { … }` rule per theme — verify in the built CSS.

### 4.4 Materials, elevation, radii

- **Chrome material** (sidebar, player bar, mobile tab bar, sticky toolbars, modal sheets): `background: var(--color-chrome); backdrop-filter: blur(20px) saturate(180%);` with a solid `base-100` fallback under `@supports not (backdrop-filter: blur(1px))` and when `prefers-reduced-transparency: reduce`.
- **Hairlines**: 1px `var(--color-separator)`. Never 2px borders for decoration.
- **Shadows** (remove the global `box-shadow: none`): three levels — `--shadow-1` 0 1px 2px rgba(0,0,0,.05) for resting cards; `--shadow-2` 0 2px 8px rgba(0,0,0,.08) for hover/dropdowns; `--shadow-3` 0 8px 32px rgba(0,0,0,.16) for modals/popovers. Dark themes roughly double the alpha.
- **Radii** via daisyUI tokens: `--radius-selector: 0.375rem` (badges, checkboxes), `--radius-field: 0.625rem` (inputs, buttons), `--radius-box: 0.875rem` (cards, modals). Artwork: 6px small (≤48px), 10px large, always with `box-shadow: inset 0 0 0 1px var(--color-separator)` over the image so light covers hold an edge. Pills/scrubber/segmented controls: full capsule.
- Set `--depth: 0` and `--noise: 0` in every daisyUI theme block (no daisyUI faux-3D).

### 4.5 Iconography

Keep Heroicons via `x-mary-icon` (closest web analog to SF Symbols). Sizes: 16px inline/sidebar, 20px toolbar buttons, 24px player transport. Stroke ("o-") variants for inactive, solid ("s-") for active/toggled states (e.g. shuffle on, repeat on) — that on/off pairing is the SF Symbols idiom.

### 4.6 Motion

- Tokens: `--ease-out: cubic-bezier(0.25, 1, 0.5, 1)`, `--ease-sheet: cubic-bezier(0.32, 0.72, 0, 1)`. Durations: 120ms hover, 200–240ms enter, 160ms exit, 320–400ms sheets/panels.
- Popovers/menus: opacity + scale(0.97→1), `transform-origin` at the trigger.
- Modals: fade + 8px rise; backdrop `rgba(0,0,0,.4)` + 8px blur. Mobile: bottom sheets sliding up with a grabber.
- Scrubber/volume: 4px track that grows to 6px with a visible knob on hover.
- `prefers-reduced-motion: reduce` → collapse all transform/slide animations to opacity-only, ~1ms.

### 4.7 Interaction states

Every interactive element defines rest / hover (raised-fill or accent-tint) / active (pressed: slightly darker, no scale) / focus-visible (`outline: 2px solid var(--color-primary); outline-offset: 2px`) / disabled (40% opacity, no pointer). Row selection and active nav items use an accent-tinted fill (`color-mix(in srgb, var(--color-primary) 12%, transparent)`) with accent-colored text/icon — the macOS sidebar-selection look.

## 5. Theme system

### 5.1 Themes

Five options in a flat picker. Implement as daisyUI 5 custom themes (follow the existing `@plugin "daisyui/theme"` block syntax in `app.css`; set `color-scheme` in each so native controls adapt). Seed palettes below — you may refine ±5% for contrast, but keep the character:

| Theme | Slug | Scheme | Canvas (base-200) | Surface (base-100) | Raised (base-300) | Label (base-content) | Separator |
|---|---|---|---|---|---|---|---|
| **Auto** | `system` | — | resolves to Porcelain or Graphite by OS setting | | | | |
| **Porcelain** | `porcelain` | light | `#F5F5F7` | `#FFFFFF` | `#E9E9EE` | `#1D1D1F` | `rgba(0,0,0,0.08)` |
| **Graphite** | `graphite` | dark | `#161618` | `#1F1F22` | `#2C2C30` | `#F5F5F7` | `rgba(255,255,255,0.10)` |
| **Midnight** | `midnight` | dark | `#000000` | `#0C0C0E` | `#1C1C1F` | `#F2F2F7` | `rgba(255,255,255,0.13)` |
| **Frappé** | `catppuccin-frappe` | dark | `#292C3C` | `#232634` | `#303446` | `#C6D0F5` | `rgba(198,208,245,0.10)` |

`--color-chrome` per theme = surface color at ~72% alpha. Frappé is the heritage palette — it keeps Catppuccin colors but gains the new typography, radii, and materials (it is a palette, not the old terminal aesthetic; the `$`/`[ERR]` label styling dies everywhere).

Mechanics: `porcelain` gets `default: true`, `graphite` gets `prefersdark: true`. **Auto/system = render no `data-theme` attribute at all**, letting daisyUI's default/prefersdark pair follow `prefers-color-scheme` in pure CSS — zero JS, zero flash. Explicit themes render `data-theme="<slug>"` server-side.

### 5.2 Accent colors

An accent dimension on top of themes, applied as `data-accent="<slug>"` on `<html>`, implemented as a CSS layer **after** the theme blocks that overrides `--color-primary` / `--color-primary-content` (and `--color-accent` to match). Each accent defines a value per theme context so it harmonizes (iOS palette for Porcelain/light, iOS-dark palette for Graphite/Midnight, Catppuccin palette for Frappé):

| Accent | Light | Dark | Frappé |
|---|---|---|---|
| **Music Red** (default) | `#FA233B` | `#FF3B52` | `#E78284` |
| Blue | `#007AFF` | `#0A84FF` | `#8CAAEE` |
| Purple | `#AF52DE` | `#BF5AF2` | `#CA9EE6` |
| Pink | `#FF2D55` | `#FF375F` | `#F4B8E4` |
| Orange | `#FF9500` | `#FF9F0A` | `#EF9F76` |
| Green | `#34C759` | `#30D158` | `#A6D189` |
| Mono | `#6E6E73` | `#98989D` | `#838BA7` |

For each pair, pick `primary-content` (white or near-black) so text on accent fills meets WCAG AA at button sizes; darken the accent slightly if neither works (orange and the light Frappé pastels will need this). Handle the cascade for system mode too: overrides must win both under `[data-theme]` and under the no-attribute default/prefersdark case — verify in the browser in all three situations.

### 5.3 Persistence & plumbing

- `users.theme` exists (string). Migration: add `users.accent` (string, default `music-red`), change `theme` default to `system`, and backfill existing rows to `system` (this rework intentionally moves everyone to the new default; Frappé remains selectable).
- One source of truth for the `<html>` attributes — a small Blade partial or `User` helper used by **all three** layouts (`layouts/app/sidebar`, `layouts/auth/simple`, `layouts/guest`): emits `data-theme` (omitted when `system` or guest) and `data-accent` (default when guest). Remove `class="dark"` and the hardcoded `data-theme="catppuccin-frappe"` from the auth/guest layouts.
- Add `<meta name="theme-color">` entries with `media="(prefers-color-scheme: …)"` matching the effective canvas color (update via the same partial for explicit themes).
- Validate theme/accent slugs server-side against a single PHP enum or config list — the same list drives the settings UI, validation, and tests. No duplicated slug arrays.

### 5.4 Settings → Appearance page

Rebuild `pages/settings/#U26a1appearance.blade.php` as a real Livewire component:

- **Theme picker**: a grid of five cards, each a miniature mock of the UI in that theme (a tiny sidebar + content + player bar drawn with divs in the theme's actual palette — the macOS System Settings appearance-picker pattern). Auto shows a split light/dark diagonal. Selected card gets an accent ring + checkmark badge. Label + "Light"/"Dark"/"Auto" subtitle.
- **Accent picker**: a row of 28px color dots (rendered in the current effective theme's variant), selected dot gets a ring; name of the selected accent shown beside the row.
- **Live preview**: clicking applies instantly via Alpine (set `data-theme`/`data-accent` on `document.documentElement`, handling the "system = remove attribute" case) and persists via Livewire in the same action. On failure, revert the attributes and toast an error.
- Pest tests: updating theme/accent persists; invalid slugs rejected; page renders each option; layout emits correct attributes for explicit vs system vs guest.

## 6. Surface-by-surface requirements

### 6.1 App shell

- **Sidebar (desktop)**: chrome material, 240–260px. Wordmark top (see 6.12). Nav sections with caption-style labels: main nav (Home, Library, Playlists — Upload becomes a toolbar/button action, not a nav item), then the user's playlists listed directly in the sidebar (Apple Music pattern; cap at ~10 with a "See all"). Items: 16px icon + subhead text, accent-tinted selection pill. User menu moves to the sidebar **bottom** (avatar + name + chevron opening a popover with Profile / Settings / Log Out).
- **Content toolbar**: pages get a large-title header that condenses into a sticky chrome-material toolbar on scroll (IntersectionObserver + Alpine; title fades in, actions persist). Back navigation as a chevron button where hierarchy exists (album → library).
- **Mobile**: iOS tab bar (chrome material, safe-area padding, 4 items: Home, Library, Playlists, Settings) + the mini-player docked directly above it. Replace the current hamburger/drawer approach for primary nav; keep a compact top bar with title only.
- Replace `x-mary-main`/`x-mary-nav` shell with bespoke layout markup (Mary stays for menus/dropdowns/modals/inputs). Keep `wire:navigate` on all internal links and keep `@persist('audio-player')` working — test that playback continues across navigation.

### 6.2 Player + queue (the signature surface — spend effort here)

Bar: full-width, chrome material, hairline top border, ~72px desktop.

- **Left — Now Playing**: 48px artwork (6px radius, hairline inset), title (`body-emphasized`, truncating) over artist (`subhead`, secondary). Clicking artist/title is inert for now (no artist pages yet).
- **Center — Transport + scrubber**: shuffle · previous · play/pause · next · repeat. Play/pause is a 44px accent-filled circle (white glyph); others are 32px icon buttons. Shuffle/repeat show accent color + solid icon when active. Below: elapsed (`footnote`, tabular) — capsule scrubber — remaining/total. Scrubber shows buffered range subtly if easy via `audio.buffered`.
- **Right**: volume icon-button + capsule slider (icon reflects level: muted/low/high — fix the duplicated-template bug), a queue button, and (if implemented) full-screen toggle.
- **Queue panel**: the missing surface. Chrome-material slide-over from the right (~360px, sheet on mobile), titled "Playing Next": current track pinned at top with an animated-bars indicator, upcoming rows (36px artwork, title/artist, duration), click-to-jump, per-row remove, "Clear" action. Backend additions allowed on `App\Livewire\AudioPlayer` only: `playQueueIndex(int $index)`, `removeFromQueue(int $index)`, `clearQueue()`, plus a `repeat` mode (`off|all|one`) honored in `nextTrack()`/ended handling (wrap for `all`, replay for `one`). Use the existing `queueTracks()` computed. Drag-to-reorder is **out of scope**.
- **Media Session API**: set `navigator.mediaSession` metadata (title/artist/album/artwork) and action handlers (play/pause/next/prev/seek) so OS media keys and lock screens work — the "feels native" multiplier.
- **Mobile mini-player**: compact card above the tab bar — artwork, title, play/pause, next; tapping elsewhere opens a full-height now-playing sheet (artwork large, blurred-artwork backdrop at low opacity, full transport, queue access). If time-boxed, the sheet may land in the polish phase, but the mini-player itself is required.
- Keep all existing behavior working: keyboard shortcuts (space/arrows), autoplay-block handling, unavailable-track skip toast, replace-queue confirmation (restyle it as a proper modal), volume persistence, queue auto-extend events.

### 6.3 Home (replace the placeholder dashboard)

Real content from real data:

- Greeting header: time-of-day "Good morning/afternoon/evening, {first name}" as `large-title`.
- **Recently Added**: horizontal shelf of album cards (scroll-snap, edge fade), from albums by `created_at desc`.
- **Your Playlists**: shelf of playlist cards (stacked-artwork mosaic from up to 4 track covers; tasteful placeholder otherwise).
- **Library stats**: one refined row (tracks · albums · artists · total duration) as footnote-level text, not dashboard-widget cards.
- Empty library → full-bleed welcome state: wordmark, one-line pitch, primary "Upload music" + secondary "Link a folder" actions.
- Route stays `dashboard`; sidebar label becomes "Home". No new backend models — computed queries in the page component are fine.

### 6.4 Library

- Toolbar: `large-title` "Library", search field (capsule, leading magnifier, clear button), source filter as a compact Mary select or menu, Upload button (accent, capsule).
- Tabs → iOS **segmented control** (raised-fill track, sliding thumb) for Tracks / Artists / Albums. Preserve the `@entangle('activeTab')` behavior and infinite scroll (`wire:intersect`) exactly.
- **Tracks**: the shared track-table component (see §7). Columns: index/art+play-swap, Title (+ badges), Artist, Album, Duration; 44px rows, hairline separators, quaternary-fill hover revealing the play affordance and the add-to-playlist ellipsis actions. "Local" badge becomes a subtle tinted pill; "Unavailable" rows dim with a footnote-styled relink action.
- **Albums**: responsive grid (160–200px), artwork with hover overlay: dimming gradient + 36px vibrancy play button bottom-left (plays the album via existing `play-track` event contract), title `body-emphasized` + artist `subhead` secondary below. No card boxes around them — bare artwork on canvas, Apple Music style.
- **Artists**: replace the current gray boxes with clean rows — 44px circular monogram avatar (accent-tinted), name, "N tracks" secondary, and a trailing horizontal strip of that artist's album thumbnails.
- Search results view reuses the same components (this removes the duplicated table).

### 6.5 Album detail

Apple Music album layout: large artwork left (~232px, 10px radius, `--shadow-2`), right column with `title-1` album title, artist `title-3` in accent color, footnote metadata line (year · genre · N tracks · total duration — all fields exist), then capsule buttons: accent "Play", raised-fill "Shuffle". Track list below via the shared component in album mode (track number instead of artwork, no album column). Fix the `hover:bg-white` bug by construction.

### 6.6 Playlists

- Index: grid of playlist cards (artwork mosaic, name, "N tracks") + a "New Playlist" dashed-outline tile.
- Show: album-detail-like header (mosaic artwork, `title-1` name, description as body-secondary, count/duration footnote; Play/Shuffle capsules; Edit as toolbar action). Shared track list with per-row "remove from playlist" (existing controller endpoints; keep the forms/CSRF behavior).
- Create/Edit: settings-style forms (see 6.8), not bare pages.

### 6.7 Upload flow & modals

- Restyle all three modals (upload, add-to-playlist, relink) via Mary modal + new tokens: `--radius-box`, `--shadow-3`, chrome/backdrop treatment, `title-3` headers, capsule buttons. Bottom-sheet presentation on mobile.
- Upload dropzone: dashed hairline well that tints accent on dragover; per-file rows with icon, name, size; clear success state.
- Add-to-playlist: searchable list of playlist rows (mosaic thumb + name + count) with hover fill; "New playlist" pinned at bottom.
- Upload summary page: align to the new track-list styling.

### 6.8 Settings suite

- Settings shell (`pages/settings/layout.blade.php`): left nav becomes grouped sidebar items with 16px icons (Profile, Password, Two-Factor, Appearance, Linked Folders, Users·admin); content column max-w ~640px with `title-2` heading + subhead description.
- All settings forms move to **grouped inset lists** (iOS Settings): rounded `base-100` cards on the canvas, 44px rows, hairline row separators inset from the leading edge, labels left / values-controls right. Danger zone (delete account) = red-tinted group at the bottom.
- Rebuild each page to this pattern: profile (avatar upload with 96px circular preview + camera badge), password, two-factor + recovery codes (recovery codes as a monospace-*numerals* grid card — `font-mono` is acceptable here only), linked folders (rows with path, status pill, scan progress as a thin accent progress bar, rescan/remove actions), users admin (avatar+name+email rows, role pill, invite action), delete account.
- Appearance page per §5.4.

### 6.9 Auth pages

Porcelain/Graphite via system (no stored pref yet): centered 400px card (`base-100`, `--radius-box`, `--shadow-1`) on the canvas, wordmark above, `title-2` heading + subhead. Kill the `[ rekodo ]` bracket title and every `$`/`[ERR]` label prefix (delete those CSS rules). Fields: 44px, `--radius-field`, raised-fill background, accent focus ring; full-width accent capsule submit. Restyle: login, register, forgot/reset password, verify email, confirm password, 2FA challenge (6-digit code boxes centered — keep the existing `x-mary-pin` + hidden-input bridge, it exists for a reason).

### 6.10 Profile

Header: 96px avatar, `title-1` display name, footnote join date, bio as body-secondary (max-w-prose). Their public content below using the shared shelf/card components.

### 6.11 System states

- **Toasts**: keep the `mary-toast` event contract (JS dispatches it), restyle the container/appearance: top-center compact capsule, chrome material, icon + `subhead` text, slide-down + fade, ~3s.
- **Empty states**: extend the existing `x-empty-state` — 56px icon in an accent-tinted circle, `title-3` heading, subhead-secondary description, optional capsule action.
- **Loading**: skeleton shimmer (rounded blocks in raised fill, respecting reduced-motion) for track rows and album cards during `wire:loading` / infinite-scroll fetches, replacing bare spinners where a layout is known.

### 6.12 Wordmark

Simple and typographic: "Rekodo" in `title-3`/600 next to a 20px inline-SVG glyph — a vinyl record (two concentric circles + center dot, `currentColor`, accent-colored). No image assets. Use it in sidebar, auth pages, favicon can stay as-is.

## 7. Shared components to build

Blade components under `resources/views/components/ui/` (usable as `<x-ui.track-table>` etc.):

| Component | Replaces / used by |
|---|---|
| `ui/track-table` (+ `ui/track-row`) | The four duplicated tables: library tabs, library search, album detail (`mode: album`), playlist detail (`mode: playlist`, remove action slot). Props: tracks payload, trackIds for the `play-track` dispatch, mode, show-album/show-artist flags. Preserves the exact existing event contracts: `play-track`, `open-add-to-playlist`, `open-relink-modal`. |
| `ui/album-card` | Library grid, home shelves, artist strips |
| `ui/media-shelf` | Horizontal scroll-snap shelf (home) |
| `ui/page-header` | Large-title + condensing toolbar behavior |
| `ui/segmented-control` | Library tabs (Alpine-driven, entangle-compatible) |
| `ui/icon-button` | Toolbar/player secondary buttons (hover-fill circle) |
| `ui/settings-group` (+ `ui/settings-row`) | All settings pages |
| `ui/theme-card`, `ui/accent-dot` | Appearance page |
| `ui/empty-state` (evolve existing) | Everywhere |

If a component is styled once and never reused, inline it — don't manufacture abstraction. But the track table is non-negotiable: one implementation.

## 8. Engineering constraints & guardrails

- **Stack is fixed**: no new npm or composer dependencies, no framework/library swaps, no build-tool changes. Tailwind v4 CSS-first (`@theme`, `@plugin`, `@utility`) in `resources/css/app.css`; delete dead CSS rather than accreting overrides.
- **Keep Mary UI** for functional primitives (modal, input/select/textarea, dropdown/menu, toast, pin, badge, icon) — they inherit daisyUI tokens. Replace Mary layout components (`x-mary-main`, `x-mary-nav`) with bespoke shell markup.
- **Livewire discipline**: pages stay single-file components under `resources/views/pages/`; don't break `wire:model` bindings, `wire:intersect` infinite scroll, `@persist('audio-player')`, or dispatched browser events. Backend/PHP changes are limited to: the appearance component, theme/accent enum + migration + `User`, `AudioPlayer` (queue/repeat additions), the home page component's read-only queries, and view-layer components. **Do not touch** StreamController, scanning/upload jobs, auth logic, or policies. Routes should need no changes; if you think one does, reconsider the design first.
- **Accessibility**: preserve every existing `aria-*`/`role`/`data-test` attribute through restyles (tests depend on `data-test`). AA contrast (4.5:1 body text, 3:1 large/UI) for label-on-canvas/surface and content-on-accent **in all five themes** — check, don't assume. Full keyboard operability for the queue panel, segmented control, theme picker. Honor `prefers-reduced-motion` and `prefers-reduced-transparency`.
- **Conventions**: follow `AGENTS.md` — Pest for every change (`php artisan test --compact`), `vendor/bin/pint --dirty` before finishing, Boost MCP tools (`search-docs` for Livewire/Tailwind/daisyUI specifics, `tinker`, `browser-logs`, `get-absolute-url`) whenever applicable. If the `frontend-design` skill is available in your environment, load it before styling work.
- **No extra docs**: don't create new markdown/docs files; don't edit `.planning/`. Leave stray root files (`tmp-git-status.txt`, `node-git-status.txt`, `test-gsd.js`) alone. `resources/views/welcome.blade.php` is unreachable (routes redirect) — you may delete it.
- If the repo is a git repository when you start, commit at each phase boundary with a clear message. If it is not, tell the user to `git init` first and stop until they confirm.
- If the dev database is empty, seed a handful of albums/tracks/playlists via the existing factories (tinker) so you can actually see what you're designing. Never touch a non-empty database's data.

## 9. Execution plan — phases with gates

Complete phases in order. **Gate after every phase**: `npm run build` succeeds, `php artisan test --compact` shows no new failures, `vendor/bin/pint --dirty` clean, and a hands-on browser check of the affected surfaces in at least Porcelain + Graphite (all five for Phase 1–2). Commit at each gate if under git.

1. **Foundation** — rewrite `app.css`: five theme blocks, accent layer, type ramp, radii/shadows/materials/motion tokens, base styles (focus/selection/scrollbar via tokens), remove terminal-era rules, remove mono fonts + Google Fonts links, restore 16px root. Migration for `accent` + `theme` default/backfill; theme/accent enum; the shared `<html>`-attributes partial wired into all three layouts; remove `class="dark"`, `@custom-variant dark`, and all `dark:` utilities. App must render coherently (if plainly) in every theme via hand-set attributes.
2. **Appearance settings** — §5.4 complete with live preview + persistence + tests. From here on, theme switching is your QA tool: flip themes while building everything else.
3. **Shell** — sidebar, toolbars/page-header component, mobile tab bar, user-menu popover, wordmark.
4. **Player** — bar redesign, queue panel + repeat + Media Session, mobile mini-player, restyled replace-queue modal.
5. **Library suite** — track-table extraction, library page (segmented control, search, grids), album detail, upload summary, all three modals.
6. **Home, playlists, profile** — placeholder dashboard dies here.
7. **Settings suite & auth** — grouped lists everywhere, auth cards.
8. **Polish & audit** — motion pass, contrast audit per theme (fix failures), reduced-motion/transparency verification, skeletons/empty states, mobile safe-areas, full acceptance sweep (§10), optional full-screen now-playing sheet if all else is done.

## 10. Acceptance criteria

Mechanical — all must hold:

```bash
grep -rn "zinc-\|slate-\|gray-\|neutral-[0-9]" resources/views resources/css   # → no matches
grep -rn "dark:" resources/views                                              # → no matches
grep -rn "text-white\|bg-white\|bg-black" resources/views                     # → no matches
grep -rn "fonts.googleapis\|JetBrains\|IBM Plex" resources/                   # → no matches
grep -rn "class=\"dark\"\|@custom-variant dark" resources/                    # → no matches
```

Additionally: the total count of `data-test` attributes in `resources/views` must be ≥ the count before the rework (record it in Phase 1), and hex colors in `app.css` may exist **only** inside theme/accent definition blocks.

Behavioral:

- All 5 themes × 7 accents selectable in Settings → Appearance with instant preview; persist across reload and re-login; Auto follows the OS setting live; no flash of wrong theme on any page including auth.
- Playback continues across `wire:navigate`; space/arrow shortcuts work; queue panel lists, jumps, removes, clears; repeat off/all/one behaves correctly at queue end; OS media keys control playback and show artwork.
- One `x-ui.track-table` serves library/search/album/playlist; the album-page white-hover bug is gone.
- Dashboard shows real content (or the welcome state when the library is empty).
- Full Pest suite: zero new failures (the 3 documented pre-existing ones excepted); new tests cover appearance persistence/validation, layout theme attributes, and player repeat/queue actions.
- Contrast spot-checks pass AA in every theme for: sidebar item, track row title+metadata, secondary text on canvas, button text on accent, badge text.
- At 375px width: tab bar + mini-player don't overlap content; safe-area respected; sheets replace popovers.

## 11. Out of scope (later milestones — do not build now)

Global search (⌘K), play history / "recently played", artist detail pages, smart playlists, Subsonic API, transcoding, scrobbling, lyrics, drag-to-reorder queue, PWA manifest/offline, multi-user shared libraries. These are the Navidrome-parity track. Your only obligation to them: the token system and shared components must be generic enough that these surfaces can adopt them without redesign.
