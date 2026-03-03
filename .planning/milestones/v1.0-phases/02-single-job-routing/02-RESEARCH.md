# Phase 2: Single Job Routing - Research

**Researched:** 2026-03-01
**Domain:** WordPress routing, template_redirect hook, WP_Post object swapping, WordPress Settings API
**Confidence:** HIGH

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

All implementation decisions for this phase are delegated to Claude. User did not specify
preferences for any area — proceed with best-practice defaults:

- **Job template page configuration:** Add a new `cws_core_job_template_page_id` option to
  the existing CWS Core settings page. Render it as a `<select>` dropdown populated from
  `get_pages()` — standard WordPress pattern. Save as page ID (integer). If not set, the
  health check warning fires but routing still attempts to proceed (graceful degradation).

- **Valid job scope:** Attempt to fetch ANY numeric job ID from the API via
  `CWS_Core_API::get_job( $job_id )`. If the API returns no data (unknown ID, deleted job),
  trigger a WordPress 404 response. Do NOT restrict routing to only the configured job IDs
  in `cws_core_job_ids` — that list is for listing pages, not for restricting which detail
  pages are accessible. Any ID the API knows about should resolve.

- **template_redirect handler:** Hook at `template_redirect`. Read `get_query_var( 'cws_job_id' )`.
  If set and non-empty: fetch single job via API. If API returns no data → 404. If data found
  → store job on `$this->current_job_id`/`$this->current_job` on the etch class, swap global
  `$post` and `$wp_query->posts[0]` to the configured template page object, inject
  `{options.cws_job}` in the existing `inject_options()` filter callback.

- **404 mechanism:** Use the standard WordPress pattern:
  ```php
  global $wp_query;
  $wp_query->set_404();
  status_header( 404 );
  nocache_headers();
  include( get_404_template() );
  exit;
  ```

- **Admin health check placement:** Settings page only (per success criteria). Add an inline
  notice below the "Job Slug" field in `render_settings_page()` when `cws_core_job_template_page_id`
  is empty or points to a deleted/trashed page. No WP admin notice on other admin screens.

- **Rewrite flush on slug change:** Hook `update_option_cws_core_job_slug` to call
  `flush_rewrite_rules()`. This already has precedent in the codebase (AJAX action
  `cws_core_flush_rules`). Same approach, triggered automatically on option save.

- **Class placement:** Phase 2 routing logic lives in `class-cws-core-etch.php` (it's already
  scaffolded with `$current_job_id = null` and a "Phase 2" comment). Do not create a new class.
  Add admin settings to `class-cws-core-admin.php`. Add the `cws_core_job_template_page_id`
  option registration to the existing `register_settings()` method.

- **`cws_job` injection scope:** `{options.cws_job}` is only populated when `$this->current_job`
  is non-null (i.e., on a `/job/{id}/` URL). On all other pages it is absent from the context,
  not an empty array. `{options.cws_jobs}` (plural, the listing) continues injecting everywhere
  as Phase 1 established.

### Claude's Discretion

All implementation decisions are at Claude's discretion (see Locked Decisions above — those ARE
the discretion decisions already made by Claude in CONTEXT.md).

### Deferred Ideas (OUT OF SCOPE)

None — user did not raise any out-of-scope ideas. Discussion stayed within phase scope.
</user_constraints>

---

## Summary

Phase 2 implements single-job URL routing: when `/job/{id}/` is visited, a `template_redirect` handler fetches the job from the API, swaps the global WordPress post to a configured template page, and injects the job data as `{options.cws_job}` via the existing Etch filter. All the scaffolding (rewrite rules, `cws_job_id` query var, `$current_job_id` property, `inject_options()` filter) is already in place from Phase 1.

The implementation is entirely within existing files: routing logic in `class-cws-core-etch.php`, admin settings in `class-cws-core-admin.php`. There is one architectural conflict to resolve: `class-cws-core.php` already registers a stub `handle_job_request()` at `template_redirect` — this stub must either be removed or the new Etch handler must be the sole handler. The CONTEXT.md says to add the handler to `CWS_Core_Etch::init()`, which means the stub in `CWS_Core::register_hooks()` should be removed.

A second critical finding: `CWS_Core_API::get_job()` returns RAW API data (a single element from `queryResult`), NOT a `format_job_data()`-processed array. The `inject_options()` method must call `$this->plugin->api->format_job_data( $job )` on the result before storing it as `$this->current_job`, to ensure consistent dot-notation access for Etch templates (`{options.cws_job.title}` etc.). The Phase 1 `inject_options()` for `cws_jobs` also passes raw data — this is a pre-existing inconsistency, but Phase 2 MUST use formatted data since `cws_job` field names are part of the documented interface.

**Primary recommendation:** Add `handle_single_job()` to `CWS_Core_Etch`, hook it at `template_redirect`, have it fetch + format the job, swap `$post`, and store `$this->current_job`. Remove the existing stub from `CWS_Core::handle_job_request()`. Add `cws_core_job_template_page_id` settings field to admin. Hook `update_option_cws_core_job_slug` for rewrite flush.

---

## Standard Stack

### Core

| Component | Version/Source | Purpose | Why Standard |
|-----------|----------------|---------|--------------|
| `template_redirect` hook | WordPress core | Intercept page load before template is chosen | Fires after WP query is set up; correct hook for routing decisions |
| `get_query_var( 'cws_job_id' )` | WordPress core | Read the job ID from the URL | Already registered in `CWS_Core::add_query_vars()`; safe to read here |
| `get_post( $page_id )` | WordPress core | Get the WP_Post template page object | Returns WP_Post or null; use to swap `$post` |
| `$wp_query->set_404()` | WordPress core | Trigger proper 404 response | Sets all WP_Query flags correctly; does not call `exit` alone |
| `get_404_template()` | WordPress core | Locate the 404 template | Follows theme hierarchy; correct way to serve 404 |
| `register_setting()` | WordPress Settings API | Register new option | Existing pattern in `CWS_Core_Admin::register_settings()` |
| `add_settings_field()` | WordPress Settings API | Add field to settings page | Existing pattern in `CWS_Core_Admin::register_settings()` |
| `get_pages()` | WordPress core | Populate page dropdown | Standard WP function for page selects; returns array of WP_Post objects |
| `update_option_{option_name}` action | WordPress core | Fire callback on option save | Action hook fired by `update_option()` after option is saved |

### No External Libraries Required

This phase is pure WordPress PHP. No Composer packages, no npm, no new files beyond modifications to existing classes.

---

## Architecture Patterns

### Recommended File Changes

```
includes/
├── class-cws-core-etch.php    # Add $current_job property, handle_single_job(),
│                               # update inject_options() to add cws_job key
├── class-cws-core-admin.php   # Add cws_core_job_template_page_id field,
│                               # update render_settings_page() for health check
└── class-cws-core.php         # Remove/empty handle_job_request() stub,
                                # remove its template_redirect hook registration
```

### Pattern 1: template_redirect Handler (in CWS_Core_Etch)

**What:** Detect `cws_job_id` query var, fetch job, store on `$this`, swap `$post` to template page
**When to use:** Any WordPress plugin that needs to render a real WP page with custom data for a custom URL

```php
// In CWS_Core_Etch::init():
add_action( 'template_redirect', array( $this, 'handle_single_job' ) );

// The handler:
public function handle_single_job() {
    $job_id = get_query_var( 'cws_job_id' );

    if ( empty( $job_id ) ) {
        return; // Not a job URL — do nothing
    }

    // Fetch from API (includes caching via fetch_job_data)
    $raw_job = $this->plugin->api->get_job( $job_id );

    if ( false === $raw_job || empty( $raw_job ) ) {
        // Unknown job ID — serve 404
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        include( get_404_template() );
        exit;
    }

    // Format for Etch consumption
    $this->current_job    = $this->plugin->api->format_job_data( $raw_job );
    $this->current_job_id = $job_id;

    // Get the configured template page
    $template_page_id = (int) $this->plugin->get_option( 'job_template_page_id', 0 );
    $template_page    = $template_page_id ? get_post( $template_page_id ) : null;

    if ( ! $template_page || 'publish' !== $template_page->post_status ) {
        // No template page configured — Etch filter will still inject cws_job,
        // but WP will try to render the 404 page. Log and degrade gracefully.
        $this->plugin->log( 'No job template page configured or page is not published', 'error' );
        return;
    }

    // Swap $post and the main query to point at the template page
    global $post, $wp_query;
    $post                    = $template_page;
    $wp_query->posts         = array( $template_page );
    $wp_query->post          = $template_page;
    $wp_query->post_count    = 1;
    $wp_query->found_posts   = 1;
    $wp_query->is_404        = false;
    $wp_query->is_page       = true;
    $wp_query->is_singular   = true;
    $wp_query->queried_object = $template_page;
    setup_postdata( $post );

    $this->plugin->log( sprintf( 'Single job routing: job %s → template page %d', $job_id, $template_page_id ), 'debug' );
}
```

### Pattern 2: Conditional cws_job Injection (in inject_options)

**What:** Only add `cws_job` key when `$this->current_job` is set (i.e., on a job URL)
**When to use:** Data that is page-scoped, not site-scoped

```php
// Extend the existing inject_options() method:
public function inject_options( array $options ) {
    // ... existing cws_jobs injection (Phase 1) ...

    // Phase 2: inject single job only on /job/{id}/ URLs
    if ( null !== $this->current_job ) {
        $options['cws_job'] = $this->current_job;
        $this->plugin->log( sprintf( 'Injected cws_job for job %s', $this->current_job_id ), 'debug' );
    }

    return $options;
}
```

**Important:** `$this->current_job` must be a separate property from `$this->current_job_id`. The class already declares `$current_job_id = null` — add `$current_job = null` alongside it.

### Pattern 3: Admin Settings Field — Page Select Dropdown

**What:** `<select>` populated from `get_pages()`, saved as integer page ID
**When to use:** Admin needs to designate an existing WP page as a template

```php
// In register_settings():
register_setting(
    'cws_core_settings',
    'cws_core_job_template_page_id',
    array(
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 0,
    )
);

add_settings_field(
    'cws_core_job_template_page_id',
    __( 'Job Template Page', 'cws-core' ),
    array( $this, 'render_job_template_page_field' ),
    'cws-core-settings',
    'cws_core_url_section'
);

// The field renderer:
public function render_job_template_page_field() {
    $value = (int) get_option( 'cws_core_job_template_page_id', 0 );
    $pages = get_pages( array( 'post_status' => 'publish' ) );
    ?>
    <select id="cws_core_job_template_page_id" name="cws_core_job_template_page_id">
        <option value="0"><?php esc_html_e( '— Select a page —', 'cws-core' ); ?></option>
        <?php foreach ( $pages as $page ) : ?>
            <option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $value, $page->ID ); ?>>
                <?php echo esc_html( $page->post_title ); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <p class="description">
        <?php esc_html_e( 'The WordPress page that contains the Etch job detail template.', 'cws-core' ); ?>
    </p>
    <?php
    // Inline health check warning
    if ( 0 === $value ) {
        echo '<p class="description" style="color:#d63638;">'
            . esc_html__( 'Warning: No job template page selected. Single job URLs will not load correctly.', 'cws-core' )
            . '</p>';
    } else {
        $page = get_post( $value );
        if ( ! $page || 'publish' !== $page->post_status ) {
            echo '<p class="description" style="color:#d63638;">'
                . esc_html__( 'Warning: The selected page does not exist or is not published.', 'cws-core' )
                . '</p>';
        }
    }
}
```

### Pattern 4: Automatic Rewrite Flush on Slug Change

**What:** WordPress fires `update_option_{option_name}` action after any `update_option()` call
**When to use:** Slug changes that affect custom rewrite rules

```php
// In CWS_Core_Admin::register_hooks() (or CWS_Core_Admin::init()):
add_action( 'update_option_cws_core_job_slug', array( $this, 'flush_rules_on_slug_change' ), 10, 3 );

public function flush_rules_on_slug_change( $old_value, $new_value, $option ) {
    if ( $old_value !== $new_value ) {
        flush_rewrite_rules();
        $this->plugin->log( sprintf( 'Job slug changed from "%s" to "%s" — rewrite rules flushed', $old_value, $new_value ), 'info' );
    }
}
```

Note: The `update_option_{option_name}` action signature is `( $old_value, $new_value, $option_name )`.

### Anti-Patterns to Avoid

- **Hooking at `wp` or `parse_request` instead of `template_redirect`:** These fire before WP_Query is fully resolved. `template_redirect` is the correct hook for swapping templates/posts.
- **Using `query_posts()` to change the main query:** This creates a secondary query and breaks pagination/other features. Use direct `$wp_query` property manipulation instead.
- **Returning early without `exit` after `include(get_404_template())`:** The 404 template include will fall through to normal execution. Always call `exit` after serving the 404.
- **Storing `$this->current_job` without calling `format_job_data()`:** `get_job()` returns raw API data with inconsistent keys. Always format before storing.
- **Checking `$this->current_job` inside `inject_options()` before `handle_single_job()` runs:** The filter `etch/dynamic_data/option` fires inside Etch's `the_content` processing (priority 1). `template_redirect` fires before `the_content`, so the order is correct — `handle_single_job()` will have populated `$this->current_job` by the time `inject_options()` is called.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Listing published WP pages | Custom `WP_Query` for pages | `get_pages()` | Built-in, caches results, handles status/hierarchy automatically |
| Validating page still exists | Manual DB query | `get_post( $id )` + check `post_status` | Handles trashed, deleted, draft states correctly |
| Sanitizing integer option | `intval()` or custom validator | `absint` as sanitize_callback | WordPress standard; handles negatives, type coercion |
| Flushing rules after save | Custom transient/flag | `update_option_{name}` action hook | Already fires at the right time; no custom logic needed |
| Detecting a 404 situation | Redirecting or custom headers | `$wp_query->set_404()` + `status_header(404)` + `nocache_headers()` | Sets all WP internal flags; correct for theme compatibility |

**Key insight:** WordPress already provides all the primitives needed. The risk is building custom solutions that miss edge cases (trashed pages, multisite, caching layers) that core functions handle.

---

## Common Pitfalls

### Pitfall 1: Conflict with Existing handle_job_request() Stub

**What goes wrong:** `class-cws-core.php` already hooks `handle_job_request()` at `template_redirect` (line 173 in `register_hooks()`). If the new Etch handler is also added at `template_redirect`, both fire. The stub returns immediately, so it's harmless today — but if the stub is left in place it creates confusion and is dead code.

**Why it happens:** The stub was a placeholder that says "Phase 2 will implement single-job routing here." CONTEXT.md redirected Phase 2 logic to `CWS_Core_Etch` instead.

**How to avoid:** In Plan 02-01, remove the `add_action( 'template_redirect', array( $this, 'handle_job_request' ), ... )` line from `CWS_Core::register_hooks()` and remove or empty the `handle_job_request()` method. The `CWS_Core_Etch::handle_single_job()` becomes the sole handler.

**Warning signs:** Two `template_redirect` hooks from the same plugin both touching the same query var.

### Pitfall 2: get_job() Returns Raw Data, Not Formatted

**What goes wrong:** `CWS_Core_API::get_job( $job_id )` returns `$data['queryResult'][0]` — a raw API response array. It does NOT call `format_job_data()`. If `cws_job` is injected raw, Etch templates using `{options.cws_job.title}` may work (if the API uses `title` as the key) but other fields like `location` (which `format_job_data()` reshapes into a nested array) will be inconsistent.

**Why it happens:** `get_jobs()` also returns raw data. The `format_job_data()` method exists but is not called in the data-access path.

**How to avoid:** In `handle_single_job()`, after calling `get_job()`, pass the result through `$this->plugin->api->format_job_data( $raw_job )` before storing in `$this->current_job`.

**Warning signs:** `{options.cws_job.location.city}` returning nothing while `{options.cws_job.primary_city}` works (the raw key).

### Pitfall 3: WP_Query Not Fully Swapped

**What goes wrong:** Swapping only `global $post` is not enough. Etch (and WordPress theme functions) inspect multiple `$wp_query` properties to determine page context. If `$wp_query->is_page`, `$wp_query->is_singular`, and `$wp_query->queried_object` are not updated, theme templates may fall back to wrong templates, and functions like `is_page()` return false.

**Why it happens:** Developers see `the_content` work but find sidebar/breadcrumbs/title wrong.

**How to avoid:** Set all relevant `$wp_query` properties: `posts`, `post`, `post_count`, `found_posts`, `is_404`, `is_page`, `is_singular`, `queried_object`. Call `setup_postdata( $post )` after.

**Warning signs:** Theme shows 404 layout despite job data rendering in Etch blocks.

### Pitfall 4: Rewrite Flush Fires on Every Page Load

**What goes wrong:** If `flush_rewrite_rules()` is called on every `init` action (e.g., when checking slug), it writes to the database on every request — catastrophic for performance.

**Why it happens:** Using `add_action( 'init', 'flush_rewrite_rules' )` instead of responding to an option-change event.

**How to avoid:** ONLY flush inside the `update_option_cws_core_job_slug` action hook callback. Never call `flush_rewrite_rules()` unconditionally on `init` or `plugins_loaded`.

**Warning signs:** Database writes on every frontend page load; slow admin.

### Pitfall 5: Health Check Warning Appears on Wrong Admin Screens

**What goes wrong:** Using `add_action( 'admin_notices', ... )` for the health check would display a banner on every admin page, not just the settings page.

**Why it happens:** `admin_notices` is the "easy" way to display notices but it's global.

**How to avoid:** Per CONTEXT.md, render the warning inline inside `render_job_template_page_field()` (the field renderer), not via `admin_notices`. This ensures the warning only appears below the relevant field on the settings page.

### Pitfall 6: Template Page Posts Array Not Correctly Set

**What goes wrong:** Setting `$wp_query->posts[0]` but not `$wp_query->post` (or vice versa) causes subtle failures — some theme functions use `$wp_query->post`, others use `$wp_query->posts[0]`.

**How to avoid:** Set BOTH `$wp_query->post` and `$wp_query->posts = array( $template_page )` as shown in Pattern 1 above.

---

## Code Examples

### Verified: WordPress template_redirect 404 Pattern

```php
// Source: WordPress core (wp-includes/template-loader.php), widely documented
global $wp_query;
$wp_query->set_404();
status_header( 404 );
nocache_headers();
include( get_404_template() );
exit;
```

### Verified: update_option_{option} Action Hook Signature

```php
// Source: WordPress developer docs — action: update_option_{option_name}
// Fires AFTER the option has been saved, with old value, new value, option name
add_action( 'update_option_cws_core_job_slug', function( $old_value, $new_value, $option_name ) {
    // $old_value — value before save
    // $new_value — value just saved
    flush_rewrite_rules();
}, 10, 3 );
```

### Verified: get_pages() for Dropdown Population

```php
// Source: WordPress developer docs
$pages = get_pages( array(
    'post_status' => 'publish',
    'sort_column' => 'post_title',
    'sort_order'  => 'ASC',
) );
// Returns array of WP_Post objects or empty array (never false)
```

### Verified: Existing get_option() Pattern in Admin

```php
// Source: class-cws-core-admin.php (existing code)
// Use get_option() directly in field renderers (not $this->plugin->get_option())
// because admin class accesses options directly
$value = (int) get_option( 'cws_core_job_template_page_id', 0 );
```

Note: `CWS_Core::get_option( 'job_template_page_id' )` adds the `cws_core_` prefix automatically, so it can be used from the Etch class. The admin class uses `get_option( 'cws_core_job_template_page_id' )` directly (consistent with all other admin field renderers in the existing code).

---

## Critical Architectural Findings

### Finding 1: Existing template_redirect Stub Must Be Removed

`class-cws-core.php` line 173:
```php
add_action( 'template_redirect', array( $this, 'handle_job_request' ) );
```

And the method (lines 212-215):
```php
public function handle_job_request() {
    // Phase 2 will implement single-job routing here.
    return;
}
```

**Action required in Plan 02-01:** Remove this `add_action` call from `register_hooks()` and either remove or deprecate `handle_job_request()`. The new `CWS_Core_Etch::handle_single_job()` replaces it entirely.

### Finding 2: $current_job Property Not Yet Declared

`class-cws-core-etch.php` declares `$current_job_id = null` (line 31) but NOT `$current_job`. Phase 2 needs both:

```php
private $current_job_id = null;  // Already exists
private $current_job    = null;  // Must be added in Plan 02-01
```

### Finding 3: get_job() Is NOT Cached Per Job ID

`CWS_Core_API::fetch_job_data()` caches with key `'job_data_' . md5( $url )` — which IS per job ID since each job ID generates a unique URL. So `get_job( $job_id )` IS cached, just via MD5 of the API URL. No change needed.

### Finding 4: URL Section is the Correct Settings Section

The settings page already has a `cws_core_url_section` section with "Job URL Slug" and "Job IDs" fields. `cws_core_job_template_page_id` belongs in this section (add after the slug field).

---

## State of the Art

| Concern | Old Approach (virtual CPT) | Phase 2 Approach | Why Better |
|---------|---------------------------|-----------------|------------|
| Single job template | Custom PHP template file (`templates/job.php`) | Real WP page with Etch builder template | Editor-configurable; no PHP deployment needed |
| Job data on single page | Virtual post metadata via `get_post_metadata` hooks | `{options.cws_job}` via `etch/dynamic_data/option` filter | Official Etch API; survives upgrades |
| 404 for unknown jobs | Template rendered with empty data | WordPress `set_404()` with proper headers | Correct HTTP status; SEO-clean |
| Rewrite flush | Manual admin button only | Automatic on slug change + manual button preserved | Eliminates "forgot to flush" support issues |

---

## Open Questions

1. **Should `handle_job_request()` in CWS_Core be removed or emptied?**
   - What we know: It's a stub that immediately returns; the Etch class will be the new handler
   - What's unclear: Whether any future code might call it directly
   - Recommendation: Remove the `add_action` line; leave the method body as empty with a deprecation comment for safety

2. **Should `cws_jobs` also call `format_job_data()` on each job?**
   - What we know: Phase 1 `inject_options()` passes raw API data for `cws_jobs` (array of raw objects)
   - What's unclear: Whether Etch templates in production currently rely on raw key names vs formatted key names
   - Recommendation: Out of scope for Phase 2. Fix `cws_job` (singular) to use formatted data. Leave `cws_jobs` behaviour unchanged to avoid breaking live templates. Document inconsistency.

3. **What happens if `$wp_query->posts` is an empty array when handle_single_job() fires?**
   - What we know: The rewrite rule maps `/job/{id}/` to `index.php?cws_job_id=X` which does not match any real post, so WP_Query may return empty `posts` / set `is_404=true` before `template_redirect` fires
   - What's unclear: Whether we need to also clear the `is_404` flag on WP_Query after swapping
   - Recommendation: Include `$wp_query->is_404 = false` in the post-swap code (shown in Pattern 1) to ensure the 404 template is not served even when WP's initial query found nothing.

---

## Sources

### Primary (HIGH confidence)

- WordPress developer docs (developer.wordpress.org) — `template_redirect`, `WP_Query`, `get_pages()`, `update_option_{option}`, `set_404()`, `get_404_template()`
- Codebase direct inspection — `class-cws-core.php`, `class-cws-core-etch.php`, `class-cws-core-api.php`, `class-cws-core-admin.php` (all read in full)

### Secondary (MEDIUM confidence)

- WordPress Settings API patterns — verified against existing code in `class-cws-core-admin.php::register_settings()` which uses identical patterns

### Tertiary (LOW confidence)

- None — all findings are either from official WordPress API docs or direct codebase inspection

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — pure WordPress core APIs, all verified against official docs
- Architecture: HIGH — based on direct codebase inspection; all integration points confirmed
- Pitfalls: HIGH — conflict with existing stub and raw-data issue both discovered by reading actual code

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (WordPress APIs are stable; Etch filter confirmed from Phase 1)
