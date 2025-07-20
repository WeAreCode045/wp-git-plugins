# WP Git Plugins - Update Button Troubleshooting Guide

## Current Issue
The update button is not showing when an update is available.

## Debugging Steps

### 1. Check Repository Data
Access `/wp-content/plugins/wp-git-plugins/debug-repos.php` in your browser to see:
- Current repository data structure
- Version values in database
- Version comparison results

### 2. Check WordPress Debug Log
Enable WordPress debugging in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

Then check `/wp-content/debug.log` for messages like:
- "WP Git Plugins Debug - Repo X: plugin_installed=..."
- "WP Git Plugins Debug - Repo X: git_version=..."

### 3. Browser Console Debug
1. Open browser developer tools (F12)
2. Go to the repository list page
3. Click "Check Version" button
4. Look for console messages showing:
   - Version comparison debug info
   - Whether update_available is true/false

### 4. Manual Version Check
Use the version check AJAX endpoint directly:
```javascript
// In browser console
jQuery.post(ajaxurl, {
    action: 'wp_git_plugins_check_version',
    _ajax_nonce: wpGitPlugins.ajax_nonce,
    repo_id: 1  // Replace with actual repo ID
}, function(response) {
    console.log('Version check result:', response);
});
```

## Expected Behavior

1. **Initial Load**: If `git_version > local_version`, update button should show
2. **After Version Check**: If update available, check button should become update button
3. **Version Display**: Latest version should show in red if update available

## Files Modified for Debugging

- Added debug logging to template (`repository-list.php`)
- Added console logging to JavaScript (`repository-list.js`)
- Added git_version and local_version keys to database mapping
- Created debug script (`debug-repos.php`)

## Quick Fix Test

If the update button still doesn't show:

1. **Check database directly**:
   ```sql
   SELECT id, gh_name, local_version, git_version FROM wp_wpgp_repos;
   ```

2. **Force an update check** via AJAX
3. **Verify template logic** by temporarily hardcoding `$update_available = true`

## Cleanup After Debugging

Remember to remove debug output from production:
- Remove error_log statements from template
- Remove console.log statements from JavaScript
- Delete debug-repos.php file
