---
phase: quick
plan: 1
type: execute
wave: 1
depends_on: []
files_modified:
  - resources/views/layouts/app/sidebar.blade.php
autonomous: true
requirements: []
must_haves:
  truths:
    - "Sidebar renders full viewport height on desktop"
    - "Page content renders beside the sidebar, not below it"
    - "Mobile header still appears on small screens"
    - "Audio player bottom padding still prevents content overlap"
  artifacts:
    - path: "resources/views/layouts/app/sidebar.blade.php"
      provides: "Fixed sidebar layout with flux:main as direct sibling of flux:sidebar"
  key_links:
    - from: "resources/views/layouts/app.blade.php"
      to: "resources/views/layouts/app/sidebar.blade.php"
      via: "x-layouts::app.sidebar component passing flux:main in slot"
      pattern: "flux:main"
---

<objective>
Fix the sidebar layout so the sidebar is full height with content beside it instead of stacked vertically.

Purpose: The wrapper `<div class="pb-28">` around `{{ $slot }}` breaks Flux's expected sibling relationship between `<flux:sidebar>` and `<flux:main>`, causing the sidebar to end mid-screen and content to render below at full width.

Output: Corrected sidebar.blade.php where flux:main is a direct sibling of flux:sidebar.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@resources/views/layouts/app/sidebar.blade.php
@resources/views/layouts/app.blade.php
</context>

<tasks>

<task type="auto">
  <name>Task 1: Fix sidebar layout structure</name>
  <files>resources/views/layouts/app/sidebar.blade.php</files>
  <action>
In `resources/views/layouts/app/sidebar.blade.php`, make three changes:

1. **Remove the wrapper div** (line 90-92): The `<div class="pb-28">{{ $slot }}</div>` wraps `flux:main` (passed via slot from app.blade.php), breaking the required `flux:sidebar` + `flux:main` sibling relationship. Replace with bare `{{ $slot }}`.

2. **Move `pb-28` onto flux:main**: In `resources/views/layouts/app.blade.php`, add `class="pb-28"` to the `<flux:main>` component so audio player padding stays scoped to the content area.

3. **Move the mobile header inside flux:main**: Cut the entire `<flux:header class="lg:hidden">...</flux:header>` block (lines 33-88) from between flux:sidebar and the slot. Place it inside `resources/views/layouts/app.blade.php` as the first child inside `<flux:main>`, before `{{ $slot }}`. This keeps it flowing naturally on mobile without breaking the desktop sidebar layout.

After changes, sidebar.blade.php should have this structure:
```
<body>
  <flux:sidebar ...>...</flux:sidebar>
  {{ $slot }}
  @auth ... audio-player ... @endauth
  @fluxScripts
</body>
```

And app.blade.php should become:
```
<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="pb-28">
        <flux:header class="lg:hidden">
            ...mobile menu...
        </flux:header>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
```
  </action>
  <verify>
    <automated>php artisan view:cache 2>&1 && echo "Views compile OK" && php artisan view:clear</automated>
  </verify>
  <done>
    - flux:sidebar and flux:main are direct siblings (no wrapper div between them)
    - pb-28 is on flux:main, not on a wrapper div
    - Mobile header is inside flux:main
    - Views compile without errors
  </done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <what-built>Fixed sidebar layout so sidebar is full height with content beside it on desktop, and mobile header still works on small screens.</what-built>
  <how-to-verify>
    1. Open the app in a desktop browser — sidebar should be full height on the left, content should render beside it (not below)
    2. Resize to mobile width — sidebar should collapse, mobile header with hamburger menu should appear
    3. Scroll down on a page with content — verify bottom padding keeps content above the audio player
    4. Navigate between Dashboard, Library, Playlists — layout should be consistent
  </how-to-verify>
  <resume-signal>Type "approved" or describe issues</resume-signal>
</task>

</tasks>

<verification>
- `php artisan view:cache` compiles all views without errors
- Visual: sidebar full height, content beside it on desktop
- Visual: mobile header visible and functional on small screens
</verification>

<success_criteria>
Sidebar renders full viewport height with page content beside it (not stacked below). Mobile layout unaffected. Audio player padding preserved.
</success_criteria>

<output>
After completion, create `.planning/quick/1-fix-sidebar-layout-sidebar-should-be-ful/1-SUMMARY.md`
</output>
