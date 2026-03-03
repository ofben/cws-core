---
phase: 03-preview-and-polish
verified: 2026-03-01T00:00:00Z
status: passed
score: 6/6 must-haves verified
re_verification: false
gaps: []
human_verification:
  - test: "Open the job template page in the Etch builder (?etch=magic) while logged in"
    expected: "{options.cws_job.title}, {options.cws_job.open_date_formatted} and all other cws_job fields render with real API data — not blank"
    why_human: "Requires a live WordPress environment, logged-in session, and Etch builder running to confirm the filter fires correctly at the right hook timing"
  - test: "Open a non-job page in the Etch builder (?etch=magic)"
    expected: "No PHP errors; cws_job is absent from the data context; page renders normally"
    why_human: "Requires live environment to confirm absence of cws_job does not cause template errors in the builder frame"
  - test: "Click 'Clear Cache' in admin settings, then visit a job page"
    expected: "Job data is freshly fetched from the API (not from cache); page renders correctly"
    why_human: "Requires live WordPress admin + browser to verify AJAX handler fires, cache is cleared, and fresh data loads"
---

# Phase 3: Preview and Polish Verification Report

**Phase Goal:** A site editor can open the job template page in the Etch builder (?etch=magic) and see real job data (not blank fields). Date fields are pre-formatted. Admin cache clear reliably clears all job transients.
**Verified:** 2026-03-01T00:00:00Z
**Status:** passed
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | Opening the job template page with ?etch=magic while logged in populates {options.cws_job.*} with real job data | VERIFIED | Preview branch in `handle_single_job()` (lines 108-132) detects all four conditions; sets `$this->current_job` via `get_job()` + `format_job_data()`; `inject_options()` reads it at line 67 |
| 2 | Opening any non-job page with ?etch=magic does not cause errors; cws_job is absent | VERIFIED | `empty($job_id)` is the first condition — branch only fires if no `cws_job_id` query var is active; `inject_options()` guard at line 67 (`null !== $this->current_job`) prevents injection when unset |
| 3 | Existing /job/{id}/ routing is unaffected — $post/$wp_query swap still runs for real visitor requests | VERIFIED | Preview branch has `return;` at line 131 before any swap code; real-job path at lines 139-183 is structurally unchanged; the `if ( empty( $job_id ) )` guard at line 134 is still present immediately after the preview block |
| 4 | If no job IDs configured, preview logs a notice and returns without error or 404 | VERIFIED | Lines 117-120: `if ( empty( $preview_job_id ) )` logs `'Etch preview: no configured job IDs — cws_job will be empty'` at `info` level and `return`s cleanly — no 404, no exception |
| 5 | open_date_formatted and update_date_formatted render human-readable date strings in Etch templates | VERIFIED | `format_job_data()` lines 325-331: both keys use `wp_date( 'F j, Y', strtotime( $job[...] ) ) ?: ''`; empty/null/malformed inputs produce `''` not false; raw keys `open_date` and `update_date` unchanged |
| 6 | Admin Clear Cache reliably clears all job transients including per-job keys | VERIFIED | `clear_all()` SQL DELETE at lines 156-162 uses `LIKE '_transient_cws_core_%'` covering per-job keys; `wp_cache_flush_group( 'transient' )` at lines 166-168 adds Redis/Memcached coverage, guarded by `function_exists()` |

**Score:** 6/6 truths verified

---

## Required Artifacts

### Plan 03-01 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-cws-core-etch.php` | handle_single_job() with Etch builder preview branch | VERIFIED | File exists, 186 lines, passes `php -l`; preview branch present at lines 107-132; contains `?etch=magic` detection |

### Plan 03-02 Artifacts

| Artifact | Expected | Status | Details |
|----------|----------|--------|---------|
| `includes/class-cws-core-api.php` | format_job_data() with open_date_formatted and update_date_formatted keys | VERIFIED | File exists, passes `php -l`; both keys present at lines 325-331 using `wp_date()` pattern |
| `includes/class-cws-core-cache.php` | clear_all() with per-job transient audit comment + object cache flush | VERIFIED | File exists, passes `php -l`; audit comment at lines 145-153; `wp_cache_flush_group` call at lines 166-168 |

---

## Key Link Verification

| From | To | Via | Status | Details |
|------|----|-----|--------|---------|
| handle_single_job() preview branch | $this->current_job | get_job() + format_job_data() — same path as real job requests | VERIFIED | Lines 122-125: `$raw_job = $this->plugin->api->get_job( $preview_job_id )` then `$this->current_job = $this->plugin->api->format_job_data( $raw_job )` |
| $this->current_job (set in preview branch) | inject_options() — etch/dynamic_data/option filter | null !== $this->current_job check at line 67 | VERIFIED | `inject_options()` line 67: `if ( null !== $this->current_job )` — existing code path, no changes needed; filter registered at line 53 |
| format_job_data() — open_date_formatted key | Etch template — {options.cws_job.open_date_formatted} | inject_options() -> $options['cws_job'] -> Etch dynamic data filter | VERIFIED | `open_date_formatted` is returned by `format_job_data()` (line 325); `inject_options()` assigns entire formatted array to `$options['cws_job']` at line 68 |
| clear_all() SQL LIKE pattern | wp_options — _transient_cws_core_job_data_{md5} | LIKE '_transient_cws_core_%' matches all per-job transient rows | VERIFIED | SQL at line 158: `LIKE '_transient_cws_core_%'` — prefix is `cws_core_` (line 27); per-job key path confirmed in audit comment lines 145-149 |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status |
|-------------|-------------|-------------|--------|
| PREVIEW-01 | 03-01-PLAN.md | Etch builder preview: ?etch=magic detection, inject first configured job as sample | SATISFIED — all four conditions present, populates current_job, returns without $post swap |
| POLISH-01 | 03-02-PLAN.md | Pre-formatted date fields (open_date_formatted, update_date_formatted) in format_job_data() | SATISFIED — both keys verified with wp_date() + false-coercion guard |
| POLISH-02 | 03-02-PLAN.md | Admin cache clear covers per-job transient keys; object cache flush for Redis/Memcached | SATISFIED — SQL pattern coverage documented, wp_cache_flush_group added |

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `includes/class-cws-core-cache.php` | 152 | Comment says "SQL delete above misses those entries" but the audit comment is placed BEFORE the SQL delete (SQL is at line 156, below the comment) | Info | Comment wording is slightly inaccurate ("above" should be "below") — no functional impact, purely a comment clarity issue |

No stub patterns, empty implementations, or blocker anti-patterns found in any of the three modified files. The `placeholder` grep hit in `class-cws-core-admin.php` is an HTML `placeholder` attribute on an input field — expected and unrelated to Phase 3.

---

## Detailed Artifact Analysis

### includes/class-cws-core-etch.php — Preview Branch

**Level 1 (Exists):** File present, 186 lines.
**Level 2 (Substantive):** Preview branch is 27 lines of real logic (lines 107-132). Contains all four required conditions, no stub patterns.
**Level 3 (Wired):**
- `$this->current_job` set in preview branch (line 124) is read by `inject_options()` (line 68) — wired
- `inject_options()` is registered on `etch/dynamic_data/option` filter (line 53) — wired
- Preview branch ends with `return;` (line 131), correctly skipping `$post`/`$wp_query` swap — correct

**Four-condition check confirmed (lines 108-112):**
- `empty( $job_id )` — no real job URL active
- `isset( $_GET['etch'] )` — param present
- `'magic' === $_GET['etch']` — strict equality (not `==`)
- `is_user_logged_in()` — logged-in guard

**Existing `if ( empty( $job_id ) ) { return; }` guard confirmed at line 134** — structurally unchanged.

### includes/class-cws-core-api.php — Date Fields

**Level 1 (Exists):** File present, 340 lines.
**Level 2 (Substantive):** Both date keys (lines 325-331) use `wp_date( 'F j, Y', strtotime( ... ) ) ?: ''` — not `date()`, not `date_format()`. False-coercion guard (`?: ''`) is present. Raw `open_date` and `update_date` keys unchanged at lines 324 and 328.
**Level 3 (Wired):** `format_job_data()` is called in `class-cws-core-etch.php` at lines 124 and 153; its return value is assigned to `$this->current_job` which flows into `inject_options()` — fully wired.

**Empty/malformed date handling:**
- Empty raw date: `! empty( $job['open_date'] )` check returns `''` directly — correct
- Malformed date: `strtotime()` returns false for bad input; `wp_date( 'F j, Y', false )` returns false; `?: ''` coerces to `''` — correct

### includes/class-cws-core-cache.php — Cache Hardening

**Level 1 (Exists):** File present, 331 lines.
**Level 2 (Substantive):** Audit comment block at lines 145-153 traces the full key derivation path. `wp_cache_flush_group( 'transient' )` at line 167 is guarded by `function_exists()` at line 166.
**Level 3 (Wired):** `clear_all()` is called by `clear_cache_ajax()` at line 275 which is registered as an AJAX handler at line 50 — wired to the admin Clear Cache button.

**SQL LIKE pattern correctness confirmed:**
- `$prefix` = `'cws_core_'` (line 27)
- Per-job key: `'job_data_' . md5( $url )` → prefixed to `'cws_core_job_data_{md5}'`
- Stored in wp_options as `'_transient_cws_core_job_data_{md5}'`
- SQL `LIKE '_transient_cws_core_%'` matches — coverage is correct

---

## Human Verification Required

### 1. Etch Builder Preview — Real Data Visible

**Test:** Log in as an administrator. Navigate to the configured job template page and append `?etch=magic` to the URL (e.g., `https://site.local/job-template/?etch=magic`). Open the Etch builder.
**Expected:** Template fields using `{options.cws_job.title}`, `{options.cws_job.open_date_formatted}`, `{options.cws_job.description}`, etc. show populated values from the first configured job ID — not blank placeholders.
**Why human:** Requires a live WP environment with at least one configured job ID, a valid API endpoint, and the Etch builder running. Hook timing (`template_redirect` vs. `the_content` priority) can only be confirmed at runtime.

### 2. Non-Job Page Preview — No Errors

**Test:** Navigate to any regular WordPress page (not the job template page) and append `?etch=magic`. Open the Etch builder.
**Expected:** The page loads normally in the builder. No PHP notices or warnings appear (check with WP_DEBUG enabled). `cws_job` key is absent from the data context — templates that reference `{options.cws_job.*}` either show empty or use fallback values.
**Why human:** Absence of PHP errors requires a live environment with WP_DEBUG enabled.

### 3. Admin Cache Clear — Full Coverage

**Test:** Visit a job page to populate the transient cache. Open admin settings and click "Clear Cache". Return to the job page.
**Expected:** Job data is freshly fetched from the API (observable via debug log or brief load delay). Admin button shows a success message with a count of cleared entries.
**Why human:** Requires live admin session, browser interaction, and debug logging or network inspection to confirm cache was actually cleared and fresh data was fetched.

---

## Gaps Summary

No blocking gaps. All six observable truths are verified against the actual codebase. The three modified files pass PHP syntax checks and contain substantive, wired implementations with no stub patterns.

One minor comment wording inaccuracy noted in `class-cws-core-cache.php` line 152: "SQL delete above" references a query that appears below the comment in the file. This is informational only — no functional impact.

Three items require human verification (live environment testing) as they involve runtime behavior: Etch builder frame rendering, WP_DEBUG error visibility, and AJAX cache clear interaction.

---

_Verified: 2026-03-01T00:00:00Z_
_Verifier: Claude (gsd-verifier)_
