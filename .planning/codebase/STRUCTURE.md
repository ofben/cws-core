# Codebase Structure

**Analysis Date:** 2026-03-01

## Directory Layout

```
cws-core/
├── cws-core.php                 # Plugin bootstrap and entry point
├── uninstall.php                # Plugin uninstall cleanup
├── includes/                     # Core plugin classes
│   ├── class-cws-core.php
│   ├── class-cws-core-api.php
│   ├── class-cws-core-cache.php
│   ├── class-cws-core-admin.php
│   ├── class-cws-core-public.php
│   └── class-cws-core-virtual-cpt.php
├── templates/                    # Frontend templates
│   └── job.php
├── admin/                        # Admin-facing assets
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── views/                    # Admin template files
├── public/                       # Frontend-facing assets
│   ├── css/
│   │   └── public.css
│   └── js/
│       └── public.js
├── languages/                    # i18n translation files
├── .planning/                    # Documentation
│   └── codebase/                # Architecture/structure docs
└── [debug/test files]           # Temporary debugging utilities
```

## Directory Purposes

**Root Directory:**
- Purpose: Plugin metadata and main entry point
- Contains: Plugin file, uninstall handler, git repo
- Key files: `cws-core.php` (main entry), `uninstall.php` (cleanup on deletion)

**includes/:**
- Purpose: Core plugin class files implementing functionality
- Contains: All class definitions for plugin components
- Key files: Main orchestrator class, API client, caching, admin UI, frontend, virtual posts

**templates/:**
- Purpose: Default frontend template for job page rendering
- Contains: Single template file for job display
- Key files: `job.php` (fallback template when theme doesn't provide custom template)

**admin/:**
- Purpose: Administration panel assets and templates
- Contains: CSS for admin styling, JavaScript for admin interactions, admin page templates
- Key files: `admin.css` (menu/form styling), `admin.js` (settings interactions), views/ (settings page HTML)

**public/:**
- Purpose: Frontend-facing CSS and JavaScript assets
- Contains: Styling for job display, JavaScript for frontend functionality
- Key files: `public.css` (job page styling), `public.js` (frontend interactions)

**languages/:**
- Purpose: Translation files for internationalization
- Contains: .po/.pot translation files
- Key files: Translation catalogs for 'cws-core' text domain

**.planning/codebase/:**
- Purpose: Architecture and structure documentation
- Contains: Generated analysis documents
- Key files: ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, TESTING.md (created by GSD)

## Key File Locations

**Entry Points:**
- `cws-core.php`: Plugin bootstrap, class loading, activation/deactivation hooks, initialization trigger
- `includes/class-cws-core.php`: Main plugin class, component coordination, public API
- `templates/job.php`: Default frontend template for job pages

**Configuration:**
- Plugin options managed via WordPress options table (no separate config files)
- API endpoint, organization ID, job IDs stored as options with `cws_core_` prefix
- Admin settings page at `includes/class-cws-core-admin.php`

**Core Logic:**
- `includes/class-cws-core-api.php`: External API communication and job data
- `includes/class-cws-core-cache.php`: Data caching via WordPress transients
- `includes/class-cws-core-virtual-cpt.php`: Dynamic WordPress post creation

**Frontend Rendering:**
- `includes/class-cws-core-public.php`: Frontend hooks, job display building
- `public/js/public.js`: Frontend JavaScript functionality
- `public/css/public.css`: Frontend styling

**Admin Interface:**
- `includes/class-cws-core-admin.php`: Settings page, menu registration, AJAX handlers
- `admin/js/admin.js`: Admin panel interactions
- `admin/css/admin.css`: Admin panel styling
- `admin/views/`: Settings page template files

**Testing/Debugging:**
- `debug-*.php`: Temporary debug scripts (not production code)
- `test-*.php`: Temporary test scripts (not production code)

## Naming Conventions

**Files:**
- Class files: `class-{component-name}.php` (e.g., `class-cws-core-api.php`)
- Template files: `{purpose}.php` (e.g., `job.php`)
- Asset files: `{purpose}.{type}` (e.g., `admin.css`, `public.js`)
- Debug files: `debug-{purpose}.php` (e.g., `debug-virtual-posts.php`)

**Directories:**
- Feature groups: lowercase plural nouns (`includes/`, `templates/`, `admin/`, `public/`)
- Asset subdirectories: `css/` and `js/` under feature directories

**Classes:**
- Namespace: `CWS_Core` (all classes use this namespace)
- Class names: `CWS_Core_{Feature}` (e.g., `CWS_Core_API`, `CWS_Core_Cache`)
- Main class: `CWS_Core` (no feature suffix, acts as coordinator)

**Functions:**
- Plugin-wide functions: `cws_core_{purpose}` (e.g., `cws_core_init()`, `cws_core_activate()`)
- Class method names: verb_object pattern (e.g., `get_job()`, `build_api_url()`, `fetch_job_data()`)

**Constants:**
- Plugin constants: `CWS_CORE_{PURPOSE}` (e.g., `CWS_CORE_VERSION`, `CWS_CORE_PLUGIN_DIR`)
- Prefixed in options table: `cws_core_` (e.g., `cws_core_api_endpoint`, `cws_core_job_ids`)

**Hooks:**
- AJAX actions: `cws_core_{purpose}` (e.g., `cws_core_test_api`, `cws_core_flush_rules`)
- Query variables: `cws_{type}` (e.g., `cws_job_id`, `cws_job_title`)
- Scheduled events: `cws_core_{event}` (e.g., `cws_core_cache_cleanup`)
- Custom filters: `cws_core_{context}` (e.g., `cws_virtual_post_meta`)

## Where to Add New Code

**New Feature for Job Display:**
- Primary code: `includes/class-cws-core-public.php` (add to `build_job_display()` or new method)
- JavaScript: `public/js/public.js` (frontend interactivity)
- Styling: `public/css/public.css` (visual presentation)
- Template: `templates/job.php` (default template rendering)

**New Admin Setting:**
- Register field: `includes/class-cws-core-admin.php` - `register_settings()` method
- Render control: `includes/class-cws-core-admin.php` - `render_settings_page()` method
- JavaScript interaction: `admin/js/admin.js`
- Admin styling: `admin/css/admin.css`

**New API Integration:**
- Client code: `includes/class-cws-core-api.php` (add method to fetch/format new data type)
- Call from: `includes/class-cws-core-public.php` via `$this->plugin->api->method_name()`

**New Component/Module:**
- Create class file: `includes/class-cws-core-{feature}.php`
- Use namespace: `namespace CWS_Core;`
- Implement initialization: `public function init()` method for hook registration
- Dependency injection: Pass main plugin class via constructor
- Add to loader: Include file in `cws-core.php`, instantiate in `class-cws-core.php` `init_components()`
- Access from other code: Via `$this->plugin->{feature}->method()`

**Utilities/Helpers:**
- Shared helpers: Consider adding as static methods to main class if single use, or create `class-cws-core-helpers.php` if reusable
- Plugin-wide functions: Define in `cws-core.php` after class includes, use `cws_core_` prefix

## Special Directories

**Debug Files:**
- Purpose: Temporary test/debugging scripts for development
- Generated: Manually created during development
- Committed: Yes (for debugging reference, should be removed before release)
- Location: Root directory (e.g., `debug-virtual-posts.php`, `test-meta-query.php`)
- Note: Not loaded by plugin, accessed directly via web or CLI

**Admin Views:**
- Purpose: Separate HTML templates for admin page rendering
- Generated: Manually created for complex admin UIs
- Committed: Yes
- Location: `admin/views/` directory
- Usage: Included from `class-cws-core-admin.php` `render_settings_page()`

**Languages:**
- Purpose: Translation files for i18n
- Generated: Manually created or via translation tools
- Committed: Yes
- Location: `languages/` directory
- Pattern: `cws-core-{lang_code}.po` and `cws-core-{lang_code}.mo`

**.planning Directory:**
- Purpose: Project planning and documentation
- Generated: By GSD commands (`/gsd:map-codebase`)
- Committed: Yes (useful for future phases)
- Location: `.planning/codebase/`
- Contains: ARCHITECTURE.md, STRUCTURE.md, CONVENTIONS.md, TESTING.md, CONCERNS.md

**.git Directory:**
- Purpose: Version control repository
- Generated: Repository initialization
- Committed: N/A (git internals)
- Location: Root directory `.git/`

---

*Structure analysis: 2026-03-01*
