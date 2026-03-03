# Architecture

**Analysis Date:** 2026-03-01

## Pattern Overview

**Overall:** Multi-component WordPress plugin using singleton pattern for main plugin class and modular feature separation

**Key Characteristics:**
- Single entry point through plugin bootstrap at `cws-core.php`
- Singleton main class that coordinates component initialization
- Separate layers for admin, public, API, caching, and virtual post management
- Direct hook-based integration with WordPress lifecycle
- Virtual Custom Post Type implementation for dynamic job post generation

## Layers

**Bootstrap/Entry:**
- Purpose: Initialize plugin and load all class dependencies
- Location: `cws-core.php`
- Contains: Plugin header metadata, constant definitions, file includes, activation/deactivation hooks
- Depends on: None (WordPress core only)
- Used by: WordPress plugin loader

**Main Coordinator:**
- Purpose: Initialize and manage all plugin components; provide central API for options and logging
- Location: `includes/class-cws-core.php`
- Contains: Singleton instance management, component instantiation, plugin initialization flow, rewrite rules, query variable registration, job request handling
- Depends on: API, Cache, Admin, Public, Virtual CPT classes
- Used by: All component classes via dependency injection

**API Integration Layer:**
- Purpose: Handle external API communication, data fetching, and response parsing
- Location: `includes/class-cws-core-api.php`
- Contains: URL building, HTTP requests, response validation, job data fetching and formatting
- Depends on: Main plugin class (for options and logging), Cache class (via main plugin)
- Used by: Public layer, Virtual CPT, templates

**Cache Layer:**
- Purpose: Store API responses and job data using WordPress transients
- Location: `includes/class-cws-core-cache.php`
- Contains: Transient-based cache operations, cache expiration, cleanup scheduling
- Depends on: Main plugin class (for options and logging)
- Used by: API layer, Main coordinator

**Admin Interface:**
- Purpose: Provide settings page and administrative functions
- Location: `includes/class-cws-core-admin.php`
- Contains: Settings page rendering, option registration, admin menu creation, AJAX handlers for admin actions
- Depends on: Main plugin class (for options and logging)
- Used by: WordPress admin interface only

**Public Frontend:**
- Purpose: Handle frontend job page display and user-facing functionality
- Location: `includes/class-cws-core-public.php`
- Contains: Script/style enqueueing, job page detection, content injection, job display HTML building, meta tag generation
- Depends on: Main plugin class (API access), API layer
- Used by: Frontend page rendering, job templates

**Virtual Post Type:**
- Purpose: Create dynamic WordPress posts from API job data without database storage
- Location: `includes/class-cws-core-virtual-cpt.php`
- Contains: Virtual post registration, query interception, metadata handling, EtchWP integration hooks
- Depends on: Main plugin class (for caching and logging)
- Used by: Main coordinator, job request handler

## Data Flow

**Job Request Flow:**

1. User visits `/job/{job_id}/` URL
2. WordPress matches rewrite rule defined in `add_rewrite_rules()` (main class)
3. Query variables `cws_job_id` and `cws_job_title` are registered via `add_query_vars()` filter
4. `template_redirect` action triggers `handle_job_request()` (main class)
5. Main class checks if job ID is configured or dynamically discovered:
   - If new: calls `fetch_and_cache_job()` via API layer
   - Adds to discovered list in cache if successful
6. Virtual CPT creates temporary `WP_Post` object via `create_virtual_job_post()`
7. Post is set as global `$post` and template is loaded
8. Job template (`templates/job.php`) or theme template (if exists) renders the page
9. API layer retrieves cached job data and formats it
10. Public layer's `build_job_display()` generates HTML markup

**API Call Flow:**

1. API class builds URL with job IDs and organization ID from options
2. `fetch_job_data()` checks cache first (transient with `cws_core_` prefix)
3. If not cached, `make_request()` calls `wp_remote_get()` with JSON headers
4. Response validated for structure (requires `totalHits` and `queryResult` fields)
5. Successful response cached for duration configured in options (default 3600s)
6. Data returned to caller for formatting/display

**Virtual Post Meta Flow:**

1. Virtual CPT registers `cws_job` post type with support for native WordPress features
2. Multiple filters on `get_post_metadata` intercept meta requests
3. Meta is stored in cache and retrieved dynamically instead of from `wp_postmeta` table
4. EtchWP can query virtual posts like normal posts through query interception

**State Management:**

- Plugin options stored via WordPress options table (prefixed with `cws_core_`)
- Job data cached via WordPress transients (prefixed with `cws_core_`)
- Discovered job IDs list stored in cache
- Configuration includes: API endpoint, organization ID, job IDs, cache duration, debug mode, job slug
- Virtual posts held in global `$GLOBALS['cws_virtual_posts']` during page load for meta access

## Key Abstractions

**Job Data:**
- Purpose: Standardized representation of job information from external API
- Examples: `includes/class-cws-core-api.php` (`get_job()`, `format_job_data()`)
- Pattern: Object passed as associative array through layers, sanitized/escaped at boundaries

**Virtual Post Object:**
- Purpose: WordPress-compatible post representation created on-the-fly without database storage
- Examples: Created in `class-cws-core-virtual-cpt.php` (`create_virtual_job_post()`)
- Pattern: Minimal `WP_Post` with required fields, metadata dynamically resolved via filters

**Configuration Options:**
- Purpose: Centralized plugin settings accessible throughout
- Examples: API endpoint, organization ID, cache duration (accessed via `get_option()` / `update_option()`)
- Pattern: Wrapper methods in main class add `cws_core_` prefix automatically

**Cache Keys:**
- Purpose: Consistent naming for transient storage
- Examples: `job_data_{job_id}`, `discovered_job_ids`, `job_data_{md5(url)}`
- Pattern: Prefixed with `cws_core_`, suffixed with contextual identifier

## Entry Points

**Plugin Activation:**
- Location: `cws-core.php` - `cws_core_activate()`
- Triggers: WordPress plugin activation
- Responsibilities: Create default options, flush rewrite rules

**Plugin Initialization:**
- Location: `cws-core.php` - `cws_core_init()`
- Triggers: `plugins_loaded` action (after WordPress core loaded)
- Responsibilities: Instantiate main plugin class, call `init()` to initialize all components

**URL Request Handler:**
- Location: `includes/class-cws-core.php` - `handle_job_request()`
- Triggers: `template_redirect` action (after main query is run)
- Responsibilities: Detect job requests, fetch/cache data, create virtual post, set up globals

**Admin Page:**
- Location: `includes/class-cws-core-admin.php` - `render_settings_page()`
- Triggers: Admin menu click to `?page=cws-core-settings`
- Responsibilities: Display settings form for API configuration, job ID management

**Frontend Template:**
- Location: `templates/job.php`
- Triggers: Theme template loading for job requests
- Responsibilities: Get plugin instance, fetch job data, build display HTML, include JavaScript

## Error Handling

**Strategy:** Graceful degradation with logging, try-catch in initialization, option validation

**Patterns:**
- Options validated for existence before use (checked via `get_option()` with defaults)
- API requests wrapped in `is_wp_error()` checks
- Transient operations checked for false returns
- Class existence checked before instantiation with `class_exists()`
- Method existence checked before calling with `method_exists()`
- Errors logged via `error_log()` if debug mode enabled (checked via `get_option( 'cws_core_debug_mode' )`)
- Nonce verification on AJAX endpoints with fallback permission checks
- User capability checks on admin/sensitive operations (`current_user_can()`)

## Cross-Cutting Concerns

**Logging:**
- Implemented via `log()` method in main class
- Only outputs if `cws_core_debug_mode` option is true
- Prefixes messages with `[CWS Core]` and log level
- Available throughout codebase via `$this->plugin->log()`

**Validation:**
- API response structure validated via `validate_response()` (checks for required fields)
- Job IDs validated as numeric before API calls
- HTML output escaped with `esc_html()`, `esc_url()`, `wp_kses_post()`
- Incoming data sanitized with `sanitize_text_field()`

**Authentication:**
- Admin operations check `current_user_can( 'manage_options' )`
- AJAX handlers verify nonce with `wp_verify_nonce()`
- No public API key authentication (uses organization ID only)

**Caching:**
- Automatic 1-hour (configurable) expiration via transients
- Cache key includes context (job ID or URL hash) to prevent collisions
- Expired cache entries cleaned by scheduled `cws_core_cache_cleanup` event

---

*Architecture analysis: 2026-03-01*
