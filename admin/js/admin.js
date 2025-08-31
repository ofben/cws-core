/**
 * CWS Core Admin JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        CWS_Core_Admin.init();
    });

    // Main admin object
    var CWS_Core_Admin = {
        
        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // API connection test
            $('#cws-core-test-api').on('click', this.testApiConnection);
            
            // Cache management
            $('#cws-core-clear-cache').on('click', this.clearCache);
            $('#cws-core-get-cache-stats').on('click', this.getCacheStats);
            
            // URL management
            $('#cws-core-flush-rules').on('click', this.flushRewriteRules);
            
            // Virtual CPT testing
            $('#cws-core-test-virtual-cpt').on('click', this.testVirtualCPT);
        },

        /**
         * Test API connection
         */
        testApiConnection: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#cws-core-test-result');
            
            // Disable button and show loading
            $button.prop('disabled', true).text(cws_core_admin.strings.testing_connection);
            $result.removeClass('success error').addClass('loading').html(
                '<span class="cws-core-status-icon"></span>' + cws_core_admin.strings.testing_connection
            );

            // Make AJAX request
            $.ajax({
                url: cws_core_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cws_core_test_api',
                    nonce: cws_core_admin.nonces.test_api
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('loading').addClass('success').html(
                            '<span class="cws-core-status-icon success"></span>' + response.data.message
                        );
                        
                        // Show sample data if available
                        if (response.data.data && response.data.data.queryResult && response.data.data.queryResult.length > 0) {
                            var job = response.data.data.queryResult[0];
                            var sampleHtml = '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 3px; font-size: 12px;">';
                            sampleHtml += '<strong>Sample Job Data:</strong><br>';
                            sampleHtml += 'Title: ' + (job.title || 'N/A') + '<br>';
                            sampleHtml += 'Company: ' + (job.company_name || 'N/A') + '<br>';
                            sampleHtml += 'Location: ' + (job.primary_city || 'N/A') + ', ' + (job.primary_state || 'N/A');
                            sampleHtml += '</div>';
                            $result.append(sampleHtml);
                        }
                    } else {
                        $result.removeClass('loading').addClass('error').html(
                            '<span class="cws-core-status-icon error"></span>' + (response.data ? response.data.message : 'Connection failed')
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('loading').addClass('error').html(
                        '<span class="cws-core-status-icon error"></span>AJAX Error: ' + error
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Connection');
                }
            });
        },

        /**
         * Clear cache
         */
        clearCache: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#cws-core-cache-result');
            
            // Confirm action
            if (!confirm('Are you sure you want to clear all cached data? This will force fresh API requests.')) {
                return;
            }
            
            // Disable button and show loading
            $button.prop('disabled', true).text(cws_core_admin.strings.clearing_cache);
            $result.removeClass('success error').addClass('loading').html(
                '<span class="cws-core-status-icon"></span>' + cws_core_admin.strings.clearing_cache
            );

            // Make AJAX request
            $.ajax({
                url: cws_core_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cws_core_clear_cache',
                    nonce: cws_core_admin.nonces.clear_cache
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('loading').addClass('success').html(
                            '<span class="cws-core-status-icon success"></span>' + response.data.message
                        );
                    } else {
                        $result.removeClass('loading').addClass('error').html(
                            '<span class="cws-core-status-icon error"></span>' + (response.data ? response.data.message : 'Failed to clear cache')
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('loading').addClass('error').html(
                        '<span class="cws-core-status-icon error"></span>AJAX Error: ' + error
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Clear Cache');
                }
            });
        },

        /**
         * Get cache statistics
         */
        getCacheStats: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#cws-core-cache-result');
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Loading...');
            $result.removeClass('success error').addClass('loading').html(
                '<span class="cws-core-status-icon"></span>Loading cache statistics...'
            );

            // Make AJAX request
            $.ajax({
                url: cws_core_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cws_core_get_cache_stats',
                    nonce: cws_core_admin.nonces.cache_stats
                },
                success: function(response) {
                    if (response.success) {
                        var stats = response.data.stats;
                        var statsHtml = '<div class="cws-core-cache-stats">';
                        statsHtml += '<h4>Cache Statistics</h4>';
                        statsHtml += '<ul>';
                        statsHtml += '<li><span class="stat-label">Total Entries:</span><span class="stat-value">' + stats.total_entries + '</span></li>';
                        statsHtml += '<li><span class="stat-label">Valid Entries:</span><span class="stat-value">' + stats.valid_entries + '</span></li>';
                        statsHtml += '<li><span class="stat-label">Expired Entries:</span><span class="stat-value">' + stats.expired_entries + '</span></li>';
                        statsHtml += '<li><span class="stat-label">Total Size:</span><span class="stat-value">' + CWS_Core_Admin.formatBytes(stats.total_size) + '</span></li>';
                        statsHtml += '</ul>';
                        statsHtml += '</div>';
                        
                        $result.removeClass('loading').addClass('success').html(
                            '<span class="cws-core-status-icon success"></span>Cache statistics loaded successfully.' + statsHtml
                        );
                    } else {
                        $result.removeClass('loading').addClass('error').html(
                            '<span class="cws-core-status-icon error"></span>' + (response.data ? response.data.message : 'Failed to load cache statistics')
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('loading').addClass('error').html(
                        '<span class="cws-core-status-icon error"></span>AJAX Error: ' + error
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('View Stats');
                }
            });
        },

        /**
         * Test virtual CPT functionality
         */
        testVirtualCPT: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#cws-core-virtual-cpt-result');
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Testing...');
            $result.removeClass('success error').addClass('loading').html(
                '<span class="cws-core-status-icon"></span>Testing virtual CPT functionality...'
            );

            // Make AJAX request
            $.ajax({
                url: cws_core_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cws_core_test_virtual_cpt',
                    nonce: cws_core_admin.nonces.test_virtual_cpt
                },
                success: function(response) {
                    if (response.success) {
                        var results = response.data;
                        var resultsHtml = '<div class="cws-core-test-results">';
                        resultsHtml += '<h4>Virtual CPT Test Results</h4>';
                        resultsHtml += '<ul>';
                        
                        // Virtual CPT Class
                        var status = results.virtual_cpt_class === 'success' ? '✓' : '✗';
                        var color = results.virtual_cpt_class === 'success' ? 'green' : 'red';
                        resultsHtml += '<li style="color: ' + color + ';">' + status + ' Virtual CPT Class: ' + (results.virtual_cpt_class === 'success' ? 'Loaded' : 'Not loaded') + '</li>';
                        
                        // Post Type Registration
                        status = results.post_type_registered === 'success' ? '✓' : '✗';
                        color = results.post_type_registered === 'success' ? 'green' : 'red';
                        resultsHtml += '<li style="color: ' + color + ';">' + status + ' Post Type Registration: ' + (results.post_type_registered === 'success' ? 'Registered' : 'Not registered') + '</li>';
                        
                        // Virtual Post Creation
                        status = results.virtual_post_creation === 'success' ? '✓' : '✗';
                        color = results.virtual_post_creation === 'success' ? 'green' : 'red';
                        resultsHtml += '<li style="color: ' + color + ';">' + status + ' Virtual Post Creation: ' + (results.virtual_post_creation === 'success' ? 'Success' : 'Failed') + '</li>';
                        
                        // API Connection
                        status = results.api_connection === 'success' ? '✓' : '✗';
                        color = results.api_connection === 'success' ? 'green' : 'red';
                        resultsHtml += '<li style="color: ' + color + ';">' + status + ' API Connection: ' + (results.api_connection === 'success' ? 'Success' : 'Failed') + '</li>';
                        
                        // Cache Functionality
                        status = results.cache_functionality === 'success' ? '✓' : '✗';
                        color = results.cache_functionality === 'success' ? 'green' : 'red';
                        resultsHtml += '<li style="color: ' + color + ';">' + status + ' Cache Functionality: ' + (results.cache_functionality === 'success' ? 'Working' : 'Failed') + '</li>';
                        
                        resultsHtml += '</ul>';
                        
                        // Show virtual post data if available
                        if (results.virtual_post_data) {
                            resultsHtml += '<div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 3px; font-size: 12px;">';
                            resultsHtml += '<strong>Virtual Post Data:</strong><br>';
                            resultsHtml += 'ID: ' + results.virtual_post_data.id + '<br>';
                            resultsHtml += 'Post Type: ' + results.virtual_post_data.post_type + '<br>';
                            resultsHtml += 'Title: ' + results.virtual_post_data.title + '<br>';
                            resultsHtml += 'Job ID: ' + results.virtual_post_data.job_id + '<br>';
                            resultsHtml += 'Company: ' + results.virtual_post_data.company + '<br>';
                            resultsHtml += 'Location: ' + results.virtual_post_data.location;
                            resultsHtml += '</div>';
                        }
                        
                        resultsHtml += '</div>';
                        
                        $result.removeClass('loading').addClass('success').html(
                            '<span class="cws-core-status-icon success"></span>Virtual CPT test completed.' + resultsHtml
                        );
                    } else {
                        $result.removeClass('loading').addClass('error').html(
                            '<span class="cws-core-status-icon error"></span>' + (response.data ? response.data.message : 'Virtual CPT test failed')
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('loading').addClass('error').html(
                        '<span class="cws-core-status-icon error"></span>AJAX Error: ' + error
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Test Virtual CPT');
                }
            });
        },

        /**
         * Flush rewrite rules
         */
        flushRewriteRules: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $result = $('#cws-core-rules-result');
            
            // Disable button and show loading
            $button.prop('disabled', true).text('Flushing...');
            $result.removeClass('success error').addClass('loading').html(
                '<span class="cws-core-status-icon"></span>Flushing rewrite rules...'
            );

            // Make AJAX request
            $.ajax({
                url: cws_core_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'cws_core_flush_rules',
                    nonce: cws_core_admin.nonces.flush_rules
                },
                success: function(response) {
                    if (response.success) {
                        $result.removeClass('loading').addClass('success').html(
                            '<span class="cws-core-status-icon success"></span>' + response.data.message
                        );
                    } else {
                        $result.removeClass('loading').addClass('error').html(
                            '<span class="cws-core-status-icon error"></span>' + (response.data ? response.data.message : 'Failed to flush rewrite rules')
                        );
                    }
                },
                error: function(xhr, status, error) {
                    $result.removeClass('loading').addClass('error').html(
                        '<span class="cws-core-status-icon error"></span>AJAX Error: ' + error
                    );
                },
                complete: function() {
                    $button.prop('disabled', false).text('Flush Rewrite Rules');
                }
            });
        },

        /**
         * Format bytes to human readable format
         */
        formatBytes: function(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';

            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

            const i = Math.floor(Math.log(bytes) / Math.log(k));

            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        },

        /**
         * Show notification
         */
        showNotification: function(message, type = 'success') {
            var $notification = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.wrap h1').after($notification);
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Validate form fields
         */
        validateForm: function() {
            var isValid = true;
            var errors = [];

            // Check API endpoint
            var endpoint = $('#cws_core_api_endpoint').val();
            if (!endpoint) {
                errors.push('API endpoint is required');
                isValid = false;
            } else if (!CWS_Core_Admin.isValidUrl(endpoint)) {
                errors.push('API endpoint must be a valid URL');
                isValid = false;
            }

            // Check organization ID
            var orgId = $('#cws_core_organization_id').val();
            if (!orgId) {
                errors.push('Organization ID is required');
                isValid = false;
            } else if (!/^\d+$/.test(orgId)) {
                errors.push('Organization ID must be a number');
                isValid = false;
            }

            // Show errors if any
            if (!isValid) {
                CWS_Core_Admin.showNotification('Please fix the following errors: ' + errors.join(', '), 'error');
            }

            return isValid;
        },

        /**
         * Check if string is valid URL
         */
        isValidUrl: function(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    };

})(jQuery);
