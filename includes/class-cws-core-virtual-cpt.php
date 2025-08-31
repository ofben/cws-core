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
        
        // Add filter to ensure meta data is available in all contexts
        add_filter( 'get_post_metadata', array( $this, 'get_virtual_post_meta' ), 10, 4 );
        
        // Add filter for EtchWP to access meta data
        add_filter( 'rest_prepare_cws_job', array( $this, 'add_meta_to_rest_response' ), 10, 3 );
        
        // Hook into WordPress query results to add meta data
        add_filter( 'the_posts', array( $this, 'add_meta_to_query_results' ), 10, 2 );
        add_filter( 'posts_results', array( $this, 'add_meta_to_posts_results' ), 10, 2 );
        
        // Hook into the query preparation to ensure meta data is included
        add_filter( 'posts_pre_query', array( $this, 'prepare_virtual_posts_query' ), 10, 2 );
        
        // Hook into the_posts filter - this is called after posts are retrieved
        add_filter( 'the_posts', array( $this, 'add_meta_to_the_posts' ), 10, 2 );
        
        // Hook into posts_results filter - another filter called after posts are retrieved
        add_filter( 'posts_results', array( $this, 'add_meta_to_posts_results' ), 10, 2 );
        
        // Add more aggressive hooks for EtchWP compatibility
        add_filter( 'posts_clauses', array( $this, 'modify_posts_clauses' ), 10, 2 );
        add_filter( 'posts_where', array( $this, 'modify_posts_where' ), 10, 2 );
        
        // Note: posts_selection filter removed as it only receives SQL string, not query object
        
        // Hook into get_post to ensure meta data is always available
        add_filter( 'get_post', array( $this, 'add_meta_to_post' ), 10, 2 );
        
        // Hook into get_post_metadata - this is the core function that retrieves meta data
        add_filter( 'get_post_metadata', array( $this, 'get_virtual_post_meta' ), 10, 4 );
        
        // Hook into get_post_meta function directly
        add_filter( 'get_post_meta', array( $this, 'get_virtual_post_meta_direct' ), 10, 4 );
        
        // Hook into the core get_post_metadata function at a very early stage
        add_filter( 'get_post_metadata', array( $this, 'get_virtual_post_meta_early' ), 1, 4 );
        
        // Hook into the posts_pre_query at a very early stage
        add_filter( 'posts_pre_query', array( $this, 'prepare_virtual_posts_query_early' ), 1, 2 );
        
        // Hook into WP_Query to intercept all queries
        add_action( 'pre_get_posts', array( $this, 'intercept_wp_query' ), 10, 1 );
        
        // Hook into the main query to see what's happening
        add_action( 'wp', array( $this, 'debug_main_query' ), 10 );
        
        // Add a custom function for getting virtual post meta
        add_action( 'init', array( $this, 'add_custom_functions' ) );
        
        // Add a custom REST API endpoint for EtchWP
        add_action( 'rest_api_init', array( $this, 'register_etchwp_endpoint' ) );
        
        // Register fields in a way that EtchWP might recognize
        add_action( 'init', array( $this, 'register_etchwp_compatible_fields' ) );
        
        // Add admin menu for the CPT
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
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
        
        // Add schema post to the results if it exists
        $schema_post = $this->get_schema_post();
        if ( $schema_post ) {
            $virtual_posts[] = $schema_post;
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
    public function create_virtual_job_post( string $job_id ) {
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
        
        // Store meta data in WordPress meta system for standard queries
        $meta_data = array(
            'cws_job_id' => sanitize_text_field( $job_id ),
            'cws_job_company' => sanitize_text_field( $formatted_job['company_name'] ),
            'cws_job_location' => sanitize_text_field( $formatted_job['primary_city'] . ', ' . $formatted_job['primary_state'] ),
            'cws_job_salary' => sanitize_text_field( $formatted_job['salary'] ),
            'cws_job_department' => sanitize_text_field( $formatted_job['department'] ),
            'cws_job_category' => sanitize_text_field( $formatted_job['primary_category'] ),
            'cws_job_status' => sanitize_text_field( $formatted_job['entity_status'] ),
            'cws_job_type' => sanitize_text_field( $formatted_job['employment_type'] ),
            'cws_job_url' => esc_url_raw( $formatted_job['url'] ),
            'cws_job_seo_url' => esc_url_raw( $formatted_job['seo_url'] ),
            'cws_job_open_date' => sanitize_text_field( $formatted_job['open_date'] ),
            'cws_job_update_date' => sanitize_text_field( $formatted_job['update_date'] ),
            'cws_job_industry' => sanitize_text_field( $formatted_job['industry'] ),
            'cws_job_function' => sanitize_text_field( $formatted_job['function'] ),
        );
        
        // Note: We can't use update_post_meta() for virtual posts (negative IDs)
        // Instead, we'll hook into the meta retrieval system
        
        // Also store as object properties for backward compatibility
        $post->meta_data = $meta_data;
        
        // Add individual meta properties directly to the post object
        foreach ( $meta_data as $key => $value ) {
            $post->$key = $value;
        }
        
        // Also keep legacy properties for backward compatibility
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
        
        $this->log_debug( 'Virtual post created with meta data: ' . print_r( $meta_data, true ) );
        
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
        
        // Register meta fields for REST API and EtchWP
        add_action( 'init', array( $this, 'register_meta_fields' ) );
    }

    /**
     * Register meta fields for the cws_job post type
     */
    public function register_meta_fields(): void {
        // Method 1: Register meta fields like ACF does - with object_type and additional properties
        $meta_fields = [
            'cws_job_id' => 'string',
            'cws_job_company' => 'string',
            'cws_job_location' => 'string',
            'cws_job_salary' => 'string',
            'cws_job_department' => 'string',
            'cws_job_category' => 'string',
            'cws_job_status' => 'string',
            'cws_job_type' => 'string',
            'cws_job_url' => 'string',
            'cws_job_seo_url' => 'string',
            'cws_job_open_date' => 'string',
            'cws_job_update_date' => 'string',
            'cws_job_industry' => 'string',
            'cws_job_function' => 'string',
        ];

        foreach ($meta_fields as $field_name => $field_type) {
            // Method 1: Standard WordPress registration with additional properties
            register_post_meta( 'cws_job', $field_name, array(
                'type' => $field_type,
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => '__return_true',
                'object_subtype' => 'cws_job',
                'description' => 'CWS Job ' . str_replace('cws_job_', '', $field_name),
                'default' => '',
            ) );
        }

        // Method 2: Register as ACF-compatible fields
        $this->register_acf_compatible_fields();
        
        // Method 3: Create a real post to establish the schema
        $this->create_schema_post();
    }

    /**
     * Register fields in ACF-compatible format
     */
    private function register_acf_compatible_fields(): void {
        // Register fields that ACF would recognize
        $acf_fields = [
            'field_cws_job_id' => 'cws_job_id',
            'field_cws_job_company' => 'cws_job_company',
            'field_cws_job_location' => 'cws_job_location',
            'field_cws_job_salary' => 'cws_job_salary',
            'field_cws_job_department' => 'cws_job_department',
            'field_cws_job_category' => 'cws_job_category',
            'field_cws_job_status' => 'cws_job_status',
            'field_cws_job_type' => 'cws_job_type',
            'field_cws_job_url' => 'cws_job_url',
            'field_cws_job_seo_url' => 'cws_job_seo_url',
            'field_cws_job_open_date' => 'cws_job_open_date',
            'field_cws_job_update_date' => 'cws_job_update_date',
            'field_cws_job_industry' => 'cws_job_industry',
            'field_cws_job_function' => 'cws_job_function',
        ];

        foreach ($acf_fields as $acf_field_name => $meta_key) {
            register_post_meta( 'cws_job', $acf_field_name, array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => '__return_true',
                'object_subtype' => 'cws_job',
            ) );
        }
    }

    /**
     * Create a real post to establish the schema for EtchWP
     */
    private function create_schema_post(): void {
        $this->log_debug('create_schema_post() called');
        
        // Check if schema post already exists
        $schema_post = get_posts([
            'post_type' => 'cws_job',
            'post_status' => 'draft',
            'meta_query' => [
                [
                    'key' => '_cws_schema_post',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (empty($schema_post)) {
            $this->log_debug('No existing schema post found, creating new one');
            
            // Create a schema post with all the meta fields
            $post_data = [
                'post_title' => 'CWS Job Schema Template',
                'post_content' => 'This post establishes the schema for CWS Job meta fields.',
                'post_status' => 'draft',
                'post_type' => 'cws_job',
                'post_name' => 'cws-job-schema-template'
            ];

            $post_id = wp_insert_post($post_data);

            if ($post_id && !is_wp_error($post_id)) {
                $this->log_debug('Schema post created successfully with ID: ' . $post_id);
                
                // Add all the meta fields to establish the schema
                $meta_fields = [
                    'cws_job_id' => '16873230',
                    'cws_job_company' => 'NYU Langone Medical Center',
                    'cws_job_location' => 'New York, NY',
                    'cws_job_salary' => '$260k-$280k',
                    'cws_job_department' => 'Emergency Medicine',
                    'cws_job_category' => 'Faculty',
                    'cws_job_status' => 'Open',
                    'cws_job_type' => 'Full-time',
                    'cws_job_url' => 'https://example.com/job/16873230',
                    'cws_job_seo_url' => 'https://apply.interfolio.com/168481',
                    'cws_job_open_date' => '2025-05-29T14:04:33.73Z',
                    'cws_job_update_date' => '2025-05-29T14:04:36.87Z',
                    'cws_job_industry' => 'Health Care General',
                    'cws_job_function' => 'Education General',
                ];

                foreach ($meta_fields as $key => $value) {
                    $result = update_post_meta($post_id, $key, $value);
                    $this->log_debug('Added meta field ' . $key . ' = ' . $value . ' (result: ' . ($result ? 'success' : 'failed') . ')');
                }

                // Mark this as a schema post
                update_post_meta($post_id, '_cws_schema_post', '1');

                $this->log_debug('Schema post creation completed successfully');
            } else {
                $this->log_debug('Failed to create schema post: ' . (is_wp_error($post_id) ? $post_id->get_error_message() : 'Unknown error'));
            }
        } else {
            $this->log_debug('Schema post already exists with ID: ' . $schema_post[0]->ID);
        }
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
        
        // Handle both global $post and specific post_id
        $current_post = $post;
        if ( $post_id < 0 ) {
            // If we have a specific post_id, we need to find the corresponding post
            // This is tricky with virtual posts, so we'll use a different approach
            $current_post = $this->get_virtual_post_by_id( $post_id );
        }
        
        // Only handle virtual posts (negative IDs)
        if ( $current_post && $current_post->ID < 0 && $current_post->post_type === 'cws_job' ) {
            // First check meta_data array (for EtchWP compatibility)
            if ( isset( $current_post->meta_data ) && is_array( $current_post->meta_data ) && isset( $current_post->meta_data[ $key ] ) ) {
                return $single ? $current_post->meta_data[ $key ] : array( $current_post->meta_data[ $key ] );
            }
            
            // Fallback to object properties
            $meta_key = 'cws_job_' . str_replace( 'cws_job_', '', $key );
            
            if ( isset( $current_post->$meta_key ) ) {
                return $single ? $current_post->$meta_key : array( $current_post->$meta_key );
            }
        }
        
        return $value;
    }

    /**
     * Get virtual post by ID
     *
     * @param int $post_id Post ID.
     * @return \stdClass|false
     */
    private function get_virtual_post_by_id( int $post_id ) {
        // For virtual posts, we need to extract the job ID and recreate the post
        // This is a fallback method for when we don't have the global $post
        if ( $post_id < 0 ) {
            // Try to get the post from the current query
            global $wp_query;
            if ( $wp_query && $wp_query->posts ) {
                foreach ( $wp_query->posts as $post ) {
                    if ( $post->ID === $post_id ) {
                        return $post;
                    }
                }
            }
        }
        
        return null;
    }

    /**
     * Add custom functions for getting virtual post meta
     */
    public function add_custom_functions() {
        // Add a global function for getting virtual post meta
        if ( ! function_exists( 'get_virtual_post_meta' ) ) {
            function get_virtual_post_meta( $post_id, $key = '', $single = true ) {
                global $cws_core;
                
                if ( $cws_core && $cws_core->virtual_cpt ) {
                    return $cws_core->virtual_cpt->get_virtual_post_meta( null, $post_id, $key, $single );
                }
                
                return false;
            }
        }
        
        // Add a global function for getting all virtual post meta
        if ( ! function_exists( 'get_virtual_post_meta_all' ) ) {
            function get_virtual_post_meta_all( $post_id ) {
                global $cws_core;
                
                if ( $cws_core && $cws_core->virtual_cpt ) {
                    $virtual_post = $cws_core->virtual_cpt->get_virtual_post_by_id( $post_id );
                    if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                        return $virtual_post->meta_data;
                    }
                }
                
                return array();
            }
        }
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

    /**
     * Debug virtual post creation (temporary method for testing)
     *
     * @param string $job_id The job ID to debug.
     * @return \stdClass|false
     */
    public function debug_virtual_post( string $job_id ) {
        $post = $this->create_virtual_job_post( $job_id );
        if ( $post ) {
            $this->log_debug( 'Virtual post debug: ' . print_r( $post, true ) );
            
            // Debug meta data specifically
            if ( isset( $post->meta_data ) ) {
                $this->log_debug( 'Meta data found: ' . print_r( $post->meta_data, true ) );
            } else {
                $this->log_error( 'No meta_data found in virtual post' );
            }
            
            return $post;
        }
        $this->log_error( 'Failed to create virtual post for job: ' . $job_id );
        return false;
    }

    /**
     * Log debug message using WordPress debug logging
     *
     * @param string $message Debug message.
     */
    private function log_debug( string $message ): void {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'CWS Core Virtual CPT Debug: ' . $message );
        }
    }

    /**
     * Add meta data to REST API response for EtchWP
     *
     * @param \WP_REST_Response $response The response object.
     * @param \WP_Post         $post     The post object.
     * @param \WP_REST_Request $request  The request object.
     * @return \WP_REST_Response
     */
    public function add_meta_to_rest_response( $response, $post, $request ) {
        // Check if this is a virtual post (negative ID)
        if ( $post->ID < 0 && $post->post_type === 'cws_job' ) {
            // Get the virtual post data
            $job_id = get_post_meta( $post->ID, 'cws_job_id', true );
            if ( ! $job_id ) {
                // Try to extract job ID from post slug
                $slug_parts = explode( '-', $post->post_name );
                $job_id = end( $slug_parts );
            }
            
            if ( $job_id ) {
                $virtual_post = $this->create_virtual_job_post( $job_id );
                if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                    // Add meta data to the response
                    $response->data['meta'] = $virtual_post->meta_data;
                }
            }
        }
        
        return $response;
    }

    /**
     * Add meta data to WordPress query results for EtchWP
     *
     * @param array    $posts Array of post objects.
     * @param \WP_Query $query The query object.
     * @return array
     */
    public function add_meta_to_query_results( $posts, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $posts;
        }

        foreach ( $posts as $post ) {
            // Check if this is a virtual post (negative ID)
            if ( $post->ID < 0 && $post->post_type === 'cws_job' ) {
                // Extract job ID from post slug
                $slug_parts = explode( '-', $post->post_name );
                $job_id = end( $slug_parts );
                
                if ( $job_id && is_numeric( $job_id ) ) {
                    $virtual_post = $this->create_virtual_job_post( $job_id );
                    if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                        // Add meta data as a property that EtchWP can access
                        $post->meta_data = $virtual_post->meta_data;
                        
                        // Also add individual meta properties for compatibility
                        foreach ( $virtual_post->meta_data as $key => $value ) {
                            $post->$key = $value;
                        }
                    }
                }
            }
        }

        return $posts;
    }

    /**
     * Add meta data to posts_results for EtchWP compatibility
     *
     * @param array    $posts Array of post objects.
     * @param \WP_Query $query The query object.
     * @return array
     */
    public function add_meta_to_posts_results( $posts, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $posts;
        }

        $this->log_debug( 'add_meta_to_posts_results called - Processing ' . count( $posts ) . ' posts for cws_job query' );

        foreach ( $posts as $post ) {
            // Check if this is a virtual post (negative ID)
            if ( $post->ID < 0 && $post->post_type === 'cws_job' ) {
                $this->log_debug( 'Processing virtual post: ' . $post->post_name );
                
                // Extract job ID from post slug
                $slug_parts = explode( '-', $post->post_name );
                $job_id = end( $slug_parts );
                
                $this->log_debug( 'Extracted job ID: ' . $job_id );
                
                if ( $job_id && is_numeric( $job_id ) ) {
                    $virtual_post = $this->create_virtual_job_post( $job_id );
                    if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                        $this->log_debug( 'Adding meta data to post: ' . print_r( $virtual_post->meta_data, true ) );
                        
                        // Add meta data as a property that EtchWP can access
                        $post->meta_data = $virtual_post->meta_data;
                        
                        // Also add individual meta properties for compatibility
                        foreach ( $virtual_post->meta_data as $key => $value ) {
                            $post->$key = $value;
                        }
                    } else {
                        $this->log_error( 'Failed to create virtual post or no meta_data for job: ' . $job_id );
                    }
                } else {
                    $this->log_error( 'Invalid job ID extracted: ' . $job_id );
                }
            }
        }

        return $posts;
    }

    /**
     * Prepare virtual posts query to ensure meta data is included
     *
     * @param array|null $posts Posts array.
     * @param \WP_Query $query The query object.
     * @return array|null
     */
    public function prepare_virtual_posts_query( $posts, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $posts;
        }

        $this->log_debug( 'prepare_virtual_posts_query called for cws_job query' );

        // If posts are already set (from our replace_job_query), ensure meta data is attached
        if ( $posts && is_array( $posts ) ) {
            foreach ( $posts as $post ) {
                if ( $post->ID < 0 && $post->post_type === 'cws_job' ) {
                    // Extract job ID from post slug
                    $slug_parts = explode( '-', $post->post_name );
                    $job_id = end( $slug_parts );
                    
                    if ( $job_id && is_numeric( $job_id ) ) {
                        $virtual_post = $this->create_virtual_job_post( $job_id );
                        if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                            // Ensure meta data is attached to the post object
                            $post->meta_data = $virtual_post->meta_data;
                            
                            // Also add individual meta properties
                            foreach ( $virtual_post->meta_data as $key => $value ) {
                                $post->$key = $value;
                            }
                        }
                    }
                }
            }
        }

        return $posts;
    }

    /**
     * Modify posts clauses to include meta data
     *
     * @param array    $clauses Query clauses.
     * @param \WP_Query $query   The query object.
     * @return array
     */
    public function modify_posts_clauses( $clauses, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $clauses;
        }

        $this->log_debug( 'modify_posts_clauses called for cws_job query' );
        return $clauses;
    }

    /**
     * Modify posts where clause
     *
     * @param string   $where WHERE clause.
     * @param \WP_Query $query The query object.
     * @return string
     */
    public function modify_posts_where( $where, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $where;
        }

        $this->log_debug( 'modify_posts_where called for cws_job query' );
        return $where;
    }



    /**
     * Add meta data to post when retrieved
     *
     * @param \WP_Post $post Post object.
     * @param string   $output Output type.
     * @return \WP_Post
     */
    public function add_meta_to_post( $post, $output ) {
        // Only handle cws_job posts with negative IDs (virtual posts)
        if ( $post && $post->post_type === 'cws_job' && $post->ID < 0 ) {
            $this->log_debug( 'add_meta_to_post called for virtual post: ' . $post->post_name );
            
            // Extract job ID from post slug
            $slug_parts = explode( '-', $post->post_name );
            $job_id = end( $slug_parts );
            
            if ( $job_id && is_numeric( $job_id ) ) {
                $virtual_post = $this->create_virtual_job_post( $job_id );
                if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                    // Add meta data to the post object
                    $post->meta_data = $virtual_post->meta_data;
                    
                    // Also add individual meta properties
                    foreach ( $virtual_post->meta_data as $key => $value ) {
                        $post->$key = $value;
                    }
                    
                    $this->log_debug( 'Meta data added to post: ' . print_r( $virtual_post->meta_data, true ) );
                }
            }
        }
        
        return $post;
    }

    /**
     * Add meta data to posts in the_posts filter
     *
     * @param array    $posts Array of post objects.
     * @param \WP_Query $query The query object.
     * @return array
     */
    public function add_meta_to_the_posts( $posts, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $posts;
        }

        $this->log_debug( 'add_meta_to_the_posts called - Processing ' . count( $posts ) . ' posts' );

        if ( $posts && is_array( $posts ) ) {
            foreach ( $posts as $post ) {
                if ( $post->ID < 0 && $post->post_type === 'cws_job' ) {
                    $this->log_debug( 'Processing virtual post: ' . $post->post_name );
                    
                    // Extract job ID from post slug
                    $slug_parts = explode( '-', $post->post_name );
                    $job_id = end( $slug_parts );
                    
                    if ( $job_id && is_numeric( $job_id ) ) {
                        $virtual_post = $this->create_virtual_job_post( $job_id );
                        if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                            // Add meta data to the post object
                            $post->meta_data = $virtual_post->meta_data;
                            
                            // Also add individual meta properties
                            foreach ( $virtual_post->meta_data as $key => $value ) {
                                $post->$key = $value;
                            }
                            
                            $this->log_debug( 'Meta data added to post in the_posts filter: ' . print_r( $virtual_post->meta_data, true ) );
                        }
                    }
                }
            }
        }

        return $posts;
    }

    /**
     * Direct hook into get_post_meta function
     *
     * @param mixed  $value  The post meta value.
     * @param int    $post_id Post ID.
     * @param string $key     Meta key.
     * @param bool   $single  Whether to return a single value.
     * @return mixed
     */
    public function get_virtual_post_meta_direct( $value, $post_id, $key, $single ) {
        // Only handle virtual posts (negative IDs)
        if ( $post_id < 0 ) {
            $this->log_debug( 'get_virtual_post_meta_direct called for post_id: ' . $post_id . ', key: ' . $key );
            
            // Try to get the virtual post
            $virtual_post = $this->get_virtual_post_by_id( $post_id );
            if ( $virtual_post && isset( $virtual_post->meta_data ) && is_array( $virtual_post->meta_data ) ) {
                if ( isset( $virtual_post->meta_data[ $key ] ) ) {
                    $this->log_debug( 'Found meta value for key ' . $key . ': ' . $virtual_post->meta_data[ $key ] );
                    return $single ? $virtual_post->meta_data[ $key ] : array( $virtual_post->meta_data[ $key ] );
                }
            }
        }
        
        return $value;
    }

    /**
     * Intercept WP_Query to debug what's happening
     *
     * @param \WP_Query $query The query object.
     */
    public function intercept_wp_query( $query ) {
        // Only log cws_job queries
        if ( $query->get( 'post_type' ) === 'cws_job' ) {
            $this->log_debug( 'WP_Query intercepted for cws_job: ' . print_r( $query->query_vars, true ) );
        }
    }

    /**
     * Debug the main query
     */
    public function debug_main_query() {
        global $wp_query;
        
        if ( $wp_query && $wp_query->get( 'post_type' ) === 'cws_job' ) {
            $this->log_debug( 'Main query debug - post_type: cws_job, found_posts: ' . $wp_query->found_posts );
            
            if ( $wp_query->posts ) {
                foreach ( $wp_query->posts as $post ) {
                    $this->log_debug( 'Post in main query: ID=' . $post->ID . ', title=' . $post->post_title . ', has_meta_data=' . ( isset( $post->meta_data ) ? 'yes' : 'no' ) );
                }
            }
        }
    }

    /**
     * Early-stage hook into get_post_metadata
     *
     * @param mixed  $value  The post meta value.
     * @param int    $post_id Post ID.
     * @param string $key     Meta key.
     * @param bool   $single  Whether to return a single value.
     * @return mixed
     */
    public function get_virtual_post_meta_early( $value, $post_id, $key, $single ) {
        // Only handle virtual posts (negative IDs)
        if ( $post_id < 0 ) {
            $this->log_debug( 'get_virtual_post_meta_early called for post_id: ' . $post_id . ', key: ' . $key );
            
            // Try to get the virtual post
            $virtual_post = $this->get_virtual_post_by_id( $post_id );
            if ( $virtual_post && isset( $virtual_post->meta_data ) && is_array( $virtual_post->meta_data ) ) {
                if ( isset( $virtual_post->meta_data[ $key ] ) ) {
                    $this->log_debug( 'Early meta found for key ' . $key . ': ' . $virtual_post->meta_data[ $key ] );
                    return $single ? $virtual_post->meta_data[ $key ] : array( $virtual_post->meta_data[ $key ] );
                }
            }
        }
        
        return $value;
    }

    /**
     * Early-stage hook into posts_pre_query
     *
     * @param array|null $posts Posts array or null.
     * @param \WP_Query  $query The query object.
     * @return array|null
     */
    public function prepare_virtual_posts_query_early( $posts, $query ) {
        // Only process cws_job queries
        if ( $query->get( 'post_type' ) !== 'cws_job' ) {
            return $posts;
        }

        $this->log_debug( 'prepare_virtual_posts_query_early called for cws_job query' );
        return $posts;
    }

    /**
     * Register custom REST API endpoint for EtchWP
     */
    public function register_etchwp_endpoint() {
        register_rest_route( 'cws-core/v1', '/etchwp-meta/(?P<post_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array( $this, 'get_etchwp_meta' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Get meta data for EtchWP
     *
     * @param \WP_REST_Request $request The request object.
     * @return \WP_REST_Response
     */
    public function get_etchwp_meta( $request ) {
        $post_id = $request->get_param( 'post_id' );
        
        if ( $post_id < 0 ) {
            $virtual_post = $this->get_virtual_post_by_id( $post_id );
            if ( $virtual_post && isset( $virtual_post->meta_data ) ) {
                return new \WP_REST_Response( $virtual_post->meta_data, 200 );
            }
        }
        
        return new \WP_REST_Response( array(), 404 );
    }

    /**
     * Register fields in a way that EtchWP might recognize better
     */
    public function register_etchwp_compatible_fields(): void {
        // Register fields without the cws_job_ prefix for EtchWP compatibility
        $etchwp_fields = [
            'job_id' => 'cws_job_id',
            'job_company' => 'cws_job_company',
            'job_location' => 'cws_job_location',
            'job_salary' => 'cws_job_salary',
            'job_department' => 'cws_job_department',
            'job_category' => 'cws_job_category',
            'job_status' => 'cws_job_status',
            'job_type' => 'cws_job_type',
            'job_url' => 'cws_job_url',
            'job_seo_url' => 'cws_job_seo_url',
            'job_open_date' => 'cws_job_open_date',
            'job_update_date' => 'cws_job_update_date',
            'job_industry' => 'cws_job_industry',
            'job_function' => 'cws_job_function',
        ];

        foreach ($etchwp_fields as $field_name => $meta_key) {
            register_post_meta( 'cws_job', $field_name, array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => '__return_true',
                'object_subtype' => 'cws_job',
                'description' => 'CWS Job ' . str_replace('job_', '', $field_name),
            ) );
        }

        // Also register with common field names that EtchWP might expect
        $common_fields = [
            'company' => 'cws_job_company',
            'location' => 'cws_job_location',
            'salary' => 'cws_job_salary',
            'department' => 'cws_job_department',
            'category' => 'cws_job_category',
            'status' => 'cws_job_status',
            'type' => 'cws_job_type',
            'url' => 'cws_job_url',
            'industry' => 'cws_job_industry',
        ];

        foreach ($common_fields as $field_name => $meta_key) {
            register_post_meta( 'cws_job', $field_name, array(
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => '__return_true',
                'object_subtype' => 'cws_job',
                'description' => 'CWS Job ' . $field_name,
            ) );
        }
    }

    /**
     * Add admin menu for the CPT
     */
    public function add_admin_menu(): void {
        add_menu_page(
            'CWS Jobs',
            'CWS Jobs',
            'manage_options',
            'cws-jobs',
            array( $this, 'admin_page' ),
            'dashicons-businessman',
            30
        );
    }

    /**
     * Admin page content
     */
    public function admin_page(): void {
        echo '<div class="wrap">';
        echo '<h1>CWS Jobs Management</h1>';
        
        // Show schema post status
        $schema_post = get_posts([
            'post_type' => 'cws_job',
            'post_status' => 'draft',
            'meta_query' => [
                [
                    'key' => '_cws_schema_post',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (!empty($schema_post)) {
            echo '<div class="notice notice-success"><p>✓ Schema post exists (ID: ' . $schema_post[0]->ID . ')</p></div>';
            
            // Show schema post meta
            echo '<h2>Schema Post Meta Fields:</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Meta Key</th><th>Value</th></tr></thead>';
            echo '<tbody>';
            
            $meta_fields = [
                'cws_job_id', 'cws_job_company', 'cws_job_location', 'cws_job_salary',
                'cws_job_department', 'cws_job_category', 'cws_job_status', 'cws_job_type',
                'cws_job_url', 'cws_job_seo_url', 'cws_job_open_date', 'cws_job_update_date',
                'cws_job_industry', 'cws_job_function'
            ];
            
            foreach ($meta_fields as $field) {
                $value = get_post_meta($schema_post[0]->ID, $field, true);
                echo '<tr><td><code>' . $field . '</code></td><td>' . esc_html($value) . '</td></tr>';
            }
            
            echo '</tbody></table>';
        } else {
            echo '<div class="notice notice-error"><p>✗ Schema post not found.</p></div>';
            
            // Add manual creation button
            if (isset($_GET['create_schema']) && $_GET['create_schema'] === '1') {
                echo '<div class="notice notice-info"><p>Creating schema post...</p></div>';
                $this->create_schema_post();
                
                // Redirect to remove the parameter
                echo '<script>window.location.href = "' . admin_url('admin.php?page=cws-jobs') . '";</script>';
                return;
            }
            
            echo '<p><a href="' . admin_url('admin.php?page=cws-jobs&create_schema=1') . '" class="button button-primary">Create Schema Post</a></p>';
            
            // Show registered meta fields
            echo '<h2>Registered Meta Fields:</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Field Name</th><th>Type</th><th>Status</th></tr></thead>';
            echo '<tbody>';
            
            $meta_fields = [
                'cws_job_id', 'cws_job_company', 'cws_job_location', 'cws_job_salary',
                'cws_job_department', 'cws_job_category', 'cws_job_status', 'cws_job_type',
                'cws_job_url', 'cws_job_seo_url', 'cws_job_open_date', 'cws_job_update_date',
                'cws_job_industry', 'cws_job_function'
            ];
            
            foreach ($meta_fields as $field) {
                $registered = get_registered_meta_keys('post', 'cws_job');
                $status = isset($registered[$field]) ? '✓ Registered' : '✗ Not Registered';
                echo '<tr><td><code>' . $field . '</code></td><td>string</td><td>' . $status . '</td></tr>';
            }
            
            echo '</tbody></table>';
        }

        // Show virtual posts
        echo '<h2>Virtual Posts (from API):</h2>';
        $job_ids = $this->get_configured_job_ids();
        if (!empty($job_ids)) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Job ID</th><th>Title</th><th>Company</th><th>Location</th></tr></thead>';
            echo '<tbody>';
            
            foreach (array_slice($job_ids, 0, 10) as $job_id) {
                $virtual_post = $this->create_virtual_job_post($job_id);
                if ($virtual_post) {
                    echo '<tr>';
                    echo '<td>' . esc_html($job_id) . '</td>';
                    echo '<td>' . esc_html($virtual_post->post_title) . '</td>';
                    echo '<td>' . esc_html($virtual_post->cws_job_company ?? 'N/A') . '</td>';
                    echo '<td>' . esc_html($virtual_post->cws_job_location ?? 'N/A') . '</td>';
                    echo '</tr>';
                }
            }
            
            echo '</tbody></table>';
            echo '<p><em>Showing first 10 jobs. Total: ' . count($job_ids) . '</em></p>';
        } else {
            echo '<p>No virtual posts found.</p>';
        }

        echo '</div>';
    }

    /**
     * Get the schema post for reference
     *
     * @return \WP_Post|null
     */
    private function get_schema_post() {
        $schema_posts = get_posts([
            'post_type' => 'cws_job',
            'post_status' => 'draft',
            'meta_query' => [
                [
                    'key' => '_cws_schema_post',
                    'value' => '1',
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1
        ]);

        if (!empty($schema_posts)) {
            $schema_post = $schema_posts[0];
            
            // Add meta data to the schema post for consistency
            $meta_fields = [
                'cws_job_id', 'cws_job_company', 'cws_job_location', 'cws_job_salary',
                'cws_job_department', 'cws_job_category', 'cws_job_status', 'cws_job_type',
                'cws_job_url', 'cws_job_seo_url', 'cws_job_open_date', 'cws_job_update_date',
                'cws_job_industry', 'cws_job_function'
            ];
            
            $schema_post->meta_data = [];
            foreach ($meta_fields as $field) {
                $value = get_post_meta($schema_post->ID, $field, true);
                $schema_post->meta_data[$field] = $value;
                $schema_post->$field = $value; // Also add as direct properties
            }
            
            return $schema_post;
        }
        
        return null;
    }
}
