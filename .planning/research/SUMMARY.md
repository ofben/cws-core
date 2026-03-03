# Project Research Summary

**Project:** CWS Core — Etch Job Integration (Dynamic Data Rebuild)
**Domain:** WordPress plugin — Etch page builder dynamic data filter integration
**Researched:** 2026-03-01 (filter confirmed 2026-03-02)
**Confidence:** HIGH

---

## Executive Summary

This milestone replaces a 2,494-line virtual CPT class (`class-cws-core-virtual-cpt.php`) with a single ~150-line integration class (`class-cws-core-etch.php`) that hooks into Etch's official dynamic data filter. The virtual CPT approach was the wrong abstraction — Etch cannot render its block template system against a non-database post object, which is why the existing implementation is fragile and fails. The correct approach is: hook `etch/dynamic_data/option` to inject job data into the Etch options context, and use `template_redirect` to swap the global `$post` to a real WordPress page that carries the Etch template. Both mechanisms are confirmed stable: the filter exists in Etch v1.3.1 at `classes/Traits/DynamicData.php:196`, and the `template_redirect` post-swap pattern is standard WordPress.

The key architectural insight is timing. Etch hooks `the_content` at priority 1 — before almost anything else runs. Data cannot be injected via `the_content` at any later priority. The solution is to store the job ID in a class property during `template_redirect` (which fires before `the_content`), then read that property inside the `etch/dynamic_data/option` callback when Etch asks for options data. This timing contract is the spine of the entire integration.

The main non-code risk is a setup dependency: a real WordPress page with slug matching `cws_core_job_slug` (default: `job`) must exist in the database. Without it, `get_page_by_path()` returns null, the post swap fails, and all single-job URLs return 404. The plugin must detect this missing page and surface a clear admin warning. This is a documentation and admin-UX concern, not a code correctness concern, but it will catch every new installation.

---

## Key Findings

### Recommended Stack

The integration requires no new dependencies. Everything runs on PHP 7.4+, WordPress 5.0+, and the WordPress Hooks API. The existing `CWS_Core_API`, `CWS_Core_Cache`, and `CWS_Core_Admin` classes remain unchanged — the entire new surface is one file.

**Core technologies:**
- `etch/dynamic_data/option` filter — the single official Etch integration point; confirmed in Etch v1.3.1 at `DynamicData.php:196`; single argument, must return array; result is cached per page render by Etch
- `template_redirect` action — standard WordPress hook for rerouting requests; fires before `the_content`, making it the correct place to store job ID and swap `$post`
- WordPress Transients API (`CWS_Core_Cache`) — existing transient wrapper handles all API response caching; no changes needed
- WordPress Rewrite Rules API — existing rules (`^job/([0-9]+)/?$`) survive the migration unchanged; must not be broken by CPT removal

**Eliminated:**
- `class-cws-core-virtual-cpt.php` — 2,494 lines; registers 20+ conflicting hooks; entire file deleted
- `templates/job.php` — virtual-post template; superseded by real WP page with Etch template

See [STACK.md](STACK.md) for full hook signatures and data shapes.

### Expected Features

The integration delivers two template-accessible data keys in the Etch options context. Everything editors build depends on these two keys being populated correctly.

**Must have (table stakes):**
- `{options.cws_jobs}` — array of all configured jobs; powers `{#loop options.cws_jobs as job}` on the listing page
- `{options.cws_job.*}` — single job object; powers all field variables on single-job pages (`{options.cws_job.title}`, `{options.cws_job.description}`, `{options.cws_job.url}`, etc.)
- Single-job URL routing (`/job/{id}/`) — existing rewrite rules preserved; `template_redirect` loads real template page
- Etch builder preview with real data — `?etch=magic` detection falls back to first configured job ID so editors see real data while designing templates
- Virtual CPT removed — prerequisite for everything else; conflicting hooks make the new integration unreliable if left in place

**Should have (differentiators):**
- Formatted date fields (`open_date_formatted`) — low effort; dates are nearly always displayed; avoids editors concatenating ISO strings
- `apply_url` alias for the `url` field — one-line addition; makes templates self-documenting
- Graceful 404 fallback for unknown job IDs — prevents blank Etch template rendering when a job ID is not in the API response
- Admin notice when job template page is missing — surfaces the critical setup dependency before it causes a confusing 404

**Defer to v2+:**
- Pagination of job listings — Etch pagination hooks are pending upstream; building fake pagination now will break when native support ships
- Search/filter UI — requires client-side JS or API support; out of scope per PROJECT.md
- REST API endpoints — no use case in this approach; data is injected server-side
- Database-backed job sync — transient caching is the right abstraction; no case for writing to `wp_posts`
- Job application form — external ATS handles applications; the `url` field links there directly

See [FEATURES.md](FEATURES.md) for full feature dependency graph.

### Architecture Approach

The post-migration architecture replaces the sprawling virtual CPT with a clean three-layer design: a coordinator (`class-cws-core.php`) that owns rewrite rules and query vars; a new Etch integration class (`class-cws-core-etch.php`) that owns all Etch-specific hooks; and an unchanged data layer (`CWS_Core_API` + `CWS_Core_Cache`) that the Etch class calls. The Etch class is the only new file. Everything else is either unchanged or trimmed of virtual CPT wiring.

**Major components:**
1. `class-cws-core-etch.php` (NEW, ~150 lines) — registers `template_redirect` handler and `etch/dynamic_data/option` filter; owns job ID state; provides builder preview fallback
2. `class-cws-core.php` (MODIFIED) — remove `$this->virtual_cpt` instantiation and wiring; rewrite rules and query var registration stay here
3. `class-cws-core-api.php` (UNCHANGED) — `get_jobs()`, `get_job()`, `format_job_data()`; cache-backed; single source of truth for job data shape
4. `class-cws-core-virtual-cpt.php` (DELETED) — all 2,494 lines removed in one commit

See [ARCHITECTURE.md](ARCHITECTURE.md) for full data flow diagrams and implementation skeletons.

### Critical Pitfalls

1. **Hook timing — data injected after Etch processes blocks** — Etch hooks `the_content` at priority 1. Any data injection at `the_content` priority 1 or later is too late. Prevention: store `$current_job_id` in the class property during `template_redirect`, which fires before `the_content`. The `etch/dynamic_data/option` callback reads the class property — it never calls `get_query_var()` directly. (Pitfall 2 — CRITICAL)

2. **Missing job template page returns 404** — If a real WordPress page with slug matching `cws_core_job_slug` does not exist, `get_page_by_path()` returns null, the post swap does nothing, and all single-job URLs fall through to 404 with no PHP error. Prevention: admin health check in settings UI; detect missing page on activation/settings save; surface a clear admin notice. (Pitfall 3 — CRITICAL)

3. **Virtual CPT partially removed — conflicting hooks remain** — The virtual CPT registers 20+ hooks in its constructor. Commenting out its instantiation without removing the `require_once` can still cause hooks to fire depending on class loading. Prevention: remove the `require_once` for `class-cws-core-virtual-cpt.php` from `cws-core.php` in the same commit as adding the new integration. (Pitfall 9 — CRITICAL)

4. **Etch builder preview shows blank fields** — The Etch builder loads pages via `?etch=magic` at the home URL with no `cws_job_id`. Without a fallback, `{options.cws_job.*}` renders blank and editors cannot design the template. Prevention: detect `?etch=magic` + `is_user_logged_in()` + empty job ID; fall back to first configured job ID. (Pitfall 5 — HIGH)

5. **Rewrite rules not flushed after slug change** — Changing `cws_core_job_slug` without flushing rewrite rules leaves stale rules in the WordPress options cache. All job URLs 404 with no PHP error. Prevention: hook `flush_rewrite_rules()` to `update_option_cws_core_job_slug`; existing activation flush already handled. (Pitfall 4 — HIGH)

See [PITFALLS.md](PITFALLS.md) for the full list including moderate and minor pitfalls.

---

## Implications for Roadmap

Based on the research, the natural phase structure follows the dependency graph: you cannot test Etch data injection until the virtual CPT is gone, you cannot test single-job routing until the Etch filter works, and you cannot test the builder preview until single-job routing works. The MVP order documented in FEATURES.md maps directly to a four-phase plan.

### Phase 1: Remove Virtual CPT and Wire Etch Filter

**Rationale:** The virtual CPT's 20+ conflicting hooks make the new integration unreliable if both run simultaneously. Removal is a strict prerequisite. Etch listing-page data injection is the simplest integration surface — no URL routing required, no post swap — and proves the filter works before building on top of it.

**Delivers:** `{options.cws_jobs}` available in any Etch template; virtual CPT completely gone; logging gated behind debug mode; CSRF-vulnerable GET admin actions audited.

**Addresses features:** All-jobs array injection; cache-backed API calls; admin cache clear propagation; virtual CPT removal without breaking routing.

**Avoids pitfalls:** Pitfall 9 (conflicting hooks), Pitfall 7 (CSRF vulnerability), Pitfall 8 (production log flooding).

**Research flag:** None — filter is confirmed at `DynamicData.php:196`, implementation skeleton is in ARCHITECTURE.md.

---

### Phase 2: Single Job URL Routing and Template Swap

**Rationale:** With the Etch filter proven in Phase 1, Phase 2 adds the `template_redirect` handler that stores `$current_job_id` in the class property and swaps `$post` to the real template page. This is the hook timing contract — it must be established before any template work.

**Delivers:** `/job/{id}/` URLs load the real WP page and inject `{options.cws_job.*}` for that specific job; hook timing contract verified.

**Addresses features:** Single job injected as `{options.cws_job}`; real WordPress page as single job template; URL routing preserved.

**Avoids pitfalls:** Pitfall 2 (data ready before `the_content`), Pitfall 3 (missing job page — add admin warning), Pitfall 4 (rewrite flush on slug change), Pitfall 11 (slug collision with existing page).

**Research flag:** None — `template_redirect` post-swap pattern is verified in ARCHITECTURE.md; the only validation needed is confirming the admin warning for the missing-page setup dependency.

---

### Phase 3: Etch Builder Preview Fallback

**Rationale:** Preview support is Phase 3 because it depends on Phase 1 (Etch filter working) and Phase 2 (single-job injection logic in place). The preview fallback reuses the same injection path — it only adds detection of `?etch=magic` and a job ID fallback when none is present from the URL.

**Delivers:** Etch builder preview shows real job data when editing the single-job template; `{options.cws_job.*}` populated from first configured job ID; `{options.cws_jobs}` available in listing preview.

**Addresses features:** Etch builder preview shows sample data; `{options.cws_jobs}` also available in Etch preview.

**Avoids pitfalls:** Pitfall 5 (blank builder preview), Pitfall 10 (wrong page renders no job data in builder).

**Research flag:** None — `?etch=magic` detection is confirmed in `Plugin.php`; implementation is a three-line conditional already shown in ARCHITECTURE.md.

---

### Phase 4: Polish and Edge Case Hardening

**Rationale:** Once the core integration works end-to-end, the remaining differentiator features and defensive measures can be added without risk of breaking the integration.

**Delivers:** Formatted date fields; `apply_url` alias; graceful 404 for unknown job IDs; admin health check for missing template page; `ksort()` fix for cache key determinism.

**Addresses features:** Formatted date fields; `apply_url` alias; graceful job-not-found fallback; admin notice for missing page.

**Avoids pitfalls:** Pitfall 6 (stale transient cache — shorter default TTL or cache-age display); Pitfall 12 (API schema changes — minimal field validation in `format_job_data()`); Pitfall 13 (object cache miss on Redis/Memcached — use `delete_transient()` instead of raw SQL); Pitfall 15 (cache key ordering — `ksort()` before serialize).

**Research flag:** None — all standard WordPress patterns.

---

### Phase Ordering Rationale

- Phase 1 before Phase 2: The Etch filter must be proven working before adding URL routing complexity. A broken filter is much easier to debug on a simple listing page than on a routed single-job URL.
- Phase 2 before Phase 3: Preview fallback is a conditional branch inside the same injection callback. Building it before the base case (real job routing) would test the fallback before the primary path is confirmed.
- Phase 4 last: Polish features add no new architectural risk. Deferring them keeps the first three phases focused on correctness.
- Virtual CPT removal in Phase 1 (not a separate phase): Partial removal is as dangerous as no removal. It must happen atomically with the new integration to prevent a window where both systems run.

### Research Flags

Phases requiring no additional research (patterns fully documented):
- **Phase 1:** Filter confirmed, implementation skeleton in ARCHITECTURE.md
- **Phase 2:** `template_redirect` post-swap is standard WordPress; signature verified
- **Phase 3:** `?etch=magic` detection confirmed in Etch source
- **Phase 4:** All standard WordPress/PHP patterns

There are no phases in this milestone that need a `/gsd:research-phase` run before implementation. The primary unknowns from STACK.md (filter existence, exact signature) are now resolved.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All hooks verified by direct source inspection of Etch v1.3.1 and existing plugin source; no inferred signatures |
| Features | HIGH | Derived from existing codebase, PROJECT.md, and confirmed Etch behavior; no speculative features |
| Architecture | HIGH | Implementation skeletons verified against actual Etch internals; timing contract confirmed via `Preprocessor.php` line 34 |
| Pitfalls | HIGH | All critical pitfalls grounded in direct source evidence (line numbers cited); one major pitfall (filter existence) resolved before build |

**Overall confidence:** HIGH

### Gaps to Address

- **`?etch=magic` preview detection — verify `post_id` parameter scope:** ARCHITECTURE.md notes that the builder passes `?etch=magic&post_id=X`. The preview fallback should check that `post_id` matches the configured job page (not any page) to avoid injecting job data into unrelated Etch builder previews. Add a `get_option('cws_core_job_slug')` cross-check during implementation.

- **Admin health check UX — missing template page:** The research identifies the need for an admin warning when the job template page is missing, but the exact settings UI integration point is not specified. During Phase 2 planning, confirm whether this surfaces as an admin notice, a settings field validation message, or a dashboard widget.

- **Cache invalidation on individual job transients:** PITFALLS.md Pitfall 6 flags that `CWS_Core_Cache::clear_all()` uses direct SQL and may miss Redis/Memcached entries. Confirm during Phase 4 whether `clear_all()` needs to be refactored to use `delete_transient()` per key, or whether the environment is guaranteed to not use a persistent object cache.

---

## Sources

### Primary (HIGH confidence)

- Etch v1.3.1 source: `classes/Traits/DynamicData.php:196` — confirmed `etch/dynamic_data/option` filter with single-arg signature
- Etch v1.3.1 source: `classes/Preprocessor/Preprocessor.php` — `the_content` hook at priority 1
- Etch v1.3.1 source: `classes/Preprocessor/Blocks/BaseBlock.php` — context construction, `add_this_post_context()`
- Etch v1.3.1 source: `classes/Preprocessor/Utilities/EtchParser.php` — dot-notation variable resolution
- Etch v1.3.1 source: `classes/Plugin.php` — `?etch=magic` builder URL pattern
- CWS Core source: `includes/class-cws-core.php` — existing rewrite rules and `template_redirect` wiring
- CWS Core source: `includes/class-cws-core-api.php` — `format_job_data()`, `get_job()`, `get_jobs()`
- CWS Core source: `includes/class-cws-core-virtual-cpt.php` — 2,494 lines; diagnosis of hook conflicts
- CWS Core source: `includes/class-cws-core-cache.php` — transient wrapper, `clear_all()` SQL pattern
- `.planning/PROJECT.md` — requirements, constraints, key decisions
- `.planning/codebase/INTEGRATIONS.md` — API field schema, settings inventory, hook inventory
- `.planning/codebase/CONCERNS.md` — CSRF vulnerability, logging concerns

### Secondary (MEDIUM confidence)

- snippetnest.com/snippet/loop-external-api-in-etch/ — `etch/dynamic_data/option` documentation; filter name confirmed against source, usage pattern described; original URL was inaccessible at research time but cited in PROJECT.md

---

*Research completed: 2026-03-01 (ARCHITECTURE.md and filter confirmation: 2026-03-02)*
*Ready for roadmap: yes*
