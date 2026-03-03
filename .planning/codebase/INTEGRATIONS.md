# External Integrations

**Analysis Date:** 2026-03-01

## APIs & External Services

**Job Data API:**
- **Service:** m-cloud Jobs API (jobsapi-internal.m-cloud.io)
- **What it's used for:** Fetching job listings by ID(s) and organization
- **SDK/Client:** WordPress native `wp_remote_get()` (no external SDK)
- **Auth:** Organization ID parameter in query string
- **Endpoint:** `https://jobsapi-internal.m-cloud.io/api/stjob`
- **Query Parameters:**
  - `organization`: Organization ID (numeric, required)
  - `jobList`: Comma-delimited job IDs (required)

**API Response Format:**
- **Expected structure:** JSON with `totalHits` and `queryResult` fields
- **Fields from API:**
  - `id`: Job identifier
  - `title`: Job position title
  - `company_name`: Employer name
  - `description`: Full job description HTML
  - `entity_status`: Job status indicator
  - `primary_city`: Job location city
  - `primary_state`: Job location state
  - `primary_country`: Job location country
  - `primary_location`: Location object
  - `primary_category`: Job category
  - `department`: Department/team
  - `industry`: Industry classification
  - `function`: Job function/role type
  - `salary`: Salary range or information
  - `employment_type`: Full-time, part-time, contract, etc.
  - `url`: Direct application URL
  - `seo_url`: SEO-friendly URL
  - `open_date`: Job posting date
  - `update_date`: Last update date

**Request Configuration:**
- **Timeout:** 30 seconds
- **Method:** HTTP GET
- **Headers:**
  - Content-Type: application/json
  - Accept: application/json

## Data Storage

**Databases:**
- **Type:** WordPress (MySQL/MariaDB)
- **Connection:** Via WordPress `$wpdb` global
- **Client:** WordPress core database API
- **Tables used:**
  - `wp_options` - Plugin settings and cache storage
  - `wp_posts` - Virtual job post objects
  - `wp_postmeta` - Virtual job metadata

**Cache Storage:**
- **Type:** Transients (WordPress database-backed cache)
- **Prefix:** `cws_core_`
- **Storage location:** `wp_options` table
- **Keys:**
  - `cws_core_job_data_*` - Individual job data by ID
  - `cws_core_discovered_job_ids` - List of discovered job IDs
  - Cache cleanup entries with `_transient_timeout_` suffix

**File Storage:**
- Not used - All data stored in database

**Caching:**
- **Type:** WordPress Transients API (database)
- **Default duration:** 3600 seconds (1 hour)
- **Configurable:** Yes, via `cws_core_cache_duration` option
- **Range:** 15 minutes to 24 hours
- **Manual clear:** Available through admin panel AJAX endpoint
- **Automatic cleanup:** Daily scheduled job via WordPress cron

## Authentication & Identity

**Auth Provider:**
- **Type:** None - Custom API key pattern
- **Implementation:** Organization ID parameter in query string
- **Configuration:**
  - Stored in: `cws_core_organization_id` WordPress option
  - Set via: Admin settings page (Settings > CWS Core)
  - Not stored as environment variable

**WordPress Admin Authentication:**
- **Required for:** Admin panel access
- **Capability:** `manage_options` (administrator level)
- **Nonce verification:** Used for AJAX endpoints (`cws_core_test_api`, `cws_core_clear_cache`)

## Monitoring & Observability

**Error Tracking:**
- **Type:** Native WordPress error logging
- **Method:** `error_log()` function writes to WordPress debug log
- **Enabled by:** Setting `WP_DEBUG_LOG` to true in wp-config.php
- **Log location:** Configured via `WP_DEBUG_LOG` constant
- **Log level:** Info, debug, error levels supported

**Logs:**
- **Error logging:** Native PHP error_log()
- **Conditional:** Debug mode toggle via `cws_core_debug_mode` option
- **Information logged:**
  - Plugin initialization events
  - API requests and responses
  - Cache hits/misses
  - Job data fetching success/failure
  - Virtual post creation events

## CI/CD & Deployment

**Hosting:**
- **Platform:** Self-hosted WordPress
- **Type:** WordPress plugin (wp-content/plugins/cws-core)
- **Location:** `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core`

**CI Pipeline:**
- **Type:** None detected
- **Build process:** Manual plugin upload/activation
- **Version control:** Git repository present

## Environment Configuration

**Required env vars:**
- None - All configuration stored in WordPress options

**Critical WordPress Options:**
- `cws_core_api_endpoint` - API endpoint URL
- `cws_core_organization_id` - Organization ID (required for API calls)
- `cws_core_cache_duration` - Cache expiration time
- `cws_core_job_slug` - URL slug for job pages
- `cws_core_debug_mode` - Enable/disable debug logging
- `cws_core_job_ids` - Configured job IDs list

**Secrets location:**
- Organization ID stored in WordPress options (database)
- No API keys or sensitive credentials detected in code
- All secrets managed through WordPress admin interface

## REST API Endpoints

**Incoming - Plugin-provided endpoints:**

**Test API Connection:**
- **Endpoint:** AJAX action `cws_core_test_api`
- **Method:** POST
- **Auth:** `manage_options` capability
- **Purpose:** Verify external API connectivity

**Clear Cache:**
- **Endpoint:** AJAX action `cws_core_clear_cache`
- **Method:** POST
- **Auth:** `manage_options` capability
- **Purpose:** Manually clear all plugin cache

**Cache Statistics:**
- **Endpoint:** AJAX action `cws_core_get_cache_stats`
- **Method:** POST
- **Auth:** `manage_options` capability
- **Purpose:** Retrieve cache hit/miss statistics

**EtchWP Meta Endpoint:**
- **Endpoint:** `GET /wp-json/cws-core/v1/etchwp-meta/{post_id}`
- **Auth:** Public (nopriv)
- **Purpose:** Provide virtual post metadata for EtchWP rendering

**Flush Rewrite Rules:**
- **Endpoint:** AJAX action `cws_core_flush_rules`
- **Method:** POST
- **Auth:** `manage_options` capability
- **Purpose:** Flush WordPress rewrite rules after slug changes

## Webhooks & Callbacks

**Incoming:**
- None configured

**Outgoing:**
- Job application redirect URL from API response (`url` field in job data)
- User redirected to external application URL on job apply action

## WordPress Hook Integration Points

**External API Calls:**
- `fetch_job_data()` - Called during page load for job ID
- `get_job()` - Retrieves single job by ID
- `get_jobs()` - Retrieves multiple jobs by IDs

**Cache Hooks:**
- `cws_core_cache_cleanup` - Daily scheduled event for cache maintenance
- `set_transient()` / `get_transient()` - WordPress native caching

**Admin Panel:**
- Settings page: Settings > CWS Core
- Connection testing through admin interface
- Manual cache clearing from admin panel

---

*Integration audit: 2026-03-01*
