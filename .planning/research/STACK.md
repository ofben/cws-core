# Technology Stack

**Project:** CWS Core — Etch Job Integration (Milestone: Dynamic Data Rebuild)
**Researched:** 2026-03-01
**Confidence:** HIGH (based on direct Etch v0.38.0 source code inspection + existing plugin PHP source)

---

## Summary

This milestone replaces the virtual CPT approach with the `etch/dynamic_data/option` filter. The
installed Etch v0.38.0 source was inspected directly. Key finding: the filter string
`etch/dynamic_data/option` does NOT appear as an `apply_filters()` call anywhere in the installed
plugin PHP. The filter is documented externally (snippetnest.com) and cited in PROJECT.md. The
actual mechanism — how `{options.*}` gets resolved — was traced through the source:
`EtchParser::process_expression()` resolves `{options.cws_jobs}` by looking up `options` in the
block context array. The Etch `BaseBlock::add_this_post_context()` sets `this`, `user`, `site`,
`url` — but NOT `options`. The `options` key must be injected externally.

The integration approach that will work regardless of whether `etch/dynamic_data/option` is a
live PHP filter or a future/different mechanism: **hook into the Etch preprocessing pipeline and
inject `options` into the block context**. Two verified paths exist for this. See the Integration
Pattern section for the definitive approach.

---

## Recommended Stack

### Core Technology

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| PHP | 7.4+ | Plugin implementation | Existing constraint — no change |
| WordPress | 5.0+ | Base platform | Existing constraint — no change |
| WordPress Hooks API | Core | All integration points | The only sanctioned integration method |
| WordPress Transients API | Core | Job data caching | Already implemented, well-proven |

### Reused Components (No Changes)

| Class | Location | Keep As-Is |
|-------|----------|------------|
| `CWS_Core_API` | `includes/class-cws-core-api.php` | Yes — HTTP client + response parsing |
| `CWS_Core_Cache` | `includes/class-cws-core-cache.php` | Yes — transient wrapper |
| `CWS_Core_Admin` | `includes/class-cws-core-admin.php` | Yes — settings UI |
| `CWS_Core` (main) | `includes/class-cws-core.php` | Mostly — remove virtual CPT wiring, keep rewrite rules |

### New Components

| Class | Location | Purpose |
|-------|----------|---------|
| `CWS_Core_Etch` | `includes/class-cws-core-etch.php` | All Etch integration — filter registration, data injection |

### Removed Components

| Class | Reason |
|-------|--------|
| `CWS_Core_Virtual_CPT` | `includes/class-cws-core-virtual-cpt.php` | Replaced entirely by `CWS_Core_Etch` |

---

## The `etch/dynamic_data/option` Filter — What We Actually Know

**Confidence: MEDIUM** — Cited in PROJECT.md (snippetnest.com source), not found in installed
Etch v0.38.0 PHP source as `apply_filters()`. Two possibilities:

1. The filter was added after v0.38.0 and this installation is on an older version
2. The filter exists but is fired from compiled JavaScript or a different code path

**Finding from source inspection:** The Etch `EtchParser::process_expression()` resolves
`{options.cws_jobs}` by looking up `options` as a key in the block `$context` array. The
`BaseBlock::add_this_post_context()` sets standard keys (`this`, `user`, `site`, `url`). The
`add_context($key, $value)` method allows injecting additional keys.

**Definitive integration approach:** If `etch/dynamic_data/option` exists as a PHP filter, hook
into it. If it does not fire (version mismatch), hook into `the_content` or `render_block_data`
with lower priority to inject the `options` context key before Etch's preprocessor runs.

### Filter Signature (if it exists)

Based on the PROJECT.md description and standard WordPress filter conventions:

```php
// Probable signature (MEDIUM confidence — based on naming convention + snippetnest doc):
add_filter( 'etch/dynamic_data/option', function( array $options, int $post_id ): array {
    // $options — array of current options context values (initially empty or existing)
    // $post_id — the ID of the current post being rendered
    $options['cws_jobs']  = []; // your data here
    $options['cws_job']   = []; // your data here
    return $options;
}, 10, 2 );
```

**Verification required:** The actual filter signature must be tested against the running plugin.
Add `add_filter('etch/dynamic_data/option', function($v) { error_log(print_r($v, true)); return
$v; })` to confirm it fires and what arguments it passes.

---

## WordPress Hooks and Patterns — Exact Signatures

All signatures below are HIGH confidence (verified against WordPress core or existing plugin source).

### 1. Rewrite Rules (existing, keep as-is)

```php
// Hook: WordPress 'init' action
add_action( 'init', function() {
    $slug = get_option( 'cws_core_job_slug', 'job' );

    // Match /job/12345/ — sets cws_job_id query var
    add_rewrite_rule(
        '^' . $slug . '/([0-9]+)/?$',
        'index.php?cws_job_id=$matches[1]',
        'top'
    );

    // Match /job/12345/some-title/ — sets both vars
    add_rewrite_rule(
        '^' . $slug . '/([0-9]+)/([^/]+)/?$',
        'index.php?cws_job_id=$matches[1]&cws_job_title=$matches[2]',
        'top'
    );
} );

// Must flush once after adding rules:
// flush_rewrite_rules(); — call on plugin activation only, never on every request
```

**Signature:** `add_rewrite_rule( string $regex, string $redirect, string $after ): void`

### 2. Query Vars (existing, keep as-is)

```php
// Hook: 'query_vars' filter
// Signature: add_filter( 'query_vars', callable $callback ): void
// Callback receives: array $vars — current list of recognized query vars
// Callback must return: array — the modified list

add_filter( 'query_vars', function( array $vars ): array {
    $vars[] = 'cws_job_id';
    $vars[] = 'cws_job_title';
    return $vars;
} );

// Reading the var anywhere after query setup:
$job_id = get_query_var( 'cws_job_id', '' );
```

### 3. Template Redirect for Single Job Routing

The `template_redirect` action fires after WordPress determines the current query but before any
template is loaded. This is where the plugin detects a job URL and loads the real WP page that
serves as the Etch template.

```php
// Hook: 'template_redirect' action — fires after WP_Query is set up, before template load
// Signature: add_action( 'template_redirect', callable $callback ): void
// Callback receives: no arguments
// Callback returns: nothing (side-effects only)

add_action( 'template_redirect', function(): void {
    $job_id = sanitize_text_field( get_query_var( 'cws_job_id' ) );
    if ( empty( $job_id ) ) {
        return;
    }

    // Get the real WP page that has the job template (slug = configured job slug)
    $job_slug = get_option( 'cws_core_job_slug', 'job' );
    $page     = get_page_by_path( $job_slug );

    if ( ! $page ) {
        return; // No template page configured yet — fall through to 404
    }

    // Store job_id in a global or transient for the etch/dynamic_data/option filter to read
    // Use a request-scoped global (safe: set at template_redirect, read at filter time)
    $GLOBALS['cws_current_job_id'] = $job_id;

    // Swap the global $post to the real template page
    // This makes Etch render against the real page's content/template
    global $post, $wp_query;
    $post = $page;
    setup_postdata( $post );
    $wp_query->queried_object    = $page;
    $wp_query->queried_object_id = $page->ID;
    $wp_query->is_page           = true;
    $wp_query->is_singular       = true;
    $wp_query->is_404            = false;
} );
```

**Why real WP page:** Etch renders its block templates against `global $post`. A virtual post is
invisible to Etch's REST API and block rendering pipeline. A real WordPress page with matching
slug is required for Etch to resolve its template and render `{options.*}` variables.

### 4. Etch Dynamic Data Filter (the integration core)

Based on source inspection of Etch v0.38.0 `EtchParser::resolve()` and `BaseBlock::add_this_post_context()`:
Etch resolves `{options.cws_jobs}` by looking up `options` in the block context array. The
`etch/dynamic_data/option` filter is the documented mechanism for populating that key.

```php
// Hook: 'etch/dynamic_data/option' filter
// Fires: During Etch's block preprocessing, once per page render
// Receives: array $options — current options context (initially empty array)
// Optional arg 2: int $post_id — ID of current post being rendered
// Must return: array — the augmented options context

add_filter( 'etch/dynamic_data/option', function( array $options ): array {
    // --- All jobs (for listing page) ---
    $plugin = \CWS_Core\CWS_Core::get_instance();
    $jobs   = $plugin->api->get_jobs( $plugin->get_configured_job_ids() );

    if ( is_array( $jobs ) ) {
        $options['cws_jobs'] = array_values( $jobs );
    }

    // --- Single job (for job detail page) ---
    $job_id = $GLOBALS['cws_current_job_id'] ?? get_query_var( 'cws_job_id', '' );
    if ( ! empty( $job_id ) ) {
        $job = $plugin->api->get_job( $job_id );
        if ( $job ) {
            $options['cws_job'] = $job;
        }
    }

    return $options;
}, 10, 1 );
```

**If the filter does not fire** (version issue), the fallback is to inject the `options` key via
`render_block_data` at priority 9 (before Etch's SingleDynamicManager at priority 15):

```php
// Fallback: inject options context before Etch processes blocks
add_filter( 'render_block_data', function( array $block, array $source_block ): array {
    // Only act on Etch-origin blocks
    $etch_data = $source_block['attrs']['etchData'] ?? null;
    if ( ! $etch_data || ( $etch_data['origin'] ?? '' ) !== 'etch' ) {
        return $block;
    }

    // Inject options into block attrs for Etch's parser to resolve
    // (This requires understanding Etch's internal attrs structure — verify before using)
    return $block;
}, 9, 2 );
```

**Recommendation:** Use `etch/dynamic_data/option` as the primary hook. Verify it fires in the
running installation before implementing the fallback.

### 5. Etch Builder Preview Detection

The Etch builder preview uses `?etch=magic` as the URL parameter when a logged-in user is
editing. The plugin must detect this context and return fallback job data.

```php
// Detecting Etch builder preview context:
$is_etch_preview = isset( $_GET['etch'] ) && $_GET['etch'] === 'magic' && is_user_logged_in();

// In the etch/dynamic_data/option callback:
add_filter( 'etch/dynamic_data/option', function( array $options ): array {
    $plugin  = \CWS_Core\CWS_Core::get_instance();
    $job_ids = $plugin->get_configured_job_ids();

    // Listing data — always inject for listing page template
    $jobs = $plugin->api->get_jobs( $job_ids );
    if ( is_array( $jobs ) ) {
        $options['cws_jobs'] = array_values( $jobs );
    }

    // Single job — inject real job or preview fallback
    $job_id = $GLOBALS['cws_current_job_id'] ?? get_query_var( 'cws_job_id', '' );

    $is_etch_preview = isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] && is_user_logged_in();

    if ( empty( $job_id ) && $is_etch_preview ) {
        // Fallback: use first configured job ID for builder preview
        $job_id = ! empty( $job_ids ) ? reset( $job_ids ) : '';
    }

    if ( ! empty( $job_id ) ) {
        $job = $plugin->api->get_job( $job_id );
        if ( $job ) {
            $options['cws_job'] = $job;
        }
    }

    return $options;
} );
```

---

## Etch Template Variable Syntax

**Confidence: HIGH** — verified via `EtchParser::process_expression()` and `EtchParser::resolve()` source.

The Etch parser resolves `{context_key.property.subproperty}` using dot-notation against the
block context array. Keys are resolved by iterating `$context` and matching prefix.

| Template Syntax | Context Key | Data Shape |
|-----------------|-------------|------------|
| `{options.cws_jobs}` | `options` → `cws_jobs` | `array` of job arrays |
| `{#loop options.cws_jobs as job}` | `options` → `cws_jobs` | Array used as loop source |
| `{job.title}` | `job` (loop item) | String |
| `{options.cws_job.title}` | `options` → `cws_job` → `title` | String |
| `{options.cws_job.description}` | `options` → `cws_job` → `description` | String (HTML) |

**Loop syntax in Etch template:** `{#loop options.cws_jobs as job}...{job.field}...{/loop}`

This maps to `LoopBlock::process_target_item_id()` which resolves `options` from the context,
then traverses `cws_jobs` as the target path, iterating each item as `job`.

---

## Data Shape: What the Filter Must Return

The job data array structure must use flat, camelCase-compatible keys for dot notation access.
Based on the API response fields in `INTEGRATIONS.md`:

```php
// Single job shape returned by $options['cws_job']:
[
    'id'              => '22026695',
    'title'           => 'Software Engineer',
    'company_name'    => 'Acme Corp',
    'description'     => '<p>Full job description HTML...</p>',
    'employment_type' => 'Full-time',
    'salary'          => '$100,000 - $120,000',
    'primary_city'    => 'New York',
    'primary_state'   => 'NY',
    'primary_country' => 'US',
    'primary_category'=> 'Engineering',
    'department'      => 'Product',
    'industry'        => 'Technology',
    'function'        => 'Software Development',
    'url'             => 'https://apply.example.com/job/22026695',
    'seo_url'         => 'https://company.com/job/software-engineer',
    'open_date'       => '2026-01-15T00:00:00Z',
    'update_date'     => '2026-02-20T00:00:00Z',
    'entity_status'   => 'ACTIVE',
    'pretty_url'      => '/job/22026695/', // generated by plugin
]
```

`$options['cws_jobs']` is an array of the above structures. Template access: `{job.title}`,
`{job.company_name}`, `{job.description}`, `{job.url}` etc.

---

## WordPress Routing: How Single Job Pages Work

**Confidence: HIGH** — traced through existing `class-cws-core.php` source.

```
Request: /job/22026695/
  ↓
WordPress rewrite rule matches: ^job/([0-9]+)/?$
  ↓
WP_Query runs with: cws_job_id=22026695
  ↓
template_redirect fires
  ↓
Plugin detects cws_job_id, stores in $GLOBALS['cws_current_job_id']
  ↓
Plugin swaps $post to real "job" page (get_page_by_path('job'))
  ↓
Etch renders the real page's block template
  ↓
During block rendering: etch/dynamic_data/option filter fires
  ↓
Plugin injects $options['cws_job'] = fetch job 22026695
  ↓
Etch template resolves {options.cws_job.title} etc.
```

**Critical constraint:** The real WP page must exist with slug matching `cws_core_job_slug`
(default: `job`). Without it, the `get_page_by_path()` call returns null and there is no template
to render. Admin setup documentation must call this out.

**Flush rewrite rules:** Must be called once when the job slug option changes, and on plugin
activation. The existing `flush_rewrite_rules()` call in `cws_core_activate()` handles activation.
The admin AJAX handler `cws_core_flush_rules` handles slug changes.

---

## WordPress APIs Used

| API | Function/Hook | Purpose | Confidence |
|-----|--------------|---------|------------|
| Rewrite API | `add_rewrite_rule()` | `/job/{id}/` URL pattern | HIGH |
| Query Vars | `add_filter('query_vars')` | Register `cws_job_id` | HIGH |
| Query Vars | `get_query_var('cws_job_id')` | Read job ID from URL | HIGH |
| Template | `add_action('template_redirect')` | Intercept job URL requests | HIGH |
| Post API | `get_page_by_path( $slug )` | Get real template page | HIGH |
| Post Data | `setup_postdata( $post )` | Set up global $post | HIGH |
| Transients | `get_transient()`, `set_transient()` | Cache job data | HIGH |
| Options | `get_option()`, `update_option()` | Plugin settings | HIGH |
| HTTP | `wp_remote_get()` | Fetch from jobs API | HIGH |
| Admin | `wp_ajax_{action}` | AJAX handlers | HIGH |

---

## Etch-Specific APIs

| API | Signature | Purpose | Confidence |
|-----|-----------|---------|------------|
| `etch/dynamic_data/option` | `apply_filters( 'etch/dynamic_data/option', array $options )` | Inject `options` context | MEDIUM |
| `etch/preview/additional_stylesheets` | `apply_filters( 'etch/preview/additional_stylesheets', array $sheets )` | Add CSS to builder preview | HIGH |
| `render_block_data` | `add_filter( 'render_block_data', callable, int $priority, 2 )` | Fallback for context injection | HIGH |

---

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Data injection | `etch/dynamic_data/option` filter | Virtual CPT + `get_post_metadata` | Virtual CPT breaks on Etch upgrades; fragments data across 2,494 lines of fragile code |
| Data injection | `etch/dynamic_data/option` filter | REST API endpoint for Etch | Etch doesn't call arbitrary REST endpoints for dynamic data |
| URL routing | WordPress rewrite rules | Query parameter `?job_id=123` | Worse SEO, existing `/job/{id}/` structure must be preserved |
| Template serving | Real WP page via `template_redirect` | Virtual post as `$post` | Etch cannot render its block template system against a non-database post |
| Preview fallback | Check `$_GET['etch'] === 'magic'` | Check `is_admin()` | Admin check does not catch Etch's iframe preview on the frontend |
| Caching | WordPress transients | Object cache (Memcached/Redis) | No infrastructure requirement; transients work without additional server configuration |

---

## Installation Notes

No new dependencies. All integration uses WordPress core APIs and existing plugin classes.

### Files to Create

```
includes/class-cws-core-etch.php   ← New: all Etch filter hooks
```

### Files to Modify

```
includes/class-cws-core.php        ← Remove virtual CPT wiring; add Etch class init
cws-core.php                       ← Remove virtual CPT file include; add Etch file include
```

### Files to Delete

```
includes/class-cws-core-virtual-cpt.php   ← 2,494-line virtual CPT — entire file removed
templates/job.php                         ← Old template for virtual post — likely superseded
```

---

## Confidence Assessment

| Area | Confidence | Evidence |
|------|------------|---------|
| WordPress rewrite rules + query vars | HIGH | Verified in existing `class-cws-core.php` source |
| `template_redirect` redirect approach | HIGH | Standard WP pattern; existing code uses it |
| `get_page_by_path()` for template page | HIGH | Core WordPress function; standard pattern |
| Etch `EtchParser` dot-notation resolution | HIGH | Verified in `classes/Preprocessor/Utilities/EtchParser.php` |
| Etch context keys (`this`, `user`, `site`, `url`) | HIGH | Verified in `BaseBlock::add_this_post_context()` |
| `etch/dynamic_data/option` filter signature | MEDIUM | In PROJECT.md (snippetnest.com), NOT found in Etch v0.38.0 PHP source |
| `?etch=magic` preview detection | MEDIUM | Cited in PROJECT.md; standard Etch preview URL; not verified in source |
| Data shape (job fields from API) | HIGH | Verified in `INTEGRATIONS.md` + `class-cws-core-api.php` |

---

## Critical Unknowns That Need Verification Before Build

1. **Does `etch/dynamic_data/option` actually fire?** Run: `add_filter('etch/dynamic_data/option', function($v) { error_log('FIRED: '.print_r($v,true)); return $v; });` in a test snippet and load a page with Etch content.

2. **What arguments does the filter pass?** The probable signature is `(array $options)` or `(array $options, int $post_id)`. Confirm with `func_get_args()`.

3. **Does a real WP page with slug `job` exist?** `get_page_by_path('job')` will return null if not. Page must be created manually during setup.

4. **Does swapping `$post` in `template_redirect` cause Etch to render the page's template?** Etch's `Preprocessor::prepare_content_blocks()` hooks into `the_content` filter and calls `parse_blocks()` — it processes whatever content `$post->post_content` contains. Replacing `global $post` before content renders should work.

---

## Sources

- Etch v0.38.0 source: `/wp-content/plugins/etch/classes/Preprocessor/Utilities/EtchParser.php` — resolver logic (HIGH)
- Etch v0.38.0 source: `/wp-content/plugins/etch/classes/Preprocessor/Blocks/BaseBlock.php` — context setup (HIGH)
- Etch v0.38.0 source: `/wp-content/plugins/etch/classes/Preprocessor/Preprocessor.php` — block processing pipeline (HIGH)
- Etch v0.38.0 source: `/wp-content/plugins/etch/classes/Preprocessor/Blocks/LoopBlock.php` — loop resolution (HIGH)
- CWS Core source: `/wp-content/plugins/cws-core/includes/class-cws-core.php` — existing rewrite rules (HIGH)
- PROJECT.md: `/wp-content/plugins/cws-core/.planning/PROJECT.md` — integration requirements (HIGH)
- INTEGRATIONS.md: `/wp-content/plugins/cws-core/.planning/codebase/INTEGRATIONS.md` — API field list (HIGH)
- External: snippetnest.com/snippet/loop-external-api-in-etch/ — `etch/dynamic_data/option` documentation (MEDIUM — not independently verified)
- WordPress Codex: `add_rewrite_rule()`, `add_filter('query_vars')`, `template_redirect`, `get_page_by_path()` (HIGH)

---

*Research completed: 2026-03-01. Based on direct source inspection of installed Etch v0.38.0.*
