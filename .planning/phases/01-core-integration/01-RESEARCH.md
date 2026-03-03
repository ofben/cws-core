# Phase 1: Core Integration - Research

**Researched:** 2026-03-01
**Domain:** WordPress plugin — virtual CPT removal + Etch `etch/dynamic_data/option` filter integration
**Confidence:** HIGH — all critical claims verified against actual source files (Etch v1.3.1 + cws-core PHP)

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

All implementation decisions for this phase are delegated to Claude. User did not specify
preferences for any area — proceed with best-practice defaults.

### Claude's Discretion

- **Job source scope:** Inject only explicitly configured job IDs (`cws_core_job_ids` option).
  Discovered job IDs are a virtual CPT concept; do not carry them forward into the new class.
  If a configured job ID fails to fetch, skip it silently and log if debug mode is on.

- **Cleanup depth:** Full removal. Delete `class-cws-core-virtual-cpt.php`,
  `templates/job.php`, and all 13 root `test-*.php` / `debug-*.php` files. These files
  all test virtual CPT functionality and have no value after removal.

- **API failure behavior:** Return empty array silently. If API is unavailable and cache is
  cold, `{options.cws_jobs}` resolves to `[]` — Etch renders an empty loop, no PHP error,
  no crash. Log the failure via `$this->plugin->log()` if debug mode is on.

- **New class pattern:** Follow existing conventions — `CWS_Core` namespace, `class-cws-core-etch.php`
  filename, constructor receives `CWS_Core $plugin`, PHPDoc on class and public methods, tabs
  indentation, `array()` not `[]`. Register hooks in `init()` method matching the existing pattern.

- **Logging:** Use `$this->plugin->log()` exclusively (debug-gated). No raw `error_log()` calls
  in the new class.

### Deferred Ideas (OUT OF SCOPE)

None — user did not raise any out-of-scope ideas. Discussion stayed within phase scope.
</user_constraints>

---

## Summary

Phase 1 removes the 2,494-line virtual CPT infrastructure and wires a new, lean `CWS_Core_Etch`
class that injects job data into any Etch template via the `etch/dynamic_data/option` filter. The
filter has been confirmed in Etch v1.3.1 at `classes/Traits/DynamicData.php:196` — it accepts a
single `array $data` argument, must return an array, and its result is cached for the page lifetime.
All Phase 1 work uses only existing WordPress APIs and existing plugin classes (`CWS_Core_API`,
`CWS_Core_Cache`); no new dependencies are introduced.

The two implementation plans map cleanly onto two sequential concerns: (1) surgical removal of the
virtual CPT class and all its references — files, includes, wiring in the coordinator, admin AJAX
handlers, and root-level test/debug scripts; (2) creation of `class-cws-core-etch.php` with the
filter registration that populates `{options.cws_jobs}`. The filter firing once per page (Etch
caches the result in `$this->cached_data['option_page']`) means `inject_options()` is called
exactly once per render — no per-block overhead.

The key constraint the planner must enforce is atomic removal: the virtual CPT file reference in
`cws-core.php` and its instantiation in `class-cws-core.php` must be removed in the same commit
as the new class is wired in, because PHP registers the virtual CPT's 20+ hooks the moment the
class file is `require_once`'d — even if instantiation is skipped.

**Primary recommendation:** Remove virtual CPT in plan 01-01 first (no new code), then create and
wire `CWS_Core_Etch` in plan 01-02. Test each plan independently with `WP_DEBUG` on.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| WordPress Hooks API | Core | All integration points (`add_filter`, `add_action`) | Only sanctioned WP integration method; survives upgrades |
| WordPress Transients API | Core | Job data caching (already in `CWS_Core_Cache`) | No infrastructure requirement; works everywhere |
| `wp_remote_get()` | Core | HTTP client for jobs API (already in `CWS_Core_API`) | WP-native; no composer dependency |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| `CWS_Core_API` | plugin | Fetch and format jobs from external API | Use `get_jobs( array $ids )` in filter callback |
| `CWS_Core_Cache` | plugin | Transient wrapper (transparent via API layer) | No direct use needed — API layer handles caching |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `etch/dynamic_data/option` filter | `render_block_data` at priority 9 | Fallback only — more brittle, requires Etch internals knowledge |
| Existing `CWS_Core_API::get_jobs()` | Direct `wp_remote_get()` in new class | Never re-implement — cache layer is already correct |

**Installation:** No new packages. All dependencies are WordPress core or existing plugin classes.

---

## Architecture Patterns

### Recommended File Changes

```
cws-core/
├── cws-core.php                          # MODIFY: remove virtual CPT require_once; add Etch require_once
├── includes/
│   ├── class-cws-core.php                # MODIFY: remove virtual_cpt property, init, wiring, and handle_job_request body
│   ├── class-cws-core-etch.php           # CREATE: all Etch integration (~80-100 lines)
│   ├── class-cws-core-admin.php          # MODIFY: remove test_virtual_cpt_ajax method and its AJAX hook registration
│   ├── class-cws-core-virtual-cpt.php    # DELETE
│   └── [api, cache, public unchanged]
├── templates/
│   └── job.php                           # DELETE (+ job-debug-template.html, job-template-examples.html)
└── [13 root test-*.php / debug-*.php]    # DELETE
```

### Pattern 1: Etch Dynamic Data Filter (Phase 1 core)

**What:** Hook `etch/dynamic_data/option` to inject `cws_jobs` (and later `cws_job`) into Etch's
`options` context. The filter fires once per page render, result cached for the page lifetime.

**When to use:** Any time external data needs to be available as `{options.*}` in Etch templates.

**Confirmed signature** (Etch v1.3.1, `classes/Traits/DynamicData.php:196`):
```php
// Source: /wp-content/plugins/etch/classes/Traits/DynamicData.php:196 (confirmed)
$data_filtered = apply_filters( 'etch/dynamic_data/option', $data );
// - Single argument only ($data is array<string, mixed>)
// - Must return array — E_USER_WARNING fires if not
// - Result cached in $this->cached_data['option_page'] — filter fires ONCE per page
```

**Implementation skeleton for `class-cws-core-etch.php`:**
```php
<?php
/**
 * CWS Core Etch Integration Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates job data with the Etch page builder via the dynamic data filter.
 */
class CWS_Core_Etch {

	/**
	 * Plugin instance.
	 *
	 * @var CWS_Core
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param CWS_Core $plugin Plugin instance.
	 */
	public function __construct( CWS_Core $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function init() {
		add_filter( 'etch/dynamic_data/option', array( $this, 'inject_options' ), 10, 1 );
	}

	/**
	 * Inject job data into Etch options context.
	 *
	 * @param array $options Current options context array.
	 * @return array Modified options context.
	 */
	public function inject_options( array $options ) {
		$job_ids = $this->plugin->get_configured_job_ids();

		if ( empty( $job_ids ) ) {
			$this->plugin->log( 'No configured job IDs — cws_jobs will be empty', 'info' );
			$options['cws_jobs'] = array();
			return $options;
		}

		$jobs = $this->plugin->api->get_jobs( $job_ids );

		if ( ! empty( $jobs ) && is_array( $jobs ) ) {
			$options['cws_jobs'] = array_values( $jobs );
			$this->plugin->log( sprintf( 'Injected %d jobs into cws_jobs', count( $jobs ) ), 'debug' );
		} else {
			$options['cws_jobs'] = array();
			$this->plugin->log( 'API returned no jobs — cws_jobs is empty', 'info' );
		}

		return $options;
	}
}
```

**Note on `get_jobs()` return value:** The actual `CWS_Core_API::get_jobs()` method returns an
empty `array()` (not `false`) on failure — confirmed at `class-cws-core-api.php:292-294`. The
CONTEXT.md description ("returns array or false") describes `fetch_job_data()`, not `get_jobs()`.
Guard with `! empty( $jobs )` rather than `false === $jobs`.

### Pattern 2: Hook Registration in Coordinator (`class-cws-core.php`)

**What:** Swap the virtual CPT wiring for the new Etch class. Follow the exact existing pattern.

**Changes to `class-cws-core.php`:**

1. Remove `$virtual_cpt` property declaration (lines 50-55)
2. In `init_components()`: remove the `CWS_Core_Virtual_CPT` block (lines 106-112) and add:
   ```php
   if ( class_exists( 'CWS_Core\\CWS_Core_Etch' ) ) {
       $this->etch = new CWS_Core_Etch( $this );
   }
   ```
3. In `init()`: remove the `$this->virtual_cpt->init()` block (lines 154-159) and add:
   ```php
   if ( $this->etch && method_exists( $this->etch, 'init' ) ) {
       $this->etch->init();
   }
   ```
4. In `handle_job_request()`: remove the entire body that calls `$this->virtual_cpt` (lines 241-257)
   — the method can remain but return early (or be emptied) since Phase 1 doesn't implement single-job routing
5. Remove `test_virtual_post()` method entirely (lines 343-352)
6. Add `$this->etch = null;` property declaration to match pattern

**Changes to `cws-core.php`:**
- Line 42: replace `require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-virtual-cpt.php';`
  with `require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-etch.php';`

### Pattern 3: Admin Class Cleanup

**What:** Remove the `test_virtual_cpt_ajax` AJAX handler and its registration from `class-cws-core-admin.php`.

**Changes to `class-cws-core-admin.php`:**
- Remove `add_action( 'wp_ajax_cws_core_test_virtual_cpt', ... )` from `init()` (line 60)
- Remove `test_virtual_cpt_ajax()` method (lines 634-~715)
- Remove nonce generation for `test_virtual_cpt` from settings page (line 260)
- Remove the "Test Virtual CPT" button HTML from settings page (lines 341-344)

### Anti-Patterns to Avoid

- **Partial virtual CPT removal:** Do not comment out instantiation while leaving the `require_once`.
  The class file being loaded means PHP sees all its hooks at class-load time in some configurations.
  Remove the `require_once` entirely.
- **Calling `get_jobs()` outside `inject_options()`:** The filter fires once per page and Etch
  caches the result. Fetching jobs anywhere else creates redundant API/cache calls.
- **Using `false` check on `get_jobs()`:** `CWS_Core_API::get_jobs()` returns `array()` on failure
  (not `false`). Use `! empty( $jobs )` not `false !== $jobs`.
- **Raw `error_log()` in new class:** Use `$this->plugin->log()` exclusively. The base class
  already gates logging behind the `cws_core_debug_mode` option.
- **Leaving `$GLOBALS['cws_virtual_posts']` references:** `handle_job_request()` in `class-cws-core.php`
  sets `$GLOBALS['cws_virtual_posts']` — this global must not be set after Phase 1.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Job fetching + caching | Custom HTTP + transient logic | `CWS_Core_API::get_jobs( $ids )` | Already handles URL building, cache key generation, error handling, response parsing |
| Job ID list parsing | `explode` + type checks in new class | `CWS_Core::get_configured_job_ids()` | Already exists at `class-cws-core.php:359-363`; returns filtered numeric array |
| Debug-gated logging | `if (WP_DEBUG) error_log(...)` | `$this->plugin->log( $msg, $level )` | Already exists; gates on `cws_core_debug_mode` option |
| Etch data caching | Per-request static variable | Nothing — Etch's own `$this->cached_data['option_page']` | Etch caches the filter result; `inject_options()` is called once per page automatically |

**Key insight:** The entire data layer is already built and working. `CWS_Core_Etch::inject_options()`
is a thin adapter — it calls existing methods and returns the augmented array.

---

## Common Pitfalls

### Pitfall 1: Partial Virtual CPT Removal Leaves Conflicting Hooks

**What goes wrong:** Virtual CPT registers 20+ WordPress hooks (`pre_get_posts`, `get_post_metadata`
at priorities 1/10/999, `the_posts`, `posts_pre_query`, etc.) — all registered in its `init()`.
If the file is still `require_once`'d, those hooks can fire even if instantiation is skipped.

**Why it happens:** Developers comment out `new CWS_Core_Virtual_CPT( $this )` but leave the
`require_once` in `cws-core.php`. In PHP, class definition alone does not register hooks, but it
creates the risk of accidental instantiation from test/debug scripts that are also being deleted.

**How to avoid:** Remove `require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-virtual-cpt.php';`
from `cws-core.php` in the same commit that deletes the physical file. Also remove all 4 virtual CPT
references from `class-cws-core.php` in the same commit.

**Warning signs:** PHP fatal "Class not found" after partial removal means the file was deleted
but the `require_once` was not removed. PHP notices about undefined property `$virtual_cpt` mean
the property declaration was removed but references in `handle_job_request()` were not.

---

### Pitfall 2: `get_jobs()` Returns Empty Array (Not False) on Failure

**What goes wrong:** CONTEXT.md describes the API returning "array of formatted job arrays or
false on failure" — this describes `fetch_job_data()`, not `get_jobs()`. The `get_jobs()` method
(line 289-297 of `class-cws-core-api.php`) returns `array()` in ALL failure cases.

**How to avoid:** Guard with `! empty( $jobs )` in `inject_options()`, not `false === $jobs`.

---

### Pitfall 3: Etch Filter Fires at `the_content` Priority 1 — Too Late for Data Stored in Filter

**What goes wrong:** Etch hooks `prepare_content_blocks` to `the_content` at priority 1
(`Preprocessor.php:34`). This is the earliest `the_content` fires. The `etch/dynamic_data/option`
filter fires *inside* that callback. The job data must be available at filter invocation time.

**For Phase 1 (listing page only):** This is NOT a problem. `inject_options()` reads configured
job IDs from the options table and calls `get_jobs()` — both are available at any WordPress
hook point. No data storage timing is required.

**For Phase 2 (single job routing):** The `$current_job_id` class property must be populated
during `template_redirect` (before `the_content` fires). Phase 1 skeleton should include the
`$current_job_id` property and `handle_job_request()` stub even if empty, so Phase 2 can fill it.

**Warning signs:** `{options.cws_jobs}` renders blank despite no PHP errors. Add
`$this->plugin->log( 'inject_options called, job count: ' . count($jobs), 'debug' )` — if not
logged, the filter is not firing.

---

### Pitfall 4: Admin Class Still References `$this->plugin->virtual_cpt` After CPT Removal

**What goes wrong:** `class-cws-core-admin.php:648` checks `$this->plugin->virtual_cpt` and
line 664 calls `$this->plugin->virtual_cpt->create_virtual_job_post()`. After the property is
removed from `CWS_Core`, this generates PHP notices and the AJAX handler crashes.

**How to avoid:** Remove `test_virtual_cpt_ajax()` from admin class and its AJAX registration
in the same commit as virtual CPT removal.

---

### Pitfall 5: Root-Level Test/Debug Files Reference Removed Class

**What goes wrong:** 13 root-level files (`test-*.php`, `debug-*.php`) access `$cws_core->virtual_cpt`
or `CWS_Core\CWS_Core_Virtual_CPT`. If these files exist after Phase 1, anyone loading them
(e.g., via browser URL or WP-CLI) gets a fatal PHP error.

**How to avoid:** Delete all 13 files atomically with the virtual CPT class. Confirmed list:
`debug-check.php`, `debug-meta.php`, `debug-query.php`, `debug-template.php`,
`debug-virtual-posts-simple.php`, `debug-virtual-posts.php`, `test-etchwp-meta.php`,
`test-etchwp-rest.php`, `test-meta-query-simple.php`, `test-meta-query.php`,
`test-unique-ids.php`, `test-virtual-cpt.php`, `test-virtual-meta.php`.

---

### Pitfall 6: `handle_job_request()` in Main Coordinator Must Not Crash

**What goes wrong:** The existing `handle_job_request()` (lines 220-261 of `class-cws-core.php`)
calls `$this->virtual_cpt->create_virtual_job_post()` and sets `$GLOBALS['cws_virtual_posts']`.
After virtual CPT removal, if this method is not cleaned up, accessing `$this->virtual_cpt` (now
null) causes a fatal error on any job URL request (`/job/123/`).

**How to avoid:** In Phase 1, replace the entire body of `handle_job_request()` with an early
return (the method signature stays — Phase 2 will implement it properly). Success criterion 5
requires existing rewrite rules to "still fire" — the rules fire via `add_rewrite_rules()` which
is independent of `handle_job_request()`. The method can be empty in Phase 1.

---

## Code Examples

Verified patterns from actual source files:

### Hook Registration Following Existing Pattern

```php
// Source: includes/class-cws-core.php — existing component init pattern
// In init_components():
if ( class_exists( 'CWS_Core\\CWS_Core_Etch' ) ) {
	$this->etch = new CWS_Core_Etch( $this );
}

// In init():
if ( $this->etch && method_exists( $this->etch, 'init' ) ) {
	$this->etch->init();
}
```

### Reading Configured Job IDs (already exists)

```php
// Source: includes/class-cws-core.php:359-363
public function get_configured_job_ids() {
	$job_ids_string = $this->get_option( 'job_ids', '22026695' );
	$job_ids = array_map( 'trim', explode( ',', $job_ids_string ) );
	return array_filter( $job_ids, 'is_numeric' );
}
```

### Calling API Layer (verified return type)

```php
// Source: includes/class-cws-core-api.php:289-297
// get_jobs() returns array() (NOT false) on failure:
public function get_jobs( $job_ids ) {
	$data = $this->fetch_job_data( $job_ids );
	if ( false === $data || empty( $data['queryResult'] ) ) {
		return array();  // ← always array, never false
	}
	return $data['queryResult'];
}
```

### Confirmed Etch Filter Location

```php
// Source: /wp-content/plugins/etch/classes/Traits/DynamicData.php:196 (Etch v1.3.1)
$data_filtered = apply_filters( 'etch/dynamic_data/option', $data );
if ( ! is_array( $data_filtered ) ) {
	trigger_error( 'etch/dynamic_data/option filter must return an array', E_USER_WARNING );
	$this->cached_data[ $cache_key ] = $data;
} else {
	$this->cached_data[ $cache_key ] = $data_filtered;
}
```

### Etch Template Usage (target end state)

```
{#loop options.cws_jobs as job}
  {job.title}
  {job.company_name}
  {job.salary}
  {job.employment_type}
  {job.primary_city}, {job.primary_state}
  {job.description}
{/loop}
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Virtual CPT: 20+ WP hooks, 2,494 lines | Single `etch/dynamic_data/option` filter, ~80 lines | Phase 1 | Eliminates Etch upgrade breakage |
| `$GLOBALS['cws_virtual_posts']` state | Class property `$current_job_id` (Phase 2) | Phase 1→2 | Scoped state, no global pollution |
| Virtual post meta for Etch field access | Direct array key access via filter | Phase 1 | No WP_Query interception; dot-notation works natively |
| 13 root test/debug scripts | None (deleted) | Phase 1 | Eliminates dead code and CSRF-vulnerable admin actions |

**Deprecated/outdated:**
- `class-cws-core-virtual-cpt.php`: Replaced entirely. Do not preserve or migrate any of its logic.
- `templates/job.php`: The job template is now a real WordPress page with an Etch block template. The PHP file is superseded.
- `$this->plugin->virtual_cpt` property: Removed. Any code referencing it is dead code.
- `cws_core_test_virtual_cpt` AJAX action: Removed with admin handler cleanup.

---

## Open Questions

1. **Does `get_configured_job_ids()` stay in `CWS_Core` or move to `CWS_Core_Etch`?**
   - What we know: The method currently lives in `class-cws-core.php` and is called by multiple
     components (admin, public, main class). Moving it would require updating callers.
   - What's unclear: Whether any remaining components (admin, public) call it after Phase 1.
   - Recommendation: Leave it in `CWS_Core` (the main coordinator). `CWS_Core_Etch` calls it
     via `$this->plugin->get_configured_job_ids()` — consistent with how other components access
     shared data.

2. **Should `handle_job_request()` be emptied or removed from `class-cws-core.php` in Phase 1?**
   - What we know: Phase 2 will implement single-job routing in `CWS_Core_Etch`, not in the
     main coordinator. The method's current hook (`template_redirect`) will move to `CWS_Core_Etch::init()`.
   - What's unclear: Whether the `template_redirect` hook registration in `register_hooks()` should
     be removed now or deferred to Phase 2 when it's moved to the Etch class.
   - Recommendation: Empty the method body in Phase 1 (leave it returning early). Remove the
     `template_redirect` hook in Phase 2 when the Etch class takes over. This avoids a dead hook
     but doesn't introduce risk.

3. **Which templates files in `templates/` directory should be deleted?**
   - What we know: Confirmed: `templates/job.php`, `templates/job-debug-template.html`,
     `templates/job-template-examples.html`. Directory itself has 3 files total.
   - Recommendation: Delete all 3 files. The `templates/` directory can be left empty or removed
     if no other templates are planned (Phase 2 uses a real WP page, not a PHP template file).

---

## File-Level Change Inventory

Exact list of all changes required for Phase 1 (for planner reference):

### Files to DELETE (13 root scripts + 3 templates + 1 include)

```
includes/class-cws-core-virtual-cpt.php
templates/job.php
templates/job-debug-template.html
templates/job-template-examples.html
debug-check.php
debug-meta.php
debug-query.php
debug-template.php
debug-virtual-posts-simple.php
debug-virtual-posts.php
test-etchwp-meta.php
test-etchwp-rest.php
test-meta-query-simple.php
test-meta-query.php
test-unique-ids.php
test-virtual-cpt.php
test-virtual-meta.php
```

### Files to CREATE (1 new class)

```
includes/class-cws-core-etch.php
```

### Files to MODIFY (3 existing files)

**`cws-core.php`** — line 42:
- Remove: `require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-virtual-cpt.php';`
- Add: `require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-etch.php';`

**`includes/class-cws-core.php`** — multiple locations:
- Remove property: `public $virtual_cpt = null;` (lines 50-55) → replace with `public $etch = null;`
- Remove in `init_components()`: `CWS_Core_Virtual_CPT` block (lines 106-112) → add `CWS_Core_Etch` wiring
- Remove in `init()`: `$this->virtual_cpt->init()` block (lines 154-159) → add `$this->etch->init()` wiring
- Empty `handle_job_request()` body (lines 221-261): replace with early return (rewrite rules still fire via `add_rewrite_rules()`)
- Remove `test_virtual_post()` method (lines 343-352)

**`includes/class-cws-core-admin.php`** — multiple locations:
- Remove `add_action( 'wp_ajax_cws_core_test_virtual_cpt', ... )` from `init()` (line 60)
- Remove `'test_virtual_cpt' => wp_create_nonce(...)` from nonce array (line 260)
- Remove "Test Virtual CPT" button HTML + result div from settings page (lines 341-344)
- Remove `test_virtual_cpt_ajax()` method (lines 634-~715)

---

## Sources

### Primary (HIGH confidence)

- `/wp-content/plugins/etch/classes/Traits/DynamicData.php:196` — `etch/dynamic_data/option` filter confirmed (direct inspection)
- `/wp-content/plugins/etch/classes/Preprocessor/Preprocessor.php:34` — `the_content` priority 1 confirmed (direct inspection)
- `/wp-content/plugins/cws-core/includes/class-cws-core.php` — coordinator pattern, property declarations, init flow (direct inspection)
- `/wp-content/plugins/cws-core/includes/class-cws-core-api.php:289-297` — `get_jobs()` return type confirmed as `array()` (direct inspection)
- `/wp-content/plugins/cws-core/cws-core.php` — exact `require_once` to replace at line 42 (direct inspection)
- `/wp-content/plugins/cws-core/includes/class-cws-core-admin.php:60,260,341,634` — virtual CPT references to remove (direct inspection)
- `.planning/research/ARCHITECTURE.md` — implementation skeleton and hook timing contract (HIGH, based on source inspection)
- `.planning/research/PITFALLS.md` — Pitfalls 2, 8, 9 directly applicable (HIGH, based on source inspection)
- `.planning/codebase/CONVENTIONS.md` — coding style: tabs, `array()`, PHPDoc requirements (HIGH)
- `.planning/codebase/INTEGRATIONS.md` — job field names for Etch dot-notation access (HIGH)

### Secondary (MEDIUM confidence)

- `.planning/codebase/CONCERNS.md` — admin CSRF vulnerability in virtual CPT GET actions, confirmed removed with class deletion
- `.planning/research/STACK.md` — integration approach analysis (note: filter was MEDIUM in v0.38.0 research, now HIGH after v1.3.1 confirmation)

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — uses only existing WordPress core APIs and plugin classes; no new dependencies
- Architecture: HIGH — filter signature confirmed in Etch v1.3.1 source; existing coordinator pattern verified in source
- Pitfalls: HIGH — all pitfalls derived from direct source inspection of specific file/line references

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (stable WP APIs; Etch filter unlikely to change in 30 days)
