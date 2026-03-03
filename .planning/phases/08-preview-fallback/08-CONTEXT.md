# Phase 8: Preview Fallback - Context

**Gathered:** 2026-03-03
**Status:** Ready for planning

<domain>
## Phase Boundary

Add a single admin-configurable job ID that the plugin uses as the Etch builder preview job when `?etch=magic` is active and no real job ID is in the URL. When no preview job ID is configured, fall back to the first job in the configured job list (existing behavior preserved). Admin UI and Etch integration only — no changes to live/front-end job routing.

</domain>

<decisions>
## Implementation Decisions

### Settings section placement
- Preview job ID field goes in the existing `cws_core_url_section` (URL Configuration)
- Rationale: URL section already contains `job_slug` and `job_template_page_id` — all Etch routing/builder config lives there; no new section needed

### Field input type
- Free-text `<input type="text" class="regular-text">` (matches `render_job_slug_field()` pattern)
- Accepts any job ID string, not restricted to the configured job list
- Rationale: Admin may want to preview a specific job without adding it to the listing; API call handles invalid IDs gracefully (returns false → falls back)

### Inline feedback
- When empty: show a static note "No preview job configured — falling back to first job in list when ?etch=magic is active"
- When a value is set: no warning (field is populated; API failure degrades gracefully at runtime)
- Pattern: mirrors the template page field's inline health check (inline, not via admin_notices, `<p class="description">`)

### Validation behavior
- Accept any job ID (no restriction to configured IDs)
- Sanitize with `sanitize_text_field()` on save (consistent with other string options)
- At runtime: `get_job($preview_id)` returns false for an invalid ID → existing fallback chain handles it

### Claude's Discretion
- Exact description copy for the settings field label
- Whether to show the currently-active fallback job ID in the empty-state note
- Option name: `cws_core_preview_job_id` (follows existing convention)

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `render_job_slug_field()`: exact pattern for a simple `<input type="text" class="regular-text">` settings field
- `render_job_template_page_field()`: pattern for inline health-check note below a field (not admin_notices)
- `plugin->api->get_job($id)`: already handles invalid IDs (returns false) — preview error path is covered

### Established Patterns
- Option naming: `cws_core_{thing}` — new option should be `cws_core_preview_job_id`
- `register_setting()` + `add_settings_field()` + `render_{field}()` triplet required for each new setting
- `sanitize_text_field()` for string settings (see `sanitize_job_slug()` as reference)
- Inline description text: `<p class="description">` below the field

### Integration Points
- `class-cws-core-etch.php` → `handle_single_job()` → the `?etch=magic` preview block (~lines 130–155)
- Current preview logic: `$preview_job_id = !empty($job_ids) ? reset($job_ids) : '';`
- Required change: try `get_option('cws_core_preview_job_id', '')` first, fall back to `reset($job_ids)` when empty
- No changes to `inject_options()`, `format_job_data()`, or any front-end routing

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope

</deferred>

---

*Phase: 08-preview-fallback*
*Context gathered: 2026-03-03*
