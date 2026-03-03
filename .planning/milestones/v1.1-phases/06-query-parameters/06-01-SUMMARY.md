---
phase: 06-query-parameters
plan: 01
subsystem: api
tags: [wordpress, admin, settings, repeater, query-params]

# Dependency graph
requires:
  - phase: 05-cache-status
    provides: admin settings pattern (register_setting + render field + sanitize)
provides:
  - Admin-configurable query parameters repeater in API Configuration settings section
  - cws_core_query_params wp_options storage with sanitization
  - build_api_url() appends all stored params to every outgoing API request URL
affects: [api-requests, admin-settings, build_api_url]

# Tech tracking
tech-stack:
  added: []
  patterns: [WordPress settings API repeater field, event-delegated JS repeater, sanitize_key+urlencode for URL params]

key-files:
  created: []
  modified:
    - includes/class-cws-core-admin.php
    - includes/class-cws-core-api.php
    - admin/js/admin.js
    - admin/css/admin.css

key-decisions:
  - "Repeater rows use indexed name attributes (cws_core_query_params[N][key]) — WordPress receives a native array, no json_encode needed"
  - "sanitize_key() applied to param keys in build_api_url() — converts to lowercase alphanumeric + underscores/hyphens, safe for URL query params"
  - "urlencode() applied to param values — ensures special characters are safe in the URL"
  - "Re-indexing on row removal keeps form submission indices contiguous, preventing gaps that could confuse PHP array parsing"
  - "sanitize_query_params() strips entries with empty key after sanitize_text_field — empty keys are never stored or appended to URLs"

patterns-established:
  - "Repeater field pattern: server-renders rows from stored option, JS adds/removes rows and re-indexes name attributes on removal"
  - "Event delegation on parent container for dynamically added remove buttons"

requirements-completed: [QUERY-01, QUERY-02, QUERY-03]

# Metrics
duration: 2min
completed: 2026-03-03
---

# Phase 6 Plan 01: Query Parameters Summary

**Admin-configurable query params repeater that stores key/value pairs via WordPress Settings API and appends all configured params to every build_api_url() output via add_query_arg()**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-03T12:12:06Z
- **Completed:** 2026-03-03T12:14:02Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments

- `register_setting()` for `cws_core_query_params` (type array, sanitize callback) and `add_settings_field()` in API Configuration section
- `render_query_params_field()` renders server-side repeater rows with indexed name attributes; `sanitize_query_params()` strips empty keys and sanitizes all values
- JS `addQueryParamRow` / `removeQueryParamRow` methods handle dynamic row add/remove with re-indexing; bound via event delegation
- `build_api_url()` reads `cws_core_query_params` from wp_options and appends each param via `add_query_arg()` with `sanitize_key()` + `urlencode()`

## Task Commits

Each task was committed atomically:

1. **Task 1: Register cws_core_query_params option and render repeater field in admin PHP** - `71ead05` (feat)
2. **Task 2: JS add/remove handlers for query params repeater + repeater row styles** - `22f7a43` (feat)
3. **Task 3: Append stored query params to every API URL in build_api_url()** - `94fd95b` (feat)

## Files Created/Modified

- `includes/class-cws-core-admin.php` - Added register_setting(), add_settings_field(), render_query_params_field(), sanitize_query_params()
- `includes/class-cws-core-api.php` - Added extra_params loop in build_api_url() after organization/jobList args
- `admin/js/admin.js` - Added addQueryParamRow and removeQueryParamRow methods; bound in bindEvents()
- `admin/css/admin.css` - Appended .cws-core-query-param-row flex layout styles

## Decisions Made

- Repeater rows use indexed name attributes (`cws_core_query_params[N][key]`) — WordPress receives a native PHP array, no json_encode/decode needed
- `sanitize_key()` applied to param keys in `build_api_url()` — converts to lowercase alphanumeric + underscores/hyphens, appropriate for URL query param keys
- `urlencode()` applied to param values — ensures special characters are safe in the URL string
- Re-indexing on row removal ensures contiguous PHP array indices on form submission
- `sanitize_query_params()` strips entries with empty key — empty keys are never stored or appended to URLs

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Query parameters feature is fully implemented end-to-end
- Admin can now configure arbitrary key/value pairs that are appended to all API requests without code changes
- Ready for Phase 6 Plan 02 if applicable, or phase complete

---
*Phase: 06-query-parameters*
*Completed: 2026-03-03*

## Self-Check: PASSED

- FOUND: includes/class-cws-core-admin.php
- FOUND: includes/class-cws-core-api.php
- FOUND: admin/js/admin.js
- FOUND: admin/css/admin.css
- FOUND: .planning/phases/06-query-parameters/06-01-SUMMARY.md
- FOUND commit: 71ead05 (Task 1)
- FOUND commit: 22f7a43 (Task 2)
- FOUND commit: 94fd95b (Task 3)
