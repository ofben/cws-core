<?php
/**
 * Debug Query Page for CWS Core Virtual CPT
 * 
 * This page tests the standard WordPress query for cws_job posts
 * and outputs detailed information about the results.
 */

// Load WordPress
$wp_load_path = dirname(__FILE__) . '/wp-load.php';
if (!file_exists($wp_load_path)) {
    // Try parent directory
    $wp_load_path = dirname(dirname(__FILE__)) . '/wp-load.php';
    if (!file_exists($wp_load_path)) {
        // Try grandparent directory
        $wp_load_path = dirname(dirname(dirname(__FILE__))) . '/wp-load.php';
        if (!file_exists($wp_load_path)) {
            die('Could not find wp-load.php. Please place this file in the WordPress root directory.');
        }
    }
}

require_once $wp_load_path;

// Check if CWS Core plugin is active
if (!class_exists('CWS_Core\\CWS_Core')) {
    die('CWS Core plugin is not active.');
}

// Set up the query arguments (same as EtchWP)
$query_args = [
    'post_type' => 'cws_job',
    'posts_per_page' => 10,
    'post_status' => 'publish',
    'orderby' => 'date',
    'order' => 'DESC',
    'meta_query' => [],
    'tax_query' => []
];

echo "<!DOCTYPE html>\n";
echo "<html>\n";
echo "<head>\n";
echo "<title>CWS Core Virtual CPT Debug Query</title>\n";
echo "<style>\n";
echo "body { font-family: Arial, sans-serif; margin: 20px; }\n";
echo ".post { border: 1px solid #ccc; margin: 10px 0; padding: 15px; }\n";
echo ".meta-data { background: #f5f5f5; padding: 10px; margin: 10px 0; }\n";
echo ".debug-info { background: #e8f4fd; padding: 10px; margin: 10px 0; }\n";
echo ".error { background: #ffe6e6; padding: 10px; margin: 10px 0; color: #d00; }\n";
echo ".success { background: #e6ffe6; padding: 10px; margin: 10px 0; color: #0a0; }\n";
echo "pre { background: #f8f8f8; padding: 10px; overflow-x: auto; }\n";
echo "</style>\n";
echo "</head>\n";
echo "<body>\n";

echo "<h1>CWS Core Virtual CPT Debug Query</h1>\n";

echo "<div class='debug-info'>\n";
echo "<h2>Query Arguments:</h2>\n";
echo "<pre>" . print_r($query_args, true) . "</pre>\n";
echo "</div>\n";

// Execute the query
$query = new WP_Query($query_args);

echo "<div class='debug-info'>\n";
echo "<h2>Query Results Summary:</h2>\n";
echo "<p><strong>Found Posts:</strong> " . $query->found_posts . "</p>\n";
echo "<p><strong>Post Count:</strong> " . $query->post_count . "</p>\n";
echo "<p><strong>Max Num Pages:</strong> " . $query->max_num_pages . "</p>\n";
echo "</div>\n";

if ($query->have_posts()) {
    echo "<h2>Posts Found:</h2>\n";
    
    while ($query->have_posts()) {
        $query->the_post();
        $post = get_post();
        
        echo "<div class='post'>\n";
        echo "<h3>Post ID: " . $post->ID . "</h3>\n";
        echo "<p><strong>Title:</strong> " . $post->post_title . "</p>\n";
        echo "<p><strong>Slug:</strong> " . $post->post_name . "</p>\n";
        echo "<p><strong>Type:</strong> " . $post->post_type . "</p>\n";
        echo "<p><strong>Status:</strong> " . $post->post_status . "</p>\n";
        echo "<p><strong>Date:</strong> " . $post->post_date . "</p>\n";
        
        // Check if this is a virtual post
        if ($post->ID < 0) {
            echo "<p class='success'><strong>✓ Virtual Post Detected (ID: " . $post->ID . ")</strong></p>\n";
        } else {
            echo "<p class='error'><strong>✗ Not a Virtual Post (ID: " . $post->ID . ")</strong></p>\n";
        }
        
        // Check for meta_data property
        if (isset($post->meta_data)) {
            echo "<p class='success'><strong>✓ meta_data property found</strong></p>\n";
            echo "<div class='meta-data'>\n";
            echo "<h4>meta_data Property:</h4>\n";
            echo "<pre>" . print_r($post->meta_data, true) . "</pre>\n";
            echo "</div>\n";
        } else {
            echo "<p class='error'><strong>✗ No meta_data property found</strong></p>\n";
        }
        
        // Check for individual meta properties
        $meta_properties = [
            'cws_job_id', 'cws_job_company', 'cws_job_location', 'cws_job_salary',
            'cws_job_department', 'cws_job_category', 'cws_job_status', 'cws_job_type',
            'cws_job_url', 'cws_job_seo_url', 'cws_job_open_date', 'cws_job_update_date',
            'cws_job_industry', 'cws_job_function'
        ];
        
        echo "<div class='meta-data'>\n";
        echo "<h4>Individual Meta Properties:</h4>\n";
        foreach ($meta_properties as $prop) {
            if (isset($post->$prop)) {
                echo "<p class='success'><strong>" . $prop . ":</strong> " . $post->$prop . "</p>\n";
            } else {
                echo "<p class='error'><strong>" . $prop . ":</strong> Not found</p>\n";
            }
        }
        echo "</div>\n";
        
        // Test get_post_meta function
        echo "<div class='meta-data'>\n";
        echo "<h4>get_post_meta() Results:</h4>\n";
        foreach ($meta_properties as $prop) {
            $meta_value = get_post_meta($post->ID, $prop, true);
            if (!empty($meta_value)) {
                echo "<p class='success'><strong>" . $prop . ":</strong> " . $meta_value . "</p>\n";
            } else {
                echo "<p class='error'><strong>" . $prop . ":</strong> Not found</p>\n";
            }
        }
        echo "</div>\n";
        
        // Test custom functions if available
        if (function_exists('get_virtual_post_meta')) {
            echo "<div class='meta-data'>\n";
            echo "<h4>Custom get_virtual_post_meta() Results:</h4>\n";
            foreach ($meta_properties as $prop) {
                $meta_value = get_virtual_post_meta($post->ID, $prop, true);
                if (!empty($meta_value)) {
                    echo "<p class='success'><strong>" . $prop . ":</strong> " . $meta_value . "</p>\n";
                } else {
                    echo "<p class='error'><strong>" . $prop . ":</strong> Not found</p>\n";
                }
            }
            echo "</div>\n";
        }
        
        // Test custom function for all meta
        if (function_exists('get_virtual_post_meta_all')) {
            echo "<div class='meta-data'>\n";
            echo "<h4>get_virtual_post_meta_all() Results:</h4>\n";
            $all_meta = get_virtual_post_meta_all($post->ID);
            if (!empty($all_meta)) {
                echo "<pre>" . print_r($all_meta, true) . "</pre>\n";
            } else {
                echo "<p class='error'>No meta data returned</p>\n";
            }
            echo "</div>\n";
        }
        
        // Show raw JSON for EtchWP compatibility
        echo "<div class='meta-data'>\n";
        echo "<h4>Raw JSON for EtchWP:</h4>\n";
        $json_data = [
            'id' => $post->ID,
            'title' => $post->post_title,
            'slug' => $post->post_name,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'date' => $post->post_date,
            'status' => $post->post_status,
            'type' => $post->post_type,
            'meta' => $all_meta ?? [],
            'meta_data' => isset($post->meta_data) ? $post->meta_data : [],
            'individual_meta' => []
        ];
        
        // Add individual meta properties
        $meta_properties = [
            'cws_job_id', 'cws_job_company', 'cws_job_location', 'cws_job_salary',
            'cws_job_department', 'cws_job_category', 'cws_job_status', 'cws_job_type',
            'cws_job_url', 'cws_job_seo_url', 'cws_job_open_date', 'cws_job_update_date',
            'cws_job_industry', 'cws_job_function'
        ];
        
        foreach ($meta_properties as $prop) {
            $json_data['individual_meta'][$prop] = isset($post->$prop) ? $post->$prop : null;
        }
        
        echo "<pre>" . json_encode($json_data, JSON_PRETTY_PRINT) . "</pre>\n";
        echo "</div>\n";
        
        echo "</div>\n";
    }
    
    wp_reset_postdata();
} else {
    echo "<div class='error'>\n";
    echo "<h2>No Posts Found</h2>\n";
    echo "<p>The query returned no posts.</p>\n";
    echo "</div>\n";
}

// Additional debugging information
echo "<div class='debug-info'>\n";
echo "<h2>Additional Debug Information:</h2>\n";

// Check if virtual CPT class exists
if (class_exists('CWS_Core\\CWS_Core_Virtual_CPT')) {
    echo "<p class='success'>✓ CWS_Core_Virtual_CPT class exists</p>\n";
} else {
    echo "<p class='error'>✗ CWS_Core_Virtual_CPT class not found</p>\n";
}

// Check if global CWS Core instance exists
global $cws_core;
if ($cws_core) {
    echo "<p class='success'>✓ Global \$cws_core instance exists</p>\n";
    if (isset($cws_core->virtual_cpt)) {
        echo "<p class='success'>✓ Virtual CPT instance exists</p>\n";
    } else {
        echo "<p class='error'>✗ Virtual CPT instance not found</p>\n";
    }
} else {
    echo "<p class='error'>✗ Global \$cws_core instance not found</p>\n";
}

// Check registered post types
$post_types = get_post_types(['public' => true], 'names');
if (in_array('cws_job', $post_types)) {
    echo "<p class='success'>✓ cws_job post type is registered</p>\n";
} else {
    echo "<p class='error'>✗ cws_job post type is not registered</p>\n";
}

echo "</div>\n";

echo "</body>\n";
echo "</html>\n";
?>
