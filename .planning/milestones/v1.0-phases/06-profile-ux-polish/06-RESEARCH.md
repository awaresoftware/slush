# Phase 6: Profile UX Polish - Research

**Researched:** 2026-03-12
**Domain:** Laravel/Livewire — Blade component editing, Eloquent withCount, Flux UI menu items
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions
- Add "Profile" link to the user menu dropdown (both `desktop-user-menu.blade.php` and mobile menu in `sidebar.blade.php`)
- NOT in the sidebar nav — keep sidebar for content areas (Dashboard, Library, Playlists)
- Position: above Settings, below user info section (order: Profile → Settings → Logout)
- Icon: Heroicon `user`
- Label: "Profile" (not "My Profile" or "View Profile")
- Links to `route('profile.show', auth()->user())`
- Show three stat cards: Tracks, Albums, Playlists
- All three stats visible to any logged-in user viewing the profile (no privacy distinction)
- 3-column grid layout (sm:grid-cols-3), stacking on mobile — extends existing grid pattern
- Stats are static display only — not clickable, no links to Library/Playlists
- Counts come from actual database queries on the profile user's data (not hardcoded)

### Claude's Discretion
- Exact Eloquent query approach for counts (withCount, separate queries, etc.)
- Whether to eager-load counts or compute in the component
- Mobile responsive breakpoints for the 3-column grid

### Deferred Ideas (OUT OF SCOPE)
- None — discussion stayed within phase scope
</user_constraints>

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| PROF-04 | User can view other users' profiles | Closes two integration gaps: (1) nav entry point to own profile, (2) accurate stat counts on profile page |
</phase_requirements>

## Summary

Phase 6 is gap closure work targeting two integration defects identified in the v1.0 milestone audit. Both gaps are small, focused changes to existing files — no new routes, models, or services needed.

Gap 1 is a navigation addition: insert a "Profile" `flux:menu.item` into both desktop and mobile user dropdown menus, positioning it above Settings in each. The existing `flux:menu.radio.group` + `flux:menu.item` pattern is already established in both files.

Gap 2 is replacing a hardcoded `0` with live database counts in `pages/profile/⚡show.blade.php`. The component currently has one stat card (Tracks) with a literal `0`. The fix expands this to three cards (Tracks, Albums, Playlists) backed by Eloquent `withCount` eager-loaded on the `$user` in the component's `mount()`. All relationships exist on the User model indirectly — Track and Album both have `user_id`, Playlist has `user_id`.

**Primary recommendation:** Use `withCount` in the Livewire component's `mount()` to load all three counts in one query, then reference `$user->tracks_count`, `$user->albums_count`, `$user->playlists_count` in the template.

## Standard Stack

### Core (already in project)

| Component | Version | Purpose | Notes |
|-----------|---------|---------|-------|
| Livewire | v4 | Livewire page component (`⚡show.blade.php`) | SFC format, `#[Layout]` attribute |
| Flux UI Free | v2 | `flux:menu.item`, `flux:heading`, `flux:text` | Use existing free components |
| Tailwind CSS | v4 | Grid/spacing utilities | Follow existing `rounded-lg bg-zinc-800 p-4` pattern |
| Eloquent `withCount` | Laravel 12 | Single-query count loading | Returns `tracks_count`, `albums_count`, `playlists_count` |

### No new installations needed

All required tools are already installed. This phase is pure code editing.

## Architecture Patterns

### Pattern 1: Flux Menu Item (navigation link)

**What:** `flux:menu.item` with `:href`, `icon`, and `wire:navigate` — already used for Settings in both menus.

**Exact existing pattern (from `desktop-user-menu.blade.php`, line 22):**
```blade
<flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
    {{ __('Settings') }}
</flux:menu.item>
```

**New Profile item (to insert ABOVE Settings):**
```blade
<flux:menu.item :href="route('profile.show', auth()->user())" icon="user" wire:navigate>
    {{ __('Profile') }}
</flux:menu.item>
```

Both desktop and mobile menus use the same `flux:menu.item` component. In the desktop menu, the Settings item is inside a single `flux:menu.radio.group`. In the mobile menu, Settings has its own `flux:menu.radio.group` with a separator above and below.

### Pattern 2: Eloquent withCount for Profile Stats

**What:** Load relationship counts on the `$user` in `mount()` using `withCount()`. This issues one SQL query, adding virtual `*_count` attributes.

**Current `mount()` method:**
```php
public function mount(User $user): void
{
    $this->user = $user;
}
```

**Recommended approach — reload user with counts:**
```php
public function mount(User $user): void
{
    $this->user = User::withCount(['tracks', 'albums', 'playlists'])->findOrFail($user->id);
}
```

This requires adding `tracks()`, `albums()`, and `playlists()` `HasMany` relationships to the User model. Currently User has no relationship methods — all relationships are defined on Track/Album/Playlist pointing back to User.

**Alternative — separate count queries in template:**
```blade
{{ $user->tracks()->count() }}
```
This triggers one query per stat card (3 extra queries). Acceptable for this small page but not the cleaner approach.

**Recommended:** Add relationships to User model + `withCount` in mount.

### Pattern 3: Three-column stat grid

**Existing single-card grid (line 42 of `⚡show.blade.php`):**
```blade
<div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div class="rounded-lg bg-zinc-800 p-4">
        <flux:text class="text-sm text-zinc-400">Tracks</flux:text>
        <flux:heading size="xl" class="mt-1">0</flux:heading>
    </div>
</div>
```

**Replacement — three-card grid matching the decision:**
```blade
<div class="mt-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg bg-zinc-800 p-4">
        <flux:text class="text-sm text-zinc-400">Tracks</flux:text>
        <flux:heading size="xl" class="mt-1">{{ $user->tracks_count }}</flux:heading>
    </div>
    <div class="rounded-lg bg-zinc-800 p-4">
        <flux:text class="text-sm text-zinc-400">Albums</flux:text>
        <flux:heading size="xl" class="mt-1">{{ $user->albums_count }}</flux:heading>
    </div>
    <div class="rounded-lg bg-zinc-800 p-4">
        <flux:text class="text-sm text-zinc-400">Playlists</flux:text>
        <flux:heading size="xl" class="mt-1">{{ $user->playlists_count }}</flux:heading>
    </div>
</div>
```

Note: `sm:grid-cols-3` matches the locked decision and follows the existing `sm:grid-cols-2` breakpoint pattern.

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Counting related records | Manual `SELECT COUNT(*)` raw queries | Eloquent `withCount()` | Handles all SQL, returns virtual attribute, single query |
| Relationship count | `$user->tracks->count()` (loads all records) | `withCount` or `->count()` on query | `->count()` on loaded collection hits DB for all rows first |

**Key insight:** `withCount` is the right tool here — it adds `LEFT JOIN ... COUNT` to the user query and returns integer attributes, not loaded collections.

## Common Pitfalls

### Pitfall 1: Missing HasMany relationships on User model
**What goes wrong:** `withCount(['tracks', 'albums', 'playlists'])` requires those relationship methods to exist on the User model. Currently User has no `tracks()`, `albums()`, or `playlists()` methods.
**Why it happens:** Relationships were defined in the other direction (Track/Album/Playlist `belongsTo` User) but the inverse `hasMany` on User was never added.
**How to avoid:** Add `tracks()`, `albums()`, `playlists()` HasMany methods to User before using `withCount`.
**Warning signs:** `BadMethodCallException: Call to undefined method App\Models\User::tracks()` at runtime.

### Pitfall 2: $user public property loses withCount attributes after Livewire hydration
**What goes wrong:** Livewire serializes `$user` between requests. On initial page load the model has count attributes, but those virtual attributes (not real DB columns) may not survive the Livewire dehydration/hydration cycle if the component re-renders.
**Why it happens:** Livewire serializes models via their primary key and re-fetches them on next request, losing any extra attributes not in the DB.
**How to avoid:** Since this is a non-interactive display page (no wire: actions that would trigger re-renders), this is not an issue in practice. The page renders once and stays static. If it were interactive, compute counts in a `computed` property or pass them as separate component properties.
**Warning signs:** Counts showing `null` after a Livewire action (not applicable to this page).

### Pitfall 3: Mobile menu uses a different structure than desktop menu
**What goes wrong:** Desktop menu has Settings inside a single `flux:menu.radio.group` alongside the Profile item. Mobile menu splits Settings into its own `flux:menu.radio.group` with a separator. Copy-paste without checking context.
**Why it happens:** Desktop and mobile menus have different structures despite similar appearance.
**How to avoid:** Read both files carefully. Mobile menu in `sidebar.blade.php` has this structure:
- `flux:menu.radio.group` (user info only)
- `flux:menu.separator`
- `flux:menu.radio.group` (Settings only — this is where Profile item goes, above Settings)
- `flux:menu.separator`
- Logout form
**Warning signs:** Profile item appearing in wrong position in mobile menu.

### Pitfall 4: Hardcoded `0` test that breaks after real counts
**What goes wrong:** Existing test `profile page shows track count of zero` asserts `assertSee('Tracks')`. This will still pass. But if any test creates tracks for the profile user, a `assertSee('0')` check would fail. Current tests don't do this — but be careful when writing new tests.
**How to avoid:** New tests for accurate counts should create known fixtures (e.g., `Track::factory()->count(3)->create(['user_id' => $user->id])`) and assert the known number.

## Code Examples

### Adding HasMany relationships to User model
```php
// In app/Models/User.php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function tracks(): HasMany
{
    return $this->hasMany(Track::class);
}

public function albums(): HasMany
{
    return $this->hasMany(Album::class);
}

public function playlists(): HasMany
{
    return $this->hasMany(Playlist::class);
}
```

### withCount in Livewire mount
```php
// In pages/profile/⚡show.blade.php PHP section
public function mount(User $user): void
{
    $this->user = User::withCount(['tracks', 'albums', 'playlists'])->findOrFail($user->id);
}
```

### Desktop menu Profile item insertion (desktop-user-menu.blade.php)
The `flux:menu.radio.group` currently contains only Settings + Logout. Insert Profile as the first item:
```blade
<flux:menu.radio.group>
    <flux:menu.item :href="route('profile.show', auth()->user())" icon="user" wire:navigate>
        {{ __('Profile') }}
    </flux:menu.item>
    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
        {{ __('Settings') }}
    </flux:menu.item>
    <form method="POST" action="{{ route('logout') }}" class="w-full">
        @csrf
        <flux:menu.item ...>{{ __('Log Out') }}</flux:menu.item>
    </form>
</flux:menu.radio.group>
```

### Mobile menu Profile item insertion (sidebar.blade.php)
The mobile Settings group (lines 63-67) is:
```blade
<flux:menu.radio.group>
    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
        {{ __('Settings') }}
    </flux:menu.item>
</flux:menu.radio.group>
```
Insert Profile above Settings in this same group:
```blade
<flux:menu.radio.group>
    <flux:menu.item :href="route('profile.show', auth()->user())" icon="user" wire:navigate>
        {{ __('Profile') }}
    </flux:menu.item>
    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
        {{ __('Settings') }}
    </flux:menu.item>
</flux:menu.radio.group>
```

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Pest 4 / PHPUnit 12 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test --compact --filter=PublicProfile` |
| Full suite command | `php artisan test --compact` |

### Phase Requirements → Test Map
| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| PROF-04-nav | Profile link appears in desktop user menu | Feature | `php artisan test --compact --filter=PublicProfile` | ❌ new test needed |
| PROF-04-nav | Profile link appears in mobile user menu | Feature | `php artisan test --compact --filter=PublicProfile` | ❌ new test needed |
| PROF-04-trackcount | Profile shows accurate track count | Feature | `php artisan test --compact --filter=PublicProfile` | ❌ add to existing PublicProfileTest.php |
| PROF-04-trackcount | Profile shows album count | Feature | `php artisan test --compact --filter=PublicProfile` | ❌ add to existing PublicProfileTest.php |
| PROF-04-trackcount | Profile shows playlist count | Feature | `php artisan test --compact --filter=PublicProfile` | ❌ add to existing PublicProfileTest.php |

### Sampling Rate
- **Per task commit:** `php artisan test --compact --filter=PublicProfile`
- **Per wave merge:** `php artisan test --compact`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] New tests in `tests/Feature/Profile/PublicProfileTest.php` — covers PROF-04-nav and PROF-04-trackcount
- No new test files needed — extend existing `PublicProfileTest.php`
- No framework install needed — Pest already configured

## Sources

### Primary (HIGH confidence)
- Direct code inspection of `resources/views/components/desktop-user-menu.blade.php` — current desktop menu structure
- Direct code inspection of `resources/views/layouts/app/sidebar.blade.php` — current mobile menu structure
- Direct code inspection of `resources/views/pages/profile/⚡show.blade.php` — existing profile page with hardcoded `0`
- Direct code inspection of `app/Models/User.php`, `Track.php`, `Album.php`, `Playlist.php` — confirms relationships exist on child models, not on User
- Direct code inspection of `tests/Feature/Profile/PublicProfileTest.php` — existing test coverage baseline

### Secondary (MEDIUM confidence)
- Eloquent `withCount` documentation — standard Laravel feature, stable across v11/v12

## Metadata

**Confidence breakdown:**
- Navigation changes: HIGH — exact target files and patterns identified from codebase inspection
- Stat count approach: HIGH — Eloquent `withCount` is standard Laravel, relationships are clear
- Pitfalls: HIGH — identified from direct code inspection (missing User hasMany relationships, mobile menu structure difference)
- Test approach: HIGH — existing test file and pattern identified

**Research date:** 2026-03-12
**Valid until:** 2026-04-12 (stable domain — Livewire 4 / Flux UI 2 / Laravel 12 all stable)
