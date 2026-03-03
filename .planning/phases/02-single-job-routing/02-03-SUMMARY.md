---
phase: 02-single-job-routing
plan: 03
subsystem: api
tags: [wordpress, php, etch, job-routing, dynamic-data]

# Dependency graph
requires:
  - phase: 02-single-job-routing
    provides: handle_single_job() populates $this->current_job at template_redirect; inject_options() injects cws_job into Etch context
affects: [02-single-job-routing, 03-preview-polish]

# Tech tracking
tech-stack:
  added: []
  patterns: [cws_job injection now independent of cws_jobs listing state — single-concern ordering in inject_options()]

key-files:
  created: []
  modified:
    - includes/class-cws-core-etch.php

key-decisions:
  - "cws_job null-check moved before empty($job_ids) early return — the two injection concerns (single job vs. listing) are independent and must not share control flow"

patterns-established:
  - "Inject single-resource data before early-return guards that only apply to list resources"

requirements-completed:
  - "Inject single job as {options.cws_job} when on a /job/{id}/ URL"

# Metrics
duration: 1min
completed: 2026-03-01
---

# Phase 2 Plan 03: Single Job Routing Gap-Closure Summary

**Restructured inject_options() so cws_job injection runs before the empty($job_ids) early return, closing the gap where single-job pages received no data when listing Job IDs were unconfigured**

## Performance

- **Duration:** ~1 min
- **Started:** 2026-03-01T04:45:14Z
- **Completed:** 2026-03-01T04:45:47Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments
- Fixed the early-return bypass: `{options.cws_job}` now injects on `/job/{id}/` URLs even when the admin has configured zero listing Job IDs
- Preserved all existing `cws_jobs` listing behaviour — the `empty($job_ids)` early return is unchanged
- No regression: sites with configured listing Job IDs still receive both `{options.cws_job}` and `{options.cws_jobs}` on single-job pages

## Task Commits

Each task was committed atomically:

1. **Task 1: Restructure inject_options() so cws_job injection is independent of cws_jobs listing state** - `ba93a06` (fix)

## Files Created/Modified
- `includes/class-cws-core-etch.php` - Moved `null !== $this->current_job` block to before `empty($job_ids)` early return in `inject_options()`

## Decisions Made
- cws_job null-check moved before empty($job_ids) early return — the two injection concerns (single job listing vs. single-job page) are independent and must not share control flow

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- Phase 2 gap now fully closed: single-job routing works whether or not the admin has configured listing Job IDs
- Ready for Phase 3 (Preview and Polish): ?etch=magic fallback, date formatting
- No blockers

---
*Phase: 02-single-job-routing*
*Completed: 2026-03-01*
