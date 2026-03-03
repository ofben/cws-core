# Feature Landscape

**Domain:** WordPress plugin — job listing + single job page builder (Etch) integration
**Researched:** 2026-03-01
**Confidence:** HIGH (derived from existing codebase, PROJECT.md, INTEGRATIONS.md, and Etch filter behavior)

---

## Table Stakes

Features that must exist or the integration is useless. Without these, editors cannot build job pages in Etch at all.

| Feature | Why Expected | Complexity | Notes |
|---------|--------------|------------|-------|
| **All-jobs array injected as `{options.cws_jobs}`** | Without this, the Etch loop block `{#loop options.cws_jobs as job}` has nothing to iterate over. The integration's core promise is broken. | Low | Hook: `etch/dynamic_data/option`. Return formatted array from `get_jobs()` keyed by configured job IDs. |
| **All job fields accessible via dot notation inside loop** | Editor expects `{job.title}`, `{job.company_name}`, `{job.salary}`, `{job.url}`, etc. to resolve inside loop blocks. Fields must match the API response shape exactly. | Low | `format_job_data()` already produces the right shape. Must ensure no fields are dropped or renamed. |
| **Single job injected as `{options.cws_job}`** | Without this, the single job template (`/job/{id}/`) cannot display any job-specific content. | Medium | Must detect `cws_job_id` query var on `template_redirect`, fetch that job, and inject it. Different from listing — scoped to one job, not an array. |
| **Single job fields accessible as `{options.cws_job.title}`, etc.** | Same field availability expectation as loop, but accessed as flat properties on a single object. | Low | Same `format_job_data()` output, injected as a single object rather than array element. |
| **Real WordPress page as single job template** | Etch's rendering pipeline requires a real `WP_Post` object to resolve templates, set context, and call its layout engine. A virtual CPT or rewrite-to-nothing approach breaks this — confirmed by the existing 2494-line failed attempt. | Medium | Admin must configure a "job page slug" (already an option: `cws_core_job_slug`). `template_redirect` loads that page's post object as global `$post`. |
| **`/job/{id}/` URL routing preserved** | Existing URLs must keep working. SEO and any inbound links depend on this. Rewrite rule already exists and must remain exactly as-is. | Low | Already implemented in `add_rewrite_rules()`. Must survive the removal of `class-cws-core-virtual-cpt.php`. |
| **Etch builder preview shows sample data** | When a site editor opens the job template page in Etch (`?etch=magic`), Etch renders the template in a preview context. If `{options.cws_job.*}` resolves to nothing, the page is blank and the editor cannot work. | Medium | Detect `?etch=magic` + `is_user_logged_in()`. Fall back to the first configured job ID to hydrate `cws_job` with real data. No fake hardcoded data needed — use a real API job. |
| **`{options.cws_jobs}` also available in Etch preview** | Same preview concern for the listing page. Without data, the loop block renders nothing and the editor has no feedback. | Low | Same mechanism as single job preview — inject the full jobs array when in builder context. |
| **Cache-backed API calls** | Without caching, every page load hits the external API. On a listing page this means one hit per visitor. The existing `CWS_Core_Cache` (transients) already solves this and must be used by the new integration. | Low | Already works — `fetch_job_data()` checks cache before calling the API. The new Etch filter just calls `get_jobs()` which uses it. |
| **Admin cache clear propagates to Etch data** | When a site admin clears the cache from the settings page, the next Etch page load must reflect fresh API data. | Low | Already implemented in `clear_cache_ajax()`. No new work needed unless the new filter caches additional keys. |
| **Virtual CPT removed without breaking URL routing or Etch** | `class-cws-core-virtual-cpt.php` is 2494 lines of conflicting hooks. Leaving it in place while adding the new filter will cause double-execution, filter conflicts, and undefined behavior. | Medium | Requires surgical removal: deregister hooks, remove file include from `cws-core.php`, remove `$this->virtual_cpt` initialization. Rewrite rules already duplicated in `class-cws-core.php` so routing survives. |

---

## Differentiators

Features that are not strictly required but improve the integration meaningfully. Worth building in this milestone if complexity is low; defer otherwise.

| Feature | Value Proposition | Complexity | Notes |
|---------|-------------------|------------|-------|
| **`location` convenience field** | `format_job_data()` already produces a `location` sub-object with `city`, `state`, `country` keys. Exposing this as `{job.location.city}` in Etch avoids editors having to concatenate `primary_city` and `primary_state` manually. | None | Already produced by `format_job_data()`. Only needs to survive intact when the array is injected into Etch. |
| **Formatted date fields** | `open_date` and `update_date` come from the API as ISO strings. Providing pre-formatted versions (e.g., `open_date_formatted: 'March 1, 2026'`) gives editors a ready-to-display value. | Low | Add `date()` formatting inside `format_job_data()` or in the Etch injection layer. Worth doing — dates are nearly always displayed on job pages. |
| **`apply_url` alias for `url`** | The API field is named `url` which is ambiguous. An alias `apply_url` makes Etch templates self-documenting. | None | One line in `format_job_data()`. Editors can use `{job.apply_url}` instead of `{job.url}`. |
| **Admin "Sample Job ID" setting** | Currently the preview fallback would use the first ID in `cws_core_job_ids`. An explicit "preview job ID" setting gives admins control over which job appears in the builder. | Low | One new `register_setting()` call and one line in the preview detection logic. |
| **Cache warm on settings save** | When the admin saves job IDs or clears cache, immediately pre-fetch those jobs so the first front-end visitor gets cached data. | Medium | `add_action('update_option_cws_core_job_ids', ...)` triggers `fetch_job_data()`. Avoids cold-cache first-visitor penalty. Worthwhile but not blocking. |
| **Graceful "job not found" fallback** | If `/job/99999/` is visited for a job not in the configured list and the API returns nothing, the page should show a sensible message rather than an Etch template with all fields blank. | Medium | `template_redirect`: if `get_job($id)` returns false, redirect to 404 or a configured fallback page. Prevents confusing broken templates. |

---

## Anti-Features

Things to explicitly NOT build in this milestone.

| Anti-Feature | Why Avoid | What to Do Instead |
|--------------|-----------|-------------------|
| **Search/filter UI** | Requires either client-side JS filtering (limited by what's already loaded) or server-side filtering (requires API support or full data in memory). Neither is scoped here and Etch pagination hooks are pending. | Defer until Etch exposes pagination hooks. Note in PROJECT.md as deferred. |
| **Pagination of job listings** | The SnippetNest article on Etch pagination hooks indicates this is a pending Etch feature. Building fake pagination now will break when native support ships. | Defer. Load all configured jobs in one call. |
| **REST API endpoints exposing job data** | Already marked out-of-scope in PROJECT.md. Adds attack surface, auth complexity, and is not needed by the Etch filter approach. | The `etch/dynamic_data/option` filter injects data server-side. No REST API needed. |
| **Database-backed job sync** | Sync jobs to `wp_posts` or custom tables introduces a data-staleness layer and write complexity that transient caching already handles more simply. | Stay API-first. Transient cache is the right abstraction here. |
| **Job application form** | The API provides an `url` (apply link) that points to the external ATS. Building an in-page form duplicates ATS functionality and introduces data-liability. | Use the `url` field to link directly to the external application page. |
| **Virtual CPT preserved alongside new integration** | Running both systems simultaneously — the new `etch/dynamic_data/option` filter AND the existing virtual CPT — will produce conflicting `get_post_metadata` hooks, duplicate query interceptions, and undefined rendering behavior. | Remove virtual CPT as part of the same milestone. This is not optional. |
| **Hardcoded sample/fixture data for preview** | Hardcoded preview data goes stale, requires maintenance, and diverges from real API fields. | Use a real configured job ID as the preview sample. Call the API (cache handles performance). |
| **Custom Etch block or widget** | Building a custom Etch block or widget requires internal Etch APIs that are not stable or documented. | Use the official `etch/dynamic_data/option` filter only. This is the documented, stable integration point. |

---

## Feature Dependencies

```
cws_core_job_ids option (configured)
    └── get_jobs() [existing API method]
            └── fetch_job_data() [existing, cache-backed]
                    └── {options.cws_jobs} injection via etch/dynamic_data/option filter   [NEW]
                            └── {#loop options.cws_jobs as job}...{/loop} works in Etch listing template

cws_job_id query var (set by rewrite rule) [existing rewrite rule in class-cws-core.php]
    └── template_redirect hook  [NEW - replaces virtual CPT approach]
            └── get_job($id) [existing API method]
                    └── {options.cws_job} injection via etch/dynamic_data/option filter  [NEW]
                            └── {options.cws_job.title} etc. work in Etch single-job template

?etch=magic + is_user_logged_in()  [NEW detection]
    └── falls back to first configured job ID  [NEW]
            └── hydrates both {options.cws_job} and {options.cws_jobs} for preview

Virtual CPT removal  [prerequisite]
    └── must happen before or simultaneously with new filter registration
            └── otherwise: duplicate hooks cause rendering conflicts
```

### Critical dependency: Real WordPress page must exist

The single-job template requires a real `WP_Post` to exist in the database with a slug matching `cws_core_job_slug`. Without this page:
- Etch cannot load its template engine for that URL
- `template_redirect` has no page to load
- The rewrite rule sends the request to `index.php?cws_job_id=...` but there is no template page to serve

The admin must create this page (or the plugin must detect its absence and warn). This is a setup dependency, not a code dependency — but it must be documented and ideally surfaced in the admin settings.

---

## MVP Recommendation

Prioritize in this order:

1. **Virtual CPT removal** — unblocks everything else; conflicting hooks make the new integration unreliable if left in
2. **`etch/dynamic_data/option` filter for `cws_jobs` (all jobs)** — listing page works
3. **`etch/dynamic_data/option` filter for `cws_job` (single job via `template_redirect`)** — single job page works
4. **Etch builder preview support** — editors can actually design templates without blank pages
5. **Formatted date fields** — low effort, high value for display
6. **Graceful 404 fallback for unknown job IDs** — prevents confusing broken templates

Defer:
- Cache warm on settings save: useful but not blocking
- Admin "sample job ID" setting: the first configured ID works fine as default
- Pagination and search/filter: explicitly out of scope

---

## Sources

- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/.planning/PROJECT.md` — Requirements, constraints, key decisions (HIGH confidence)
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/.planning/codebase/INTEGRATIONS.md` — API fields, settings, WordPress hook inventory (HIGH confidence)
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/includes/class-cws-core-api.php` — `format_job_data()`, `get_job()`, `get_jobs()`, cache integration (HIGH confidence)
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/includes/class-cws-core.php` — `add_rewrite_rules()`, `handle_job_request()`, plugin initialization (HIGH confidence)
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/includes/class-cws-core-virtual-cpt.php` — 2494-line file showing the fragile hook-proliferation that must be removed (HIGH confidence — diagnosis based on init() hook count)
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/includes/class-cws-core-cache.php` — Transient cache layer, `clear_all()`, `get()`, `set()` (HIGH confidence)
- SnippetNest Etch loop article (https://snippetnest.com/snippet/loop-external-api-in-etch/) — Referenced in PROJECT.md as the authoritative Etch integration pattern; page content inaccessible at research time, but filter name and usage confirmed in PROJECT.md (MEDIUM confidence — described in PROJECT.md but could not independently verify filter signature)
