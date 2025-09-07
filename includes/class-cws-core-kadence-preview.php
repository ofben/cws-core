<?php
declare(strict_types=1);

/**
 * Kadence Block Editor Preview System
 * 
 * Provides preview data and functionality for Kadence block editor
 * 
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Kadence Preview System
 */
class CWS_Core_Kadence_Preview {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private CWS_Core $plugin;

    /**
     * Sample job data for previews
     *
     * @var array
     */
    private array $sample_jobs = array();

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct(CWS_Core $plugin) {
        $this->plugin = $plugin;
        $this->init_sample_data();
    }

    /**
     * Initialize preview system
     */
    public function init(): void {
        // Add preview data for block editor
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_preview_assets'));
        
        // Add REST API endpoint for preview data
        add_action('rest_api_init', array($this, 'register_preview_endpoints'));
        
        // Add preview data to block editor
        add_filter('kadence_blocks_editor_data', array($this, 'add_preview_data'));
        
        // Handle preview requests
        add_action('wp_ajax_cws_get_preview_job', array($this, 'handle_preview_job_request'));
        add_action('wp_ajax_nopriv_cws_get_preview_job', array($this, 'handle_preview_job_request'));
        
        // Add preview mode detection
        add_filter('kadence_blocks_is_preview_mode', array($this, 'detect_preview_mode'));
        
        $this->plugin->log('Kadence preview system initialized', 'info');
    }

    /**
     * Initialize sample job data
     */
    private function init_sample_data(): void {
        $this->sample_jobs = array(
            array(
                'id' => '22026695',
                'title' => 'Senior Software Engineer',
                'company' => 'TechCorp Inc.',
                'company_name' => 'TechCorp Inc.',
                'description' => 'We are looking for a talented Senior Software Engineer to join our growing team. You will be responsible for developing and maintaining our core platform, working with modern technologies like React, Node.js, and AWS. The ideal candidate has 5+ years of experience in full-stack development and a passion for creating scalable solutions.',
                'location' => 'San Francisco, CA',
                'primary_city' => 'San Francisco',
                'primary_state' => 'CA',
                'primary_country' => 'US',
                'department' => 'Engineering',
                'category' => 'Technology',
                'primary_category' => 'Technology',
                'industry' => 'Software Development',
                'function' => 'Engineering',
                'salary' => '$120,000 - $160,000',
                'employment_type' => 'Full-time',
                'job_type' => 'Permanent',
                'status' => 'Open',
                'entity_status' => 'Open',
                'url' => 'https://techcorp.com/careers/senior-software-engineer',
                'seo_url' => 'https://techcorp.com/careers/senior-software-engineer',
                'open_date' => '2024-01-15T10:00:00Z',
                'update_date' => '2024-01-20T14:30:00Z'
            ),
            array(
                'id' => '22026696',
                'title' => 'Product Marketing Manager',
                'company' => 'InnovateLabs',
                'company_name' => 'InnovateLabs',
                'description' => 'Join our dynamic marketing team as a Product Marketing Manager. You will be responsible for developing go-to-market strategies, creating compelling product messaging, and collaborating with cross-functional teams to drive product adoption. Experience with SaaS products and B2B marketing is preferred.',
                'location' => 'New York, NY',
                'primary_city' => 'New York',
                'primary_state' => 'NY',
                'primary_country' => 'US',
                'department' => 'Marketing',
                'category' => 'Marketing',
                'primary_category' => 'Marketing',
                'industry' => 'Technology',
                'function' => 'Marketing',
                'salary' => '$90,000 - $120,000',
                'employment_type' => 'Full-time',
                'job_type' => 'Permanent',
                'status' => 'Open',
                'entity_status' => 'Open',
                'url' => 'https://innovatelabs.com/careers/product-marketing-manager',
                'seo_url' => 'https://innovatelabs.com/careers/product-marketing-manager',
                'open_date' => '2024-01-18T09:00:00Z',
                'update_date' => '2024-01-22T11:15:00Z'
            ),
            array(
                'id' => '22026697',
                'title' => 'UX Designer',
                'company' => 'DesignStudio Pro',
                'company_name' => 'DesignStudio Pro',
                'description' => 'We are seeking a creative UX Designer to join our design team. You will be responsible for creating intuitive user experiences, conducting user research, and collaborating with product and engineering teams. Proficiency in Figma, Sketch, and user research methodologies is required.',
                'location' => 'Remote',
                'primary_city' => 'Remote',
                'primary_state' => 'Remote',
                'primary_country' => 'US',
                'department' => 'Design',
                'category' => 'Design',
                'primary_category' => 'Design',
                'industry' => 'Creative Services',
                'function' => 'Design',
                'salary' => '$80,000 - $110,000',
                'employment_type' => 'Full-time',
                'job_type' => 'Permanent',
                'status' => 'Open',
                'entity_status' => 'Open',
                'url' => 'https://designstudiopro.com/careers/ux-designer',
                'seo_url' => 'https://designstudiopro.com/careers/ux-designer',
                'open_date' => '2024-01-20T08:30:00Z',
                'update_date' => '2024-01-23T16:45:00Z'
            ),
            array(
                'id' => '22026698',
                'title' => 'Data Scientist',
                'company' => 'Analytics Solutions',
                'company_name' => 'Analytics Solutions',
                'description' => 'Join our data science team to help drive insights and build machine learning models. You will work with large datasets, develop predictive models, and collaborate with stakeholders to solve complex business problems. Experience with Python, R, SQL, and machine learning frameworks is essential.',
                'location' => 'Boston, MA',
                'primary_city' => 'Boston',
                'primary_state' => 'MA',
                'primary_country' => 'US',
                'department' => 'Data Science',
                'category' => 'Technology',
                'primary_category' => 'Technology',
                'industry' => 'Analytics',
                'function' => 'Data Science',
                'salary' => '$110,000 - $140,000',
                'employment_type' => 'Full-time',
                'job_type' => 'Permanent',
                'status' => 'Open',
                'entity_status' => 'Open',
                'url' => 'https://analyticssolutions.com/careers/data-scientist',
                'seo_url' => 'https://analyticssolutions.com/careers/data-scientist',
                'open_date' => '2024-01-22T12:00:00Z',
                'update_date' => '2024-01-24T10:20:00Z'
            ),
            array(
                'id' => '22026699',
                'title' => 'Sales Development Representative',
                'company' => 'GrowthTech',
                'company_name' => 'GrowthTech',
                'description' => 'We are looking for a motivated Sales Development Representative to join our sales team. You will be responsible for prospecting new leads, qualifying opportunities, and setting up meetings for our sales team. This is a great opportunity to start your career in sales with a fast-growing company.',
                'location' => 'Chicago, IL',
                'primary_city' => 'Chicago',
                'primary_state' => 'IL',
                'primary_country' => 'US',
                'department' => 'Sales',
                'category' => 'Sales',
                'primary_category' => 'Sales',
                'industry' => 'Technology',
                'function' => 'Sales',
                'salary' => '$50,000 - $70,000 + Commission',
                'employment_type' => 'Full-time',
                'job_type' => 'Permanent',
                'status' => 'Open',
                'entity_status' => 'Open',
                'url' => 'https://growthtech.com/careers/sales-development-representative',
                'seo_url' => 'https://growthtech.com/careers/sales-development-representative',
                'open_date' => '2024-01-25T14:00:00Z',
                'update_date' => '2024-01-26T09:30:00Z'
            )
        );
    }

    /**
     * Enqueue preview assets
     */
    public function enqueue_preview_assets(): void {
        wp_enqueue_script(
            'cws-kadence-preview',
            CWS_CORE_PLUGIN_URL . 'public/js/kadence-preview.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components'),
            CWS_CORE_VERSION,
            true
        );

        wp_enqueue_style(
            'cws-kadence-preview',
            CWS_CORE_PLUGIN_URL . 'public/css/kadence-preview.css',
            array('wp-edit-blocks'),
            CWS_CORE_VERSION
        );

        // Localize script with preview data
        wp_localize_script('cws-kadence-preview', 'cwsPreviewData', array(
            'sampleJobs' => $this->sample_jobs,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cws_preview_nonce'),
            'isPreviewMode' => $this->is_preview_mode()
        ));
    }

    /**
     * Register preview REST API endpoints
     */
    public function register_preview_endpoints(): void {
        register_rest_route('cws-core/v1', '/preview/jobs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_preview_jobs'),
            'permission_callback' => array($this, 'check_preview_permissions')
        ));

        register_rest_route('cws-core/v1', '/preview/job/(?P<id>[a-zA-Z0-9-]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_preview_job'),
            'permission_callback' => array($this, 'check_preview_permissions'),
            'args' => array(
                'id' => array(
                    'required' => true,
                    'type' => 'string'
                )
            )
        ));

        register_rest_route('cws-core/v1', '/preview/fields', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_preview_fields'),
            'permission_callback' => array($this, 'check_preview_permissions')
        ));
    }

    /**
     * Add preview data to block editor
     */
    public function add_preview_data($data): array {
        $data['cwsPreview'] = array(
            'sampleJobs' => $this->sample_jobs,
            'isPreviewMode' => $this->is_preview_mode(),
            'previewUrl' => rest_url('cws-core/v1/preview/')
        );
        
        return $data;
    }

    /**
     * Handle preview job request
     */
    public function handle_preview_job_request(): void {
        check_ajax_referer('cws_preview_nonce', 'nonce');
        
        $job_id = sanitize_text_field($_POST['job_id'] ?? '');
        $job_data = $this->get_sample_job($job_id);
        
        if ($job_data) {
            wp_send_json_success($job_data);
        } else {
            wp_send_json_error('Job not found');
        }
    }

    /**
     * Detect preview mode
     */
    public function detect_preview_mode($is_preview): bool {
        // Check if we're in block editor
        if (is_admin() && isset($_GET['post']) && get_post_type($_GET['post']) === 'cws_job') {
            return true;
        }
        
        // Check if we're in Kadence block editor
        if (isset($_GET['kadence_blocks_preview'])) {
            return true;
        }
        
        return $is_preview;
    }

    /**
     * Check preview permissions
     */
    public function check_preview_permissions(): bool {
        return current_user_can('edit_posts');
    }

    /**
     * Get preview jobs
     */
    public function get_preview_jobs(\WP_REST_Request $request): \WP_REST_Response {
        $limit = $request->get_param('limit') ?: 10;
        $offset = $request->get_param('offset') ?: 0;
        
        $jobs = array_slice($this->sample_jobs, $offset, $limit);
        
        return new \WP_REST_Response(array(
            'jobs' => $jobs,
            'total' => count($this->sample_jobs),
            'limit' => $limit,
            'offset' => $offset
        ));
    }

    /**
     * Get preview job
     */
    public function get_preview_job(\WP_REST_Request $request): \WP_REST_Response {
        $job_id = $request->get_param('id');
        $job_data = $this->get_sample_job($job_id);
        
        if ($job_data) {
            return new \WP_REST_Response($job_data);
        } else {
            return new \WP_REST_Response(array('error' => 'Job not found'), 404);
        }
    }

    /**
     * Get preview fields
     */
    public function get_preview_fields(\WP_REST_Request $request): \WP_REST_Response {
        $fields = array();
        
        foreach ($this->sample_jobs[0] as $key => $value) {
            $fields[] = array(
                'key' => 'cws_job_' . $key,
                'label' => ucwords(str_replace('_', ' ', $key)),
                'value' => $value,
                'type' => $this->get_field_type($value)
            );
        }
        
        return new \WP_REST_Response($fields);
    }

    /**
     * Get sample job by ID
     */
    private function get_sample_job(string $job_id): ?array {
        foreach ($this->sample_jobs as $job) {
            if ($job['id'] === $job_id) {
                return $job;
            }
        }
        
        return null;
    }

    /**
     * Get field type based on value
     */
    private function get_field_type($value): string {
        if (is_numeric($value)) {
            return 'number';
        } elseif (filter_var($value, FILTER_VALIDATE_URL)) {
            return 'url';
        } elseif (strtotime($value) !== false) {
            return 'date';
        } elseif (strlen($value) > 100) {
            return 'html';
        } else {
            return 'text';
        }
    }

    /**
     * Check if we're in preview mode
     */
    private function is_preview_mode(): bool {
        return is_admin() || 
               (isset($_GET['kadence_blocks_preview']) && $_GET['kadence_blocks_preview'] === '1') ||
               (isset($_GET['preview']) && $_GET['preview'] === 'true');
    }

    /**
     * Get sample jobs
     */
    public function get_sample_jobs(): array {
        return $this->sample_jobs;
    }

    /**
     * Get random sample job
     */
    public function get_random_sample_job(): array {
        return $this->sample_jobs[array_rand($this->sample_jobs)];
    }

    /**
     * Get sample job by category
     */
    public function get_sample_jobs_by_category(string $category): array {
        return array_filter($this->sample_jobs, function($job) use ($category) {
            return $job['primary_category'] === $category;
        });
    }

    /**
     * Get sample job by location
     */
    public function get_sample_jobs_by_location(string $location): array {
        return array_filter($this->sample_jobs, function($job) use ($location) {
            return $job['primary_city'] === $location || $job['location'] === $location;
        });
    }
}
