# Roadmap: CWS Core — Etch Job Integration

## Milestones

- ✅ **v1.0 Dynamic Data Rebuild** — Phases 1–4 (shipped 2026-03-03)
- 🚧 **v1.1 Admin Tooling & Dynamic Groupings** — Phases 5–8 (in progress)

## Phases

<details>
<summary>✅ v1.0 Dynamic Data Rebuild (Phases 1–4) — SHIPPED 2026-03-03</summary>

- [x] Phase 1: Core Integration (2/2 plans) — completed 2026-03-02
- [x] Phase 2: Single Job Routing (3/3 plans) — completed 2026-03-01
- [x] Phase 3: Preview and Polish (2/2 plans) — completed 2026-03-02
- [x] Phase 4: Integration Cleanup (1/1 plan) — completed 2026-03-02

Full details: [milestones/v1.0-ROADMAP.md](milestones/v1.0-ROADMAP.md)

</details>

### 🚧 v1.1 Admin Tooling & Dynamic Groupings (In Progress)

**Milestone Goal:** Give site editors visibility and control over API configuration — cache status, query params, field-based job groupings, and a configurable builder preview fallback.

- [ ] **Phase 5: Cache Status & Controls** - Admin can see cache health and clear it from the settings page
- [ ] **Phase 6: Query Parameters** - Admin can define key/value pairs appended to every API request
- [ ] **Phase 7: Field Groupings** - Admin can configure field-based groupings that auto-expose as Etch template variables
- [ ] **Phase 8: Preview Fallback** - Admin can set a specific job ID as the Etch builder preview job

## Phase Details

### Phase 5: Cache Status & Controls
**Goal**: Admin has full visibility into cache health and can clear the cache from the settings page
**Depends on**: Phase 4 (v1.0 complete — existing cache infrastructure in class-cws-core-api.php)
**Requirements**: CACHE-01, CACHE-02, CACHE-03, CACHE-04
**Success Criteria** (what must be TRUE):
  1. Admin can see the exact timestamp of when the jobs cache was last refreshed on the settings page
  2. Admin can see whether the last API fetch succeeded or failed, with the HTTP status code displayed
  3. Admin can see how old the current cache is in human-readable form (e.g. "2 hours ago")
  4. Admin can click a button on the settings page to clear the full jobs list cache immediately
**Plans**: 2 plans
Plans:
- [x] 05-01-PLAN.md — Record fetch metadata (timestamp + HTTP code) to persistent options
- [ ] 05-02-PLAN.md — Render cache status in admin UI + JS reset on cache clear

### Phase 6: Query Parameters
**Goal**: Admin can define key/value query parameter pairs in settings that are appended to every API request
**Depends on**: Phase 5
**Requirements**: QUERY-01, QUERY-02, QUERY-03
**Success Criteria** (what must be TRUE):
  1. Admin can add a new key/value query parameter row via a repeater field in the settings page
  2. Admin can remove any individual query parameter row from the settings page
  3. All configured query parameters appear in every outgoing API request URL
**Plans**: TBD

### Phase 7: Field Groupings
**Goal**: Admin can define field-based groupings so that jobs are automatically exposed as grouped Etch template variables
**Depends on**: Phase 6
**Requirements**: GROUP-01, GROUP-02, GROUP-03
**Success Criteria** (what must be TRUE):
  1. Admin can add a field name as a grouping source (e.g. "category") via the settings page
  2. Admin can remove a configured grouping from the settings page
  3. Each configured grouping results in a usable `{options.cws_jobs_by_{field}}` variable in Etch templates, keyed by unique field values with arrays of matching jobs as values
**Plans**: TBD

### Phase 8: Preview Fallback
**Goal**: Admin can set a specific job ID as the Etch builder preview fallback, with graceful fallback to existing behavior when none is configured
**Depends on**: Phase 7
**Requirements**: PREV-01, PREV-02, PREV-03
**Success Criteria** (what must be TRUE):
  1. Admin can enter a specific job ID as the Etch builder preview fallback in the settings page
  2. When `?etch=magic` is active and no real job ID is in the URL, the plugin uses the admin-configured preview job ID
  3. When no preview job ID is configured, the plugin falls back to the first job in the jobs list (existing behavior preserved)
**Plans**: TBD

## Progress

| Phase | Milestone | Plans Complete | Status | Completed |
|-------|-----------|----------------|--------|-----------|
| 1. Core Integration | v1.0 | 2/2 | Complete | 2026-03-02 |
| 2. Single Job Routing | v1.0 | 3/3 | Complete | 2026-03-01 |
| 3. Preview and Polish | v1.0 | 2/2 | Complete | 2026-03-02 |
| 4. Integration Cleanup | v1.0 | 1/1 | Complete | 2026-03-02 |
| 5. Cache Status & Controls | v1.1 | 1/2 | In progress | - |
| 6. Query Parameters | v1.1 | 0/TBD | Not started | - |
| 7. Field Groupings | v1.1 | 0/TBD | Not started | - |
| 8. Preview Fallback | v1.1 | 0/TBD | Not started | - |
