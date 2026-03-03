---
phase: 03-preview-and-polish
plan: 01
subsystem: api
tags: [wordpress, etch, builder-preview, php, query-var]

# Dependency graph
requires:
  - phase: 02-single-job-routing
    provides: handle_single_job() with $post/$wp_query swap and $this->current_job populated for real job URLs; inject_options() reading $this->current_job to inject cws_job
provides:
  - Etch builder preview branch in handle_single_job() — ?etch=magic + is_user_logged_in() populates $this->current_job with first configured job
  - cws_job data visible in Etch builder when editing the job template page
  - Graceful no-op when no job IDs configured (log notice, no 404)
affects: [03-preview-and-polish]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Preview detection via superglobal ($_GET) checked in template_redirect action, not in the filter callback"
    - "Preview branch sets $this->current_job via same get_job() + format_job_data() path as real job routing"

key-files:
  created: []
  modified:
    - includes/class-cws-core-etch.php

key-decisions:
  - "No post_id query param check in preview branch — CONTEXT.md confirmed simple detection is sufficient; extra API calls are cached"
  - "Preview branch returns before $post/$wp_query swap — builder already has correct page loaded; swap would break the iframe"
  - "Empty configured job IDs logs info notice and returns cleanly — no 404, editor can still design template shell"

patterns-established:
  - "Preview fetch uses identical code path (get_job + format_job_data) as real job routing — ensures preview data includes all Phase 3 date fields automatically"

requirements-completed: [PREVIEW-01]

# Metrics
duration: 1min
completed: 2026-03-02
---

# Phase 3 Plan 01: Etch Builder Preview Summary

**Preview branch in handle_single_job() populates cws_job with first configured job when ?etch=magic + is_user_logged_in() — editors now see real data in the Etch builder**

## Performance

- **Duration:** ~1 min
- **Started:** 2026-03-02T05:45:04Z
- **Completed:** 2026-03-02T05:46:00Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Added Etch builder preview branch at top of `handle_single_job()`, before the existing early-return
- Preview detects `?etch=magic` + `is_user_logged_in()` with no real job URL active (four-condition check)
- Fetches first configured job ID, populates `$this->current_job` / `$this->current_job_id` via same `get_job()` + `format_job_data()` path as real job routing
- Ends with `return;` — no `$post`/`$wp_query` swap occurs, builder iframe remains stable
- Graceful empty-IDs path: logs info notice, returns without error or 404

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Etch builder preview branch to handle_single_job()** - `db52fbf` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `includes/class-cws-core-etch.php` — Added 27-line preview branch inside `handle_single_job()` before the existing `if ( empty( $job_id ) ) { return; }` early-return; `inject_options()` unchanged

## Decisions Made
- No `post_id` check: CONTEXT.md explicitly states no post_id check required — keeping detection simple. Extra API call is cached (MD5 key via fetch_job_data), negligible cost.
- Preview branch uses `return;` not `elseif`: The existing `if ( empty( $job_id ) ) { return; }` that follows is a separate guard; having both as sequential `if` blocks (not `if/elseif`) keeps the original guard visually clear and unchanged.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness
- Plan 03-01 complete: editors can now open the job template page with `?etch=magic` while logged in and see `{options.cws_job.*}` fields populated with real job data from the first configured job ID
- Plan 03-02 ready: add `open_date_formatted` and `update_date_formatted` to `format_job_data()`, and document/audit cache clear scope for object cache environments

---
*Phase: 03-preview-and-polish*
*Completed: 2026-03-02*
