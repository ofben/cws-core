<?php
declare(strict_types=1);

/**
 * Kadence Theme Compatibility Class
 * 
 * Provides compatibility with Kadence Theme and Kadence Blocks
 * 
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Kadence Theme Compatibility
 */
class CWS_Core_Kadence_Compatibility {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private CWS_Core $plugin;

    /**
     * Whether Kadence theme is active
     *
     * @var bool
     */
    private bool $is_kadence_active = false;

    /**
     * Whether Kadence Blocks is active
     *
     * @var bool
     */
    private bool $is_kadence_blocks_active = false;

    /**
     * Whether Kadence Pro is active
     *
     * @var bool
     */
    private bool $is_kadence_pro_active = false;

    /**
     * Dynamic fields integration
     *
     * @var CWS_Core_Kadence_Dynamic_Fields
     */
    private $dynamic_fields = null;

    /**
     * Query builder integration
     *
     * @var CWS_Core_Kadence_Query_Builder
     */
    private $query_builder = null;

    /**
     * Preview system
     *
     * @var CWS_Core_Kadence_Preview
     */
    private $preview_system = null;

    /**
     * Query cards integration
     *
     * @var CWS_Core_Kadence_Query_Cards
     */
    private $query_cards = null;

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct(CWS_Core $plugin) {
        $this->plugin = $plugin;
        $this->detect_kadence_components();
    }

    /**
     * Initialize Kadence compatibility
     */
    public function init(): void {
        if (!$this->is_kadence_active) {
            $this->plugin->log('Kadence theme not detected, skipping compatibility initialization', 'info');
            return;
        }

        $this->plugin->log('Initializing Kadence compatibility', 'info');
        
        // Initialize advanced integrations
        $this->init_advanced_integrations();
        
        // Add Kadence-specific hooks
        $this->add_kadence_hooks();
        
        // Add Kadence Blocks compatibility
        if ($this->is_kadence_blocks_active) {
            $this->add_kadence_blocks_hooks();
        }
        
        // Add Kadence Pro compatibility
        if ($this->is_kadence_pro_active) {
            $this->add_kadence_pro_hooks();
        }

        // Add custom CSS for Kadence
        add_action('wp_enqueue_scripts', array($this, 'enqueue_kadence_styles'));
        
        // Add custom JavaScript for Kadence
        add_action('wp_enqueue_scripts', array($this, 'enqueue_kadence_scripts'));

        $this->plugin->log('Kadence compatibility initialized successfully', 'info');
    }

    /**
     * Initialize advanced integrations
     */
    private function init_advanced_integrations(): void {
        // Initialize dynamic fields integration
        if (class_exists('CWS_Core\\CWS_Core_Kadence_Dynamic_Fields')) {
            $this->dynamic_fields = new CWS_Core_Kadence_Dynamic_Fields($this->plugin);
            $this->dynamic_fields->init();
            $this->plugin->log('Kadence dynamic fields integration initialized', 'info');
        }

        // Initialize query builder integration
        if (class_exists('CWS_Core\\CWS_Core_Kadence_Query_Builder')) {
            $this->query_builder = new CWS_Core_Kadence_Query_Builder($this->plugin);
            $this->query_builder->init();
            $this->plugin->log('Kadence query builder integration initialized', 'info');
        }

        // Initialize preview system
        if (class_exists('CWS_Core\\CWS_Core_Kadence_Preview')) {
            $this->preview_system = new CWS_Core_Kadence_Preview($this->plugin);
            $this->preview_system->init();
            $this->plugin->log('Kadence preview system initialized', 'info');
        }

        // Initialize query cards integration
        if (class_exists('CWS_Core\\CWS_Core_Kadence_Query_Cards')) {
            $this->query_cards = new CWS_Core_Kadence_Query_Cards($this->plugin);
            $this->query_cards->init();
            $this->plugin->log('Kadence query cards integration initialized', 'info');
        }
    }

    /**
     * Detect Kadence components
     */
    private function detect_kadence_components(): void {
        // Check for Kadence theme
        $this->is_kadence_active = (
            function_exists('kadence') ||
            get_template() === 'kadence' ||
            wp_get_theme()->get('Name') === 'Kadence' ||
            wp_get_theme()->get('Template') === 'kadence'
        );

        // Check for Kadence Blocks
        $this->is_kadence_blocks_active = (
            class_exists('Kadence_Blocks') ||
            function_exists('kadence_blocks_init') ||
            is_plugin_active('kadence-blocks/kadence-blocks.php')
        );

        // Check for Kadence Pro
        $this->is_kadence_pro_active = (
            class_exists('Kadence_Pro') ||
            function_exists('kadence_pro_init') ||
            is_plugin_active('kadence-pro/kadence-pro.php')
        );

        $this->plugin->log(sprintf(
            'Kadence detection - Theme: %s, Blocks: %s, Pro: %s',
            $this->is_kadence_active ? 'YES' : 'NO',
            $this->is_kadence_blocks_active ? 'YES' : 'NO',
            $this->is_kadence_pro_active ? 'YES' : 'NO'
        ), 'info');
    }

    /**
     * Add Kadence theme hooks
     */
    private function add_kadence_hooks(): void {
        // Single post hooks
        add_action('kadence_single_before', array($this, 'kadence_single_before'));
        add_action('kadence_single_after', array($this, 'kadence_single_after'));
        
        // Archive hooks
        add_action('kadence_archive_before', array($this, 'kadence_archive_before'));
        add_action('kadence_archive_after', array($this, 'kadence_archive_after'));
        
        // Content hooks - use more generic WordPress hooks instead of Kadence-specific ones
        add_filter('the_content', array($this, 'kadence_single_content'), 10, 1);
        
        // Meta hooks
        add_action('kadence_single_meta', array($this, 'kadence_single_meta'));
        
        // Custom post type support
        add_filter('kadence_post_types', array($this, 'add_cws_job_to_kadence_post_types'));
    }

    /**
     * Add Kadence Blocks hooks
     */
    private function add_kadence_blocks_hooks(): void {
        // Block rendering hooks
        add_filter('kadence_blocks_render_block', array($this, 'kadence_blocks_render'), 10, 2);
        
        // Dynamic content hooks
        add_filter('kadence_blocks_dynamic_content', array($this, 'kadence_blocks_dynamic_content'), 10, 3);
        
        // Custom block registration
        add_action('init', array($this, 'register_cws_kadence_blocks'));
        
        // Block editor assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_kadence_block_assets'));
    }

    /**
     * Add Kadence Pro hooks
     */
    private function add_kadence_pro_hooks(): void {
        // Dynamic content for Kadence Pro
        add_filter('kadence_pro_dynamic_content', array($this, 'kadence_pro_dynamic_content'), 10, 2);
        
        // Custom fields integration
        add_filter('kadence_pro_custom_fields', array($this, 'kadence_pro_custom_fields'));
        
        // Advanced features
        add_action('kadence_pro_advanced_features', array($this, 'kadence_pro_advanced_features'));
    }

    /**
     * Handle Kadence single post before content
     */
    public function kadence_single_before(): void {
        if (is_singular('cws_job')) {
            $this->plugin->log('Kadence single before hook for CWS job', 'debug');
            
            // Add custom classes or styling
            echo '<div class="cws-job-kadence-wrapper">';
            
            // Add breadcrumbs if needed
            if (function_exists('kadence_breadcrumbs')) {
                kadence_breadcrumbs();
            }
        }
    }

    /**
     * Handle Kadence single post after content
     */
    public function kadence_single_after(): void {
        if (is_singular('cws_job')) {
            $this->plugin->log('Kadence single after hook for CWS job', 'debug');
            
            // Close wrapper
            echo '</div>';
            
            // Add related jobs or other content
            $this->add_related_jobs();
        }
    }

    /**
     * Handle Kadence archive before content
     */
    public function kadence_archive_before(): void {
        if (is_post_type_archive('cws_job')) {
            $this->plugin->log('Kadence archive before hook for CWS jobs', 'debug');
            
            // Add archive-specific content
            echo '<div class="cws-jobs-archive-kadence">';
        }
    }

    /**
     * Handle Kadence archive after content
     */
    public function kadence_archive_after(): void {
        if (is_post_type_archive('cws_job')) {
            $this->plugin->log('Kadence archive after hook for CWS jobs', 'debug');
            
            // Close archive wrapper
            echo '</div>';
        }
    }

    /**
     * Filter Kadence single content
     */
    public function kadence_single_content($content, $context = null): string {
        // Handle case where only content is passed
        if ($context === null) {
            $context = get_post_type();
        }
        
        if ($context === 'cws_job') {
            $this->plugin->log('Filtering Kadence single content for CWS job', 'debug');
            
            // Add custom content or modify existing content
            $job_id = get_query_var('cws_job_id');
            if ($job_id) {
                $content = $this->enhance_job_content($content, $job_id);
            }
        }
        
        return $content;
    }

    /**
     * Handle Kadence single meta
     */
    public function kadence_single_meta(): void {
        if (is_singular('cws_job')) {
            $this->plugin->log('Adding Kadence single meta for CWS job', 'debug');
            
            // Add custom meta information
            $this->display_job_meta_kadence();
        }
    }

    /**
     * Add CWS job to Kadence post types
     */
    public function add_cws_job_to_kadence_post_types($post_types): array {
        $post_types[] = 'cws_job';
        return $post_types;
    }

    /**
     * Handle Kadence Blocks rendering
     */
    public function kadence_blocks_render($content, $block): string {
        if (isset($block['blockName']) && strpos($block['blockName'], 'cws-') === 0) {
            $this->plugin->log('Rendering CWS block in Kadence Blocks', 'debug');
            
            // Handle custom CWS blocks
            $content = $this->render_cws_kadence_block($content, $block);
        }
        
        return $content;
    }

    /**
     * Handle Kadence Blocks dynamic content
     */
    public function kadence_blocks_dynamic_content($content, $context, $post_id): string {
        if (get_post_type($post_id) === 'cws_job') {
            $this->plugin->log('Handling Kadence Blocks dynamic content for CWS job', 'debug');
            
            // Replace dynamic content placeholders
            $content = $this->replace_cws_dynamic_content($content, $post_id);
        }
        
        return $content;
    }

    /**
     * Register CWS Kadence blocks
     */
    public function register_cws_kadence_blocks(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        // Register job listing block
        register_block_type('cws/job-listing', array(
            'render_callback' => array($this, 'render_job_listing_block'),
            'attributes' => array(
                'jobIds' => array(
                    'type' => 'string',
                    'default' => '',
                ),
                'showMeta' => array(
                    'type' => 'boolean',
                    'default' => true,
                ),
                'layout' => array(
                    'type' => 'string',
                    'default' => 'grid',
                ),
            ),
        ));

        // Register job meta block
        register_block_type('cws/job-meta', array(
            'render_callback' => array($this, 'render_job_meta_block'),
            'attributes' => array(
                'metaField' => array(
                    'type' => 'string',
                    'default' => 'company',
                ),
                'label' => array(
                    'type' => 'string',
                    'default' => '',
                ),
            ),
        ));

        $this->plugin->log('Registered CWS Kadence blocks', 'info');
    }

    /**
     * Enqueue Kadence block editor assets
     */
    public function enqueue_kadence_block_assets(): void {
        wp_enqueue_script(
            'cws-kadence-blocks',
            CWS_CORE_PLUGIN_URL . 'public/js/kadence-blocks.js',
            array('wp-blocks', 'wp-element', 'wp-editor'),
            CWS_CORE_VERSION,
            true
        );

        wp_enqueue_style(
            'cws-kadence-blocks-editor',
            CWS_CORE_PLUGIN_URL . 'public/css/kadence-blocks-editor.css',
            array('wp-edit-blocks'),
            CWS_CORE_VERSION
        );
    }

    /**
     * Handle Kadence Pro dynamic content
     */
    public function kadence_pro_dynamic_content($content, $context): string {
        if ($context === 'cws_job') {
            $this->plugin->log('Handling Kadence Pro dynamic content for CWS job', 'debug');
            
            // Handle Kadence Pro specific dynamic content
            $content = $this->replace_kadence_pro_dynamic_content($content);
        }
        
        return $content;
    }

    /**
     * Add custom fields to Kadence Pro
     */
    public function kadence_pro_custom_fields($fields): array {
        $cws_fields = array(
            'cws_job_company' => 'Company',
            'cws_job_location' => 'Location',
            'cws_job_department' => 'Department',
            'cws_job_category' => 'Category',
            'cws_job_status' => 'Status',
            'cws_job_url' => 'Application URL',
        );

        return array_merge($fields, $cws_fields);
    }

    /**
     * Handle Kadence Pro advanced features
     */
    public function kadence_pro_advanced_features(): void {
        if (is_singular('cws_job')) {
            $this->plugin->log('Handling Kadence Pro advanced features for CWS job', 'debug');
            
            // Add advanced features specific to job posts
        }
    }

    /**
     * Enqueue Kadence-specific styles
     */
    public function enqueue_kadence_styles(): void {
        if ($this->is_kadence_active) {
            wp_enqueue_style(
                'cws-kadence-compatibility',
                CWS_CORE_PLUGIN_URL . 'public/css/kadence-compatibility.css',
                array('kadence-style'),
                CWS_CORE_VERSION
            );
        }
    }

    /**
     * Enqueue Kadence-specific scripts
     */
    public function enqueue_kadence_scripts(): void {
        if ($this->is_kadence_active) {
            wp_enqueue_script(
                'cws-kadence-compatibility',
                CWS_CORE_PLUGIN_URL . 'public/js/kadence-compatibility.js',
                array('jquery'),
                CWS_CORE_VERSION,
                true
            );

            // Localize script with job data
            wp_localize_script('cws-kadence-compatibility', 'cwsKadence', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cws_kadence_nonce'),
                'isJobPage' => is_singular('cws_job'),
                'jobId' => get_query_var('cws_job_id'),
            ));
        }
    }

    /**
     * Add related jobs
     */
    private function add_related_jobs(): void {
        // Implementation for related jobs
        echo '<div class="cws-related-jobs">';
        echo '<h3>Related Jobs</h3>';
        // Add related jobs logic here
        echo '</div>';
    }

    /**
     * Enhance job content for Kadence
     */
    private function enhance_job_content(string $content, string $job_id): string {
        // Add Kadence-specific enhancements to job content
        return $content;
    }

    /**
     * Display job meta in Kadence format
     */
    private function display_job_meta_kadence(): void {
        $job_id = get_query_var('cws_job_id');
        if (!$job_id) {
            return;
        }

        echo '<div class="cws-job-meta-kadence">';
        echo '<div class="job-meta-item"><strong>Company:</strong> ' . get_post_meta(get_the_ID(), 'cws_job_company', true) . '</div>';
        echo '<div class="job-meta-item"><strong>Location:</strong> ' . get_post_meta(get_the_ID(), 'cws_job_location', true) . '</div>';
        echo '<div class="job-meta-item"><strong>Department:</strong> ' . get_post_meta(get_the_ID(), 'cws_job_department', true) . '</div>';
        echo '</div>';
    }

    /**
     * Render CWS Kadence block
     */
    private function render_cws_kadence_block(string $content, array $block): string {
        // Implementation for custom CWS blocks in Kadence
        return $content;
    }

    /**
     * Replace CWS dynamic content
     */
    private function replace_cws_dynamic_content(string $content, int $post_id): string {
        // Replace dynamic content placeholders with actual job data
        $replacements = array(
            '{job_company}' => get_post_meta($post_id, 'cws_job_company', true),
            '{job_location}' => get_post_meta($post_id, 'cws_job_location', true),
            '{job_department}' => get_post_meta($post_id, 'cws_job_department', true),
            '{job_category}' => get_post_meta($post_id, 'cws_job_category', true),
        );

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Replace Kadence Pro dynamic content
     */
    private function replace_kadence_pro_dynamic_content(string $content): string {
        // Handle Kadence Pro specific dynamic content
        return $content;
    }

    /**
     * Render job listing block
     */
    public function render_job_listing_block(array $attributes): string {
        $job_ids = !empty($attributes['jobIds']) ? explode(',', $attributes['jobIds']) : array();
        $show_meta = $attributes['showMeta'] ?? true;
        $layout = $attributes['layout'] ?? 'grid';

        $output = '<div class="cws-job-listing-block layout-' . esc_attr($layout) . '">';
        
        foreach ($job_ids as $job_id) {
            $job_id = trim($job_id);
            if ($job_id) {
                $output .= $this->render_single_job_block($job_id, $show_meta);
            }
        }
        
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render job meta block
     */
    public function render_job_meta_block(array $attributes): string {
        $meta_field = $attributes['metaField'] ?? 'company';
        $label = $attributes['label'] ?? '';
        
        $value = get_post_meta(get_the_ID(), 'cws_job_' . $meta_field, true);
        
        if (!$value) {
            return '';
        }
        
        $output = '<div class="cws-job-meta-block">';
        if ($label) {
            $output .= '<span class="meta-label">' . esc_html($label) . ':</span> ';
        }
        $output .= '<span class="meta-value">' . esc_html($value) . '</span>';
        $output .= '</div>';
        
        return $output;
    }

    /**
     * Render single job block
     */
    private function render_single_job_block(string $job_id, bool $show_meta): string {
        // Implementation for single job block rendering
        return '<div class="cws-single-job-block">Job ID: ' . esc_html($job_id) . '</div>';
    }

    /**
     * Get compatibility status
     */
    public function get_compatibility_status(): array {
        return array(
            'kadence_theme' => $this->is_kadence_active,
            'kadence_blocks' => $this->is_kadence_blocks_active,
            'kadence_pro' => $this->is_kadence_pro_active,
        );
    }
}
