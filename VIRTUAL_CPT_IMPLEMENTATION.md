# Virtual CPT Implementation Guide

## Overview
This guide walks through implementing a virtual Custom Post Type (CPT) for CWS Core that dynamically pulls job data from your API cache without storing it in the database. This allows EtchWP to query job data using standard WordPress queries while keeping data fresh from your API.

## Prerequisites
- CWS Core plugin is installed and working
- API connection is tested and functional
- EtchWP is installed and configured
- WordPress debug mode is enabled for testing

## API Architecture Analysis

Based on the API documentation, we now understand the complete structure:

### 1. Job Listing Endpoint ✅
- **Primary:** `GET /api/stjob?organization=1637`
- **Returns:** Paginated job list with `totalHits` count
- **Pagination:** `limit` and `offset` parameters supported
- **Total Jobs:** 1061 available jobs in the system

### 2. Job Data Structure ✅
- **Core Fields:** `id`, `title`, `description`, `company_name`, `entity_status`
- **Location:** `primary_city`, `primary_state`, `primary_country`, `primary_location`
- **Categorization:** `primary_category`, `department`, `industry`, `function`
- **URLs:** `url`, `seo_url`, `fndly_url`
- **Timestamps:** `open_date`, `update_date`

### 3. API Pagination ✅
- **Parameters:** `limit` (default: 10), `offset` (default: 1)
- **Strategy:** Can fetch 100 jobs per request for efficiency
- **Total Pages:** ~11 API calls to get all 1061 jobs

### 4. Job Status ✅
- **Field:** `entity_status` (e.g., "Open")
- **Filtering:** Can use `facet=entity_status:Open`
- **Recommendation:** Filter to show only open jobs

### 5. Advanced Features Available
- **Location Filtering:** `stateCity`, `countryStateCity`, `locationRadius`
- **Category Filtering:** `facet=primary_category:Faculty`
- **Search:** `searchText` parameter for keyword search
- **Specific Jobs:** `jobList` parameter for exact job IDs

## Implementation Strategy

### Recommended Approach: Hybrid Dynamic Discovery
1. **Phase 1:** Static job list (manual configuration)
2. **Phase 2:** Dynamic job discovery via API pagination
3. **Phase 3:** Advanced filtering and search
4. **Phase 4:** Performance optimization

## Implementation Steps

### Phase 1: Foundation Setup

#### Step 1.1: Create Virtual CPT Class
**File:** `includes/class-cws-core-virtual-cpt.php`

```php
<?php
declare(strict_types=1);

/**
 * CWS Core Virtual CPT Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Virtual CPT functionality class
 */
class CWS_Core_Virtual_CPT {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private CWS_Core $plugin;

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct( CWS_Core $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize virtual CPT
     */
    public function init(): void {
        // Register virtual post type
        add_action( 'init', array( $this, 'register_job_post_type' ) );
        
        // Intercept job queries
        add_action( 'pre_get_posts', array( $this, 'intercept_job_queries' ) );
        
        // Override post meta for virtual posts
        add_action( 'init', array( $this, 'override_post_meta' ) );
    }

    /**
     * Register virtual custom post type for jobs
     */
    public function register_job_post_type(): void {
        $labels = array(
            'name'               => esc_html__( 'Jobs', 'cws-core' ),
            'singular_name'      => esc_html__( 'Job', 'cws-core' ),
            'menu_name'          => esc_html__( 'Jobs', 'cws-core' ),
            'add_new'            => esc_html__( 'Add New', 'cws-core' ),
            'add_new_item'       => esc_html__( 'Add New Job', 'cws-core' ),
            'edit_item'          => esc_html__( 'Edit Job', 'cws-core' ),
            'new_item'           => esc_html__( 'New Job', 'cws-core' ),
            'view_item'          => esc_html__( 'View Job', 'cws-core' ),
            'search_items'       => esc_html__( 'Search Jobs', 'cws-core' ),
            'not_found'          => esc_html__( 'No jobs found', 'cws-core' ),
            'not_found_in_trash' => esc_html__( 'No jobs found in trash', 'cws-core' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => false, // Hide from admin since it's virtual
            'show_in_menu'        => false,
            'query_var'           => true,
            'rewrite'             => array( 'slug' => sanitize_title( get_option( 'cws_core_job_slug', 'job' ) ) ),
            'capability_type'     => 'post',
            'has_archive'         => true,
            'hierarchical'        => false,
            'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
            'show_in_rest'        => true,
        );

        register_post_type( 'cws_job', $args );
    }

    /**
     * Intercept WordPress queries for cws_job post type
     *
     * @param \WP_Query $query The query object.
     */
    public function intercept_job_queries( \WP_Query $query ): void {
        // Only intercept frontend queries for cws_job post type
        if ( ! is_admin() && $query->get( 'post_type' ) === 'cws_job' && ! $query->is_main_query() ) {
            add_filter( 'posts_pre_query', array( $this, 'replace_job_query' ), 10, 2 );
        }
    }

    /**
     * Replace job queries with virtual posts
     *
     * @param array|null $posts Posts array.
     * @param \WP_Query $query The query object.
     * @return array|null
     */
    public function replace_job_query( $posts, \WP_Query $query ) {
        // Remove the filter to prevent infinite loops
        remove_filter( 'posts_pre_query', array( $this, 'replace_job_query' ) );
        
        // Get job IDs from the query or use a default list
        $job_ids = $this->get_job_ids_from_query( $query );
        
        if ( empty( $job_ids ) ) {
            return array();
        }
        
        // Convert job data to virtual posts
        $virtual_posts = array();
        
        foreach ( $job_ids as $job_id ) {
            $virtual_post = $this->create_virtual_job_post( $job_id );
            if ( $virtual_post ) {
                $virtual_posts[] = $virtual_post;
            }
        }
        
        return $virtual_posts;
    }

    /**
     * Get job IDs from the query
     *
     * @param \WP_Query $query The query object.
     * @return array
     */
    private function get_job_ids_from_query( \WP_Query $query ): array {
        // Check if we're looking for a specific job
        $job_id = sanitize_text_field( get_query_var( 'cws_job_id' ) );
        if ( ! empty( $job_id ) ) {
            return array( $job_id );
        }
        
        // For archive/listing pages, get from cache or config
        $cached_job_list = $this->get_cached_job_list();
        
        if ( ! empty( $cached_job_list ) ) {
            return $cached_job_list;
        }
        
        // Fallback to configured job IDs
        return $this->get_configured_job_ids();
    }

    /**
     * Get cached list of available job IDs
     *
     * @return array|false
     */
    private function get_cached_job_list() {
        if ( $this->plugin && $this->plugin->cache ) {
            return $this->plugin->cache->get( 'available_job_ids' );
        }
        return false;
    }

    /**
     * Get configured job IDs from settings
     *
     * @return array
     */
    private function get_configured_job_ids(): array {
        $job_ids_string = sanitize_text_field( get_option( 'cws_core_job_ids', '22026695' ) );
        $job_ids = array_map( 'trim', explode( ',', $job_ids_string ) );
        return array_filter( $job_ids, 'is_numeric' ); // Only allow numeric IDs
    }

    /**
     * Create a virtual post from job data
     *
     * @param string $job_id The job ID.
     * @return \stdClass|false
     */
    private function create_virtual_job_post( string $job_id ) {
        if ( ! $this->plugin || ! $this->plugin->api ) {
            $this->log_error( 'Plugin or API not available for job: ' . $job_id );
            return false;
        }
        
        // Get job data from cache or API
        $job_data = $this->get_job_data( $job_id );
        
        if ( ! $job_data ) {
            $this->log_error( 'No job data found for job: ' . $job_id );
            return false;
        }
        
        $formatted_job = $this->plugin->api->format_job_data( $job_data );
        
        // Create virtual post object
        $post = new \stdClass();
        $post->ID = -1; // Negative ID to indicate virtual post
        $post->post_type = 'cws_job';
        $post->post_status = 'publish';
        $post->post_title = sanitize_text_field( $formatted_job['title'] );
        $post->post_content = wp_kses_post( $formatted_job['description'] );
        $post->post_excerpt = wp_trim_words( wp_kses_post( $formatted_job['description'] ), 55, '...' );
        $post->post_author = 1;
        $post->post_date = current_time( 'mysql' );
        $post->post_date_gmt = current_time( 'mysql', 1 );
        $post->post_modified = current_time( 'mysql' );
        $post->post_modified_gmt = current_time( 'mysql', 1 );
        $post->post_name = sanitize_title( $formatted_job['title'] . '-' . $job_id );
        $post->post_parent = 0;
        $post->guid = esc_url( home_url( '/' . sanitize_title( get_option( 'cws_core_job_slug', 'job' ) ) . '/' . $job_id . '/' ) );
        $post->comment_status = 'closed';
        $post->ping_status = 'closed';
        $post->comment_count = 0;
        $post->menu_order = 0;
        $post->post_category = array();
        $post->post_excerpt = '';
        $post->post_password = '';
        $post->to_ping = '';
        $post->pinged = '';
        $post->post_content_filtered = '';
        $post->post_mime_type = '';
        $post->filter = 'raw';
        
        // Add custom meta data based on actual API structure
        $post->cws_job_id = sanitize_text_field( $job_id );
        $post->cws_job_company = sanitize_text_field( $formatted_job['company_name'] );
        $post->cws_job_location = sanitize_text_field( $formatted_job['primary_city'] . ', ' . $formatted_job['primary_state'] );
        $post->cws_job_salary = sanitize_text_field( $formatted_job['salary'] );
        $post->cws_job_department = sanitize_text_field( $formatted_job['department'] );
        $post->cws_job_category = sanitize_text_field( $formatted_job['primary_category'] );
        $post->cws_job_status = sanitize_text_field( $formatted_job['entity_status'] );
        $post->cws_job_type = sanitize_text_field( $formatted_job['employment_type'] );
        $post->cws_job_url = esc_url_raw( $formatted_job['url'] );
        $post->cws_job_seo_url = esc_url_raw( $formatted_job['seo_url'] );
        $post->cws_job_open_date = sanitize_text_field( $formatted_job['open_date'] );
        $post->cws_job_update_date = sanitize_text_field( $formatted_job['update_date'] );
        $post->cws_job_industry = sanitize_text_field( $formatted_job['industry'] );
        $post->cws_job_function = sanitize_text_field( $formatted_job['function'] );
        $post->cws_job_raw_data = $formatted_job['raw_data'];
        
        return $post;
    }

    /**
     * Get job data from cache or API
     *
     * @param string $job_id The job ID.
     * @return array|false
     */
    private function get_job_data( string $job_id ) {
        // Try cache first
        if ( $this->plugin && $this->plugin->cache ) {
            $cached_data = $this->plugin->cache->get( 'job_data_' . $job_id );
            if ( $cached_data ) {
                return $cached_data;
            }
        }
        
        // Fallback to API
        if ( $this->plugin && $this->plugin->api ) {
            return $this->plugin->api->get_job( $job_id );
        }
        
        return false;
    }

    /**
     * Override WordPress post meta functions for virtual posts
     */
    public function override_post_meta(): void {
        add_filter( 'get_post_metadata', array( $this, 'get_virtual_post_meta' ), 10, 4 );
        add_filter( 'get_post_meta', array( $this, 'get_virtual_post_meta' ), 10, 4 );
    }

    /**
     * Get virtual post meta data
     *
     * @param mixed  $value    The value to return.
     * @param int    $post_id  Post ID.
     * @param string $key      Meta key.
     * @param bool   $single   Whether to return a single value.
     * @return mixed
     */
    public function get_virtual_post_meta( $value, int $post_id, string $key, bool $single ) {
        global $post;
        
        // Only handle virtual posts (negative IDs)
        if ( $post && $post->ID < 0 && $post->post_type === 'cws_job' ) {
            $meta_key = 'cws_job_' . str_replace( 'cws_job_', '', $key );
            
            if ( isset( $post->$meta_key ) ) {
                return $single ? $post->$meta_key : array( $post->$meta_key );
            }
        }
        
        return $value;
    }

    /**
     * Log error using WordPress debug logging
     *
     * @param string $message Error message.
     */
    private function log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWS Core Virtual CPT: ' . $message );
        }
    }
}
```

#### Step 1.2: Update Main Plugin Class
**File:** `includes/class-cws-core.php`

Add to the class properties:
```php
public ?CWS_Core_Virtual_CPT $virtual_cpt = null;
```

Add to `init_components()` method:
```php
// Initialize virtual CPT class
if ( class_exists( 'CWS_Core\\CWS_Core_Virtual_CPT' ) ) {
    $this->virtual_cpt = new CWS_Core_Virtual_CPT( $this );
}
```

Add to `init()` method:
```php
if ( $this->virtual_cpt && method_exists( $this->virtual_cpt, 'init' ) ) {
    $this->virtual_cpt->init();
}
```

#### Step 1.3: Update Main Plugin File
**File:** `cws-core.php`

Add this line with the other require_once statements:
```php
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-virtual-cpt.php';
```

### Phase 2: Testing Foundation

#### Step 2.1: Test Virtual CPT Registration
1. **Activate the plugin**
2. **Check WordPress admin** - Jobs should NOT appear in the admin menu (since `show_ui` is false)
3. **Check rewrite rules** - Visit `/job/22026695/` should not 404

#### Step 2.2: Test Basic Virtual Post Creation
Add this temporary debug code to test:

```php
// Add to your main plugin class temporarily
public function test_virtual_post(): void {
    if ( $this->virtual_cpt ) {
        $post = $this->virtual_cpt->create_virtual_job_post( '22026695' );
        if ( $post ) {
            $this->log_debug( 'Virtual post created: ' . $post->post_title );
        } else {
            $this->log_error( 'Failed to create virtual post' );
        }
    }
}

/**
 * Log debug message using WordPress debug logging
 *
 * @param string $message Debug message.
 */
private function log_debug( string $message ): void {
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'CWS Core Debug: ' . $message );
    }
}
```

### Phase 3: Admin Settings

#### Step 3.1: Add Job IDs Setting
**File:** `includes/class-cws-core-admin.php`

Add to `register_settings()` method:
```php
register_setting(
    'cws_core_settings',
    'cws_core_job_ids',
    array(
        'type'              => 'string',
        'sanitize_callback' => array( $this, 'sanitize_job_ids' ),
        'default'           => '22026695',
    )
);
```

Add to `add_settings_field()` calls:
```php
add_settings_field(
    'cws_core_job_ids',
    esc_html__( 'Job IDs', 'cws-core' ),
    array( $this, 'render_job_ids_field' ),
    'cws-core-settings',
    'cws_core_url_section'
);
```

Add the render method:
```php
/**
 * Render job IDs field
 */
public function render_job_ids_field(): void {
    $value = sanitize_textarea_field( get_option( 'cws_core_job_ids', '22026695' ) );
    ?>
    <textarea 
        id="cws_core_job_ids" 
        name="cws_core_job_ids" 
        rows="3" 
        cols="50"
        placeholder="22026695, 22026696, 22026697"
    ><?php echo esc_textarea( $value ); ?></textarea>
    <p class="description">
        <?php esc_html_e( 'Enter job IDs separated by commas. These will be available for EtchWP queries.', 'cws-core' ); ?>
    </p>
    <?php
}

/**
 * Sanitize job IDs
 *
 * @param string $value The input value.
 * @return string
 */
public function sanitize_job_ids( string $value ): string {
    $job_ids = array_map( 'trim', explode( ',', $value ) );
    $job_ids = array_filter( $job_ids, 'is_numeric' ); // Only allow numeric IDs
    return implode( ',', $job_ids );
}
```

### Phase 4: URL Handling

#### Step 4.1: Update URL Handling
**File:** `includes/class-cws-core.php`

Update the `handle_job_request()` method:
```php
/**
 * Handle job request
 */
public function handle_job_request(): void {
    $job_id = sanitize_text_field( get_query_var( 'cws_job_id' ) );
    
    if ( ! empty( $job_id ) ) {
        // Create virtual post for this job
        if ( $this->virtual_cpt ) {
            $virtual_post = $this->virtual_cpt->create_virtual_job_post( $job_id );
            
            if ( $virtual_post ) {
                // Set up the post for EtchWP
                global $post;
                $post = $virtual_post;
                setup_postdata( $post );
                
                // Let EtchWP handle the template
                return;
            }
        }
        
        // Log error if virtual post creation failed
        $this->log_error( 'Failed to create virtual post for job: ' . $job_id );
    }
}
```

### Phase 5: Testing Virtual Posts

#### Step 5.1: Test Single Job Page
1. **Visit** `/job/22026695/`
2. **Check browser console** for any errors
3. **Verify** the page loads (even if template is basic)

#### Step 5.2: Test Job Archive
1. **Visit** `/job/`
2. **Check** if it shows a list of jobs
3. **Verify** no 404 errors

### Phase 6: EtchWP Integration

#### Step 6.1: Create EtchWP Template
1. **Go to EtchWP Templates**
2. **Create new template** named "Job Page Template"
3. **Add dynamic content** using:
   - `{this.title}` for job title
   - `{this.meta.cws_job_company}` for company
   - `{this.meta.cws_job_location}` for location
   - `{this.content}` for job description

#### Step 6.2: Test EtchWP Query
Create a test page in EtchWP with this query:
```php
$query_args = array(
    'post_type' => 'cws_job',
    'posts_per_page' => 1,
    'meta_query' => array(
        array(
            'key' => 'cws_job_id',
            'value' => '22026695',
            'compare' => '='
        )
    )
);
```

### Phase 7: Dynamic Job Discovery

#### Step 7.1: API Job List Collection
**File:** `includes/class-cws-core-job-discovery.php`

```php
<?php
declare(strict_types=1);

/**
 * CWS Core Job Discovery Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Job discovery and synchronization class
 */
class CWS_Core_Job_Discovery {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private CWS_Core $plugin;

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct( CWS_Core $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize job discovery
     */
    public function init(): void {
        // Schedule background sync
        add_action( 'init', array( $this, 'schedule_sync' ) );
        
        // Add sync action
        add_action( 'cws_core_sync_jobs', array( $this, 'sync_all_jobs' ) );
        
        // Add admin action for manual sync
        add_action( 'wp_ajax_cws_core_manual_sync', array( $this, 'manual_sync' ) );
    }

    /**
     * Schedule background job sync
     */
    public function schedule_sync(): void {
        if ( ! wp_next_scheduled( 'cws_core_sync_jobs' ) ) {
            wp_schedule_event( time(), 'hourly', 'cws_core_sync_jobs' );
        }
    }

    /**
     * Sync all jobs from API
     */
    public function sync_all_jobs(): void {
        $organization_id = sanitize_text_field( get_option( 'cws_core_organization_id' ) );
        
        if ( empty( $organization_id ) ) {
            $this->log_error( 'No organization ID configured for job sync' );
            return;
        }

        // Get total job count
        $total_jobs = $this->get_total_job_count( $organization_id );
        
        if ( $total_jobs === false ) {
            $this->log_error( 'Failed to get total job count' );
            return;
        }

        // Collect all job IDs
        $job_ids = $this->collect_all_job_ids( $organization_id, $total_jobs );
        
        if ( empty( $job_ids ) ) {
            $this->log_error( 'No job IDs collected' );
            return;
        }

        // Cache the job list
        set_transient( 'cws_available_job_ids', $job_ids, HOUR_IN_SECONDS );
        
        // Cache individual job data
        $this->cache_job_data( $job_ids );
        
        $this->log_debug( 'Synced ' . count( $job_ids ) . ' jobs' );
    }

    /**
     * Get total job count from API
     *
     * @param string $organization_id Organization ID.
     * @return int|false
     */
    private function get_total_job_count( string $organization_id ) {
        $api_url = add_query_arg( array(
            'organization' => urlencode( $organization_id ),
            'limit' => 1,
        ), 'https://jobsapi-internal.m-cloud.io/api/stjob' );

        $response = wp_remote_get( esc_url_raw( $api_url ) );
        
        if ( is_wp_error( $response ) ) {
            $this->log_error( 'API request failed: ' . $response->get_error_message() );
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['totalHits'] ) ) {
            $this->log_error( 'Invalid API response structure' );
            return false;
        }

        return (int) $data['totalHits'];
    }

    /**
     * Collect all job IDs from API
     *
     * @param string $organization_id Organization ID.
     * @param int    $total_jobs      Total number of jobs.
     * @return array
     */
    private function collect_all_job_ids( string $organization_id, int $total_jobs ): array {
        $job_ids = array();
        $limit = 100; // Get 100 jobs per request
        $total_pages = ceil( $total_jobs / $limit );

        for ( $page = 1; $page <= $total_pages; $page++ ) {
            $offset = ( $page - 1 ) * $limit + 1;
            
            $api_url = add_query_arg( array(
                'organization' => urlencode( $organization_id ),
                'limit' => $limit,
                'offset' => $offset,
            ), 'https://jobsapi-internal.m-cloud.io/api/stjob' );

            $response = wp_remote_get( esc_url_raw( $api_url ) );
            
            if ( is_wp_error( $response ) ) {
                $this->log_error( 'Failed to fetch page ' . $page . ': ' . $response->get_error_message() );
                continue;
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( ! isset( $data['queryResult'] ) ) {
                $this->log_error( 'Invalid response for page ' . $page );
                continue;
            }

            foreach ( $data['queryResult'] as $job ) {
                if ( isset( $job['id'] ) && is_numeric( $job['id'] ) ) {
                    $job_ids[] = sanitize_text_field( $job['id'] );
                }
            }

            // Add delay to avoid overwhelming the API
            usleep( 100000 ); // 0.1 second delay
        }

        return $job_ids;
    }

    /**
     * Cache individual job data
     *
     * @param array $job_ids Array of job IDs.
     */
    private function cache_job_data( array $job_ids ): void {
        foreach ( $job_ids as $job_id ) {
            // Check if we already have recent data
            $cached_data = get_transient( 'cws_job_data_' . $job_id );
            
            if ( $cached_data === false ) {
                // Fetch fresh data
                if ( $this->plugin && $this->plugin->api ) {
                    $job_data = $this->plugin->api->get_job( $job_id );
                    
                    if ( $job_data ) {
                        set_transient( 'cws_job_data_' . $job_id, $job_data, 30 * MINUTE_IN_SECONDS );
                    }
                }
            }
        }
    }

    /**
     * Manual sync via AJAX
     */
    public function manual_sync(): void {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cws_core_manual_sync' ) ) {
            wp_die( esc_html__( 'Security check failed', 'cws-core' ) );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Insufficient permissions', 'cws-core' ) );
        }

        // Run sync
        $this->sync_all_jobs();

        // Return success
        wp_send_json_success( array(
            'message' => esc_html__( 'Job sync completed successfully', 'cws-core' ),
            'job_count' => count( get_transient( 'cws_available_job_ids' ) ?: array() ),
        ) );
    }

    /**
     * Log error using WordPress debug logging
     *
     * @param string $message Error message.
     */
    private function log_error( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWS Core Job Discovery: ' . $message );
        }
    }

    /**
     * Log debug message using WordPress debug logging
     *
     * @param string $message Debug message.
     */
    private function log_debug( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWS Core Job Discovery Debug: ' . $message );
        }
    }
}
```

#### Step 7.2: Update API Class for Job Discovery
**File:** `includes/class-cws-core-api.php`

Add these methods:

```php
/**
 * Get job data with proper field mapping
 *
 * @param array $job_data Raw job data from API.
 * @return array
 */
public function format_job_data( array $job_data ): array {
    return array(
        'id' => sanitize_text_field( $job_data['id'] ?? '' ),
        'title' => sanitize_text_field( $job_data['title'] ?? '' ),
        'description' => wp_kses_post( $job_data['description'] ?? '' ),
        'company_name' => sanitize_text_field( $job_data['company_name'] ?? '' ),
        'entity_status' => sanitize_text_field( $job_data['entity_status'] ?? '' ),
        'primary_city' => sanitize_text_field( $job_data['primary_city'] ?? '' ),
        'primary_state' => sanitize_text_field( $job_data['primary_state'] ?? '' ),
        'primary_country' => sanitize_text_field( $job_data['primary_country'] ?? '' ),
        'primary_location' => is_array( $job_data['primary_location'] ?? null ) ? $job_data['primary_location'] : array(),
        'primary_category' => sanitize_text_field( $job_data['primary_category'] ?? '' ),
        'department' => sanitize_text_field( $job_data['department'] ?? '' ),
        'industry' => sanitize_text_field( $job_data['industry'] ?? '' ),
        'function' => sanitize_text_field( $job_data['function'] ?? '' ),
        'salary' => sanitize_text_field( $job_data['salary'] ?? '' ),
        'employment_type' => sanitize_text_field( $job_data['employment_type'] ?? '' ),
        'url' => esc_url_raw( $job_data['url'] ?? '' ),
        'seo_url' => esc_url_raw( $job_data['seo_url'] ?? '' ),
        'open_date' => sanitize_text_field( $job_data['open_date'] ?? '' ),
        'update_date' => sanitize_text_field( $job_data['update_date'] ?? '' ),
        'raw_data' => $job_data,
    );
}

/**
 * Get filtered jobs by status
 *
 * @param string $status Job status to filter by.
 * @return array|false
 */
public function get_jobs_by_status( string $status = 'Open' ) {
    $organization_id = sanitize_text_field( get_option( 'cws_core_organization_id' ) );
    
    $api_url = add_query_arg( array(
        'organization' => urlencode( $organization_id ),
        'facet' => 'entity_status:' . urlencode( $status ),
        'limit' => 100,
    ), 'https://jobsapi-internal.m-cloud.io/api/stjob' );

    return $this->make_api_request( esc_url_raw( $api_url ) );
}

/**
 * Get jobs by category
 *
 * @param string $category Job category to filter by.
 * @return array|false
 */
public function get_jobs_by_category( string $category ) {
    $organization_id = sanitize_text_field( get_option( 'cws_core_organization_id' ) );
    
    $api_url = add_query_arg( array(
        'organization' => urlencode( $organization_id ),
        'facet' => 'primary_category:' . urlencode( $category ),
        'limit' => 100,
    ), 'https://jobsapi-internal.m-cloud.io/api/stjob' );

    return $this->make_api_request( esc_url_raw( $api_url ) );
}
```

### Phase 8: Advanced Features

#### Step 8.1: Job List Management
Based on the API documentation, implement:

**Option A: Static List (Phase 1)**
- Use configured job IDs from admin settings
- Manual management for testing

**Option B: Dynamic Discovery (Phase 2)**
- Use API pagination to discover all available jobs
- Cache job lists and auto-update hourly
- Filter by `entity_status:Open` for active jobs

**Option C: Hybrid Approach (Recommended)**
- Use dynamic discovery as primary source
- Allow manual override in admin for specific jobs
- Fallback to configured list if API fails

#### Step 7.2: Pagination Support
If your API supports pagination:
1. **Implement pagination logic** in virtual CPT
2. **Handle offset/limit** parameters
3. **Cache paginated results**

#### Step 7.3: Job Status Filtering
If jobs have status fields:
1. **Add status filtering** to queries
2. **Only show active jobs** by default
3. **Add admin option** to show inactive jobs

### Phase 8: Performance Optimization

#### Step 8.1: Caching Strategy
1. **Cache job lists** for 1 hour
2. **Cache individual jobs** for 30 minutes
3. **Implement cache warming** for popular jobs

#### Step 8.2: Error Handling
1. **Graceful fallbacks** when API is down
2. **User-friendly error messages**
3. **Admin notifications** for API issues

### Phase 9: Final Testing

#### Step 9.1: Complete Integration Test
1. **Test single job pages** with EtchWP templates
2. **Test job archives** with loops
3. **Test search functionality**
4. **Test pagination** (if implemented)

#### Step 9.2: Performance Test
1. **Load test** with multiple job pages
2. **Monitor cache hit rates**
3. **Check API response times**

## Troubleshooting

### Common Issues

1. **404 Errors on Job Pages**
   - Check rewrite rules are flushed
   - Verify job IDs are configured
   - Check API connection

2. **Virtual Posts Not Loading**
   - Check cache is working
   - Verify API responses
   - Check for PHP errors

3. **EtchWP Not Recognizing Posts**
   - Verify post type registration
   - Check meta data is accessible
   - Test with simple queries first

### Debug Tools

Add this debug method to your virtual CPT class:
```php
/**
 * Debug virtual post creation
 *
 * @param string $job_id The job ID to debug.
 * @return \stdClass|false
 */
public function debug_virtual_post( string $job_id ) {
    $post = $this->create_virtual_job_post( $job_id );
    if ( $post ) {
        $this->log_debug( 'Virtual post debug: ' . print_r( $post, true ) );
        return $post;
    }
    $this->log_error( 'Failed to create virtual post for job: ' . $job_id );
    return false;
}
```

## Next Steps

After completing this implementation:

1. **Remove debug code**
2. **Add comprehensive error logging**
3. **Implement job list API integration** (based on your API answers)
4. **Add admin interface** for managing job lists
5. **Optimize caching strategy**
6. **Add monitoring and analytics**

## Questions for API Integration

Please provide answers to these questions to help design the job list management:

1. **Do you have an endpoint that returns all available job IDs?**
2. **What's the structure of your job listing endpoint?**
3. **Do jobs have status fields (active/inactive)?**
4. **Does your API support pagination for job lists?**
5. **How often do job listings change?**
6. **Are there any authentication requirements for job lists?**
