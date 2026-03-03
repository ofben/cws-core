# Phase 1: Core Integration - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Remove the 2,494-line virtual CPT (`class-cws-core-virtual-cpt.php`) entirely and wire the
`etch/dynamic_data/option` filter so `{options.cws_jobs}` returns all configured jobs in any
Etch template. No single-job routing. No URL dispatch. Just the filter foundation.

A site editor can create a listing page in Etch using `{#loop options.cws_jobs as job}` and
see all API job fields resolve via dot notation.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

All implementation decisions for this phase are delegated to Claude. User did not specify
preferences for any area — proceed with best-practice defaults:

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

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CWS_Core_API::get_jobs( array $job_ids )` — fetches and formats multiple jobs; cache-backed via `CWS_Core_Cache`; returns array of formatted job arrays or `false` on failure
- `CWS_Core_API::format_job_data( array $job )` — normalizes raw API fields to plugin-standard shape; all fields documented in `INTEGRATIONS.md`
- `CWS_Core_Cache` — transient wrapper with `get()`, `set()`, `delete()`, `clear_all()`; already handles 1-hour TTL
- `$this->plugin->log( $message, $level )` — debug-gated logger; use throughout new class

### Established Patterns
- Single class per file; `namespace CWS_Core;` at top; `ABSPATH` guard; PHPDoc on class and public methods
- Hook registration in `init()` method called from main coordinator
- Constructor takes `CWS_Core $plugin` as dependency; no static singletons in feature classes
- Options accessed via `$this->plugin->get_option( 'key' )` (adds `cws_core_` prefix automatically)
- `class-cws-core.php` instantiates all feature classes and calls their `init()` methods

### Integration Points
- `cws-core.php` — `require_once` list; add new class here, remove virtual CPT include
- `class-cws-core.php` — `init()` method; swap `$this->virtual_cpt = new CWS_Core_Virtual_CPT()` for `$this->etch = new CWS_Core_Etch( $this )`
- `etch/dynamic_data/option` filter — single argument (array `$data`), must return array; fires inside Etch's `DynamicData` trait during block preprocessing
- Job IDs from `$this->plugin->get_option( 'job_ids' )` — comma-separated string, parse with `explode()`

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches. Research ARCHITECTURE.md has a confirmed
implementation skeleton for `class-cws-core-etch.php` that should be used as the basis.

</specifics>

<deferred>
## Deferred Ideas

None — user did not raise any out-of-scope ideas. Discussion stayed within phase scope.

</deferred>

---

*Phase: 01-core-integration*
*Context gathered: 2026-03-01*
