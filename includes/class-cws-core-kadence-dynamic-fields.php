<?php
declare(strict_types=1);

/**
 * Kadence Dynamic Content Fields Integration
 * 
 * Integrates CWS Core job data with Kadence Blocks dynamic content system
 * 
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Kadence Dynamic Fields Integration
 */
class CWS_Core_Kadence_Dynamic_Fields {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private CWS_Core $plugin;

    /**
     * Available dynamic fields
     *
     * @var array
     */
    private array $dynamic_fields = array();

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct(CWS_Core $plugin) {
        $this->plugin = $plugin;
        $this->init_dynamic_fields();
    }

    /**
     * Initialize dynamic fields
     */
    public function init(): void {
        // Register dynamic content fields with Kadence
        add_filter('kadence_blocks_dynamic_content_fields', array($this, 'register_dynamic_fields'));
        
        // Register dynamic content sources
        add_filter('kadence_blocks_dynamic_content_sources', array($this, 'register_content_sources'));
        
        // Handle dynamic content rendering
        add_filter('kadence_blocks_render_dynamic_content', array($this, 'render_dynamic_content'), 10, 3);
        
        // Add preview data for block editor
        add_filter('kadence_blocks_preview_data', array($this, 'add_preview_data'));
        
        // Register custom field groups
        add_action('init', array($this, 'register_field_groups'));
        
        // Register REST API fields for Kadence
        add_action('rest_api_init', array($this, 'register_rest_fields'));
        
        // Add support for Kadence Query Cards
        add_filter('kadence_blocks_query_card_sources', array($this, 'add_query_card_sources'));
        
        // Register custom meta fields for REST API
        add_action('init', array($this, 'register_meta_fields'));
        
        $this->plugin->log('Kadence dynamic fields integration initialized', 'info');
    }

    /**
     * Initialize available dynamic fields
     */
    private function init_dynamic_fields(): void {
        $this->dynamic_fields = array(
            // Basic Job Information
            'cws_job_id' => array(
                'label' => 'Job ID',
                'description' => 'Unique job identifier',
                'type' => 'text',
                'group' => 'basic',
                'preview' => '22026695'
            ),
            'cws_job_title' => array(
                'label' => 'Job Title',
                'description' => 'Full job title',
                'type' => 'text',
                'group' => 'basic',
                'preview' => 'Senior Software Engineer'
            ),
            'cws_job_company' => array(
                'label' => 'Company Name',
                'description' => 'Hiring company name',
                'type' => 'text',
                'group' => 'basic',
                'preview' => 'TechCorp Inc.'
            ),
            'cws_job_company_name' => array(
                'label' => 'Company Name (Alt)',
                'description' => 'Alternative company name field',
                'type' => 'text',
                'group' => 'basic',
                'preview' => 'TechCorp Inc.'
            ),
            
            // Location Information
            'cws_job_location' => array(
                'label' => 'Job Location',
                'description' => 'Formatted location (City, State)',
                'type' => 'text',
                'group' => 'location',
                'preview' => 'New York, NY'
            ),
            'cws_job_primary_city' => array(
                'label' => 'City',
                'description' => 'Primary city',
                'type' => 'text',
                'group' => 'location',
                'preview' => 'New York'
            ),
            'cws_job_primary_state' => array(
                'label' => 'State',
                'description' => 'Primary state',
                'type' => 'text',
                'group' => 'location',
                'preview' => 'NY'
            ),
            'cws_job_primary_country' => array(
                'label' => 'Country',
                'description' => 'Primary country',
                'type' => 'text',
                'group' => 'location',
                'preview' => 'US'
            ),
            
            // Job Details
            'cws_job_department' => array(
                'label' => 'Department',
                'description' => 'Job department',
                'type' => 'text',
                'group' => 'details',
                'preview' => 'Engineering'
            ),
            'cws_job_category' => array(
                'label' => 'Category',
                'description' => 'Job category',
                'type' => 'text',
                'group' => 'details',
                'preview' => 'Technology'
            ),
            'cws_job_primary_category' => array(
                'label' => 'Primary Category',
                'description' => 'Primary job category',
                'type' => 'text',
                'group' => 'details',
                'preview' => 'Technology'
            ),
            'cws_job_industry' => array(
                'label' => 'Industry',
                'description' => 'Job industry',
                'type' => 'text',
                'group' => 'details',
                'preview' => 'Software Development'
            ),
            'cws_job_function' => array(
                'label' => 'Function',
                'description' => 'Job function',
                'type' => 'text',
                'group' => 'details',
                'preview' => 'Engineering'
            ),
            
            // Employment Details
            'cws_job_salary' => array(
                'label' => 'Salary',
                'description' => 'Salary information',
                'type' => 'text',
                'group' => 'employment',
                'preview' => '$80,000 - $120,000'
            ),
            'cws_job_employment_type' => array(
                'label' => 'Employment Type',
                'description' => 'Type of employment',
                'type' => 'text',
                'group' => 'employment',
                'preview' => 'Full-time'
            ),
            'cws_job_type' => array(
                'label' => 'Job Type',
                'description' => 'Job type classification',
                'type' => 'text',
                'group' => 'employment',
                'preview' => 'Permanent'
            ),
            'cws_job_status' => array(
                'label' => 'Status',
                'description' => 'Job status',
                'type' => 'text',
                'group' => 'employment',
                'preview' => 'Open'
            ),
            'cws_job_entity_status' => array(
                'label' => 'Entity Status',
                'description' => 'Entity status',
                'type' => 'text',
                'group' => 'employment',
                'preview' => 'Open'
            ),
            
            // URLs and Links
            'cws_job_url' => array(
                'label' => 'Application URL',
                'description' => 'Direct application URL',
                'type' => 'url',
                'group' => 'links',
                'preview' => 'https://company.com/apply/123'
            ),
            'cws_job_seo_url' => array(
                'label' => 'SEO URL',
                'description' => 'SEO-friendly application URL',
                'type' => 'url',
                'group' => 'links',
                'preview' => 'https://company.com/careers/software-engineer'
            ),
            
            // Dates
            'cws_job_open_date' => array(
                'label' => 'Open Date',
                'description' => 'Date job was opened',
                'type' => 'date',
                'group' => 'dates',
                'preview' => '2024-01-15'
            ),
            'cws_job_update_date' => array(
                'label' => 'Update Date',
                'description' => 'Date job was last updated',
                'type' => 'date',
                'group' => 'dates',
                'preview' => '2024-01-20'
            ),
            
            // Content
            'cws_job_description' => array(
                'label' => 'Job Description',
                'description' => 'Full job description',
                'type' => 'html',
                'group' => 'content',
                'preview' => 'We are looking for a talented software engineer to join our team...'
            ),
            
            // Computed Fields
            'cws_job_days_open' => array(
                'label' => 'Days Open',
                'description' => 'Number of days job has been open',
                'type' => 'number',
                'group' => 'computed',
                'preview' => '15'
            ),
            'cws_job_location_formatted' => array(
                'label' => 'Location (Formatted)',
                'description' => 'Formatted location with country',
                'type' => 'text',
                'group' => 'computed',
                'preview' => 'New York, NY, US'
            ),
            'cws_job_salary_formatted' => array(
                'label' => 'Salary (Formatted)',
                'description' => 'Formatted salary with currency',
                'type' => 'text',
                'group' => 'computed',
                'preview' => '$80,000 - $120,000 per year'
            )
        );
    }

    /**
     * Register dynamic fields with Kadence
     */
    public function register_dynamic_fields($fields): array {
        $cws_fields = array();
        
        foreach ($this->dynamic_fields as $key => $field) {
            $cws_fields[$key] = array(
                'label' => $field['label'],
                'description' => $field['description'],
                'type' => $field['type'],
                'group' => 'CWS Job Data',
                'source' => 'cws_job',
                'callback' => array($this, 'get_field_value'),
                'preview' => $field['preview']
            );
        }
        
        return array_merge($fields, $cws_fields);
    }

    /**
     * Register content sources
     */
    public function register_content_sources($sources): array {
        $sources['cws_job'] = array(
            'label' => 'CWS Job Data',
            'description' => 'Job data from CWS Core plugin',
            'post_types' => array('cws_job'),
            'fields' => array_keys($this->dynamic_fields)
        );
        
        return $sources;
    }

    /**
     * Render dynamic content
     */
    public function render_dynamic_content($content, $field, $post_id): string {
        if (strpos($field, 'cws_job_') !== 0) {
            return $content;
        }
        
        $post_type = get_post_type($post_id);
        if ($post_type !== 'cws_job') {
            return $content;
        }
        
        $value = $this->get_field_value($field, $post_id);
        
        // Format based on field type
        $field_config = $this->dynamic_fields[$field] ?? null;
        if ($field_config) {
            $value = $this->format_field_value($value, $field_config);
        }
        
        return $value;
    }

    /**
     * Get field value
     */
    public function get_field_value($field, $post_id): string {
        // Handle computed fields
        if ($field === 'cws_job_days_open') {
            return $this->get_days_open($post_id);
        }
        
        if ($field === 'cws_job_location_formatted') {
            return $this->get_formatted_location($post_id);
        }
        
        if ($field === 'cws_job_salary_formatted') {
            return $this->get_formatted_salary($post_id);
        }
        
        // Get standard meta field
        $value = get_post_meta($post_id, $field, true);
        
        return $value ?: '';
    }

    /**
     * Format field value based on type
     */
    private function format_field_value($value, $field_config): string {
        if (empty($value)) {
            return '';
        }
        
        switch ($field_config['type']) {
            case 'date':
                return $this->format_date($value);
            case 'url':
                return esc_url($value);
            case 'html':
                return wp_kses_post($value);
            case 'number':
                return number_format($value);
            default:
                return esc_html($value);
        }
    }

    /**
     * Format date field
     */
    private function format_date($date): string {
        if (empty($date)) {
            return '';
        }
        
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        
        return date('F j, Y', $timestamp);
    }

    /**
     * Get days open
     */
    private function get_days_open($post_id): string {
        $open_date = get_post_meta($post_id, 'cws_job_open_date', true);
        if (empty($open_date)) {
            return '0';
        }
        
        $open_timestamp = strtotime($open_date);
        if ($open_timestamp === false) {
            return '0';
        }
        
        $days = floor((time() - $open_timestamp) / DAY_IN_SECONDS);
        return (string) max(0, $days);
    }

    /**
     * Get formatted location
     */
    private function get_formatted_location($post_id): string {
        $city = get_post_meta($post_id, 'cws_job_primary_city', true);
        $state = get_post_meta($post_id, 'cws_job_primary_state', true);
        $country = get_post_meta($post_id, 'cws_job_primary_country', true);
        
        $parts = array_filter(array($city, $state, $country));
        return implode(', ', $parts);
    }

    /**
     * Get formatted salary
     */
    private function get_formatted_salary($post_id): string {
        $salary = get_post_meta($post_id, 'cws_job_salary', true);
        if (empty($salary)) {
            return '';
        }
        
        // Add "per year" if not already present
        if (stripos($salary, 'per') === false && stripos($salary, 'annually') === false) {
            $salary .= ' per year';
        }
        
        return $salary;
    }

    /**
     * Add preview data for block editor
     */
    public function add_preview_data($data): array {
        $data['cws_job'] = array();
        
        foreach ($this->dynamic_fields as $key => $field) {
            $data['cws_job'][$key] = $field['preview'];
        }
        
        return $data;
    }

    /**
     * Register field groups for organization
     */
    public function register_field_groups(): void {
        $groups = array(
            'basic' => 'Basic Information',
            'location' => 'Location Details',
            'details' => 'Job Details',
            'employment' => 'Employment Information',
            'links' => 'URLs and Links',
            'dates' => 'Important Dates',
            'content' => 'Content',
            'computed' => 'Computed Fields'
        );
        
        foreach ($groups as $key => $label) {
            do_action('kadence_blocks_register_field_group', $key, $label, 'cws_job');
        }
    }

    /**
     * Get all available fields
     */
    public function get_available_fields(): array {
        return $this->dynamic_fields;
    }

    /**
     * Get fields by group
     */
    public function get_fields_by_group($group): array {
        return array_filter($this->dynamic_fields, function($field) use ($group) {
            return $field['group'] === $group;
        });
    }

    /**
     * Get field configuration
     */
    public function get_field_config($field): ?array {
        return $this->dynamic_fields[$field] ?? null;
    }

    /**
     * Register REST API fields for Kadence
     */
    public function register_rest_fields(): void {
        // Register all job meta fields for REST API
        foreach ($this->dynamic_fields as $field_key => $field_config) {
            register_rest_field('cws_job', $field_key, array(
                'get_callback' => array($this, 'get_rest_field_value'),
                'update_callback' => null,
                'schema' => array(
                    'description' => $field_config['description'],
                    'type' => $this->get_rest_field_type($field_config['type']),
                    'context' => array('view', 'edit')
                )
            ));
        }
    }

    /**
     * Get REST field value
     */
    public function get_rest_field_value($object, $field_name, $request): string {
        $post_id = $object['id'];
        return $this->get_field_value($field_name, $post_id);
    }

    /**
     * Get REST field type
     */
    private function get_rest_field_type($field_type): string {
        switch ($field_type) {
            case 'number':
                return 'number';
            case 'date':
                return 'string';
            case 'url':
                return 'string';
            case 'html':
                return 'string';
            default:
                return 'string';
        }
    }

    /**
     * Add query card sources
     */
    public function add_query_card_sources($sources): array {
        $sources['cws_job'] = array(
            'label' => 'CWS Job Data',
            'post_type' => 'cws_job',
            'fields' => array_keys($this->dynamic_fields),
            'supports' => array('title', 'excerpt', 'thumbnail', 'meta')
        );
        
        return $sources;
    }

    /**
     * Register meta fields for REST API
     */
    public function register_meta_fields(): void {
        foreach ($this->dynamic_fields as $field_key => $field_config) {
            register_meta('post', $field_key, array(
                'object_subtype' => 'cws_job',
                'type' => $this->get_rest_field_type($field_config['type']),
                'description' => $field_config['description'],
                'single' => true,
                'show_in_rest' => true,
                'auth_callback' => '__return_true'
            ));
        }
    }
}
