# CWS Core API Behavior Documentation

## API Endpoint Overview

**Base URL:** `https://jobsapi-internal.m-cloud.io/api/stjob`

**Primary Endpoint:** `GET /api/stjob?organization={org_id}`

## Core API Structure

### Response Format
```json
{
  "aggregations": null,
  "titles": null,
  "totalHits": 1061,
  "queryResult": [
    {
      // Individual job object
    },
    {
      // Individual job object
    }
  ]
}
```

### Key Response Fields
- **`totalHits`**: Total number of jobs available (1061 in example)
- **`queryResult`**: Array of job objects (defaults to 10 results)
- **`aggregations`**: Used for faceted search results
- **`titles`**: Simple array of job titles when `facetList=title` is used

## Job Object Structure

Based on the API response, each job contains:

### Core Fields
```json
{
  "id": 10154446,
  "title": "FACULTY OPPORTUNITY: Infectious Diseases Clinician Educator",
  "description": "HTML formatted job description",
  "company_name": "NYU Langone Medical Center",
  "entity_status": "Open",
  "open_date": "2022-12-11T06:02:33.797Z",
  "update_date": "2025-05-27T14:04:45.26Z"
}
```

### Location Fields
```json
{
  "primary_city": "New York",
  "primary_state": "NY",
  "primary_country": "US",
  "primary_location": [-74.005899999999997, 40.712800000000001],
  "addtnl_locations": []
}
```

### Categorization Fields
```json
{
  "primary_category": "Faculty",
  "addtnl_categories": [],
  "parent_category": "Faculty",
  "sub_category": "Faculty",
  "department": "Division of Infectious Diseases and Immunology",
  "industry": "Health Care General",
  "function": "Education General"
}
```

### URL Fields
```json
{
  "url": "http://jobs.nyulangone.org/job/10154446/...",
  "seo_url": "https://apply.interfolio.com/68670",
  "fndly_url": "https://d.hodes.com/r/tp2?..."
}
```

### Additional Fields
```json
{
  "salary": "",
  "job_type": "",
  "employment_type": "",
  "clientid": "68670",
  "ref": "68670",
  "publish_to_cws": "true",
  "is_posted": "true",
  "hidden": "false"
}
```

## API Parameters

### Required Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `organization` | int | Required. SmartPost Hiring Org ID value |

### Pagination Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `limit` | int | 10 | Number of records in result set |
| `offset` | int | 1 | Number of results to skip |
| `node` | int | 0 | Elasticsearch cluster node (0, 1, or 2) |

### Sorting Parameters
| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `sortField` | string | - | Any field except `primary_location` and `addtnl_locations` |
| `sortOrder` | string | "asc" | "asc", "desc", "a", "ascending" |

### Filtering Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `openedDate` | string | Jobs opened after this date |
| `updatedDate` | string | Jobs updated on specific date(s) |
| `timeZone` | string | Timezone for date filtering (with updatedDate only) |
| `searchText` | string | Keyword search |
| `fuzzy` | boolean | Enable fuzzy search |
| `boost` | string | Search boost |
| `facet` | string[] | Filter by specific field values |
| `facetList` | string[] | Get distinct values for a field |

### Location Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `latitude` | numeric | Latitude (-90.0 to 90.0) |
| `longitude` | numeric | Longitude (-180.0 to 180.0) |
| `locationRadius` | int | Location radius |
| `locationUnits` | string | Distance units (default: "mi") |
| `locationType` | string | "Nationwide", "Statewide", "Remote" |
| `stateCity` | string[] | State and city combinations |
| `countryStateCity` | string[] | Country, state, city combinations |
| `locationSet` | string[] | Multiple lat/lng/radius sets |

### Special Parameters
| Parameter | Type | Description |
|-----------|------|-------------|
| `jobList` | string | Comma-delimited list of specific Job IDs |

## Technical Implementation Details

### Elasticsearch Schema
- **Schema Version:** Wax 2.0
- **Search Engine:** Elasticsearch 1.7
- **API Version:** JobsApi3

### Filter Types by Parameter

#### Term Filters
These parameters use [Elasticsearch Term filters](https://www.elastic.co/guide/en/elasticsearch/reference/1.7/query-dsl-term-filter.html):
- `organization` - Exact match for organization ID
- `stateCity` - Exact state and city combination
- `countryStateCity` - Exact country, state, and city combination

#### Terms Filters
These parameters use [Elasticsearch Terms filters](https://www.elastic.co/guide/en/elasticsearch/reference/1.7/query-dsl-terms-filter.html):
- `facet` - Multiple field value filters
- `multiCategory` - Multiple category filters
- `jobList` - Multiple job ID filters
- `locationType` - Multiple location type filters ("Nationwide", "Statewide", "Remote")
- `nationwideCountries` - Multiple country codes
- `statewideStates` - Multiple state codes
- `EmediaList` - Multiple media list filters

#### Range Filters
These parameters use [Elasticsearch Range filters](https://www.elastic.co/guide/en/elasticsearch/reference/1.7/query-dsl-range-filter.html):
- `openedDate` - Date range filtering for job opening dates
- `updatedDate` - Date range filtering for job update dates

#### Geo Distance Filters
These parameters use [Elasticsearch Geo Distance filters](https://www.elastic.co/guide/en/elasticsearch/reference/1.7/query-dsl-geo-distance-filter.html):
- `latitude` - Geographic latitude coordinate
- `longitude` - Geographic longitude coordinate
- `locationRadius` - Distance radius for geographic searches
- `locationSet` - Multiple geographic coordinate sets

### Location Search Behavior
**Important:** Only one location search method should be used per query:
- `stateCity` OR
- `countryStateCity` OR
- `latitude/longitude/locationRadius` OR
- `locationSet`

Using multiple location parameters may result in unexpected behavior.

### Sorting Behavior

#### Default Sorting
- **Primary:** By specified `sortField` (if provided)
- **Secondary:** By relevance score (only when `searchText` is provided)

#### Geographic Sorting
- **When:** No `sortField` provided but geographic coordinates are used
- **Method:** [Proximity-based sorting](https://www.elastic.co/guide/en/elasticsearch/guide/1.x/sorting-by-distance.html)
- **LocationSet:** Uses first set of coordinates for proximity calculation

#### Sort Implementation
Uses [Elasticsearch Search Request Sort](https://www.elastic.co/guide/en/elasticsearch/reference/1.7/search-request-sort.html)

### Aggregations
- **facetList parameter:** Uses [Terms Aggregations](https://www.elastic.co/guide/en/elasticsearch/reference/1.7/search-aggregations-bucket-terms-aggregation.html)
- **Purpose:** Get distinct values for specified fields
- **Example:** `facetList=primary_category` returns all unique category values

### Search Relevance
- **Relevance scoring:** Only applies when `searchText` parameter is provided
- **Scoring method:** Elasticsearch relevance scoring
- **Secondary sort:** When `sortField` is provided, relevance becomes secondary sort criteria

### Date Filtering Details

#### openedDate Parameter
- **Format:** `YYYY-MM-DD` or `YYYY-MM-DDTHH24:MI:SS`
- **Range Support:** Yes, comma-separated values
- **Examples:**
  - `openedDate=2023-08-15` - Jobs opened on or after August 15, 2023
  - `openedDate=2023-08-15T10:00:00,2023-08-15T12:00:00` - Jobs opened between 10 AM and 12 PM on August 15, 2023
- **Use Case:** Filter for recent job postings

#### updatedDate Parameter
- **Format:** `YYYY-MM-DD` or `YYYY-MM-DDTHH24:MI:SS`
- **Range Support:** Yes, comma-separated values
- **Timezone Support:** Yes, requires `timeZone` parameter
- **Examples:**
  - `updatedDate=2023-07-25T18:00:00` - Jobs updated at specific time
  - `updatedDate=2023-07-25T02:00:00,2023-07-27T23:00:30` - Jobs updated between two dates
- **Use Case:** Find recently modified job listings

#### timeZone Parameter
- **Requirement:** Must be used with `updatedDate` only
- **Format:** Standard timezone identifiers
- **Examples:**
  - `timeZone=UTC`
  - `timeZone=America/New_York`
  - `timeZone=Europe/London`
- **Behavior:** Converts user's date to EST before querying Elasticsearch
- **Available Timezones:** Full list available in Elasticsearch documentation

#### Date Filtering Best Practices
1. **Use openedDate for:** Recent job postings, new opportunities
2. **Use updatedDate for:** Recently modified jobs, content updates
3. **Always specify timeZone with updatedDate** to avoid timezone confusion
4. **Use ranges for:** Time-sensitive searches, audit trails
5. **Combine with other filters:** Date + location + category for precise results

## API Behavior Patterns

### 1. Default Behavior
- **URL:** `?organization=1637`
- **Returns:** First 10 jobs
- **Total:** Shows total available jobs in `totalHits`

### 2. Pagination
- **URL:** `?organization=1637&limit=20&offset=21`
- **Returns:** Jobs 21-40 (20 results)
- **Use Case:** Building job archives/lists

### 3. Specific Job List
- **URL:** `?organization=1637&jobList=10154446,10154448`
- **Returns:** Only specified jobs
- **Use Case:** Featured jobs, specific job pages

### 4. Status Filtering
- **URL:** `?organization=1637&facet=entity_status:Open`
- **Returns:** Only open jobs
- **Use Case:** Active job listings

### 5. Location Filtering
- **URL:** `?organization=1637&stateCity=NY,New%20York`
- **Returns:** Jobs in specific city
- **Use Case:** Location-based job searches

### 6. Category Filtering
- **URL:** `?organization=1637&facet=primary_category:Faculty`
- **Returns:** Jobs in specific category
- **Use Case:** Department-specific job listings

### 7. Date Range Filtering
- **URL:** `?organization=1637&openedDate=2023-08-15`
- **Returns:** Jobs opened on or after specific date
- **Use Case:** Recent job listings

### 8. Update Date Filtering
- **URL:** `?organization=1637&updatedDate=2023-07-25T18:00:00,2023-07-27T23:00:30&timeZone=UTC`
- **Returns:** Jobs updated within date range
- **Use Case:** Recently updated jobs

## Implementation Strategy for Virtual CPT

### Phase 1: Job List Discovery
**Approach:** Use the main API endpoint to discover available jobs

```php
// Get total job count and first batch
$api_url = "https://jobsapi-internal.m-cloud.io/api/stjob?organization=1637&limit=1";
$response = wp_remote_get($api_url);
$data = json_decode(wp_remote_retrieve_body($response), true);

$total_jobs = $data['totalHits']; // 1061 jobs available
```

### Phase 2: Job ID Collection
**Approach:** Paginate through all jobs to collect job IDs

```php
// Collect all job IDs
$job_ids = [];
$limit = 100; // Get 100 jobs per request
$total_pages = ceil($total_jobs / $limit);

for ($page = 1; $page <= $total_pages; $page++) {
    $offset = ($page - 1) * $limit + 1;
    $api_url = "https://jobsapi-internal.m-cloud.io/api/stjob?organization=1637&limit={$limit}&offset={$offset}";
    
    $response = wp_remote_get($api_url);
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    foreach ($data['queryResult'] as $job) {
        $job_ids[] = $job['id'];
    }
}
```

### Phase 3: Caching Strategy
**Approach:** Cache job lists and individual job data

```php
// Cache job list for 1 hour
set_transient('cws_available_job_ids', $job_ids, HOUR_IN_SECONDS);

// Cache individual jobs for 30 minutes
foreach ($job_ids as $job_id) {
    $job_data = $this->get_job_data($job_id);
    set_transient("cws_job_data_{$job_id}", $job_data, 30 * MINUTE_IN_SECONDS);
}
```

### Phase 4: Virtual Post Creation
**Approach:** Create virtual posts from cached job data

```php
// Map API fields to WordPress post fields
$post->post_title = $job_data['title'];
$post->post_content = $job_data['description'];
$post->post_excerpt = wp_trim_words($job_data['description'], 55);

// Map to custom meta
$post->cws_job_id = $job_data['id'];
$post->cws_job_company = $job_data['company_name'];
$post->cws_job_location = $job_data['primary_city'] . ', ' . $job_data['primary_state'];
$post->cws_job_department = $job_data['department'];
$post->cws_job_category = $job_data['primary_category'];
$post->cws_job_status = $job_data['entity_status'];
$post->cws_job_url = $job_data['url'];
$post->cws_job_open_date = $job_data['open_date'];
$post->cws_job_update_date = $job_data['update_date'];
```

### Phase 5: Advanced Filtering Implementation
**Approach:** Implement sophisticated filtering using API parameters

```php
// Example: Recent jobs in specific location
$api_url = add_query_arg(array(
    'organization' => $org_id,
    'openedDate' => date('Y-m-d', strtotime('-30 days')), // Last 30 days
    'stateCity' => 'NY,New York',
    'facet' => 'entity_status:Open',
    'sortField' => 'open_date',
    'sortOrder' => 'desc'
), $base_url);

// Example: Recently updated jobs
$api_url = add_query_arg(array(
    'organization' => $org_id,
    'updatedDate' => date('Y-m-d\TH:i:s', strtotime('-7 days')) . ',' . date('Y-m-d\TH:i:s'),
    'timeZone' => 'America/New_York',
    'facet' => 'entity_status:Open'
), $base_url);

// Example: Jobs in specific category with date range
$api_url = add_query_arg(array(
    'organization' => $org_id,
    'facet' => 'primary_category:Faculty',
    'openedDate' => '2023-01-01,2023-12-31', // Year 2023
    'sortField' => 'open_date',
    'sortOrder' => 'desc'
), $base_url);
```

## API Limitations and Considerations

### 1. Rate Limiting
- **Unknown limits** - need to test
- **Recommendation:** Implement request throttling
- **Cache aggressively** to minimize API calls

### 2. Data Freshness
- **Job updates:** `update_date` field available
- **Status changes:** `entity_status` field
- **Recommendation:** Cache for 30 minutes, refresh on demand

### 3. Error Handling
- **Network failures:** Implement retry logic
- **Invalid job IDs:** Graceful fallback
- **API changes:** Version monitoring

### 4. Performance
- **Large job lists:** 1061 jobs = ~11 API calls for full sync
- **Individual jobs:** Cache each job separately
- **Recommendation:** Background sync process

## Recommended Implementation Phases

### Phase 1: Basic Virtual CPT
1. **Static job list** from admin settings
2. **Individual job caching**
3. **Basic virtual post creation**

### Phase 2: Dynamic Job Discovery
1. **API job list collection**
2. **Background sync process**
3. **Status-based filtering**

### Phase 3: Advanced Features
1. **Location-based filtering**
2. **Category-based filtering**
3. **Search integration**

### Phase 4: Performance Optimization
1. **Intelligent caching**
2. **Request batching**
3. **Error recovery**

## Testing Scenarios

### 1. Single Job Page
- **URL:** `/job/10154446/`
- **Expected:** Virtual post with job data
- **Test:** All meta fields accessible

### 2. Job Archive
- **URL:** `/job/`
- **Expected:** List of all available jobs
- **Test:** Pagination works correctly

### 3. Category Filtering
- **URL:** `/job/?category=Faculty`
- **Expected:** Only faculty jobs
- **Test:** API filtering works

### 4. Location Filtering
- **URL:** `/job/?location=New%20York`
- **Expected:** Only NY jobs
- **Test:** Location search works

### 5. Search Integration
- **URL:** `/job/?s=infectious`
- **Expected:** Jobs matching search
- **Test:** Search functionality works

## Error Scenarios

### 1. API Unavailable
- **Fallback:** Use cached data
- **User Experience:** Show last known data
- **Admin Notification:** Log API failures

### 2. Invalid Job ID
- **Fallback:** 404 page
- **User Experience:** Clear error message
- **Admin Notification:** Log invalid IDs

### 3. Cache Miss
- **Fallback:** Direct API call
- **User Experience:** Slight delay
- **Admin Notification:** Cache warming needed

### 4. Large Data Sets
- **Fallback:** Paginated loading
- **User Experience:** Progressive loading
- **Admin Notification:** Performance monitoring
