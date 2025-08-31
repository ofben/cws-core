<?php
/**
 * CWS Core Public Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Frontend functionality class
 */
class CWS_Core_Public {

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
     * Initialize the public class
     */
    public function init() {
        // Register hooks
        $this->register_hooks();

        // Enqueue scripts and styles
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Add content filter for job pages
        add_filter( 'the_content', array( $this, 'inject_job_data' ) );

        // Add JavaScript variables
        add_action( 'wp_footer', array( $this, 'add_javascript_variables' ) );

        // Add meta tags for job pages
        add_action( 'wp_head', array( $this, 'add_job_meta_tags' ) );
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on job pages
        if ( $this->is_job_page() ) {
            wp_enqueue_script(
                'cws-core-public',
                CWS_CORE_PLUGIN_URL . 'public/js/public.js',
                array( 'jquery' ),
                CWS_CORE_VERSION,
                true
            );

            wp_enqueue_style(
                'cws-core-public',
                CWS_CORE_PLUGIN_URL . 'public/css/public.css',
                array(),
                CWS_CORE_VERSION
            );

            // Localize script with AJAX URL and nonce
            wp_localize_script(
                'cws-core-public',
                'cws_core_ajax',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'cws_core_public_nonce' ),
                )
            );
        }
    }

    /**
     * Check if current page is a job page
     *
     * @return bool True if job page, false otherwise.
     */
    public function is_job_page() {
        $job_id = get_query_var( 'cws_job_id' );
        return ! empty( $job_id );
    }

    /**
     * Get current job ID
     *
     * @return string|false Job ID or false if not found.
     */
    public function get_current_job_id() {
        return get_query_var( 'cws_job_id' );
    }

    /**
     * Inject job data into page content
     *
     * @param string $content Page content.
     * @return string Modified content.
     */
    public function inject_job_data( $content ) {
        if ( ! $this->is_job_page() ) {
            return $content;
        }

        $job_id = $this->get_current_job_id();
        
        if ( empty( $job_id ) ) {
            return $content . $this->get_error_message( __( 'No job ID found.', 'cws-core' ) );
        }

        // Fetch job data
        $job_data = $this->plugin->api->get_job( $job_id );

        if ( false === $job_data ) {
            return $content . $this->get_error_message( __( 'Job not found or API error occurred.', 'cws-core' ) );
        }

        // Format job data
        $formatted_job = $this->plugin->api->format_job_data( $job_data );

        // Build job display HTML
        $job_html = $this->build_job_display( $formatted_job );

        // Add JSON data for JavaScript
        $json_data = wp_json_encode( $formatted_job, JSON_PRETTY_PRINT );

        $json_html = sprintf(
            '<div class="cws-core-json-data" style="display: none;">
                <h3>%s</h3>
                <pre><code>%s</code></pre>
            </div>',
            esc_html__( 'Job Data (JSON)', 'cws-core' ),
            esc_html( $json_data )
        );

        // Store job data for JavaScript
        $this->plugin->public->current_job_data = $formatted_job;

        return $content . $job_html . $json_html;
    }

    /**
     * Build job display HTML
     *
     * @param array $job Formatted job data.
     * @return string HTML for job display.
     */
    public function build_job_display( $job ) {
        $html = '<div class="cws-core-job-display">';
        
        // Job title
        if ( ! empty( $job['title'] ) ) {
            $html .= sprintf(
                '<h1 class="cws-core-job-title">%s</h1>',
                esc_html( $job['title'] )
            );
        }

        // Company name
        if ( ! empty( $job['company_name'] ) ) {
            $html .= sprintf(
                '<div class="cws-core-job-company">%s</div>',
                esc_html( $job['company_name'] )
            );
        }

        // Location
        if ( ! empty( $job['location']['city'] ) || ! empty( $job['location']['state'] ) ) {
            $location_parts = array();
            if ( ! empty( $job['location']['city'] ) ) {
                $location_parts[] = esc_html( $job['location']['city'] );
            }
            if ( ! empty( $job['location']['state'] ) ) {
                $location_parts[] = esc_html( $job['location']['state'] );
            }
            
            $html .= sprintf(
                '<div class="cws-core-job-location">%s</div>',
                implode( ', ', $location_parts )
            );
        }

        // Department
        if ( ! empty( $job['department'] ) ) {
            $html .= sprintf(
                '<div class="cws-core-job-department">%s</div>',
                esc_html( $job['department'] )
            );
        }

        // Salary
        if ( ! empty( $job['salary'] ) ) {
            $html .= sprintf(
                '<div class="cws-core-job-salary">%s</div>',
                esc_html( $job['salary'] )
            );
        }

        // Employment type
        if ( ! empty( $job['employment_type'] ) ) {
            $html .= sprintf(
                '<div class="cws-core-job-employment-type">%s</div>',
                esc_html( $job['employment_type'] )
            );
        }

        // Description
        if ( ! empty( $job['description'] ) ) {
            $html .= sprintf(
                '<div class="cws-core-job-description">%s</div>',
                wp_kses_post( $job['description'] )
            );
        }

        // Apply button
        if ( ! empty( $job['url'] ) ) {
            $html .= sprintf(
                '<div class="cws-core-job-apply">
                    <a href="%s" class="cws-core-apply-button" target="_blank" rel="noopener noreferrer">%s</a>
                </div>',
                esc_url( $job['url'] ),
                esc_html__( 'Apply Now', 'cws-core' )
            );
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Get error message HTML
     *
     * @param string $message Error message.
     * @return string Error message HTML.
     */
    private function get_error_message( $message ) {
        return sprintf(
            '<div class="cws-core-error">
                <p>%s</p>
            </div>',
            esc_html( $message )
        );
    }

    /**
     * Add JavaScript variables
     */
    public function add_javascript_variables() {
        if ( ! $this->is_job_page() ) {
            return;
        }

        $job_id = $this->get_current_job_id();
        
        if ( empty( $job_id ) ) {
            return;
        }

        // Fetch job data if not already available
        if ( ! isset( $this->current_job_data ) ) {
            $job_data = $this->plugin->api->get_job( $job_id );
            if ( false !== $job_data ) {
                $this->current_job_data = $this->plugin->api->format_job_data( $job_data );
            }
        }

        if ( isset( $this->current_job_data ) ) {
            printf(
                '<script type="text/javascript">
                    var cwsCoreJobData = %s;
                </script>',
                wp_json_encode( $this->current_job_data )
            );
        }
    }

    /**
     * Add meta tags for job pages
     */
    public function add_job_meta_tags() {
        if ( ! $this->is_job_page() ) {
            return;
        }

        $job_id = $this->get_current_job_id();
        
        if ( empty( $job_id ) ) {
            return;
        }

        // Fetch job data
        $job_data = $this->plugin->api->get_job( $job_id );

        if ( false === $job_data ) {
            return;
        }

        $formatted_job = $this->plugin->api->format_job_data( $job_data );

        // Add Open Graph meta tags
        if ( ! empty( $formatted_job['title'] ) ) {
            printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $formatted_job['title'] ) );
        }

        if ( ! empty( $formatted_job['description'] ) ) {
            $description = wp_strip_all_tags( $formatted_job['description'] );
            $description = substr( $description, 0, 200 ) . ( strlen( $description ) > 200 ? '...' : '' );
            printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $description ) );
        }

        if ( ! empty( $formatted_job['url'] ) ) {
            printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $formatted_job['url'] ) );
        }

        // Add Twitter Card meta tags
        if ( ! empty( $formatted_job['title'] ) ) {
            printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $formatted_job['title'] ) );
        }

        if ( ! empty( $formatted_job['description'] ) ) {
            $description = wp_strip_all_tags( $formatted_job['description'] );
            $description = substr( $description, 0, 200 ) . ( strlen( $description ) > 200 ? '...' : '' );
            printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $description ) );
        }

        // Add structured data
        $this->add_structured_data( $formatted_job );
    }

    /**
     * Add structured data (JSON-LD)
     *
     * @param array $job Formatted job data.
     */
    private function add_structured_data( $job ) {
        $structured_data = array(
            '@context' => 'https://schema.org',
            '@type'    => 'JobPosting',
            'title'    => $job['title'],
            'company'  => array(
                '@type' => 'Organization',
                'name'  => $job['company_name'],
            ),
        );

        if ( ! empty( $job['description'] ) ) {
            $structured_data['description'] = wp_strip_all_tags( $job['description'] );
        }

        if ( ! empty( $job['location']['city'] ) || ! empty( $job['location']['state'] ) ) {
            $structured_data['jobLocation'] = array(
                '@type' => 'Place',
                'address' => array(
                    '@type' => 'PostalAddress',
                    'addressLocality' => $job['location']['city'],
                    'addressRegion'   => $job['location']['state'],
                    'addressCountry'  => $job['location']['country'],
                ),
            );
        }

        if ( ! empty( $job['url'] ) ) {
            $structured_data['url'] = $job['url'];
        }

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $structured_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        );
    }

    /**
     * Parse job ID from URL
     *
     * @param string $url URL to parse.
     * @return string|false Job ID or false if not found.
     */
    public function parse_job_id_from_url( $url ) {
        // Pattern: /job/{job_id}/
        $pattern = '/\/job\/(\d+)\/?/';
        
        if ( preg_match( $pattern, $url, $matches ) ) {
            return $matches[1];
        }

        return false;
    }

    /**
     * Get job URL
     *
     * @param string $job_id Job ID.
     * @param string $job_title Job title (optional).
     * @return string Job URL.
     */
    public function get_job_url( $job_id, $job_title = '' ) {
        $base_url = home_url( '/job/' . $job_id . '/' );
        
        if ( ! empty( $job_title ) ) {
            $slug = sanitize_title( $job_title );
            $base_url = home_url( '/job/' . $job_id . '/' . $slug . '/' );
        }

        return $base_url;
    }
}
