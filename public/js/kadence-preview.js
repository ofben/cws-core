/**
 * Kadence Block Editor Preview System
 * 
 * @package CWS_Core
 */

(function() {
    'use strict';

    // CWS Kadence Preview Object
    window.CWSKadencePreview = {
        
        /**
         * Initialize preview system
         */
        init: function() {
            this.bindEvents();
            this.setupPreviewMode();
            this.initializeDynamicFields();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Listen for block editor changes
            if (wp && wp.data) {
                wp.data.subscribe(() => {
                    this.handleBlockEditorChanges();
                });
            }

            // Handle preview requests
            document.addEventListener('click', (e) => {
                if (e.target.matches('.cws-preview-button')) {
                    this.handlePreviewRequest(e);
                }
            });
        },

        /**
         * Setup preview mode
         */
        setupPreviewMode: function() {
            if (cwsPreviewData && cwsPreviewData.isPreviewMode) {
                document.body.classList.add('cws-preview-mode');
                this.enablePreviewFeatures();
            }
        },

        /**
         * Initialize dynamic fields
         */
        initializeDynamicFields: function() {
            if (cwsPreviewData && cwsPreviewData.sampleJobs) {
                this.registerDynamicFields();
                this.setupFieldPreviews();
            }
        },

        /**
         * Register dynamic fields with Kadence
         */
        registerDynamicFields: function() {
            if (window.kadence_blocks && window.kadence_blocks.registerDynamicField) {
                const fields = this.getDynamicFieldsConfig();
                
                fields.forEach(field => {
                    window.kadence_blocks.registerDynamicField('cws_job', field.key, {
                        label: field.label,
                        type: field.type,
                        preview: field.preview,
                        callback: (postId) => this.getFieldValue(field.key, postId)
                    });
                });
            }
        },

        /**
         * Get dynamic fields configuration
         */
        getDynamicFieldsConfig: function() {
            const sampleJob = cwsPreviewData.sampleJobs[0];
            const fields = [];

            Object.keys(sampleJob).forEach(key => {
                const fieldKey = 'cws_job_' + key;
                fields.push({
                    key: fieldKey,
                    label: this.formatFieldLabel(key),
                    type: this.getFieldType(sampleJob[key]),
                    preview: sampleJob[key]
                });
            });

            return fields;
        },

        /**
         * Format field label
         */
        formatFieldLabel: function(key) {
            return key.replace(/_/g, ' ')
                    .replace(/\b\w/g, l => l.toUpperCase());
        },

        /**
         * Get field type based on value
         */
        getFieldType: function(value) {
            if (typeof value === 'number') {
                return 'number';
            } else if (this.isUrl(value)) {
                return 'url';
            } else if (this.isDate(value)) {
                return 'date';
            } else if (typeof value === 'string' && value.length > 100) {
                return 'html';
            } else {
                return 'text';
            }
        },

        /**
         * Check if value is a URL
         */
        isUrl: function(value) {
            try {
                new URL(value);
                return true;
            } catch {
                return false;
            }
        },

        /**
         * Check if value is a date
         */
        isDate: function(value) {
            return !isNaN(Date.parse(value));
        },

        /**
         * Get field value for preview
         */
        getFieldValue: function(fieldKey, postId) {
            // In preview mode, return sample data
            if (cwsPreviewData && cwsPreviewData.isPreviewMode) {
                const sampleJob = this.getRandomSampleJob();
                const key = fieldKey.replace('cws_job_', '');
                return sampleJob[key] || '';
            }

            // In real mode, get actual meta value
            return this.getRealFieldValue(fieldKey, postId);
        },

        /**
         * Get real field value from meta
         */
        getRealFieldValue: function(fieldKey, postId) {
            // This would typically make an AJAX call to get the real value
            // For now, return empty string
            return '';
        },

        /**
         * Get random sample job
         */
        getRandomSampleJob: function() {
            if (!cwsPreviewData || !cwsPreviewData.sampleJobs) {
                return {};
            }
            
            const jobs = cwsPreviewData.sampleJobs;
            return jobs[Math.floor(Math.random() * jobs.length)];
        },

        /**
         * Setup field previews
         */
        setupFieldPreviews: function() {
            // Add preview data to block editor
            if (window.kadence_blocks && window.kadence_blocks.setPreviewData) {
                window.kadence_blocks.setPreviewData('cws_job', cwsPreviewData.sampleJobs);
            }
        },

        /**
         * Handle block editor changes
         */
        handleBlockEditorChanges: function() {
            // Update preview when blocks change
            this.updatePreview();
        },

        /**
         * Update preview
         */
        updatePreview: function() {
            // Update preview content based on current block configuration
            const previewContainer = document.querySelector('.cws-preview-container');
            if (previewContainer) {
                this.renderPreview(previewContainer);
            }
        },

        /**
         * Render preview
         */
        renderPreview: function(container) {
            const sampleJob = this.getRandomSampleJob();
            const previewHtml = this.generatePreviewHtml(sampleJob);
            
            container.innerHTML = previewHtml;
        },

        /**
         * Generate preview HTML
         */
        generatePreviewHtml: function(job) {
            return `
                <div class="cws-job-preview">
                    <h3 class="cws-job-preview__title">${job.title}</h3>
                    <div class="cws-job-preview__company">${job.company}</div>
                    <div class="cws-job-preview__location">${job.location}</div>
                    <div class="cws-job-preview__salary">${job.salary}</div>
                    <div class="cws-job-preview__description">${job.description}</div>
                </div>
            `;
        },

        /**
         * Handle preview request
         */
        handlePreviewRequest: function(e) {
            e.preventDefault();
            
            const button = e.target;
            const jobId = button.dataset.jobId;
            
            this.loadPreviewJob(jobId);
        },

        /**
         * Load preview job
         */
        loadPreviewJob: function(jobId) {
            if (!cwsPreviewData || !cwsPreviewData.ajaxUrl) {
                return;
            }

            const data = new FormData();
            data.append('action', 'cws_get_preview_job');
            data.append('job_id', jobId);
            data.append('nonce', cwsPreviewData.nonce);

            fetch(cwsPreviewData.ajaxUrl, {
                method: 'POST',
                body: data
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.displayPreviewJob(data.data);
                } else {
                    console.error('Preview error:', data.data);
                }
            })
            .catch(error => {
                console.error('Preview request failed:', error);
            });
        },

        /**
         * Display preview job
         */
        displayPreviewJob: function(jobData) {
            const previewContainer = document.querySelector('.cws-preview-container');
            if (previewContainer) {
                previewContainer.innerHTML = this.generatePreviewHtml(jobData);
            }
        },

        /**
         * Enable preview features
         */
        enablePreviewFeatures: function() {
            // Add preview controls
            this.addPreviewControls();
            
            // Setup preview data
            this.setupPreviewData();
        },

        /**
         * Add preview controls
         */
        addPreviewControls: function() {
            const controlsHtml = `
                <div class="cws-preview-controls">
                    <button class="cws-preview-button" data-job-id="22026695">Preview Job 1</button>
                    <button class="cws-preview-button" data-job-id="22026696">Preview Job 2</button>
                    <button class="cws-preview-button" data-job-id="22026697">Preview Job 3</button>
                    <button class="cws-preview-button" data-job-id="22026698">Preview Job 4</button>
                    <button class="cws-preview-button" data-job-id="22026699">Preview Job 5</button>
                </div>
            `;
            
            const editor = document.querySelector('.block-editor');
            if (editor) {
                editor.insertAdjacentHTML('afterbegin', controlsHtml);
            }
        },

        /**
         * Setup preview data
         */
        setupPreviewData: function() {
            // Make sample jobs available globally
            window.cwsSampleJobs = cwsPreviewData.sampleJobs;
            
            // Setup field previews
            this.setupFieldPreviews();
        },

        /**
         * Get available job categories
         */
        getJobCategories: function() {
            const categories = new Set();
            
            if (cwsPreviewData && cwsPreviewData.sampleJobs) {
                cwsPreviewData.sampleJobs.forEach(job => {
                    categories.add(job.primary_category);
                });
            }
            
            return Array.from(categories);
        },

        /**
         * Get available job locations
         */
        getJobLocations: function() {
            const locations = new Set();
            
            if (cwsPreviewData && cwsPreviewData.sampleJobs) {
                cwsPreviewData.sampleJobs.forEach(job => {
                    locations.add(job.primary_city);
                });
            }
            
            return Array.from(locations);
        },

        /**
         * Get available companies
         */
        getCompanies: function() {
            const companies = new Set();
            
            if (cwsPreviewData && cwsPreviewData.sampleJobs) {
                cwsPreviewData.sampleJobs.forEach(job => {
                    companies.add(job.company);
                });
            }
            
            return Array.from(companies);
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            CWSKadencePreview.init();
        });
    } else {
        CWSKadencePreview.init();
    }

    // Expose to global scope
    window.CWSKadencePreview = CWSKadencePreview;

})();
