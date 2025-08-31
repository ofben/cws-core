<?php
/**
 * CWS Core Admin Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Admin functionality class
 */
class CWS_Core_Admin {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private $plugin;

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize the admin class
     */
    public function init() {
        error_log( 'CWS Core: Admin init() method called' );
        // Register hooks
        $this->register_hooks();

        // Enqueue admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        error_log( 'CWS Core: Admin init() method completed' );
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        error_log( 'CWS Core: Registering admin hooks' );
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Add settings link to plugins page
        add_filter( 'plugin_action_links_' . CWS_CORE_PLUGIN_BASENAME, array( $this, 'add_settings_link' ) );
        
        // Add AJAX handlers
        add_action( 'wp_ajax_cws_core_flush_rules', array( $this, 'flush_rewrite_rules_ajax' ) );
        
        error_log( 'CWS Core: Admin hooks registered successfully' );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        error_log( 'CWS Core: Adding admin menu' );
        add_options_page(
            __( 'CWS Core Settings', 'cws-core' ),
            __( 'CWS Core', 'cws-core' ),
            'manage_options',
            'cws-core-settings',
            array( $this, 'render_settings_page' )
        );
        error_log( 'CWS Core: Admin menu added successfully' );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // Register settings group
        register_setting(
            'cws_core_settings',
            'cws_core_api_endpoint',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_api_endpoint' ),
                'default'           => 'https://jobsapi-internal.m-cloud.io/api/stjob',
            )
        );

        register_setting(
            'cws_core_settings',
            'cws_core_organization_id',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_organization_id' ),
                'default'           => '',
            )
        );

        register_setting(
            'cws_core_settings',
            'cws_core_cache_duration',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_cache_duration' ),
                'default'           => 3600,
            )
        );

        register_setting(
            'cws_core_settings',
            'cws_core_job_slug',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_job_slug' ),
                'default'           => 'job',
            )
        );

        register_setting(
            'cws_core_settings',
            'cws_core_debug_mode',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => array( $this, 'sanitize_debug_mode' ),
                'default'           => false,
            )
        );

        register_setting(
            'cws_core_settings',
            'cws_core_job_ids',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_job_ids' ),
                'default'           => '22026695',
            )
        );

        // Add settings sections
        add_settings_section(
            'cws_core_api_section',
            __( 'API Configuration', 'cws-core' ),
            array( $this, 'render_api_section' ),
            'cws-core-settings'
        );

        add_settings_section(
            'cws_core_url_section',
            __( 'URL Configuration', 'cws-core' ),
            array( $this, 'render_url_section' ),
            'cws-core-settings'
        );

        add_settings_section(
            'cws_core_cache_section',
            __( 'Cache Settings', 'cws-core' ),
            array( $this, 'render_cache_section' ),
            'cws-core-settings'
        );

        add_settings_section(
            'cws_core_debug_section',
            __( 'Debug Settings', 'cws-core' ),
            array( $this, 'render_debug_section' ),
            'cws-core-settings'
        );

        // Add settings fields
        add_settings_field(
            'cws_core_api_endpoint',
            __( 'API Endpoint', 'cws-core' ),
            array( $this, 'render_api_endpoint_field' ),
            'cws-core-settings',
            'cws_core_api_section'
        );

        add_settings_field(
            'cws_core_organization_id',
            __( 'Organization ID', 'cws-core' ),
            array( $this, 'render_organization_id_field' ),
            'cws-core-settings',
            'cws_core_api_section'
        );

        add_settings_field(
            'cws_core_job_slug',
            __( 'Job URL Slug', 'cws-core' ),
            array( $this, 'render_job_slug_field' ),
            'cws-core-settings',
            'cws_core_url_section'
        );

        add_settings_field(
            'cws_core_job_ids',
            __( 'Job IDs', 'cws-core' ),
            array( $this, 'render_job_ids_field' ),
            'cws-core-settings',
            'cws_core_url_section'
        );

        add_settings_field(
            'cws_core_cache_duration',
            __( 'Cache Duration', 'cws-core' ),
            array( $this, 'render_cache_duration_field' ),
            'cws-core-settings',
            'cws_core_cache_section'
        );

        add_settings_field(
            'cws_core_debug_mode',
            __( 'Debug Mode', 'cws-core' ),
            array( $this, 'render_debug_mode_field' ),
            'cws-core-settings',
            'cws_core_debug_section'
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook_suffix Current admin page.
     */
    public function enqueue_scripts( $hook_suffix ) {
        if ( 'settings_page_cws-core-settings' !== $hook_suffix ) {
            return;
        }

        wp_enqueue_script(
            'cws-core-admin',
            CWS_CORE_PLUGIN_URL . 'admin/js/admin.js',
            array( 'jquery' ),
            CWS_CORE_VERSION,
            true
        );

        wp_enqueue_style(
            'cws-core-admin',
            CWS_CORE_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            CWS_CORE_VERSION
        );

        // Localize script
        wp_localize_script(
            'cws-core-admin',
            'cws_core_admin',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonces'   => array(
                    'test_api'     => wp_create_nonce( 'cws_core_test_api' ),
                    'clear_cache'  => wp_create_nonce( 'cws_core_clear_cache' ),
                    'cache_stats'  => wp_create_nonce( 'cws_core_get_cache_stats' ),
                    'flush_rules'  => wp_create_nonce( 'cws_core_flush_rules' ),
                ),
                'strings'  => array(
                    'testing_connection' => __( 'Testing connection...', 'cws-core' ),
                    'connection_success' => __( 'Connection successful!', 'cws-core' ),
                    'connection_failed'  => __( 'Connection failed!', 'cws-core' ),
                    'clearing_cache'     => __( 'Clearing cache...', 'cws-core' ),
                    'cache_cleared'      => __( 'Cache cleared successfully!', 'cws-core' ),
                ),
            )
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'cws-core' ) );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="cws-core-admin-container">
                <div class="cws-core-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields( 'cws_core_settings' );
                        do_settings_sections( 'cws-core-settings' );
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="cws-core-admin-sidebar">
                    <div class="cws-core-admin-box">
                        <h3><?php esc_html_e( 'API Connection Test', 'cws-core' ); ?></h3>
                        <p><?php esc_html_e( 'Test your API connection to ensure everything is working correctly.', 'cws-core' ); ?></p>
                        <button type="button" class="button button-secondary" id="cws-core-test-api">
                            <?php esc_html_e( 'Test Connection', 'cws-core' ); ?>
                        </button>
                        <div id="cws-core-test-result"></div>
                    </div>
                    
                    <div class="cws-core-admin-box">
                        <h3><?php esc_html_e( 'Cache Management', 'cws-core' ); ?></h3>
                        <p><?php esc_html_e( 'Manage cached data and view cache statistics.', 'cws-core' ); ?></p>
                        <button type="button" class="button button-secondary" id="cws-core-clear-cache">
                            <?php esc_html_e( 'Clear Cache', 'cws-core' ); ?>
                        </button>
                        <button type="button" class="button button-secondary" id="cws-core-get-cache-stats">
                            <?php esc_html_e( 'View Stats', 'cws-core' ); ?>
                        </button>
                        <div id="cws-core-cache-result"></div>
                    </div>
                    
                    <div class="cws-core-admin-box">
                        <h3><?php esc_html_e( 'URL Management', 'cws-core' ); ?></h3>
                        <p><?php esc_html_e( 'Flush rewrite rules to ensure job URLs work correctly.', 'cws-core' ); ?></p>
                        <button type="button" class="button button-secondary" id="cws-core-flush-rules">
                            <?php esc_html_e( 'Flush Rewrite Rules', 'cws-core' ); ?>
                        </button>
                        <div id="cws-core-rules-result"></div>
                    </div>
                    
                    <div class="cws-core-admin-box">
                        <h3><?php esc_html_e( 'URL Examples', 'cws-core' ); ?></h3>
                        <p><?php esc_html_e( 'Your job URLs will follow this pattern:', 'cws-core' ); ?></p>
                        <?php 
                        $job_slug = get_option( 'cws_core_job_slug', 'job' );
                        ?>
                        <code><?php echo esc_url( home_url( '/' . $job_slug . '/22026695/' ) ); ?></code>
                        <br><br>
                        <code><?php echo esc_url( home_url( '/' . $job_slug . '/22026695/job-title-slug/' ) ); ?></code>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render API section
     */
    public function render_api_section() {
        echo '<p>' . esc_html__( 'Configure your API endpoint and organization ID.', 'cws-core' ) . '</p>';
    }

    /**
     * Render URL section
     */
    public function render_url_section() {
        echo '<p>' . esc_html__( 'Configure how job URLs are structured on your site.', 'cws-core' ) . '</p>';
    }

    /**
     * Render cache section
     */
    public function render_cache_section() {
        echo '<p>' . esc_html__( 'Configure caching settings for better performance.', 'cws-core' ) . '</p>';
    }

    /**
     * Render debug section
     */
    public function render_debug_section() {
        echo '<p>' . esc_html__( 'Enable debug mode for troubleshooting.', 'cws-core' ) . '</p>';
    }

    /**
     * Render API endpoint field
     */
    public function render_api_endpoint_field() {
        $value = get_option( 'cws_core_api_endpoint', 'https://jobsapi-internal.m-cloud.io/api/stjob' );
        ?>
        <input type="url" 
               id="cws_core_api_endpoint" 
               name="cws_core_api_endpoint" 
               value="<?php echo esc_attr( $value ); ?>" 
               class="regular-text" 
               required />
        <p class="description">
            <?php esc_html_e( 'The API endpoint URL (e.g., https://jobsapi-internal.m-cloud.io/api/stjob)', 'cws-core' ); ?>
        </p>
        <?php
    }

    /**
     * Render organization ID field
     */
    public function render_organization_id_field() {
        $value = get_option( 'cws_core_organization_id', '' );
        ?>
        <input type="text" 
               id="cws_core_organization_id" 
               name="cws_core_organization_id" 
               value="<?php echo esc_attr( $value ); ?>" 
               class="regular-text" 
               required />
        <p class="description">
            <?php esc_html_e( 'Your organization ID (e.g., 1637)', 'cws-core' ); ?>
        </p>
        <?php
    }

    /**
     * Render job slug field
     */
    public function render_job_slug_field() {
        $value = get_option( 'cws_core_job_slug', 'job' );
        ?>
        <input type="text" 
               id="cws_core_job_slug" 
               name="cws_core_job_slug" 
               value="<?php echo esc_attr( $value ); ?>" 
               class="regular-text" 
               required />
        <p class="description">
            <?php esc_html_e( 'The base slug for job URLs (e.g., "job" will create URLs like /job/123/)', 'cws-core' ); ?>
        </p>
        <?php
    }

    /**
     * Render job IDs field
     */
    public function render_job_ids_field() {
        $value = get_option( 'cws_core_job_ids', '22026695' );
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
     * Render cache duration field
     */
    public function render_cache_duration_field() {
        $value = get_option( 'cws_core_cache_duration', 3600 );
        ?>
        <select id="cws_core_cache_duration" name="cws_core_cache_duration">
            <option value="900" <?php selected( $value, 900 ); ?>><?php esc_html_e( '15 minutes', 'cws-core' ); ?></option>
            <option value="1800" <?php selected( $value, 1800 ); ?>><?php esc_html_e( '30 minutes', 'cws-core' ); ?></option>
            <option value="3600" <?php selected( $value, 3600 ); ?>><?php esc_html_e( '1 hour', 'cws-core' ); ?></option>
            <option value="7200" <?php selected( $value, 7200 ); ?>><?php esc_html_e( '2 hours', 'cws-core' ); ?></option>
            <option value="14400" <?php selected( $value, 14400 ); ?>><?php esc_html_e( '4 hours', 'cws-core' ); ?></option>
            <option value="28800" <?php selected( $value, 28800 ); ?>><?php esc_html_e( '8 hours', 'cws-core' ); ?></option>
            <option value="86400" <?php selected( $value, 86400 ); ?>><?php esc_html_e( '24 hours', 'cws-core' ); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e( 'How long to cache API responses', 'cws-core' ); ?>
        </p>
        <?php
    }

    /**
     * Render debug mode field
     */
    public function render_debug_mode_field() {
        $value = get_option( 'cws_core_debug_mode', false );
        ?>
        <label>
            <input type="checkbox" 
                   id="cws_core_debug_mode" 
                   name="cws_core_debug_mode" 
                   value="1" 
                   <?php checked( $value, true ); ?> />
            <?php esc_html_e( 'Enable debug mode', 'cws-core' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Enable debug logging for troubleshooting', 'cws-core' ); ?>
        </p>
        <?php
    }

    /**
     * Add settings link to plugins page
     *
     * @param array $links Plugin action links.
     * @return array Modified links.
     */
    public function add_settings_link( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'options-general.php?page=cws-core-settings' ),
            __( 'Settings', 'cws-core' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }

    /**
     * Sanitize API endpoint
     *
     * @param string $value API endpoint value.
     * @return string Sanitized value.
     */
    public function sanitize_api_endpoint( $value ) {
        $value = esc_url_raw( $value );
        
        if ( empty( $value ) ) {
            add_settings_error(
                'cws_core_api_endpoint',
                'cws_core_api_endpoint_error',
                __( 'API endpoint is required.', 'cws-core' )
            );
        }
        
        return $value;
    }

    /**
     * Sanitize organization ID
     *
     * @param string $value Organization ID value.
     * @return string Sanitized value.
     */
    public function sanitize_organization_id( $value ) {
        $value = sanitize_text_field( $value );
        
        if ( empty( $value ) ) {
            add_settings_error(
                'cws_core_organization_id',
                'cws_core_organization_id_error',
                __( 'Organization ID is required.', 'cws-core' )
            );
        }
        
        return $value;
    }

    /**
     * Sanitize cache duration
     *
     * @param int $value Cache duration value.
     * @return int Sanitized value.
     */
    public function sanitize_cache_duration( $value ) {
        $value = intval( $value );
        
        if ( $value < 300 ) { // Minimum 5 minutes
            $value = 3600; // Default to 1 hour
        }
        
        return $value;
    }

    /**
     * Sanitize job slug
     *
     * @param string $value Job slug value.
     * @return string Sanitized value.
     */
    public function sanitize_job_slug( $value ) {
        $value = sanitize_title( $value );
        
        if ( empty( $value ) ) {
            add_settings_error(
                'cws_core_job_slug',
                'cws_core_job_slug_error',
                __( 'Job slug is required and must be a valid URL slug.', 'cws-core' )
            );
            return 'job'; // Default fallback
        }
        
        return $value;
    }

    /**
     * Sanitize debug mode
     *
     * @param mixed $value Debug mode value.
     * @return bool Sanitized value.
     */
    public function sanitize_debug_mode( $value ) {
        return (bool) $value;
    }

    /**
     * Sanitize job IDs
     *
     * @param string $value Job IDs value.
     * @return string Sanitized value.
     */
    public function sanitize_job_ids( $value ) {
        $job_ids = array_map( 'trim', explode( ',', $value ) );
        $job_ids = array_filter( $job_ids, 'is_numeric' ); // Only allow numeric IDs
        return implode( ',', $job_ids );
    }

    /**
     * Flush rewrite rules via AJAX
     */
    public function flush_rewrite_rules_ajax() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'cws_core_flush_rules' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        wp_send_json_success( array(
            'message' => __( 'Rewrite rules flushed successfully! Job URLs should now work correctly.', 'cws-core' ),
        ) );
    }
}
