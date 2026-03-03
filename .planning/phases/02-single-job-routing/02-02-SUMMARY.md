---
phase: 02-single-job-routing
plan: 02
subsystem: admin
tags: [wordpress, settings-api, rewrite-rules, admin-ui]

# Dependency graph
requires:
  - phase: 02-single-job-routing
    provides: CWS_Core_Etch handle_single_job() routing infrastructure built in plan 02-01

provides:
  - cws_core_job_template_page_id setting registration (integer, absint sanitize, default 0)
  - render_job_template_page_field() — page dropdown with inline health check warning
  - flush_rules_on_slug_change() — automatic rewrite flush on slug option change

affects:
  - 02-single-job-routing plan 03 (template redirect reads cws_core_job_template_page_id)
  - 03-preview-polish (admin UI polish may extend this settings section)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - WordPress Settings API register_setting + add_settings_field pattern with absint sanitize for integer options
    - Inline health check warning inside field renderer (not via admin_notices) for settings-page-only feedback
    - update_option_{option_name} action hook for reactive side-effects on option change

key-files:
  created: []
  modified:
    - includes/class-cws-core-admin.php

key-decisions:
  - "Inline health check in field renderer, not admin_notices — warning scoped to the settings page only, not site-wide banner"
  - "flush_rules_on_slug_change() guarded by old !== new check — prevents no-op flush when slug saved unchanged"
  - "absint sanitize_callback for job_template_page_id — WordPress built-in, no custom method needed"

patterns-established:
  - "Inline field warnings: use echo '<p class=\"description\" style=\"color:#d63638;\">' inside field renderer, never admin_notices"
  - "Option-change side-effects: use update_option_{name} hook with (old, new, option) signature at priority 10"

requirements-completed:
  - "Admin must be able to designate which WP page serves as the job template"
  - "If the job template WP page is missing, the admin settings page shows a visible warning"
  - "Changing the job slug setting automatically flushes rewrite rules"

# Metrics
duration: 2min
completed: 2026-03-01
---

# Phase 2 Plan 02: Admin Job Template Page Setting Summary

**Admin settings extended with a Job Template Page dropdown, inline health check warning, and automatic rewrite rule flushing on slug change — all in class-cws-core-admin.php**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-01T04:26:16Z
- **Completed:** 2026-03-01T04:28:00Z
- **Tasks:** 2
- **Files modified:** 1

## Accomplishments

- Added `cws_core_job_template_page_id` setting (integer, `absint` sanitize, default `0`) to the Settings API
- Rendered a `get_pages()` dropdown in the URL Configuration section with inline health check warning (red text) when no valid published page is selected
- Hooked `update_option_cws_core_job_slug` to auto-flush rewrite rules only when the value actually changes

## Task Commits

Each task was committed atomically:

1. **Task 1: Register cws_core_job_template_page_id setting and field** - `f259e2b` (feat)
2. **Task 2: Add automatic rewrite flush on slug change** - `0932fc1` (feat)

**Plan metadata:** _(docs commit added after summary)_

## Files Created/Modified

- `includes/class-cws-core-admin.php` — Added `register_setting` block, `add_settings_field` call, `render_job_template_page_field()` method, `update_option_cws_core_job_slug` hook, and `flush_rules_on_slug_change()` method

## Decisions Made

- Inline health check in field renderer, not `admin_notices` — warning scoped to the settings page only, not a site-wide banner that nags on every admin screen
- `flush_rules_on_slug_change()` guarded by `$old_value !== $new_value` — prevents a pointless `flush_rewrite_rules()` call when the admin saves the settings form without changing the slug
- Used WordPress built-in `absint` as the sanitize callback for the page ID integer — no custom method needed

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- `cws_core_job_template_page_id` setting is available for Plan 02-03 (`template_redirect` handler) to read the designated WP page
- Inline health check will alert the admin if they forget to select a template page before testing single-job URLs
- Auto-flush removes the "forgot to flush" support issue when the job slug changes

---
*Phase: 02-single-job-routing*
*Completed: 2026-03-01*

## Self-Check: PASSED

- FOUND: includes/class-cws-core-admin.php
- FOUND: .planning/phases/02-single-job-routing/02-02-SUMMARY.md
- FOUND commit: f259e2b (feat(02-02): register cws_core_job_template_page_id setting and field)
- FOUND commit: 0932fc1 (feat(02-02): auto-flush rewrite rules when job slug changes)
- php -l: No syntax errors detected
