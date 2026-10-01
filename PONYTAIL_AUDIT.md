# Over-Engineering Audit & Fix Plan — Rekodo

**Date:** 2026-09-30
**Scope:** Full tree, not a diff.
**Stack:** Laravel 13.25 · Livewire 4.4 · Mary UI 2.9.9 · daisyUI 5.5 · PHP 8.4
**Sizing:** 10,530 lines PHP+Blade in `app/` + `resources/views/` · 9,325 lines tests · 282-line `config/livewire.php` · 191-line `routes/console.php`

> **Net: ~1,170 lines cut (11%). 0 dependencies removed, 1 relocated.**
> Every "dead code" claim below was verified by grep across `app/ resources/ database/ routes/ tests/ config/ bootstrap/` — not by inspection.

---

## Part 1 — Findings, biggest cut first

### Tier 1 — dead code with real maintenance cost

- `delete` Delete the entire upload block on the Library page (`save()`, `userAlbums()`, `$audioFiles`, `$selectedAlbumId`, `$duplicateWarnings`, `WithFileUploads`) — byte-identical copy of `UploadModal::save()`, and **no markup in the file binds any of it**; every upload button dispatches `open-upload-modal` → `UploadModal`. Delete lines 3-4, 15-16, 21-22, 25-31, 175-220. [resources/views/pages/library/index.blade.php]
- `delete` Delete `albums:backfill-artwork` — a one-shot migration tool (118 lines) for a filename scheme that was settled in v1.0, wrapped in a `realpath` disk-root guard, plus its 191-line test. [routes/console.php:74-191] — **DECISION: keep, but extract (Step 13).**
- `native` Delete `CommandPalette` (123 PHP + 126 Blade) and adopt `<x-mary-spotlight>`, which already ships ⌘K toggle, escape-to-close, `$focus` arrow nav, the modal box + transitions, the `<kbd>esc</kbd>`, and the empty state — all currently re-implemented. Requires writing the `Mary\UI\Commands\*` provider that `config/mary.php` already points at. [app/Livewire/CommandPalette.php, resources/views/livewire/command-palette.blade.php]
- `shrink` Delete the hand-rolled directory browser: 65 lines of Alpine `fetch(url, {headers})` → Livewire properties + `wire:loading`; 106 lines of hand-built modal (backdrop, panel, header, 6 `x-transition:*`) → `<x-mary-modal>`, which **the same file already uses twice**; 2 inline raw `<svg>` → `<x-mary-icon>`. [resources/views/pages/settings/⚡linked-folders.blade.php:275-339, 552-657]
- `delete` Delete the `user_invitations` create + drop migration pair — 62 lines, and the drop's `down()` is a verbatim copy of the create's `up()`. No model, factory, route, or test references the table. [database/migrations/2026_03_08_230033_*.php, 2026_03_30_065513_*.php]
- `shrink` Collapse the queue and lyrics slide-over panels in the player — their backdrop, class string (`surface-chrome … lg:bottom-[72px]`), 6 `x-transition:*` directives, and `<header>` are byte-identical; only the state variable and body differ. One `<x-ui.side-panel>`. [resources/views/livewire/audio-player.blade.php:374-548]
- `shrink` Collapse `layouts/guest.blade.php` and `layouts/auth/simple.blade.php` — byte-identical modulo indentation and `@yield` vs `{{ $slot }}`. Both are live (4 pages use `@extends`, 3 use `<x-layouts::auth>`). Keep one. [resources/views/layouts/]
- `shrink` Merge the two pagination views — 62 shared non-blank lines; both are live (`paginate()` → `rekodo`, `simplePaginate()` on album-show → `simple-rekodo`). One view branching on `$paginator instanceof \Illuminate\Pagination\Paginator`. [resources/views/vendor/livewire/]
- `shrink` Replace `segmented-control` (48 lines) with `<x-mary-tabs>` + `@entangle` — Mary's `Tabs` uses `x-show`, not `<template x-if>`, so panels stay morphed. Also removes a **full server round-trip per tab click**. [resources/views/components/ui/segmented-control.blade.php]
- `shrink` Delete `auth-header.blade.php` (8 lines) — a duplicate of `auth-heading.blade.php` differing only in the prop name (`description` vs `subtitle`) and one class; 3 call sites. [resources/views/components/]
- `delete` Delete `PlaylistTrackController::store()` + its route — **no view posts to it**; the add-track UI is `$dispatch('open-add-to-playlist')` → `AddToPlaylistModal`, whose `add()` is byte-identical. `destroy()` stays (wired from `track-row.blade.php:182`). [app/Http/Controllers/PlaylistTrackController.php:16-38, routes/web.php:40]
- `shrink` Delete 22 `<template x-if>` icon pairs → `<x-mary-swap>`, which is a hidden checkbox + daisyUI `swap` with **zero JS** and no `<template x-if>` morph hazard. Pairs: 3× play/pause, 3× heart, 3× speaker, eye/eye-slash, document/check, cover/glyph. [resources/views/livewire/audio-player.blade.php, ui/track-row.blade.php]
- `yagni` Delete `StorePlaylistRequest` — `diff` against `UpdatePlaylistRequest` is **one line**: the class name. Same rules, same 3 `messages()`. One `PlaylistRequest`. [app/Http/Requests/]
- `yagni` Delete `ArtworkSearch::itunes()` and `::deezer()` — one-line delegations to `search()` that exist *only* so `ArtworkModal:101` can do `(new ArtworkSearch)->{$provider}(...)` on a client-supplied property (already whitelisted 8 lines earlier). Make `search()` public. [app/Services/ArtworkSearch.php:36-47]
- `native` Replace the `scandir()` + loop + `usort()` in `BrowseDirectoriesController` with Symfony `Finder` — already a dependency, already imported in `CssFoundationTest`. [app/Http/Controllers/BrowseDirectoriesController.php:30-55]
- `shrink` Delete `authorizePlaylist()` from both controllers — identical bodies, 9 call sites; `TrackPolicy` already exists for this shape. Move to the empty base `Controller` or add `PlaylistPolicy`. [app/Http/Controllers/]
- `delete` Delete `resources/views/components/ui/track-skeleton.blade.php` and `components/placeholder-pattern.blade.php` — **0 references each** (14 + 12 lines). `placeholder-pattern` is a Breeze leftover. [resources/views/components/]
- `delete` Delete 4 zero-caller members: `Track::scopeAvailable()` (0 callers; only `->unavailable()` is used), `Track::plays()` (0 callers; all `TrackPlay` access is static), `ProfileValidationRules::avatarRules()` (0 callers; duplicated inline as `['nullable','image','max:2048']` in 2 files), `AudioPlayer::currentTrack()` (the view reads the `$currentTrackId` property, never this computed). [app/Models/Track.php, app/Concerns/, app/Livewire/AudioPlayer.php:399]
- `native` Replace 34 hand-rolled daisyUI primitives with the Mary wrappers the project already pays for: 10 `.badge` (3 uses of `x-mary-badge` exist), 7 `.alert` (`x-mary-alert`), 7 `.select` (`x-mary-select`), 5 `.input`, 4 `.loading` (`x-mary-loading`), 1 `.progress`, 2 `.kbd`. [resources/views/**]
- `native` Collapse `ArtworkSearch::cacheKey()`'s `Cache::has()` + `Cache::get()` into one `Cache::get()` — `.env:46` sets `CACHE_STORE=database`, so every hit is **2 SQL queries**; `get()` already returns `null` on a miss. Same in `FetchAlbumArtwork:88`. [app/Services/ArtworkSearch.php:67-69]
- `delete` Delete `AudioValidationRules` trait (18 lines, 1 real user) — inline the rule into `UploadModal::save()`. Cascades from the Library-page deletion above. [app/Concerns/AudioValidationRules.php]
- `native` Delete `Theme::values()` and `Accent::values()` (~10 lines of `array_map(fn ($t) => $t->value, self::cases())`) → `Rule::enum(Theme::class)`, which Laravel 13 ships and which returns a case instance. [app/Enums/]
- `native` Delete the inert `ini_set('default_socket_timeout')` machinery in the health check — `.env:23` is sqlite (no socket) and `DB::getPdo()` on a resolved connection does no I/O. Also the unbound `catch (\Throwable $e)`. [app/Http/Controllers/HealthCheckController.php:11-32]
- `native` Collapse the 19-line `$_SERVER['HOME']` → `posix_getpwuid()` → `/` fallback chain to one `getenv('HOME') ?: '/'`. [app/Http/Controllers/BrowseDirectoriesController.php:64-82]
- `delete` Delete 4 redundant `str_replace('\\','/')` path normalisations in `ScanLinkedFolder:85`, `RelinkTrackModal:59`, and `linked-folders:86,192` — every one feeds a `Track`/`LinkedFolder` write and **both models already normalise in an `Attribute` mutator**. [app/]
- `delete` Delete the 2 `unset()` calls in `ScanLinkedFolder:137,140` — refcounting frees both on reassignment. [app/Jobs/ScanLinkedFolder.php]
- `delete` Delete the `inspire` command — Laravel skeleton boilerplate, **0 references** repo-wide (no test, script, CI, or doc). [routes/console.php:11-13]
- `delete` Delete `config/mary.php`'s `components.spotlight.class` pointing at `App\Support\Spotlight`, which **does not exist** — and `vendor/robsontenorio/mary/routes/web.php:14` registers `GET /mary/spotlight` behind `web` only, **no auth**. A public route to a fatal. (Either implement the class as part of the Spotlight cut, or drop the key.) [config/mary.php:39-43]
- `delete` Delete the second health route `health: '/up'` in `bootstrap/app.php:13` — every consumer (compose, install.sh, install.ps1, docker/, 10 tests) uses `/healthz`. [bootstrap/app.php:13]
- `delete` Delete both middleware aliases in `bootstrap/app.php:23-26` — `require.no-users` and `force.password.change` are never applied to any route; both classes are reached only via `appendToGroup('web', …)`. [bootstrap/app.php]
- `delete` Delete `setScanning()` from the linked-folders page — a public browser-callable method whose own docblock says "used in testing". [resources/views/pages/settings/⚡linked-folders.blade.php:224-230]
- `shrink` Collapse the add-track-to-playlist block (exists-check + `max('position')` + `attach`) duplicated byte-for-byte in 2 files → `Playlist::addTrack(Track $track)`. Also lets `playlistTracks()` (2 callers, defined only for this lookup) go. [app/Http/Controllers/PlaylistTrackController.php, app/Livewire/AddToPlaylistModal.php]
- `shrink` Consolidate the artwork-with-glyph-fallback + `onerror="this.remove()"` block, repeated across **10 files** (5 with the same 3-line comment), into `<x-ui.artwork>`. Same for `<x-ui.duration>` (3 sites, same `str_pad` expression) and avatar-or-initials (3 sites → Mary's `x-mary-avatar`). [resources/views/**]
- `shrink` Consolidate the Play/Shuffle button pair (identical `@js($ids)` dispatch, identical classes) duplicated in 3 files → `<x-ui.play-controls>`. Same for the album-shelf loop (3× in home), the album grid (2× verbatim in `library/index`), the playlist create/edit forms, the settings nav (duplicates `x-ui.nav-item`), and `settings-heading.blade.php` (included 6×, already rendered by `settings/layout.blade.php`). [resources/views/pages/]
- `shrink` Collapse `action-message.blade.php`'s 14-line Alpine `x-show`+`setTimeout` toast → `$this->success()`; `x-mary-toast` is already mounted in the sidebar and `Mary\Traits\Toast` is already imported. [resources/views/components/action-message.blade.php]
- `shrink` Collapse `recentAlbums()` onto the existing `albumCard()` helper it re-spells, and the 3 near-identical `Album::query()` shelf chains onto one `scopeForShelf()`. [resources/views/pages/home/⚡index.blade.php]
- `delete` Tighten visibility: `FetchAlbumArtwork::missKey()` and `ArtworkSearch::cacheKey()` are `public` with no external production caller → `private static`. [app/]
- `native` Delete the hand-rolled `wire:keydown.arrow-left/right` roving-tabindex in the segmented control — Mary's `Tabs` handles this, and the current version costs a server round-trip. [resources/views/components/ui/segmented-control.blade.php]
- `native` Delete the hand-rolled HTML5 drag-reorder in the player queue (4 `x-on:drag*` handlers + `dragIdx` state) → Livewire 4's `wire:sort`. [resources/views/livewire/audio-player.blade.php:416-421]

### Rejected — considered and NOT cut

735 lines of meta-tests assert on the *text inside* `.css`/`.sh`/`.ps1`/`.yaml` files and on stdlib behaviour (`preg_match('/^APP_KEY=base64:/m')`, `touch()`). `CssFoundationTest` guards against Flux CSS selectors, but Flux was removed back in v1.1. **DECISION: keep all — cheap insurance for the cross-platform install path.** [tests/Feature/{CssFoundation,NativeFoundation,BashInstallScript,PowerShellInstallScript,DockerConfiguration}Test.php, tests/Unit/ExampleTest.php]

**Email verification is inert** — `User` does not implement `MustVerifyEmail` (import commented at `app/Models/User.php:5`), `config/fortify.php` does not enable `Features::emailVerification()`, and `EnsureEmailIsVerified` passes anyone without the contract. So `'verified'` on `routes/web.php:25` and `routes/settings.php:14` is a no-op, and the profile resend-verification block is unreachable. **DECISION: leave as-is.** [app/Models/User.php, config/fortify.php, resources/views/pages/settings/⚡profile.blade.php:83-104]

---

## Part 2 — Explicitly do NOT cut

- **`audioPlayer` Alpine, 466 lines in `resources/js/app.js`** — `grep -ril audio vendor/robsontenorio/mary/src/` returns zero. Mary and Livewire ship no audio player. Media Session API, LRC parsing, and the play-count rule are irreducible. This is the one piece of JS in the repo that genuinely has to exist.
- **`x-ui/scrubber`, `media-shelf`, `track-row`, `layouts/app/sidebar`** — buying something Mary lacks (slider overlays, scroll-snap, hover-reveal rows, a fixed Apple-Music-style shell that `$persist` would fight).
- **All 21 enum cases across 5 enums** — verified every one. `Accent::{Pink,Orange,Mono}` look unreferenced but are reached via `Accent::cases()` in the picker. `Theme`/`Accent` look extractable but at 2 enums a shared base is *more* over-engineering.
- **All 7 factories, all 14 `AudioPlayer` methods, `config/rekodo.php`** — every key and case read.
- **`AppServiceProvider::useBuiltAssetsWhenDevServerIsUnreachable()`** — 58 lines but a deliberate, tested Cloudflare-tunnel workaround, dev-only, early-returns on `!Vite::isRunningHot()`.
- **The 44 `x-transition*` directives** — `wire:transition` is the View Transitions API for DOM *morphing*, not an Alpine enter/leave replacement. Swapping them would break the design.
- **`partials/html-attributes.blade.php`** — replacing Mary's `x-mary-theme-toggle` localStorage with server-rendered `data-theme`/`data-accent` is the better call for a per-user, DB-backed theme.
- **`resources/views/vendor/livewire/rekodo.blade.php`** — custom pagination is correct; the comment at :16-19 explains why it exists rather than using `x-mary-pagination`. Only the *duplication* with `simple-rekodo.blade.php` is cut (Step 11.3).

---

## Part 3 — Step-by-Step Fix Plan

**14 steps in 8 waves, each independently shippable.** Rule for every step: run the listed tests at the end, and `vendor/bin/sail bin pint --dirty --format agent` before finishing.

### Wave 1 — Zero-risk deletions

**Step 1 — dead members, ~81 lines. No test edits.**

1. `rm resources/views/components/ui/track-skeleton.blade.php` and `resources/views/components/placeholder-pattern.blade.php` (0 refs each; the latter is a Breeze leftover).
2. `app/Models/Track.php` — delete `scopeAvailable()` (:127-133) and `plays()` (:95-101). Grep confirms only `->unavailable()` is used; all `TrackPlay` access is static.
3. `app/Concerns/ProfileValidationRules.php` — delete `avatarRules()` (:34-42). The same `['nullable','image','max:2048']` is already inlined in `settings/⚡profile.blade.php:23` and `ArtworkModal.php:52`.
4. `app/Livewire/AudioPlayer.php` — delete `currentTrack()` (:399-410). The view reads the `$currentTrackId` property, never this computed. Remove the 2 assertions at `AudioPlayerTest.php:200-218`.
5. `routes/console.php:11-13` — delete the `inspire` command.
6. `config/mary.php:39-43` — delete the `components.spotlight.class` key. ⚠️ **See Step 8 — this is reversed there.** Doing it now removes an **unauthenticated** `GET /mary/spotlight` route that fatals.
7. `bootstrap/app.php` — delete `health: '/up'` (:13, every consumer uses `/healthz`) and both middleware aliases (:23-26, never applied to any route; both classes are reached only via `appendToGroup('web', …)`) plus their two `use` imports.
8. `resources/views/pages/settings/⚡linked-folders.blade.php:224-230` — delete `setScanning()`; have `LinkedFoldersSettingsTest.php:371` use `->set('scanningFolderIds', [...])`.
9. `app/Jobs/FetchAlbumArtwork.php:133` and `app/Services/ArtworkSearch.php:196` — `public static` → `private static` (`missKey`, `cacheKey`). All callers are internal.

**Step 2 — delete the dead Library-page upload block, ~83 lines.**

- `resources/views/pages/library/index.blade.php` — delete lines 3-4, 15-16, 21-22, 25-31, 175-220. That removes `save()`, `userAlbums()`, `$audioFiles`, `$selectedAlbumId`, `$duplicateWarnings`, and the `use AudioValidationRules` / `use WithFileUploads` imports. Nothing in the file's markup binds any of it — all four upload buttons dispatch `open-upload-modal` → `UploadModal`.
- `rm app/Concerns/AudioValidationRules.php` — one caller left after this. Inline `'audioFiles.*' => 'file|mimes:mp3,wav,flac,m4a,aac,ogg|max:102400'` into `UploadModal::save()`.
- `tests/Feature/Library/TrackUploadTest.php` — retarget the 7 `->call('save')` blocks from `pages::library.index` to `livewire:upload-modal`. `UploadModalTest.php:41-93` already covers create-tracks, dispatch-metadata-job, store-session-ids, and duplicate warnings for the identical path.

Tests: `Library/*`, `UploadModalTest`.

### Wave 2 — Collapse the playlist duplication

**Step 3 — one auth path, one request, one add-track helper, ~89 lines.**

1. `app/Models/Playlist.php` — add `addTrack(Track $track): void` holding the exists-check + `max('position')` + `attach` block currently byte-identical in `PlaylistTrackController::store()` and `AddToPlaylistModal::add()`. Delete the `playlistTracks()` relation (2 callers, defined solely for that lookup).
2. `app/Http/Controllers/PlaylistTrackController.php` — delete `store()` (:16-38) and its route (`routes/web.php:40`). No view posts to it; the UI is `$dispatch('open-add-to-playlist')` → `AddToPlaylistModal`. **Keep `destroy()`** — it is genuinely wired from `track-row.blade.php:182`.
3. Move `authorizePlaylist()` (identical in both controllers, 9 call sites) into the currently-empty base `app/Http/Controllers/Controller.php`, or add a `PlaylistPolicy` to match the existing `TrackPolicy`.
4. Merge `StorePlaylistRequest` + `UpdatePlaylistRequest` into one `PlaylistRequest` — `diff` shows a single differing line (the class name), and `messages()` is identical too. Have `smart-editor.blade.php:118-119` compose from `PlaylistRequest::rules()` instead of its third copy of the same name/description rules.

Tests: `PlaylistControllerTest`, `PlaylistTrackControllerTest` (retarget to `AddToPlaylistModal`), `SmartPlaylistTest:134`, `PlaylistModelTest`.

### Wave 3 — Mary UI alignment, mechanical

**Step 4 — 34 hand-rolled daisyUI primitives → the Mary wrappers already installed. ~0 net lines.**

`Spotlight Kbd Avatar ListItem Toast Alert Badge Select Progress Loading Checkbox Card Tabs Tab Swap Range Table` all ship in `vendor/robsontenorio/mary/src/View/Components/`. Sites: 10 `.badge`, 7 `.alert`, 7 `.select`, 5 `.input`, 4 `.loading`, 1 `.progress`, 2 `.kbd`. Near-zero net line change, but it removes design-system drift against a library the project already depends on.

**Step 5 — 22 `<template x-if>` icon pairs → `<x-mary-swap>`, ~40 lines.**

`Swap.php` is a hidden checkbox + daisyUI `swap` with **zero JavaScript**. Pairs: 3× play/pause, 3× heart, 3× speaker, eye/eye-slash, document/check, cover/glyph. This also removes the `<template x-if>` class that `segmented-control.blade.php:16-19` blames for Livewire not morphing. The volume 3-state case is genuinely custom — leave it.

**Step 6 — `segmented-control.blade.php` (48 lines) → `<x-mary-tabs>` + `@entangle`, ~45 lines.**

Mary's `Tabs` uses `x-show`, not `<template x-if>`, so panels stay in the DOM and keep morphing. Delete the 95-line `@if` ladder in `library/index.blade.php:346-440` and the `:current` prop in `artwork-modal.blade.php:28-33`.

> **Side benefit: this removes a full server round-trip per tab click.** The current `wire:click="$set(...)"` is synchronous.

- Update `tests/Feature/Library/LibraryPaginationTest.php:192-198` — the test *"drives the segmented control from Livewire rather than Alpine"* asserts the literal string `wire:click="$set('activeTab', 'albums')"`. Rewrite it to assert the tab renders and that the panel is present in the DOM.

Tests: `LibraryPaginationTest`, `LibraryAlbumsPaginationTest`, `LibrarySearchTest`, `ArtworkModalTest`.

### Wave 4 — The two large rewrites

**Step 7 — audio player internals, ~100 lines. Leave `resources/js/app.js` alone.**

The 466-line `Alpine.data('audioPlayer')` is irreducible — see "Do NOT cut". Only the Blade is fat:

1. Extract `<x-ui.side-panel :open="$open" :title="…">` from the queue/lyrics pair (`:374-548`) — their backdrop, `surface-chrome … lg:bottom-[72px]` class string, 6 `x-transition:*` directives, and `<header>` are byte-identical; only the state variable and body differ. 126 → ~60.
2. Replace the replace-queue modal (`:551-578`, hand-built `div.modal` + backdrop + 4 transitions) with `<x-mary-modal>` — the project already uses it 16 times. 28 → 8.
3. Give `x-ui/scrubber.blade.php` a `:buffered="false"` prop and reuse it for the volume slider (`:165-177`) instead of a third copy of the three overlay divs.
4. Delete the hand-rolled HTML5 drag-reorder (`:416-421`, 4 `x-on:drag*` handlers + `dragIdx` state) → **Livewire 4's `wire:sort`** (`SupportWireSort`).

Tests: `AudioPlayer/AudioPlayerTest`, `MobileTouchPlaybackTest`.

**Step 8 — `CommandPalette` → `<x-mary-spotlight>`, ~200 lines.**

The single biggest cut. Mary ships, out of the box, the ⌘K/ctrl-K toggle, escape-to-close, `$focus` arrow nav, enter-to-choose, the modal box + transitions, the `<kbd>esc</kbd>`, focus management, and the no-results state — all re-implemented in the current 126-line Blade and its 123-line component. `$focus` ships inside Livewire 4's bundle (`livewire.esm.js:5611`), so **no npm dependency**.

1. ⚠️ **This reverses Step 1.6.** Instead of deleting `config/mary.php`'s `components.spotlight.class`, implement the class it points at: a `Mary\UI\Commands\*` provider reusing the existing query logic (tracks, albums, artists, playlists, plus the nav/URL items and the play action).
2. Keep the `route('mary.spotlight')` registration, and **add `auth`** to the middleware on Mary's route so it stops being public.
3. Keep the grouped headers by rendering them in the component's Blade slot (`x-mary-spotlight` slots are Blade).
4. Port the search predicates into `Track::scopeSearch()` / `Album::scopeSearch()` — `CommandPalette.php:29` and `ResolveSmartPlaylist.php:106` currently hand-roll the same `str_replace(['%','_'], '', $term)` wildcard-strip with near-identical comments about SQLite's missing default ESCAPE.
5. Rewrite `tests/Feature/CommandPaletteTest.php` (123 lines) against the provider's `search()` and the shell's render. Existing assertions on `array_keys($results) => ['Tracks','Albums','Playlists']` and label values should survive unchanged if the provider returns the same shape.

### Wave 5 — The second large rewrite

**Step 9 — linked-folders directory browser → Livewire + Mary, ~140 lines.**

`settings/⚡linked-folders.blade.php` is 660 lines, of which 171 is a hand-rolled directory picker.

1. Convert `fetchDirectories()` / `navigateTo()` / `goUp()` / `selectFolder()` (`:275-339`, 65 lines of Alpine `fetch` with manual headers, error, and loading state, plus a `$wire.set()` bridge) into Livewire methods with `$directories` / `$currentPath` / `$parentPath` / `$loading` properties. `wire:model` + `wire:loading` + `wire:submit` replace the whole block.
2. Replace the 106-line hand-built modal at `:552-657` with `<x-mary-modal wire:model="browserOpen">`. **The same file already uses `<x-mary-modal>` twice** at `:461` and `:498`.
3. Delete the two inline raw `<svg>` chevron/folder paths (`:592`, `:627`) → `<x-mary-icon>`.

Tests: `Settings/LinkedFoldersSettingsTest`, `BrowseDirectoriesTest`.

### Wave 6 — Deduplication

**Step 10 — 12 new micro-components, ~217 lines.** Each is copy-paste verified across the given files:

| New | Replaces | Sites |
|---|---|---|
| `<x-ui.artwork>` | artwork + glyph-fallback + `onerror="this.remove()"` | **10** (5 repeat the same 3-line comment verbatim) |
| `<x-ui.duration>` | the same `str_pad`/`intdiv` expression | 3 |
| `<x-ui.avatar>` | `img.avatar` else `bg-primary/12` + initials | 3 (Mary has `x-mary-avatar`) |
| `<x-ui.play-controls>` | identical `@js($ids)` Play/Shuffle dispatch | 3 |
| `<x-ui.album-shelf>` | near-identical shelf blocks | 3 (`home/⚡index`) |
| `<x-ui.album-grid>` | verbatim grid markup | 2 (`library/index:296`, `:424`) |
| `<x-ui.playlist-form>` | near-identical create/edit forms | 2 |
| `<x-ui.nav-item>` (reuse) | settings nav duplicates the existing one | 1 |
| move into `settings/layout.blade.php` | `settings-heading.blade.php` partial | 6 includes |
| `$this->success()` | `action-message.blade.php` Alpine toast — `x-mary-toast` already mounted in sidebar, `Mary\Traits\Toast` already imported | 2 |
| `->map(fn ($a) => $this->albumCard($a))` | `recentAlbums()` re-spells `albumCard()`'s shape | 1 |
| `Album::scopeForShelf()` | 3 near-identical `Album::query()` chains | 3 |

**Step 11 — auth layouts + pagination, ~100 lines.**

1. `layouts/guest.blade.php` and `layouts/auth/simple.blade.php` are byte-identical modulo indentation and `@yield` vs `{{ $slot }}`. Both are live. Keep one, convert the 4 `@extends` pages to `<x-layouts::auth>`.
2. Delete `components/auth-header.blade.php` (8 lines) — duplicate of `auth-heading.blade.php` differing only in prop name; 3 call sites. Fold the session-status block (copy-pasted 3×) in as an optional `status` prop.
3. `resources/views/vendor/livewire/rekodo.blade.php` and `simple-rekodo.blade.php` share **62 non-blank lines** — the `$step`/`$idle`/`$off` triples, the entire "Showing X to Y of Z" caption, and both button pairs. Both are live (`paginate()` ×3 in `library/index`; `simplePaginate(50)` in `album-show:35`). Merge into one view branching on `$paginator instanceof \Illuminate\Pagination\Paginator`. Keep the comment at `rekodo:16-19`.

> No tests reference `auth-header`, `auth-heading`, or `layouts.guest` — Steps 11.1/11.2 are test-free. Step 11.3 is covered by the three `Library*PaginationTest` files.

### Wave 7 — Use what the framework already ships

**Step 12 — native/stdlib substitutions, ~67 lines.**

1. `BrowseDirectoriesController:30-55` — replace 26 lines of `scandir()` + loop + `usort()` with Symfony `Finder`, already a dependency and already imported in `CssFoundationTest`. Preserve the exact JSON shape so `BrowseDirectoriesTest` passes untouched.
2. `BrowseDirectoriesController:64-82` — collapse the 19-line `$_SERVER['HOME']` → `posix_getpwuid()` → `/` fallback chain to `getenv('HOME') ?: '/'`.
3. `ArtworkSearch.php:67-69` and `FetchAlbumArtwork.php:88` — `Cache::has()` + `Cache::get()` is **2 SQL queries per hit** under `.env:46`'s `CACHE_STORE=database`. `Cache::get()` already returns `null` on a miss. Leave the `Cache::add()` calls at `FetchAlbumArtwork:148` and `ExtractAudioMetadata:178` — those are atomic and correct.
4. `Enums/Theme.php:26-30` and `Enums/Accent.php:28-32` — delete both `values()` methods; use `Rule::enum(Theme::class)` in `appearance.blade.php:42-43`. Returns a case instance, which is what the view wants anyway.
5. `HealthCheckController:11-32` — delete `DB_TIMEOUT_SECONDS`, both `ini_set('default_socket_timeout')` calls, and the `finally` block. `.env:23` is sqlite (no socket) and `DB::getPdo()` on a resolved connection does no I/O, so it measures nothing. Change `catch (\Throwable $e)` to `catch (\Throwable)`.
6. Delete 4 redundant `str_replace('\\','/')` calls in `ScanLinkedFolder:85`, `RelinkTrackModal:59`, `linked-folders:86,192` — all four feed writes that `Track`/`LinkedFolder` already normalise in an `Attribute` mutator. `TrackPathNormalisationTest` already proves the mutator path.
7. Delete `unset($chunk)` / `unset($files)` in `ScanLinkedFolder:137,140` — refcounting frees both on reassignment.
8. `ArtworkSearch::itunes()` / `::deezer()` — delete (~14 lines). They exist only so `ArtworkModal:101` can do `(new ArtworkSearch)->{$provider}(...)` on a client-supplied property that is already whitelisted 8 lines earlier. Make `search()` public and call it directly.

### Wave 8 — Housekeeping

**Step 13 — extract the backfill command (net 0, per decision).**

Move `routes/console.php:74-191` into `app/Console/Commands/BackfillArtwork.php` as a real class, so `console.php` stops being a 191-line closure file (→ ~73 lines). Register via `bootstrap/app.php`'s `withCommands()`. **Keep the `realpath` disk-root guard and all 191 lines of `BackfillArtworkCommandTest` as-is.**

**Step 14 — `composer.json`.**

Move `laravel/tinker` from `require` to `require-dev` — it is a REPL, referenced only from `bootstrap/cache/`. **0 packages removed**; `intervention/image` (`Album::storeCoverArt`) and `james-heinrich/getid3` (`ExtractAudioMetadata`) are both load-bearing.

---

## Final tally

| Wave | Steps | Lines cut |
|---|---|---|
| 1. Dead code | 1–2 | ~164 |
| 2. Playlists | 3 | ~89 |
| 3. Mary alignment | 4–6 | ~100 |
| 4. Player + palette | 7–8 | ~300 |
| 5. Directory browser | 9 | ~140 |
| 6. Deduplication | 10–11 | ~317 |
| 7. Native/stdlib | 12 | ~67 |
| 8. Housekeeping | 13–14 | 0 |
| | **Total** | **~1,170 of 10,530 (11%)** |

**Dependencies removable: 0.** 1 relocated (`laravel/tinker` → `require-dev`).

---

## Sequencing notes

1. **Step 8 reverses Step 1.6.** Skip deleting `config/mary.php:39-43` until the Spotlight provider lands, then add `auth` to Mary's spotlight route. As things stand it is a **public route to a fatal**.
2. **Step 6 is the only change that makes the app faster** rather than just smaller — it removes a synchronous server round-trip per tab click.
3. **Steps 4, 5, 7, 10 are mostly test-free** — they touch Blade markup that no test asserts on. Steps 2, 3, 6, 8, 9, 11.3 require test edits, all mapped above.

## Known follow-ups (out of scope, flagged not cut)

- **Playlists are the only resource still on HTTP controllers + plain Blade.** `resources/views/playlists/*` (4 files, 199 lines) already use `wire:navigate`, `$dispatch(...)`, and `mary-toast` — pseudo-Livewire. `create`/`edit` could collapse into one Livewire form component and take the merged `PlaylistRequest` with them. If keeping the controller, Step 3's consolidation is what keeps the three competing styles honest.
- `config/livewire.php:135` hand-rolls `throttle:5,1` where the app already uses named `RateLimiter::for()` limiters twice (`ArtworkModal.php:100`, `FortifyServiceProvider.php:62,66`).
- `config/livewire.php:133` `max:102400` duplicates the same constant; `:250` `release_token => 'a'` is a leftover placeholder.
- The audio extension list is hardcoded in 2 places (`ScanLinkedFolder::AUDIO_EXTENSIONS` and the validation rule) — `config/rekodo.php` is the natural single source.

---

*Generated 2026-09-30. Re-verify each grep before acting — this audit is a snapshot.*