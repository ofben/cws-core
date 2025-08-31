<?php
/**
 * Test Virtual CPT Implementation
 * 
 * This file can be used to test the virtual CPT functionality.
 * Place this file in your WordPress root directory and access it via browser.
 * 
 * IMPORTANT: Remove this file after testing for security reasons.
 */

// Load WordPress
require_once( dirname( __FILE__ ) . '/wp-load.php' );

// Check if user is logged in and has admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Access denied. Admin privileges required.' );
}

// Get the CWS Core plugin instance
$cws_core = CWS_Core\CWS_Core::get_instance();

echo '<h1>CWS Core Virtual CPT Test</h1>';

// Test 1: Check if virtual CPT class exists
echo '<h2>Test 1: Virtual CPT Class</h2>';
if ( $cws_core->virtual_cpt ) {
    echo '<p style="color: green;">✓ Virtual CPT class is loaded</p>';
} else {
    echo '<p style="color: red;">✗ Virtual CPT class is not loaded</p>';
    exit;
}

// Test 2: Check if post type is registered
echo '<h2>Test 2: Post Type Registration</h2>';
$post_types = get_post_types( array(), 'names' );
if ( in_array( 'cws_job', $post_types ) ) {
    echo '<p style="color: green;">✓ cws_job post type is registered</p>';
} else {
    echo '<p style="color: red;">✗ cws_job post type is not registered</p>';
}

// Test 3: Test virtual post creation
echo '<h2>Test 3: Virtual Post Creation</h2>';
$test_job_id = '22026695';
$virtual_post = $cws_core->virtual_cpt->create_virtual_job_post( $test_job_id );

if ( $virtual_post ) {
    echo '<p style="color: green;">✓ Virtual post created successfully</p>';
    echo '<h3>Virtual Post Details:</h3>';
    echo '<ul>';
    echo '<li><strong>ID:</strong> ' . esc_html( $virtual_post->ID ) . '</li>';
    echo '<li><strong>Post Type:</strong> ' . esc_html( $virtual_post->post_type ) . '</li>';
    echo '<li><strong>Title:</strong> ' . esc_html( $virtual_post->post_title ) . '</li>';
    echo '<li><strong>Status:</strong> ' . esc_html( $virtual_post->post_status ) . '</li>';
    echo '<li><strong>Job ID:</strong> ' . esc_html( $virtual_post->cws_job_id ) . '</li>';
    echo '<li><strong>Company:</strong> ' . esc_html( $virtual_post->cws_job_company ) . '</li>';
    echo '<li><strong>Location:</strong> ' . esc_html( $virtual_post->cws_job_location ) . '</li>';
    echo '</ul>';
} else {
    echo '<p style="color: red;">✗ Failed to create virtual post</p>';
}

// Test 4: Check configured job IDs
echo '<h2>Test 4: Configured Job IDs</h2>';
$job_ids = get_option( 'cws_core_job_ids', '22026695' );
echo '<p><strong>Configured Job IDs:</strong> ' . esc_html( $job_ids ) . '</p>';

// Test 5: Check API connection
echo '<h2>Test 5: API Connection</h2>';
if ( $cws_core->api ) {
    $job_data = $cws_core->api->get_job( $test_job_id );
    if ( $job_data ) {
        echo '<p style="color: green;">✓ API connection successful</p>';
        echo '<p><strong>Raw Job Data Keys:</strong> ' . esc_html( implode( ', ', array_keys( $job_data ) ) ) . '</p>';
    } else {
        echo '<p style="color: red;">✗ API connection failed</p>';
    }
} else {
    echo '<p style="color: red;">✗ API class not available</p>';
}

// Test 6: Check cache functionality
echo '<h2>Test 6: Cache Functionality</h2>';
if ( $cws_core->cache ) {
    echo '<p style="color: green;">✓ Cache class is available</p>';
    
    // Test cache set/get
    $test_key = 'test_virtual_cpt';
    $test_data = array( 'test' => 'data' );
    $cws_core->cache->set( $test_key, $test_data, 60 );
    $cached_data = $cws_core->cache->get( $test_key );
    
    if ( $cached_data && $cached_data['test'] === 'data' ) {
        echo '<p style="color: green;">✓ Cache set/get working</p>';
    } else {
        echo '<p style="color: red;">✗ Cache set/get failed</p>';
    }
} else {
    echo '<p style="color: red;">✗ Cache class not available</p>';
}

// Test 7: Check rewrite rules
echo '<h2>Test 7: Rewrite Rules</h2>';
$rewrite_rules = get_option( 'rewrite_rules' );
$job_rule_found = false;

foreach ( $rewrite_rules as $pattern => $replacement ) {
    if ( strpos( $pattern, 'job' ) !== false && strpos( $replacement, 'cws_job_id' ) !== false ) {
        $job_rule_found = true;
        echo '<p style="color: green;">✓ Job rewrite rule found: ' . esc_html( $pattern ) . '</p>';
        break;
    }
}

if ( ! $job_rule_found ) {
    echo '<p style="color: orange;">⚠ Job rewrite rule not found. You may need to flush rewrite rules.</p>';
    echo '<p><a href="' . admin_url( 'options-general.php?page=cws-core-settings' ) . '">Go to CWS Core Settings</a></p>';
}

echo '<h2>Next Steps</h2>';
echo '<p>If all tests pass, you can:</p>';
echo '<ol>';
echo '<li>Visit <a href="' . home_url( '/job/22026695/' ) . '">/job/22026695/</a> to test the job page</li>';
echo '<li>Create an EtchWP template for the cws_job post type</li>';
echo '<li>Test job queries in EtchWP</li>';
echo '</ol>';

echo '<p><strong>Remember to delete this test file after testing!</strong></p>';
