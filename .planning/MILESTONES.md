# Milestones

## v1.2 Tech Debt & Stability (Shipped: 2026-03-03)

**Phases completed:** 1 phase (9), 1 plan
**Files changed:** 3 | **PHP/JS LOC:** ~3,866 | **Timeline:** 2026-03-03 (1 day)
**Commits:** 3 | **Git range:** a69e7fb → 1f04c07

**Delivered:** All v1.1 audit gaps closed — error-path status writes complete, uninstall cleanup covers all 9 wp_options, dead `testVirtualCPT` JS removed. Plugin internals are now consistent with the admin UI guarantees made in v1.1.

**Key accomplishments:**
- `fetch_job_data()` now writes status metadata (`cws_core_last_fetch_time` + `cws_core_last_fetch_status=0`) on all 5 error paths including JSON parse failure and invalid response structure — GAP-1 closed
- `uninstall.php` now deletes all 9 `cws_core_*` wp_options (4 original + 5 added in v1.1), preventing orphaned data after plugin removal — GAP-2 closed
- Dead `testVirtualCPT` method and event binding removed from `admin.js` (~95 lines) — no server-side handler ever existed

**Tech debt carried forward:**
- GAP-3 (pre-existing from v1.0): 3 additional wp_options missing from uninstall.php — `cws_core_job_slug`, `cws_core_job_ids`, `cws_core_job_template_page_id`

---

## v1.1 Admin Tooling & Dynamic Groupings (Shipped: 2026-03-03)

**Phases completed:** 4 phases (5–8), 5 plans
**Files changed:** 55 | **PHP LOC:** ~2,511 | **Timeline:** 2026-03-03 (1 day)
**Commits:** 36 | **Git range:** feat(05-01) → docs(phase-08)

**Delivered:** Site editors gain full visibility and control over the plugin's API configuration — cache health, custom query parameters, field-based job groupings, and a configurable Etch builder preview job.

**Key accomplishments:**
- Cache status visibility — records fetch timestamp + HTTP status code to wp_options after every live API attempt; admin settings page shows 4 states (no cache, success, HTTP error, connection error)
- Live cache management — AJAX cache clear resets status display without page reload; `clear_all()` deletes status metadata alongside transients
- Custom query parameters — admin key/value repeater stores params to wp_options; `build_api_url()` appends all configured params to every outgoing API request
- Dynamic field groupings — admin-configurable repeater replaces hardcoded `cws_jobs_by_category`/`cws_jobs_by_city`; any API field becomes `{options.cws_jobs_by_{field}}` in Etch templates
- Configurable Etch preview job — admin sets a specific job ID as the builder preview fallback; `resolve_preview_job()` handles both frontend (`?etch=magic`) and Etch's REST API context

**Tech debt carried forward:**
- `fetch_job_data()` does not write status metadata on JSON parse failure / invalid response structure paths (stale display only — edge case)
- `uninstall.php` missing cleanup for all 5 v1.1 wp_options
- Breaking change: `cws_jobs_by_category`/`cws_jobs_by_city` removed — existing templates require Field Groupings configuration

---

## v1.0 Dynamic Data Rebuild (Shipped: 2026-03-03)

**Phases completed:** 4 phases, 8 plans
**Files changed:** 55 | **PHP LOC:** ~2,167 | **Timeline:** 2025-08-31 → 2026-03-02

**Delivered:** Job data from the external API is reliably available in any Etch template via the official `etch/dynamic_data/option` filter — listing pages, single job pages, and builder preview all work without WordPress CPT infrastructure.

**Key accomplishments:**
- Deleted `class-cws-core-virtual-cpt.php` (2,494 lines, 20+ hooks) and 17 associated files — fragile virtual CPT fully removed
- Created `class-cws-core-etch.php` — injects all jobs as `{options.cws_jobs}` via `etch/dynamic_data/option`; survives Etch upgrades
- Wired `template_redirect` so `/job/{id}/` loads a real WP page with `{options.cws_job.*}` populated; 404 for unknown IDs
- Added admin job template page selector with inline health check and automatic rewrite flush on slug change
- Etch builder preview (`?etch=magic`) shows real job data instead of blank fields
- Removed legacy `the_content` injection; normalized all `cws_jobs` items through `format_job_data()` — consistent schema across listing and single-job contexts

---
