# Domain Pitfalls

**Domain:** WordPress plugin integrating external API data with the Etch page builder
**Researched:** 2026-03-01
**Confidence:** HIGH — based on direct inspection of the installed Etch source code and the existing cws-core codebase

---

## Critical Pitfalls

Mistakes that cause rewrites or major breakage.

---

### ~~Pitfall 1: The `etch/dynamic_data/option` Filter Does Not Exist in This Etch Version~~ ✅ RESOLVED

**Status:** This pitfall does NOT apply. The filter was confirmed present on 2026-03-02.

**What the original research found:** The research agent searched Etch at an incorrect version
assumption (v0.38.0) and found zero matches. The installed version is **Etch v1.3.1**, which
ships this filter.

**Confirmed location:** `classes/Traits/DynamicData.php:196`
```php
$data_filtered = apply_filters( 'etch/dynamic_data/option', $data );
```

**Confirmed signature:** Single argument (`array $data`). Must return `array` or `E_USER_WARNING`
fires. Result is cached in `$this->cached_data['option_page']` for the page lifetime.

**No action needed.** Use the filter as documented in ARCHITECTURE.md and STACK.md.

---

### Pitfall 2: Etch Preprocessor Runs at `the_content` Priority 1 — Data Must Be Ready Before Then

**What goes wrong:** Etch hooks `prepare_content_blocks` to `the_content` at priority 1, which is very early. If the plugin injects job data into the `$context` at `the_content` priority 10 or later (or at `template_redirect`), Etch has already processed all blocks before the job data is available. All `{options.*}` placeholders are resolved against an empty or incomplete context.

**Why it happens:** WordPress fires `the_content` only once per page load, and Etch processes all placeholders in that single pass. Any data injection that happens after priority 1 on `the_content` is too late.

**Evidence from source code:** `Preprocessor.php` line 34:
```php
add_filter( 'the_content', array( $this, 'prepare_content_blocks' ), 1 );
```
And `get_block_templates` is hooked at priority 10. Context is built inside `BaseBlock::add_this_post_context()`, which calls `get_post()` to get the current post at block construction time.

**Consequences:** Job data is injected too late. `{options.cws_job.title}` renders blank. On a single-job page, the template appears to load but shows no job content. This is one of the hardest bugs to diagnose because everything looks correct — the hook is registered, the data is fetched — but the timing is off.

**Prevention:**
- Fetch and store job data in a class property during `template_redirect` (priority 10 or earlier).
- If the integration method requires hooking `the_content`, use priority 0 (before Etch).
- If using a Gutenberg block template path (via `get_block_templates`), inject data before priority 10.

**Detection:** Add a temporary `error_log( 'data ready' )` at the injection point and `error_log( 'etch processing' )` in the context-building code. If "etch processing" appears before "data ready" in the log, the order is wrong.

**Phase:** Phase 1 (core Etch integration). The timing contract must be established before any template work begins.

---

### Pitfall 3: Rewrite Rules Without a Real WordPress Page Return 404

**What goes wrong:** The rewrite rule `^job/([0-9]+)/?$` routes to `index.php?cws_job_id=$matches[1]`. WordPress resolves this against its template hierarchy. If there is no real WordPress page with the slug matching `cws_core_job_slug` (default: `job`), WordPress falls through to a 404 template. The rewrite rule fires, but WordPress has no page to render the Etch template against.

**Why it happens:** Etch's `get_template_data()` method (in `DynamicData.php`) searches for templates using `page-{slug}`, `page-{ID}`, `page`, and `index` slugs in the `wp_template` post type. If no page exists, there is no post object for `get_post()` to return, so `$this->context['this']` is null and the entire dynamic data pipeline has nothing to attach to.

**Evidence from source code:** `DynamicData.php` lines 310–340 show Etch resolves templates via `get_template_data_by_slug()`, which queries `wp_template` posts. There is no fallback for the case where `get_post()` returns null during `BaseBlock::add_this_post_context()`.

**Consequences:** Either a 404 page or a template with completely blank dynamic fields. The Etch builder's "Edit with Etch" row action generates URLs like `/?etch=magic&post_id=X` — if the page doesn't exist, the builder opens against an invalid post and job fields won't preview.

**Prevention:**
- The plugin must verify the job page exists on activation/settings save and prompt the admin to create it if missing.
- Add a health check in the admin settings that confirms: (a) the page exists, (b) the page slug matches the configured job slug, (c) rewrite rules are flushed after the page is created.
- Flush rewrite rules after page creation — creating the page alone is not enough.

**Detection:** Visit `/job/22026695/` with a fresh WordPress install and no job page configured. Expect 404. The admin settings page should display a warning if the required page is missing.

**Phase:** Phase 2 (URL routing). The page creation requirement must be documented in the settings page UI.

---

### Pitfall 4: Rewrite Rules Flushed at Wrong Time — URLs Silently Return 404

**What goes wrong:** `flush_rewrite_rules()` is called in `cws_core_activate` action (plugin activation) and via an AJAX button in the admin. If the job slug setting is changed after activation, the old rewrite rule remains in the `$wp_rewrite` cache until manually flushed. New URLs (`/newslug/123/`) 404; old URLs (`/job/123/`) may also break if they were already flushed pointing to the old rule.

**Why it happens:** WordPress caches rewrite rules in the `rewrite_rules` option. `add_rewrite_rule()` adds the rule to the in-memory rewrite object but does not flush the database cache. `flush_rewrite_rules()` must be called after the slug changes.

**Specific risk in this codebase:** `add_rewrite_rules()` reads `cws_core_job_slug` from the database at `init`. If an admin changes the slug setting and saves without flushing, `add_rewrite_rules()` registers the new rule in memory on the next request, but WordPress still uses the old cached rules from the database.

**Consequences:** All job URLs return 404. No PHP error. Appears as a frontend-only problem, making it difficult to diagnose.

**Prevention:**
- Hook `flush_rewrite_rules()` to `update_option_cws_core_job_slug` so flush happens automatically when the slug setting changes.
- Show a prominent admin notice after settings save that includes a "Flush Rules" button.
- On plugin update (new version), flush rewrite rules via `update_option` hook on the plugin version option.

**Detection:** Change the job slug setting and immediately visit `/newslug/123/`. If 404, rules are stale.

**Phase:** Phase 2 (URL routing). Must be tested explicitly after every slug setting change during development.

---

### Pitfall 5: Etch Builder Preview Has No `cws_job_id` Query Var — Data Must Fall Back to a Sample Job

**What goes wrong:** When an editor opens the Etch builder (`?etch=magic&post_id=X`), the URL is the site root, not `/job/123/`. The `cws_job_id` query var is empty. Without a fallback, `{options.cws_job.*}` renders blank in the builder preview, making the template impossible to design usefully.

**Why it happens:** The Etch builder loads the page via an iframe at the home URL with `?etch=magic`. WordPress sees no `cws_job_id` query var. The `template_redirect` handler sees no job ID, so no job is fetched. The `options.cws_job` context key is never set.

**Evidence from source code:** `Plugin.php` lines 197–215 confirm `?etch=magic` triggers the builder template. The `LoopBlock.php` `process_target_item_id()` method shows that context keys missing from `$this->context` cause PHP array access errors (`$this->get_context()[$this->targetItemId]` with no isset check).

**Consequences:** Two distinct failure modes:
1. `{options.cws_job.title}` renders blank — editor cannot see what the template looks like with real data.
2. If a loop references an undefined context key, it may throw a PHP notice/warning or silently return empty array.

**Prevention:**
- During `template_redirect`, detect `?etch=magic` AND check that the current user is logged in AND that no real `cws_job_id` is present. In that case, inject the first configured job ID as the sample job.
- Make the sample job ID configurable in the admin settings (separate from the displayed jobs list).
- Guard the context injection: only populate `options.cws_job` when a valid job ID is available (real or preview fallback).

**Detection:** Open the Etch builder on the single-job page template. If all `{options.cws_job.*}` fields show blank, the preview fallback is missing.

**Phase:** Phase 3 (preview mode). Should be tested by opening the builder on the job page after Phase 1 and 2 are working.

---

### Pitfall 6: Stale Transient Cache Serves Old Job Data After API Updates

**What goes wrong:** Job data is cached in transients (default: 1 hour). If the API updates a job listing — title change, salary update, position filled — the site continues serving the old cached data until the transient expires. There is no mechanism to invalidate the cache when the upstream data changes.

**Why it happens:** WordPress transients are time-based only. The existing `CWS_Core_Cache` class sets transients with `set_transient()` and has no event-driven invalidation. The external API (`jobsapi-internal.m-cloud.io`) does not send webhooks.

**Specific risk:** The cache key for a job is `cws_core_job_data_{job_id}`. If a job is removed from the API (position filled), the cached stale data continues to be served. Visitors can click "Apply Now" on a closed position for up to an hour.

**Consequences:** Stale job listings with outdated salary/status data. Apply links for closed positions. No visibility into cache staleness from the admin.

**Prevention:**
- Ensure the "Clear Cache" button in admin settings clears all `cws_core_*` transients, including per-job transients.
- Add cache-busting on settings save (when job IDs list or API endpoint changes).
- Display the cache age in the admin so editors know when data was last refreshed.
- Consider a shorter default cache duration (15 minutes) for job status data, with the current 1-hour option available as a performance tradeoff.

**Detection:** Update a job in the source API system. Immediately visit the job page on the site. Observe stale data. Confirm transient has not expired.

**Phase:** Phase 1 (cache layer). The clear-cache UI must be confirmed to clear per-job transients, not just the job list transient.

---

### Pitfall 7: GET-Parameter Admin Actions Without CSRF Protection (Existing Vulnerability)

**What goes wrong:** The existing codebase triggers admin actions via GET parameters (`?add_job=X`, `?create_schema=1`) with no nonce verification. These are being replaced, but if any new admin actions use the same pattern without nonces, an authenticated admin can be tricked into executing unintended actions via a malicious link.

**Why it happens:** The pattern was used throughout `class-cws-core-virtual-cpt.php` (lines 1410, 1462). It is the path of least resistance for quick admin features but violates WordPress security guidelines.

**Consequences:** CSRF attack vector. An attacker who can get an admin to click a link can trigger admin actions — adding/removing job IDs, clearing cache, modifying settings — without the admin's explicit intent.

**Prevention:**
- All new admin actions triggered by URL parameters must use `wp_verify_nonce()`.
- Use `wp_nonce_url()` when building admin action links.
- Prefer POST forms with `settings_fields()` nonce over GET-parameter actions.
- When removing the virtual CPT class, confirm all its GET-based action handlers are also removed — do not leave dead routes.

**Detection:** Review any admin page URL that triggers a state change. If it does not include a `_wpnonce` parameter and a corresponding `wp_verify_nonce()` check, it is vulnerable.

**Phase:** Phase 1 (security cleanup). Must be completed before the virtual CPT class is removed to ensure no vulnerable patterns are reimplemented.

---

## Moderate Pitfalls

---

### Pitfall 8: `error_log()` Calls Active in Production Cause Log Flooding and Performance Drag

**What goes wrong:** The codebase contains hundreds of unconditional `error_log()` calls that fire on every page load regardless of `WP_DEBUG` or the plugin's debug mode setting. In the new Etch integration, if any error_log calls land inside a loop that iterates over all jobs, log files can grow to gigabytes within hours on a high-traffic site.

**Prevention:** All `error_log()` calls must be wrapped in a debug mode check before the new integration is written:
```php
if ( $this->plugin->get_option( 'debug_mode', false ) ) {
    error_log( '...' );
}
```
Do not introduce new unconditional `error_log()` calls in the integration code.

**Phase:** Phase 1 (cleanup). Establish the logging pattern before writing new integration code.

---

### Pitfall 9: Conflicting Hook Registration from Virtual CPT Class During Transition

**What goes wrong:** If the virtual CPT class is not fully removed when the new Etch integration is activated, both systems run simultaneously. The old `get_post_metadata` filters at priorities 1, 10, and 999 interact unpredictably with the new system. The old `pre_get_posts` and `the_posts` filters may interfere with Etch's own `get_block_templates` query.

**Why it happens:** The virtual CPT class registers 20+ hooks in its `init()` method. Commenting out its instantiation leaves all those hooks in memory for as long as the class file is loaded. Partial removal is as risky as full removal.

**Consequences:** Difficult-to-reproduce bugs where job data sometimes appears correctly and sometimes does not, depending on which filter fires first in a given context.

**Prevention:**
- Remove the virtual CPT class and its instantiation in a single commit.
- Remove the `require_once` for `class-cws-core-virtual-cpt.php` from the main plugin file.
- Confirm all references to `$this->plugin->virtual_cpt` are removed from `class-cws-core.php`.
- After removal, load the site with `WP_DEBUG` enabled and confirm no undefined property notices.

**Phase:** Phase 1 (cleanup and new integration). Complete removal must happen before testing the new integration.

---

### Pitfall 10: The Etch Builder Opens Against Any Page (`?post_id=X`) — Wrong Page Renders No Job Data

**What goes wrong:** The Etch builder "Edit with Etch" row action adds `?etch=magic&post_id=X` to the home URL. If a template designer opens the builder from the job detail page's WP page (e.g., the page with slug `job`), but no `cws_job_id` is in the URL, `template_redirect` will not detect a job context and `options.cws_job` will be absent.

**Prevention:**
- The preview fallback (Pitfall 5) covers this scenario.
- Make the fallback trigger on: `?etch=magic` is present AND `post_id` matches the configured job page AND user is logged in.

**Phase:** Phase 3 (preview mode). Covered by the same fix as Pitfall 5.

---

### Pitfall 11: Job Slug Collides with Existing WordPress Page Slug

**What goes wrong:** If the WordPress site already has a page with slug `job` (e.g., a "Job Application" static page), the rewrite rule and the existing page create a routing conflict. WordPress may route `/job/123/` to the static page instead of firing the rewrite rule, because WordPress page routes take priority over custom rewrite rules when using 'top' position in certain permalink configurations.

**Prevention:**
- Check for slug collision in the settings page sanitizer and display an admin notice.
- Document that the configured slug must not match an existing page slug other than the designated job template page.
- Use `get_page_by_path()` to detect collisions before saving the slug setting.

**Phase:** Phase 2 (URL routing).

---

### Pitfall 12: API Response Schema Changes Break Silent Data Mapping

**What goes wrong:** The API at `jobsapi-internal.m-cloud.io` returns a specific JSON structure. The plugin maps fields like `title`, `company_name`, `salary`, `location.city`, etc. If the API renames or restructures a field, the mapping silently returns empty strings — there is no schema validation.

**Prevention:**
- Add a schema validation step in `class-cws-core-api.php`'s `format_job_data()` method that checks for required fields and logs a structured warning if fields are missing.
- Cache the raw API response in addition to the formatted response, so field mapping bugs can be debugged without a live API call.

**Phase:** Phase 1 (API layer). Minimal validation before building templates on top of the data contract.

---

## Minor Pitfalls

---

### Pitfall 13: WordPress Object Cache (Redis/Memcached) Invalidates Transients Differently

**What goes wrong:** On hosting with a persistent object cache (Redis, Memcached), transients are stored in the object cache, not the `wp_options` table. The `clear_all()` method in `CWS_Core_Cache` uses a direct SQL `DELETE` on `wp_options` which does not clear object cache entries.

**Prevention:** Use `delete_transient()` for each known transient key rather than direct SQL deletion. Or call `wp_cache_flush()` in addition to the SQL delete. Document this limitation in the admin cache-clear UI.

**Phase:** Phase 1 (cache layer).

---

### Pitfall 14: Excessive Error Suppression Hides Real Initialization Failures

**What goes wrong:** `class-cws-core.php` wraps `init_components()` in a try-catch that logs errors to `error_log` and continues. If the API class or cache class fails to instantiate, the plugin appears to load but silently has no API access. The new Etch integration will then inject empty data arrays.

**Prevention:** Add an admin notice when critical components fail to initialize. Do not silently continue past a failure in `CWS_Core_API` instantiation.

**Phase:** Phase 1 (cleanup).

---

### Pitfall 15: `serialize()` in Cache Key Generation Is PHP-Version-Sensitive

**What goes wrong:** `CWS_Core_Cache::generate_key()` uses `serialize($params)` to build a cache key. On different PHP versions, `serialize()` output for arrays with identical values but different internal ordering can differ, producing cache misses.

**Prevention:** For cache keys derived from arrays, use `ksort()` before serializing, or use `wp_json_encode()` which is deterministic.

**Phase:** Phase 1 (cache layer).

---

## Phase-Specific Warnings

| Phase Topic | Likely Pitfall | Mitigation |
|-------------|----------------|------------|
| Core Etch integration (Phase 1) | `etch/dynamic_data/option` filter does not exist in installed Etch version | Inspect Etch source directly; inject via `options` context key before `the_content` priority 1 |
| Core Etch integration (Phase 1) | Hook timing — data injected after Etch processes blocks | Register data provider at `wp` action or `template_redirect`, store in class property accessed by `the_content` priority 0 filter |
| Security cleanup (Phase 1) | GET-parameter admin actions without nonces survive the virtual CPT removal | Audit all admin page action handlers before and after removal |
| Virtual CPT removal (Phase 1) | Partial removal leaves conflicting hooks active | Remove class file reference and all instantiations in a single commit |
| URL routing (Phase 2) | Missing job page causes 404 on all job URLs | Admin must create the job template page; plugin validates and warns |
| URL routing (Phase 2) | Rewrite rules not flushed after slug change | Hook flush to `update_option_cws_core_job_slug` |
| URL routing (Phase 2) | Job slug collides with existing page | Validate slug against existing pages in settings sanitizer |
| Preview mode (Phase 3) | Builder preview shows blank fields — no job ID in URL | Detect `?etch=magic` and inject configured sample job ID |
| Cache invalidation | Stale data served for up to 1 hour after API update | Expose clear-cache as one-click admin action; consider shorter default TTL |
| Production logging | Hundreds of `error_log()` calls per page load | Audit and gate all logging behind debug mode toggle before writing new code |

---

## Sources

- Direct inspection of `/wp-content/plugins/etch/classes/Preprocessor/Preprocessor.php` — confirmed `the_content` hook at priority 1
- Direct inspection of `/wp-content/plugins/etch/classes/Preprocessor/Blocks/BaseBlock.php` — confirmed context construction pattern
- Direct inspection of `/wp-content/plugins/etch/classes/Preprocessor/Utilities/LoopHandlerManager.php` — confirmed loop preset architecture
- Direct inspection of `/wp-content/plugins/etch/classes/Plugin.php` — confirmed `?etch=magic` builder URL pattern
- Direct inspection of `/wp-content/plugins/cws-core/includes/class-cws-core-virtual-cpt.php` — confirmed hook duplication pattern
- Direct inspection of `/wp-content/plugins/cws-core/includes/class-cws-core.php` — confirmed rewrite rule and template_redirect setup
- Direct inspection of `/wp-content/plugins/cws-core/.planning/codebase/CONCERNS.md` — confirmed CSRF vulnerability and logging concerns
- Full-text search of Etch PHP files for `etch/dynamic_data` — zero matches (HIGH confidence the filter does not exist)
