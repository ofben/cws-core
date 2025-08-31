# CWS Core Plugin Development Instructions

## Overview
CWS Core is a WordPress plugin that connects to a public API endpoint to fetch job data and display it on designated pages. The plugin dynamically constructs API URLs based on job IDs extracted from URL paths.

## Plugin Structure
```
cws-core/
├── cws-core.php                 # Main plugin file
├── includes/
│   ├── class-cws-core.php       # Main plugin class
│   ├── class-cws-core-admin.php # Admin functionality
│   ├── class-cws-core-api.php   # API handling
│   ├── class-cws-core-cache.php # Caching functionality
│   └── class-cws-core-public.php # Frontend functionality
├── admin/
│   ├── css/
│   │   └── admin.css
│   ├── js/
│   │   └── admin.js
│   └── views/
│       └── settings-page.php
├── public/
│   ├── css/
│   │   └── public.css
│   └── js/
│       └── public.js
├── languages/                   # For internationalization
├── readme.txt
└── uninstall.php
```

## Error Handling Ideas:
1. **Graceful Degradation**: Show a user-friendly message when API is unavailable
2. **Admin Notifications**: Log errors to WordPress debug log and optionally email admin
3. **Fallback Content**: Display cached data or placeholder content when API fails
4. **Retry Logic**: Implement exponential backoff for temporary failures
5. **Health Checks**: Add a status indicator in admin panel showing API connectivity

## Phase 1: Core Plugin Setup

### 1.1 Main Plugin File (cws-core.php)
- Plugin header with proper metadata
- Define plugin constants (version, path, URL)
- Autoloader setup
- Plugin activation/deactivation hooks
- Initialize main plugin class

### 1.2 Main Plugin Class (includes/class-cws-core.php)
- Singleton pattern implementation
- Hook registration for admin and public functionality
- Plugin initialization methods
- Utility methods for common operations

## Phase 2: Admin Settings Panel

### 2.1 Admin Class (includes/class-cws-core-admin.php)
- Modern settings panel using WordPress Settings API
- Sophisticated UI with:
  - API endpoint URL field with validation
  - Organization ID field
  - Page selection dropdown (for core slug)
  - Connection test functionality
  - Error logging display
  - Cache management interface

### 2.2 Settings Page Features
- **API Configuration Section**:
  - Endpoint URL input with URL validation
  - Organization ID input
  - "Test Connection" button
  - Connection status indicator

- **Page Configuration Section**:
  - Dropdown to select WordPress page for core slug
  - Preview of constructed URLs
  - URL pattern explanation

- **Advanced Settings Section**:
  - Cache duration settings
  - Error handling preferences
  - Debug mode toggle

### 2.3 Admin Assets
- Modern CSS styling for settings panel
- JavaScript for dynamic functionality
- AJAX handlers for connection testing

## Phase 3: API Integration

### 3.1 API Class (includes/class-cws-core-api.php)
- **URL Construction**:
  - Build API URLs using endpoint + org ID + job ID
  - Validate URL format
  - Handle URL encoding

- **Request Handling**:
  - HTTP requests using wp_remote_get()
  - Timeout configuration
  - User agent setting
  - Error handling with specific error codes

- **Response Processing**:
  - JSON validation
  - Response sanitization
  - Error response handling

### 3.2 Error Handling Strategy
1. **Network Errors**:
   - Connection timeout (30 seconds)
   - DNS resolution failures
   - SSL certificate issues

2. **HTTP Errors**:
   - 404 (Job not found)
   - 500+ (Server errors)
   - Rate limiting (429)

3. **Data Errors**:
   - Invalid JSON response
   - Missing required fields
   - Malformed data structure

4. **User Experience**:
   - Display user-friendly error messages
   - Log technical details for debugging
   - Provide fallback content when possible

## Phase 4: Frontend Integration

### 4.1 Public Class (includes/class-cws-core-public.php)
- **URL Parsing**:
  - Extract job ID from URL path
  - Validate job ID format
  - Handle various URL patterns

- **Content Integration**:
  - Hook into WordPress content filters
  - Inject JSON data into page
  - Add JavaScript variables

### 4.2 URL Pattern Handling
- Support patterns like:
  - `/job/123/`
  - `/job/123/job-title/`
  - `/job/123/` (with trailing slash)
- Extract job ID using regex patterns
- Handle edge cases and invalid URLs

### 4.3 Content Output
- **JSON Display**:
  - Format JSON for readability
  - Add syntax highlighting
  - Collapsible sections for large responses

- **JavaScript Integration**:
  - Add data as global JavaScript variable
  - Include in wp_localize_script()
  - Make available to theme JavaScript

## Phase 5: Caching Implementation

### 5.1 Cache Class (includes/class-cws-core-cache.php)
- **WordPress Transients API**:
  - Store API responses as transients
  - Configurable cache duration
  - Automatic cache invalidation

- **Cache Management**:
  - Admin interface for cache control
  - Manual cache clearing
  - Cache statistics display

## Implementation Details

### Security Considerations
- Sanitize all user inputs
- Validate API responses
- Use nonces for admin actions
- Escape output properly
- Implement rate limiting

### Performance Optimization
- Efficient URL parsing
- Minimal database queries
- Optimized asset loading
- Caching strategies

### WordPress Integration
- Follow WordPress coding standards
- Use WordPress hooks and filters
- Implement proper internationalization
- Follow plugin development best practices

## Testing Strategy
1. **Unit Tests**: Test individual classes and methods
2. **Integration Tests**: Test API communication
3. **User Acceptance Tests**: Test complete user workflows
4. **Error Scenario Testing**: Test all error conditions

## Deployment Checklist
- [ ] Plugin activation testing
- [ ] Settings panel functionality
- [ ] API connection testing
- [ ] URL parsing validation
- [ ] Error handling verification
- [ ] Cache functionality testing
- [ ] Security review
- [ ] Performance testing
- [ ] Cross-browser compatibility
- [ ] WordPress version compatibility

## Future Considerations
- REST API endpoints for external access
- Webhook support for real-time updates
- Advanced caching with Redis/Memcached
- Multi-site compatibility
- Import/export settings functionality
