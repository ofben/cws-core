# Phase 3: Preview and Polish - Research

**Researched:** 2026-03-01
**Domain:** WordPress plugin — Etch builder preview detection, PHP date formatting, transient cache audit
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

All implementation decisions fall within Claude's discretion — the roadmap plans are specific
enough and the user deferred to the existing direction:

- **Preview trigger**: Simple detection — `isset($_GET['etch']) && 'magic' === $_GET['etch'] && is_user_logged_in() && empty(get_query_var('cws_job_id'))`. Fires in `handle_single_job()` on `template_redirect`. No `post_id` check required — keeping it simple matches the roadmap plan description.

- **Sample job for preview**: Use the first configured job ID from `$this->plugin->get_configured_job_ids()`. If that list is empty, log a notice and return without injecting — do not 404, as the editor may still want to design the template shell.

- **Date format**: `F j, Y` (e.g., "March 1, 2026") as specified in the roadmap. Applied via PHP `date_create()` + `date_format()` or `wp_date()`. Two new keys added to `format_job_data()`: `open_date_formatted` and `update_date_formatted`. If the raw date is empty/null → empty string (consistent with other empty fields).

- **Cache clear scope**: Audit existing `clear_all()` SQL LIKE pattern (`_transient_cws_core_%`). The per-job transient keys are `cws_core_job_data_{md5(url)}` — these match the pattern and should already be deleted. If the LIKE underscore-as-wildcard behaviour causes any gaps, escape or verify. No new UI needed — the existing "Clear Cache" button is the mechanism.

- **cws_jobs in builder preview**: The `inject_options` filter fires for all Etch page renders including `?etch=magic`. No extra handling needed — `cws_jobs` will populate automatically via existing code path as long as job IDs are configured.

### Claude's Discretion

All implementation decisions are Claude's discretion — see locked decisions above for the specific approach already decided.

### Deferred Ideas (OUT OF SCOPE)

None — discussion stayed within phase scope.

</user_constraints>

---

## Summary

Phase 3 is a tight polish pass on top of the working Phase 1+2 foundation. The implementation
surface is small: one new branch in `handle_single_job()`, two new keys in `format_job_data()`,
and one SQL audit in `clear_all()`. All three areas have clear insertion points in already-existing
methods.

The builder preview branch (`?etch=magic`) is the most architecturally interesting piece. The
detection logic must live in `handle_single_job()` at `template_redirect` — this is where
`$this->current_job` gets populated. The `inject_options()` filter reads `$this->current_job`
and fires later during Etch's `the_content` priority 1 processing. The preview branch simply
populates `$this->current_job` via the same `get_job()` + `format_job_data()` path used for
real job URLs, using the first configured job ID as the sample. `cws_jobs` needs no changes —
it already populates unconditionally in `inject_options()`.

The date formatting addition to `format_job_data()` is straightforward. The confirmed WordPress
function is `wp_date()`, which reads the site timezone from WP settings and handles edge cases
around timezone-aware ISO 8601 strings. The cache `clear_all()` SQL audit is a verification-only
task — direct source inspection confirms the existing LIKE pattern `_transient_cws_core_%`
already covers all per-job transient keys (`_transient_cws_core_job_data_{md5}`). No code
change is required for cache clearing, only a verification comment in the task.

**Primary recommendation:** Add the preview branch to `handle_single_job()` first (Plan 03-01),
then add date fields and perform cache audit (Plan 03-02). Both plans are independent and can
be implemented sequentially within the same wave.

---

## Standard Stack

### Core

| Technology | Version | Purpose | Why Standard |
|------------|---------|---------|--------------|
| PHP | 7.4+ (existing) | All implementation | Existing constraint |
| WordPress Hooks API | Core | `template_redirect`, `etch/dynamic_data/option` | Only sanctioned integration method |
| `wp_date()` | WordPress core | Timezone-aware date formatting | Reads WP site timezone; handles ISO 8601 input |
| WordPress Transients API | Core | Cache storage and deletion | Already in use throughout plugin |

### Supporting

| Function | Source | Purpose | When to Use |
|----------|--------|---------|-------------|
| `is_user_logged_in()` | WordPress core | Guard preview branch to logged-in editors | Preview detection |
| `get_query_var('cws_job_id')` | WordPress core | Confirm no real job ID in preview context | Preview detection guard |
| `reset()` | PHP | Get first element of configured job IDs array | Preview fallback job ID |
| `date_create()` + `date_format()` | PHP | Alternative to `wp_date()` for date parsing | Use `wp_date()` instead (handles timezone) |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `wp_date()` | `date()` / `date_format(date_create())` | `wp_date()` respects WordPress site timezone setting; `date()` uses PHP server timezone which may differ; `wp_date()` is the correct choice |
| First configured job ID as preview sample | Admin-configurable sample job ID setting | Adding a setting field is deferred — no new UI in Phase 3; using first configured ID is correct for Phase 3 scope |
| SQL LIKE delete in `clear_all()` | Per-key `delete_transient()` calls | SQL approach is already implemented and covers all keys; only use `delete_transient()` approach if object cache (Redis/Memcached) is in play |

---

## Architecture Patterns

### How Phase 3 Fits the Existing Structure

```
template_redirect
  └─ CWS_Core_Etch::handle_single_job()
       ├── [EXISTING] if cws_job_id → fetch real job, set $this->current_job
       └── [NEW - Plan 03-01] elseif ?etch=magic + logged in → fetch first configured job, set $this->current_job

the_content @ priority 1 (Etch fires here)
  └─ apply_filters('etch/dynamic_data/option', $data)
       └─ CWS_Core_Etch::inject_options()
            ├── [EXISTING] if $this->current_job → inject cws_job (covers real + preview)
            └── [EXISTING] inject cws_jobs (always — no changes needed)

CWS_Core_API::format_job_data()
  └── [NEW - Plan 03-02] add open_date_formatted + update_date_formatted keys

CWS_Core_Cache::clear_all()
  └── [AUDIT - Plan 03-02] SQL LIKE '_transient_cws_core_%' already covers per-job keys
```

### Pattern 1: Builder Preview Branch in handle_single_job()

**What:** Add an `elseif` branch at the top of `handle_single_job()` that detects the Etch
builder context and populates `$this->current_job` with a real API job.

**When to use:** `?etch=magic` is present, user is logged in, and no real `cws_job_id` query var exists.

**Key constraint:** The branch must set `$this->current_job` and `$this->current_job_id` via
the same path (`get_job()` + `format_job_data()`) used for real job requests. This ensures
the preview data goes through identical formatting — including the new Phase 3 date fields.
The branch must NOT swap `$post` / `$wp_query` — that swap is only for real job URL routing.

```php
// Source: class-cws-core-etch.php — handle_single_job() — Phase 3 addition
public function handle_single_job() {
    $job_id = get_query_var( 'cws_job_id' );

    // --- NEW: Etch builder preview branch ---
    if (
        empty( $job_id ) &&
        isset( $_GET['etch'] ) &&
        'magic' === $_GET['etch'] &&
        is_user_logged_in()
    ) {
        $job_ids       = $this->plugin->get_configured_job_ids();
        $preview_job_id = ! empty( $job_ids ) ? reset( $job_ids ) : '';

        if ( empty( $preview_job_id ) ) {
            $this->plugin->log( 'Etch preview: no configured job IDs — cws_job will be empty', 'info' );
            return;
        }

        $raw_job = $this->plugin->api->get_job( $preview_job_id );
        if ( $raw_job ) {
            $this->current_job    = $this->plugin->api->format_job_data( $raw_job );
            $this->current_job_id = $preview_job_id;
            $this->plugin->log(
                sprintf( 'Etch preview: injecting job %s as sample', $preview_job_id ),
                'debug'
            );
        }
        return; // Do NOT swap $post — preview renders against the existing page.
    }
    // --- END NEW ---

    if ( empty( $job_id ) ) {
        return; // Not a job URL — do nothing.
    }
    // ... rest of existing method unchanged ...
}
```

**Note on $post swap:** The existing `$post` swap code (which redirects WordPress to render the
job template page) must NOT run for preview. In preview, the editor already has the correct page
open in the Etch builder frame. The swap is for frontend visitor routing only.

### Pattern 2: Date Fields in format_job_data()

**What:** Add `open_date_formatted` and `update_date_formatted` keys alongside the existing
raw `open_date` / `update_date` keys.

**When to use:** Always — `format_job_data()` is called for every job, whether real URL or preview.

**`wp_date()` function signature** (WordPress core, HIGH confidence):
```php
wp_date( string $format, int|null $timestamp = null, DateTimeZone|null $timezone = null ): string|false
// Returns formatted date string, or false if $timestamp is negative.
// When $timestamp is null, uses current time.
// $timezone defaults to WP site timezone when null.
```

**Important:** `wp_date()` takes a Unix timestamp, not a date string. The raw API values
(`open_date`, `update_date`) are ISO 8601 strings (e.g., `'2026-01-15T00:00:00Z'`). Must
convert via `strtotime()` first.

```php
// Source: class-cws-core-api.php — format_job_data() — Phase 3 addition
// Add alongside existing 'open_date' and 'update_date' keys:

'open_date'           => isset( $job['open_date'] ) ? sanitize_text_field( $job['open_date'] ) : '',
'open_date_formatted' => ! empty( $job['open_date'] )
    ? ( wp_date( 'F j, Y', strtotime( $job['open_date'] ) ) ?: '' )
    : '',

'update_date'           => isset( $job['update_date'] ) ? sanitize_text_field( $job['update_date'] ) : '',
'update_date_formatted' => ! empty( $job['update_date'] )
    ? ( wp_date( 'F j, Y', strtotime( $job['update_date'] ) ) ?: '' )
    : '',
```

**Edge cases handled:**
- Empty/null raw date → empty string (consistent with all other empty fields in `format_job_data()`)
- `wp_date()` returns `false` on negative timestamps → ternary `?: ''` coerces to empty string
- `strtotime()` failure on malformed date string → returns `false`, `wp_date( 'F j, Y', false )` returns `false` → coerces to `''`

### Pattern 3: Cache Clear Audit (verify-only)

**What:** Confirm the existing `clear_all()` SQL LIKE pattern covers per-job transient keys.

**Audit result (HIGH confidence — source inspection):**

The cache key for per-job data is built in `CWS_Core_API::fetch_job_data()`:
```php
$cache_key = 'job_data_' . md5( $url );  // e.g., 'job_data_a1b2c3d4...'
```

`CWS_Core_Cache::set()` prepends the class `$prefix = 'cws_core_'`:
```php
$cache_key = $this->prefix . $key;  // → 'cws_core_job_data_a1b2c3d4...'
```

WordPress stores this as:
```
option_name: _transient_cws_core_job_data_a1b2c3d4...
option_name: _transient_timeout_cws_core_job_data_a1b2c3d4...
```

The `clear_all()` LIKE patterns:
```php
'_transient_' . $this->prefix . '%'          // → '_transient_cws_core_%'
'_transient_timeout_' . $this->prefix . '%'  // → '_transient_timeout_cws_core_%'
```

Both patterns match the per-job transient rows. **No code change required in `clear_all()`.**

**The one genuine gap (Pitfall 13 from prior research):** On hosts with a persistent object
cache (Redis, Memcached), WordPress stores transients in the object cache rather than
`wp_options`. The SQL delete misses those entries. The `clear_all()` should also call
`wp_cache_flush_group( 'transient' )` or at minimum document this limitation. This is a minor
enhancement — assess during Plan 03-02 implementation.

### Anti-Patterns to Avoid

- **Swapping `$post` in the preview branch:** The `$post`/`$wp_query` swap in `handle_single_job()` is only for visitor-facing routing. In preview, Etch already has the correct page loaded in the builder iframe. Swapping `$post` in the preview branch would redirect the builder to the wrong page.

- **Adding preview detection inside `inject_options()`:** The `inject_options()` filter fires during Etch's `the_content` processing (priority 1). Accessing `$_GET` there works, but it breaks the established data flow — `inject_options()` is supposed to be a passive reader of `$this->current_job`, not an active fetcher. Fetch in `handle_single_job()`, read in `inject_options()`.

- **Using `date()` instead of `wp_date()`:** `date()` uses the PHP server timezone, which may differ from the WordPress site timezone. `wp_date()` always reads `get_option('timezone_string')` and applies it correctly.

- **Forgetting `strtotime()` before `wp_date()`:** `wp_date()` takes a Unix timestamp, not a string. Passing an ISO 8601 string directly returns `false`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Timezone-aware date formatting | Custom date string parser | `wp_date( 'F j, Y', strtotime( $raw ) )` | `wp_date()` reads WP site timezone, handles DST, handles all edge cases |
| ISO 8601 to timestamp conversion | Custom regex parser | `strtotime()` | PHP's `strtotime()` handles ISO 8601 including timezone offsets |
| Preview user detection | Custom session check | `is_user_logged_in()` | WordPress core function, already available, no custom auth logic needed |

**Key insight:** This phase has minimal new logic. The three implementation areas each have a
single correct WordPress/PHP primitive. Resist adding abstraction layers.

---

## Common Pitfalls

### Pitfall 1: Preview Branch Swaps $post (Breaks Builder)

**What goes wrong:** Copying the `$post`/`$wp_query` swap code into the preview branch causes
the Etch builder to reload against a different page, breaking the editor's frame.

**Why it happens:** The swap code looks like a "complete job initialization" pattern, so it
seems like it should run for previews too.

**How to avoid:** The preview branch ONLY sets `$this->current_job` and `$this->current_job_id`,
then returns. No `$post` manipulation.

**Warning signs:** After adding preview, the Etch builder URL redirects or shows the wrong
page content.

### Pitfall 2: wp_date() Returns False on Bad Input

**What goes wrong:** If `open_date` from the API is an unexpected format, `strtotime()` returns
`false`, then `wp_date( 'F j, Y', false )` returns `false`, and the template sees PHP `false`
instead of an empty string.

**Why it happens:** `wp_date()` returns `string|false` — `false` is a valid return value.

**How to avoid:** Use `?: ''` to coerce `false` to empty string:
```php
'open_date_formatted' => ! empty( $job['open_date'] )
    ? ( wp_date( 'F j, Y', strtotime( $job['open_date'] ) ) ?: '' )
    : '',
```

**Warning signs:** Etch template shows PHP `false` or `0` where a date should appear.

### Pitfall 3: Preview Fires on All ?etch=magic Pages, Not Just Job Template

**What goes wrong:** The preview branch fires for any page opened with `?etch=magic`, not just
the job template page. This injects `cws_job` data on listing pages and other pages when an
editor opens them in the builder.

**Why it matters:** Injecting `cws_job` on a listing page does no harm (the template doesn't
use `{options.cws_job}` there), but it wastes an API call.

**The decision (from CONTEXT.md):** No `post_id` check required — keep it simple. The extra
API call is cached, so the cost is negligible. The CONTEXT.md explicitly says "No `post_id`
check required — keeping it simple."

**Warning signs:** Extra API calls visible in debug logs when editor opens non-job pages.
Acceptable per design decision.

### Pitfall 4: Cache Clear Misses Object Cache (Redis/Memcached)

**What goes wrong:** On hosts with Redis or Memcached as the WordPress object cache backend,
transients are stored in memory, not `wp_options`. The SQL DELETE in `clear_all()` does not
touch the object cache. Editors click "Clear Cache" and see no effect.

**Why it happens:** WordPress's transient API transparently uses object cache when available.
The `clear_all()` SQL approach bypasses this layer.

**How to avoid:** After the SQL delete, also call `wp_cache_delete_group( 'transient' )` or
document the limitation. Standard Local dev environment (used for this project) does not use
a persistent object cache, so this is not a blocker for Phase 3.

**Warning signs:** On production hosts with Redis, cache clear appears to succeed (returns
count > 0) but stale data persists on the frontend.

### Pitfall 5: get_configured_job_ids() Returns Empty Array in Preview

**What goes wrong:** If the admin hasn't configured any job IDs yet, `get_configured_job_ids()`
returns an empty array. `reset()` on an empty array returns `false`. `get_job( false )` may
produce unexpected results.

**How to avoid:** Guard with `! empty( $job_ids )` before calling `reset()`. Log a notice and
return early if empty. Do NOT 404 — the editor should still be able to view the template shell.

```php
$preview_job_id = ! empty( $job_ids ) ? reset( $job_ids ) : '';
if ( empty( $preview_job_id ) ) {
    $this->plugin->log( 'Etch preview: no configured job IDs — cws_job will be empty', 'info' );
    return;
}
```

---

## Code Examples

### Full Preview Branch (Plan 03-01)

```php
// Source: includes/class-cws-core-etch.php — handle_single_job() method
// Insert BEFORE the existing: if ( empty( $job_id ) ) { return; }

$job_id = get_query_var( 'cws_job_id' );

// Etch builder preview: populate cws_job with a real sample job.
if (
    empty( $job_id ) &&
    isset( $_GET['etch'] ) &&
    'magic' === $_GET['etch'] &&
    is_user_logged_in()
) {
    $job_ids        = $this->plugin->get_configured_job_ids();
    $preview_job_id = ! empty( $job_ids ) ? reset( $job_ids ) : '';

    if ( empty( $preview_job_id ) ) {
        $this->plugin->log( 'Etch preview: no configured job IDs — cws_job will be empty', 'info' );
        return;
    }

    $raw_job = $this->plugin->api->get_job( $preview_job_id );
    if ( $raw_job ) {
        $this->current_job    = $this->plugin->api->format_job_data( $raw_job );
        $this->current_job_id = $preview_job_id;
        $this->plugin->log(
            sprintf( 'Etch preview: injecting job %s as sample', $preview_job_id ),
            'debug'
        );
    }
    return; // Do NOT swap $post/$wp_query for preview.
}

if ( empty( $job_id ) ) {
    return; // Not a job URL.
}
// ... rest of existing method continues unchanged ...
```

### Date Fields Addition (Plan 03-02)

```php
// Source: includes/class-cws-core-api.php — format_job_data() method
// Add these two entries alongside existing 'open_date' and 'update_date':

'open_date'             => isset( $job['open_date'] ) ? sanitize_text_field( $job['open_date'] ) : '',
'open_date_formatted'   => ! empty( $job['open_date'] )
                               ? ( wp_date( 'F j, Y', strtotime( $job['open_date'] ) ) ?: '' )
                               : '',
'update_date'           => isset( $job['update_date'] ) ? sanitize_text_field( $job['update_date'] ) : '',
'update_date_formatted' => ! empty( $job['update_date'] )
                               ? ( wp_date( 'F j, Y', strtotime( $job['update_date'] ) ) ?: '' )
                               : '',
```

### Cache Clear Audit Comment (Plan 03-02)

```php
// Source: includes/class-cws-core-cache.php — clear_all() method
// AUDIT RESULT: existing SQL covers all per-job transients. No code change needed.
//
// Per-job transient key path:
//   CWS_Core_API::fetch_job_data() builds: 'job_data_' . md5( $url )
//   CWS_Core_Cache::set() prepends $prefix ('cws_core_'): 'cws_core_job_data_{md5}'
//   WordPress stores as: '_transient_cws_core_job_data_{md5}'
//   This matches LIKE '_transient_cws_core_%' — covered.
//
// NOTE: On hosts with Redis/Memcached object cache, SQL delete bypasses object cache layer.
// Consider adding wp_cache_delete_group('transient') after SQL delete for full coverage.
```

### wp_date() Reference

```php
// WordPress core function — HIGH confidence
// Source: WordPress Developer Reference — https://developer.wordpress.org/reference/functions/wp_date/

wp_date( string $format, int|null $timestamp = null, DateTimeZone|null $timezone = null ): string|false

// $format  — PHP date() format string (e.g., 'F j, Y' → 'March 1, 2026')
// $timestamp — Unix timestamp (null = current time)
// $timezone — DateTimeZone object (null = WP site timezone from Settings > General)
// Returns: formatted string, or false on error (negative timestamp, invalid format)

// Usage for ISO 8601 API dates:
$ts = strtotime( '2026-01-15T00:00:00Z' ); // → Unix timestamp int
wp_date( 'F j, Y', $ts );                  // → 'January 15, 2026'
```

---

## State of the Art

| Old Approach | Current Approach | When Changed | Impact |
|--------------|------------------|--------------|--------|
| Virtual CPT (2,494 lines) | `etch/dynamic_data/option` filter (~150 lines) | Phase 1 | Clean separation, survives Etch upgrades |
| `?etch=magic` shows blank fields | Preview branch in `handle_single_job()` | Phase 3 (now) | Editor sees real data while designing |
| Raw ISO 8601 dates in templates | `open_date_formatted`, `update_date_formatted` | Phase 3 (now) | No string manipulation needed in Etch templates |

**No deprecated patterns in this phase** — all additions are new keys and a new branch on existing infrastructure.

---

## Open Questions

1. **Object cache on production host**
   - What we know: `clear_all()` uses SQL DELETE which does not clear object cache
   - What's unclear: Whether the production hosting environment uses Redis/Memcached
   - Recommendation: Add `wp_cache_delete_group( 'transient' )` call after SQL delete as a defensive improvement during Plan 03-02. Low risk, adds object cache coverage.

2. **Etch builder URL format — post_id query param**
   - What we know: Etch builder opens with `?etch=magic` (confirmed in Pitfall 5 prior research). CONTEXT.md confirms no `post_id` check required.
   - What's unclear: Whether other Etch builder modes (e.g., preview modal vs. full builder) use different URL parameters
   - Recommendation: The simple `isset($_GET['etch']) && 'magic' === $_GET['etch']` detection is sufficient per the locked decision. No additional investigation needed.

3. **cws_jobs population when no job IDs configured in preview**
   - What we know: `inject_options()` already handles empty job IDs by setting `cws_jobs = []`
   - What's unclear: Whether an empty `cws_jobs` in the builder causes Etch to show errors vs. silently skip the loop
   - Recommendation: Existing behavior (empty array) is fine — Etch's LoopBlock iterates 0 items silently. No change needed.

---

## Sources

### Primary (HIGH confidence)

- Direct source inspection: `includes/class-cws-core-etch.php` — current `handle_single_job()` and `inject_options()` implementation
- Direct source inspection: `includes/class-cws-core-api.php` — `format_job_data()` existing keys, `fetch_job_data()` cache key format
- Direct source inspection: `includes/class-cws-core-cache.php` — `clear_all()` SQL LIKE pattern, `set()` key prefixing
- Direct source inspection: `includes/class-cws-core.php` — `get_configured_job_ids()` implementation
- Prior research: `.planning/research/ARCHITECTURE.md` — builder preview flow diagram, hook timing contract
- Prior research: `.planning/research/PITFALLS.md` — Pitfall 5 (preview blank fields), Pitfall 13 (object cache gap)
- Prior research: `.planning/research/STACK.md` — `is_etch_preview()` pattern, `etch/dynamic_data/option` confirmation

### Secondary (MEDIUM confidence)

- WordPress Developer Reference: `wp_date()` function — timezone-aware date formatting
- WordPress Developer Reference: `is_user_logged_in()` — user authentication check

### Tertiary (LOW confidence)

- None — all claims in this research are verifiable via source inspection or WordPress core documentation.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all functions verified in source or WordPress core
- Architecture: HIGH — traced through actual class files; insertion points identified exactly
- Pitfalls: HIGH — derived from direct source inspection and prior project research
- Cache audit: HIGH — traced complete key construction path from API through cache layer to wp_options

**Research date:** 2026-03-01
**Valid until:** 2026-04-01 (stable stack — WordPress core APIs, existing plugin classes)
