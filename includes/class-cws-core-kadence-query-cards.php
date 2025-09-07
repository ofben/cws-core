<?php
declare(strict_types=1);

/**
 * Kadence Query Cards Integration
 * 
 * Direct integration with Kadence's Query Card system
 * 
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Kadence Query Cards Integration
 */
class CWS_Core_Kadence_Query_Cards {

    /**
     * Plugin instance
     *
     * @var CWS_Core
     */
    private CWS_Core $plugin;

    /**
     * Constructor
     *
     * @param CWS_Core $plugin Plugin instance.
     */
    public function __construct(CWS_Core $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * Initialize query cards integration
     */
    public function init(): void {
        // Register custom query card templates
        add_action('init', array($this, 'register_query_card_templates'));
        
        // Add custom query card sources
        add_filter('kadence_blocks_query_card_sources', array($this, 'add_query_card_sources'));
        
        // Register REST API endpoints for query cards
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Add custom query card fields
        add_filter('kadence_blocks_query_card_fields', array($this, 'add_query_card_fields'));
        
        // Handle query card rendering
        add_filter('kadence_blocks_render_query_card', array($this, 'render_query_card'), 10, 3);
        
        $this->plugin->log('Kadence query cards integration initialized', 'info');
    }

    /**
     * Register query card templates
     */
    public function register_query_card_templates(): void {
        // Register job card template
        register_block_type('cws/job-query-card', array(
            'render_callback' => array($this, 'render_job_query_card'),
            'attributes' => array(
                'template' => array('type' => 'string', 'default' => 'standard'),
                'showCompany' => array('type' => 'boolean', 'default' => true),
                'showLocation' => array('type' => 'boolean', 'default' => true),
                'showSalary' => array('type' => 'boolean', 'default' => true),
                'showDate' => array('type' => 'boolean', 'default' => true),
                'showDescription' => array('type' => 'boolean', 'default' => false),
                'descriptionLength' => array('type' => 'number', 'default' => 100),
                'buttonText' => array('type' => 'string', 'default' => 'Apply Now'),
                'cardStyle' => array('type' => 'string', 'default' => 'default')
            )
        ));
    }

    /**
     * Add query card sources
     */
    public function add_query_card_sources($sources): array {
        $sources['cws_job'] = array(
            'label' => 'CWS Jobs',
            'description' => 'Job listings from CWS Core',
            'post_type' => 'cws_job',
            'supports' => array('title', 'excerpt', 'thumbnail', 'meta', 'custom-fields'),
            'templates' => array(
                'standard' => 'Standard Job Card',
                'compact' => 'Compact Job Card',
                'featured' => 'Featured Job Card'
            )
        );
        
        return $sources;
    }

    /**
     * Register REST API endpoints
     */
    public function register_rest_endpoints(): void {
        // Endpoint to get job data for query cards
        register_rest_route('cws-core/v1', '/query-cards/jobs', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_jobs_for_query_cards'),
            'permission_callback' => '__return_true',
            'args' => array(
                'per_page' => array(
                    'default' => 10,
                    'type' => 'integer'
                ),
                'page' => array(
                    'default' => 1,
                    'type' => 'integer'
                ),
                'search' => array(
                    'default' => '',
                    'type' => 'string'
                ),
                'category' => array(
                    'default' => '',
                    'type' => 'string'
                ),
                'location' => array(
                    'default' => '',
                    'type' => 'string'
                )
            )
        ));
    }

    /**
     * Add query card fields
     */
    public function add_query_card_fields($fields): array {
        $job_fields = array(
            'cws_job_title' => array(
                'label' => 'Job Title',
                'type' => 'text',
                'source' => 'meta'
            ),
            'cws_job_company' => array(
                'label' => 'Company',
                'type' => 'text',
                'source' => 'meta'
            ),
            'cws_job_location' => array(
                'label' => 'Location',
                'type' => 'text',
                'source' => 'meta'
            ),
            'cws_job_salary' => array(
                'label' => 'Salary',
                'type' => 'text',
                'source' => 'meta'
            ),
            'cws_job_description' => array(
                'label' => 'Description',
                'type' => 'html',
                'source' => 'meta'
            ),
            'cws_job_open_date' => array(
                'label' => 'Open Date',
                'type' => 'date',
                'source' => 'meta'
            ),
            'cws_job_url' => array(
                'label' => 'Application URL',
                'type' => 'url',
                'source' => 'meta'
            )
        );
        
        return array_merge($fields, $job_fields);
    }

    /**
     * Render query card
     */
    public function render_query_card($content, $post, $attributes): string {
        if (get_post_type($post) !== 'cws_job') {
            return $content;
        }
        
        $template = $attributes['template'] ?? 'standard';
        
        switch ($template) {
            case 'compact':
                return $this->render_compact_card($post, $attributes);
            case 'featured':
                return $this->render_featured_card($post, $attributes);
            default:
                return $this->render_standard_card($post, $attributes);
        }
    }

    /**
     * Render job query card
     */
    public function render_job_query_card($attributes): string {
        global $post;
        
        if (!$post || get_post_type($post) !== 'cws_job') {
            return '<div class="cws-job-card-error">No job data available</div>';
        }
        
        $template = $attributes['template'] ?? 'standard';
        
        switch ($template) {
            case 'compact':
                return $this->render_compact_card($post, $attributes);
            case 'featured':
                return $this->render_featured_card($post, $attributes);
            default:
                return $this->render_standard_card($post, $attributes);
        }
    }

    /**
     * Render standard card
     */
    private function render_standard_card($post, $attributes): string {
        $show_company = $attributes['showCompany'] ?? true;
        $show_location = $attributes['showLocation'] ?? true;
        $show_salary = $attributes['showSalary'] ?? true;
        $show_date = $attributes['showDate'] ?? true;
        $show_description = $attributes['showDescription'] ?? false;
        $description_length = $attributes['descriptionLength'] ?? 100;
        $button_text = $attributes['buttonText'] ?? 'Apply Now';
        $card_style = $attributes['cardStyle'] ?? 'default';
        
        $company = $show_company ? get_post_meta($post->ID, 'cws_job_company', true) : '';
        $location = $show_location ? get_post_meta($post->ID, 'cws_job_location', true) : '';
        $salary = $show_salary ? get_post_meta($post->ID, 'cws_job_salary', true) : '';
        $open_date = $show_date ? get_post_meta($post->ID, 'cws_job_open_date', true) : '';
        $description = $show_description ? get_post_meta($post->ID, 'cws_job_description', true) : '';
        $apply_url = get_post_meta($post->ID, 'cws_job_url', true);
        
        if ($description && $description_length > 0) {
            $description = wp_trim_words(strip_tags($description), $description_length);
        }
        
        $formatted_date = $open_date ? date('M j, Y', strtotime($open_date)) : '';
        
        ob_start();
        ?>
        <div class="cws-job-card cws-job-card--<?php echo esc_attr($card_style); ?>">
            <div class="cws-job-card__header">
                <h3 class="cws-job-card__title">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
                </h3>
                <?php if ($company): ?>
                    <div class="cws-job-card__company"><?php echo esc_html($company); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="cws-job-card__meta">
                <?php if ($location): ?>
                    <span class="cws-job-card__location">📍 <?php echo esc_html($location); ?></span>
                <?php endif; ?>
                
                <?php if ($salary): ?>
                    <span class="cws-job-card__salary">💰 <?php echo esc_html($salary); ?></span>
                <?php endif; ?>
                
                <?php if ($formatted_date): ?>
                    <span class="cws-job-card__date">📅 <?php echo esc_html($formatted_date); ?></span>
                <?php endif; ?>
            </div>
            
            <?php if ($description): ?>
                <div class="cws-job-card__description">
                    <?php echo esc_html($description); ?>
                </div>
            <?php endif; ?>
            
            <div class="cws-job-card__actions">
                <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="cws-job-card__link">View Details</a>
                <?php if ($apply_url): ?>
                    <a href="<?php echo esc_url($apply_url); ?>" class="cws-job-card__apply" target="_blank">
                        <?php echo esc_html($button_text); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render compact card
     */
    private function render_compact_card($post, $attributes): string {
        $show_company = $attributes['showCompany'] ?? true;
        $show_location = $attributes['showLocation'] ?? true;
        $show_date = $attributes['showDate'] ?? true;
        
        $company = $show_company ? get_post_meta($post->ID, 'cws_job_company', true) : '';
        $location = $show_location ? get_post_meta($post->ID, 'cws_job_location', true) : '';
        $open_date = $show_date ? get_post_meta($post->ID, 'cws_job_open_date', true) : '';
        
        $formatted_date = $open_date ? date('M j', strtotime($open_date)) : '';
        
        ob_start();
        ?>
        <div class="cws-job-card cws-job-card--compact">
            <div class="cws-job-card__content">
                <h4 class="cws-job-card__title">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
                </h4>
                <div class="cws-job-card__meta">
                    <?php if ($company): ?>
                        <span class="cws-job-card__company"><?php echo esc_html($company); ?></span>
                    <?php endif; ?>
                    <?php if ($location): ?>
                        <span class="cws-job-card__location"><?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                    <?php if ($formatted_date): ?>
                        <span class="cws-job-card__date"><?php echo esc_html($formatted_date); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render featured card
     */
    private function render_featured_card($post, $attributes): string {
        $show_image = $attributes['showImage'] ?? true;
        $show_company = $attributes['showCompany'] ?? true;
        $show_location = $attributes['showLocation'] ?? true;
        $show_salary = $attributes['showSalary'] ?? true;
        $show_description = $attributes['showDescription'] ?? true;
        $description_length = $attributes['descriptionLength'] ?? 150;
        $button_text = $attributes['buttonText'] ?? 'Apply Now';
        $featured_style = $attributes['featuredStyle'] ?? 'highlight';
        
        $company = $show_company ? get_post_meta($post->ID, 'cws_job_company', true) : '';
        $location = $show_location ? get_post_meta($post->ID, 'cws_job_location', true) : '';
        $salary = $show_salary ? get_post_meta($post->ID, 'cws_job_salary', true) : '';
        $description = $show_description ? get_post_meta($post->ID, 'cws_job_description', true) : '';
        $apply_url = get_post_meta($post->ID, 'cws_job_url', true);
        
        if ($description && $description_length > 0) {
            $description = wp_trim_words(strip_tags($description), $description_length);
        }
        
        ob_start();
        ?>
        <div class="cws-job-card cws-job-card--featured cws-job-card--<?php echo esc_attr($featured_style); ?>">
            <?php if ($show_image && has_post_thumbnail($post->ID)): ?>
                <div class="cws-job-card__image">
                    <?php echo get_the_post_thumbnail($post->ID, 'medium'); ?>
                </div>
            <?php endif; ?>
            
            <div class="cws-job-card__content">
                <div class="cws-job-card__badge">Featured</div>
                
                <h3 class="cws-job-card__title">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>"><?php echo esc_html($post->post_title); ?></a>
                </h3>
                
                <?php if ($company): ?>
                    <div class="cws-job-card__company"><?php echo esc_html($company); ?></div>
                <?php endif; ?>
                
                <div class="cws-job-card__meta">
                    <?php if ($location): ?>
                        <span class="cws-job-card__location">📍 <?php echo esc_html($location); ?></span>
                    <?php endif; ?>
                    
                    <?php if ($salary): ?>
                        <span class="cws-job-card__salary">💰 <?php echo esc_html($salary); ?></span>
                    <?php endif; ?>
                </div>
                
                <?php if ($description): ?>
                    <div class="cws-job-card__description">
                        <?php echo esc_html($description); ?>
                    </div>
                <?php endif; ?>
                
                <div class="cws-job-card__actions">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="cws-job-card__link">View Details</a>
                    <?php if ($apply_url): ?>
                        <a href="<?php echo esc_url($apply_url); ?>" class="cws-job-card__apply" target="_blank">
                            <?php echo esc_html($button_text); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get jobs for query cards
     */
    public function get_jobs_for_query_cards(\WP_REST_Request $request): \WP_REST_Response {
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        $search = $request->get_param('search');
        $category = $request->get_param('category');
        $location = $request->get_param('location');
        
        $args = array(
            'post_type' => 'cws_job',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'post_status' => 'publish'
        );
        
        if ($search) {
            $args['s'] = $search;
        }
        
        if ($category || $location) {
            $args['meta_query'] = array();
            
            if ($category) {
                $args['meta_query'][] = array(
                    'key' => 'cws_job_primary_category',
                    'value' => $category,
                    'compare' => '='
                );
            }
            
            if ($location) {
                $args['meta_query'][] = array(
                    'key' => 'cws_job_primary_city',
                    'value' => $location,
                    'compare' => '='
                );
            }
        }
        
        $query = new \WP_Query($args);
        $jobs = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $jobs[] = array(
                    'id' => $post_id,
                    'title' => get_the_title(),
                    'excerpt' => get_the_excerpt(),
                    'permalink' => get_permalink(),
                    'company' => get_post_meta($post_id, 'cws_job_company', true),
                    'location' => get_post_meta($post_id, 'cws_job_location', true),
                    'salary' => get_post_meta($post_id, 'cws_job_salary', true),
                    'open_date' => get_post_meta($post_id, 'cws_job_open_date', true),
                    'description' => get_post_meta($post_id, 'cws_job_description', true),
                    'apply_url' => get_post_meta($post_id, 'cws_job_url', true)
                );
            }
        }
        
        wp_reset_postdata();
        
        return new \WP_REST_Response(array(
            'jobs' => $jobs,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages,
            'current_page' => $page
        ));
    }
}
