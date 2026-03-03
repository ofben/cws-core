# Phase 5: Cache Status & Controls - Context

**Gathered:** 2026-03-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Add cache health visibility and a manual clear button to the admin settings page. Admin can see when the jobs cache was last refreshed, whether the last API fetch succeeded or failed (with HTTP code), how old the cache is in human-readable form, and clear the cache on demand. No new public-facing features — this is entirely within the admin settings UI.

</domain>

<decisions>
## Implementation Decisions

### Where status lives on the page
- Cache status info (last refresh time, age, result) goes inside the **existing "Cache Management" sidebar box** in `render_settings_page()`
- Display status above the existing Clear Cache button — keeps all cache-related UI in one place
- No new main-form sections needed; the sidebar box already carries the right heading

### Status display format
- Show: **"Last refreshed: 2 hours ago — HTTP 200"** (human-readable relative age + HTTP code)
- Also show absolute timestamp as secondary detail: `(2026-03-03 14:22)`
- Format: relative age is the primary label; absolute timestamp and HTTP code are supporting detail
- If no cache exists yet: show "No cache — will refresh on next page load"
- HTTP code is always shown (requirement CACHE-02 explicitly calls for it)

### Clear cache button feedback
- After successful clear: status display resets to "No cache — will refresh on next page load"
- Success message shown in existing `#cws-core-cache-result` div (already wired up)
- The existing "Clear Cache" AJAX handler (`clear_cache_ajax`) already works — extend it to also clear the stored status metadata (last fetch time, HTTP code)

### Failure state presentation
- When last fetch failed: display **red/warning text** — e.g. "Last attempt failed — HTTP 503"
- Use WordPress admin red (`#d63638`) matching the existing inline warning style used elsewhere in admin.php
- Failed state is visually distinct from success — admin can immediately see something is wrong

### Claude's Discretion
- Where exactly to store last-fetch metadata (options vs transient) — use `update_option()` with `cws_core_last_fetch_time` and `cws_core_last_fetch_status` keys since this data needs to survive cache clears
- JavaScript for live relative age formatting ("2 hours ago") vs server-side rendering — either is fine
- Exact HTML structure within the sidebar box

</decisions>

<specifics>
## Specific Ideas

- The phase requirement (CACHE-02) explicitly says "HTTP status code displayed" — this is locked, not optional
- CACHE-03 says "human-readable form (e.g. '2 hours ago')" — relative age is the required format
- The existing `admin.js` already has AJAX wiring for `clear_cache` and `cache_stats` — new status display should integrate with this rather than replace it
- The existing `cws_core_admin` localized JS object already contains the `cache_stats` nonce — reuse it

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CWS_Core_Cache::clear_all()`: Already clears all cache transients — extend to also clear status options
- `CWS_Core_Cache::clear_cache_ajax()`: AJAX handler already wired up — extend its response to include reset status for the UI
- `CWS_Core_Admin::render_settings_page()`: The "Cache Management" sidebar box is where status info goes (lines 325–335)
- `CWS_Core_Admin::enqueue_scripts()`: Already localizes nonces for `clear_cache` and `cache_stats` — can add new strings here
- `admin/js/admin.js`: Already handles Clear Cache button click and shows result in `#cws-core-cache-result`

### Established Patterns
- Options stored with `cws_core_` prefix via `update_option()` / `get_option()`
- AJAX handlers: nonce verify → permission check → `wp_send_json_success()` / `wp_send_json_error()`
- Inline admin warnings use WordPress red `#d63638` (see `render_job_template_page_field()` for pattern)
- `CWS_Core_API::fetch_job_data()` already captures `$response_code` at line 143 — this is where to hook status recording

### Integration Points
- `CWS_Core_API::fetch_job_data()` — record `time()` and `$response_code` to options after every API attempt (success AND failure)
- `CWS_Core_Admin::render_settings_page()` — read stored status options and render them in the Cache Management box
- `CWS_Core_Cache::clear_all()` — clear stored status options alongside transients (so cleared state reads "no cache")

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 05-cache-status-controls*
*Context gathered: 2026-03-03*
