# Architecture Research

**Domain:** WordPress plugin — Etch page builder dynamic data integration
**Researched:** 2026-03-01 (updated 2026-03-02 — filter confirmed in Etch v1.3.1)
**Confidence:** HIGH — based on direct Etch v1.3.1 source inspection and existing plugin analysis

---

## Target Architecture (Post-Milestone)

This document describes the architecture **after** the Dynamic Data Rebuild milestone. The existing
virtual CPT approach is replaced by a clean Etch filter integration.

### System Overview

```
┌──────────────────────────────────────────────────────────────┐
│                    WordPress Request Pipeline                  │
│                                                              │
│  HTTP Request → WP Router → WP_Query → template_redirect    │
│                                    ↓                         │
│                           CWS_Core_Etch                      │
│                      (detects cws_job_id,                    │
│                        swaps $post, stores job ID)           │
└──────────────────────────────┬───────────────────────────────┘
                               ↓
┌──────────────────────────────────────────────────────────────┐
│                     Etch Render Pipeline                       │
│                                                              │
│  Preprocessor (the_content @ priority 1)                     │
│    → BaseBlock::add_this_post_context()                      │
│    → DynamicData::get_dynamic_option_pages_data()            │
│    → apply_filters('etch/dynamic_data/option', $data)  ←────────── CWS_Core_Etch::inject_options()
│    → {options.cws_jobs} + {options.cws_job} resolved         │
└──────────────────────────────────────────────────────────────┘
                               ↓
┌──────────────────────────────────────────────────────────────┐
│                      Data Layer                               │
│                                                              │
│  CWS_Core_API          CWS_Core_Cache                        │
│  ┌─────────────┐       ┌─────────────┐                       │
│  │ get_jobs()  │──────▶│  transients │                       │
│  │ get_job()   │       │  (1hr TTL)  │                       │
│  │ format_job_ │       └─────────────┘                       │
│  │   data()    │                                              │
│  └─────────────┘                                             │
│         ↓                                                    │
│  External API: jobsapi-internal.m-cloud.io                   │
└──────────────────────────────────────────────────────────────┘
```

### Component Responsibilities

| Component | Responsibility | Status |
|-----------|----------------|--------|
| `cws-core.php` | Bootstrap, file includes, activation hook | Modify: remove virtual CPT include |
| `class-cws-core.php` | Singleton coordinator, rewrite rules, query vars | Modify: remove virtual CPT wiring |
| `class-cws-core-etch.php` | All Etch integration: filter registration, data injection, preview detection | **NEW** |
| `class-cws-core-api.php` | HTTP client, API response parsing, `format_job_data()` | Keep as-is |
| `class-cws-core-cache.php` | Transient wrapper, cache invalidation | Keep as-is |
| `class-cws-core-admin.php` | Settings UI, AJAX handlers | Keep as-is |
| `class-cws-core-public.php` | Frontend asset loading | Keep as-is |
| `class-cws-core-virtual-cpt.php` | Virtual post creation, 20+ conflicting hooks | **DELETE** |
| `templates/job.php` | Old virtual-post template | **DELETE** |

---

## Recommended File Structure (After Migration)

```
cws-core/
├── cws-core.php                          # Bootstrap — remove virtual CPT include
├── uninstall.php                         # No changes
├── includes/
│   ├── class-cws-core.php                # Main coordinator — remove virtual_cpt wiring
│   ├── class-cws-core-api.php            # Keep as-is
│   ├── class-cws-core-cache.php          # Keep as-is
│   ├── class-cws-core-admin.php          # Keep as-is
│   ├── class-cws-core-public.php         # Keep as-is
│   └── class-cws-core-etch.php           # NEW — entire Etch integration
├── admin/
│   ├── css/admin.css
│   └── js/admin.js
└── public/
    ├── css/public.css
    └── js/public.js
```

**Removed:**
- `includes/class-cws-core-virtual-cpt.php` — 2,494 lines, replaced by ~150 lines in etch.php
- `templates/job.php` — virtual-post template, superseded by real WP page + Etch template

---

## The Core Integration Pattern

### Pattern 1: Etch Dynamic Data Filter

**What:** Hook `etch/dynamic_data/option` to inject custom keys into Etch's `options` context. The
filter fires once per page render inside `DynamicData::get_dynamic_option_pages_data()`. The
returned array becomes the `options` context root — all `{options.*}` template variables resolve
against it.

**Confirmed signature** (Etch v1.3.1, `classes/Traits/DynamicData.php:196`):
```php
$data_filtered = apply_filters( 'etch/dynamic_data/option', $data );
// Single argument — $data is array<string, mixed>
// Must return array or E_USER_WARNING fires
// Result cached in $this->cached_data['option_page'] for the page lifetime
```

**When to use:** Any time external data needs to be available as `{options.*}` in Etch templates.
This is the only stable, documented hook for injecting into the options context.

**Trade-offs:**
- Pro: Official, documented, survives Etch upgrades
- Pro: Fires once per page (cached result), no per-block overhead
- Pro: Works across all Etch block types — loop, text, image, etc.
- Con: Single arg (no post_id) — must use `get_query_var()` or `$GLOBALS` to determine context
- Con: Result is cached in `$this->cached_data` — must provide all data in one callback

**Implementation skeleton:**
```php
class CWS_Core_Etch {
    private $plugin;
    private $current_job_id = '';

    public function init(): void {
        add_action( 'template_redirect', [ $this, 'handle_job_request' ], 10 );
        add_filter( 'etch/dynamic_data/option', [ $this, 'inject_options' ], 10, 1 );
    }

    public function handle_job_request(): void {
        $job_id = sanitize_text_field( get_query_var( 'cws_job_id' ) );
        if ( empty( $job_id ) ) {
            return;
        }
        $this->current_job_id = $job_id;
        // Swap $post to real job template page...
    }

    public function inject_options( array $options ): array {
        // All jobs (listing page + preview)
        $jobs = $this->plugin->api->get_jobs( $this->plugin->get_configured_job_ids() );
        if ( is_array( $jobs ) ) {
            $options['cws_jobs'] = array_values( $jobs );
        }

        // Single job (detail page or preview fallback)
        $job_id = $this->current_job_id;
        if ( empty( $job_id ) && $this->is_etch_preview() ) {
            $ids    = $this->plugin->get_configured_job_ids();
            $job_id = ! empty( $ids ) ? reset( $ids ) : '';
        }
        if ( ! empty( $job_id ) ) {
            $job = $this->plugin->api->get_job( $job_id );
            if ( $job ) {
                $options['cws_job'] = $job;
            }
        }
        return $options;
    }

    private function is_etch_preview(): bool {
        return isset( $_GET['etch'] ) && 'magic' === $_GET['etch'] && is_user_logged_in();
    }
}
```

---

### Pattern 2: Real WordPress Page as Job Template

**What:** Instead of creating a virtual `WP_Post` in memory, `template_redirect` swaps the global
`$post` to a real WordPress page that the site admin creates (slug = `cws_core_job_slug`). Etch
renders its block template against this real page. The job data is available via
`{options.cws_job.*}` from Pattern 1.

**Why needed:** Etch's preprocessor calls `get_post()` to get the current post and build context.
A virtual/null post object causes Etch to fail silently — `$this->context['this']` is null and
all dynamic data resolution fails.

**Implementation:**
```php
public function handle_job_request(): void {
    $job_id = sanitize_text_field( get_query_var( 'cws_job_id' ) );
    if ( empty( $job_id ) ) {
        return;
    }

    $this->current_job_id = $job_id;

    $slug = get_option( 'cws_core_job_slug', 'job' );
    $page = get_page_by_path( $slug );
    if ( ! $page ) {
        return; // No template page configured — fall through to 404
    }

    global $post, $wp_query;
    $post = $page;
    setup_postdata( $post );
    $wp_query->queried_object    = $page;
    $wp_query->queried_object_id = $page->ID;
    $wp_query->is_page           = true;
    $wp_query->is_singular       = true;
    $wp_query->is_404            = false;
}
```

**Setup requirement:** Admin must create a WordPress page with slug matching `cws_core_job_slug`
(default: `job`). Plugin should detect and warn if this page is missing.

---

## Data Flow

### Listing Page Flow

```
Request: /jobs-page/ (any page with {#loop options.cws_jobs as job} in Etch template)
    ↓
WordPress loads the page normally
    ↓
Etch Preprocessor: the_content @ priority 1
    ↓
DynamicData::get_dynamic_option_pages_data()
    ↓
apply_filters('etch/dynamic_data/option', [])
    ↓
CWS_Core_Etch::inject_options() — no job ID set
    ↓
$options['cws_jobs'] = all jobs from API/cache
    ↓
Etch resolves {#loop options.cws_jobs as job} → renders each job card
```

### Single Job Page Flow

```
Request: /job/22026695/
    ↓
WordPress rewrite rule: ^job/([0-9]+)/?$ → cws_job_id=22026695
    ↓
template_redirect (priority 10)
    ↓
CWS_Core_Etch::handle_job_request()
  - stores current_job_id = '22026695'
  - loads page with slug 'job', swaps global $post
    ↓
Etch Preprocessor: the_content @ priority 1 (runs against swapped $post)
    ↓
apply_filters('etch/dynamic_data/option', [])
    ↓
CWS_Core_Etch::inject_options()
  - $options['cws_jobs'] = all jobs
  - $options['cws_job']  = job 22026695 from API/cache
    ↓
Etch resolves {options.cws_job.title}, {options.cws_job.description}, etc.
```

### Etch Builder Preview Flow

```
Request: /?etch=magic&post_id=X (editor opening job template page)
    ↓
template_redirect — no cws_job_id query var
CWS_Core_Etch::handle_job_request() returns early
    ↓
apply_filters('etch/dynamic_data/option', [])
    ↓
CWS_Core_Etch::inject_options()
  - current_job_id is empty
  - is_etch_preview() → true (etch=magic + is_user_logged_in())
  - falls back to first configured job ID
  - $options['cws_job'] = first configured job (real API data)
  - $options['cws_jobs'] = all jobs
    ↓
Editor sees real job data in preview — can design template
```

---

## Hook Timing Contract

**Critical constraint:** Etch hooks `the_content` at **priority 1**. Data must be stored in the
class property before this fires. `template_redirect` fires before `the_content`, so storing
`$current_job_id` in `handle_job_request()` is safe.

```
WordPress lifecycle (simplified):
  init
  wp (main query set up)
  template_redirect   ← CWS_Core_Etch::handle_job_request() stores job_id HERE
  the_content (p=1)   ← Etch processes blocks, calls inject_options() HERE
  the_content (p=10)  ← (other filters — too late for Etch)
```

---

## Integration Points

### External Services

| Service | Integration Pattern | Notes |
|---------|---------------------|-------|
| Etch v1.3.1 | `etch/dynamic_data/option` filter | Confirmed at `DynamicData.php:196` |
| Jobs API | `wp_remote_get()` via `CWS_Core_API` | Read-only, org ID auth, no rate limits |

### Internal Boundaries

| Boundary | Communication | Notes |
|----------|---------------|-------|
| `CWS_Core_Etch` ↔ `CWS_Core_API` | Direct method call via `$this->plugin->api` | API class is singleton-accessible via main plugin |
| `CWS_Core_Etch` ↔ `CWS_Core_Cache` | Indirect via API layer | Cache is transparent to Etch class |
| `CWS_Core` ↔ `CWS_Core_Etch` | Instantiation + `init()` call | Same pattern as other components |

---

## Anti-Patterns

### Anti-Pattern 1: Injecting Data via `the_content` Hook

**What people do:** Hook into `the_content` to inject job data into the page.
**Why it's wrong:** Etch fires at priority 1. A `the_content` hook at any priority ≥ 1 is too late
— Etch has already resolved all `{options.*}` placeholders to empty strings.
**Do this instead:** Store data in a class property during `template_redirect`. The
`etch/dynamic_data/option` filter reads from that property when it fires.

### Anti-Pattern 2: Keeping the Virtual CPT Class Alongside New Integration

**What people do:** Comment out one line from `class-cws-core.php` but leave the file included.
**Why it's wrong:** The virtual CPT registers 20+ WordPress hooks in its constructor. As long as
the file is included (even if not instantiated), those hooks can still fire depending on PHP
class loading. Double hook registration causes unpredictable rendering.
**Do this instead:** Remove the `require_once` for `class-cws-core-virtual-cpt.php` from
`cws-core.php` entirely in the same commit as adding the new integration.

### Anti-Pattern 3: Calling `get_jobs()` Inside a Loop

**What people do:** Fetch job data inside a block render callback or a loop that fires per-block.
**Why it's wrong:** The API + cache layer is efficient, but `get_jobs()` deserializes the full
cached payload every call. Per-block execution multiplies this overhead.
**Do this instead:** Call `get_jobs()` once inside `inject_options()` and assign to `$options`.
Etch's result caching (`$this->cached_data['option_page']`) ensures the filter fires only once
per page render.

---

## Sources

- Etch v1.3.1 source: `classes/Traits/DynamicData.php:186–207` — `get_dynamic_option_pages_data()` + confirmed filter (HIGH)
- Etch v1.3.1 source: `classes/Preprocessor/Preprocessor.php` — `the_content` hook at priority 1 (HIGH)
- Etch v1.3.1 source: `classes/Preprocessor/Blocks/BaseBlock.php` — context construction pattern (HIGH)
- CWS Core source: `includes/class-cws-core.php` — existing rewrite rules + `template_redirect` pattern (HIGH)
- CWS Core source: `includes/class-cws-core-api.php` — `get_jobs()`, `get_job()`, `format_job_data()` (HIGH)
- STACK.md: integration approach analysis, hook timing research (HIGH)
- FEATURES.md: MVP feature prioritization and dependency graph (HIGH)
- PITFALLS.md: hook timing pitfall (Pitfall 2), virtual CPT conflict (Pitfall 9) (HIGH)

---

*Architecture research for: CWS Core — Etch Dynamic Data Rebuild*
*Researched: 2026-03-02*
