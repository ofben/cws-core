# Phase 4: Integration Cleanup - Research

**Researched:** 2026-03-02
**Domain:** WordPress plugin cleanup — hook removal, data normalization, dead code deletion, activation defaults
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

All implementation details are left to Claude's judgement — no user preferences captured.

### Claude's Discretion

- **Dead code scope in public.php:** Claude may remove the entire `inject_job_data()` method
  (not just the `add_filter` call) if it has no other callers. If the rest of
  `class-cws-core-public.php` (enqueue_scripts, add_job_meta_tags, add_javascript_variables)
  is also dead, Claude may clean those up too — but only if clearly unused.
- **Field name backwards-compatibility:** Raw API keys (`primary_city`, etc.) being absent
  from the normalized `cws_jobs` output is acceptable. The Etch integration is the sole
  mechanism and normalized keys (`location.city`) are the intended API.
- **`load_job_template()` removal depth:** Remove the entire method. Check for any other
  clearly dead code in the same file while making the change, but don't scope-creep into
  unrelated cleanup.
- **Activation hook seed value:** Use `0` (integer) as the default for
  `cws_core_job_template_page_id` — consistent with how WordPress page IDs work
  (0 = not configured).

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.
</user_constraints>

---

## Summary

Phase 4 is a targeted cleanup pass with four discrete, non-risky changes to a fully-working plugin. There is no new logic or new feature work. All four changes touch code that is already understood: two are line-level removals, one is an `array_map` wrapping an existing function call, and one is a single `add_option` call in the activation hook.

The most important change is the removal of `CWS_Core_Public::inject_job_data()` from the `the_content` filter. This hook is the root cause of REQ-P2-5 being unsatisfied (duplicate raw job HTML appended after Etch output on every `/job/{id}/` page). Removing the hook registration at line 47 of `class-cws-core-public.php` immediately fixes the symptom. Additional dead code in `CWS_Core_Public` (enqueue_scripts, add_javascript_variables, add_job_meta_tags and their helper methods) can be removed in the same pass because the entire class is purpose-built around the old injection mechanism — `$this->plugin->public` has no callers outside `class-cws-core.php` itself, the enqueued JS/CSS files do not exist on disk, and all public methods are self-contained within the class.

The listing normalization (applying `format_job_data()` to `cws_jobs` items) resolves the shared root cause of REQ-P1-3 and REQ-P3-11 in a single one-line change to `CWS_Core_Etch::inject_options()`. The dead method removal and activation hook seeding are cosmetic but necessary for a clean install story.

**Primary recommendation:** Execute all four changes in a single plan (04-01). Group them in order of impact: hook removal first, normalization second, dead method removal third, activation default fourth. Each change is independently verifiable with `WP_DEBUG` enabled and a browser test on `/job/{id}/`.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress core | 5.0+ | `add_option`, `array_map`, `remove_filter` | Plugin's existing runtime |
| PHP | 7.4+ | Native `array_map` | Plugin's existing requirement |

No third-party libraries required. All operations use WordPress core APIs already in use by this plugin.

---

## Architecture Patterns

### Recommended Project Structure

No structural changes. All edits are within existing files:

```
includes/
├── class-cws-core-public.php   # Remove add_filter line 47; optionally remove whole class
├── class-cws-core-etch.php     # Wrap $options['cws_jobs'] with array_map(format_job_data)
└── class-cws-core.php          # Delete load_job_template() method (lines 221-238)
cws-core.php                    # Add add_option('cws_core_job_template_page_id', 0)
```

### Pattern 1: Removing a WordPress Hook

**What:** Delete (or comment out) the `add_filter` / `add_action` registration line. The method body can optionally be left or deleted — leaving a dead method is safe but leaving an active filter registration is not.

**When to use:** When the hook's callback is the root cause of unwanted behaviour and no other caller depends on the callback method.

**Example:**
```php
// class-cws-core-public.php — register_hooks() BEFORE
private function register_hooks() {
    add_filter( 'the_content', array( $this, 'inject_job_data' ) );  // ← DELETE THIS LINE
    add_action( 'wp_footer', array( $this, 'add_javascript_variables' ) );
    add_action( 'wp_head', array( $this, 'add_job_meta_tags' ) );
}
```

**Important:** `remove_filter` is NOT the right approach here. `remove_filter` must be called after the filter was added, at the correct priority, from outside the class. Since we own the code, deleting the `add_filter` call is cleaner and definitive.

### Pattern 2: Normalizing Items with array_map

**What:** Wrap an existing array of raw API results with `array_map` + an existing formatter before assigning it to the options context.

**When to use:** When a formatter function already exists (it does: `format_job_data()`) and needs to be applied uniformly to a batch of items.

**Example — CWS_Core_Etch::inject_options() BEFORE:**
```php
$options['cws_jobs'] = array_values( $jobs );
```

**AFTER:**
```php
$options['cws_jobs'] = array_values(
    array_map( array( $this->plugin->api, 'format_job_data' ), $jobs )
);
```

`array_values()` is kept as the outer wrapper to preserve zero-indexed numeric keys, which is important for Etch's loop rendering. `array_map` does not guarantee re-indexing on its own.

**Confirmed:** `format_job_data()` is a public method on `CWS_Core_API` (line 305 of `class-cws-core-api.php`). It is already used for single-job normalization in both `handle_single_job()` and the Etch preview branch. It expects a single raw job array and returns a normalized array.

### Pattern 3: Deleting a Dead Method

**What:** Delete the PHPDoc block and method body entirely. Do not leave an empty stub unless the method is part of a public interface contract.

**When to use:** When the method is never registered as a hook and has no external callers.

**Evidence that load_job_template() is dead:**
- Not registered anywhere via `add_filter('template_include', ...)` or similar — confirmed by grep across all plugin PHP files
- Confirmed dead in Phase 1 verification (`01-VERIFICATION.md` line 102) and again in Phase 2 verification (`02-VERIFICATION.md` line 171)
- The `templates/job.php` file it references was deleted in Phase 1

**What to keep:** The deprecated `handle_job_request()` method (lines 211-213) was intentionally left as an empty stub in Phase 2 per the [02-01] decision: "only add_action hook removed; method signature kept to avoid fatal if called directly." Do NOT remove this stub — it is a deliberate Phase 2 decision.

### Pattern 4: Seeding Activation Defaults with add_option

**What:** Call `add_option( 'option_name', $default_value )` inside the activation hook function. WordPress `add_option` is idempotent — if the option already exists it does nothing. This makes it safe to add at any time without migration concerns.

**When to use:** When an option is used by the plugin at runtime but may not exist on fresh installs. The runtime fallback (`get_option(..., 0)`) already handles absence correctly, so this is a cosmetic improvement for installation hygiene.

**Existing pattern in cws-core.php lines 79-82:**
```php
add_option( 'cws_core_api_endpoint', 'https://jobsapi-internal.m-cloud.io/api/stjob' );
add_option( 'cws_core_organization_id', '' );
add_option( 'cws_core_cache_duration', 3600 );
add_option( 'cws_core_debug_mode', false );
```

**New line to add:**
```php
add_option( 'cws_core_job_template_page_id', 0 );
```

The default value of `0` is correct: it is the convention used throughout the plugin for "not configured" page IDs (`get_option( 'cws_core_job_template_page_id', 0 )` in `class-cws-core-admin.php` line 472 and `$this->plugin->get_option( 'job_template_page_id', 0 )` in `class-cws-core-etch.php` line 157).

### Anti-Patterns to Avoid

- **Using remove_filter instead of deleting the add_filter call:** `remove_filter` requires matching hook name, callback, and priority, and must be called after the filter was registered. Deleting the `add_filter` call is unambiguous and leaves no runtime trace.
- **Leaving the inject_job_data method body while removing only the hook:** This is acceptable (the method becomes unreachable dead code), but the CONTEXT.md permits full removal if no other callers exist. Since `inject_job_data` is only ever called by `the_content` filter and internally calls `build_job_display` (itself only called from `inject_job_data`), both methods can be deleted with the hook.
- **Removing handle_job_request() from class-cws-core.php:** This was intentionally left as a deprecated stub in Phase 2 decision [02-01]. Do not touch it.
- **Calling array_map on the result of get_jobs() rather than the already-fetched $jobs:** `$jobs` is already assigned from `get_jobs()` — wrap the assignment line, not the `get_jobs()` call, to avoid a double call.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Normalize listing items | Custom field-mapping loop | `array_map( array( $this->plugin->api, 'format_job_data' ), $jobs )` | `format_job_data()` already exists, is tested in production, and covers all fields including `location`, `open_date_formatted`, `update_date_formatted` |
| Default option on fresh install | Custom install check | `add_option()` (WordPress core) | Idempotent by design — silently no-ops if option exists |

---

## Common Pitfalls

### Pitfall 1: Leaving the_content hook while removing only the method

**What goes wrong:** If only the method is removed but the `add_filter` line at line 47 remains, WordPress will attempt to call a non-existent callback and produce a PHP fatal error (or a PHP warning in some contexts).

**Why it happens:** Partial edits when the intent is "remove the feature" but only one half gets deleted.

**How to avoid:** Delete the `add_filter` line first. Then delete the method. In that order, both are safe at any intermediate state.

**Warning signs:** PHP fatal "Call to undefined method" on any page load if the filter is kept and the method is removed.

### Pitfall 2: array_map changes key structure

**What goes wrong:** `array_map` preserves the input array's keys. If `get_jobs()` returns an associative array (e.g., keyed by job ID), the mapped result will also have those keys. Etch's loop renderer expects a 0-indexed array.

**Why it happens:** `get_jobs()` returns `$data['queryResult']` from the API response. `queryResult` is a JSON array, which `json_decode(..., true)` gives as a PHP numerically-indexed array. So keys are already 0-based integers in practice — but the `array_values()` wrapper that already exists in the code makes this safe regardless.

**How to avoid:** Keep `array_values()` as the outer wrapper, exactly as in the existing code.

### Pitfall 3: Accidentally removing handle_job_request()

**What goes wrong:** Phase 2 decision [02-01] explicitly kept `handle_job_request()` as an empty deprecated stub to avoid a PHP fatal if anything calls it directly. Removing it would violate a prior architectural decision.

**Why it happens:** During `load_job_template()` removal, both dead-looking methods are in proximity (lines 211-238). It's easy to remove both.

**How to avoid:** Only delete `load_job_template()` (lines 221-238). Leave `handle_job_request()` (lines 211-213) in place with its `@deprecated` PHPDoc.

### Pitfall 4: add_option placement within the activation hook

**What goes wrong:** If `add_option` is added outside the try/catch block in `cws_core_activate()`, an unexpected exception propagates uncaught and kills activation.

**Why it happens:** The activation hook has a try/catch starting at line 78. All four existing `add_option` calls are inside it.

**How to avoid:** Add the new `add_option` call inside the try block, following the same indentation as lines 79-82.

---

## Code Examples

Verified patterns from codebase inspection:

### Task 1: Remove the_content hook — register_hooks() change

```php
// class-cws-core-public.php — register_hooks() AFTER
private function register_hooks() {
    // the_content injection removed — job display is handled by Etch template
    add_action( 'wp_footer', array( $this, 'add_javascript_variables' ) );
    add_action( 'wp_head', array( $this, 'add_job_meta_tags' ) );
}
```

Note: The comment is optional. If the full `CWS_Core_Public` class is determined to be dead (see Task 1 analysis below), the register_hooks call itself goes away with the class.

### Task 2: Normalize cws_jobs listing items — inject_options() change

```php
// class-cws-core-etch.php — inject_options() BEFORE (line 86)
$options['cws_jobs'] = array_values( $jobs );

// AFTER
$options['cws_jobs'] = array_values(
    array_map( array( $this->plugin->api, 'format_job_data' ), $jobs )
);
```

### Task 3: Remove load_job_template() from class-cws-core.php

Delete lines 215-238 entirely (PHPDoc block + method body):

```php
// DELETE THIS BLOCK:
/**
 * Load job template
 *
 * @param string $template Template path.
 * @return string
 */
public function load_job_template( $template ) {
    // Check if we have a job ID
    $job_id = get_query_var( 'cws_job_id' );

    if ( ! empty( $job_id ) ) {
        // Look for a custom job template in the theme
        $job_template = locate_template( array( 'job.php', 'single-job.php' ) );

        if ( $job_template ) {
            return $job_template;
        }

        // Fall back to default template
        return CWS_CORE_PLUGIN_DIR . 'templates/job.php';
    }

    return $template;
}
```

### Task 4: Seed cws_core_job_template_page_id in activation hook

```php
// cws-core.php — cws_core_activate() — ADD after line 82
add_option( 'cws_core_job_template_page_id', 0 );
```

Full updated block context:
```php
function cws_core_activate() {
    try {
        add_option( 'cws_core_api_endpoint', 'https://jobsapi-internal.m-cloud.io/api/stjob' );
        add_option( 'cws_core_organization_id', '' );
        add_option( 'cws_core_cache_duration', 3600 );
        add_option( 'cws_core_debug_mode', false );
        add_option( 'cws_core_job_template_page_id', 0 );   // ← NEW
        flush_rewrite_rules();
    } catch ( Exception $e ) {
        error_log( 'CWS Core Activation Error: ' . $e->getMessage() );
    }
}
```

---

## Dead Code Scope Analysis: CWS_Core_Public

The CONTEXT.md permits (but does not require) removing the entire `CWS_Core_Public` class if the rest of it is clearly unused. The findings are:

**`enqueue_scripts()`:** Enqueues `public/js/public.js` and `public/css/public.css`. Neither file exists on disk (`public/` directory does not exist). The `is_job_page()` guard means this only fires on job pages anyway. With the `the_content` hook removed, there is no remaining frontend behavior in this class that needs a script.

**`add_javascript_variables()`:** Prints `var cwsCoreJobData = {...}` in the footer. This was the companion to `inject_job_data()` — it exposed job data to frontend JS. No other code in the plugin reads `cwsCoreJobData`. With Etch as the sole mechanism, JS access to job data is not provided by this plugin.

**`add_job_meta_tags()`:** Adds Open Graph and Twitter Card meta tags, plus JSON-LD structured data for job postings. This is independent of `inject_job_data()` and has standalone value (SEO). However, if Etch is "the sole job display mechanism," whether meta tags are part of the plugin's contract is ambiguous.

**`is_job_page()`, `get_current_job_id()`:** Used only internally within this class.

**`build_job_display()`:** Only called from `inject_job_data()`. Dead if `inject_job_data` is removed.

**`parse_job_id_from_url()`, `get_job_url()`:** Utility methods not called from within this class or anywhere else in the codebase (grep confirmed no external callers).

**Recommendation for planner:** The safest approach is a two-tier removal:
1. **Minimum (required):** Remove `add_filter( 'the_content', ... )` at line 47.
2. **Recommended (within discretion):** Remove the entire `inject_job_data()` + `build_job_display()` + `get_error_message()` group (these are only reachable via the removed hook).
3. **Optional (Claude's discretion):** Remove `add_javascript_variables()`, `add_job_meta_tags()`, `enqueue_scripts()` and their hook registrations if the planner judges them as clearly unused given the Etch-only model.

The planner should lean toward removing the whole `CWS_Core_Public` class body given:
- No external callers of any of its public methods
- No JS/CSS files exist to enqueue
- Meta tags are not listed as a phase requirement or success criterion
- `$this->plugin->public` property in `CWS_Core` can be left as `null` (it's already `null`-checked before `init()` is called at line 136)

---

## Open Questions

1. **Should the entire CWS_Core_Public class be removed, or just the inject_job_data hook?**
   - What we know: No external callers. Assets don't exist. All methods are self-contained.
   - What's unclear: Whether `add_job_meta_tags()` (OG/Twitter/JSON-LD) is considered a feature to preserve. It is not mentioned in any requirement or success criterion.
   - Recommendation: Remove entirely. The success criteria make no mention of meta tags. The Etch integration is the sole mechanism. If meta tags are desired in future, they can be added as a separate Etch-aware feature. The CONTEXT.md explicitly authorizes this cleanup.

2. **Should the `CWS_Core_Public` class file be deleted from disk, or just emptied?**
   - What we know: `cws-core.php` has `require_once` for it at line 41. If the file is deleted, the require_once causes a PHP fatal.
   - Recommendation: Keep the file. Empty the class body (leave only the class declaration shell), or restructure the class to contain only a no-op constructor — or remove the `require_once` line from `cws-core.php` and delete the file. The planner should decide: if the whole class is removed, both the file and the require_once must be removed together in the same atomic commit.

---

## Sources

### Primary (HIGH confidence)

All findings are based on direct code inspection of the plugin files:

- `/includes/class-cws-core-public.php` — Full file read, confirmed inject_job_data wiring at line 47, confirmed no external callers of any public method
- `/includes/class-cws-core-etch.php` — Full file read, confirmed line 86 (`array_values($jobs)`) is the normalization point
- `/includes/class-cws-core.php` — Full file read, confirmed load_job_template() at lines 221-238, confirmed handle_job_request() at 211-213 must be preserved
- `/includes/class-cws-core-api.php` — Full file read, confirmed `format_job_data()` at line 305 is public and correct for array_map usage
- `/cws-core.php` — Full file read, confirmed add_option pattern at lines 79-82 and activation hook structure
- `/.planning/v1.0-MILESTONE-AUDIT.md` — Audit confirms root causes and fixes for all four tasks
- `/.planning/phases/04-integration-cleanup/04-CONTEXT.md` — Exact file/line references for all integration points

### Secondary (MEDIUM confidence)

- WordPress `add_option` idempotency — well-established WordPress behavior (function does nothing if option already exists), consistent with observed usage pattern in the codebase

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — pure WordPress core APIs, all already in use
- Architecture: HIGH — all changes are to code already read and understood; no new patterns introduced
- Pitfalls: HIGH — pitfalls derived from direct code inspection, not hypothetical

**Research date:** 2026-03-02
**Valid until:** Not time-sensitive — this is refactoring work on a local codebase with no external dependencies
