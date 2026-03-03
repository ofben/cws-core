---
phase: 01-core-integration
plan: 02
subsystem: api
tags: [wordpress, php, etch, dynamic-data, filter, jobs-api]

# Dependency graph
requires:
  - phase: 01-core-integration/01-01
    provides: class-cws-core.php coordinator wiring (class_exists guard + etch property + init_components + init hooks)
provides:
  - CWS_Core_Etch class — registers etch/dynamic_data/option filter and injects cws_jobs array
  - includes/class-cws-core-etch.php — 76-line Etch integration adapter
  - cws-core.php — require_once wired for class-cws-core-etch.php
affects:
  - 01-03 (Phase 2 single-job routing — will extend CWS_Core_Etch with $current_job_id population)
  - All Etch templates that use {options.cws_jobs}

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WordPress add_filter adapter pattern — thin class with init() registering hooks
    - Etch dynamic data injection via etch/dynamic_data/option filter (single-arg, must return array)
    - Defensive empty-array fallback on all failure paths (API unavailable, no job IDs, no results)

key-files:
  created:
    - includes/class-cws-core-etch.php
  modified:
    - cws-core.php

key-decisions:
  - "require_once for class-cws-core-etch.php deferred to Plan 01-02 (this plan) to avoid PHP fatal on missing file — Plan 01-01 added class_exists guard, Plan 01-02 creates the file then adds the require_once"
  - "inject_options() uses !empty($jobs) guard not false === because CWS_Core_API::get_jobs() always returns array() on failure"
  - "$current_job_id property declared but null/unused in Phase 1 — Phase 2 stub to avoid architectural break between phases"

patterns-established:
  - "Etch filter adapter: init() registers add_filter, inject_options() merges data into $options and returns it"
  - "All failure paths (no IDs, API down, empty results) set cws_jobs to array() — no PHP warnings, no crashes"
  - "No raw error_log() — all logging via $this->plugin->log() to respect debug_mode setting"

requirements-completed:
  - "Inject all-jobs array via etch/dynamic_data/option"
  - "All job API fields accessible via Etch dot notation"

# Metrics
duration: 1min
completed: 2026-03-02
---

# Phase 1 Plan 2: Etch Integration Class Summary

**CWS_Core_Etch adapter wired into plugin bootstrap — injects cws_jobs array via etch/dynamic_data/option filter so Etch templates can loop over API job data**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-02T03:53:22Z
- **Completed:** 2026-03-02T03:54:15Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Created `includes/class-cws-core-etch.php` (76 lines) — the Etch integration adapter that hooks `etch/dynamic_data/option` and populates `$options['cws_jobs']` from the configured API job IDs
- Wired `require_once` for the new class into `cws-core.php` after `class-cws-core-public.php`, completing the bootstrap sequence started in Plan 01-01
- Verified all success criteria: PHP syntax clean, filter registered, correct API method calls used, `!empty()` guard in place, no raw `error_log()`, `$current_job_id` Phase 2 stub declared

## Task Commits

Each task was committed atomically:

1. **Task 1: Create includes/class-cws-core-etch.php** - `fe815a3` (feat)
2. **Task 2: Add require_once for class-cws-core-etch.php to cws-core.php** - `94db9ec` (feat)

**Plan metadata:** (docs commit — see final_commit step)

## Files Created/Modified

- `includes/class-cws-core-etch.php` — CWS_Core_Etch class; registers etch/dynamic_data/option filter; inject_options() fetches job IDs + calls api->get_jobs() + returns $options with cws_jobs key always set
- `cws-core.php` — Added `require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-etch.php';` after the public class include

## Decisions Made

- Used the exact verified skeleton from the research/interfaces block — no deviations warranted; the skeleton was derived directly from Etch v1.3.1 source inspection
- Placed `require_once` after `class-cws-core-public.php` to maintain logical ordering in the bootstrap file

## Deviations from Plan

None - plan executed exactly as written. The implementation skeleton was provided in full in the plan interfaces block and used verbatim.

## Issues Encountered

None. Both tasks executed cleanly on first attempt.

## Frontend Smoke Test

The automated PHP syntax and grep checks all passed. The end-to-end frontend smoke test (plugin activation + Etch template with `{#loop options.cws_jobs as job}{job.title}{/loop}`) requires a running WordPress environment and could not be performed in this execution context. The class wiring is complete and correct — smoke test should be performed manually after deployment.

Job fields available via Etch dot notation once the smoke test is run:
- `job.title` — job title
- `job.company_name` — employer name
- `job.salary` — salary information
- `job.employment_type` — full-time, part-time, etc.
- `job.location` — job location
- `job.url` — direct job URL
- `job.description` — job description text

## User Setup Required

None - no external service configuration required for this plan.

## Next Phase Readiness

- Phase 1 is now complete: virtual CPT removed (Plan 01-01) and Etch filter integration live (Plan 01-02)
- Any Etch template can use `{#loop options.cws_jobs as job}{job.title}{/loop}` to iterate over configured API jobs
- Phase 2 (single job routing) will extend `CWS_Core_Etch` with `$current_job_id` population via `template_redirect` hook — the Phase 2 stub property is already declared in the class
- Existing blocker from STATE.md still applies: a real WP page with slug matching `cws_core_job_slug` (default: `job`) must exist for Phase 2 single-job routing

---
*Phase: 01-core-integration*
*Completed: 2026-03-02*
