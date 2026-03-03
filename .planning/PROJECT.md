# CWS Core — Etch Job Integration

## What This Is

A WordPress plugin that fetches job data from a public API and makes it available as dynamic data in the Etch page builder. Site editors design job listing and single-job templates in Etch using `{options.cws_jobs}` and `{options.cws_job}` — standard Etch loop and option syntax. The plugin handles API fetching, caching, URL routing, data injection, and admin tooling. Admins can monitor cache health, configure custom API query parameters, define field-based job groupings for Etch templates, and set a specific preview job for the Etch builder.

## Core Value

Job data from the external API is reliably available in any Etch template via the official `etch/dynamic_data/option` filter — works across Etch upgrades, requires no workarounds.

## Requirements

### Validated

- ✓ API client fetches jobs from `https://jobsapi-internal.m-cloud.io/api/stjob` — existing
- ✓ Transient-based caching with configurable duration — existing
- ✓ Admin settings page for API endpoint, org ID, job IDs, cache duration — existing
- ✓ Pretty URL routing for `/job/{id}/` via WordPress rewrite rules — existing
- ✓ Debug logging with debug mode toggle — existing
- ✓ Inject all jobs as `{options.cws_jobs}` array via `etch/dynamic_data/option` filter — v1.0
- ✓ Inject single job as `{options.cws_job}` when on a `/job/{id}/` URL — v1.0
- ✓ Load a real WordPress page as the job detail template — v1.0
- ✓ Etch builder preview support — fallback to first configured job when `?etch=magic` — v1.0
- ✓ Remove `class-cws-core-virtual-cpt.php` and all virtual post infrastructure — v1.0
- ✓ All job API fields accessible via dot notation; `open_date_formatted`, `update_date_formatted` available — v1.0
- ✓ Consistent field schema across `{options.cws_jobs}` listing and `{options.cws_job}` single via `format_job_data()` — v1.0
- ✓ Cache status tracking — last fetch success/fail, HTTP status code, timestamp visible in admin — v1.1
- ✓ Cache management controls — clear all jobs cache, clear per-job transients, see cache age — v1.1
- ✓ Query parameters UI — key/value repeater in admin settings; params appended to API requests — v1.1
- ✓ Field groupings UI — define field name in admin; plugin auto-exposes `{options.cws_jobs_by_{field}}` in Etch — v1.1
- ✓ Configurable builder preview fallback — admin setting for which job ID loads when `?etch=magic` is active — v1.1

### Active

*(None — fresh for next milestone)*

### Out of Scope

- Virtual CPT / `get_post_metadata` hook approach — replaced
- Database-backed job sync — staying API-first with transient cache
- Search/filter UI — deferred
- Pagination of job listings — deferred (Etch team hook pending)
- REST API endpoints for job data — deferred
- Per-field sanitize/format hooks — deferred (current format_job_data() handles all cases)

## Context

**Shipped v1.2** with ~3,866 LOC PHP/JS/CSS. Tech stack: PHP, WordPress 5.0+, Etch v1.3.1.

The virtual CPT approach (2,494 lines, 20+ hooks) has been fully removed (v1.0). Admin tooling added in v1.1 gives full visibility and control over the API connection, including cache monitoring, custom query parameters, field-based groupings, and preview job configuration. v1.2 closed all v1.1 audit gaps — status metadata is now written on all `fetch_job_data()` error paths and `uninstall.php` covers all 9 `cws_core_*` wp_options.

**Etch template usage:**
- Listing page: `{````#loop```` options.cws_jobs as job}...{/loop}`
- Single page: `{options.cws_job.title}`, `{options.cws_job.description}`, etc.
- Preview in builder: uses admin-configured preview job ID, falls back to first configured job
- Date fields: `{options.cws_job.open_date_formatted}`, `{options.cws_job.update_date_formatted}`
- Field groupings: `{options.cws_jobs_by_primary_category}`, `{options.cws_jobs_by_primary_city}`, etc. (configured in settings)

**Known issues / tech debt:**
- GAP-3 (pre-existing from v1.0): `uninstall.php` missing cleanup for 3 v1.0-era wp_options — `cws_core_job_slug`, `cws_core_job_ids`, `cws_core_job_template_page_id`
- `@var CWS_Core_Public` PHPDoc comment on dead `$public` property in `class-cws-core.php` (harmless)

## Constraints

- **Tech stack**: PHP, WordPress 5.0+, no Composer dependencies — must stay consistent with existing plugin
- **Etch compatibility**: Must use only `etch/dynamic_data/option` filter (stable, documented hook) — no internal Etch class hooks
- **API**: External API is read-only, organization ID auth, no rate limiting on our side
- **Backwards compatibility**: Admin settings and URL structure stay the same — `/job/{id}/` URLs must continue to work
- **Breaking change in v1.1**: `cws_jobs_by_category` and `cws_jobs_by_city` hardcoded Etch variables removed — existing templates require Field Groupings configuration in admin

## Key Decisions

| Decision | Rationale | Outcome |
| --- | --- | --- |
| Use `etch/dynamic_data/option` filter | Official Etch hook, documented, survives upgrades | ✓ Good — clean integration, confirmed stable in v1.3.1 |
| Replace virtual CPT entirely | 2494-line file, fragile meta hook approach, root cause of breakage | ✓ Good — 2,511 LOC result vs original ~5k+; no more upgrade breakage |
| Keep pretty URLs `/job/{id}/` | Better SEO than query params, user preference | ✓ Good — unchanged, works correctly |
| Load real WP page for single job template | Required for Etch to render its template system against a real page | ✓ Good — `$post`/`$wp_query` swap pattern works well |
| Reuse existing API and cache classes | They work well, no reason to rebuild | ✓ Good — no changes needed |
| Store job at `template_redirect`, read at filter | Etch fires `the_content` at priority 1; data must be ready before filter | ✓ Good — `$current_job` property pattern is clean |
| `format_job_data()` as single normalization path | Consistent schema between listing and single-job contexts | ✓ Good — closes cross-phase inconsistency gap |
| Status code 0 for WP_Error (connection failure) | No HTTP code available from WP_Error; 0 is a sentinel value | ✓ Good — admin UI handles this as "connection error" label |
| Status options not written on cache-hit path | Metadata should reflect real API attempts only, not cache reads | ✓ Good — clean separation |
| `delete_option()` in `clear_all()` after transient cleanup | Reset both cache and status metadata atomically when clearing | ✓ Good — consistent state |
| Indexed name attributes for query params repeater (`cws_core_query_params[N][key]`) | Native PHP array from form submission — no json_encode needed | ✓ Good — clean sanitize callback |
| `sanitize_key()` on keys + `urlencode()` on values in `build_api_url()` | Safe URL construction | ✓ Good — prevents injection |
| No fallback auto-creation of grouped variables | Admins must explicitly configure field names — avoids unexpected variable collisions | ✓ Good — predictable behavior |
| Flat array of strings for `cws_core_field_groupings` | Field names are atomic — no value component needed | ✓ Good — simpler than key/value repeater |
| Non-scalar field values silently skipped in grouping loop | Prevents PHP warnings on array fields | ✓ Good — clean guard |
| `resolve_preview_job()` extracted as private method | Called from both `handle_single_job()` and `inject_options()` — shared logic | ✓ Good — single source of truth for preview resolution |
| `elseif` fallback in `resolve_preview_job()` — retry with `reset($job_ids)` when configured ID returns no data | Graceful degradation when configured preview job is unavailable | ✓ Good — builder never shows blank fields |
| `REST_REQUEST` guard in `inject_options()` | Etch builder uses REST API context where `template_redirect` never fires — preview resolution must also work there | ✓ Good — discovered and fixed during Phase 8 execution |
| Sentinel value `0` for status on JSON parse / invalid structure failure | No HTTP code available for these paths; `0` is distinct from `null` (no fetch yet) and already handled by `render_cache_status_block()` | ✓ Good — v1.2 |
| No PHP-side change needed for `testVirtualCPT` removal | The AJAX action `cws_core_test_virtual_cpt` was never registered server-side — removing JS-only was sufficient | ✓ Good — v1.2 |

---
*Last updated: 2026-03-03 after v1.2 milestone*
