# Phase 9: Tech Debt Cleanup - Context

**Gathered:** 2026-03-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Close the 4 specific gaps identified in the v1.1 milestone audit (GAP-1, GAP-2, and two tech-debt items). This phase fixes known bugs and removes dead code — it does not add new features or change any admin-visible behavior beyond correcting stale cache status display.

</domain>

<decisions>
## Implementation Decisions

### Error status metadata (GAP-1)
- Both failure paths in `fetch_job_data()` should write status metadata before returning `false`
- **JSON parse failure** (class-cws-core-api.php ~line 174): write `time()` to `cws_core_last_fetch_time` and `0` to `cws_core_last_fetch_status`
- **Invalid response structure** (class-cws-core-api.php ~line 182): same — write `time()` and `0`
- Sentinel value `0` chosen: clearly not an HTTP code (all real HTTP codes are positive), distinct from the null/"no fetch yet" state, easy to branch on in admin display logic

### Uninstall cleanup (GAP-2)
- Add all 5 v1.1 options to `$options_to_delete` in `uninstall.php`:
  - `cws_core_query_params`
  - `cws_core_field_groupings`
  - `cws_core_preview_job_id`
  - `cws_core_last_fetch_time`
  - `cws_core_last_fetch_status`

### Dead code removal scope
- Remove full footprint of `testVirtualCPT` from `admin/js/admin.js`:
  - Line 38 event binding: `$('#cws-core-test-virtual-cpt').on('click', this.testVirtualCPT)`
  - Full `testVirtualCPT` method body (lines ~220+)
- No PHP AJAX handler exists for `cws_core_test_virtual_cpt` — nothing to remove server-side
- `nonces.test_virtual_cpt` is not registered in the PHP localize script — no PHP-side change needed

### Claude's Discretion
- Exact formatting/ordering of the new uninstall options array entries
- Whether to add inline comments distinguishing v1.0 vs v1.1 options in uninstall.php

</decisions>

<specifics>
## Specific Ideas

- Fix must match existing pattern: the HTTP error path (lines ~164-170) already writes `time()` + HTTP code — the two new paths should follow the same two-update pattern
- User confirmed: remove "any code that is no longer relevant" — full testVirtualCPT footprint, not just the method

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `update_option()` calls already present on success and HTTP-error paths — same pattern used for the two new error paths

### Established Patterns
- Status write pattern: always write `cws_core_last_fetch_time` first, then `cws_core_last_fetch_status`
- `$options_to_delete` array in uninstall.php uses bare option name strings, one per line

### Integration Points
- `cws_core_last_fetch_status` is read by the admin cache status display — value `0` must be handled gracefully (treat as failure, not success)
- `admin/js/admin.js` init method at line 38 binds all event handlers — removing the testVirtualCPT binding from here is required alongside removing the method

</code_context>

<deferred>
## Deferred Ideas

- REQUIREMENTS.md PREV-01/02/03 stale checkboxes — documentation artifact only, not a code gap; can be addressed as a one-line docs fix in this phase or noted for future cleanup

</deferred>

---

*Phase: 09-tech-debt-cleanup*
*Context gathered: 2026-03-03*
