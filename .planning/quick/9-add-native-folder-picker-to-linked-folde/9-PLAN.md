---
phase: quick-9
plan: 01
type: execute
wave: 1
depends_on: []
files_modified:
  - app/Http/Controllers/BrowseDirectoriesController.php
  - routes/web.php
  - resources/views/pages/settings/⚡linked-folders.blade.php
  - tests/Feature/BrowseDirectoriesTest.php
autonomous: false
requirements: [QUICK-9]

must_haves:
  truths:
    - "Authenticated user can browse server-side directories via JSON API"
    - "User can click Browse button to open a folder picker modal"
    - "User can navigate into subdirectories and back to parent"
    - "Selecting a folder populates the path input on the linked folders form"
    - "Browse button also works in the Update Path modal"
    - "Unauthenticated requests to browse API return 401"
    - "Non-existent or unreadable paths return 422 validation error"
  artifacts:
    - path: "app/Http/Controllers/BrowseDirectoriesController.php"
      provides: "Server-side directory listing endpoint"
      exports: ["__invoke"]
    - path: "routes/web.php"
      provides: "GET /api/browse-directories route"
      contains: "browse-directories"
    - path: "resources/views/pages/settings/⚡linked-folders.blade.php"
      provides: "Alpine.js folder browser modal with Browse buttons"
      contains: "x-data"
    - path: "tests/Feature/BrowseDirectoriesTest.php"
      provides: "Tests for directory browsing API"
      contains: "browse-directories"
  key_links:
    - from: "resources/views/pages/settings/⚡linked-folders.blade.php"
      to: "/api/browse-directories"
      via: "Alpine.js fetch() on button click and folder navigation"
      pattern: "fetch.*browse-directories"
    - from: "routes/web.php"
      to: "app/Http/Controllers/BrowseDirectoriesController.php"
      via: "Route::get with auth middleware"
      pattern: "BrowseDirectoriesController"
---

<objective>
Add a server-side directory browser to the linked folders settings page, replacing manual path typing with a clickable folder navigation UI.

Purpose: Users currently must type absolute filesystem paths manually to link folders. A clickable directory browser (like Plex/Jellyfin) lets users navigate and select folders visually, reducing errors and improving usability.

Output: A BrowseDirectoriesController JSON endpoint, an Alpine.js folder browser modal in the linked-folders Blade template, and feature tests for the API.
</objective>

<execution_context>
@./.claude/get-shit-done/workflows/execute-plan.md
@./.claude/get-shit-done/templates/summary.md
</execution_context>

<context>
@.planning/STATE.md
@resources/views/pages/settings/⚡linked-folders.blade.php
@routes/web.php
@routes/settings.php
@app/Http/Controllers/StreamController.php
@app/Http/Requests/StorePlaylistRequest.php

<interfaces>
<!-- Existing controller conventions: single-action invokable controllers use __invoke, multi-action use named methods. -->
<!-- Auth pattern: Gate::authorize() or manual Auth::id() check. Routes use middleware(['auth']). -->
<!-- Form Request pattern: array-based rules, separate messages() method. -->
<!-- Livewire anonymous component pattern: PHP class at top of blade file using `new class extends Component`. -->
<!-- Toast pattern: `use Toast;` trait, then `$this->success()`, `$this->info()` -->

From app/Http/Controllers/StreamController.php:
```php
class StreamController extends Controller
{
    public function __invoke(Track $track): BinaryFileResponse|JsonResponse
    {
        Gate::authorize('stream', $track);
        // ...
    }
}
```

From the linked-folders component, the path property wired to the input:
```php
public string $path = '';
public string $newFolderPath = '';
```
</interfaces>
</context>

<tasks>

<task type="auto" tdd="true">
  <name>Task 1: Create BrowseDirectoriesController and route with tests</name>
  <files>app/Http/Controllers/BrowseDirectoriesController.php, routes/web.php, tests/Feature/BrowseDirectoriesTest.php</files>
  <behavior>
    - Test: Unauthenticated GET /api/browse-directories returns 302 redirect to login
    - Test: Authenticated GET /api/browse-directories (no path param) returns JSON with current_path set to user home dir, parent_path, and directories array
    - Test: Authenticated GET /api/browse-directories?path=/tmp returns JSON listing subdirectories of /tmp
    - Test: Each directory entry has name and path keys
    - Test: Response does not include files, only directories
    - Test: GET with a non-existent path returns 422 validation error
    - Test: Directories are sorted alphabetically, hidden dirs (starting with .) are excluded
  </behavior>
  <action>
1. Use `php artisan make:controller BrowseDirectoriesController --no-interaction` to create the controller.

2. Implement as a single-action invokable controller with `__invoke(Request $request)`:
   - Accept optional `path` query parameter
   - Default to the user's home directory: use PHP's `$_SERVER['HOME']` or `posix_getpwuid(posix_getuid())['dir']` — wrap in a fallback to `/` if neither available
   - Validate the path exists (`is_dir`) and is readable (`is_readable`) — return 422 with error message if not
   - Use `scandir()` to list contents, filter to only directories (using `is_dir` on each entry)
   - Exclude `.` and `..` entries, and hidden directories (names starting with `.`)
   - Sort directories alphabetically (case-insensitive)
   - Return JsonResponse with shape:
     ```json
     {
       "current_path": "/Users/you/Music",
       "parent_path": "/Users/you",
       "directories": [
         {"name": "Albums", "path": "/Users/you/Music/Albums"},
         {"name": "Singles", "path": "/Users/you/Music/Singles"}
       ]
     }
     ```
   - `parent_path` should be `dirname($currentPath)`, or `null` if at filesystem root

3. Add the route in `routes/web.php` inside the existing `Route::middleware(['auth'])->group(...)` closure:
   ```php
   Route::get('api/browse-directories', App\Http\Controllers\BrowseDirectoriesController::class)
       ->name('browse-directories');
   ```
   Place it just before the closing `});` of the auth middleware group.

4. Create test file with `php artisan make:test BrowseDirectoriesTest --pest --no-interaction`.

5. Write tests using a temporary directory created with PHP's `sys_get_temp_dir()` and `mkdir()` in a `beforeEach`:
   - Create a temp dir with 2-3 subdirectories and one file (to prove files are excluded)
   - Test unauthenticated access returns redirect
   - Test default path (no param) returns valid JSON structure
   - Test explicit path param returns correct subdirectories
   - Test non-existent path returns 422
   - Test hidden directories are excluded (create a `.hidden` subdir, assert it's not in response)
   - Clean up temp dirs in `afterEach`

6. Run `vendor/bin/pint --dirty` after all changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/BrowseDirectoriesTest.php</automated>
  </verify>
  <done>BrowseDirectoriesController returns JSON directory listings, route is registered with auth middleware, all tests pass including auth guard, valid paths, invalid paths, and hidden dir exclusion.</done>
</task>

<task type="auto">
  <name>Task 2: Add Alpine.js folder browser modal to linked-folders Blade template</name>
  <files>resources/views/pages/settings/⚡linked-folders.blade.php</files>
  <action>
1. Activate `livewire-development` and `tailwindcss-development` skills. Use `search-docs` for Alpine.js x-data, x-show, x-for, and Livewire $wire interop patterns.

2. Add a reusable Alpine.js directory browser component using `x-data` at the section level (or as a nested Alpine component). The Alpine data object should include:
   - `browserOpen: false` — controls modal visibility
   - `browserTarget: 'path'` — which Livewire property to populate ('path' for add form, 'newFolderPath' for update modal)
   - `currentPath: ''` — current directory being browsed
   - `parentPath: null` — parent directory path
   - `directories: []` — list of {name, path} objects
   - `loading: false` — loading state
   - `error: null` — error message

   Methods:
   - `openBrowser(target)` — sets browserTarget, resets state, calls fetchDirectories with no path (defaults to home)
   - `async fetchDirectories(path = null)` — sets loading=true, fetches from `/api/browse-directories` with optional `?path=` param, populates directories/currentPath/parentPath, handles errors, sets loading=false
   - `navigateTo(path)` — calls fetchDirectories(path) to drill into a subdirectory
   - `goUp()` — calls fetchDirectories(parentPath) to navigate to parent
   - `selectFolder()` — sets `$wire.path` or `$wire.newFolderPath` based on browserTarget using `$wire.set(browserTarget, currentPath)`, closes modal

3. In the "Add folder" form section, add a "Browse" button NEXT TO the path `<x-mary-input>`. Wrap the input and button in a flex container:
   ```html
   <div class="flex items-end gap-2">
       <div class="flex-1">
           <x-mary-input wire:model="path" ... />
       </div>
       <x-mary-button
           class="btn-ghost"
           type="button"
           @click="openBrowser('path')"
       >
           {{ __('Browse') }}
       </x-mary-button>
   </div>
   ```

4. In the "Update Path" modal, add a similar "Browse" button next to the `newFolderPath` input:
   ```html
   <div class="flex items-end gap-2">
       <div class="flex-1">
           <x-mary-input wire:model="newFolderPath" ... />
       </div>
       <x-mary-button
           class="btn-ghost"
           type="button"
           @click="openBrowser('newFolderPath')"
       >
           {{ __('Browse') }}
       </x-mary-button>
   </div>
   ```

5. Add the browser modal markup (use `x-show="browserOpen"` with a `<dialog>` or a simple overlay div styled like the existing Mary modals, or use a plain div with fixed positioning). The modal should contain:
   - Header showing current path in a monospace font (`font-mono bg-base-200 rounded px-3 py-2 text-sm truncate`)
   - "Go up" button (disabled when parentPath is null) — use an up-arrow icon or "Parent folder" text
   - Loading spinner shown when `loading` is true (use daisyUI `loading loading-spinner`)
   - Error message shown when `error` is not null (`text-error text-sm`)
   - Scrollable directory list (`max-h-64 overflow-y-auto`):
     - Each directory as a clickable row with folder icon and name
     - Click navigates into that directory (`@click="navigateTo(dir.path)"`)
     - Hover state for visual feedback (`hover:bg-base-200 cursor-pointer`)
   - Footer with "Select this folder" primary button and "Cancel" ghost button
   - Click outside or Cancel closes the modal (`browserOpen = false`)

6. Use `x-transition` on the modal for smooth open/close animation. Use Tailwind classes consistent with existing daisyUI modal styling in the template.

7. For the fetch URL, use `{{ route('browse-directories') }}` to generate the correct URL in the Alpine fetch call. Store it in an Alpine data property or use a template literal.

8. Run `vendor/bin/pint --dirty` after changes.
  </action>
  <verify>
    <automated>cd /Users/defou/Herd/foonk && php artisan test --compact tests/Feature/BrowseDirectoriesTest.php && grep -q "openBrowser" resources/views/pages/settings/⚡linked-folders.blade.php && grep -q "fetchDirectories" resources/views/pages/settings/⚡linked-folders.blade.php && grep -q "browse-directories" resources/views/pages/settings/⚡linked-folders.blade.php && echo "PASS" || echo "FAIL"</automated>
  </verify>
  <done>Both path inputs (add form and update path modal) have a "Browse" button. Clicking opens a modal that fetches and displays directories from the server. Users can navigate into subdirectories, go up to parent, and click "Select this folder" to populate the corresponding Livewire path property.</done>
</task>

<task type="checkpoint:human-verify" gate="blocking">
  <name>Task 3: Verify directory browser works end-to-end</name>
  <action>
    Human verifies the directory browser modal works correctly on the linked folders settings page.
  </action>
  <what-built>Server-side directory browser with Alpine.js modal on the linked folders settings page. Both the "add folder" form and the "update path" modal have Browse buttons.</what-built>
  <how-to-verify>
    1. Visit the Linked Folders settings page
    2. Click "Browse" next to the folder path input
    3. Verify the modal opens showing directories from your home directory
    4. Click into a subdirectory — verify it navigates and shows that directory's contents
    5. Click "Parent folder" / go up — verify it navigates back
    6. Click "Select this folder" — verify the path input is populated with the selected directory
    7. If you have an existing linked folder, click "Update Path", then click "Browse" in that modal — verify it also works
    8. Verify hidden directories (starting with .) are not shown
  </how-to-verify>
  <verify>Human confirms directory browser works correctly</verify>
  <done>User has verified Browse button opens folder picker, navigation works, and selecting a folder populates the path input.</done>
  <resume-signal>Type "approved" or describe issues</resume-signal>
</task>

</tasks>

<verification>
- `php artisan test --compact tests/Feature/BrowseDirectoriesTest.php` passes all tests
- Browse button exists on both path inputs in the linked folders template
- API endpoint returns correct JSON structure with directories only
- Modal opens, navigates, and populates path correctly
</verification>

<success_criteria>
- GET /api/browse-directories returns JSON with current_path, parent_path, and directories array
- Unauthenticated requests are rejected
- Invalid/non-existent paths return 422
- Hidden directories are excluded from listings
- Browse button on add-folder form opens modal and populates `$wire.path` on select
- Browse button on update-path modal opens modal and populates `$wire.newFolderPath` on select
- All tests pass
</success_criteria>

<output>
After completion, create `.planning/quick/9-add-native-folder-picker-to-linked-folde/9-SUMMARY.md`
</output>
