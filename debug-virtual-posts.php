<?php
/**
 * Debug Virtual Posts - Test page to see virtual post URLs and data
 */

// Load WordPress - try different paths
$wp_load_paths = [
    '../../../../wp-load.php',
    '../../../wp-load.php',
    '../../wp-load.php',
    '../wp-load.php',
    'wp-load.php'
];

$wp_loaded = false;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        require_once($path);
        $wp_loaded = true;
        break;
    }
}

if (!$wp_loaded) {
    die('Could not load WordPress. Check the file path.');
}

// Get the virtual CPT instance
global $cws_core;

echo '<h1>Debug Virtual Posts</h1>';

if ($cws_core && $cws_core->virtual_cpt) {
    $virtual_cpt = $cws_core->virtual_cpt;
    
    // Get configured job IDs
    $job_ids = $virtual_cpt->get_configured_job_ids();
    
    echo '<h2>Configured Job IDs:</h2>';
    echo '<ul>';
    foreach ($job_ids as $job_id) {
        echo '<li>' . esc_html($job_id) . '</li>';
    }
    echo '</ul>';
    
    echo '<h2>Virtual Post URLs:</h2>';
    echo '<ul>';
    foreach (array_slice($job_ids, 0, 5) as $job_id) { // Show first 5
        $virtual_post = $virtual_cpt->create_virtual_job_post($job_id);
        if ($virtual_post) {
            $url = home_url('/job/' . $virtual_post->post_name . '/');
            echo '<li>';
            echo '<strong>' . esc_html($virtual_post->post_title) . '</strong><br>';
            echo '<a href="' . esc_url($url) . '" target="_blank">' . esc_url($url) . '</a><br>';
            echo '<small>Job ID: ' . esc_html($job_id) . ' | Post Name: ' . esc_html($virtual_post->post_name) . '</small>';
            echo '</li>';
        }
    }
    echo '</ul>';
    
    echo '<h2>Schema Post URL:</h2>';
    $schema_post = $virtual_cpt->get_schema_post();
    if ($schema_post) {
        $schema_url = get_permalink($schema_post->ID);
        echo '<a href="' . esc_url($schema_url) . '" target="_blank">' . esc_url($schema_url) . '</a>';
        echo '<br><small>Schema Post ID: ' . esc_html($schema_post->ID) . '</small>';
    }
    
    echo '<h2>Test Virtual Post Data:</h2>';
    if (!empty($job_ids)) {
        $test_job_id = $job_ids[0];
        $test_post = $virtual_cpt->create_virtual_job_post($test_job_id);
        if ($test_post) {
            echo '<h3>Test Post: ' . esc_html($test_post->post_title) . '</h3>';
            echo '<pre>' . print_r($test_post, true) . '</pre>';
        }
    }
    
} else {
    echo '<p>Virtual CPT not available.</p>';
}

echo '<hr>';
echo '<p><a href="' . home_url('/debug-query.php') . '">Back to Debug Query</a></p>';
?>
