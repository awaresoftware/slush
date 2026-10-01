# Phase 6: Profile UX Polish - Context

**Gathered:** 2026-03-12
**Status:** Ready for planning

<domain>
## Phase Boundary

Close two integration gaps from the v1.0 milestone audit: add an in-app navigation entry point to the user's own profile page, and replace the hardcoded track count on the public profile with accurate stats. No new profile features — this is gap closure only.

</domain>

<decisions>
## Implementation Decisions

### Profile navigation link
- Add "Profile" link to the user menu dropdown (both desktop `desktop-user-menu.blade.php` and mobile menu in `sidebar.blade.php`)
- NOT in the sidebar nav — keep sidebar for content areas (Dashboard, Library, Playlists)
- Position: above Settings, below the user info section (order: Profile → Settings → Logout)
- Icon: Heroicon `user`
- Label: "Profile" (not "My Profile" or "View Profile")
- Links to `route('profile.show', auth()->user())` — the user's own public profile

### Profile stats display
- Show three stat cards: Tracks, Albums, Playlists
- All three stats visible to any logged-in user viewing the profile (no privacy distinction)
- 3-column grid layout (sm:grid-cols-3), stacking on mobile — extends existing grid pattern on profile page
- Stats are static display only — not clickable, no links to Library/Playlists
- Counts come from actual database queries on the profile user's data (not hardcoded)

### Claude's Discretion
- Exact Eloquent query approach for counts (withCount, separate queries, etc.)
- Whether to eager-load counts or compute in the component
- Mobile responsive breakpoints for the 3-column grid

</decisions>

<specifics>
## Specific Ideas

No specific requirements — straightforward gap closure following existing patterns.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `desktop-user-menu.blade.php`: Desktop dropdown with Settings + Logout — add Profile item here
- `sidebar.blade.php` mobile section: Mobile dropdown with Settings + Logout — add Profile item here
- `profile/⚡show.blade.php`: Existing profile show page with hardcoded `0` track count on line 45
- `flux:menu.item` component: Used for dropdown items with icon + label + wire:navigate

### Established Patterns
- User menu items use `flux:menu.item` with `:href`, `icon`, and `wire:navigate`
- Items grouped inside `flux:menu.radio.group`
- Profile page uses `flux:heading` and `flux:text` for stat display
- Stat cards use `rounded-lg bg-zinc-800 p-4` styling

### Integration Points
- `route('profile.show', auth()->user())` — existing named route for profile pages
- User model has `tracks()`, relationships for counting
- Album and Playlist models have `user_id` relationships

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 06-profile-ux-polish*
*Context gathered: 2026-03-12*
