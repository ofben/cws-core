<?php
/**
 * CWS Core API Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * API handling class
 */
class CWS_Core_API {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private $plugin;

    /**
     * Constructor
     */
    public function __construct() {
        // Plugin instance will be set later
    }

    /**
     * Set plugin instance
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function set_plugin( $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize the API class
     */
    public function init() {
        // Register AJAX handlers
        add_action( 'wp_ajax_cws_core_test_api', array( $this, 'test_api_connection' ) );
        add_action( 'wp_ajax_nopriv_cws_core_test_api', array( $this, 'test_api_connection' ) );
    }

    /**
     * Build API URL
     *
     * @param string|array $job_ids Job ID(s) to fetch.
     * @return string|false API URL or false on error.
     */
    public function build_api_url( $job_ids ) {
        if ( ! $this->plugin ) {
            return false;
        }
        
        $endpoint = $this->plugin->get_option( 'api_endpoint' );
        $organization_id = $this->plugin->get_option( 'organization_id' );

        if ( empty( $endpoint ) || empty( $organization_id ) ) {
            if ( $this->plugin ) {
                $this->plugin->log( 'API endpoint or organization ID not configured', 'error' );
            }
            return false;
        }

        // Ensure endpoint doesn't end with slash
        $endpoint = rtrim( $endpoint, '/' );

        // Convert single job ID to array
        if ( ! is_array( $job_ids ) ) {
            $job_ids = array( $job_ids );
        }

        // Validate job IDs
        $valid_job_ids = array();
        foreach ( $job_ids as $job_id ) {
            if ( is_numeric( $job_id ) && $job_id > 0 ) {
                $valid_job_ids[] = intval( $job_id );
            }
        }

        if ( empty( $valid_job_ids ) ) {
            if ( $this->plugin ) {
                $this->plugin->log( 'No valid job IDs provided', 'error' );
            }
            return false;
        }

        // Build URL with parameters
        $url = add_query_arg(
            array(
                'organization' => intval( $organization_id ),
                'jobList'      => implode( ',', $valid_job_ids ),
            ),
            $endpoint
        );

        if ( $this->plugin ) {
            $this->plugin->log( sprintf( 'Built API URL: %s', $url ), 'debug' );
        }

        return $url;
    }

    /**
     * Fetch job data from API
     *
     * @param string|array $job_ids Job ID(s) to fetch.
     * @return array|false Job data or false on error.
     */
    public function fetch_job_data( $job_ids ) {
        $url = $this->build_api_url( $job_ids );

        if ( ! $url ) {
            return false;
        }

        // Check cache first
        if ( $this->plugin && $this->plugin->cache ) {
            $cache_key = 'job_data_' . md5( $url );
            $cached_data = $this->plugin->cache->get( $cache_key );

            if ( false !== $cached_data ) {
                if ( $this->plugin ) {
                    $this->plugin->log( 'Returning cached job data', 'debug' );
                }
                return $cached_data;
            }
        }

        // Make API request
        $response = $this->make_request( $url );

        if ( is_wp_error( $response ) ) {
            if ( $this->plugin ) {
                $this->plugin->log( sprintf( 'API request failed: %s', $response->get_error_message() ), 'error' );
            }
            return false;
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        if ( 200 !== $response_code ) {
            if ( $this->plugin ) {
                $this->plugin->log( sprintf( 'API returned error code %d: %s', $response_code, $response_body ), 'error' );
            }
            return false;
        }

        // Parse JSON response
        $data = json_decode( $response_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            if ( $this->plugin ) {
                $this->plugin->log( sprintf( 'Failed to parse JSON response: %s', json_last_error_msg() ), 'error' );
            }
            return false;
        }

        // Validate response structure
        if ( ! $this->validate_response( $data ) ) {
            if ( $this->plugin ) {
                $this->plugin->log( 'Invalid API response structure', 'error' );
            }
            return false;
        }

        // Cache the response
        if ( $this->plugin && $this->plugin->cache ) {
            $cache_key = 'job_data_' . md5( $url );
            $this->plugin->cache->set( $cache_key, $data );
        }

        if ( $this->plugin ) {
            $job_count = is_array( $job_ids ) ? count( $job_ids ) : 1;
            $this->plugin->log( sprintf( 'Successfully fetched job data for %d job(s)', $job_count ), 'info' );
        }

        return $data;
    }

    /**
     * Make HTTP request to API
     *
     * @param string $url API URL.
     * @return array|WP_Error Response or error.
     */
    private function make_request( $url ) {
        $args = array(
            'timeout'     => 30,
            'user-agent'  => 'CWS-Core-Plugin/' . CWS_CORE_VERSION,
            'headers'     => array(
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ),
        );

        if ( $this->plugin ) {
            $this->plugin->log( sprintf( 'Making API request to: %s', $url ), 'debug' );
        }

        return wp_remote_get( $url, $args );
    }

    /**
     * Validate API response structure
     *
     * @param array $data Response data.
     * @return bool True if valid, false otherwise.
     */
    private function validate_response( $data ) {
        // Check if response is an array
        if ( ! is_array( $data ) ) {
            return false;
        }

        // Check for required fields based on the API response structure
        $required_fields = array( 'totalHits', 'queryResult' );

        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[ $field ] ) ) {
                return false;
            }
        }

        // Validate queryResult is an array
        if ( ! is_array( $data['queryResult'] ) ) {
            return false;
        }

        return true;
    }

    /**
     * Test API connection
     */
    public function test_api_connection() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'cws_core_test_api' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $test_job_id = '22026695'; // Use the example job ID from your API
        $result = $this->fetch_job_data( $test_job_id );

        if ( false === $result ) {
            wp_send_json_error( array(
                'message' => __( 'API connection failed. Please check your settings.', 'cws-core' ),
            ) );
        }

        wp_send_json_success( array(
            'message' => __( 'API connection successful!', 'cws-core' ),
            'data'    => $result,
        ) );
    }

    /**
     * Get job by ID
     *
     * @param string $job_id Job ID.
     * @return array|false Job data or false on error.
     */
    public function get_job( $job_id ) {
        $data = $this->fetch_job_data( $job_id );

        if ( false === $data || empty( $data['queryResult'] ) ) {
            return false;
        }

        // Return the first job from the results
        return $data['queryResult'][0];
    }

    /**
     * Get multiple jobs by IDs
     *
     * @param array $job_ids Array of job IDs.
     * @return array Array of job data.
     */
    public function get_jobs( $job_ids ) {
        $data = $this->fetch_job_data( $job_ids );

        if ( false === $data || empty( $data['queryResult'] ) ) {
            return array();
        }

        return $data['queryResult'];
    }

    /**
     * Format job data for display
     *
     * @param array $job Job data.
     * @return array Formatted job data.
     */
    public function format_job_data( $job ) {
        return array(
            'id'              => isset( $job['id'] ) ? intval( $job['id'] ) : 0,
            'title'           => isset( $job['title'] ) ? sanitize_text_field( $job['title'] ) : '',
            'company_name'    => isset( $job['company_name'] ) ? sanitize_text_field( $job['company_name'] ) : '',
            'description'     => isset( $job['description'] ) ? wp_kses_post( $job['description'] ) : '',
            'location'        => array(
                'city'    => isset( $job['primary_city'] ) ? sanitize_text_field( $job['primary_city'] ) : '',
                'state'   => isset( $job['primary_state'] ) ? sanitize_text_field( $job['primary_state'] ) : '',
                'country' => isset( $job['primary_country'] ) ? sanitize_text_field( $job['primary_country'] ) : '',
            ),
            'salary'          => isset( $job['salary'] ) ? sanitize_text_field( $job['salary'] ) : '',
            'department'      => isset( $job['department'] ) ? sanitize_text_field( $job['department'] ) : '',
            'employment_type' => isset( $job['employment_type'] ) ? sanitize_text_field( $job['employment_type'] ) : '',
            'url'             => isset( $job['url'] ) ? esc_url( $job['url'] ) : '',
            'open_date'       => isset( $job['open_date'] ) ? sanitize_text_field( $job['open_date'] ) : '',
            'raw_data'        => $job, // Keep original data for advanced usage
        );
    }
}
