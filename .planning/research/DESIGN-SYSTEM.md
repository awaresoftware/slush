# Design System — Foonk

> Catppuccin Frappé palette · Monospace typography · Solid surfaces with visible borders · v0.1.0

---

## Color Palette

### Base & Surfaces

| Token | Hex | Usage |
|---|---|---|
| `crust` | `#232634` | Deepest background, html/body bg, track bg |
| `mantle` | `#292c3c` | Sidebar bg, sticky header bg |
| `base` / `bg` | `#303446` | Card/component backgrounds |
| `surface-0` | `#414559` | Borders, album art placeholder |
| `surface-1` | `#51576d` | Strong borders, button outlines |
| `surface-2` | `#626880` | Hover borders, subtle dividers |
| `overlay-0` | `#737994` | Muted text, placeholders, icons |
| `overlay-1` | `#838ba7` | — |
| `overlay-2` | `#949cbb` | — |

### Text

| Token | Hex | Usage |
|---|---|---|
| `fg` | `#c6d0f5` | Primary text, body copy |
| `subtext-0` | `#a5adce` | Secondary text, nav items |
| `subtext-1` | `#b5bfe2` | Section titles |

### Accent & Semantic

| Token | Hex | Usage |
|---|---|---|
| `accent` | `#8caaee` | Primary actions, links, active states, focus rings |
| `lavender` | `#babbf1` | Accent hover, interactive highlight |
| `sapphire` | `#85c1dc` | Secondary decorative |
| `sky` | `#99d1db` | — |
| `teal` | `#81c8be` | — |
| `green` | `#a6d189` | Success, quality badges |
| `yellow` | `#e5c890` | Warning, medium strength |
| `peach` | `#ef9f76` | — |
| `maroon` | `#ea999c` | — |
| `red` | `#e78284` | Errors, weak password |
| `mauve` | `#ca9ee6` | Decorative |
| `pink` | `#f4b8e4` | — |
| `flamingo` | `#eebebe` | — |
| `rosewater` | `#f2d5cf` | — |

### Opacity Tokens

| Token | Value | Usage |
|---|---|---|
| `accent-a5` | `rgba(140, 170, 238, 0.05)` | Active row bg, badge bg |
| `accent-a10` | `rgba(140, 170, 238, 0.1)` | — |
| `accent-a30` | `rgba(140, 170, 238, 0.3)` | Badge border |
| `green-a5` | `rgba(166, 209, 137, 0.05)` | Green badge bg |
| `green-a30` | `rgba(166, 209, 137, 0.3)` | Green badge border |
| `red-a6` | `rgba(231, 130, 132, 0.06)` | — |
| `red-a15` | `rgba(231, 130, 132, 0.15)` | — |
| `bg-a85` | `rgba(48, 52, 70, 0.85)` | Overlays |
| `crust-a85` | `rgba(35, 38, 52, 0.85)` | Overlays |
| `crust-a50` | `rgba(35, 38, 52, 0.55)` | — |
| `mantle-a90` | `rgba(41, 44, 60, 0.4)` | — |

---

## Typography

### Font Stack

```
--font-mono: 'JetBrains Mono', 'IBM Plex Mono', 'SF Mono', ui-monospace, Menlo, monospace;
```

- **Global**: `13px / 1.6` line-height, monospace
- **Antialiasing**: `-webkit-font-smoothing: antialiased`, `-moz-osx-font-smoothing: grayscale`

### Text Styles

| Class | Size | Weight | Tracking | Transform | Notes |
|---|---|---|---|---|---|
| `.page-title` | 32px | 700 | -0.03em | — | line-height 1.1; `::after` blinking cursor underscore (`_`) in accent |
| `.page-subtitle` | 13px | 400 | 0.02em | — | color overlay-0 |
| `.section-title` | 14px | 600 | 0.06em | uppercase | `::before` "> " prefix in accent |
| `.section-link` | 11px | — | 0.06em | uppercase | accent color |
| `.nav-label` | 10px | 600 | 0.1em | uppercase | `::before` "// " prefix; color overlay-0 |
| `.nav-item` | 13px | — | 0.01em | — | color subtext-0; hover → fg; active → accent + weight 500 |
| `.input-label` | 10px | 600 | 0.1em | uppercase | `::before` "$ " prefix in accent; color overlay-0 |
| `.album-card-title` | 13px | 500 | 0 | — | line-height 1.3; -webkit-line-clamp: 2 |
| `.album-card-artist` | 11px | 400 | 0.02em | — | color overlay-0 |
| `.track-name` | 13px | 450 | 0.01em | — | single-line truncate |
| `.track-artist-sub` | 11px | — | — | — | color overlay-0; single-line truncate |
| `.track-album-col` | 12px | — | — | — | color overlay-0 |
| `.track-num` | 12px | — | — | — | tabular-nums; color overlay-0 |
| `.track-duration` | 12px | — | — | — | tabular-nums; color overlay-0 |
| `.player-song` | 13px | 500 | 0.01em | — | single-line truncate |
| `.player-artist-name` | 11px | — | — | — | color overlay-0; single-line truncate |
| `.player-time` | 10px | — | 0.06em | — | tabular-nums; min-width 36px |
| `.badge` | 10px | 600 | 0.08em | uppercase | inline-flex |
| `.btn-primary` | 12px | 600 | 0.08em | uppercase | — |
| `.btn-secondary` | 12px | 500 | 0.04em | — | — |
| `.btn-ghost` | 12px | — | 0.04em | — | — |
| `.mobile-nav-item` | 9px | — | 0.06em | uppercase | — |

### Typography Design Rules

- **Headings use `::after`/`::before` pseudo-element decoration** (never raw text prefixes)
- **Page titles always end with a blinking cursor underscore** (`.page-title::after`)
- **Section labels use prefix characters**: `> ` for section titles, `// ` for nav labels, `$ ` for input labels
- **All durations and track numbers use `font-variant-numeric: tabular-nums`** for alignment
- **Text truncation** uses `white-space: nowrap; overflow: hidden; text-overflow: ellipsis`

---

## Spacing & Layout

### App Shell Grid

```
.app-shell {
  display: grid;
  grid-template-columns: 280px 1fr;
  grid-template-rows: 1fr 80px;
  height: 100dvh;
  overflow: hidden;
}
```

| Area | Column | Row | Description |
|---|---|---|---|
| Sidebar | 1 | 1 | 280px, mantle bg, surface-0 border-right |
| Main content | 2 | 1 | Scrollable, crust bg |
| Player bar | 1 / -1 | 2 | 80px, crust bg, surface-0 border-top |

### CSS Custom Properties

| Variable | Value |
|---|---|
| `--sidebar-w` | 280px |
| `--player-h` | 80px |
| `--transition` | 0.2s cubic-bezier(0.16, 1, 0.3, 1) |

### Content Padding

| Region | Padding |
|---|---|
| `.main-header` | 12px 32px |
| `.main-content` | 32px 40px 40px |
| `.sidebar-header` | 24px 24px 20px |
| `.sidebar-nav` | 8px 12px 16px |
| `.sidebar-search` | margin: 16px 16px 8px; padding: 10px 14px |

---

## Component Library

### Buttons

#### `.btn-primary`
- Solid accent (`#8caaee`) background, crust (`#232634`) text
- Border: 1px solid accent
- Hover: lavender (`#babbf1`) bg + border
- Uppercase, 12px, 0.08em tracking, 600 weight
- Padding: 10px 28px

```html
<button class="btn-primary">Primary Action</button>
<button class="btn-primary" disabled>Disabled</button>
```

#### `.btn-secondary`
- Transparent bg, fg (`#c6d0f5`) text
- Border: 1px solid surface-1 (`#51576d`)
- Hover: border → accent, text → accent
- Padding: 10px 28px

```html
<button class="btn-secondary">Secondary</button>
```

#### `.btn-ghost`
- Transparent bg, overlay-0 (`#737994`) text
- Hover: text → accent, border → surface-0 (`#414559`)
- Padding: 8px 16px

```html
<button class="btn-ghost">Ghost</button>
```

#### `.player-btn-play`
- 36×36px square, fg (`#c6d0f5`) bg, crust (`#232634`) icon
- Hover: accent bg + border
- Border: 1px solid fg

---

### Inputs

#### `.input-field`
- Width: 100%, padding: 12px 16px
- Background: crust (`#232634`), border: 1px solid surface-0 (`#414559`)
- Focus: border → accent
- Error: border → red (`#e78284`)
- Placeholder: overlay-0 (`#737994`), 0.04em tracking

#### `.input-group`
- Margin-bottom: 20px (spacing wrapper)

#### `.input-label`
- Display: block, 10px, 600 weight, uppercase, 0.1em tracking
- Color: overlay-0
- `::before` "$ " prefix in accent

#### `.sidebar-search`
- Background: crust, border: surface-0
- Padding: 10px 14px, gap: 10px
- Focus-within: border → accent
- Input: 12px, fg color, 0.02em tracking

#### Password Strength Meter
3-segment bar:
- Red (`#e78284`) — weak
- Yellow (`#e5c890`) — medium
- Green (`#a6d189`) — strong
- Height: 3px, gap: 4px
- Text label: `[WARN] medium strength` / `[ERR] weak password`

#### Form States
| State | Implementation |
|---|---|
| Default | surface-0 border |
| Focus | accent border (`#8caaee`) |
| Error | red border (`#e78284`) + `[ERR]` message in red, 11px |
| Disabled | native `disabled` attribute |

---

### Badges

```html
<span class="badge badge-accent">Now Playing</span>
<span class="badge badge-green">320 kbps</span>
```

- Inline-flex, padding: 3px 10px
- 10px, 600 weight, uppercase, 0.08em tracking
- Border: 1px solid with 0.3 alpha accent
- Background: 0.05 alpha accent
- Text: accent or green respectively

---

### Navigation

#### Sidebar
- 280px wide, mantle (`#292c3c`) bg, surface-0 border-right
- `sidebar-header`: logo (32×32, accent bg, crust text) + title (14px, 600, 0.08em, uppercase)
- `sidebar-search`: compact search (see Inputs)
- `sidebar-nav`: scrollable, padding 8px 12px 16px

#### Nav Items
- Default: subtext-0 (`#a5adce`), 13px, 0.01em tracking
- Hover: fg (`#c6d0f5`), surface-0 bg at 0.4 alpha
- Active: accent (`#8caaee`), 500 weight, left-border indicator (2px accent, 60% height)
- SVG icons: 16×16, opacity 0.7 (1.0 on hover/active)
- Left-border: 2px accent, grows from 0→60% height on active

#### Nav Labels
- 10px, 600, 0.1em tracking, uppercase, overlay-0
- `::before` "// " prefix in surface-2
- Padding: 12px 12px 6px

#### Playlist Items
- `.nav-item.playlist-item`: no icon, plain text
- Grouped under `.sidebar-playlists` (border-top, padding-top)

#### Header Navigation
- `.main-header`: sticky, mantle bg, surface-0 border-bottom, padding 12px 32px
- `.nav-arrows`: 30×30 buttons, transparent bg, surface-1 border
  - Hover: border → accent, text → accent
- `.user-avatar`: 30×30, surface-0 bg, surface-1 border, accent text
  - Hover: border → accent

#### Mobile Bottom Nav
- Fixed bottom, crust bg, surface-0 border-top
- `.mobile-nav-inner`: flex, space-around
- `.mobile-nav-item`: 9px, overlay-0, uppercase, 0.06em tracking
  - Active: accent, hover: fg
  - SVG icons: 20×20
- `.mobile-menu-btn`: 32×32 hamburger, display:none (shown ≤768px)

---

### Album Cards

```html
<a class="album-card" href="#">
  <div class="album-art">
    <!-- artwork or ASCII placeholder -->
    <button class="album-play-btn"><svg>…</svg></button>
  </div>
  <div class="album-card-title">Title</div>
  <div class="album-card-artist">Artist</div>
</a>
```

- **Grid**: `grid-template-columns: repeat(auto-fill, minmax(180px, 1fr))`, gap: 24px
- **Card**: flex column, gap 10px, cursor pointer
- **Hover**: card bg → mantle; album-art border → accent
- **Album art**: aspect-ratio 1, surface-0 bg, surface-0 border, overflow hidden
- **Play button**: absolute bottom-right, 40×40, accent bg/crust icon, opacity 0 → 1 on `.album-art` hover; translateY(4px) → 0
- **Title**: -webkit-line-clamp: 2
- **Artist**: overlay-0, margin-top -6px
- **Animation**: staggered `.slide-up` (0.4s, 50ms delay increments up to 0.5s)
- **Placeholder**: monospace ASCII art in `.album-art-placeholder`

---

### Track List

```html
<div class="track-list">
  <div class="track-header"><span>#</span><span>Title</span><span>Album</span><span>Time</span></div>
  <div class="track-row playing">
    <span class="track-num"><span class="waveform-mini">…</span></span>
    <div class="track-title-col"><span class="track-name">Song</span><span class="track-artist-sub">Artist</span></div>
    <span class="track-album-col">Album</span>
    <span class="track-duration">4:20</span>
  </div>
</div>
```

- **Grid**: `48px 1fr 1fr 70px`, aligns center
- **Header**: 10px, uppercase, 0.1em tracking, overlay-0, border-bottom
- **Row padding**: 10px 16px
- **Row hover**: surface-0 bg at 0.3 alpha, left-border surface-2
- **Row playing**: accent text, accent-a5 bg, left-border accent
- **Track number**: overlay-0 (→ fg on hover, → accent on playing)
- **Column widths**: responsive — at ≤768px album column hidden, grid becomes `40px 1fr 60px`
- **Playing indicator**: replaces track number with `.waveform-mini`

---

### Player Bar

```html
<div class="player-bar">
  <div class="player-track">
    <div class="player-thumb">…</div>
    <div class="player-info">
      <div class="player-song">Song</div>
      <div class="player-artist-name">Artist</div>
    </div>
  </div>
  <div class="player-controls">
    <div class="player-buttons">…</div>
    <div class="player-progress">…</div>
  </div>
  <div class="player-right">
    <div class="volume-control">…</div>
  </div>
</div>
```

- **Layout**: 3-column grid (`1fr 2fr 1fr`), 80px height, crust bg, surface-0 border-top, z-index 20
- **Left (player-track)**: 48×48 thumbnail (surface-0 bg, surface-0 border, overflow hidden) + song/artist text
- **Center (player-controls)**: buttons (gap 20px) + progress bar row (gap 6px between rows)
- **Right (player-right)**: volume icon + slider (80px wide)
- **Responsive (≤768px)**: 2 columns (`1fr 1fr`), center + right hidden

#### Play Button
- 36×36 square, fg (`#c6d0f5`) bg, crust (`#232634`) icon
- Hover: accent bg + border
- `!important` on bg/color/border to override button reset

#### Control Buttons
- Default: overlay-0 (`#737994`)
- Hover: fg (`#c6d0f5`)
- Gap: 20px between buttons

---

### Now Playing Page

- **Background**: radial gradient (blurred album art) + semi-transparent crust overlay (rgba(35,38,52,0.7))
- **Layout**: flex row — center content + right queue panel
- **Center**: album art (180×180), track info (text-center), progress bar (max 360px), play controls
- **Queue panel**: header with badge, scrollable track list
- **Queue items**: thumbnail + title/artist + duration

---

### Progress & Volume

#### Progress Bar
- Height: 3px (expands to 5px on hover)
- Track: surface-0 (`#414559`)
- Fill: accent (`#8caaee`)
- Scrubber dot: 10×10 accent circle, scale(0) → scale(1) on hover
- Transition on fill: `width 0.1s linear`
- Time labels: 10px, tabular-nums, min-width 36px, overlay-0

#### Volume Slider
- 80px wide, 3px height
- Fill: fg (`#c6d0f5`), hover → accent
- Track: surface-0

#### Waveform Mini
- 4 bars, 2px wide, 2px gap, accent color
- Staggered `waveform` animation (0.8s, height 30%↔90%)
- Delays: 0s, 0.15s, 0.3s, 0.1s

---

### Auth Forms

- **Card**: max-width 420px, centered, bg (`#303446`) bg, surface-1 border
- **No glass/blur** — solid surfaces only
- **Header**: centered, 24px bold title (bracket-style: `[ login ]` / `[ register ]`), subtitle in overlay-0
- **Form fields**: `.input-group` + `.input-label` + `.input-field` (see Inputs)
- **Submit button**: `.btn-primary` full-width
- **Footer link**: overlay-0 text with accent link

---

## Animations

| Keyframe | Duration | Easing | Usage |
|---|---|---|---|
| `cursor-blink` | 1s step-end | — | Page title underscore |
| `fade-in` | 0.4s ease | — | Main content load (translateY 6px → 0, opacity 0 → 1) |
| `slide-up` | 0.4s ease | — | Album card stagger (translateY 12px → 0, opacity 0 → 1) |
| `waveform` | 0.8s ease-in-out infinite | — | Mini bar indicator (height 30%–90%) |
| `drift` | — | — | Blurred background slow movement |
| `np-bg-drift` | — | — | Now-playing background position pan |

### Standard Easing

```css
--transition: 0.2s cubic-bezier(0.16, 1, 0.3, 1); /* power-out ease */
```

All interactive transitions (hover, focus, active) use this easing curve with 0.2s duration.

---

## Iconography

- **Format**: Inline SVG, 24×24 viewBox
- **Stroke width**: 1.8px–2px (consistent: 1.8px for nav icons, 2px for controls)
- **Coloring**: `stroke="currentColor"` / `fill="currentColor"` (inherits from parent)
- **Size**: 16px (nav), 14px (small), 18px (player controls), 20px (mobile nav)
- **Style**: minimal, geometric, Feather-like

---

## Responsive Breakpoints

| Breakpoint | Changes |
|---|---|
| ≤1024px | Sidebar narrows to 240px |
| ≤768px | Sidebar hidden, mobile bottom nav visible, single-column layout, album column hidden in track table, player simplifies to 2 columns, content padding reduces to 24px 20px 100px, page title 24px |
| ≤430px | Album grid: 2 columns, 12px gap |

---

## CSS Reset / Base Layer

- `box-sizing: border-box` globally
- `html, body`: height 100%, crust bg, fg text, 13px monospace, antialiased
- Links inherit color, no text-decoration
- Buttons inherit font, no bg/border, cursor pointer
- Inputs inherit font/color, no bg/border/outline
- Lists: no style
- Images: block, max-width 100%
- Selection: accent bg + crust text
- Scrollbar: 6px, transparent track, surface-1 thumb (surface-2 on hover)

---

## Design Principles

1. **Solid surfaces** — no glassmorphism or backdrop blur; visible borders (1px surface-0) define component boundaries
2. **Monospace everywhere** — JetBrains Mono stack for all text, from UI labels to track metadata
3. **Prefix decorations** — pseudo-elements (`>`, `//`, `$`, `_`) distinguish label types without extra markup
4. **Hover reveals** — play buttons, scrubber dots, and border highlights appear on hover only
5. **Staggered animations** — album cards and lists animate in with cascading delays
6. **Tabular numbers** — all durations, track numbers, and time displays use monospaced numeric rendering
7. **Accent-only interactive** — only accent blue (`#8caaee`) indicates interactivity; semantic colors reserved for status
8. **Dark-first** — no light mode variant; Catppuccin Frappé is the only palette

---

## Complete Class Reference

### Layout
| Class | Description |
|---|---|
| `.app-shell` | Grid container (sidebar + main + player) |
| `.sidebar-base` | Left sidebar (280px, mantle bg) |
| `.main-area` | Right content (scrollable) |
| `.main-header` | Sticky top header |
| `.main-content` | Padded content wrapper |
| `.main-header-left` | Left side of header (arrows) |
| `.main-header-right` | Right side of header (avatar) |

### Sidebar
| Class | Description |
|---|---|
| `.sidebar-header` | Logo + title |
| `.sidebar-logo` | Square accent icon (32×32) |
| `.sidebar-title` | Uppercase app name |
| `.sidebar-search` | Search input container |
| `.sidebar-nav` | Scrollable nav list |
| `.sidebar-playlists` | Bordered playlist section |

### Navigation
| Class | Description |
|---|---|
| `.nav-section` | Nav grouping |
| `.nav-label` | Section header (`// ` prefix) |
| `.nav-item` | Nav link (left-border hover) |
| `.nav-item.active` | Active state (accent + left border) |
| `.nav-item.playlist-item` | Plain text nav item |
| `.nav-arrows` | Header back/forward buttons |
| `.user-avatar` | 30×30 avatar square |

### Typography
| Class | Description |
|---|---|
| `.page-title` | 32px bold + blinking cursor |
| `.page-subtitle` | Muted description text |
| `.section-header` | Flex row with border-bottom |
| `.section-title` | Uppercase (`> ` prefix) |
| `.section-link` | Accent link |

### Album Cards
| Class | Description |
|---|---|
| `.album-grid` | Auto-fill responsive grid |
| `.album-card` | Card with staggered animation |
| `.album-art` | Square aspect-ratio container |
| `.album-play-btn` | Hover-reveal play button |
| `.album-card-title` | 2-line clamp title |
| `.album-card-artist` | Muted artist name |
| `.album-art-placeholder` | Monospace ASCII art style |

### Track List
| Class | Description |
|---|---|
| `.track-list` | Full-width table |
| `.track-header` | Column labels (4-col grid) |
| `.track-row` | Track row (4-col grid) |
| `.track-row.playing` | Active track (accent) |
| `.track-num` | Track number column |
| `.track-title-col` | Title + artist stack |
| `.track-name` | Song title |
| `.track-artist-sub` | Artist (muted, small) |
| `.track-album-col` | Album column |
| `.track-duration` | Time column |

### Player Bar
| Class | Description |
|---|---|
| `.player-bar` | Full-width bottom strip |
| `.player-track` | Left: thumb + info |
| `.player-thumb` | 48×48 artwork |
| `.player-info` | Song + artist container |
| `.player-song` | Track title |
| `.player-artist-name` | Track artist |
| `.player-controls` | Center: buttons + progress |
| `.player-buttons` | Prev/play/next row |
| `.player-btn-play` | Center play button (36×36) |
| `.player-progress` | Progress bar row |
| `.player-time` | Time display (tabular) |
| `.player-right` | Right: volume controls |

### Now Playing
| Class | Description |
|---|---|
| `.np-back` | Back navigation arrow |
| `.np-main` | Flex container |
| `.np-center` | Center column |
| `.np-art-bg` | Blurred album art background |
| `.np-visualizer` | Canvas waveform |
| `.np-art-wrap` | Artwork wrapper |
| `.np-art` | Album art (220×220) |
| `.np-info` | Song/artist/album text |
| `.np-song` | Large song title |
| `.np-artist-link` | Clickable artist name |
| `.np-album-name` | "Album · Year" |
| `.np-controls-block` | Progress + buttons wrapped |
| `.np-progress` | Progress bar row |
| `.np-buttons` | Prev/play/next |
| `.np-btn-play` | Large play button |
| `.np-extra` | Extra controls row |
| `.np-queue` | Right queue panel |
| `.np-queue-header` | Header with badge |
| `.np-queue-list` | Scrollable list |
| `.queue-item` | Track row |
| `.q-active` | Playing track |
| `.queue-thumb` | Small thumbnail |
| `.queue-info` | Text container |
| `.queue-song` | Track title |
| `.queue-artist` | Artist name |
| `.queue-dur` | Duration |

### Buttons
| Class | Description |
|---|---|
| `.btn-primary` | Solid accent, uppercase |
| `.btn-secondary` | Outlined, uppercase |
| `.btn-ghost` | Transparent with hover |

### Inputs
| Class | Description |
|---|---|
| `.input-field` | Text input |
| `.input-group` | Spacing wrapper |
| `.input-label` | `$ ` prefix label |

### Badges
| Class | Description |
|---|---|
| `.badge` | Base pill |
| `.badge-accent` | Accent tint |
| `.badge-green` | Green tint |

### Progress
| Class | Description |
|---|---|
| `.progress-bar` | Track (3px, hover 5px) |
| `.progress-fill` | Filled portion + scrubber dot |
| `.volume-bar` | Volume track |
| `.volume-fill` | Volume level |
| `.volume-control` | Volume row |

### Mobile
| Class | Description |
|---|---|
| `.mobile-nav` | Fixed bottom bar (≤768px) |
| `.mobile-nav-inner` | Flex row |
| `.mobile-nav-item` | Nav item |
| `.mobile-menu-btn` | Hamburger button |

### Indicators
| Class | Description |
|---|---|
| `.waveform-mini` | 4-bar animated indicator |
