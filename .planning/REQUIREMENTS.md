# Requirements: CWS Core — Etch Job Integration

**Defined:** 2026-03-03
**Milestone:** v1.1 Admin Tooling & Dynamic Groupings
**Core Value:** Job data from the external API is reliably available in any Etch template via the official `etch/dynamic_data/option` filter — works across Etch upgrades, requires no workarounds.

## v1.1 Requirements

### Cache

- [ ] **CACHE-01**: Admin can see when the jobs cache was last refreshed (timestamp) on the settings page
- [ ] **CACHE-02**: Admin can see whether the last API fetch succeeded or failed, with HTTP status code
- [ ] **CACHE-03**: Admin can clear the full jobs list cache from the settings page
- [ ] **CACHE-04**: Admin can see how old the current cache is (human-readable age, e.g. "2 hours ago")

### Query Parameters

- [ ] **QUERY-01**: Admin can add query parameter key/value pairs via repeater fields in settings
- [ ] **QUERY-02**: Admin can remove individual query parameter rows
- [ ] **QUERY-03**: Plugin appends all configured query parameters to every API request

### Groupings

- [ ] **GROUP-01**: Admin can define a field name as a grouping source (e.g. "category") via the settings page
- [ ] **GROUP-02**: Admin can remove a configured grouping
- [ ] **GROUP-03**: Plugin injects grouped jobs as `{options.cws_jobs_by_{field}}` via the Etch filter — object keyed by unique field values, each value being an array of matching jobs

### Preview

- [ ] **PREV-01**: Admin can set a specific job ID as the Etch builder preview fallback in settings
- [ ] **PREV-02**: Plugin uses the configured preview job ID when `?etch=magic` is active and no real job ID is in the URL
- [ ] **PREV-03**: Falls back to first job in the jobs list when no preview job ID is configured (existing behavior preserved)

## Future Requirements

*(None identified yet — captured here as milestone progresses)*

## Out of Scope

| Feature | Reason |
|---------|--------|
| Search/filter UI for visitors | Deferred — Etch pagination hook not yet available |
| Pagination of job listings | Deferred — awaiting Etch team hook |
| REST API endpoints for job data | Deferred — not needed for current use case |
| Database-backed job sync | Out of scope — staying API-first with transient cache |
| Multiple API connections | Out of scope — single-endpoint plugin, not a general-purpose tool |
| Response preview panel in admin | Deferred — useful but lower priority than functional improvements |
| wp-config constant for credentials | Deferred — org ID is not sensitive; not needed now |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| CACHE-01 | Phase 5 | Pending |
| CACHE-02 | Phase 5 | Pending |
| CACHE-03 | Phase 5 | Pending |
| CACHE-04 | Phase 5 | Pending |
| QUERY-01 | Phase 6 | Pending |
| QUERY-02 | Phase 6 | Pending |
| QUERY-03 | Phase 6 | Pending |
| GROUP-01 | Phase 7 | Pending |
| GROUP-02 | Phase 7 | Pending |
| GROUP-03 | Phase 7 | Pending |
| PREV-01 | Phase 8 | Pending |
| PREV-02 | Phase 8 | Pending |
| PREV-03 | Phase 8 | Pending |

**Coverage:**
- v1.1 requirements: 13 total
- Mapped to phases: 13
- Unmapped: 0 ✓

---
*Requirements defined: 2026-03-03*
*Last updated: 2026-03-03 after roadmap creation (Phases 5–8)*
