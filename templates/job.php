<?php
/**
 * Default Job Template for CWS Core
 *
 * This template is used when no custom theme template is found for job pages.
 *
 * @package CWS_Core
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main" role="main">
        
        <?php
        // Get the job ID from query vars
        $job_id = get_query_var( 'cws_job_id' );
        
        if ( ! empty( $job_id ) ) {
            // Get the plugin instance
            $cws_core = CWS_Core\CWS_Core::get_instance();
            
            // Fetch job data
            $job_data = $cws_core->api->get_job( $job_id );
            
            if ( false !== $job_data ) {
                // Format job data
                $formatted_job = $cws_core->api->format_job_data( $job_data );
                
                // Build job display HTML
                $job_html = $cws_core->public->build_job_display( $formatted_job );
                
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
                
                // Output the job content
                echo $job_html . $json_html;
                
                // Add JavaScript variable
                printf(
                    '<script type="text/javascript">
                        var cwsCoreJobData = %s;
                    </script>',
                    wp_json_encode( $formatted_job )
                );
                
            } else {
                // Job not found
                printf(
                    '<div class="cws-core-error">
                        <h1>%s</h1>
                        <p>%s</p>
                        <p><a href="%s">%s</a></p>
                    </div>',
                    esc_html__( 'Job Not Found', 'cws-core' ),
                    esc_html__( 'The requested job could not be found or is no longer available.', 'cws-core' ),
                    esc_url( home_url() ),
                    esc_html__( 'Return to Homepage', 'cws-core' )
                );
            }
        } else {
            // No job ID provided
            printf(
                '<div class="cws-core-error">
                    <h1>%s</h1>
                    <p>%s</p>
                    <p><a href="%s">%s</a></p>
                </div>',
                esc_html__( 'Invalid Job URL', 'cws-core' ),
                esc_html__( 'No job ID was provided in the URL.', 'cws-core' ),
                esc_url( home_url() ),
                esc_html__( 'Return to Homepage', 'cws-core' )
            );
        }
        ?>
        
    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_sidebar();
get_footer();
