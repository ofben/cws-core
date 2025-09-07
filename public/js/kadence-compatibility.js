/**
 * Kadence Theme Compatibility JavaScript
 * 
 * @package CWS_Core
 */

(function($) {
    'use strict';

    // CWS Kadence Compatibility Object
    window.CWSKadence = {
        
        /**
         * Initialize Kadence compatibility features
         */
        init: function() {
            this.bindEvents();
            this.initializeJobFeatures();
            this.setupAjaxHandlers();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Job application tracking
            $(document).on('click', '.cws-job-apply-button', this.trackJobApplication);
            
            // Job view tracking
            if (cwsKadence.isJobPage) {
                this.trackJobView();
            }
            
            // Lazy loading for job images
            this.setupLazyLoading();
            
            // Smooth scrolling for job sections
            this.setupSmoothScrolling();
        },

        /**
         * Initialize job-specific features
         */
        initializeJobFeatures: function() {
            // Add job-specific classes to body
            if (cwsKadence.isJobPage) {
                $('body').addClass('cws-job-page');
                
                // Add job ID to body for CSS targeting
                if (cwsKadence.jobId) {
                    $('body').addClass('cws-job-' + cwsKadence.jobId);
                }
            }
            
            // Initialize job meta tooltips
            this.initializeTooltips();
            
            // Setup job sharing
            this.setupJobSharing();
        },

        /**
         * Setup AJAX handlers
         */
        setupAjaxHandlers: function() {
            // Handle job application form submissions
            $(document).on('submit', '.cws-job-application-form', this.handleJobApplication);
            
            // Handle job bookmarking
            $(document).on('click', '.cws-job-bookmark', this.handleJobBookmark);
            
            // Handle job sharing
            $(document).on('click', '.cws-job-share', this.handleJobShare);
        },

        /**
         * Track job application clicks
         */
        trackJobApplication: function(e) {
            var jobId = cwsKadence.jobId;
            var jobTitle = $('.entry-title').text();
            var company = $('.cws-job-company').text();
            
            // Send tracking data
            $.ajax({
                url: cwsKadence.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_track_job_application',
                    job_id: jobId,
                    job_title: jobTitle,
                    company: company,
                    nonce: cwsKadence.nonce
                },
                success: function(response) {
                    console.log('Job application tracked:', response);
                }
            });
        },

        /**
         * Track job page views
         */
        trackJobView: function() {
            var jobId = cwsKadence.jobId;
            var jobTitle = $('.entry-title').text();
            var company = $('.cws-job-company').text();
            
            // Send tracking data
            $.ajax({
                url: cwsKadence.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_track_job_view',
                    job_id: jobId,
                    job_title: jobTitle,
                    company: company,
                    nonce: cwsKadence.nonce
                },
                success: function(response) {
                    console.log('Job view tracked:', response);
                }
            });
        },

        /**
         * Setup lazy loading for job images
         */
        setupLazyLoading: function() {
            if ('IntersectionObserver' in window) {
                var imageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var img = entry.target;
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            imageObserver.unobserve(img);
                        }
                    });
                });

                document.querySelectorAll('img[data-src]').forEach(function(img) {
                    imageObserver.observe(img);
                });
            }
        },

        /**
         * Setup smooth scrolling for job sections
         */
        setupSmoothScrolling: function() {
            $('a[href*="#"]').on('click', function(e) {
                var target = $(this.getAttribute('href'));
                if (target.length) {
                    e.preventDefault();
                    $('html, body').animate({
                        scrollTop: target.offset().top - 100
                    }, 800);
                }
            });
        },

        /**
         * Initialize tooltips for job meta
         */
        initializeTooltips: function() {
            $('.cws-job-meta-item').each(function() {
                var $this = $(this);
                var title = $this.attr('title');
                if (title) {
                    $this.tooltip({
                        placement: 'top',
                        trigger: 'hover'
                    });
                }
            });
        },

        /**
         * Setup job sharing functionality
         */
        setupJobSharing: function() {
            // Add social sharing buttons
            var shareButtons = this.generateShareButtons();
            $('.cws-job-meta-kadence').after(shareButtons);
        },

        /**
         * Generate social sharing buttons
         */
        generateShareButtons: function() {
            var jobUrl = window.location.href;
            var jobTitle = $('.entry-title').text();
            var encodedUrl = encodeURIComponent(jobUrl);
            var encodedTitle = encodeURIComponent(jobTitle);
            
            return `
                <div class="cws-job-sharing">
                    <h4>Share this job:</h4>
                    <div class="share-buttons">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}" 
                           target="_blank" class="share-facebook" title="Share on Facebook">
                            <i class="fab fa-facebook-f"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}" 
                           target="_blank" class="share-twitter" title="Share on Twitter">
                            <i class="fab fa-twitter"></i> Twitter
                        </a>
                        <a href="https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}" 
                           target="_blank" class="share-linkedin" title="Share on LinkedIn">
                            <i class="fab fa-linkedin-in"></i> LinkedIn
                        </a>
                        <button class="share-copy" title="Copy link">
                            <i class="fas fa-copy"></i> Copy Link
                        </button>
                    </div>
                </div>
            `;
        },

        /**
         * Handle job application form submission
         */
        handleJobApplication: function(e) {
            e.preventDefault();
            
            var $form = $(this);
            var $submitBtn = $form.find('button[type="submit"]');
            var originalText = $submitBtn.text();
            
            // Show loading state
            $submitBtn.text('Applying...').prop('disabled', true);
            
            // Submit form data
            $.ajax({
                url: cwsKadence.ajaxUrl,
                type: 'POST',
                data: $form.serialize() + '&action=cws_submit_job_application&nonce=' + cwsKadence.nonce,
                success: function(response) {
                    if (response.success) {
                        $form.html('<div class="success-message">Application submitted successfully!</div>');
                    } else {
                        $form.html('<div class="error-message">Error: ' + response.data + '</div>');
                    }
                },
                error: function() {
                    $submitBtn.text(originalText).prop('disabled', false);
                    $form.html('<div class="error-message">An error occurred. Please try again.</div>');
                }
            });
        },

        /**
         * Handle job bookmarking
         */
        handleJobBookmark: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var jobId = cwsKadence.jobId;
            var isBookmarked = $btn.hasClass('bookmarked');
            
            $.ajax({
                url: cwsKadence.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cws_toggle_job_bookmark',
                    job_id: jobId,
                    nonce: cwsKadence.nonce
                },
                success: function(response) {
                    if (response.success) {
                        if (isBookmarked) {
                            $btn.removeClass('bookmarked').text('Bookmark');
                        } else {
                            $btn.addClass('bookmarked').text('Bookmarked');
                        }
                    }
                }
            });
        },

        /**
         * Handle job sharing
         */
        handleJobShare: function(e) {
            e.preventDefault();
            
            var $btn = $(this);
            var shareType = $btn.data('share');
            var jobUrl = window.location.href;
            var jobTitle = $('.entry-title').text();
            
            switch (shareType) {
                case 'copy':
                    navigator.clipboard.writeText(jobUrl).then(function() {
                        $btn.text('Copied!');
                        setTimeout(function() {
                            $btn.text('Copy Link');
                        }, 2000);
                    });
                    break;
                case 'email':
                    var subject = 'Job Opportunity: ' + jobTitle;
                    var body = 'Check out this job opportunity: ' + jobUrl;
                    window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
                    break;
            }
        },

        /**
         * Utility function to format job data
         */
        formatJobData: function(data) {
            return {
                id: data.id,
                title: data.title,
                company: data.company_name,
                location: data.primary_city + ', ' + data.primary_state,
                department: data.department,
                category: data.primary_category,
                status: data.entity_status,
                url: data.url,
                openDate: data.open_date,
                updateDate: data.update_date
            };
        },

        /**
         * Utility function to validate job data
         */
        validateJobData: function(data) {
            var required = ['id', 'title', 'company_name'];
            var missing = required.filter(function(field) {
                return !data[field];
            });
            
            if (missing.length > 0) {
                console.warn('Missing required job data fields:', missing);
                return false;
            }
            
            return true;
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CWSKadence.init();
    });

    // Expose to global scope for debugging
    window.CWSKadence = CWSKadence;

})(jQuery);
