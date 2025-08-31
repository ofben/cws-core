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
        
        // Add filter for EtchWP to access meta data
        add_filter( 'rest_prepare_cws_job', array( $this, 'add_meta_to_rest_response' ), 10, 3 );
        
        // Hook into WordPress query results to add meta data
        add_filter( 'the_posts', array( $this, 'add_meta_to_query_results' ), 10, 2 );
        add_filter( 'posts_results', array( $this, 'add_meta_to_posts_results' ), 10, 2 );
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
        
        // Store meta data in WordPress meta system for EtchWP access
        $post->meta_data = array(
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
        
        // Also keep as object properties for backward compatibility
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
        
        // Register meta fields for REST API and EtchWP
        add_action( 'init', array( $this, 'register_meta_fields' ) );
    }

    /**
     * Register meta fields for the cws_job post type
     */
    public function register_meta_fields(): void {
        // Register meta fields for REST API access
        register_post_meta( 'cws_job', 'cws_job_id', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_company', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_location', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_salary', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_department', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_category', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_status', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_type', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_url', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_seo_url', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_open_date', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_update_date', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_industry', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
        
        register_post_meta( 'cws_job', 'cws_job_function', array(
            'type' => 'string',
            'single' => true,
            'show_in_rest' => true,
        ) );
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
            // First check meta_data array (for EtchWP compatibility)
            if ( isset( $post->meta_data ) && is_array( $post->meta_data ) && isset( $post->meta_data[ $key ] ) ) {
                return $single ? $post->meta_data[ $key ] : array( $post->meta_data[ $key ] );
            }
            
            // Fallback to object properties
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

        $this->log_debug( 'Processing ' . count( $posts ) . ' posts for cws_job query' );

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
}
