---
phase: 07-field-groupings
plan: 01
subsystem: admin
tags: [wordpress, settings-api, repeater, etch, dynamic-data]

# Dependency graph
requires:
  - phase: 06-query-params
    provides: "Query params repeater pattern (register_setting, add_settings_field, JS repeater, CSS row styles) used as direct template for field groupings repeater"
provides:
  - "cws_core_field_groupings WordPress option — flat array of field name strings"
  - "render_field_groupings_field() admin repeater UI with indexed name attributes"
  - "sanitize_field_groupings() callback strips empty strings, re-indexes"
  - "addFieldGroupingRow / removeFieldGroupingRow JS handlers in admin.js"
  - "Dynamic inject_options() loop replacing hardcoded cws_jobs_by_category and cws_jobs_by_city"
affects: [etch-templates, any-template-using-cws_jobs_by_category-or-cws_jobs_by_city]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Flat-array repeater (single input per row) vs key/value repeater from phase 06"
    - "Dynamic Etch option key construction: cws_jobs_by_{field_name}"
    - "Non-scalar field guard: is_string || is_numeric before sanitize_title() bucketing"

key-files:
  created: []
  modified:
    - includes/class-cws-core-admin.php
    - includes/class-cws-core-etch.php
    - admin/js/admin.js
    - admin/css/admin.css

key-decisions:
  - "No fallback auto-creation of cws_jobs_by_category or cws_jobs_by_city — admins must explicitly configure field names to get those groupings back"
  - "Flat array of strings (not key/value pairs) — field names stored as cws_core_field_groupings[N] indexed inputs"
  - "Non-scalar field values (location, permalink, raw_data arrays) silently skipped via is_string/is_numeric guard — no PHP warnings, no grouping produced"
  - "sanitize_title() on bucket key preserves same slug behavior as old hardcoded logic ('Product Management' -> 'product-management')"

patterns-established:
  - "Flat-array repeater: single input name=option[N], JS re-indexes with direct .attr('name', ...) not regex replace"
  - "Dynamic option key: 'cws_jobs_by_' . $field_name pattern for Etch variable construction"

requirements-completed: [GROUP-01, GROUP-02, GROUP-03]

# Metrics
duration: 2min
completed: 2026-03-03
---

# Phase 7 Plan 01: Field Groupings Summary

**Admin-configurable repeater replaces hardcoded category/city groupings with dynamic cws_jobs_by_{field} Etch variables driven by a WordPress option**

## Performance

- **Duration:** ~2 min
- **Started:** 2026-03-03T12:43:46Z
- **Completed:** 2026-03-03T12:45:45Z
- **Tasks:** 3
- **Files modified:** 4

## Accomplishments
- Registered `cws_core_field_groupings` WordPress option with sanitize callback that strips empty strings and re-indexes
- Admin repeater field in API Configuration section: add/remove rows for field names, each producing a `{options.cws_jobs_by_{field}}` Etch variable
- Replaced 28-line hardcoded `$by_category`/`$by_city` block in `inject_options()` with 33-line dynamic loop reading the option, iterating configured field names, and bucketing jobs by sanitized field value — including a non-scalar guard for array fields

## Task Commits

Each task was committed atomically:

1. **Task 1: Register option and render repeater field in admin PHP** - `bb9dbc4` (feat)
2. **Task 2: JS add/remove handlers and repeater row CSS** - `207295d` (feat)
3. **Task 3: Replace hardcoded groupings with dynamic loop in inject_options()** - `771043a` (feat)

## Files Created/Modified
- `includes/class-cws-core-admin.php` - Added register_setting(), add_settings_field(), render_field_groupings_field(), sanitize_field_groupings() methods
- `includes/class-cws-core-etch.php` - Replaced hardcoded by_category/by_city block with dynamic field_groupings loop
- `admin/js/admin.js` - Added addFieldGroupingRow and removeFieldGroupingRow methods, bound in bindEvents()
- `admin/css/admin.css` - Added .cws-core-field-grouping-row layout and input max-width rules

## Decisions Made
- No fallback auto-creation of `cws_jobs_by_category` or `cws_jobs_by_city` — admins who used those variables in Etch templates must add "primary_category" and "primary_city" to the Field Groupings setting to restore them
- Flat array of strings chosen over key/value pairs — field names are atomic, no value component needed
- Non-scalar field values (location, permalink, raw_data — all arrays in format_job_data()) silently skipped rather than erroring — prevents PHP warnings and is the correct behavior since arrays cannot be used as grouping sources
- `sanitize_title()` on bucket key preserves the same slug behavior as the old hardcoded logic

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

The Edit tool failed to match the old block in `class-cws-core-etch.php` due to tab character handling differences between the Read tool display and the actual file bytes. Resolved by using a Python script to perform the string replacement with exact tab characters.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Field groupings feature is complete and production-ready
- Template authors using `{options.cws_jobs_by_category}` or `{options.cws_jobs_by_city}` must configure the new Field Groupings setting to restore those variables
- Ready for any remaining phases in the v1.1 milestone

---
*Phase: 07-field-groupings*
*Completed: 2026-03-03*

## Self-Check: PASSED

- All 4 modified files found on disk
- All 3 task commits verified in git log (bb9dbc4, 207295d, 771043a)
- SUMMARY.md created at .planning/phases/07-field-groupings/07-01-SUMMARY.md
