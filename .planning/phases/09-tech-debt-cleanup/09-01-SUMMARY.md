---
phase: 09-tech-debt-cleanup
plan: 01
subsystem: api
tags: [wordpress, php, javascript, status-metadata, uninstall, dead-code]

# Dependency graph
requires:
  - phase: 05-cache-status-controls
    provides: Status metadata pattern (update_option cws_core_last_fetch_time/status)
  - phase: 08-preview-fallback
    provides: cws_core_preview_job_id option (v1.1 additions)
provides:
  - fetch_job_data() writes status=0 on all error paths (JSON parse, invalid structure)
  - uninstall.php cleans up all 9 cws_core_* wp_options
  - admin.js free of dead testVirtualCPT code
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns: []

key-files:
  created: []
  modified:
    - includes/class-cws-core-api.php
    - uninstall.php
    - admin/js/admin.js

key-decisions:
  - "Sentinel value 0 for cws_core_last_fetch_status on failure — not a valid HTTP code, distinct from null (no fetch yet), already handled gracefully by render_cache_status_block()"
  - "No PHP-side change needed for testVirtualCPT removal — no AJAX handler ever existed server-side"

patterns-established: []

requirements-completed: []

# Metrics
duration: 2min
completed: 2026-03-03
---

# Phase 9 Plan 01: Tech Debt Cleanup Summary

**Status metadata now written on all fetch_job_data() error paths; uninstall cleans all 9 wp_options; dead testVirtualCPT JS method (~90 lines) removed**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-03T20:14:42Z
- **Completed:** 2026-03-03T20:16:02Z
- **Tasks:** 3
- **Files modified:** 3

## Accomplishments
- `fetch_job_data()` now writes `cws_core_last_fetch_status=0` and `cws_core_last_fetch_time` on the JSON parse failure path and invalid response structure path, matching the pattern already used on WP_Error and HTTP error paths
- `uninstall.php` now deletes all 9 `cws_core_*` wp_options (4 original + 5 added in v1.1), preventing orphaned data after plugin removal
- Dead `testVirtualCPT` method and its event binding removed from `admin/js/admin.js` — the corresponding PHP AJAX handler was never implemented

## Task Commits

Each task was committed atomically:

1. **Task 1: Write status metadata on JSON parse and invalid structure error paths** - `a69e7fb` (fix)
2. **Task 2: Add 5 missing v1.1 options to uninstall cleanup** - `2d94655` (fix)
3. **Task 3: Remove dead testVirtualCPT code from admin.js** - `1f04c07` (chore)

## Files Created/Modified
- `includes/class-cws-core-api.php` - Added `update_option` calls before `return false` on JSON parse and invalid structure error paths
- `uninstall.php` - Added 5 v1.1 options to `$options_to_delete` array
- `admin/js/admin.js` - Removed `testVirtualCPT` event binding, comment, and full method body (~95 lines deleted)

## Decisions Made
- Sentinel value `0` used for `cws_core_last_fetch_status` on failure — intentional, not a valid HTTP code, distinct from `null` (no fetch yet), already handled gracefully by `render_cache_status_block()`
- No PHP-side changes needed for testVirtualCPT removal — the AJAX action `cws_core_test_virtual_cpt` was never registered server-side

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All four v1.1 tech-debt items from the audit are now resolved
- Phase 9 complete — no blockers for future milestones
- Tag `v1.2` once any additional phases in this milestone are complete

## Self-Check: PASSED

- FOUND: `.planning/phases/09-tech-debt-cleanup/09-01-SUMMARY.md`
- FOUND: commit `a69e7fb` (fix: status metadata on JSON parse and invalid structure paths)
- FOUND: commit `2d94655` (fix: 5 missing v1.1 options in uninstall.php)
- FOUND: commit `1f04c07` (chore: remove dead testVirtualCPT code from admin.js)

---
*Phase: 09-tech-debt-cleanup*
*Completed: 2026-03-03*
