---
phase: 04-integration-cleanup
plan: 01
subsystem: plugin
tags: [wordpress, etch, api, cleanup]

# Dependency graph
requires:
  - phase: 03-preview-polish
    provides: "Etch integration with single-job routing, date formatting, and preview support"
provides:
  - "CWS_Core_Public (the_content injection) removed — Etch filter is sole job display path"
  - "cws_jobs listing items normalized via format_job_data() — same schema as cws_job single items"
  - "load_job_template() dead method removed from class-cws-core.php"
  - "cws_core_job_template_page_id seeded at activation with default 0"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "array_map( array( $this->plugin->api, 'format_job_data' ), $jobs ) wraps raw API results before injecting into cws_jobs"

key-files:
  created: []
  modified:
    - includes/class-cws-core-etch.php
    - includes/class-cws-core.php
    - cws-core.php
  deleted:
    - includes/class-cws-core-public.php

key-decisions:
  - "Leave @var CWS_Core_Public PHPDoc on the $public property — property declaration is harmless dead code, plan explicitly preserved it"
  - "array_values() kept as outer wrapper around array_map() — preserves zero-indexed keys for Etch loop renderer"

patterns-established:
  - "format_job_data() is the single normalization path for all job data exposed to Etch templates"

requirements-completed:
  - "Remove legacy the_content injection"
  - "Normalize listing items through format_job_data()"
  - "Remove dead code"

# Metrics
duration: 2min
completed: 2026-03-02
---

# Phase 4 Plan 01: Integration Cleanup Summary

**Removed the_content injection class (CWS_Core_Public), normalized cws_jobs listing items through format_job_data() to match cws_job single-item schema, and eliminated two pieces of dead code**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-02T12:18:26Z
- **Completed:** 2026-03-02T12:20:00Z
- **Tasks:** 2 of 3 complete (Task 3 is human-verify checkpoint)
- **Files modified:** 3 modified, 1 deleted

## Accomplishments

- Deleted `includes/class-cws-core-public.php` — the legacy `the_content` injection class can no longer interfere with Etch rendering
- Normalized all `cws_jobs` listing items through `format_job_data()` — `{job.open_date_formatted}`, `{job.location.city}`, etc. now resolve in listing templates using the same field schema as single-job pages
- Removed dead `load_job_template()` method from `class-cws-core.php`
- Seeded `cws_core_job_template_page_id` default (`0`) in the activation hook

## Task Commits

Each task was committed atomically:

1. **Task 1: Remove CWS_Core_Public class and all its wiring** - `4dcf7a5` (feat)
2. **Task 2: Normalize cws_jobs listing items, remove dead load_job_template(), seed activation default** - `c7117e4` (feat)
3. **Task 3: Human verify** - pending checkpoint

## Files Created/Modified

- `includes/class-cws-core-public.php` - DELETED (the_content injection class, superseded by Etch filter)
- `cws-core.php` - Removed `require_once` for deleted class; added `cws_core_job_template_page_id` default in activation
- `includes/class-cws-core.php` - Removed `CWS_Core_Public` instantiation and `$this->public->init()` call; deleted `load_job_template()` method
- `includes/class-cws-core-etch.php` - Wrapped `cws_jobs` assignment with `array_map( format_job_data )`

## Decisions Made

- Left the `@var CWS_Core_Public` PHPDoc comment on the `$public` property in `class-cws-core.php` — the plan explicitly specified to leave the property declaration; the `@var` type hint is a harmless dead comment
- `array_values()` kept as outer wrapper around `array_map()` — `array_map` does not re-index keys, so the wrapper is necessary for Etch's loop renderer

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Tasks 1 and 2 complete and committed
- Task 3 (human-verify checkpoint) requires manual frontend verification:
  1. Visit `/job/{id}/` — confirm no raw job HTML appended after template content
  2. Verify `{job.open_date_formatted}` and `{job.location.city}` render on listing pages
  3. Check WP_DEBUG shows no errors
  4. Deactivate/reactivate plugin; verify `cws_core_job_template_page_id` option exists

---
*Phase: 04-integration-cleanup*
*Completed: 2026-03-02*
