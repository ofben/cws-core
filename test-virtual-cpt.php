<?php
/**
 * Test script for Virtual CPT functionality
 */

// Load WordPress
require_once 'wp-load.php';

echo "=== Virtual CPT Test Script ===\n\n";

// Test 1: Check if the plugin is active
if ( class_exists( 'CWS_Core\CWS_Core_Virtual_CPT' ) ) {
    echo "✓ CWS_Core_Virtual_CPT class exists\n";
} else {
    echo "✗ CWS_Core_Virtual_CPT class not found\n";
    exit;
}

// Test 2: Check if virtual posts are being created
$virtual_posts = CWS_Core\CWS_Core_Virtual_CPT::get_instance()->get_all_virtual_posts();
echo "Found " . count( $virtual_posts ) . " virtual posts\n";

if ( ! empty( $virtual_posts ) ) {
    $first_post = reset( $virtual_posts );
    echo "First virtual post ID: " . $first_post->ID . "\n";
    echo "First virtual post title: " . $first_post->post_title . "\n";
    
    // Test 3: Check if meta data is accessible
    $meta_data = get_post_meta( $first_post->ID );
    echo "Meta data count: " . count( $meta_data ) . "\n";
    
    // Test 4: Check specific meta fields
    $job_id = get_post_meta( $first_post->ID, 'cws_job_id', true );
    $job_company = get_post_meta( $first_post->ID, 'cws_job_company', true );
    $job_location = get_post_meta( $first_post->ID, 'cws_job_location', true );
    
    echo "Job ID: " . ( $job_id ?: 'Not found' ) . "\n";
    echo "Job Company: " . ( $job_company ?: 'Not found' ) . "\n";
    echo "Job Location: " . ( $job_location ?: 'Not found' ) . "\n";
    
    // Test 5: Check if get_post works
    $retrieved_post = get_post( $first_post->ID );
    if ( $retrieved_post ) {
        echo "✓ get_post() works for virtual post\n";
    } else {
        echo "✗ get_post() failed for virtual post\n";
    }
    
    // Test 6: Check WP_Query
    $query = new WP_Query( array(
        'post_type' => 'cws_job',
        'posts_per_page' => 5
    ) );
    
    echo "WP_Query found " . $query->found_posts . " posts\n";
    echo "WP_Query returned " . count( $query->posts ) . " posts\n";
    
    if ( $query->have_posts() ) {
        echo "✓ WP_Query works for virtual posts\n";
    } else {
        echo "✗ WP_Query failed for virtual posts\n";
    }
    
} else {
    echo "No virtual posts found\n";
}

echo "\n=== Test Complete ===\n";
