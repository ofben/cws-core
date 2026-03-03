# Coding Conventions

**Analysis Date:** 2026-03-01

## Naming Patterns

**Files:**
- Class files: `class-{namespace-slug}.php` (e.g., `class-cws-core-api.php`)
- All files lowercase with hyphens as separators
- Debug/test files: `debug-{purpose}.php`, `test-{purpose}.php`

**Functions:**
- Snake_case for all function names (e.g., `cws_core_init()`, `cws_core_activate()`)
- WordPress hook callbacks use array notation: `array( $this, 'method_name' )`
- Private methods: `private function name()`
- Public methods: `public function name()`

**Variables:**
- Snake_case for all variables: `$job_ids`, `$endpoint`, `$cache_key`, `$organization_id`
- Single letter variables acceptable only in loops: `$a`, `$b` in short comparisons
- Prefix private properties with nothing: `$this->plugin`, `$this->cache_key`
- Use descriptive names: `$virtual_posts` not `$posts`, `$formatted_job` not `$job`

**Types:**
- Namespaced classes: `CWS_Core\CWS_Core_API`, `CWS_Core\CWS_Core_Virtual_CPT`
- Namespace declaration: `namespace CWS_Core;` at top of file
- Class properties use type hints: `private CWS_Core $plugin;`
- Class constants: `CWS_CORE_VERSION`, `CWS_CORE_PLUGIN_DIR`, `CWS_CORE_PLUGIN_URL`
- Meta field names: Prefixed with `cws_job_` (e.g., `cws_job_company`, `cws_job_industry`, `cws_job_function`)

## Code Style

**Formatting:**
- No automatic code formatter in use (no PHPCS, Prettier config files found)
- Indentation: Tabs (standard WordPress style)
- Line length: No enforced limit, but examples show 100-120 character preference
- Spaces around operators: `if ( condition )`, `$var = value`
- Array formatting: Use short array syntax `array()`, not `[]`

**Linting:**
- No linting tool configured (no `.phpcs`, `phpstan.neon`, etc.)
- Code quality relies on manual review and tests
- Error logging via WordPress `error_log()` function

## Import Organization

**Order:**
1. WordPress core includes (rarely - usually auto-loaded)
2. Plugin constants (file-level, defined in main plugin file)
3. Class declarations (single per file)
4. Interface implementations

**Namespacing:**
- All plugin classes in `CWS_Core` namespace
- Classes referenced with full namespace: `CWS_Core\CWS_Core_API`
- No use of `use` statements for brevity (files too small to require them)
- Global functions prefixed: `cws_core_init()`, `cws_core_activate()`

**Plugin File Structure:**
```php
<?php
/**
 * File documentation header
 * @package CWS_Core
 */

namespace CWS_Core;

// Check for ABSPATH
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CWS_Core_ClassName {
    // Class implementation
}
```

## Error Handling

**Patterns:**
- Try-catch blocks for critical initialization: `try { ... } catch ( Exception $e ) { error_log(...); }`
- Graceful degradation: Catch exceptions, log error, continue without crashing
- Plugin stability over feature completeness: Never throw uncaught exceptions
- Class existence checks before instantiation: `if ( class_exists( 'CWS_Core\\CWS_Core_API' ) )`
- Method existence checks: `if ( method_exists( $this->api, 'init' ) )`

**Error Logging:**
```php
error_log( 'CWS Core: Description of what happened' );
error_log( 'CWS Core Component Error: ' . $e->getMessage() );
// or via plugin method:
$this->plugin->log( 'API endpoint or organization ID not configured', 'error' );
```

**Validation:**
- Sanitization: `sanitize_text_field()`, `intval()`, `esc_url()`
- Type checking: `is_numeric()`, `is_array()`, `is_string()`
- Empty checks: `if ( empty( $endpoint ) )`, `if ( null === $data )`
- Null coalescing in data formatting: `isset( $job['key'] ) ? value : ''`

## Logging

**Framework:** WordPress native `error_log()` function

**Patterns:**
- Log at plugin initialization: Track startup flow
- Log class instantiation: Record when objects are created
- Log hook registration: Confirm hooks are attached
- Log method entry/exit for critical paths
- Format: `'CWS Core: [Description]'` or `'[CWS Core] [LEVEL] [Message]'` via plugin method
- Debug messages: `$this->plugin->log( sprintf( 'Cache %s for key: %s', $status, $key ), 'debug' )`

**Examples:**
```php
error_log( 'CWS Core: Plugin initialization started' );
error_log( 'CWS Core: Admin class instantiated successfully' );
$this->plugin->log( 'Cache miss for key: user_list', 'debug' );
$this->plugin->log( 'API endpoint not configured', 'error' );
```

## Comments

**When to Comment:**
- Class docblocks: Required for all classes
- Method docblocks: Required for all public methods (may be lighter for private)
- Complex logic: Explain "why" not "what" the code does
- Workarounds: Document temporary solutions with context
- Hook explanations: Note which WordPress hooks are being used

**JSDoc/TSDoc:**
- PHPDoc format required for classes and public methods
- Format: `/** @package CWS_Core */`, `@var Type $name`, `@param Type $name Description`, `@return Type`

**Examples:**
```php
/**
 * Main CWS Core Plugin Class
 *
 * @package CWS_Core
 */
class CWS_Core {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private static $instance = null;

    /**
     * Build API URL
     *
     * @param string|array $job_ids Job ID(s) to fetch.
     * @return string|false API URL or false on error.
     */
    public function build_api_url( $job_ids ) {
        // Implementation
    }
}
```

## Function Design

**Size:** Methods range 10-150 lines
- Small utility methods: 5-20 lines
- Complex query/hook handlers: 50-150 lines
- Average: 30-60 lines

**Parameters:**
- Named parameters preferred in arrays: `array( 'key' => $value, 'key2' => $value2 )`
- Pass plugin instance via `set_plugin()` setter after instantiation
- Constructor dependency injection for main components: `__construct( CWS_Core $plugin )`

**Return Values:**
- Explicit return types where practical: `void`, `array`, `bool`, `string`
- False on error/not found: `return false;` (not `return null;` or `return [];`)
- Early returns for validation
- Always return consistent types

**Example:**
```php
public function get_option( $key, $default = '' ) {
    $option_key = 'cws_core_' . $key;
    $value = get_option( $option_key, $default );

    if ( empty( $value ) ) {
        return $default;
    }

    return $value;
}
```

## Module Design

**Exports:**
- Single class per file (one class definition)
- Static factories for singletons: `public static function get_instance()`
- Plugin instance passed as dependency after instantiation
- No global functions except `cws_core_*()` WordPress hooks

**Barrel Files:**
- Main plugin file `cws-core.php` requires all classes explicitly
- No wildcard imports or auto-loading
- Manual class loading in sequence: API, Cache, Admin, Public, Virtual CPT

**Organization:**
- One responsibility per class (API, Cache, Admin, Public, Virtual CPT)
- Shared functionality in main `CWS_Core` class
- Helper methods prefixed with `private` when not public API

## Database Queries

**Patterns:**
- Use WordPress `WP_Query` for post queries: `new WP_Query( $args )`
- Use WordPress meta functions: `get_post_meta()`, `get_transient()`
- Prefix transient/option names: `cws_core_` (cache keys, option names)
- Query interception via hooks: `posts_pre_query`, `the_posts`, `get_post_metadata`

**Examples:**
```php
$query = new WP_Query( array(
    'post_type'      => 'cws_job',
    'posts_per_page' => 10,
    'post_status'    => 'publish'
) );

$cached = get_transient( 'cws_core_job_list' );
set_transient( 'cws_core_job_list', $data, 3600 );
```

## REST API

**Patterns:**
- Register REST routes via `register_rest_route()`
- Namespace: `etch/v1` (for EtchWP compatibility)
- Endpoints: `/wp-json/etch/v1/meta`, `/wp-json/etch/v1/queries/wp-query`
- Response format: JSON arrays/objects matching EtchWP expectations
- Meta field mapping: `cws_job_*` to `job_*` aliases for compatibility

---

*Convention analysis: 2026-03-01*
