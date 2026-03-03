# Testing Patterns

**Analysis Date:** 2026-03-01

## Test Framework

**Runner:**
- None (no automated test framework in use)
- Tests are manual PHP scripts loaded via `wp-load.php`

**Assertion Library:**
- None (no formal assertion library)
- Manual validation via `if ( condition ) { echo success } else { echo failure }`
- Visual verification in browser (HTML output with ✅/❌ indicators)

**Run Commands:**
```bash
# Manual test execution (place in WordPress root, access via browser)
wp-load.php > test-*.php          # Load test file in browser
```

Note: No automated test runner. Tests require WordPress environment and manual execution.

## Test File Organization

**Location:**
- Tests placed in plugin root alongside main plugin file
- Not in separate `/tests` directory
- Not separated from source code

**Naming:**
- Format: `test-{feature}.php` or `debug-{feature}.php`
- Examples: `test-virtual-cpt.php`, `test-meta-query.php`, `debug-query.php`
- Test files: `test-*.php`
- Debug files: `debug-*.php` (exploratory/diagnostic scripts)

**Structure:**
```
cws-core/
├── cws-core.php                          (main plugin file)
├── includes/                             (source code)
│   ├── class-cws-core.php
│   ├── class-cws-core-api.php
│   ├── class-cws-core-cache.php
│   ├── class-cws-core-admin.php
│   ├── class-cws-core-public.php
│   └── class-cws-core-virtual-cpt.php
├── test-virtual-cpt.php                  (test files)
├── test-meta-query.php
├── test-meta-query-simple.php
├── test-unique-ids.php
├── test-virtual-meta.php
├── test-etchwp-meta.php
├── test-etchwp-rest.php
├── debug-check.php
├── debug-meta.php
├── debug-query.php
├── debug-template.php
├── debug-virtual-posts.php
└── debug-virtual-posts-simple.php
```

## Test Structure

**Suite Organization:**
```php
<?php
/**
 * Test [Feature Name]
 * Place this in your WordPress root directory and run it directly
 */

// Load WordPress
require_once('wp-load.php');

// Output HTML header
echo "<h1>Test Name</h1>";

// Get plugin instance
global $cws_core;
if (!$cws_core || !isset($cws_core->component)) {
    echo "<p style='color: red;'>❌ Plugin component not found!</p>";
    exit;
}

echo "<p style='color: green;'>✅ Plugin component found</p>";

// Test 1: [Scenario]
echo "<h2>Test 1: [Scenario Description]</h2>";
try {
    $result = $cws_core->component->method();
    echo "<p>✅ Test passed</p>";
    echo "<pre>" . print_r($result, true) . "</pre>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Test failed: " . $e->getMessage() . "</p>";
}

// Test 2: [Next Scenario]
// ... additional tests

?>
```

**Patterns:**
- WordPress environment loaded first: `require_once('wp-load.php')`
- Global plugin instance accessed: `global $cws_core`
- Existence checks: `if (!$cws_core || !isset($component))`
- HTML output with visual indicators: `✅` for pass, `❌` for fail
- Try-catch for exception handling
- `print_r()` for data inspection
- Direct method calls on plugin instance

## Mocking

**Framework:**
- No mocking framework in use
- Manual simulation of functions in test files
- WordPress functions called directly (no mocking)

**Patterns:**
```php
// Simulate/wrap WordPress functionality
function simulate_etchwp_get_post_meta($post) {
    $meta = get_post_meta($post->ID);
    $flat = array();

    if (!is_array($meta)) {
        return $flat;
    }

    foreach ($meta as $key => $value) {
        if (is_array($value)) {
            $flat[$key] = count($value) === 1 ? (string)$value[0] : '';
        } elseif (is_string($value)) {
            $flat[$key] = $value;
        } else {
            $flat[$key] = '';
        }
    }
    return $flat;
}
```

**What to Mock:**
- External APIs: Test API responses in isolation
- WordPress functions that may not exist in test environment
- Complex third-party integrations

**What NOT to Mock:**
- Core WordPress functions: Use real implementation
- Plugin's own classes: Instantiate and test directly
- Database operations: Use real WordPress meta functions
- Hook system: Use actual `add_action`, `do_action`

## Fixtures and Factories

**Test Data:**
- Hardcoded test data in each test file
- No factory classes
- Sample API responses in `test-*.php` files

**Example:**
```php
$query_args = array(
    'post_type' => 'cws_job',
    'posts_per_page' => 1,
    'post_status' => 'publish'
);

$test_job = array(
    'id' => '123',
    'title' => 'Senior Developer',
    'company' => 'Tech Corp',
    'location' => 'San Francisco'
);
```

**Location:**
- Test data embedded in test files
- No separate fixture files or factories
- Data created inline for each test scenario

## Coverage

**Requirements:**
- No coverage requirements enforced
- No coverage tools configured (no Xdebug or PHPUnit reports)

**Test Types Covered:**
- Virtual CPT functionality: 7 test files
- Meta query handling: 3 test files
- API integration: 2 test files
- REST compatibility: 1 test file
- Debug scripts: 5 exploratory files

## Test Types

**Unit Tests:**
- Minimal unit testing present
- Tests focus on integration scenarios
- Example: `test-virtual-cpt.php` tests virtual post creation

**Integration Tests:**
- Primary focus of test suite
- Test plugin components working together
- Test WordPress integration (hooks, queries, meta functions)
- Example: `test-etchwp-rest.php` tests REST API integration with virtual posts
- Example: `test-meta-query.php` tests meta query filtering with virtual posts

**E2E Tests:**
- Not used
- Manual browser testing of end-to-end flows required

## Common Patterns

**Async Testing:**
- Not applicable (synchronous PHP scripts)
- Caching tested synchronously: `get_transient()`, `set_transient()`

**Error Testing:**
```php
// Test failure scenarios
if ($query->have_posts()) {
    // ... success case
} else {
    echo "<p style='color: red;'>❌ No posts found!</p>";
}

// Test exception handling
try {
    $result = $cws_core->virtual_cpt->get_job_data($id);
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
```

**Verification Patterns:**
```php
// Check object properties
if (isset($post->meta_data)) {
    echo "<p style='color: green;'>✅ Has meta_data property</p>";
} else {
    echo "<p style='color: red;'>❌ No meta_data property</p>";
}

// Check array contents
$meta = get_post_meta($post->ID);
echo "<p><strong>Meta Count:</strong> " . count($meta) . "</p>";

// Inspect data structure
echo "<pre>" . print_r($data, true) . "</pre>";
```

## Running Tests

**Setup:**
1. Place test file in WordPress root directory
2. Ensure plugin is activated
3. Navigate to test file via browser (e.g., `http://localhost/wp-load.php` or direct execution)

**Examples:**
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/test-virtual-cpt.php`
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/test-meta-query-simple.php`
- `/Users/benest/Local Sites/etch2job/app/public/wp-content/plugins/cws-core/test-etchwp-rest.php`

**Interpretation:**
- Green checkmarks (✅) indicate passing assertions
- Red X marks (❌) indicate failures
- `print_r()` output shows data structure for inspection
- `echo` statements provide narrative of test flow

## Test File Sizes

- `test-meta-query-simple.php`: 144 lines
- `test-etchwp-rest.php`: 145 lines
- `test-etchwp-meta.php`: 154 lines
- `test-meta-query.php`: 129 lines
- `test-virtual-meta.php`: 93 lines
- `test-virtual-cpt.php`: 68 lines
- `test-unique-ids.php`: 69 lines
- `debug-virtual-posts-simple.php`: 186 lines
- `debug-query.php`: 246 lines (largest)
- `debug-template.php`: 152 lines
- `debug-meta.php`: 107 lines
- `debug-check.php`: 56 lines
- `debug-virtual-posts.php`: 85 lines

---

*Testing analysis: 2026-03-01*
