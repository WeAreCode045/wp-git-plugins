<?php
/**
 * Internationalization and Localization class for WP Git Plugins
 *
 * Handles WordPress textdomain loading and centralizes all localized strings 
 * and data for JavaScript files. This provides a single source of truth for 
 * all translations and makes it easier to maintain consistent messaging.
 *
 * @package    WP_Git_Plugins
 * @subpackage Internationalization
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @link       https://code045.nl/plugins/wp-git-plugins
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_i18n {

    /**
     * Load the plugin text domain for translation.
     *
     * @since 1.0.0
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wp-git-plugins',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }

    /**
     * Get the complete localized data array for JavaScript files.
     * This includes AJAX configuration and all translation strings.
     *
     * @since 1.0.0
     * @return array Complete localized data for wp_localize_script
     */
    public static function get_localized_data() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('wp_git_plugins_ajax'),
            'i18n' => self::get_i18n_strings()
        );
    }

    /**
     * Get all internationalization strings for JavaScript.
     * Groups strings by functionality for better organization.
     *
     * @since 1.0.0
     * @return array All i18n strings organized by category
     */
    public static function get_i18n_strings() {
        return array(
            // General UI strings
            'checking' => __('Checking...', 'wp-git-plugins'),
            'checking_all' => __('Checking All...', 'wp-git-plugins'),
            'updating' => __('Updating...', 'wp-git-plugins'),
            'updating_all' => __('Updating All...', 'wp-git-plugins'),
            'installing' => __('Installing...', 'wp-git-plugins'),
            'uninstalling' => __('Uninstalling...', 'wp-git-plugins'),
            'success' => __('Success!', 'wp-git-plugins'),
            'error' => __('Error:', 'wp-git-plugins'),
            'warning' => __('Warning:', 'wp-git-plugins'),
            'confirm' => __('Are you sure?', 'wp-git-plugins'),
            'loading' => __('Loading...', 'wp-git-plugins'),
            'retry' => __('Retry', 'wp-git-plugins'),
            'cancel' => __('Cancel', 'wp-git-plugins'),
            'close' => __('Close', 'wp-git-plugins'),
            'save' => __('Save', 'wp-git-plugins'),
            'delete' => __('Delete', 'wp-git-plugins'),
            'edit' => __('Edit', 'wp-git-plugins'),
            'view' => __('View', 'wp-git-plugins'),
            
            // Repository specific strings
            'repository_added' => __('Repository added successfully', 'wp-git-plugins'),
            'repository_updated' => __('Repository updated successfully', 'wp-git-plugins'),
            'repository_removed' => __('Repository removed successfully', 'wp-git-plugins'),
            'repository_not_found' => __('Repository not found', 'wp-git-plugins'),
            'invalid_repository_url' => __('Invalid repository URL', 'wp-git-plugins'),
            'repository_already_exists' => __('Repository already exists', 'wp-git-plugins'),
            'checking_repository' => __('Checking repository...', 'wp-git-plugins'),
            'fetching_repository_info' => __('Fetching repository information...', 'wp-git-plugins'),
            
            // Plugin operation strings
            'plugin_installed' => __('Plugin installed successfully', 'wp-git-plugins'),
            'plugin_updated' => __('Plugin updated successfully', 'wp-git-plugins'),
            'plugin_activated' => __('Plugin activated successfully', 'wp-git-plugins'),
            'plugin_deactivated' => __('Plugin deactivated successfully', 'wp-git-plugins'),
            'plugin_deleted' => __('Plugin deleted successfully', 'wp-git-plugins'),
            'plugin_installation_failed' => __('Plugin installation failed', 'wp-git-plugins'),
            'plugin_update_failed' => __('Plugin update failed', 'wp-git-plugins'),
            'plugin_not_found' => __('Plugin not found', 'wp-git-plugins'),
            
            // Error handling strings
            'ajax_error' => __('AJAX request failed', 'wp-git-plugins'),
            'network_error' => __('Network error occurred', 'wp-git-plugins'),
            'timeout_error' => __('Request timed out', 'wp-git-plugins'),
            'permission_error' => __('Insufficient permissions', 'wp-git-plugins'),
            'validation_error' => __('Validation failed', 'wp-git-plugins'),
            'unexpected_error' => __('An unexpected error occurred', 'wp-git-plugins'),
            
            // Status messages
            'status_active' => __('Active', 'wp-git-plugins'),
            'status_inactive' => __('Inactive', 'wp-git-plugins'),
            'status_updating' => __('Updating', 'wp-git-plugins'),
            'status_installing' => __('Installing', 'wp-git-plugins'),
            'status_available' => __('Available', 'wp-git-plugins'),
            'status_up_to_date' => __('Up to date', 'wp-git-plugins'),
            'status_update_available' => __('Update available', 'wp-git-plugins'),
            
            // Debug strings
            'debug_info' => __('Debug Information', 'wp-git-plugins'),
            'console_output' => __('Console Output', 'wp-git-plugins'),
            'error_log' => __('Error Log', 'wp-git-plugins'),
            'system_info' => __('System Information', 'wp-git-plugins'),
            'clear_log' => __('Clear Log', 'wp-git-plugins'),
            'download_log' => __('Download Log', 'wp-git-plugins'),
            
            // Confirmation messages
            'confirm_delete_repository' => __('Are you sure you want to delete this repository?', 'wp-git-plugins'),
            'confirm_delete_plugin' => __('Are you sure you want to delete this plugin?', 'wp-git-plugins'),
            'confirm_update_all' => __('Are you sure you want to update all plugins?', 'wp-git-plugins'),
            'confirm_clear_log' => __('Are you sure you want to clear the log?', 'wp-git-plugins'),
        );
    }

    /**
     * Get menu-specific translation strings.
     *
     * @since 1.0.0
     * @return array Menu translation strings
     */
    public static function get_menu_strings() {
        return array(
            'menu_title' => __('Git Plugins', 'wp-git-plugins'),
            'page_title_dashboard' => __('Git Plugins Dashboard', 'wp-git-plugins'),
            'page_title_repositories' => __('Repositories', 'wp-git-plugins'),
            'page_title_settings' => __('Git Plugins Settings', 'wp-git-plugins'),
            'page_title_debug' => __('Debug', 'wp-git-plugins')
        );
    }

    /**
     * Get notice-specific translation strings.
     *
     * @since 1.0.0
     * @return array Notice translation strings
     */
    public static function get_notice_strings() {
        return array(
            'security_check_failed' => __('Security check failed.', 'wp-git-plugins'),
            'invalid_request' => __('Invalid request.', 'wp-git-plugins'),
            'operation_completed' => __('Operation completed successfully.', 'wp-git-plugins'),
            'operation_failed' => __('Operation failed.', 'wp-git-plugins')
        );
    }

    /**
     * Convenience method to localize a script with our standard data.
     *
     * @since 1.0.0
     * @param string $handle Script handle
     * @param string $object_name JavaScript object name (default: 'wpGitPlugins')
     */
    public static function localize_script($handle, $object_name = 'wpGitPlugins') {
        wp_localize_script($handle, $object_name, self::get_localized_data());
    }

    /**
     * Get time-related translation strings.
     *
     * @since 1.0.0
     * @return array Time translation strings
     */
    public static function get_time_strings() {
        return array(
            'just_now' => __('Just now', 'wp-git-plugins'),
            'minutes_ago' => __('%s minutes ago', 'wp-git-plugins'),
            'hours_ago' => __('%s hours ago', 'wp-git-plugins'),
            'days_ago' => __('%s days ago', 'wp-git-plugins'),
            'weeks_ago' => __('%s weeks ago', 'wp-git-plugins'),
            'months_ago' => __('%s months ago', 'wp-git-plugins'),
            'years_ago' => __('%s years ago', 'wp-git-plugins'),
            'never' => __('Never', 'wp-git-plugins')
        );
    }

    /**
     * Get form-related translation strings.
     *
     * @since 1.0.0
     * @return array Form translation strings
     */
    public static function get_form_strings() {
        return array(
            'required_field' => __('This field is required', 'wp-git-plugins'),
            'optional_field' => __('Optional', 'wp-git-plugins'),
            'invalid_email' => __('Please enter a valid email address', 'wp-git-plugins'),
            'invalid_url' => __('Please enter a valid URL', 'wp-git-plugins'),
            'password_mismatch' => __('Passwords do not match', 'wp-git-plugins'),
            'weak_password' => __('Password is too weak', 'wp-git-plugins'),
            'form_submitted' => __('Form submitted successfully', 'wp-git-plugins'),
            'form_error' => __('There was an error submitting the form', 'wp-git-plugins')
        );
    }

    /**
     * Get GitHub-related translation strings.
     *
     * @since 1.0.0
     * @return array GitHub translation strings
     */
    public static function get_github_strings() {
        return array(
            'github_token' => __('GitHub Token', 'wp-git-plugins'),
            'github_token_description' => __('Personal access token for private repositories', 'wp-git-plugins'),
            'token_required' => __('Token is required for private repositories', 'wp-git-plugins'),
            'invalid_token' => __('Invalid GitHub token', 'wp-git-plugins'),
            'token_saved' => __('GitHub token saved successfully', 'wp-git-plugins'),
            'rate_limit_exceeded' => __('GitHub API rate limit exceeded', 'wp-git-plugins')
        );
    }

    /**
     * Localize debug scripts with debug-specific data.
     *
     * @since 1.0.0
     * @param string $handle Script handle
     */
    public static function localize_debug_script($handle) {
        $debug_data = array_merge(
            self::get_localized_data(),
            array(
                'debug' => array(
                    'enabled' => defined('WP_DEBUG') && WP_DEBUG,
                    'log_enabled' => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                    'display_enabled' => defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY
                )
            )
        );
        
        wp_localize_script($handle, 'wpGitPluginsDebug', $debug_data);
    }
}
