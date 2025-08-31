/**
 * CWS Core Public JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        CWS_Core_Public.init();
    });

    // Main public object
    var CWS_Core_Public = {
        
        /**
         * Initialize public functionality
         */
        init: function() {
            this.bindEvents();
            this.setupJobData();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Toggle JSON data visibility
            $(document).on('click', '.cws-core-json-toggle', this.toggleJsonData);
            
            // Apply button tracking
            $(document).on('click', '.cws-core-apply-button', this.trackApplyClick);
            
            // Copy job data to clipboard
            $(document).on('click', '.cws-core-copy-data', this.copyJobData);
            
            // Share job functionality
            $(document).on('click', '.cws-core-share-job', this.shareJob);
        },

        /**
         * Setup job data for JavaScript access
         */
        setupJobData: function() {
            // Check if job data is available
            if (typeof cwsCoreJobData !== 'undefined') {
                console.log('CWS Core: Job data loaded', cwsCoreJobData);
                
                // Add data attributes to job display
                $('.cws-core-job-display').attr('data-job-id', cwsCoreJobData.id);
                
                // Add share and copy buttons if not already present
                if ($('.cws-core-job-actions').length === 0) {
                    CWS_Core_Public.addActionButtons();
                }
                
                // Add structured data for analytics
                CWS_Core_Public.setupAnalytics();
            }
        },

        /**
         * Add action buttons to job display
         */
        addActionButtons: function() {
            var $jobDisplay = $('.cws-core-job-display');
            var $actions = $('<div class="cws-core-job-actions"></div>');
            
            // Add buttons
            $actions.append(
                '<button type="button" class="cws-core-action-btn cws-core-share-job" title="Share this job">' +
                '<span class="dashicons dashicons-share"></span> Share' +
                '</button>'
            );
            
            $actions.append(
                '<button type="button" class="cws-core-action-btn cws-core-copy-data" title="Copy job data">' +
                '<span class="dashicons dashicons-clipboard"></span> Copy Data' +
                '</button>'
            );
            
            $actions.append(
                '<button type="button" class="cws-core-action-btn cws-core-json-toggle" title="Toggle JSON view">' +
                '<span class="dashicons dashicons-code-standards"></span> View JSON' +
                '</button>'
            );
            
            // Insert after job title
            $jobDisplay.find('.cws-core-job-title').after($actions);
        },

        /**
         * Toggle JSON data visibility
         */
        toggleJsonData: function(e) {
            e.preventDefault();
            
            var $jsonData = $('.cws-core-json-data');
            var $button = $(this);
            
            if ($jsonData.is(':visible')) {
                $jsonData.slideUp();
                $button.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-code-standards');
                $button.attr('title', 'Toggle JSON view');
            } else {
                $jsonData.slideDown();
                $button.find('.dashicons').removeClass('dashicons-code-standards').addClass('dashicons-visibility');
                $button.attr('title', 'Hide JSON view');
            }
        },

        /**
         * Track apply button clicks
         */
        trackApplyClick: function(e) {
            var jobId = $(this).closest('.cws-core-job-display').attr('data-job-id');
            var jobTitle = $('.cws-core-job-title').text();
            var companyName = $('.cws-core-job-company').text();
            
            // Track with Google Analytics if available
            if (typeof gtag !== 'undefined') {
                gtag('event', 'job_apply', {
                    'event_category': 'jobs',
                    'event_label': jobTitle + ' - ' + companyName,
                    'job_id': jobId
                });
            }
            
            // Track with Facebook Pixel if available
            if (typeof fbq !== 'undefined') {
                fbq('track', 'Lead', {
                    content_name: jobTitle,
                    content_category: 'job_application',
                    value: 1,
                    currency: 'USD'
                });
            }
            
            // Custom event for other tracking
            $(document).trigger('cws_core_job_apply', {
                jobId: jobId,
                jobTitle: jobTitle,
                companyName: companyName
            });
        },

        /**
         * Copy job data to clipboard
         */
        copyJobData: function(e) {
            e.preventDefault();
            
            if (typeof cwsCoreJobData === 'undefined') {
                CWS_Core_Public.showNotification('No job data available to copy', 'error');
                return;
            }
            
            var jobData = JSON.stringify(cwsCoreJobData, null, 2);
            
            // Use modern clipboard API if available
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(jobData).then(function() {
                    CWS_Core_Public.showNotification('Job data copied to clipboard!', 'success');
                }).catch(function() {
                    CWS_Core_Public.fallbackCopyTextToClipboard(jobData);
                });
            } else {
                CWS_Core_Public.fallbackCopyTextToClipboard(jobData);
            }
        },

        /**
         * Fallback copy method for older browsers
         */
        fallbackCopyTextToClipboard: function(text) {
            var textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.top = '0';
            textArea.style.left = '0';
            textArea.style.position = 'fixed';
            textArea.style.opacity = '0';
            
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                var successful = document.execCommand('copy');
                if (successful) {
                    CWS_Core_Public.showNotification('Job data copied to clipboard!', 'success');
                } else {
                    CWS_Core_Public.showNotification('Failed to copy job data', 'error');
                }
            } catch (err) {
                CWS_Core_Public.showNotification('Failed to copy job data', 'error');
            }
            
            document.body.removeChild(textArea);
        },

        /**
         * Share job functionality
         */
        shareJob: function(e) {
            e.preventDefault();
            
            if (typeof cwsCoreJobData === 'undefined') {
                CWS_Core_Public.showNotification('No job data available to share', 'error');
                return;
            }
            
            var jobTitle = cwsCoreJobData.title || 'Job Opportunity';
            var companyName = cwsCoreJobData.company_name || '';
            var jobUrl = window.location.href;
            var shareText = jobTitle + ' at ' + companyName;
            
            // Use Web Share API if available
            if (navigator.share) {
                navigator.share({
                    title: jobTitle,
                    text: shareText,
                    url: jobUrl
                }).then(function() {
                    CWS_Core_Public.showNotification('Job shared successfully!', 'success');
                }).catch(function() {
                    CWS_Core_Public.showFallbackShare(jobTitle, shareText, jobUrl);
                });
            } else {
                CWS_Core_Public.showFallbackShare(jobTitle, shareText, jobUrl);
            }
        },

        /**
         * Show fallback share options
         */
        showFallbackShare: function(title, text, url) {
            var shareUrl = 'mailto:?subject=' + encodeURIComponent(title) + 
                          '&body=' + encodeURIComponent(text + '\n\n' + url);
            
            // Create share modal
            var $modal = $('<div class="cws-core-share-modal">' +
                '<div class="cws-core-share-content">' +
                '<h3>Share this job</h3>' +
                '<div class="cws-core-share-options">' +
                '<a href="' + shareUrl + '" class="cws-core-share-option">' +
                '<span class="dashicons dashicons-email"></span> Email' +
                '</a>' +
                '<a href="https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(url) + '" target="_blank" class="cws-core-share-option">' +
                '<span class="dashicons dashicons-linkedin"></span> LinkedIn' +
                '</a>' +
                '<a href="https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url) + '" target="_blank" class="cws-core-share-option">' +
                '<span class="dashicons dashicons-twitter"></span> Twitter' +
                '</a>' +
                '<button type="button" class="cws-core-share-option cws-core-copy-url" data-url="' + url + '">' +
                '<span class="dashicons dashicons-admin-links"></span> Copy Link' +
                '</button>' +
                '</div>' +
                '<button type="button" class="cws-core-modal-close">Close</button>' +
                '</div>' +
                '</div>');
            
            $('body').append($modal);
            $modal.fadeIn();
            
            // Close modal
            $modal.on('click', '.cws-core-modal-close, .cws-core-share-modal', function(e) {
                if (e.target === this) {
                    $modal.fadeOut(function() {
                        $(this).remove();
                    });
                }
            });
            
            // Copy URL
            $modal.on('click', '.cws-core-copy-url', function() {
                var url = $(this).data('url');
                CWS_Core_Public.fallbackCopyTextToClipboard(url);
            });
        },

        /**
         * Setup analytics tracking
         */
        setupAnalytics: function() {
            if (typeof cwsCoreJobData === 'undefined') {
                return;
            }
            
            // Track job view
            if (typeof gtag !== 'undefined') {
                gtag('event', 'job_view', {
                    'event_category': 'jobs',
                    'event_label': cwsCoreJobData.title + ' - ' + cwsCoreJobData.company_name,
                    'job_id': cwsCoreJobData.id
                });
            }
            
            // Track with Facebook Pixel
            if (typeof fbq !== 'undefined') {
                fbq('track', 'ViewContent', {
                    content_name: cwsCoreJobData.title,
                    content_category: 'job_posting',
                    content_type: 'job',
                    value: 1,
                    currency: 'USD'
                });
            }
            
            // Custom event for other tracking
            $(document).trigger('cws_core_job_view', {
                jobId: cwsCoreJobData.id,
                jobTitle: cwsCoreJobData.title,
                companyName: cwsCoreJobData.company_name
            });
        },

        /**
         * Show notification
         */
        showNotification: function(message, type) {
            var $notification = $('<div class="cws-core-notification cws-core-notification-' + type + '">' +
                '<span class="cws-core-notification-message">' + message + '</span>' +
                '<button type="button" class="cws-core-notification-close">&times;</button>' +
                '</div>');
            
            $('body').append($notification);
            $notification.fadeIn();
            
            // Auto-dismiss after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Manual close
            $notification.on('click', '.cws-core-notification-close', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        },

        /**
         * Format job data for display
         */
        formatJobData: function(data) {
            var formatted = {};
            
            // Format salary
            if (data.salary) {
                formatted.salary = data.salary.replace(/\$/g, '\\$');
            }
            
            // Format location
            if (data.location) {
                var locationParts = [];
                if (data.location.city) locationParts.push(data.location.city);
                if (data.location.state) locationParts.push(data.location.state);
                if (data.location.country) locationParts.push(data.location.country);
                formatted.location = locationParts.join(', ');
            }
            
            // Format date
            if (data.open_date) {
                var date = new Date(data.open_date);
                formatted.openDate = date.toLocaleDateString();
            }
            
            return formatted;
        }
    };

})(jQuery);
