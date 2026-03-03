# Technology Stack

**Analysis Date:** 2026-03-01

## Languages

**Primary:**
- PHP 7.4+ - Core plugin implementation and API handling

## Runtime

**Environment:**
- WordPress 5.0+ (tested up to 6.4)
- PHP 7.4+

**Package Manager:**
- None detected - Plugin uses WordPress core only, no Composer dependencies

## Frameworks

**Core:**
- WordPress - 5.0+ - Base platform for plugin functionality

**Plugin Architecture:**
- Object-Oriented PHP with class-based structure
- WordPress hooks and filters system
- Namespaced classes under `CWS_Core` namespace

## Key Dependencies

**Critical:**
- WordPress Core API (`wp_remote_get`, `wp_remote_post`) - HTTP request handling
- WordPress Transients API - Server-side caching via database
- WordPress REST API - Virtual post type exposure and EtchWP integration
- WordPress AJAX API - Admin and frontend AJAX requests

**Infrastructure:**
- `wp_options` table - Plugin settings and cache storage
- WordPress database (through `$wpdb`) - Cache cleanup and statistics

## Configuration

**Environment:**
- Configuration stored in WordPress options (wp_options table)
- Settings managed through admin interface at Settings > CWS Core
- No environment variables or .env files required

**Key Configuration Options:**
- `cws_core_api_endpoint` - Default: `https://jobsapi-internal.m-cloud.io/api/stjob`
- `cws_core_organization_id` - Organization ID for API authentication
- `cws_core_cache_duration` - Default: 3600 seconds (1 hour)
- `cws_core_job_slug` - Default: `job` (URL slug for job pages)
- `cws_core_debug_mode` - Default: false
- `cws_core_job_ids` - Comma-separated list of job IDs to fetch

## Platform Requirements

**Development:**
- WordPress installation with plugin support
- Write access to WordPress wp-content/plugins directory
- Admin access to WordPress settings panel

**Production:**
- WordPress 5.0 or higher
- PHP 7.4 or higher
- HTTPS connection to external job API endpoint
- Outbound HTTPS requests enabled
- WordPress database write permissions for caching

## API Integration

**External API Communication:**
- HTTP client: WordPress native `wp_remote_get()` function
- Request timeout: 30 seconds
- User-Agent: `CWS-Core-Plugin/{VERSION}`
- Content-Type: `application/json`

**Request Headers:**
- `Accept: application/json`
- `Content-Type: application/json`

## Transient Caching System

**Cache Driver:**
- WordPress Transients API (database-backed)
- Prefix: `cws_core_`
- Automatic expiration based on configured duration
- Manual deletion via AJAX endpoint

**Scheduled Tasks:**
- Daily cache cleanup scheduled via WordPress cron (`cws_core_cache_cleanup`)
- Automatic cleanup of expired transient entries

## Frontend Assets

**Scripts:**
- jQuery dependency for public JavaScript
- Location: `public/js/public.js`

**Styles:**
- Location: `public/css/public.css`
- Mobile-responsive design

## Admin Assets

**Admin Scripts:**
- Located in admin directory
- Enqueued only on plugin settings pages

---

*Stack analysis: 2026-03-01*
