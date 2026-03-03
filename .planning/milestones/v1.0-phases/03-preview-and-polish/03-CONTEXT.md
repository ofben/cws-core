# Phase 3: Preview and Polish - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Enable the Etch builder (`?etch=magic`) to show real job data when editing the job template page — editors should never see blank `{options.cws_job.*}` fields while designing. Add pre-formatted date fields to the job data structure. Ensure admin "Clear Cache" reliably wipes all job-related transients, including per-job keys.

This phase does NOT add new settings pages, new admin UI, or new URL routing. It polishes the Phase 1+2 foundation.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion
All implementation decisions fall within Claude's discretion — the roadmap plans are specific enough and the user deferred to the existing direction:

- **Preview trigger**: Simple detection — `isset($_GET['etch']) && 'magic' === $_GET['etch'] && is_user_logged_in() && empty(get_query_var('cws_job_id'))`. Fires in `handle_single_job()` on `template_redirect`. No `post_id` check required — keeping it simple matches the roadmap plan description.

- **Sample job for preview**: Use the first configured job ID from `$this->plugin->get_configured_job_ids()`. If that list is empty, log a notice and return without injecting — do not 404, as the editor may still want to design the template shell.

- **Date format**: `F j, Y` (e.g., "March 1, 2026") as specified in the roadmap. Applied via PHP `date_create()` + `date_format()` or `wp_date()`. Two new keys added to `format_job_data()`: `open_date_formatted` and `update_date_formatted`. If the raw date is empty/null → empty string (consistent with other empty fields).

- **Cache clear scope**: Audit existing `clear_all()` SQL LIKE pattern (`_transient_cws_core_%`). The per-job transient keys are `cws_core_job_data_{md5(url)}` — these match the pattern and should already be deleted. If the LIKE underscore-as-wildcard behaviour causes any gaps, escape or verify. No new UI needed — the existing "Clear Cache" button is the mechanism.

- **cws_jobs in builder preview**: The `inject_options` filter fires for all Etch page renders including `?etch=magic`. No extra handling needed — `cws_jobs` will populate automatically via existing code path as long as job IDs are configured.

</decisions>

<specifics>
## Specific Ideas

No specific requirements beyond the roadmap — standard approaches apply.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CWS_Core_Etch::handle_single_job()` — already runs on `template_redirect`; the `?etch=magic` preview fallback is a short branch added at the top of this method (before the `cws_job_id` check)
- `CWS_Core_Etch::inject_options()` — already injects `cws_job` and `cws_jobs`; no changes needed here for preview
- `CWS_Core_Cache::clear_all()` — existing SQL LIKE delete; needs audit to confirm per-job keys are covered
- `CWS_Core_API::format_job_data()` — all date-related changes go here; `open_date` and `update_date` already present as raw strings

### Established Patterns
- All data injected into Etch via `etch/dynamic_data/option` filter — already wired, no hook changes needed
- `$this->plugin->get_configured_job_ids()` — already exists for fetching configured job IDs from options
- `$this->plugin->log()` — use for debug messages in new branches
- `wp_date()` — preferred WordPress function for date formatting (handles timezone from WP settings)

### Integration Points
- Preview branch goes in `handle_single_job()` before the early return: detect `?etch=magic`, grab first job ID, call `get_job()` + `format_job_data()`, assign to `$this->current_job` / `$this->current_job_id`
- `format_job_data()` in `class-cws-core-api.php` — add two new keys alongside existing `open_date` and `update_date`
- `clear_cache_ajax()` in `class-cws-core-cache.php` — calls `clear_all()`; no interface change, just verify SQL covers all key patterns

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-preview-and-polish*
*Context gathered: 2026-03-01*
