# Milestones

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
