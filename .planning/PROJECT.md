# CWS Core — Etch Job Integration

## What This Is

A WordPress plugin that fetches job data from a public API and makes it available as dynamic data in the Etch page builder. Site editors design job listing and single-job templates in Etch using `{options.cws_jobs}` and `{options.cws_job}` — standard Etch loop and option syntax. The plugin handles API fetching, caching, URL routing, and data injection. The virtual CPT infrastructure has been fully replaced with the official Etch filter integration.

## Core Value

Job data from the external API is reliably available in any Etch template via the official `etch/dynamic_data/option` filter — works across Etch upgrades, requires no workarounds.

## Current Milestone: v1.1 Admin Tooling & Dynamic Groupings

**Goal:** Give site editors visibility and control over API configuration — cache status, query params, field-based job groupings, and a configurable builder preview fallback.

**Target features:**
- Cache status tracking (last fetch success/fail, HTTP code, timestamp)
- Cache management controls (clear all jobs cache, clear per-job transients)
- Query parameters UI (key/value repeater appended to API requests)
- Field groupings UI (define field → auto-expose `{options.cws_jobs_by_{field}}` in Etch)
- Configurable builder preview fallback (set job ID for `?etch=magic` in admin)

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

### Active

- [ ] Cache status tracking — last fetch success/fail, HTTP status code, timestamp visible in admin
- [ ] Cache management controls — clear all jobs cache; clear per-job transients; see cache age
- [ ] Query parameters UI — key/value repeater in admin settings; params appended to API requests
- [ ] Field groupings UI — define field name in admin; plugin auto-exposes `{options.cws_jobs_by_{field}}` in Etch
- [ ] Configurable builder preview fallback — admin setting for which job ID loads when `?etch=magic` is active

### Out of Scope

- Virtual CPT / `get_post_metadata` hook approach — replaced
- Database-backed job sync — staying API-first with transient cache
- Search/filter UI — deferred
- Pagination of job listings — deferred (Etch team hook pending per SnippetNest article)
- REST API endpoints for job data — deferred

## Context

**Shipped v1.0** with ~2,167 LOC PHP. Tech stack: PHP, WordPress 5.0+, Etch v1.3.1.

The virtual CPT approach (2,494 lines, 20+ hooks) has been fully removed and replaced with `class-cws-core-etch.php` — a clean filter integration. Listing pages, single job pages, and builder preview all work via the official `etch/dynamic_data/option` filter.

**Etch template usage:**
- Listing page: `{#loop options.cws_jobs as job}...{/loop}`
- Single page: `{options.cws_job.title}`, `{options.cws_job.description}`, etc.
- Preview in builder: falls back to first configured job ID when `?etch=magic` and user is logged in
- Date fields: `{options.cws_job.open_date_formatted}`, `{options.cws_job.update_date_formatted}`

**Known issues / tech debt:**
- `@var CWS_Core_Public` PHPDoc comment remains on dead `$public` property in `class-cws-core.php` (harmless)
- Cache `clear_all()` comment wording inaccuracy at line 152 (informational only)

## Constraints

- **Tech stack**: PHP, WordPress 5.0+, no Composer dependencies — must stay consistent with existing plugin
- **Etch compatibility**: Must use only `etch/dynamic_data/option` filter (stable, documented hook) — no internal Etch class hooks
- **API**: External API is read-only, organization ID auth, no rate limiting on our side
- **Backwards compatibility**: Admin settings and URL structure stay the same — `/job/{id}/` URLs must continue to work

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Use `etch/dynamic_data/option` filter | Official Etch hook, documented, survives upgrades | ✓ Good — clean integration, confirmed stable in v1.3.1 |
| Replace virtual CPT entirely | 2494-line file, fragile meta hook approach, root cause of breakage | ✓ Good — 2,167 LOC result vs original ~5k+; no more upgrade breakage |
| Keep pretty URLs `/job/{id}/` | Better SEO than query params, user preference | ✓ Good — unchanged, works correctly |
| Load real WP page for single job template | Required for Etch to render its template system against a real page | ✓ Good — `$post`/`$wp_query` swap pattern works well |
| Reuse existing API and cache classes | They work well, no reason to rebuild | ✓ Good — no changes needed |
| Store job at `template_redirect`, read at filter | Etch fires `the_content` at priority 1; data must be ready before filter | ✓ Good — `$current_job` property pattern is clean |
| `format_job_data()` as single normalization path | Consistent schema between listing and single-job contexts | ✓ Good — closes cross-phase inconsistency gap |

---
*Last updated: 2026-03-03 after v1.1 milestone start*
