# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — Dynamic Data Rebuild

**Shipped:** 2026-03-03
**Phases:** 4 | **Plans:** 8 | **Sessions:** ~5

### What Was Built
- Deleted 2,494-line virtual CPT class and 17 associated files; replaced with clean `class-cws-core-etch.php`
- Full Etch integration: `{options.cws_jobs}` for listing, `{options.cws_job.*}` for single job pages, builder preview with `?etch=magic`
- Consistent data schema via `format_job_data()` — normalized field names, formatted date fields, available on both listing items and single-job items
- Admin UX: job template page selector, inline health check warning, auto rewrite flush

### What Worked
- **Phased approach with tight success criteria** — each phase had clear verifiable outcomes; no scope creep
- **Minimal footprint pattern** — reusing existing API/cache classes meant zero rework; new code only where needed
- **Audit step caught real gaps** — `v1.0-MILESTONE-AUDIT.md` identified the `the_content` injection and schema inconsistency issues before shipping; Phase 4 closed them cleanly
- **Phase summaries made milestone archival straightforward** — all decisions and deliverables documented per plan

### What Was Inefficient
- **Three-phase plan underestimated scope** — initial roadmap had 3 phases; audit found gaps requiring Phase 4; better upfront analysis of legacy code might have caught the `CWS_Core_Public` dependency earlier
- **Human verify checkpoint** — Task 3 in Phase 4 required a manual frontend check; the plan could have made this more explicit earlier or structured the checkpoint differently

### Patterns Established
- `format_job_data()` is the single normalization path for all job data exposed to Etch — any new fields go here first
- `$current_job` property on `CWS_Core_Etch` stores single job at `template_redirect`; filter callback reads it — this store-then-read pattern avoids timing issues
- Preview branch (`?etch=magic + is_user_logged_in()`) returns early before `$post`/`$wp_query` swap — builder iframe already has the correct page; don't swap

### Key Lessons
1. **Audit before archiving** — the milestone audit step is worth running; it caught two real bugs (duplicate content injection, schema inconsistency) that would have shipped
2. **Legacy code needs full inventory** — virtual CPT was removed in Phase 1 but `CWS_Core_Public` (the other injection path) wasn't touched until Phase 4 audit; always grep for all injection hooks when replacing a display mechanism
3. **Schema consistency is a first-class concern** — having `format_job_data()` used in single-job but not listing created confusing template behavior; normalize at the injection layer, not the template layer

### Cost Observations
- Model mix: ~100% sonnet (balanced profile)
- Sessions: ~5 working sessions
- Notable: 8 plans averaged ~1.5 min each — tight plans with atomic commits kept velocity high

---

## Milestone: v1.1 — Admin Tooling & Dynamic Groupings

**Shipped:** 2026-03-03
**Phases:** 4 (5–8) | **Plans:** 5 | **Sessions:** 1 (same day)

### What Was Built
- Cache status tracking: `fetch_job_data()` writes timestamp + HTTP code to wp_options after every live API attempt; admin settings shows 4 states
- Live cache management: AJAX clear resets admin status display without page reload
- Custom query parameters: admin key/value repeater; `build_api_url()` appends all params to every API request URL
- Dynamic field groupings: replaces two hardcoded Etch variables with a configurable system — any API field becomes `{options.cws_jobs_by_{field}}`
- Configurable preview job: `resolve_preview_job()` private method covers both `?etch=magic` frontend path and Etch's REST API context (discovered mid-phase)

### What Worked
- **Repeater pattern reuse** — Phase 6 (query params) established the key/value repeater pattern; Phase 7 (field groupings) used an even simpler flat-array variant of the same pattern; very little rework
- **Private method extraction** — `resolve_preview_job()` was extracted during Phase 8 when the REST_REQUEST context was discovered; the refactor was cheap because the logic was already in a clean `handle_single_job()` block
- **Audit after all phases** — running audit immediately after Phase 8 caught the REQUIREMENTS.md stale checkboxes and the JSON parse failure metadata gap; both were low effort to understand and document

### What Was Inefficient
- **Phase 8 REST_REQUEST discovery** — the Etch builder's REST API context was not anticipated in the plan; it required an extra fix commit (`fix(08-01)`) mid-phase. The CONTEXT.md file noted this risk in phase planning but wasn't surfaced to the plan author. A phase assumption check earlier would have caught it.
- **REQUIREMENTS.md not updated after Phase 8** — PREV-01/02/03 checkboxes left unchecked after Phase 8 completion; caught in audit. Small process gap but created noise in the 3-source cross-reference.

### Patterns Established
- **Repeater → option → consumer chain**: all admin settings follow the same pattern: `register_setting()` → `add_settings_field()` → render PHP function → JS add/remove handlers → consumer class reads option. Established in Phase 5, proven in 6, 7, 8.
- **`resolve_preview_job()` as the canonical preview resolution path**: any future preview-related work should go through or extend this method rather than adding new conditional blocks in `handle_single_job()` or `inject_options()`
- **REST_REQUEST guard in `inject_options()`**: the guard `defined('REST_REQUEST') && REST_REQUEST && is_user_logged_in()` is the correct pattern for any preview or builder-context behavior in `inject_options()`

### Key Lessons
1. **Note REST context in phase plans for any Etch builder-facing work** — `template_redirect` does not fire in Etch's REST API calls; always check if the feature needs a `REST_REQUEST` guard in `inject_options()`
2. **Mark requirements complete immediately after plan execution** — REQUIREMENTS.md stale checkboxes added audit noise; the phase executor should update traceability as the last step
3. **Breaking changes need release notes** — the removal of `cws_jobs_by_category`/`cws_jobs_by_city` was intentional but caught existing templates off guard; a "Migration" section in MILESTONES.md helps future reference

### Cost Observations
- Model mix: ~100% sonnet (balanced profile)
- Sessions: 1 day (all 4 phases shipped same day)
- Notable: 5 plans with tight scope — average 30–90 min each including verification and UAT

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 | 4 | 8 | Initial baseline — filter integration replaces virtual CPT |
| v1.1 | 4 | 5 | Admin tooling layer; repeater pattern established and reused across 4 phases |

### Cumulative Quality

| Milestone | Audit | Gaps Found | Gap Resolution |
|-----------|-------|------------|----------------|
| v1.0 | Yes | 3 (1 critical, 2 partial) | Phase 4 added to close all gaps |
| v1.1 | Yes | 0 critical, 2 low (tech debt) | Accepted and carried forward to v1.2 |

### Top Lessons (Verified Across Milestones)

1. Always audit before archiving — gaps are cheaper to close before a milestone is marked shipped
2. Normalize data at the injection layer, not in templates
3. Etch builder uses REST API context — any builder-facing feature needs a `REST_REQUEST` guard in `inject_options()`
