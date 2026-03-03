---
phase: 08-preview-fallback
plan: 01
subsystem: admin
tags: [wordpress, settings-api, etch, preview, wp-rest-api]

# Dependency graph
requires:
  - phase: 07-field-groupings
    provides: "Field groupings option pattern for settings registration and Etch inject_options() context"
provides:
  - "cws_core_preview_job_id WordPress option — admin-configurable preview job ID"
  - "Etch Preview Job ID field in URL Configuration settings section"
  - "Updated Etch preview block with priority-ordered fallback: configured ID → first configured job"
  - "REST_REQUEST-aware preview resolution (supports Etch builder REST context)"
affects: [future-phases-using-etch-preview]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "REST_REQUEST guard in inject_options() ensures preview job resolves in Etch builder's AJAX/REST context where template_redirect never fires"
    - "Graceful fallback chain: configured preview ID → first configured job ID — each tier guarded before API call"

key-files:
  created: []
  modified:
    - includes/class-cws-core-admin.php
    - includes/class-cws-core-etch.php

key-decisions:
  - "resolve_preview_job() extracted as private method and called from inject_options() when REST_REQUEST is true — Etch builder fires REST calls with a blank template, so template_redirect never runs in that context"
  - "elseif fallback in preview block: when configured ID returns no API data, falls back to first configured job rather than yielding empty preview (PREV-03 requirement)"

patterns-established:
  - "REST_REQUEST detection pattern: check defined('REST_REQUEST') && REST_REQUEST in filter callbacks to handle Etch builder's separate REST-based data injection path"

requirements-completed: [PREV-01, PREV-02, PREV-03]

# Metrics
duration: ~25min
completed: 2026-03-03
---

# Phase 8 Plan 01: Preview Fallback Summary

**Admin-configurable Etch preview job ID via WordPress Settings API, with REST-context resolution for the Etch builder and graceful fallback to first configured job when unset or API returns no data**

## Performance

- **Duration:** ~25 min
- **Started:** 2026-03-03
- **Completed:** 2026-03-03
- **Tasks:** 3 (2 auto + 1 human-verify)
- **Files modified:** 2

## Accomplishments
- New "Etch Preview Job ID" field in the URL Configuration settings section — admin can save any job ID as the builder preview fallback
- Updated Etch preview block reads `cws_core_preview_job_id` option first, then falls back to `reset($job_ids)` — existing behavior fully preserved when field is empty
- Discovered and fixed a REST API context bug: Etch builder makes separate REST calls with a blank template, so `template_redirect` never fires there; `resolve_preview_job()` private method extracted and called from `inject_options()` when `REST_REQUEST` is true

## Task Commits

Each task was committed atomically:

1. **Task 1: Register option and render settings field in admin** - `f7edb74` (feat)
2. **Task 2: Update Etch preview block to use configured preview job ID** - `aec974c` (feat)
3. **Task 2b: Fix — resolve preview job in REST API context for Etch builder** - `804d424` (fix)
4. **Task 3: Human verify** - Approved by human (no code commit)

## Files Created/Modified
- `includes/class-cws-core-admin.php` - Added `register_setting()` for `cws_core_preview_job_id`, `add_settings_field()` in `cws_core_url_section`, `render_preview_job_id_field()` method with conditional description text, and `sanitize_preview_job_id()` method
- `includes/class-cws-core-etch.php` - Updated Etch preview block with priority-ordered resolution (`configured_preview` → `reset($job_ids)`), added `elseif` API-failure fallback, extracted `resolve_preview_job()` private method, added `REST_REQUEST` guard in `inject_options()`

## Decisions Made
- `resolve_preview_job()` extracted as a private method callable from both `handle_single_job()` (template context) and `inject_options()` (REST context) — the Etch builder uses a blank page template and fires separate REST API calls to populate dynamic data, meaning `template_redirect` never runs in that execution path
- `elseif` fallback when configured ID returns `false` from the API: re-tries with `reset($job_ids)` rather than leaving the preview empty — matches the spirit of PREV-03 (existing behavior preserved on failure)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Preview job not resolving in Etch builder REST API context**
- **Found during:** Task 3 (human verification in Etch builder)
- **Issue:** The Etch builder loads a blank template and makes separate REST calls for dynamic data (`etch/dynamic_data/option` filter). The original fix assumed `template_redirect` would have already run to store the preview job, but in the REST context this hook never fires. The preview block in `handle_single_job()` was not reachable from the REST path.
- **Fix:** Extracted `resolve_preview_job()` private method containing the configured-ID → fallback logic. Called it from both `handle_single_job()` (existing template path) and added a `REST_REQUEST` guard branch in `inject_options()` so the Etch builder's REST calls also receive the configured preview job in `cws_job`.
- **Files modified:** `includes/class-cws-core-etch.php`
- **Verification:** Human-verified in Etch builder (`?etch=magic`) — preview job appeared correctly using the admin-configured ID
- **Committed in:** `804d424`

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Required to make the feature actually work in the Etch builder context. No scope creep — the fix is a direct consequence of how Etch's REST-based data injection interacts with WordPress hook timing.

## Issues Encountered
- Etch builder uses a REST API context for dynamic data population — `template_redirect` does not fire, so the `handle_single_job()` preview block is unreachable from that path. Resolved by extracting shared `resolve_preview_job()` logic and calling it from a `REST_REQUEST` branch in `inject_options()`.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Phase 8 is the final phase of v1.1. All requirements PREV-01, PREV-02, PREV-03 satisfied.
- v1.1 milestone (Admin Tooling & Dynamic Groupings) is complete — Phases 5–8 all done.
- No blockers. Ready for `/gsd:new-milestone` to plan v1.2 or tag v1.1.

---
*Phase: 08-preview-fallback*
*Completed: 2026-03-03*
