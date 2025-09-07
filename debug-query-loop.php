<?php
/**
 * Debug script for Query Loop issues
 */

// Load WordPress
require_once('/Users/benest/Local Sites/kadencejobs/app/public/wp-config.php');

// Get the CWS Core instance
global $cws_core;
if (!$cws_core) {
    echo "CWS Core not loaded\n";
    exit;
}

echo "=== CWS Core Query Loop Debug ===\n\n";

// Test 1: Check if virtual posts are being created
echo "1. Testing virtual post creation:\n";
$virtual_cpt = $cws_core->get_virtual_cpt();
$all_posts = $virtual_cpt->get_all_virtual_posts();
echo "Total virtual posts: " . count($all_posts) . "\n";

if (!empty($all_posts)) {
    $first_post = $all_posts[0];
    echo "First post ID: " . $first_post->ID . "\n";
    echo "First post title: " . $first_post->post_title . "\n";
    echo "First post type: " . $first_post->post_type . "\n";
} else {
    echo "No virtual posts found!\n";
}

echo "\n";

// Test 2: Test WP_Query for cws_job
echo "2. Testing WP_Query for cws_job:\n";
$query = new WP_Query(array(
    'post_type' => 'cws_job',
    'posts_per_page' => 5,
    'post_status' => 'publish'
));

echo "Query found " . $query->found_posts . " posts\n";
echo "Query returned " . $query->post_count . " posts\n";

if ($query->have_posts()) {
    while ($query->have_posts()) {
        $query->the_post();
        echo "  - Post ID: " . get_the_ID() . ", Title: " . get_the_title() . "\n";
    }
    wp_reset_postdata();
} else {
    echo "No posts found in query\n";
}

echo "\n";

// Test 3: Test REST API
echo "3. Testing REST API:\n";
$rest_url = home_url('/wp-json/wp/v2/cws_job');
$response = wp_remote_get($rest_url);
if (!is_wp_error($response)) {
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (is_array($data)) {
        echo "REST API returned " . count($data) . " posts\n";
        if (!empty($data)) {
            $first = $data[0];
            echo "First REST post ID: " . $first['id'] . "\n";
            echo "First REST post title: " . $first['title']['rendered'] . "\n";
        }
    } else {
        echo "REST API returned invalid data\n";
    }
} else {
    echo "REST API error: " . $response->get_error_message() . "\n";
}

echo "\n=== Debug Complete ===\n";
