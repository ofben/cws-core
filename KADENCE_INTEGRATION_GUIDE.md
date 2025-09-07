# CWS Core Kadence Integration Guide

## Overview

This guide explains how to use CWS Core with Kadence Theme and Blocks to create powerful job listing pages with dynamic content, advanced query capabilities, and block editor preview functionality.

## Features

### 🎯 **Dynamic Content Fields**
- **25+ Job Fields**: All job data available as dynamic fields in Kadence Blocks
- **Field Groups**: Organized into logical groups (Basic, Location, Details, Employment, etc.)
- **Real-time Preview**: See actual job data in the block editor
- **Type-aware**: Automatic formatting based on field type (date, URL, HTML, etc.)

### 🔍 **Advanced Query Builder**
- **Custom Filters**: Filter by company, location, category, department, employment type
- **Smart Sorting**: Sort by date, company, location, salary
- **Query Presets**: Pre-configured queries for common use cases
- **Pagination**: Built-in pagination support

### 🎨 **Query Cards & Templates**
- **3 Card Templates**: Standard, Compact, and Featured job cards
- **Customizable**: Show/hide fields, adjust styling, set button text
- **Responsive**: Mobile-friendly designs
- **Dynamic Content**: All job fields available in card templates

### 👁️ **Block Editor Preview**
- **Live Preview**: See job data in real-time while building
- **Sample Data**: 5 realistic sample jobs for testing
- **Preview Controls**: Switch between different job examples
- **Field Testing**: Test all dynamic fields with sample data

## Quick Start

### 1. Enable Kadence Integration

The integration is automatically enabled when Kadence Theme is detected. You'll see this in your logs:

```
[CWS Core] [INFO] Kadence detection - Theme: YES, Blocks: YES, Pro: YES
[CWS Core] [INFO] Kadence compatibility initialized successfully
```

### 2. Create a Job Listing Page

1. **Create a new page** in WordPress
2. **Add the "Query Loop (Adv)" block** from Kadence Blocks
3. **Configure the query**:
   - Post Type: `cws_job`
   - Use one of the pre-configured presets or create custom filters
4. **Design your Query Card** using the CWS job card templates

### 3. Use Dynamic Content Fields

In any Kadence block that supports dynamic content:

1. **Add a dynamic content block** (e.g., Advanced Text, Advanced Heading)
2. **Select "CWS Job Data"** as the content source
3. **Choose from 25+ available fields**:
   - Basic: Job ID, Title, Company
   - Location: City, State, Country, Formatted Location
   - Details: Department, Category, Industry, Function
   - Employment: Salary, Employment Type, Status
   - Links: Application URL, SEO URL
   - Dates: Open Date, Update Date
   - Content: Job Description
   - Computed: Days Open, Formatted Salary

## Available Dynamic Fields

### Basic Information
- `cws_job_id` - Unique job identifier
- `cws_job_title` - Full job title
- `cws_job_company` - Hiring company name
- `cws_job_company_name` - Alternative company name

### Location Details
- `cws_job_location` - Formatted location (City, State)
- `cws_job_primary_city` - Primary city
- `cws_job_primary_state` - Primary state
- `cws_job_primary_country` - Primary country
- `cws_job_location_formatted` - Complete formatted location

### Job Details
- `cws_job_department` - Job department
- `cws_job_category` - Job category
- `cws_job_primary_category` - Primary job category
- `cws_job_industry` - Job industry
- `cws_job_function` - Job function

### Employment Information
- `cws_job_salary` - Salary information
- `cws_job_salary_formatted` - Formatted salary with currency
- `cws_job_employment_type` - Type of employment
- `cws_job_type` - Job type classification
- `cws_job_status` - Job status
- `cws_job_entity_status` - Entity status

### URLs and Links
- `cws_job_url` - Direct application URL
- `cws_job_seo_url` - SEO-friendly application URL

### Important Dates
- `cws_job_open_date` - Date job was opened
- `cws_job_update_date` - Date job was last updated
- `cws_job_days_open` - Number of days job has been open

### Content
- `cws_job_description` - Full job description

## Query Builder Features

### Pre-configured Presets

1. **Recent Jobs**: Show recently posted jobs
2. **Featured Jobs**: Show featured job listings
3. **Jobs by Category**: Filter by specific category
4. **Remote Jobs**: Show remote opportunities

### Custom Filters

- **Company**: Filter by hiring company
- **Location**: Filter by city/state
- **Category**: Filter by job category
- **Department**: Filter by department
- **Employment Type**: Filter by full-time, part-time, etc.
- **Status**: Filter by job status
- **Salary Range**: Filter by salary range
- **Date Range**: Filter by posting date

### Sorting Options

- **Date**: Sort by posting date (newest first)
- **Company**: Sort alphabetically by company
- **Location**: Sort by location
- **Salary**: Sort by salary (highest first)

## Job Card Templates

### Standard Card
- Full job information display
- Company, location, salary, date
- Optional description
- View Details and Apply buttons
- Customizable styling

### Compact Card
- Minimal information display
- Title, company, location, date
- Space-efficient design
- Perfect for grid layouts

### Featured Card
- Enhanced visual design
- Optional featured image
- "Featured" badge
- Full description
- Prominent call-to-action buttons

## Block Editor Preview

### Preview Features
- **Live Preview**: See job data in real-time
- **Sample Jobs**: 5 realistic sample jobs for testing
- **Field Testing**: Test all dynamic fields
- **Responsive Preview**: See how it looks on different devices

### Sample Jobs Available
1. **Senior Software Engineer** - TechCorp Inc. - San Francisco, CA
2. **Product Marketing Manager** - InnovateLabs - New York, NY
3. **UX Designer** - DesignStudio Pro - Remote
4. **Data Scientist** - Analytics Solutions - Boston, MA
5. **Sales Development Representative** - GrowthTech - Chicago, IL

### Using Preview Mode
1. **Edit a page** with CWS job blocks
2. **Preview controls** appear automatically
3. **Click preview buttons** to switch between sample jobs
4. **See real-time updates** as you modify blocks

## Advanced Usage

### Custom Query Examples

#### Show Recent Technology Jobs
```php
// Query configuration
$args = array(
    'post_type' => 'cws_job',
    'meta_query' => array(
        array(
            'key' => 'cws_job_primary_category',
            'value' => 'Technology',
            'compare' => '='
        )
    ),
    'orderby' => 'meta_value',
    'meta_key' => 'cws_job_open_date',
    'order' => 'DESC',
    'posts_per_page' => 10
);
```

#### Show Remote Jobs with Salary Filter
```php
// Query configuration
$args = array(
    'post_type' => 'cws_job',
    'meta_query' => array(
        array(
            'key' => 'cws_job_employment_type',
            'value' => 'Remote',
            'compare' => 'LIKE'
        ),
        array(
            'key' => 'cws_job_salary',
            'value' => '100000',
            'compare' => '>='
        )
    ),
    'posts_per_page' => 8
);
```

### Custom Field Usage

#### In Advanced Text Block
1. Add Advanced Text block
2. Select "CWS Job Data" as content source
3. Choose field (e.g., `cws_job_description`)
4. Apply formatting and styling

#### In Advanced Heading Block
1. Add Advanced Heading block
2. Select "CWS Job Data" as content source
3. Choose field (e.g., `cws_job_title`)
4. Set heading level and styling

#### In Advanced Button Block
1. Add Advanced Button block
2. Set button text (e.g., "Apply Now")
3. Select "CWS Job Data" as link source
4. Choose field (e.g., `cws_job_url`)

## Troubleshooting

### Common Issues

#### Dynamic Fields Not Showing
- **Check**: Kadence Blocks is active and updated
- **Check**: CWS Core plugin is active
- **Check**: Job data exists for the post type

#### Preview Not Working
- **Check**: JavaScript console for errors
- **Check**: Preview mode is enabled
- **Check**: Sample data is loaded

#### Query Not Returning Results
- **Check**: Job IDs are configured in CWS Core settings
- **Check**: API connection is working
- **Check**: Cache is not stale

### Debug Information

Enable debug logging to see detailed information:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Check logs for CWS Core messages:
```bash
tail -f /path/to/wordpress/wp-content/debug.log | grep "CWS Core"
```

## API Reference

### Hooks and Filters

#### Dynamic Fields
- `cws_kadence_dynamic_fields` - Modify available dynamic fields
- `cws_kadence_field_value` - Modify field value before display
- `cws_kadence_field_format` - Modify field formatting

#### Query Builder
- `cws_kadence_query_args` - Modify query arguments
- `cws_kadence_query_filters` - Add custom filters
- `cws_kadence_query_presets` - Add custom presets

#### Preview System
- `cws_kadence_preview_data` - Modify preview data
- `cws_kadence_preview_job` - Modify individual job preview
- `cws_kadence_preview_mode` - Detect preview mode

### JavaScript API

#### Global Objects
- `CWSKadencePreview` - Main preview system
- `cwsPreviewData` - Preview data and configuration
- `cwsSampleJobs` - Sample job data

#### Methods
- `CWSKadencePreview.getRandomSampleJob()` - Get random sample job
- `CWSKadencePreview.getJobCategories()` - Get available categories
- `CWSKadencePreview.getJobLocations()` - Get available locations
- `CWSKadencePreview.getCompanies()` - Get available companies

## Best Practices

### Performance
- **Use caching**: Enable CWS Core caching for better performance
- **Limit queries**: Use appropriate `posts_per_page` limits
- **Optimize images**: Use appropriate image sizes for job cards

### User Experience
- **Clear navigation**: Provide clear job listing navigation
- **Search functionality**: Implement job search if needed
- **Mobile optimization**: Test on mobile devices
- **Loading states**: Show loading indicators for dynamic content

### SEO
- **Structured data**: Use job posting structured data
- **Meta descriptions**: Set appropriate meta descriptions
- **URL structure**: Use SEO-friendly URLs
- **Page titles**: Include relevant keywords in page titles

## Support

For additional support:
- Check the CWS Core documentation
- Review Kadence Blocks documentation
- Enable debug logging for troubleshooting
- Check WordPress and plugin compatibility

## Changelog

### Version 1.1.0
- Initial Kadence integration
- Dynamic content fields
- Query builder integration
- Block editor preview system
- Job card templates
- Advanced filtering and sorting
