# Phase 2: Single Job Routing - Context

**Gathered:** 2026-03-01
**Status:** Ready for planning

<domain>
## Phase Boundary

Wire up single-job URL routing so that visiting `/job/{id}/` loads a real WordPress page
and injects the matching job's data as `{options.cws_job.*}` into the Etch dynamic data
context. Admin must be able to designate which WP page serves as the job template. Invalid
or API-unfetchable job IDs return a clean 404. No virtual posts. No new page templates.
No builder preview (that's Phase 3).

</domain>

<decisions>
## Implementation Decisions

### Claude's Discretion

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

</decisions>

<code_context>
## Existing Code Insights

### Reusable Assets
- `CWS_Core_API::get_job( $job_id )` — fetches a single job by ID; line 272 in class-cws-core-api.php; returns formatted job array or false on failure
- `CWS_Core_API::get_jobs()` + `format_job_data()` — already tested; same formatting shape used for `cws_job`
- `$this->current_job_id = null` — already declared on `CWS_Core_Etch` with a "populated in Phase 2" docblock; Phase 2 activates this property
- `inject_options()` in `CWS_Core_Etch` — already registered on `etch/dynamic_data/option`; Phase 2 adds `cws_job` key injection here when `$current_job` is set
- `register_settings()` / `render_settings_page()` in `class-cws-core-admin.php` — existing pattern for adding new settings fields; use `add_settings_field()` + `register_setting()`

### Established Patterns
- `template_redirect` is the standard WordPress hook for custom routing; fires before template is loaded; used similarly in virtual CPT (now removed)
- 404 pattern: `$wp_query->set_404(); status_header(404); include(get_404_template()); exit;`
- Options accessed via `$this->plugin->get_option( 'key' )` (adds `cws_core_` prefix automatically)
- New option registration: `register_setting( 'cws_core_settings', 'cws_core_{key}', array( 'sanitize_callback' => ... ) )`
- `update_option_{option_name}` action hook: fires after an option is saved; use for slug-change side effects

### Integration Points
- `cws-core.php` — `require_once` list: no new files needed (Phase 2 extends existing etch class)
- `class-cws-core.php` — `init()`: no changes needed (etch class already wired)
- `class-cws-core-etch.php` — `init()` method: add `add_action( 'template_redirect', ... )` here
- `class-cws-core-admin.php` — `register_settings()` and `render_settings_page()`: add job template page field
- Rewrite rules: already registered in `class-cws-core.php` `add_rewrite_rules()` for `/job/{id}/`; query var `cws_job_id` already registered

</code_context>

<specifics>
## Specific Ideas

No specific requirements — open to standard approaches.

</specifics>

<deferred>
## Deferred Ideas

None — user did not raise any out-of-scope ideas. Discussion stayed within phase scope.

</deferred>

---

*Phase: 02-single-job-routing*
*Context gathered: 2026-03-01*
