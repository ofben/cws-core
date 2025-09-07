=== CWS Core ===
Contributors: Ben Esteban
Tags: jobs, api, career, employment, recruitment, virtual-cpt, etchwp
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect to a job API endpoint to fetch and display job data on your WordPress site with Virtual CPT support.

== Description ==

CWS Core is a powerful WordPress plugin that connects to external job API endpoints to fetch and display job listings on your website. The plugin features a revolutionary Virtual CPT system that creates WordPress posts on-the-fly from API data, making job data fully compatible with page builders like EtchWP, Elementor, and Gutenberg.

= Key Features =

* **Virtual CPT System**: Creates WordPress posts dynamically from API data
* **Page Builder Compatible**: Full integration with EtchWP, Elementor, and Gutenberg
* **API Integration**: Connect to any job API endpoint with configurable organization ID
* **Dynamic Job Discovery**: Automatically discovers and caches new job IDs
* **URL Rewriting**: Automatic job URL generation (e.g., `/job/123/`)
* **Advanced Caching**: WordPress Transients API with intelligent cache management
* **Admin Interface**: Modern settings panel with connection testing and job management
* **REST API Support**: Full REST API integration for virtual posts
* **Meta Data Integration**: Complete job meta data available to all WordPress functions
* **Error Handling**: Graceful degradation and user-friendly error messages

= API Configuration =

The plugin supports the following API URL pattern:
`https://your-api-endpoint.com/api/stjob?organization=YOUR_ORG_ID&jobList=JOB_ID`

* **organization**: Your organization ID parameter
* **jobList**: Comma-delimited list of job IDs

= URL Structure =

Job pages are automatically generated with the following URL patterns:
* `/job/123/` - Basic job URL
* `/job/123/job-title-slug/` - SEO-friendly job URL

= Caching =

* Configurable cache duration (15 minutes to 24 hours)
* Automatic cache cleanup
* Manual cache management from admin panel
* Cache statistics and monitoring

= Virtual CPT System =

The plugin's Virtual CPT system creates WordPress posts dynamically from API data:

* **Dynamic Post Creation**: Posts are created on-the-fly when accessed
* **Full WordPress Compatibility**: Works with all WordPress functions (get_post, WP_Query, etc.)
* **Meta Data Integration**: Complete job data available as post meta
* **REST API Support**: Virtual posts accessible via WordPress REST API
* **Page Builder Integration**: Full compatibility with EtchWP, Elementor, Gutenberg
* **Cache Integration**: Intelligent caching prevents unnecessary API calls

= Developer Friendly =

* Object-oriented architecture with strict typing
* WordPress coding standards compliance
* Comprehensive error logging and debugging
* Debug mode for troubleshooting
* Extensive hooks and filters for customization
* Virtual CPT system for advanced integrations

== Installation ==

1. Upload the `cws-core` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings > CWS Core' to configure your API endpoint and organization ID
4. Add job IDs in the "Job IDs" field (e.g., `22026695, 22026696`)
5. Test the connection using the 'Test Connection' button
6. Visit `/job/22026695/` to see your job pages in action
7. Create EtchWP templates for the `cws_job` post type to customize display

== Frequently Asked Questions ==

= What API endpoints are supported? =

The plugin is designed to work with any REST API that returns job data in JSON format. The API should accept `organization` and `jobList` parameters.

= Can I customize the job display template? =

Yes! The plugin supports multiple template methods:

* **EtchWP Templates**: Create templates for the `cws_job` post type in EtchWP
* **Theme Templates**: Create `job.php` or `single-job.php` template in your theme
* **Custom Templates**: Use the plugin's template system with hooks and filters

= How do I clear the cache? =

Use the 'Clear Cache' button in the admin settings panel, or the cache will automatically expire based on your configured duration.

= Is the plugin compatible with page builders? =

Yes! The Virtual CPT system makes the plugin fully compatible with all major page builders:

* **EtchWP**: Full integration with dynamic content and meta fields
* **Elementor**: Use dynamic content widgets to display job data
* **Gutenberg**: Custom blocks and dynamic content support
* **Other Builders**: Works with any page builder that supports WordPress posts

= Can I track job applications? =

Yes, the plugin includes built-in analytics tracking for job views and application clicks. It's compatible with Google Analytics and Facebook Pixel.

= What happens if the API is down? =

The plugin will display cached data if available, or show a user-friendly error message if no cached data exists.

== Screenshots ==

1. Admin settings panel with API configuration
2. Job display page with responsive design
3. Cache management interface
4. Connection testing functionality

== Changelog ==

= 1.1.0 =
* **Virtual CPT System**: Revolutionary system that creates WordPress posts dynamically from API data
* **Page Builder Integration**: Full compatibility with EtchWP, Elementor, and Gutenberg
* **Dynamic Job Discovery**: Automatically discovers and caches new job IDs from API
* **REST API Support**: Virtual posts accessible via WordPress REST API
* **Advanced Meta Integration**: Complete job data available as post meta fields
* **Enhanced Caching**: Intelligent cache management with job-specific caching
* **Admin Improvements**: Job ID management and discovered job tracking
* **Debug Enhancements**: Comprehensive logging and debugging capabilities
* **Template System**: Multiple template options for different page builders

= 1.0.0 =
* Initial release
* API integration with configurable endpoints
* URL rewriting for job pages
* Caching system with WordPress Transients
* Admin interface with connection testing
* Responsive job display templates
* SEO optimization with structured data
* Analytics tracking support
* Comprehensive error handling

== Upgrade Notice ==

= 1.1.0 =
Major update with Virtual CPT system for full page builder compatibility. Update recommended for all users.

= 1.0.0 =
Initial release of CWS Core plugin.

== Developer Notes ==

= Hooks and Filters =

The plugin provides extensive hooks and filters for customization:

**Core Hooks:**
* `cws_core_job_data` - Filter job data before display
* `cws_core_api_url` - Filter API URL before request
* `cws_core_cache_key` - Filter cache key generation
* `cws_core_job_template` - Filter job template path

**Virtual CPT Hooks:**
* `cws_virtual_post_meta` - Filter virtual post meta data
* `cws_core_virtual_post_created` - Action when virtual post is created
* `cws_core_job_discovered` - Action when new job ID is discovered
* `cws_core_cache_job_data` - Filter job data before caching

= Custom Templates =

**For EtchWP:**
1. Create a template for the `cws_job` post type in EtchWP
2. Use dynamic content fields like `{this.title}`, `{this.meta.cws_job_company}`
3. Access all job data through standard WordPress post functions

**For Theme Templates:**
1. Create `job.php` or `single-job.php` in your theme directory
2. Use `get_query_var('cws_job_id')` to get the job ID
3. Use `get_post_meta()` to access job data
4. Style with CSS using the provided classes

**For Custom Integration:**
1. Use the Virtual CPT system to create custom post types
2. Access job data through WordPress standard functions
3. Use hooks and filters for advanced customization

= API Response Format =

The plugin expects API responses in this format:

```json
{
  "totalHits": 1,
  "queryResult": [
    {
      "id": "123",
      "title": "Job Title",
      "company_name": "Company Name",
      "description": "Job description...",
      "primary_city": "City",
      "primary_state": "State",
      "salary": "Salary range",
      "url": "Apply URL"
    }
  ]
}
```

== Support ==

For support, feature requests, or bug reports, please visit our support forum or contact us directly.

== Credits ==

Developed with WordPress best practices and modern web standards.
