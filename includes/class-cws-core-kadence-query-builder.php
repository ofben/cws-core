<?php
declare(strict_types=1);

/**
 * Kadence Query Builder Integration
 * 
 * Integrates CWS Core with Kadence's Advanced Query Loop and Query Cards
 * 
 * @package CWS_Core
 */

namespace CWS_Core;

/**
 * Kadence Query Builder Integration
 */
class CWS_Core_Kadence_Query_Builder {

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
     * Initialize query builder integration
     */
    public function init(): void {
        // Register custom query parameters
        add_filter('kadence_blocks_query_args', array($this, 'modify_query_args'), 10, 2);
        
        // Add custom query filters
        add_filter('kadence_blocks_available_filters', array($this, 'add_custom_filters'));
        
        // Register query card templates
        add_action('init', array($this, 'register_query_card_templates'));
        
        // Add custom query sources
        add_filter('kadence_blocks_query_sources', array($this, 'add_query_sources'));
        
        // Handle custom query logic
        add_filter('kadence_blocks_pre_query', array($this, 'handle_custom_queries'), 10, 2);
        
        // Add job-specific query presets
        add_filter('kadence_blocks_query_presets', array($this, 'add_job_query_presets'));
        
        $this->plugin->log('Kadence query builder integration initialized', 'info');
    }

    /**
     * Modify query arguments for CWS jobs
     */
    public function modify_query_args($args, $query_id): array {
        // Check if this is a CWS job query
        if (!isset($args['post_type']) || $args['post_type'] !== 'cws_job') {
            return $args;
        }
        
        // Ensure we're querying virtual posts
        $args['meta_query'] = $args['meta_query'] ?? array();
        
        // Add custom meta queries for job filtering
        if (isset($args['cws_job_filters'])) {
            $args = $this->add_job_filters($args, $args['cws_job_filters']);
        }
        
        // Add sorting options
        if (isset($args['cws_job_sort'])) {
            $args = $this->add_job_sorting($args, $args['cws_job_sort']);
        }
        
        return $args;
    }

    /**
     * Add custom filters for job queries
     */
    public function add_custom_filters($filters): array {
        $job_filters = array(
            'cws_job_company' => array(
                'label' => 'Company',
                'type' => 'select',
                'options' => $this->get_company_options(),
                'meta_key' => 'cws_job_company'
            ),
            'cws_job_location' => array(
                'label' => 'Location',
                'type' => 'select',
                'options' => $this->get_location_options(),
                'meta_key' => 'cws_job_primary_city'
            ),
            'cws_job_category' => array(
                'label' => 'Category',
                'type' => 'select',
                'options' => $this->get_category_options(),
                'meta_key' => 'cws_job_primary_category'
            ),
            'cws_job_department' => array(
                'label' => 'Department',
                'type' => 'select',
                'options' => $this->get_department_options(),
                'meta_key' => 'cws_job_department'
            ),
            'cws_job_employment_type' => array(
                'label' => 'Employment Type',
                'type' => 'select',
                'options' => $this->get_employment_type_options(),
                'meta_key' => 'cws_job_employment_type'
            ),
            'cws_job_status' => array(
                'label' => 'Status',
                'type' => 'select',
                'options' => $this->get_status_options(),
                'meta_key' => 'cws_job_entity_status'
            ),
            'cws_job_salary_range' => array(
                'label' => 'Salary Range',
                'type' => 'range',
                'meta_key' => 'cws_job_salary',
                'min' => 0,
                'max' => 200000,
                'step' => 10000
            ),
            'cws_job_date_range' => array(
                'label' => 'Date Posted',
                'type' => 'date_range',
                'meta_key' => 'cws_job_open_date'
            )
        );
        
        return array_merge($filters, $job_filters);
    }

    /**
     * Register query card templates
     */
    public function register_query_card_templates(): void {
        // Job Card Template 1: Standard
        register_block_type('cws/job-card-standard', array(
            'render_callback' => array($this, 'render_job_card_standard'),
            'attributes' => array(
                'showImage' => array('type' => 'boolean', 'default' => false),
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
        
        // Job Card Template 2: Compact
        register_block_type('cws/job-card-compact', array(
            'render_callback' => array($this, 'render_job_card_compact'),
            'attributes' => array(
                'showCompany' => array('type' => 'boolean', 'default' => true),
                'showLocation' => array('type' => 'boolean', 'default' => true),
                'showDate' => array('type' => 'boolean', 'default' => true)
            )
        ));
        
        // Job Card Template 3: Featured
        register_block_type('cws/job-card-featured', array(
            'render_callback' => array($this, 'render_job_card_featured'),
            'attributes' => array(
                'showImage' => array('type' => 'boolean', 'default' => true),
                'showCompany' => array('type' => 'boolean', 'default' => true),
                'showLocation' => array('type' => 'boolean', 'default' => true),
                'showSalary' => array('type' => 'boolean', 'default' => true),
                'showDescription' => array('type' => 'boolean', 'default' => true),
                'descriptionLength' => array('type' => 'number', 'default' => 150),
                'buttonText' => array('type' => 'string', 'default' => 'Apply Now'),
                'featuredStyle' => array('type' => 'string', 'default' => 'highlight')
            )
        ));
        
        $this->plugin->log('Registered CWS job card templates', 'info');
    }

    /**
     * Add query sources
     */
    public function add_query_sources($sources): array {
        $sources['cws_jobs'] = array(
            'label' => 'CWS Jobs',
            'description' => 'Query CWS job listings',
            'post_type' => 'cws_job',
            'supports' => array('filters', 'sorting', 'pagination'),
            'custom_filters' => array(
                'cws_job_company',
                'cws_job_location',
                'cws_job_category',
                'cws_job_department',
                'cws_job_employment_type',
                'cws_job_status',
                'cws_job_salary_range',
                'cws_job_date_range'
            )
        );
        
        return $sources;
    }

    /**
     * Handle custom queries
     */
    public function handle_custom_queries($query, $query_id): \WP_Query {
        // Check if this is a CWS job query
        if ($query->get('post_type') !== 'cws_job') {
            return $query;
        }
        
        // Add custom query logic here
        $this->plugin->log('Handling custom CWS job query: ' . $query_id, 'debug');
        
        return $query;
    }

    /**
     * Add job query presets
     */
    public function add_job_query_presets($presets): array {
        $job_presets = array(
            'recent_jobs' => array(
                'label' => 'Recent Jobs',
                'description' => 'Show recently posted jobs',
                'args' => array(
                    'post_type' => 'cws_job',
                    'orderby' => 'meta_value',
                    'meta_key' => 'cws_job_open_date',
                    'order' => 'DESC',
                    'posts_per_page' => 10
                )
            ),
            'featured_jobs' => array(
                'label' => 'Featured Jobs',
                'description' => 'Show featured job listings',
                'args' => array(
                    'post_type' => 'cws_job',
                    'meta_query' => array(
                        array(
                            'key' => 'cws_job_featured',
                            'value' => '1',
                            'compare' => '='
                        )
                    ),
                    'posts_per_page' => 6
                )
            ),
            'jobs_by_category' => array(
                'label' => 'Jobs by Category',
                'description' => 'Show jobs filtered by category',
                'args' => array(
                    'post_type' => 'cws_job',
                    'meta_query' => array(
                        array(
                            'key' => 'cws_job_primary_category',
                            'value' => 'Technology',
                            'compare' => '='
                        )
                    ),
                    'posts_per_page' => 12
                )
            ),
            'remote_jobs' => array(
                'label' => 'Remote Jobs',
                'description' => 'Show remote job opportunities',
                'args' => array(
                    'post_type' => 'cws_job',
                    'meta_query' => array(
                        array(
                            'key' => 'cws_job_employment_type',
                            'value' => 'Remote',
                            'compare' => 'LIKE'
                        )
                    ),
                    'posts_per_page' => 8
                )
            )
        );
        
        return array_merge($presets, $job_presets);
    }

    /**
     * Add job filters to query
     */
    private function add_job_filters($args, $filters): array {
        $meta_query = $args['meta_query'] ?? array();
        
        foreach ($filters as $filter_key => $filter_value) {
            if (empty($filter_value)) {
                continue;
            }
            
            $meta_query[] = array(
                'key' => $filter_key,
                'value' => $filter_value,
                'compare' => '='
            );
        }
        
        if (!empty($meta_query)) {
            $args['meta_query'] = $meta_query;
        }
        
        return $args;
    }

    /**
     * Add job sorting to query
     */
    private function add_job_sorting($args, $sort_config): array {
        switch ($sort_config['field']) {
            case 'date':
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = 'cws_job_open_date';
                break;
            case 'company':
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = 'cws_job_company';
                break;
            case 'location':
                $args['orderby'] = 'meta_value';
                $args['meta_key'] = 'cws_job_primary_city';
                break;
            case 'salary':
                $args['orderby'] = 'meta_value_num';
                $args['meta_key'] = 'cws_job_salary';
                break;
            default:
                $args['orderby'] = 'date';
        }
        
        $args['order'] = $sort_config['direction'] ?? 'DESC';
        
        return $args;
    }

    /**
     * Get company options for filter
     */
    private function get_company_options(): array {
        // This would typically query the database for unique companies
        // For now, return some common options
        return array(
            'TechCorp Inc.' => 'TechCorp Inc.',
            'HealthCare Systems' => 'HealthCare Systems',
            'Finance Solutions' => 'Finance Solutions',
            'Education First' => 'Education First'
        );
    }

    /**
     * Get location options for filter
     */
    private function get_location_options(): array {
        return array(
            'New York' => 'New York, NY',
            'San Francisco' => 'San Francisco, CA',
            'Chicago' => 'Chicago, IL',
            'Boston' => 'Boston, MA',
            'Remote' => 'Remote'
        );
    }

    /**
     * Get category options for filter
     */
    private function get_category_options(): array {
        return array(
            'Technology' => 'Technology',
            'Healthcare' => 'Healthcare',
            'Finance' => 'Finance',
            'Education' => 'Education',
            'Marketing' => 'Marketing',
            'Sales' => 'Sales'
        );
    }

    /**
     * Get department options for filter
     */
    private function get_department_options(): array {
        return array(
            'Engineering' => 'Engineering',
            'Product' => 'Product',
            'Design' => 'Design',
            'Marketing' => 'Marketing',
            'Sales' => 'Sales',
            'Operations' => 'Operations'
        );
    }

    /**
     * Get employment type options for filter
     */
    private function get_employment_type_options(): array {
        return array(
            'Full-time' => 'Full-time',
            'Part-time' => 'Part-time',
            'Contract' => 'Contract',
            'Remote' => 'Remote',
            'Hybrid' => 'Hybrid'
        );
    }

    /**
     * Get status options for filter
     */
    private function get_status_options(): array {
        return array(
            'Open' => 'Open',
            'Closed' => 'Closed',
            'Paused' => 'Paused'
        );
    }

    /**
     * Render standard job card
     */
    public function render_job_card_standard($attributes): string {
        $post_id = get_the_ID();
        if (!$post_id || get_post_type($post_id) !== 'cws_job') {
            return '';
        }
        
        $show_company = $attributes['showCompany'] ?? true;
        $show_location = $attributes['showLocation'] ?? true;
        $show_salary = $attributes['showSalary'] ?? true;
        $show_date = $attributes['showDate'] ?? true;
        $show_description = $attributes['showDescription'] ?? false;
        $description_length = $attributes['descriptionLength'] ?? 100;
        $button_text = $attributes['buttonText'] ?? 'Apply Now';
        $card_style = $attributes['cardStyle'] ?? 'default';
        
        $company = $show_company ? get_post_meta($post_id, 'cws_job_company', true) : '';
        $location = $show_location ? get_post_meta($post_id, 'cws_job_location', true) : '';
        $salary = $show_salary ? get_post_meta($post_id, 'cws_job_salary', true) : '';
        $open_date = $show_date ? get_post_meta($post_id, 'cws_job_open_date', true) : '';
        $description = $show_description ? get_post_meta($post_id, 'cws_job_description', true) : '';
        $apply_url = get_post_meta($post_id, 'cws_job_url', true);
        
        if ($description && $description_length > 0) {
            $description = wp_trim_words(strip_tags($description), $description_length);
        }
        
        $formatted_date = $open_date ? date('M j, Y', strtotime($open_date)) : '';
        
        ob_start();
        ?>
        <div class="cws-job-card cws-job-card--<?php echo esc_attr($card_style); ?>">
            <div class="cws-job-card__header">
                <h3 class="cws-job-card__title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
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
                <a href="<?php the_permalink(); ?>" class="cws-job-card__link">View Details</a>
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
     * Render compact job card
     */
    public function render_job_card_compact($attributes): string {
        $post_id = get_the_ID();
        if (!$post_id || get_post_type($post_id) !== 'cws_job') {
            return '';
        }
        
        $show_company = $attributes['showCompany'] ?? true;
        $show_location = $attributes['showLocation'] ?? true;
        $show_date = $attributes['showDate'] ?? true;
        
        $company = $show_company ? get_post_meta($post_id, 'cws_job_company', true) : '';
        $location = $show_location ? get_post_meta($post_id, 'cws_job_location', true) : '';
        $open_date = $show_date ? get_post_meta($post_id, 'cws_job_open_date', true) : '';
        
        $formatted_date = $open_date ? date('M j', strtotime($open_date)) : '';
        
        ob_start();
        ?>
        <div class="cws-job-card cws-job-card--compact">
            <div class="cws-job-card__content">
                <h4 class="cws-job-card__title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
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
     * Render featured job card
     */
    public function render_job_card_featured($attributes): string {
        $post_id = get_the_ID();
        if (!$post_id || get_post_type($post_id) !== 'cws_job') {
            return '';
        }
        
        $show_image = $attributes['showImage'] ?? true;
        $show_company = $attributes['showCompany'] ?? true;
        $show_location = $attributes['showLocation'] ?? true;
        $show_salary = $attributes['showSalary'] ?? true;
        $show_description = $attributes['showDescription'] ?? true;
        $description_length = $attributes['descriptionLength'] ?? 150;
        $button_text = $attributes['buttonText'] ?? 'Apply Now';
        $featured_style = $attributes['featuredStyle'] ?? 'highlight';
        
        $company = $show_company ? get_post_meta($post_id, 'cws_job_company', true) : '';
        $location = $show_location ? get_post_meta($post_id, 'cws_job_location', true) : '';
        $salary = $show_salary ? get_post_meta($post_id, 'cws_job_salary', true) : '';
        $description = $show_description ? get_post_meta($post_id, 'cws_job_description', true) : '';
        $apply_url = get_post_meta($post_id, 'cws_job_url', true);
        
        if ($description && $description_length > 0) {
            $description = wp_trim_words(strip_tags($description), $description_length);
        }
        
        ob_start();
        ?>
        <div class="cws-job-card cws-job-card--featured cws-job-card--<?php echo esc_attr($featured_style); ?>">
            <?php if ($show_image && has_post_thumbnail()): ?>
                <div class="cws-job-card__image">
                    <?php the_post_thumbnail('medium'); ?>
                </div>
            <?php endif; ?>
            
            <div class="cws-job-card__content">
                <div class="cws-job-card__badge">Featured</div>
                
                <h3 class="cws-job-card__title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
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
                    <a href="<?php the_permalink(); ?>" class="cws-job-card__link">View Details</a>
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
}
