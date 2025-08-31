<?php
/**
 * CWS Core Cache Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Caching functionality class
 */
class CWS_Core_Cache {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private $plugin;

    /**
     * Cache prefix
     *
     * @var string
     */
    private $prefix = 'cws_core_';

    /**
     * Constructor
     */
    public function __construct() {
        // Plugin instance will be set later
    }

    /**
     * Set plugin instance
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function set_plugin( $plugin ) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize the cache class
     */
    public function init() {
        // Register AJAX handlers for cache management
        add_action( 'wp_ajax_cws_core_clear_cache', array( $this, 'clear_cache_ajax' ) );
        add_action( 'wp_ajax_cws_core_get_cache_stats', array( $this, 'get_cache_stats_ajax' ) );

        // Schedule cache cleanup
        if ( ! wp_next_scheduled( 'cws_core_cache_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'cws_core_cache_cleanup' );
        }

        add_action( 'cws_core_cache_cleanup', array( $this, 'cleanup_expired_cache' ) );
    }

    /**
     * Get cached data
     *
     * @param string $key Cache key.
     * @return mixed Cached data or false if not found.
     */
    public function get( $key ) {
        $cache_key = $this->prefix . $key;
        $data = get_transient( $cache_key );

        if ( false === $data ) {
            if ( $this->plugin ) {
                $this->plugin->log( sprintf( 'Cache miss for key: %s', $key ), 'debug' );
            }
            return false;
        }

        if ( $this->plugin ) {
            $this->plugin->log( sprintf( 'Cache hit for key: %s', $key ), 'debug' );
        }
        return $data;
    }

    /**
     * Set cached data
     *
     * @param string $key Cache key.
     * @param mixed  $data Data to cache.
     * @param int    $expiration Expiration time in seconds (optional).
     * @return bool True on success, false on failure.
     */
    public function set( $key, $data, $expiration = null ) {
        $cache_key = $this->prefix . $key;

        if ( null === $expiration ) {
            $expiration = $this->plugin->get_option( 'cache_duration', 3600 );
        }

        $result = set_transient( $cache_key, $data, $expiration );

        if ( $this->plugin ) {
            if ( $result ) {
                $this->plugin->log( sprintf( 'Cached data for key: %s (expires in %d seconds)', $key, $expiration ), 'debug' );
            } else {
                $this->plugin->log( sprintf( 'Failed to cache data for key: %s', $key ), 'error' );
            }
        }

        return $result;
    }

    /**
     * Delete cached data
     *
     * @param string $key Cache key.
     * @return bool True on success, false on failure.
     */
    public function delete( $key ) {
        $cache_key = $this->prefix . $key;
        $result = delete_transient( $cache_key );

        if ( $this->plugin ) {
            if ( $result ) {
                $this->plugin->log( sprintf( 'Deleted cache for key: %s', $key ), 'debug' );
            } else {
                $this->plugin->log( sprintf( 'Failed to delete cache for key: %s', $key ), 'error' );
            }
        }

        return $result;
    }

    /**
     * Clear all plugin cache
     *
     * @return int Number of cache entries cleared.
     */
    public function clear_all() {
        global $wpdb;

        if ( $this->plugin ) {
            $this->plugin->log( 'Clearing all plugin cache', 'info' );
        }

        // Delete all transients with our prefix
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_' . $this->prefix . '%',
                '_transient_timeout_' . $this->prefix . '%'
            )
        );

        if ( $this->plugin ) {
            $this->plugin->log( sprintf( 'Cleared %d cache entries', $deleted ), 'info' );
        }

        return $deleted;
    }

    /**
     * Get cache statistics
     *
     * @return array Cache statistics.
     */
    public function get_stats() {
        global $wpdb;

        $stats = array(
            'total_entries' => 0,
            'expired_entries' => 0,
            'valid_entries' => 0,
            'total_size' => 0,
        );

        // Get all transients with our prefix
        $transients = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_' . $this->prefix . '%'
            )
        );

        $timeouts = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_timeout_' . $this->prefix . '%'
            )
        );

        // Create a map of timeout values
        $timeout_map = array();
        foreach ( $timeouts as $timeout ) {
            $key = str_replace( '_transient_timeout_', '', $timeout->option_name );
            $timeout_map[ $key ] = intval( $timeout->option_value );
        }

        $current_time = time();

        foreach ( $transients as $transient ) {
            $key = str_replace( '_transient_', '', $transient->option_name );
            $stats['total_entries']++;

            // Calculate size
            $stats['total_size'] += strlen( $transient->option_value );

            // Check if expired
            if ( isset( $timeout_map[ $key ] ) && $timeout_map[ $key ] < $current_time ) {
                $stats['expired_entries']++;
            } else {
                $stats['valid_entries']++;
            }
        }

        return $stats;
    }

    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        global $wpdb;

        if ( $this->plugin ) {
            $this->plugin->log( 'Running scheduled cache cleanup', 'info' );
        }

        // Delete expired transients
        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE t1, t2 FROM {$wpdb->options} t1
                INNER JOIN {$wpdb->options} t2 ON t1.option_name = REPLACE(t2.option_name, '_transient_timeout_', '_transient_')
                WHERE t1.option_name LIKE %s
                AND t1.option_value < %d",
                '_transient_timeout_' . $this->prefix . '%',
                time()
            )
        );

        if ( $this->plugin ) {
            $this->plugin->log( sprintf( 'Cleaned up %d expired cache entries', $deleted ), 'info' );
        }
    }

    /**
     * Clear cache via AJAX
     */
    public function clear_cache_ajax() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'cws_core_clear_cache' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $deleted = $this->clear_all();

        wp_send_json_success( array(
            'message' => sprintf( __( 'Cache cleared successfully. %d entries removed.', 'cws-core' ), $deleted ),
            'deleted' => $deleted,
        ) );
    }

    /**
     * Get cache statistics via AJAX
     */
    public function get_cache_stats_ajax() {
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['nonce'], 'cws_core_get_cache_stats' ) ) {
            wp_die( 'Security check failed' );
        }

        // Check permissions
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }

        $stats = $this->get_stats();

        wp_send_json_success( array(
            'stats' => $stats,
        ) );
    }

    /**
     * Generate cache key from parameters
     *
     * @param array $params Parameters to include in cache key.
     * @return string Cache key.
     */
    public function generate_key( $params ) {
        return md5( serialize( $params ) );
    }

    /**
     * Check if cache is enabled
     *
     * @return bool True if cache is enabled, false otherwise.
     */
    public function is_enabled() {
        return true; // Always enabled for now, can be made configurable
    }

    /**
     * Get cache duration
     *
     * @return int Cache duration in seconds.
     */
    public function get_duration() {
        return $this->plugin->get_option( 'cache_duration', 3600 );
    }
}
