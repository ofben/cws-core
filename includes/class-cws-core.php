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
                $this->virtual_cpt = new CWS_Core_Virtual_CPT( $this );
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
                $this->virtual_cpt->init();
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
        $job_id = get_query_var( 'cws_job_id' );
        
        if ( ! empty( $job_id ) ) {
            // Set up the job page template
            add_filter( 'template_include', array( $this, 'load_job_template' ) );
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
}
