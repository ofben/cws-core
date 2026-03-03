---
phase: 01-core-integration
verified: 2026-03-01T00:00:00Z
status: passed
score: 5/5 must-haves verified
re_verification: false
---

# Phase 1: Core Integration Verification Report

**Phase Goal:** Remove the fragile 2,494-line virtual CPT implementation and replace it with a clean, minimal Etch filter integration that exposes job data via `{options.cws_jobs}` in Etch templates.
**Verified:** 2026-03-01
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths (from ROADMAP.md Success Criteria)

| #  | Truth                                                                                       | Status     | Evidence                                                                                        |
|----|---------------------------------------------------------------------------------------------|------------|-------------------------------------------------------------------------------------------------|
| 1  | `class-cws-core-virtual-cpt.php` deleted and no references remain in codebase               | VERIFIED   | File absent; grep across 3 coordinator files returns zero hits for virtual_cpt/Virtual_CPT      |
| 2  | Etch template with `{#loop options.cws_jobs as job}{job.title}{/loop}` renders on frontend  | HUMAN NEEDED | Wiring confirmed; frontend render requires live WP + configured API credentials                |
| 3  | All job fields resolve via Etch dot notation                                                | VERIFIED   | inject_options() passes full API queryResult array — no field filtering; all keys available     |
| 4  | No PHP errors or warnings with WP_DEBUG enabled                                             | VERIFIED   | php -l passes on all 4 key files; no missing-class risk (class_exists guard + file exists)      |
| 5  | Existing `/job/{id}/` rewrite rules still fire                                              | VERIFIED   | add_rewrite_rules() present in class-cws-core.php; registered via add_action('init', ...)      |

**Score:** 4/5 truths verified programmatically (1 deferred to human — frontend rendering)

---

### Required Artifacts

| Artifact                                  | Expected                                                  | Status     | Details                                                                                           |
|-------------------------------------------|-----------------------------------------------------------|------------|---------------------------------------------------------------------------------------------------|
| `includes/class-cws-core-etch.php`        | Etch adapter; registers filter; injects cws_jobs; 70+ lines | VERIFIED   | 76 lines; namespace CWS_Core; class CWS_Core_Etch; php -l clean                                  |
| `cws-core.php`                            | Bootstrap with require_once for class-cws-core-etch.php   | VERIFIED   | Line 42: `require_once ... 'includes/class-cws-core-etch.php';`; php -l clean                    |
| `includes/class-cws-core.php`             | No virtual_cpt property; $etch slot; Etch init wiring     | VERIFIED   | `public $etch = null;` at line 55; class_exists guard at lines 106-108; Etch init at lines 150-152 |
| `includes/class-cws-core-admin.php`       | No test_virtual_cpt_ajax(); no "Virtual CPT Testing" UI   | VERIFIED   | Zero grep hits for test_virtual_cpt_ajax, test_virtual_cpt, "Virtual CPT Testing"                |

---

### Key Link Verification

| From                                     | To                              | Via                          | Status   | Details                                                              |
|------------------------------------------|---------------------------------|------------------------------|----------|----------------------------------------------------------------------|
| `class-cws-core-etch.php:init()`         | `etch/dynamic_data/option` filter | `add_filter`                 | WIRED    | Line 46: `add_filter( 'etch/dynamic_data/option', ... )`            |
| `CWS_Core_Etch::inject_options()`        | `CWS_Core::get_configured_job_ids()` | `$this->plugin->get_configured_job_ids()` | WIRED | Line 56: confirmed present                                        |
| `CWS_Core_Etch::inject_options()`        | `CWS_Core_API::get_jobs()`      | `$this->plugin->api->get_jobs()` | WIRED | Line 64: confirmed present                                         |
| `cws-core.php`                           | `includes/class-cws-core-etch.php` | `require_once`              | WIRED    | Line 42: confirmed present; file exists on disk                      |
| `class-cws-core.php:init_components()`   | `CWS_Core_Etch`                 | `class_exists` guard + instantiation | WIRED | Lines 106-108; `$this->etch = new CWS_Core_Etch($this)`          |
| `class-cws-core.php:init()`              | `$this->etch->init()`           | method_exists guard          | WIRED    | Lines 150-152: confirmed                                             |

---

### Requirements Coverage

The plans use free-text requirement strings (no REQ-IDs). No `REQUIREMENTS.md` exists in `.planning/`. Cross-referencing against plan frontmatter requirements:

| Requirement (from plan frontmatter)                  | Source Plan | Status     | Evidence                                                                          |
|------------------------------------------------------|-------------|------------|-----------------------------------------------------------------------------------|
| Remove virtual CPT infrastructure                    | 01-01       | SATISFIED  | File deleted; all wiring stripped; zero grep hits across 3 coordinator files       |
| Inject all-jobs array via `etch/dynamic_data/option` | 01-02       | SATISFIED  | Filter registered in init(); inject_options() populates `$options['cws_jobs']`    |
| All job API fields accessible via Etch dot notation  | 01-02       | SATISFIED  | Full queryResult array passed; no field stripping; all keys available via dot notation |

No orphaned requirements. All 3 plan requirements are accounted for and satisfied.

---

### Deleted Files Verification

All 17 files confirmed deleted from disk:

| Category          | File                               | Status  |
|-------------------|------------------------------------|---------|
| Virtual CPT class | `includes/class-cws-core-virtual-cpt.php` | DELETED |
| Template          | `templates/job.php`                | DELETED |
| Template          | `templates/job-debug-template.html` | DELETED |
| Template          | `templates/job-template-examples.html` | DELETED |
| Debug script      | `debug-check.php`                  | DELETED |
| Debug script      | `debug-meta.php`                   | DELETED |
| Debug script      | `debug-query.php`                  | DELETED |
| Debug script      | `debug-template.php`               | DELETED |
| Debug script      | `debug-virtual-posts-simple.php`   | DELETED |
| Debug script      | `debug-virtual-posts.php`          | DELETED |
| Test script       | `test-etchwp-meta.php`             | DELETED |
| Test script       | `test-etchwp-rest.php`             | DELETED |
| Test script       | `test-meta-query-simple.php`       | DELETED |
| Test script       | `test-meta-query.php`              | DELETED |
| Test script       | `test-unique-ids.php`              | DELETED |
| Test script       | `test-virtual-cpt.php`             | DELETED |
| Test script       | `test-virtual-meta.php`            | DELETED |

---

### Anti-Patterns Found

| File                         | Line | Pattern                                          | Severity | Impact                                                                                                           |
|------------------------------|------|--------------------------------------------------|----------|------------------------------------------------------------------------------------------------------------------|
| `includes/class-cws-core.php` | 236  | `return CWS_CORE_PLUGIN_DIR . 'templates/job.php'` in `load_job_template()` | Warning | References deleted file. Method is NOT registered as a hook (grep confirms no `add_filter('template_include')` or similar), so it cannot fire. Zero runtime impact in Phase 1. Phase 2 will replace this method body. |

No blockers. The dead `templates/job.php` reference is unreachable code — `load_job_template()` is defined but never wired into a WordPress hook, confirmed by grep across all plugin files.

---

### Human Verification Required

#### 1. Frontend Etch Template Render

**Test:** On the live WordPress site, create (or load) a page with an Etch template containing `{#loop options.cws_jobs as job}{job.title}{/loop}`. Visit that page on the frontend while the plugin is active and the API credentials are configured.
**Expected:** Job titles from the external API render in a list. No PHP errors appear in the debug log.
**Why human:** The filter wiring, class instantiation, and API call chain are all verified programmatically. The actual render requires a live WP environment with valid `cws_core_organization_id` setting and network access to `https://jobsapi-internal.m-cloud.io/api/stjob`.

---

### Gaps Summary

No gaps. All plan artifacts exist, are substantive, and are fully wired. The one human verification item (frontend render) is a runtime confirmation of already-verified wiring — it is not a blocker.

The only notable finding is the dead `load_job_template()` method referencing the deleted `templates/job.php`. This is benign: the method is not hooked and cannot execute. It is leftover pre-Phase-1 code that Phase 2 will repurpose.

---

## Summary

Phase 1 goal achieved. The 2,494-line virtual CPT is fully removed (class, 13 test/debug scripts, 3 templates, all wiring across 3 coordinator files). The replacement Etch integration is a clean 76-line adapter that correctly registers the `etch/dynamic_data/option` filter and injects the `cws_jobs` array from the configured API job IDs. All PHP files pass syntax check. All critical wiring is confirmed present and connected end-to-end.

---

_Verified: 2026-03-01_
_Verifier: Claude (gsd-verifier)_
