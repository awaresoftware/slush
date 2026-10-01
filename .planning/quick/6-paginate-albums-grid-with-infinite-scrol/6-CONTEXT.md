# Quick Task 6: Paginate albums grid with infinite scroll, add Artists tab, lazy tabs - Context

**Gathered:** 2026-03-19
**Status:** Ready for planning

<domain>
## Task Boundary

Fix OOM crash caused by rendering all album cover art in the initial HTML. The Albums tab uses `x-show` which renders all `<img>` tags even when hidden, triggering hundreds of concurrent image requests that crash Herd Expose's WebSocket tunnel (128MB limit).

Also restructure library into 3 tabs (Tracks, Artists, Albums) and paginate the albums grid.

</domain>

<decisions>
## Implementation Decisions

### Tab rendering — x-if
- Replace `x-show` with `x-if` for tab content so hidden tabs have NO HTML in the DOM
- This prevents image requests for album covers when the Albums tab isn't active
- Tabs re-render when switched to — acceptable tradeoff for memory safety

### Albums pagination — 20 per batch with infinite scroll
- Load 20 albums initially, load more on scroll (same pattern as tracks)
- Use `loadMoreAlbums()` method with `wire:intersect` sentinel
- Maintain `$loadedAlbums`, `$albumPage`, `$hasMoreAlbums` properties

### Artists tab — Artist list with their albums
- No Artist model exists — artists are a string field on Track
- Query: SELECT DISTINCT artist FROM tracks WHERE user_id = ? GROUP BY artist
- For each artist, show their albums (with cover art)
- Paginate artists (20 per batch with infinite scroll)
- Clicking an artist navigates to a filtered view or expands to show their tracks/albums

### Tab order
- Tracks | Artists | Albums (3 tabs)

### Claude's Discretion
- Whether to add `loading="lazy"` on images as a belt-and-suspenders approach alongside x-if
- Exact artist query approach (distinct query vs. aggregation)
- Artists tab visual layout

</decisions>

<specifics>
## Specific Ideas

- Replace `x-show="activeTab === 'tracks'"` with `x-if="activeTab === 'tracks'"`
- Same for albums tab
- Add artist aggregation query: `Track::where('user_id', Auth::id())->select('artist')->selectRaw('COUNT(*) as track_count')->groupBy('artist')->orderBy('artist')`
- For artist's albums: eager load via `Album::where('artist', $artistName)->where('user_id', Auth::id())->get()`
- Add `loading="lazy"` to all album cover `<img>` tags as secondary protection

</specifics>
