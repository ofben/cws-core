---
phase: 01-core-integration
plan: 01
subsystem: plugin
tags: [wordpress, virtual-cpt, etch, cleanup, refactor]

# Dependency graph
requires: []
provides:
  - "Virtual CPT class, files, and wiring removed from plugin"
  - "class-cws-core.php has $etch property slot ready for Plan 01-02"
  - "handle_job_request() is a safe empty stub (no crash on /job/123/)"
  - "Admin panel renders without Virtual CPT Testing section"
affects: [01-02, 01-core-integration]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "class_exists() guard pattern for optional integration classes (CWS_Core_Etch)"
    - "Empty stub method with comment citing next plan for deferred implementation"

key-files:
  created: []
  modified:
    - cws-core.php
    - includes/class-cws-core.php
    - includes/class-cws-core-admin.php

key-decisions:
  - "Do NOT add require_once for class-cws-core-etch.php in cws-core.php yet — Plan 01-02 adds it when it creates the file to avoid PHP fatal on missing file"
  - "Keep handle_job_request() hook registration in register_hooks() — only empty the method body; Phase 2 fills it"
  - "class_exists('CWS_Core\\CWS_Core_Etch') guard in init_components() makes Etch init silently no-op until Plan 01-02 creates the class"

patterns-established:
  - "Etch integration class wired via class_exists guard, never raw require_once without accompanying file"

requirements-completed:
  - "Remove virtual CPT infrastructure"

# Metrics
duration: 2min
completed: 2026-03-02
---

# Phase 1 Plan 01: Remove Virtual CPT Infrastructure Summary

**2,494-line virtual CPT class deleted along with 17 associated files; coordinator files surgically cleaned so plugin bootstraps cleanly with no virtual CPT hooks or dead code**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-02T03:48:29Z
- **Completed:** 2026-03-02T03:50:49Z
- **Tasks:** 2
- **Files modified:** 3 (+ 17 deleted)

## Accomplishments

- Deleted `includes/class-cws-core-virtual-cpt.php` (2,494 lines, 20+ WordPress hooks) and all 17 associated files
- Stripped all virtual CPT wiring from `cws-core.php`, `class-cws-core.php`, and `class-cws-core-admin.php` with zero remaining references
- Replaced `$virtual_cpt` property with `$etch = null` slot and safe class_exists guard, ready for Plan 01-02

## Task Commits

Each task was committed atomically:

1. **Task 1: Delete virtual CPT file, all test/debug scripts, and all template files** - `67bf16e` (chore)
2. **Task 2: Strip all virtual CPT wiring from coordinator files** - `5e0c94a` (feat)

**Plan metadata:** (created below as docs commit)

## Files Created/Modified

### Deleted (17 files)

- `includes/class-cws-core-virtual-cpt.php` — 2,494-line virtual CPT class (root cause of Etch upgrade breakage)
- `templates/job.php` — Virtual CPT job template
- `templates/job-debug-template.html` — Debug HTML template
- `templates/job-template-examples.html` — Example HTML template
- `debug-meta.php` — Debug script (git-tracked)
- `debug-query.php` — Debug script (git-tracked)
- `debug-virtual-posts.php` — Debug script (git-tracked)
- `test-virtual-cpt.php` — Test script (git-tracked)
- `debug-check.php` — Debug script (untracked, filesystem-only)
- `debug-template.php` — Debug script (untracked, filesystem-only)
- `debug-virtual-posts-simple.php` — Debug script (untracked, filesystem-only)
- `test-etchwp-meta.php` — Test script (untracked, filesystem-only)
- `test-etchwp-rest.php` — Test script (untracked, filesystem-only)
- `test-meta-query-simple.php` — Test script (untracked, filesystem-only)
- `test-meta-query.php` — Test script (untracked, filesystem-only)
- `test-unique-ids.php` — Test script (untracked, filesystem-only)
- `test-virtual-meta.php` — Test script (untracked, filesystem-only)

### Modified (3 files)

- `cws-core.php` — Removed `require_once` for `class-cws-core-virtual-cpt.php` (line 42). No Etch require_once added — Plan 01-02 does that when it creates the file.
- `includes/class-cws-core.php` — Replaced `$virtual_cpt` property with `$etch = null`; replaced virtual CPT init blocks with lean Etch class_exists guards; emptied `handle_job_request()` to early return stub; deleted `test_virtual_post()` method; removed all virtual-CPT-related `error_log()` calls.
- `includes/class-cws-core-admin.php` — Removed `wp_ajax_cws_core_test_virtual_cpt` action registration; removed `test_virtual_cpt` nonce from `enqueue_scripts()`; removed "Virtual CPT Testing" admin box HTML; deleted `test_virtual_cpt_ajax()` method (84 lines).

## Decisions Made

- Do NOT add `require_once` for `class-cws-core-etch.php` in `cws-core.php` — Plan 01-02 adds it when it creates the file to avoid a PHP fatal on missing file.
- Keep `handle_job_request()` hook registration in `register_hooks()` — only empty the method body; Phase 2 fills it in.
- `class_exists('CWS_Core\\CWS_Core_Etch')` guard in `init_components()` makes Etch init silently no-op until Plan 01-02 creates the class, preventing any crash.

## Deviations from Plan

None — plan executed exactly as written.

Note on git tracking: 9 of the 17 files were never git-tracked (added as `??` untracked in git status). They were deleted from the filesystem but could not be `git add`-staged since git had no record of them. The 8 git-tracked files were properly staged and committed. All 17 files are confirmed gone from disk.

## Issues Encountered

None.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Plugin bootstraps cleanly with no virtual CPT references — confirmed by zero grep hits across the three coordinator files
- `includes/class-cws-core.php` has `public $etch = null;` property slot and `class_exists('CWS_Core\\CWS_Core_Etch')` guard ready to activate when Plan 01-02 creates the file
- `cws-core.php` has no `require_once` for Etch yet — Plan 01-02 must add it alongside creating `includes/class-cws-core-etch.php`
- `templates/` directory is preserved (empty) for future phases

---
*Phase: 01-core-integration*
*Completed: 2026-03-02*
