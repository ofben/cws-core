<?php
/**
 * Plugin Name: CWS Core
 * Plugin URI: https://example.com/cws-core
 * Description: WordPress plugin that connects to a public API endpoint to fetch job data and display it on designated pages.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cws-core
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 *
 * @package CWS_Core
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Make sure WordPress is loaded
if ( ! function_exists( 'add_action' ) ) {
    return;
}

// Define plugin constants
define( 'CWS_CORE_VERSION', '1.0.0' );
define( 'CWS_CORE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CWS_CORE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CWS_CORE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Simple class loader - include all required files directly
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core.php';
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-api.php';
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-cache.php';
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-admin.php';
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-public.php';
require_once CWS_CORE_PLUGIN_DIR . 'includes/class-cws-core-virtual-cpt.php';

// Initialize the plugin
function cws_core_init() {
    try {
        error_log( 'CWS Core: Plugin initialization started' );
        
        // Check if the class exists before trying to instantiate it
        if ( ! class_exists( 'CWS_Core\\CWS_Core' ) ) {
            error_log( 'CWS Core: Main plugin class not found. Autoloader may have failed.' );
            return;
        }
        
        error_log( 'CWS Core: Main plugin class found, getting instance' );
        
        // Initialize main plugin class
        $plugin = CWS_Core\CWS_Core::get_instance();
        error_log( 'CWS Core: Plugin instance created, calling init()' );
        $plugin->init();
        
        error_log( 'CWS Core: Plugin initialization completed successfully' );
    } catch ( Exception $e ) {
        // Log error but don't crash the plugin
        error_log( 'CWS Core Plugin Error: ' . $e->getMessage() );
    }
}
add_action( 'plugins_loaded', 'cws_core_init' );

// Activation hook
function cws_core_activate() {
    try {
        // Create default options
        add_option( 'cws_core_api_endpoint', 'https://jobsapi-internal.m-cloud.io/api/stjob' );
        add_option( 'cws_core_organization_id', '' );
        add_option( 'cws_core_cache_duration', 3600 ); // 1 hour default
        add_option( 'cws_core_debug_mode', false );
        
        // Flush rewrite rules for custom endpoints
        flush_rewrite_rules();
    } catch ( Exception $e ) {
        // Log error but don't crash activation
        error_log( 'CWS Core Activation Error: ' . $e->getMessage() );
    }
}
register_activation_hook( __FILE__, 'cws_core_activate' );

// Deactivation hook
function cws_core_deactivate() {
    // Clear any scheduled events
    wp_clear_scheduled_hook( 'cws_core_cache_cleanup' );
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cws_core_deactivate' );

// Note: Uninstall functionality is handled by uninstall.php file
