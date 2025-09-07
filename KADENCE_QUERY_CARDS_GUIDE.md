# Kadence Query Cards Setup Guide

## Quick Start: Creating Query Cards for CWS Jobs

### Step 1: Create a New Page
1. Go to **Pages > Add New** in your WordPress admin
2. Give your page a title like "Job Listings"
3. Click **"Add Block"** and search for **"Query Loop (Adv)"**

### Step 2: Configure the Query Loop
1. **Click "Create New"** in the Query Loop block
2. **Select Post Type**: Choose `cws_job` from the dropdown
3. **Set Posts Per Page**: Choose how many jobs to show (e.g., 10)
4. **Click "Create"** to proceed

### Step 3: Create Your Query Card
1. **Click "Create New"** under the Query Card section
2. **Choose a layout**: Start with "Blank" for full customization
3. **Give it a name**: e.g., "Job Card Template"
4. **Click "Create"** to start designing

### Step 4: Design Your Job Card
Add these blocks to your Query Card:

#### Basic Job Card Layout:
1. **Advanced Heading Block**
   - Set to H3
   - Enable **Dynamic Content**
   - Select **"CWS Job Data"** as source
   - Choose **"Job Title"** field

2. **Advanced Text Block**
   - Enable **Dynamic Content**
   - Select **"CWS Job Data"** as source
   - Choose **"Company Name"** field

3. **Advanced Text Block**
   - Enable **Dynamic Content**
   - Select **"CWS Job Data"** as source
   - Choose **"Location"** field

4. **Advanced Button Block**
   - Set button text to "Apply Now"
   - Enable **Dynamic Content** for the link
   - Select **"CWS Job Data"** as source
   - Choose **"Application URL"** field

### Step 5: Test Your Query Card
1. **Save** your page
2. **Preview** the page to see your job listings
3. **Check** that job data is displaying correctly

## Available Dynamic Fields

### Basic Information
- **Job Title** (`cws_job_title`)
- **Company Name** (`cws_job_company`)
- **Job Description** (`cws_job_description`)

### Location Details
- **Location** (`cws_job_location`)
- **City** (`cws_job_primary_city`)
- **State** (`cws_job_primary_state`)
- **Country** (`cws_job_primary_country`)

### Job Details
- **Department** (`cws_job_department`)
- **Category** (`cws_job_primary_category`)
- **Industry** (`cws_job_industry`)
- **Function** (`cws_job_function`)

### Employment Information
- **Salary** (`cws_job_salary`)
- **Employment Type** (`cws_job_employment_type`)
- **Job Status** (`cws_job_status`)

### Important Dates
- **Open Date** (`cws_job_open_date`)
- **Update Date** (`cws_job_update_date`)
- **Days Open** (`cws_job_days_open`)

### Links
- **Application URL** (`cws_job_url`)
- **SEO URL** (`cws_job_seo_url`)

## Troubleshooting

### Issue: No jobs showing up
**Solution**: 
1. Check that job IDs are configured in CWS Core settings
2. Verify API connection is working
3. Clear any caching plugins

### Issue: Dynamic fields not showing
**Solution**:
1. Ensure Kadence Blocks is active and updated
2. Check that CWS Core plugin is active
3. Verify job data exists for the post type

### Issue: Query Card not rendering
**Solution**:
1. Check browser console for JavaScript errors
2. Verify all required fields are properly configured
3. Test with a simple card layout first

## Advanced Customization

### Custom Styling
Add custom CSS to style your job cards:

```css
.cws-job-card {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.cws-job-card__title {
    color: #333;
    margin-bottom: 10px;
}

.cws-job-card__company {
    color: #666;
    font-weight: 500;
}

.cws-job-card__location {
    color: #888;
    font-size: 14px;
}
```

### Filtering and Sorting
1. **Add Filter Blocks** above your Query Loop
2. **Configure filters** for:
   - Company
   - Location
   - Category
   - Employment Type
   - Salary Range

### Responsive Design
1. **Use Kadence's responsive controls**
2. **Test on mobile devices**
3. **Adjust spacing and typography** for different screen sizes

## Sample Query Card Templates

### Template 1: Standard Job Card
```
[Advanced Heading - Job Title]
[Advanced Text - Company]
[Advanced Text - Location]
[Advanced Text - Salary]
[Advanced Button - Apply Now]
```

### Template 2: Compact Job Card
```
[Advanced Heading - Job Title]
[Advanced Text - Company, Location]
[Advanced Button - View Details]
```

### Template 3: Featured Job Card
```
[Advanced Image - Company Logo]
[Advanced Heading - Job Title]
[Advanced Text - Company]
[Advanced Text - Location, Salary]
[Advanced Text - Description (truncated)]
[Advanced Button - Apply Now]
```

## Testing Your Setup

### Test Checklist:
- [ ] Query Loop shows job posts
- [ ] Dynamic fields display correct data
- [ ] Links work properly
- [ ] Responsive design works
- [ ] Filters function correctly
- [ ] Pagination works
- [ ] No JavaScript errors in console

### Debug Information:
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

## Need Help?

If you're still having issues:
1. Check the main **KADENCE_INTEGRATION_GUIDE.md**
2. Review the CWS Core documentation
3. Enable debug logging for troubleshooting
4. Test with a simple setup first, then add complexity
