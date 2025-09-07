<?php
/**
 * Main CWS Core Plugin Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Main plugin class
 */
class CWS_Core {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private static $instance = null;

    /**
     * Admin class instance
     *
     * @var CWS_Core_Admin
     */
    public $admin = null;

    /**
     * API class instance
     *
     * @var CWS_Core_API
     */
    public $api = null;

    /**
     * Cache class instance
     *
     * @var CWS_Core_Cache
     */
    public $cache = null;

    /**
     * Public class instance
     *
     * @var CWS_Core_Public
     */
    public $public = null;

    /**
     * Virtual CPT class instance
     *
     * @var CWS_Core_Virtual_CPT
     */
    public $virtual_cpt = null;

    /**
     * Kadence compatibility class instance
     *
     * @var CWS_Core_Kadence_Compatibility
     */
    public $kadence_compatibility = null;

    /**
     * Get plugin instance
     *
     * @return CWS_Core
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Don't initialize components here - wait for init() method
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        try {
            // Check if classes exist before instantiating
            if ( class_exists( 'CWS_Core\\CWS_Core_API' ) ) {
                $this->api = new CWS_Core_API();
                $this->api->set_plugin( $this );
            }

            if ( class_exists( 'CWS_Core\\CWS_Core_Cache' ) ) {
                $this->cache = new CWS_Core_Cache();
                $this->cache->set_plugin( $this );
            }

            // Initialize admin class
            if ( is_admin() && class_exists( 'CWS_Core\\CWS_Core_Admin' ) ) {
                $this->admin = new CWS_Core_Admin( $this );
                error_log( 'CWS Core: Admin class instantiated successfully' );
            } else {
                error_log( 'CWS Core: Admin class not instantiated. is_admin: ' . (is_admin() ? 'true' : 'false') . ', class_exists: ' . (class_exists( 'CWS_Core\\CWS_Core_Admin' ) ? 'true' : 'false') );
            }

            // Initialize public class
            if ( class_exists( 'CWS_Core\\CWS_Core_Public' ) ) {
                $this->public = new CWS_Core_Public( $this );
            }

            // Initialize virtual CPT class
            if ( class_exists( 'CWS_Core\\CWS_Core_Virtual_CPT' ) ) {
                error_log( 'CWS Core: Creating virtual CPT instance' );
                $this->virtual_cpt = new CWS_Core_Virtual_CPT( $this );
                error_log( 'CWS Core: Virtual CPT instance created successfully' );
            } else {
                error_log( 'CWS Core: CWS_Core_Virtual_CPT class not found' );
            }

            // Initialize Kadence compatibility class
            if ( class_exists( 'CWS_Core\\CWS_Core_Kadence_Compatibility' ) ) {
                error_log( 'CWS Core: Creating Kadence compatibility instance' );
                $this->kadence_compatibility = new CWS_Core_Kadence_Compatibility( $this );
                error_log( 'CWS Core: Kadence compatibility instance created successfully' );
            } else {
                error_log( 'CWS Core: CWS_Core_Kadence_Compatibility class not found' );
            }
        } catch ( Exception $e ) {
            // Log error but don't crash the plugin
            error_log( 'CWS Core Component Error: ' . $e->getMessage() );
        }
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        try {
            // Load text domain for internationalization
            load_plugin_textdomain( 'cws-core', false, dirname( CWS_CORE_PLUGIN_BASENAME ) . '/languages' );

            // Initialize components first
            $this->init_components();

            // Register hooks
            $this->register_hooks();

            // Initialize components
            if ( $this->api && method_exists( $this->api, 'init' ) ) {
                $this->api->init();
            }
            if ( $this->cache && method_exists( $this->cache, 'init' ) ) {
                $this->cache->init();
            }
            if ( $this->public && method_exists( $this->public, 'init' ) ) {
                $this->public->init();
            }

            if ( is_admin() && $this->admin && method_exists( $this->admin, 'init' ) ) {
                $this->admin->init();
                error_log( 'CWS Core: Admin init() called successfully' );
            } else {
                $admin_exists = $this->admin ? 'true' : 'false';
                $method_exists = $this->admin ? (method_exists( $this->admin, 'init' ) ? 'true' : 'false') : 'false';
                error_log( 'CWS Core: Admin init() not called. is_admin: ' . (is_admin() ? 'true' : 'false') . ', admin exists: ' . $admin_exists . ', method_exists: ' . $method_exists );
            }

            // Initialize virtual CPT
            if ( $this->virtual_cpt && method_exists( $this->virtual_cpt, 'init' ) ) {
                error_log( 'CWS Core: Initializing virtual CPT' );
                $this->virtual_cpt->init();
                error_log( 'CWS Core: Virtual CPT initialization completed' );
            } else {
                error_log( 'CWS Core: Virtual CPT not available for init. virtual_cpt: ' . ($this->virtual_cpt ? 'exists' : 'null') . ', method_exists: ' . ($this->virtual_cpt ? (method_exists( $this->virtual_cpt, 'init' ) ? 'true' : 'false') : 'false') );
            }

            // Initialize Kadence compatibility
            if ( $this->kadence_compatibility && method_exists( $this->kadence_compatibility, 'init' ) ) {
                error_log( 'CWS Core: Initializing Kadence compatibility' );
                $this->kadence_compatibility->init();
                error_log( 'CWS Core: Kadence compatibility initialization completed' );
            } else {
                error_log( 'CWS Core: Kadence compatibility not available for init. kadence_compatibility: ' . ($this->kadence_compatibility ? 'exists' : 'null') . ', method_exists: ' . ($this->kadence_compatibility ? (method_exists( $this->kadence_compatibility, 'init' ) ? 'true' : 'false') : 'false') );
            }
        } catch ( Exception $e ) {
            // Log error but don't crash the plugin
            error_log( 'CWS Core Plugin Error: ' . $e->getMessage() );
        }
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Add rewrite rules for job URLs
        add_action( 'init', array( $this, 'add_rewrite_rules' ) );

        // Flush rewrite rules on activation
        add_action( 'cws_core_activate', array( $this, 'flush_rewrite_rules' ) );

        // Add query vars
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );

        // Handle job requests
        add_action( 'template_redirect', array( $this, 'handle_job_request' ) );
    }

    /**
     * Add custom rewrite rules for job URLs
     */
    public function add_rewrite_rules() {
        $job_slug = get_option( 'cws_core_job_slug', 'job' );
        
        // Add rewrite rule for job URLs: /{slug}/{job_id}/
        add_rewrite_rule(
            '^' . $job_slug . '/([0-9]+)/?$',
            'index.php?cws_job_id=$matches[1]',
            'top'
        );

        // Add rewrite rule for job URLs with title: /{slug}/{job_id}/{job_title}/
        add_rewrite_rule(
            '^' . $job_slug . '/([0-9]+)/([^/]+)/?$',
            'index.php?cws_job_id=$matches[1]&cws_job_title=$matches[2]',
            'top'
        );
    }

    /**
     * Add custom query variables
     *
     * @param array $vars Query variables.
     * @return array
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'cws_job_id';
        $vars[] = 'cws_job_title';
        return $vars;
    }

    /**
     * Handle job requests
     */
    public function handle_job_request() {
        $job_id = sanitize_text_field( get_query_var( 'cws_job_id' ) );
        
        if ( ! empty( $job_id ) ) {
            // Check if this job ID is already in our configured list
            $configured_job_ids = $this->get_configured_job_ids();
            
            if ( ! in_array( $job_id, $configured_job_ids ) ) {
                // This is a new job ID - fetch it dynamically and cache it
                $this->log( 'New job ID discovered: ' . $job_id . ' - fetching and caching', 'info' );
                
                if ( $this->fetch_and_cache_job( $job_id ) ) {
                    // Add to discovered job IDs list (not configured list yet)
                    $this->add_job_id_to_discovered_list( $job_id );
                    $this->log( 'Successfully cached and added job ID ' . $job_id . ' to discovered list', 'info' );
                } else {
                    $this->log( 'Failed to fetch job data for new job ID: ' . $job_id, 'error' );
                    // Still try to create virtual post in case it exists in cache
                }
            }
            
            // Create virtual post for this job
            if ( $this->virtual_cpt ) {
                $virtual_post = $this->virtual_cpt->create_virtual_job_post( $job_id );
                
                if ( $virtual_post ) {
                    // Store virtual post globally for meta retrieval
                    $GLOBALS['cws_virtual_posts'][$virtual_post->ID] = $virtual_post;
                    
                    // Set up the post for EtchWP
                    global $post;
                    $post = $virtual_post;
                    setup_postdata( $post );
                    
                    // Let EtchWP handle the template
                    return;
                }
            }
            
            // Log error if virtual post creation failed
            $this->log( 'Failed to create virtual post for job: ' . $job_id, 'error' );
        }
    }

    /**
     * Load job template
     *
     * @param string $template Template path.
     * @return string
     */
    public function load_job_template( $template ) {
        // Check if we have a job ID
        $job_id = get_query_var( 'cws_job_id' );
        
        if ( ! empty( $job_id ) ) {
            // Look for a custom job template in the theme
            $job_template = locate_template( array( 'job.php', 'single-job.php' ) );
            
            if ( $job_template ) {
                return $job_template;
            }
            
            // Fall back to default template
            return CWS_CORE_PLUGIN_DIR . 'templates/job.php';
        }
        
        return $template;
    }

    /**
     * Flush rewrite rules
     */
    public function flush_rewrite_rules() {
        flush_rewrite_rules();
    }

    /**
     * Get plugin option
     *
     * @param string $key Option key.
     * @param mixed  $default Default value.
     * @return mixed
     */
    public function get_option( $key, $default = null ) {
        return get_option( 'cws_core_' . $key, $default );
    }

    /**
     * Update plugin option
     *
     * @param string $key Option key.
     * @param mixed  $value Option value.
     * @return bool
     */
    public function update_option( $key, $value ) {
        return update_option( 'cws_core_' . $key, $value );
    }

    /**
     * Delete plugin option
     *
     * @param string $key Option key.
     * @return bool
     */
    public function delete_option( $key ) {
        return delete_option( 'cws_core_' . $key );
    }

    /**
     * Log debug message
     *
     * @param string $message Debug message.
     * @param string $level Log level.
     */
    public function log( $message, $level = 'info' ) {
        if ( $this->get_option( 'debug_mode', false ) ) {
            error_log( sprintf( '[CWS Core] [%s] %s', strtoupper( $level ), $message ) );
        }
    }

    /**
     * Test virtual post creation (temporary method for testing)
     */
    public function test_virtual_post(): void {
        if ( $this->virtual_cpt ) {
            $post = $this->virtual_cpt->debug_virtual_post( '22026695' );
            if ( $post ) {
                $this->log( 'Virtual post created: ' . $post->post_title, 'debug' );
            } else {
                $this->log( 'Failed to create virtual post', 'error' );
            }
        }
    }

    /**
     * Get configured job IDs from settings
     *
     * @return array Array of job IDs.
     */
    public function get_configured_job_ids() {
        $job_ids_string = $this->get_option( 'job_ids', '22026695' );
        $job_ids = array_map( 'trim', explode( ',', $job_ids_string ) );
        return array_filter( $job_ids, 'is_numeric' );
    }

    /**
     * Fetch and cache a single job by ID
     *
     * @param string $job_id The job ID to fetch.
     * @return bool True if successful, false otherwise.
     */
    public function fetch_and_cache_job( $job_id ) {
        if ( ! $this->api ) {
            $this->log( 'API not available for fetching job: ' . $job_id, 'error' );
            return false;
        }

        // Fetch job data from API
        $job_data = $this->api->get_job( $job_id );
        
        if ( false === $job_data ) {
            $this->log( 'Failed to fetch job data from API for job: ' . $job_id, 'error' );
            return false;
        }

        // Cache the job data
        if ( $this->cache ) {
            $cache_key = 'job_data_' . $job_id;
            $this->cache->set( $cache_key, $job_data );
            $this->log( 'Cached job data for job: ' . $job_id, 'info' );
        }

        return true;
    }

    /**
     * Add a job ID to the discovered job IDs list
     *
     * @param string $job_id The job ID to add.
     * @return bool True if successful, false otherwise.
     */
    public function add_job_id_to_discovered_list( $job_id ) {
        if ( ! $this->cache ) {
            $this->log( 'Cache not available for storing discovered job ID: ' . $job_id, 'error' );
            return false;
        }

        // Get current discovered job IDs
        $discovered_job_ids = $this->cache->get( 'discovered_job_ids' );
        if ( ! is_array( $discovered_job_ids ) ) {
            $discovered_job_ids = array();
        }

        // Add the new job ID if it's not already there
        if ( ! in_array( $job_id, $discovered_job_ids ) ) {
            $discovered_job_ids[] = $job_id;
            $this->cache->set( 'discovered_job_ids', $discovered_job_ids );
            $this->log( 'Added job ID ' . $job_id . ' to discovered list', 'info' );
            return true;
        }

        return true; // Already exists
    }

    /**
     * Add a job ID to the configured job IDs list
     *
     * @param string $job_id The job ID to add.
     * @return bool True if successful, false otherwise.
     */
    public function add_job_id_to_configured_list( $job_id ) {
        $current_job_ids = $this->get_configured_job_ids();
        
        // Check if job ID is already in the list
        if ( in_array( $job_id, $current_job_ids ) ) {
            return true; // Already exists
        }

        // Add the new job ID
        $current_job_ids[] = $job_id;
        
        // Update the option
        $job_ids_string = implode( ',', $current_job_ids );
        $result = $this->update_option( 'job_ids', $job_ids_string );
        
        if ( $result ) {
            $this->log( 'Added job ID ' . $job_id . ' to configured list. New list: ' . $job_ids_string, 'info' );
        } else {
            $this->log( 'Failed to update configured job IDs list', 'error' );
        }

        return $result;
    }

    /**
     * Remove a job ID from the configured job IDs list
     *
     * @param string $job_id The job ID to remove.
     * @return bool True if successful, false otherwise.
     */
    public function remove_job_id_from_configured_list( $job_id ) {
        $current_job_ids = $this->get_configured_job_ids();
        
        // Remove the job ID
        $updated_job_ids = array_diff( $current_job_ids, array( $job_id ) );
        
        // Update the option
        $job_ids_string = implode( ',', $updated_job_ids );
        $result = $this->update_option( 'job_ids', $job_ids_string );
        
        if ( $result ) {
            $this->log( 'Removed job ID ' . $job_id . ' from configured list. New list: ' . $job_ids_string, 'info' );
        } else {
            $this->log( 'Failed to update configured job IDs list', 'error' );
        }

        return $result;
    }

    /**
     * Get all cached job IDs (both configured and dynamically discovered)
     *
     * @return array Array of job IDs that have cached data.
     */
    public function get_cached_job_ids() {
        if ( ! $this->cache ) {
            return array();
        }

        $cached_job_ids = array();
        $configured_job_ids = $this->get_configured_job_ids();
        
        // Check which configured job IDs have cached data
        foreach ( $configured_job_ids as $job_id ) {
            $cache_key = 'job_data_' . $job_id;
            if ( $this->cache->get( $cache_key ) !== false ) {
                $cached_job_ids[] = $job_id;
            }
        }

        return $cached_job_ids;
    }
}
