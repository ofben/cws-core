<?php
/**
 * Uninstall CWS Core Plugin
 *
 * This file is executed when the plugin is deleted from WordPress.
 * It cleans up all plugin data, options, and cache entries.
 *
 * @package CWS_Core
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Include WordPress database functions
require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

/**
 * Clean up all plugin data
 */
function cws_core_uninstall() {
    global $wpdb;

    // Delete all plugin options
    $options_to_delete = array(
        'cws_core_api_endpoint',
        'cws_core_organization_id',
        'cws_core_cache_duration',
        'cws_core_debug_mode',
    );

    foreach ( $options_to_delete as $option ) {
        delete_option( $option );
    }

    // Clear all plugin transients
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
            '_transient_cws_core_%',
            '_transient_timeout_cws_core_%'
        )
    );

    // Clear any scheduled events
    wp_clear_scheduled_hook( 'cws_core_cache_cleanup' );

    // Flush rewrite rules to remove custom endpoints
    flush_rewrite_rules();

    // Log uninstall for debugging (if WP_DEBUG is enabled)
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( '[CWS Core] Plugin uninstalled - all data cleaned up' );
    }
}

// Run the uninstall function
cws_core_uninstall();
