---
phase: 02-single-job-routing
verified: 2026-03-01T05:00:00Z
status: human_needed
score: 7/8 must-haves verified
re_verification:
  previous_status: gaps_found
  previous_score: 6/8
  gaps_closed:
    - "cws_job injection now runs before the empty($job_ids) early return — {options.cws_job.*} is populated on single-job pages regardless of whether listing Job IDs are configured"
  gaps_remaining: []
  regressions: []
human_verification:
  - test: "Visit /job/{valid-id}/ on the live site"
    expected: "Configured template page renders with {options.cws_job.title} and other fields populated from the API"
    why_human: "Requires live WP environment with valid API credentials, a configured Job Template Page option, and a published Etch template page"
  - test: "Visit /job/00000/ on the live site"
    expected: "WordPress 404 page is shown — not a blank or broken Etch template"
    why_human: "404 path involves $wp_query->set_404() + include(get_404_template()) + exit — requires browser request to confirm HTTP 404 status"
  - test: "Confirm {options.cws_job} is absent on non-job pages (e.g. homepage)"
    expected: "The homepage renders correctly; no cws_job key appears in Etch context"
    why_human: "Null-guard logic is verifiable in code but the Etch filter chain output requires runtime observation"
  - test: "Change Job URL Slug in admin settings and save"
    expected: "Rewrite rules flush automatically — /job/{id}/ URLs still work without manual flush"
    why_human: "flush_rewrite_rules() side-effect requires actual WordPress option save to confirm hook fires"
  - test: "Admin settings page: leave Job Template Page at -- Select a page -- and save"
    expected: "Red warning text appears below the dropdown"
    why_human: "Inline HTML rendering requires browser inspection"
---

# Phase 2: Single Job Routing — Verification Report

**Phase Goal:** Visiting `/job/{id}/` renders the configured template page with `{options.cws_job.*}` fully populated. The Etch template can display title, description, salary, location, and all other job fields for the specific job in the URL.
**Verified:** 2026-03-01
**Status:** human_needed (all code gaps closed; 5 items require live environment verification)
**Re-verification:** Yes — after gap closure (Plan 02-03)

---

## Re-Verification Summary

**Previous status:** gaps_found (6/8 score)
**Current status:** human_needed (7/8 score)

### Gap Closed

**Previous gap:** `inject_options()` had an early return at line 69 (old numbering) when `empty($job_ids)` was true, which bypassed the `cws_job` injection block. Single-job pages received no `{options.cws_job}` data when the admin had not configured any listing Job IDs.

**Fix applied (Plan 02-03, commit `ba93a06`):** The `null !== $this->current_job` block (lines 67-73) was moved to the top of `inject_options()`, before `get_configured_job_ids()` is even called. The `cws_job` injection is now fully independent of the listing state. When `empty($job_ids)` triggers the early return at line 80, `$options` already contains `cws_job` and it is returned with it.

**Verified in code:** Lines 67-80 of `includes/class-cws-core-etch.php` confirm the fix:
1. Line 67: `if ( null !== $this->current_job )` — cws_job injected first
2. Line 75: `$job_ids = $this->plugin->get_configured_job_ids();` — listing concern starts here
3. Line 77: `if ( empty( $job_ids ) )` — early return cannot bypass cws_job any more

### No Regressions Detected

All 5 items that passed in the initial verification still pass:
- `cws_jobs` listing injection: unchanged — `empty($job_ids)` guard and `get_jobs()` call are intact
- `cws_job` absent on non-job pages: `$this->current_job` initialises as `null`; the null guard still prevents injection on non-job pages
- `render_job_template_page_field()`: wired and unchanged
- `flush_rules_on_slug_change()`: wired and unchanged
- `handle_single_job()`: 54-line routing method unchanged

---

## Goal Achievement

### Observable Truths

| #  | Truth | Status | Evidence |
|----|-------|--------|----------|
| 1  | Visiting /job/22026695/ renders the configured template page (not a 404) | ? HUMAN | `handle_single_job()` fully implemented: detects `cws_job_id`, fetches via API, swaps `$post`/`$wp_query` to configured page. Requires live environment to confirm. |
| 2  | `{options.cws_job.title}` and `{options.cws_job.description}` resolve with real data — even when no listing Job IDs are configured | VERIFIED | Gap closed by Plan 02-03. `inject_options()` now injects `cws_job` before the `empty($job_ids)` early return. `format_job_data()` supplies `title`, `description`, and all other normalised fields. No edge case remains. |
| 3  | Visiting /job/00000/ (non-existent job) returns a proper 404 | ? HUMAN | Code implements the standard WP 404 pattern: `$wp_query->set_404()` + `status_header(404)` + `include(get_404_template())` + `exit`. Requires browser verification. |
| 4  | `{options.cws_jobs}` (plural, listing) continues to work on all other pages | VERIFIED | `inject_options()` still injects `$options['cws_jobs']` on every filter call; `cws_job` block only fires when `$this->current_job !== null`. No regression. |
| 5  | `{options.cws_job}` is absent from Etch context on non-job pages | VERIFIED | `$this->current_job` initialises as `null` (line 38). The null-guard at line 67 means `cws_job` key is never added on non-job pages. |
| 6  | Admin settings page shows a "Job Template Page" dropdown populated with published WP pages | VERIFIED | `render_job_template_page_field()` exists (line 471), calls `get_pages(['post_status'=>'publish'])`, renders `<select>` with page options. |
| 7  | When no page is selected (value=0), the field shows a red warning | VERIFIED | Lines 492-495: `if ( 0 === $value )` outputs `<p style="color:#d63638;">` warning. Inline, settings-page-only. |
| 8  | Changing the Job URL Slug automatically flushes rewrite rules | VERIFIED | `update_option_cws_core_job_slug` action registered at line 62 (priority 10, 3 args). `flush_rules_on_slug_change()` at line 694 with `$old_value !== $new_value` guard. |

**Score:** 7/8 truths verified (6 VERIFIED, 0 FAILED, 2 HUMAN-only)

---

## Required Artifacts

### Plan 02-01 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-cws-core-etch.php` | `handle_single_job()`, `$current_job` property, updated `inject_options()` | VERIFIED | File exists, 159 lines, substantive. `private $current_job = null` at line 38. `handle_single_job()` at line 104 (54 lines of routing logic). Gap-fixed `inject_options()` at lines 63-94. php -l: no syntax errors. |
| `includes/class-cws-core.php` | Removed `template_redirect` stub, clean `register_hooks()` | VERIFIED | `register_hooks()` at lines 162-171: only `init`, `cws_core_activate`, `query_vars` hooks — no `template_redirect` registration. `handle_job_request()` at lines 211-213: deprecation docblock + empty body. php -l: no syntax errors. |

### Plan 02-02 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-cws-core-admin.php` | `cws_core_job_template_page_id` setting, `render_job_template_page_field()`, `flush_rules_on_slug_change()` | VERIFIED | `register_setting('cws_core_settings', 'cws_core_job_template_page_id', ...)` at lines 147-155. `add_settings_field('cws_core_job_template_page_id', ...)` at lines 219-225. `render_job_template_page_field()` at line 471. `flush_rules_on_slug_change()` at line 694. php -l: no syntax errors. |

### Plan 02-03 Artifacts (Gap Closure)

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-cws-core-etch.php` | `cws_job` injection block moved before `empty($job_ids)` early return | VERIFIED | Lines 67-73: null-guard and injection. Line 77: `empty($job_ids)` early return comes after. The `return $options` at line 80 carries `cws_job` when it was set. |

---

## Key Link Verification

### Plan 02-01 Key Links

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `CWS_Core_Etch::init()` | `handle_single_job()` | `add_action('template_redirect', ...)` | WIRED | Line 54: `add_action( 'template_redirect', array( $this, 'handle_single_job' ) );` |
| `handle_single_job()` | `$this->current_job` | `format_job_data()` on `get_job()` result | WIRED | Lines 112, 126: `$raw_job = $this->plugin->api->get_job( $job_id )` then `$this->current_job = $this->plugin->api->format_job_data( $raw_job )` |
| `inject_options()` | `$options['cws_job']` | null check on `$this->current_job` | WIRED | Lines 67-73: null guard is first thing executed in `inject_options()`. Gap closed — no longer bypassed by the listing early return. |

### Plan 02-02 Key Links

| From | To | Via | Status | Details |
|------|-----|-----|--------|---------|
| `register_settings()` | `render_job_template_page_field()` | `add_settings_field()` in `cws_core_url_section` | WIRED | Lines 219-225: `add_settings_field('cws_core_job_template_page_id', 'Job Template Page', [$this, 'render_job_template_page_field'], 'cws-core-settings', 'cws_core_url_section')` |
| `register_hooks()` | `flush_rules_on_slug_change()` | `add_action('update_option_cws_core_job_slug', ...)` | WIRED | Line 62: `add_action( 'update_option_cws_core_job_slug', array( $this, 'flush_rules_on_slug_change' ), 10, 3 )` |

---

## Requirements Coverage

Requirements come from PLAN frontmatter (no REQUIREMENTS.md exists in this project).

### Plan 02-01 Requirements

| Requirement | Status | Evidence |
|-------------|--------|---------|
| "Inject single job as {options.cws_job} when on a /job/{id}/ URL" | VERIFIED | Gap closed. Injection block is now first in `inject_options()` — independent of listing state. |
| "Load a real WordPress page as the job detail template" | VERIFIED | `handle_single_job()` reads `cws_core_job_template_page_id` option, fetches the WP page, swaps `$post` and `$wp_query` to that page object. |
| "Graceful 404 for unknown jobs" | VERIFIED | API returns false/empty → `$wp_query->set_404()` + `status_header(404)` + `include(get_404_template())` + `exit`. |

### Plan 02-02 Requirements

| Requirement | Status | Evidence |
|-------------|--------|---------|
| "Admin must be able to designate which WP page serves as the job template" | VERIFIED | `cws_core_job_template_page_id` registered in Settings API; dropdown field renders in URL Configuration section. |
| "If the job template WP page is missing, the admin settings page shows a visible warning" | VERIFIED | Inline red warning fires for value=0 or when saved page is not published. |
| "Changing the job slug setting automatically flushes rewrite rules" | VERIFIED | `update_option_cws_core_job_slug` hook + `flush_rules_on_slug_change()` with old/new guard. |

### Plan 02-03 Requirements

| Requirement | Status | Evidence |
|-------------|--------|---------|
| "Inject single job as {options.cws_job} when on a /job/{id}/ URL" | VERIFIED | Re-listed requirement now fully satisfied. `cws_job` injection is independent of listing state. |

### ROADMAP.md Success Criteria Cross-Reference

| # | Success Criterion | Status | Notes |
|---|-------------------|--------|-------|
| 1 | Visiting `/job/22026695/` renders the configured job template page | HUMAN | Code structure is complete; requires live browser verification. |
| 2 | `{options.cws_job.title}`, `{options.cws_job.description}`, `{options.cws_job.url}` resolve with data | VERIFIED | Fields exist in `format_job_data()` output. Gap closed — injection is no longer blocked by listing state. |
| 3 | Visiting `/job/00000/` results in a 404, not a broken template | HUMAN | 404 code pattern is correct; requires live browser verification. |
| 4 | If job template WP page is missing, admin settings page shows a visible warning | VERIFIED | Inline warning present in `render_job_template_page_field()`. |
| 5 | Changing the job slug setting automatically flushes rewrite rules | VERIFIED | `update_option_cws_core_job_slug` hook wired correctly. |

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `includes/class-cws-core-admin.php` | 456 | `placeholder="22026695, ..."` in textarea | Info | HTML placeholder attribute for the Job IDs field — correct usage, not a code stub. No impact. |
| `includes/class-cws-core.php` | 211-238 | `load_job_template()` references deleted `templates/job.php` | Warning | Carried over from Phase 1. Method is NOT hooked — `register_hooks()` contains no `template_include` or similar. Zero runtime impact. |

No new anti-patterns introduced by Plan 02-03.

---

## Human Verification Required

### 1. Valid Job ID Renders Template Page

**Test:** With a published WP page set as Job Template Page in admin settings, visit `https://etch2job.local/job/22026695/` (or any valid API job ID).
**Expected:** The configured template page content renders (not a 404 or WordPress default). Etch field `{options.cws_job.title}` shows the actual job title from the API.
**Why human:** Requires live WP environment with API credentials configured and a published Etch template page with at least one `{options.cws_job.*}` field.

### 2. Invalid Job ID Returns 404

**Test:** Visit `https://etch2job.local/job/00000/` (a non-existent job ID).
**Expected:** WordPress 404 page renders. HTTP response code is 404 (verifiable in browser DevTools Network tab).
**Why human:** The 404 logic ends with `exit` after `include(get_404_template())` — must be verified via actual HTTP request.

### 3. cws_job Absent on Non-Job Pages

**Test:** Visit the homepage or a listing page. In Etch builder, inspect the available options context.
**Expected:** No `cws_job` key appears — only `cws_jobs` (plural) is present.
**Why human:** Requires Etch builder observation or `var_dump` of the filter output in a debug context.

### 4. Automatic Rewrite Flush on Slug Change

**Test:** In admin settings, change "Job URL Slug" from `job` to `jobs` and save. Then visit `https://etch2job.local/jobs/22026695/` without manually flushing.
**Expected:** URL resolves correctly. Job template page renders.
**Why human:** The `flush_rewrite_rules()` side-effect fires server-side on option save — a browser URL test confirms it worked without a manual flush step.

### 5. Admin Warning for Missing Template Page

**Test:** In admin settings, ensure "Job Template Page" is set to "— Select a page —" (value 0) and save.
**Expected:** A red warning paragraph appears directly below the dropdown: "Warning: No job template page selected. Single job URLs (/job/{id}/) will not load correctly."
**Why human:** Inline HTML rendering requires browser inspection of the admin settings page.

---

## Gaps Summary

No code gaps remain. The single blocker identified in the initial verification was closed by Plan 02-03:

- **Gap (closed):** `inject_options()` early return for empty listing job IDs bypassed `cws_job` injection
- **Fix:** `cws_job` null-check block moved to the top of `inject_options()`, before `get_configured_job_ids()` is called
- **Verification:** Code at lines 67-80 of `class-cws-core-etch.php` confirms correct ordering

The 2 remaining HUMAN items (truths #1 and #3) were present in the initial verification and are unchanged — they cannot be verified programmatically and require a live WordPress environment.

---

_Verified: 2026-03-01_
_Verifier: Claude (gsd-verifier)_
_Re-verification after: Plan 02-03 gap closure_
