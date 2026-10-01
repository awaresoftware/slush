# Phase 1: Authentication System - Context

**Gathered:** 2026-03-04
**Status:** Ready for planning

<domain>
## Phase Boundary

Implement a hybrid authentication system where:
- The first user to register becomes an admin automatically
- After the first user, registration is disabled (404) — only admins can invite new users via a settings page
- Users can log in with email/password
- Session management supports persistent sessions (remember me)
- Password reset functionality for existing users
- Admin can invite new users by email — invited users set their own password via a link

</domain>

<decisions>
## Implementation Decisions

### UI Framework
- Use DaisyUI + MaryUI for all auth forms — replaces Flux UI for auth views
- Apply to ALL auth forms: login, register, password reset, invite/password-set
- Dark / Spotify-inspired visual theme (dark backgrounds, green accent)
- No logo or app name on auth pages — just the form, minimal

### Registration Gate
- Middleware handles the gate: if any user exists in users table → block /register
- Blocked registration returns 404 Not Found (not a redirect)
- No ENV flag to re-open — once closed, always closed unless users table is empty
- Login page shows a 'Register' / 'Create account' link dynamically, only when no users exist (User::count() === 0)

### Admin User Creation Flow
- Admin provides email only — system sends an invite email with a password-set link
- Invited user clicks the link, sets their own password, then lands on the dashboard
- User management lives inline on a settings page (not a separate /admin route)
- Admin can delete users from the same settings section
- User management section is hidden entirely from non-admins (no visible link, no accessible route)

### Role / Admin Tracking
- `is_admin` boolean column on the users table
- Only the first registered user is admin — set automatically during registration
- No mechanism to promote other users to admin (single admin system)
- Non-admins: user management section is invisible (not rendered), with server-side gate if accessed directly

### Email Verification
- No email verification step — accounts are immediately active after registration or password set
- The invite flow (password-set link) is not email verification — it's initial password setup

### Password Reset
- Use Laravel's built-in password reset (Fortify)
- Admins can also reset user passwords from the settings user management section

### Session Management
- Persistent sessions with "remember me" functionality
- Sessions persist across browser refreshes and close/reopen cycles

</decisions>

<specifics>
## Specific Ideas

- Login page: dynamically show/hide "Create account" link based on User::count() === 0
- Registration gate middleware: single check, clean 404 abort
- User management on settings page: invite form (email input) + user list with delete actions
- First user auto-admin: set is_admin = true in the Fortify registration pipeline

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `resources/views/pages/auth/register.blade.php`: Exists but uses plain HTML/Tailwind — needs to be rewritten with DaisyUI
- `resources/views/layouts/guest.blade.php`: Guest layout exists — may need updating for dark theme
- `resources/views/components/auth/`: Auth component directory exists

### Established Patterns
- Laravel Fortify: Handles registration, login, password reset pipelines — customizable via actions
- Blade + Livewire: App uses Livewire for reactive components
- App uses Flux UI Free in the stack — DaisyUI + MaryUI will be added as additional dependencies for auth views

### Integration Points
- Fortify registration action: where `is_admin` auto-assignment logic hooks in
- Middleware stack (`bootstrap/app.php`): where the registration gate middleware registers
- Settings page (Phase 2+): where user management section will live inline

</code_context>

<deferred>
## Deferred Ideas

- Third-party authentication (Google, GitHub) — future phase
- Two-factor authentication — future phase
- OAuth integration — future phase
- Admin promoting other users to admin — out of scope (single admin system)

</deferred>

---

*Phase: 01-authentication-system*
*Context gathered: 2026-03-04*
