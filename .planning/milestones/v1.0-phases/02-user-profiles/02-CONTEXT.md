# Phase 2: User Profiles - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

User profile creation and management — display name, avatar upload, bio field, and public profile viewing. Music library stats are included on the public view (as a read-only count from Phase 3 data). Creating playlists, social following, and any music features are separate phases.

</domain>

<decisions>
## Implementation Decisions

### Profile data model
- Add profile columns directly to the `users` table (no separate profiles table)
- Reuse the existing `name` field as the display name — no separate `display_name` column
- Bio field: 160 character limit, plain text only (no markdown)
- Profile completion is fully optional — no prompts or forced setup flow
- Public profile URLs are ID-based: `/profile/{id}`

### Avatar storage & display
- Store avatars on local disk (Laravel storage/app/public)
- No avatar uploaded: show a generic placeholder image (not the initials fallback)
- Avatars resized server-side to a standard size on upload (requires image processing package)
- Accept any common image format (JPG, PNG, WebP, GIF, AVIF, HEIC, etc.)
- Max file size: 2MB
- Users can remove their avatar (clear button that deletes the file and resets avatar_path to null)

### Profile edit UX
- Dedicated settings page at `/settings/profile` for editing
- Accessible via the Settings section in navigation (not a profile icon shortcut)
- Avatar upload: standard HTML file input styled with MaryUI (NOT Flux components)
- Save feedback: flash success message / toast notification

### Public profile scope
- Public profile view at `/profile/{id}` — separate from the edit page
- Shows: name, avatar (or placeholder), bio, track/upload count
- Track count is a read-only stat — will be populated once Phase 3 (Music Library) is complete
- Auth required: unauthenticated visitors cannot view profiles
- No "Edit Profile" button on the public profile view — edit only via /settings/profile
- Logged-in users can view their own profile at /profile/{id} (same view as others see)

### Claude's Discretion
- Exact image resizing dimensions (e.g. 200x200, 400x400)
- Which image processing package to use (Intervention Image or similar)
- Placeholder image design/source
- Exact migration column types and nullable defaults
- Settings navigation placement within the sidebar

</decisions>

<specifics>
## Specific Ideas

- The avatar upload input should be styled with MaryUI, not Flux components — important distinction from the rest of the app's UI patterns

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `User::initials()` method — exists on the User model, available if ever needed as a fallback but not used for the placeholder approach chosen
- `app.blade.php` layout — wraps a Flux sidebar shell; profile pages slot into this

### Established Patterns
- No file upload patterns exist yet in the codebase — this phase establishes the first one
- Livewire components in `app/Livewire/`, Form Requests in `app/Http/Requests/`
- UI: mostly Flux components throughout the app, BUT avatar upload uses MaryUI per explicit decision

### Integration Points
- `users` table gets new columns: `avatar_path` (nullable string), `bio` (nullable string, max 160)
- `/settings/profile` route connects to existing sidebar Settings section
- `/profile/{id}` route uses User model route-model binding

</code_context>

<deferred>
## Deferred Ideas

- None — discussion stayed within phase scope

</deferred>

---

*Phase: 02-user-profiles*
*Context gathered: 2026-03-04*
