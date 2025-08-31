=== CWS Core ===
Contributors: Ben Esteban
Tags: jobs, api, career, employment, recruitment
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect to a job API endpoint to fetch and display job data on your WordPress site.

== Description ==

CWS Core is a powerful WordPress plugin that connects to external job API endpoints to fetch and display job listings on your website. The plugin dynamically constructs API URLs based on job IDs extracted from URL paths and provides a comprehensive admin interface for configuration.

= Key Features =

* **API Integration**: Connect to any job API endpoint with configurable organization ID
* **URL Rewriting**: Automatic job URL generation (e.g., `/job/123/`)
* **Caching System**: WordPress Transients API for improved performance
* **Admin Interface**: Modern settings panel with connection testing
* **Responsive Design**: Mobile-friendly job display templates
* **SEO Optimized**: Structured data and meta tags for job pages
* **Analytics Ready**: Built-in tracking for job views and applications
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

= Developer Friendly =

* Object-oriented architecture
* WordPress coding standards compliance
* Comprehensive error logging
* Debug mode for troubleshooting
* Hooks and filters for customization

== Installation ==

1. Upload the `cws-core` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'Settings > CWS Core' to configure your API endpoint and organization ID
4. Test the connection using the 'Test Connection' button
5. Visit `/job/123/` to see your job pages in action

== Frequently Asked Questions ==

= What API endpoints are supported? =

The plugin is designed to work with any REST API that returns job data in JSON format. The API should accept `organization` and `jobList` parameters.

= Can I customize the job display template? =

Yes! Create a `job.php` or `single-job.php` template in your theme to override the default display.

= How do I clear the cache? =

Use the 'Clear Cache' button in the admin settings panel, or the cache will automatically expire based on your configured duration.

= Is the plugin compatible with page builders? =

Yes, the plugin works with all major page builders. Job data is injected into the page content and can be styled with CSS.

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

= 1.0.0 =
Initial release of CWS Core plugin.

== Developer Notes ==

= Hooks and Filters =

The plugin provides several hooks and filters for customization:

* `cws_core_job_data` - Filter job data before display
* `cws_core_api_url` - Filter API URL before request
* `cws_core_cache_key` - Filter cache key generation
* `cws_core_job_template` - Filter job template path

= Custom Templates =

To create a custom job template:

1. Create `job.php` in your theme directory
2. Use `get_query_var('cws_job_id')` to get the job ID
3. Use the plugin's API methods to fetch job data
4. Style with CSS using the provided classes

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
