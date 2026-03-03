---
phase: 02-single-job-routing
plan: 01
subsystem: api
tags: [wordpress, etch, template_redirect, routing, query-vars]

# Dependency graph
requires:
  - phase: 01-core-integration
    provides: CWS_Core_Etch class with inject_options() and etch/dynamic_data/option filter wired
provides:
  - handle_single_job() method on CWS_Core_Etch — detects cws_job_id query var, fetches + formats job, swaps $post/$wp_query to template page, serves 404 for unknown IDs
  - $options['cws_job'] injection in inject_options() — available in Etch as {options.cws_job.*} on /job/{id}/ URLs only
  - template_redirect hook registered in CWS_Core_Etch::init()
  - Deprecated CWS_Core::handle_job_request() stub cleaned up
affects: [02-single-job-routing-plan-02, 03-preview-polish]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "template_redirect + $wp_query swap pattern: hook fires, detect query var, fetch API data, swap global $post/$wp_query to point at configured template page"
    - "Graceful 404 via $wp_query->set_404() + status_header(404) + get_404_template() + exit for unknown job IDs"
    - "Null guard on $this->current_job prevents cws_job from appearing in Etch context on non-job pages"

key-files:
  created: []
  modified:
    - includes/class-cws-core-etch.php
    - includes/class-cws-core.php

key-decisions:
  - "Keep handle_job_request() method in CWS_Core (empty + deprecated) to avoid fatal if called directly — only the add_action hook is removed"
  - "cws_job not injected on non-job pages — null guard on $this->current_job means key is absent from Etch context, not present as empty"
  - "Template page must be 'publish' status — degrade gracefully (log error, return early) rather than fatal if not configured"

patterns-established:
  - "Single-job routing: template_redirect → API fetch → format_job_data → wp_query swap → inject via existing filter"
  - "404 handling: $wp_query->set_404() + status_header(404) + include(get_404_template()) + exit"

requirements-completed:
  - "Inject single job as {options.cws_job} when on a /job/{id}/ URL"
  - "Load a real WordPress page as the job detail template"
  - "Graceful 404 for unknown jobs"

# Metrics
duration: 2min
completed: 2026-03-02
---

# Phase 2 Plan 01: Single Job Routing — Summary

**template_redirect handler in CWS_Core_Etch swaps $post/$wp_query to configured template page and injects {options.cws_job} for valid job IDs, with proper WordPress 404 for unknown IDs**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-02T04:22:44Z
- **Completed:** 2026-03-02T04:24:03Z
- **Tasks:** 3 of 3 (Task 3 human-verify checkpoint — approved)
- **Files modified:** 2

## Accomplishments

- `handle_single_job()` implemented: detects `cws_job_id` query var, fetches job via `CWS_Core_API::get_job()`, formats via `format_job_data()`, swaps `$post` and `$wp_query` to configured template page
- `inject_options()` updated: injects `$options['cws_job']` only when `$this->current_job` is not null — `{options.cws_job.*}` absent on non-job pages
- `CWS_Core::handle_job_request()` stub deprecated and emptied; `add_action('template_redirect', 'handle_job_request')` removed from `register_hooks()`

## Task Commits

Each task was committed atomically:

1. **Task 1: Add routing properties and handle_single_job() to CWS_Core_Etch** - `8579407` (feat)
2. **Task 2: Remove template_redirect stub from CWS_Core** - `47b85f0` (feat)
3. **Task 3: Verify single-job routing works end-to-end** - human checkpoint approved

## Files Created/Modified

- `includes/class-cws-core-etch.php` — Added `$current_job` property, `template_redirect` hook in `init()`, `handle_single_job()` method, `cws_job` injection in `inject_options()`
- `includes/class-cws-core.php` — Removed `add_action('template_redirect', 'handle_job_request')` from `register_hooks()`; replaced `handle_job_request()` body with deprecation docblock + empty body

## Decisions Made

- Keep `handle_job_request()` method signature intact to avoid fatal errors on any direct calls — only the hook registration is removed
- `cws_job` is absent (not empty) from Etch context on non-job pages — null guard means the key does not exist at all
- Template page validation requires `post_status === 'publish'` — degrade gracefully with error log rather than fatal

## Deviations from Plan

None — plan executed exactly as written. Minor cosmetic cleanup: removed a double blank line left in `register_hooks()` after removing the `add_action` line.

## Issues Encountered

None.

## User Setup Required

Before Task 3 human verification can proceed:
1. Create a WordPress page titled "Job Detail" (or similar) in WP Admin
2. Add an Etch template to that page with at least one `{options.cws_job.*}` field (e.g. a Text element with `{options.cws_job.title}`)
3. In WP Admin > Settings > CWS Core, set "Job Template Page" to that page ID

## Next Phase Readiness

- Routing logic is code-complete, committed, and verified end-to-end by user
- Single-job page renders with real job data for valid IDs
- Unknown job IDs correctly return WordPress 404
- Phase 2 plan 02 (admin settings UI) was executed in parallel and is also complete
- Ready for Phase 3: builder preview support and date field formatting

## Self-Check: PASSED

- FOUND commit: 8579407 (feat(02-01): add handle_single_job() routing and cws_job injection to CWS_Core_Etch)
- FOUND commit: 47b85f0 (feat(02-01): remove template_redirect stub from CWS_Core)
- php -l: No syntax errors detected (class-cws-core-etch.php, class-cws-core.php)
- Human checkpoint: approved by user

---
*Phase: 02-single-job-routing*
*Completed: 2026-03-01*
