<?php
/**
 * Debug Meta Data Creation
 * 
 * This file tests the virtual CPT meta data creation.
 * Place this in your WordPress root and access via browser.
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// Check if user is logged in and has admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. Admin privileges required.' );
}

// Get the CWS Core plugin instance
$cws_core = CWS_Core\CWS_Core::get_instance();

echo '<h1>Debug Meta Data Creation</h1>';

// Test 1: Create virtual post
echo '<h2>Test 1: Create Virtual Post</h2>';
$test_job_id = '16873230';
$virtual_post = $cws_core->virtual_cpt->create_virtual_job_post( $test_job_id );

if ( $virtual_post ) {
    echo '<p style="color: green;">✓ Virtual post created successfully</p>';
    echo '<h3>Virtual Post Details:</h3>';
    echo '<pre>' . print_r( $virtual_post, true ) . '</pre>';
    
    // Check meta data specifically
    if ( isset( $virtual_post->meta_data ) ) {
        echo '<h3>Meta Data Found:</h3>';
        echo '<pre>' . print_r( $virtual_post->meta_data, true ) . '</pre>';
    } else {
        echo '<p style="color: red;">✗ No meta_data found</p>';
    }
} else {
    echo '<p style="color: red;">✗ Failed to create virtual post</p>';
}

// Test 2: Test WordPress query
echo '<h2>Test 2: WordPress Query Test</h2>';
$query_args = array(
    'post_type' => 'cws_job',
    'posts_per_page' => 1,
    'post_status' => 'publish',
);

$query = new WP_Query( $query_args );

if ( $query->have_posts() ) {
    echo '<p style="color: green;">✓ Query returned posts</p>';
    
    while ( $query->have_posts() ) {
        $query->the_post();
        $post = get_post();
        
        echo '<h3>Post Details:</h3>';
        echo '<ul>';
        echo '<li><strong>ID:</strong> ' . $post->ID . '</li>';
        echo '<li><strong>Title:</strong> ' . $post->post_title . '</li>';
        echo '<li><strong>Post Type:</strong> ' . $post->post_type . '</li>';
        echo '<li><strong>Slug:</strong> ' . $post->post_name . '</li>';
        echo '</ul>';
        
        // Check for meta data
        if ( isset( $post->meta_data ) ) {
            echo '<h3>Meta Data Found in Query Result:</h3>';
            echo '<pre>' . print_r( $post->meta_data, true ) . '</pre>';
        } else {
            echo '<p style="color: red;">✗ No meta_data found in query result</p>';
        }
        
        // Check individual meta fields
        echo '<h3>Individual Meta Fields:</h3>';
        $meta_fields = array(
            'cws_job_id', 'cws_job_company', 'cws_job_location', 
            'cws_job_category', 'cws_job_department', 'cws_job_salary'
        );
        
        foreach ( $meta_fields as $field ) {
            if ( isset( $post->$field ) ) {
                echo '<p style="color: green;">✓ ' . $field . ': ' . $post->$field . '</p>';
            } else {
                echo '<p style="color: red;">✗ ' . $field . ': Not found</p>';
            }
        }
    }
    
    wp_reset_postdata();
} else {
    echo '<p style="color: red;">✗ Query returned no posts</p>';
}

// Test 3: Test REST API
echo '<h2>Test 3: REST API Test</h2>';
$rest_url = rest_url( 'wp/v2/cws_job' );
echo '<p><strong>REST URL:</strong> <a href="' . esc_url( $rest_url ) . '" target="_blank">' . esc_url( $rest_url ) . '</a></p>';

// Test 4: Check if filters are being called
echo '<h2>Test 4: Filter Debug</h2>';
echo '<p>Check the WordPress debug log for filter execution messages.</p>';

echo '<h2>Next Steps</h2>';
echo '<p>Based on the results above, we can determine the best approach to fix the meta data issue.</p>';
