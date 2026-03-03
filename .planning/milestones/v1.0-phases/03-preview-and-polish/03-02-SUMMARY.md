---
phase: 03-preview-and-polish
plan: 02
subsystem: api
tags: [wordpress, php, wp_date, transients, object-cache, date-formatting]

# Dependency graph
requires:
  - phase: 02-single-job-routing
    provides: format_job_data() with open_date and update_date raw keys; clear_all() SQL DELETE pattern
provides:
  - format_job_data() with open_date_formatted and update_date_formatted keys using wp_date( 'F j, Y' )
  - clear_all() with audit comment confirming per-job transient coverage + wp_cache_flush_group for Redis/Memcached
affects: [etch-templates, admin-cache-clear]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "wp_date( 'F j, Y', strtotime( $raw ) ) ?: '' — timezone-aware date formatting with empty-string fallback for false returns"
    - "function_exists( 'wp_cache_flush_group' ) guard — safe object cache flush on hosts without persistent cache"

key-files:
  created: []
  modified:
    - includes/class-cws-core-api.php
    - includes/class-cws-core-cache.php

key-decisions:
  - "Use wp_date() not date() — wp_date() respects WP site timezone setting; date() uses PHP server timezone"
  - "Use ?: '' not !== false check — wp_date() returns false on bad input; Etch needs strings not PHP false; ?: '' coerces false to empty string without affecting '0' string (safe for date formatting)"
  - "Audit comment rather than code change for SQL DELETE — existing LIKE '_transient_cws_core_%' pattern already covers per-job keys; comment documents the key derivation path for future maintainers"
  - "wp_cache_flush_group guarded by function_exists — no fatal on sites without persistent object cache; no-op on standard hosts"

patterns-established:
  - "Date keys follow raw/formatted pair pattern: raw key first (open_date), formatted key immediately after (open_date_formatted)"

requirements-completed: [POLISH-01, POLISH-02]

# Metrics
duration: 2min
completed: 2026-03-01
---

# Phase 3 Plan 02: Date Formatting and Cache Audit Summary

**wp_date()-formatted date fields added to format_job_data() and clear_all() hardened with object cache flush and per-job transient audit comment**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-02T05:48:07Z
- **Completed:** 2026-03-02T05:49:03Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- `format_job_data()` now returns `open_date_formatted` and `update_date_formatted` using `wp_date( 'F j, Y', strtotime( $raw ) ) ?: ''` — editors can use `{options.cws_job.open_date_formatted}` directly in Etch templates for human-readable dates like "March 1, 2026"
- Empty or null raw date values produce an empty string (not PHP false) — Etch templates receive only strings
- `clear_all()` includes an audit comment tracing the per-job transient key derivation path from `fetch_job_data()` through `CWS_Core_Cache::set()` to the wp_options row name, confirming the existing SQL LIKE pattern covers all job transients
- `clear_all()` now flushes the object cache group for transient backends (Redis/Memcached) via `wp_cache_flush_group( 'transient' )` guarded by `function_exists()` — safe on all hosting environments

## Task Commits

Each task was committed atomically:

1. **Task 1: Add open_date_formatted and update_date_formatted to format_job_data()** - `a53c42b` (feat)
2. **Task 2: Audit and harden clear_all() for per-job transient coverage** - `d53c57e` (feat)

**Plan metadata:** _(docs commit follows)_

## Files Created/Modified

- `includes/class-cws-core-api.php` - Added open_date_formatted and update_date_formatted keys after raw counterparts in format_job_data() return array
- `includes/class-cws-core-cache.php` - Added audit comment block explaining per-job transient key path + wp_cache_flush_group call with function_exists guard in clear_all()

## Decisions Made

- Used `wp_date()` not `date()` — respects WP site timezone setting
- Used `?: ''` coercion not `!== false` — wp_date() returns false on bad input; Etch templates must receive strings; `?: ''` safely coerces false to empty string without touching valid '0' string values
- Kept audit as a comment rather than changing SQL — the existing DELETE pattern is correct; the comment documents the key derivation path for future maintainers
- Guarded `wp_cache_flush_group()` with `function_exists()` — prevents fatal on sites without persistent object cache; is a no-op on standard hosts

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Phase 3 complete. All three phases delivered:
  - Phase 1: Core Etch integration replacing 2,494-line virtual CPT
  - Phase 2: Single job routing with template_redirect and admin settings UI
  - Phase 3: Etch builder preview (?etch=magic) + date formatting + cache hardening
- Plugin is production-ready: job data available in Etch templates via {options.cws_jobs} and {options.cws_job}, with human-readable date fields and a trustworthy cache clear

---
*Phase: 03-preview-and-polish*
*Completed: 2026-03-01*
