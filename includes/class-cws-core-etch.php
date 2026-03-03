<?php
/**
 * CWS Core Etch Integration Class
 *
 * @package CWS_Core
 */

namespace CWS_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrates job data with the Etch page builder via the dynamic data filter.
 */
class CWS_Core_Etch {

	/**
	 * Plugin instance.
	 *
	 * @var CWS_Core
	 */
	private $plugin;

	/**
	 * Current job ID for single job page routing (populated in Phase 2).
	 *
	 * @var string|null
	 */
	private $current_job_id = null;

	/**
	 * Current job data for single job page routing (populated in Phase 2).
	 *
	 * @var array|null
	 */
	private $current_job = null;

	/**
	 * Constructor.
	 *
	 * @param CWS_Core $plugin Plugin instance.
	 */
	public function __construct( CWS_Core $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Register WordPress hooks.
	 */
	public function init() {
		add_filter( 'etch/dynamic_data/option', array( $this, 'inject_options' ), 10, 1 );
		add_action( 'template_redirect', array( $this, 'handle_single_job' ) );
	}

	/**
	 * Inject job data into Etch options context.
	 *
	 * @param array $options Current options context array.
	 * @return array Modified options context.
	 */
	public function inject_options( array $options ) {
		// Phase 2: inject single job only on /job/{id}/ URLs.
		// This must run BEFORE the cws_jobs early-return so it is not bypassed
		// when the admin has not configured any listing Job IDs.
		if ( null !== $this->current_job ) {
			$options['cws_job']  = $this->current_job;
			$options['cws_jobs'] = array( $this->current_job );
			$this->plugin->log(
				sprintf( 'Injected cws_job and cws_jobs (single-item) for job %s', $this->current_job_id ),
				'debug'
			);
		}

		$job_ids = $this->plugin->get_configured_job_ids();

		if ( empty( $job_ids ) ) {
			$this->plugin->log( 'No configured job IDs — cws_jobs will be empty', 'info' );
			$options['cws_jobs'] = array();
			return $options;
		}

		$jobs = $this->plugin->api->get_jobs( $job_ids );

		if ( ! empty( $jobs ) && is_array( $jobs ) ) {
			$options['cws_jobs'] = array_values(
				array_map( array( $this->plugin->api, 'format_job_data' ), $jobs )
			);
			$this->plugin->log( sprintf( 'Injected %d jobs into cws_jobs', count( $jobs ) ), 'debug' );
		} else {
			$options['cws_jobs'] = array();
			$this->plugin->log( 'API returned no jobs — cws_jobs is empty', 'info' );
		}

		// Dynamic field groupings — builds cws_jobs_by_{field} for each admin-configured field name.
		// Usage: {#loop options.cws_jobs_by_primary_category.engineering as job}
		$field_groupings = get_option( 'cws_core_field_groupings', array() );

		if ( ! empty( $field_groupings ) && is_array( $field_groupings ) ) {
			foreach ( $field_groupings as $field_name ) {
				$field_name = sanitize_text_field( $field_name );
				if ( '' === $field_name ) {
					continue;
				}

				$grouped    = array();
				$option_key = 'cws_jobs_by_' . $field_name;

				foreach ( $options['cws_jobs'] as $job ) {
					$raw_value = isset( $job[ $field_name ] ) ? $job[ $field_name ] : '';

					// Only group by scalar string values — skip arrays/objects (e.g. location, permalink, raw_data).
					if ( ! is_string( $raw_value ) && ! is_numeric( $raw_value ) ) {
						continue;
					}

					$bucket_key = sanitize_title( (string) $raw_value );
					if ( '' !== $bucket_key ) {
						$grouped[ $bucket_key ][] = $job;
					}
				}

				$options[ $option_key ] = $grouped;

				$this->plugin->log(
					sprintf( 'Grouped set cws_jobs_by_%s — %d buckets', $field_name, count( $grouped ) ),
					'debug'
				);
			}
		}

		return $options;
	}

	/**
	 * Handle single job URL routing.
	 *
	 * Detects the cws_job_id query var, fetches the job from the API,
	 * stores formatted job data on $this->current_job, and swaps the
	 * global $post/$wp_query to point at the configured template page.
	 * Serves a 404 if the job ID is unknown or the API returns no data.
	 */
	public function handle_single_job() {
		$job_id = get_query_var( 'cws_job_id' );

		// Etch builder preview: populate cws_job with a real sample job.
		if (
			empty( $job_id ) &&
			isset( $_GET['etch'] ) &&
			'magic' === $_GET['etch'] &&
			is_user_logged_in()
		) {
			$job_ids = $this->plugin->get_configured_job_ids();

			// Use admin-configured preview job ID if set, fall back to first configured job.
			$configured_preview = get_option( 'cws_core_preview_job_id', '' );
			$preview_job_id     = ! empty( $configured_preview ) ? $configured_preview : ( ! empty( $job_ids ) ? reset( $job_ids ) : '' );

			if ( empty( $preview_job_id ) ) {
				$this->plugin->log( 'Etch preview: no configured job IDs — cws_job will be empty', 'info' );
				return;
			}

			$raw_job = $this->plugin->api->get_job( $preview_job_id );
			if ( $raw_job ) {
				$this->current_job    = $this->plugin->api->format_job_data( $raw_job );
				$this->current_job_id = $preview_job_id;
				$this->plugin->log(
					sprintf( 'Etch preview: injecting job %s as sample', $preview_job_id ),
					'debug'
				);
			} elseif ( ! empty( $configured_preview ) && ! empty( $job_ids ) ) {
				// Configured preview ID returned no data — fall back to first configured job.
				$fallback_id = reset( $job_ids );
				$raw_job     = $this->plugin->api->get_job( $fallback_id );
				if ( $raw_job ) {
					$this->current_job    = $this->plugin->api->format_job_data( $raw_job );
					$this->current_job_id = $fallback_id;
					$this->plugin->log(
						sprintf( 'Etch preview: configured preview job %s failed — fell back to %s', $preview_job_id, $fallback_id ),
						'info'
					);
				}
			}
			return; // Do NOT swap $post/$wp_query for preview.
		}

		if ( empty( $job_id ) ) {
			return; // Not a job URL — do nothing.
		}

		// Fetch from API (cached via MD5 of URL in fetch_job_data).
		$raw_job = $this->plugin->api->get_job( $job_id );

		if ( false === $raw_job || empty( $raw_job ) ) {
			// Unknown job ID — serve a proper WordPress 404.
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			include( get_404_template() );
			exit;
		}

		// Format for Etch consumption — get_job() returns raw API data.
		// format_job_data() normalises keys so {options.cws_job.location.city} etc. work.
		$this->current_job    = $this->plugin->api->format_job_data( $raw_job );
		$this->current_job_id = $job_id;

		// Resolve the configured template page.
		$template_page_id = (int) $this->plugin->get_option( 'job_template_page_id', 0 );
		$template_page    = $template_page_id ? get_post( $template_page_id ) : null;

		if ( ! $template_page || 'publish' !== $template_page->post_status ) {
			// No valid template page — log and degrade gracefully.
			// cws_job will still be injected but WP may render a 404 layout.
			$this->plugin->log( 'No job template page configured or page is not published', 'error' );
			return;
		}

		// Swap $post and $wp_query so WP/Etch treats the template page as the current page.
		global $post, $wp_query;
		$post                     = $template_page;
		$wp_query->posts          = array( $template_page );
		$wp_query->post           = $template_page;
		$wp_query->post_count     = 1;
		$wp_query->found_posts    = 1;
		$wp_query->is_404         = false;
		$wp_query->is_page        = true;
		$wp_query->is_singular    = true;
		$wp_query->queried_object = $template_page;
		setup_postdata( $post );

		$this->plugin->log(
			sprintf( 'Single job routing: job %s → template page %d', $job_id, $template_page_id ),
			'debug'
		);
	}
}
