<?php
/**
 * Localization class for WP Git Plugins
 *
 * Centralizes all localized strings and data for JavaScript files.
 * This provides a single source of truth for all translations and 
 * makes it easier to maintain consistent messaging across the plugin.
 *
 * @package    WP_Git_Plugins
 * @subpackage Localization
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @link       https://code045.nl/plugins/wp-git-plugins
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Localization {

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
            'deleting' => __('Deleting...', 'wp-git-plugins'),
            'activating' => __('Activating...', 'wp-git-plugins'),
            'deactivating' => __('Deactivating...', 'wp-git-plugins'),
            'changing_branch' => __('Switching branch...', 'wp-git-plugins'),
            'refreshing' => __('Refreshing...', 'wp-git-plugins'),
            'error' => __('Error', 'wp-git-plugins'),
            'success' => __('Success', 'wp-git-plugins'),
            'updated' => __('Updated!', 'wp-git-plugins'),
            'activate' => __('Activate', 'wp-git-plugins'),
            'deactivate' => __('Deactivate', 'wp-git-plugins'),

            // Confirmation messages
            'confirm_update' => __('Are you sure you want to update this plugin from version %s to %s?', 'wp-git-plugins'),
            'confirm_delete' => __('Are you sure you want to delete this repository? This will not uninstall the plugin.', 'wp-git-plugins'),
            'confirm_remove' => __('Are you sure you want to remove this repository? This will not delete the plugin files.', 'wp-git-plugins'),
            'confirm_branch_change' => __('Are you sure you want to switch to the %s branch? This will update the plugin files.', 'wp-git-plugins'),
            'confirm_activate' => __('Are you sure you want to activate this plugin?', 'wp-git-plugins'),
            'confirm_deactivate' => __('Are you sure you want to deactivate this plugin?', 'wp-git-plugins'),
            'clear_confirm' => __('Are you sure you want to clear the log?', 'wp-git-plugins'),

            // Success messages
            'update_success' => __('Plugin updated successfully to version %s.', 'wp-git-plugins'),
            'activate_success' => __('Plugin activated successfully.', 'wp-git-plugins'),
            'deactivate_success' => __('Plugin deactivated successfully.', 'wp-git-plugins'),
            'update_available' => __('Update available: %s (current: %s)', 'wp-git-plugins'),
            'no_updates' => __('This plugin is up to date.', 'wp-git-plugins'),
            'checking_updates' => __('Checking for updates...', 'wp-git-plugins'),
            'updates_available' => __('Updates available', 'wp-git-plugins'),

            // Error messages
            'update_error' => __('An error occurred while updating the plugin.', 'wp-git-plugins'),
            'update_check_error' => __('Failed to check for updates.', 'wp-git-plugins'),
            'activate_error' => __('Failed to activate plugin.', 'wp-git-plugins'),
            'deactivate_error' => __('Failed to deactivate plugin.', 'wp-git-plugins'),
            'delete_error' => __('Failed to delete the repository.', 'wp-git-plugins'),
            'branch_change_error' => __('Failed to switch branch.', 'wp-git-plugins'),
            'error_deactivating' => __('Failed to deactivate the plugin before update.', 'wp-git-plugins'),
            'update_success_reactivate_failed' => __('Plugin updated but could not be reactivated. Please activate it manually.', 'wp-git-plugins'),
            'error_activating_plugin' => __('Error activating plugin', 'wp-git-plugins'),
            'error_deactivating_plugin' => __('Error deactivating plugin', 'wp-git-plugins'),
            'error_removing_repo' => __('Error removing repository', 'wp-git-plugins'),
            'error_deleting_repo' => __('Error deleting repository', 'wp-git-plugins'),
            'error_updating_branch' => __('Error updating branch', 'wp-git-plugins'),
            'error_getting_branches' => __('Error getting branches', 'wp-git-plugins'),
            'error_adding_repo' => __('Error adding repository', 'wp-git-plugins'),
            'error_installing_plugin' => __('Error installing plugin', 'wp-git-plugins'),
        );
    }

    /**
     * Get debug-specific localized data for the debug page.
     *
     * @since 1.0.0
     * @return array Debug-specific localized data
     */
    public static function get_debug_localized_data() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wp_git_plugins_debug_nonce'), // Debug uses different nonce
            'i18n' => array(
                'refreshing' => __('Refreshing...', 'wp-git-plugins'),
                'error' => __('Error:', 'wp-git-plugins'),
                'clear_confirm' => __('Are you sure you want to clear the log?', 'wp-git-plugins'),
            )
        );
    }

    /**
     * Get admin menu and page strings.
     * Used for menu titles and page headers.
     *
     * @since 1.0.0
     * @return array Menu and page strings
     */
    public static function get_menu_strings() {
        return array(
            'git_plugins' => __('Git Plugins', 'wp-git-plugins'),
            'settings' => __('Settings', 'wp-git-plugins'),
            'debug_log' => __('Debug Log', 'wp-git-plugins'),
            'dashboard' => __('Dashboard', 'wp-git-plugins'),
        );
    }

    /**
     * Get form and button strings.
     * Used for forms, buttons, and submit actions.
     *
     * @since 1.0.0
     * @return array Form and button strings
     */
    public static function get_form_strings() {
        return array(
            'save_settings' => __('Save Settings', 'wp-git-plugins'),
            'save_github_settings' => __('Save GitHub Settings', 'wp-git-plugins'),
        );
    }

    /**
     * Get notice and message strings.
     * Used for admin notices and user feedback.
     *
     * @since 1.0.0
     * @return array Notice and message strings
     */
    public static function get_notice_strings() {
        return array(
            'repository_added' => __('Repository added successfully.', 'wp-git-plugins'),
            'repository_removed' => __('Repository removed successfully.', 'wp-git-plugins'),
            'repository_deleted' => __('Repository deleted successfully.', 'wp-git-plugins'),
            'branch_changed' => __('Branch changed successfully.', 'wp-git-plugins'),
            'failed_delete_repo' => __('Failed to delete repository.', 'wp-git-plugins'),
            'invalid_repo_id' => __('Invalid repository ID.', 'wp-git-plugins'),
            'security_check_failed' => __('Security check failed', 'wp-git-plugins'),
        );
    }

    /**
     * Get time and date strings.
     * Used for displaying relative time information.
     *
     * @since 1.0.0
     * @return array Time and date strings
     */
    public static function get_time_strings() {
        return array(
            'time_ago' => __('%s ago', 'wp-git-plugins'),
        );
    }

    /**
     * Get GitHub-specific strings.
     * Used for GitHub integration messages and links.
     *
     * @since 1.0.0
     * @return array GitHub-specific strings
     */
    public static function get_github_strings() {
        return array(
            'generate_token_link' => __('<a href="%s" target="_blank">Generate a new token</a> with the <code>repo</code> scope.', 'wp-git-plugins'),
        );
    }

    /**
     * Localize a script with the standard WP Git Plugins data.
     * Convenience method to localize scripts consistently across the plugin.
     *
     * @since 1.0.0
     * @param string $script_handle The script handle to localize
     * @param string $object_name The JavaScript object name (default: 'wpGitPlugins')
     * @param array $additional_data Additional data to merge with the standard localized data
     */
    public static function localize_script($script_handle, $object_name = 'wpGitPlugins', $additional_data = array()) {
        $localized_data = array_merge(self::get_localized_data(), $additional_data);
        wp_localize_script($script_handle, $object_name, $localized_data);
    }

    /**
     * Localize a script with debug-specific data.
     * Convenience method specifically for debug page scripts.
     *
     * @since 1.0.0
     * @param string $script_handle The script handle to localize
     * @param string $object_name The JavaScript object name (default: 'wpGitPluginsDebug')
     */
    public static function localize_debug_script($script_handle, $object_name = 'wpGitPluginsDebug') {
        wp_localize_script($script_handle, $object_name, self::get_debug_localized_data());
    }
}
