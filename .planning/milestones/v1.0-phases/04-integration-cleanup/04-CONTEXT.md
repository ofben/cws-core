# Phase 4: Integration Cleanup - Context

**Gathered:** 2026-03-02
**Status:** Ready for planning

<domain>
## Phase Boundary

Make the Etch filter the sole job display mechanism. Four discrete tasks: (1) remove the
`the_content` hook in `class-cws-core-public.php` that appends a raw job block after Etch
template content on `/job/{id}/` pages; (2) normalize `cws_jobs` listing items through
`format_job_data()` so listing templates have the same field schema as single-job templates;
(3) remove the dead `load_job_template()` method in `class-cws-core.php`; (4) seed
`cws_core_job_template_page_id` default in the activation hook.

No new features, no UI changes, no additional routing changes.

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

All implementation details are left to Claude's judgement — no user preferences captured.
Specifically:

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

</decisions>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches.

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets

- `format_job_data()` in `class-cws-core-api.php:305` — already used for single-job normalization;
  apply to each item in `get_jobs()` result via `array_map()` in `inject_options()`
- `get_jobs()` in `class-cws-core-api.php:289` — returns raw `queryResult` array; no changes
  needed to API class, only to the caller in `inject_options()`

### Established Patterns

- Hook removal: remove `add_filter` call from `register_hooks()` in the offending class
- Dead method removal: delete the method body and PHPDoc block entirely
- Activation defaults: `add_option( 'cws_core_{key}', $default )` pattern in `cws-core.php:79`

### Integration Points

- `class-cws-core-public.php:47` — `add_filter('the_content', array($this, 'inject_job_data'))` — remove this line
- `class-cws-core-etch.php:86` — `$options['cws_jobs'] = array_values($jobs)` — wrap with `format_job_data()`
- `class-cws-core.php:221` — `load_job_template()` method — delete entirely
- `cws-core.php:79–83` — activation defaults — add `cws_core_job_template_page_id`

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 04-integration-cleanup*
*Context gathered: 2026-03-02*
