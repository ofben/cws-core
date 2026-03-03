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

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 | 4 | 8 | Initial baseline — filter integration replaces virtual CPT |

### Cumulative Quality

| Milestone | Audit | Gaps Found | Gap Resolution |
|-----------|-------|------------|----------------|
| v1.0 | Yes | 3 (1 critical, 2 partial) | Phase 4 added to close all gaps |

### Top Lessons (Verified Across Milestones)

1. Always audit before archiving — gaps are cheaper to close before a milestone is marked shipped
2. Normalize data at the injection layer, not in templates
