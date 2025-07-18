<?php
/**
 * Admin class for WP Git Plugins
 *
 * Handles all admin-specific functionality including menus, pages, and AJAX callbacks.
 *
 * @package    WP_Git_Plugins
 * @subpackage Admin
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @link       https://code045.nl/plugins/wp-git-plugins
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Admin {
    /**
     * The plugin name.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The plugin name.
     */
    private $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of the plugin.
     */
    private $version;

    /**
     * The settings instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Settings    $settings    The settings instance.
     */
    private $settings;
    private $repository;

    /**
     * The single instance of the class.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Admin    $instance    The single instance of the class.
     */
    private static $instance = null;

    /**
     * Get the singleton instance of the class.
     *
     * @since 1.0.0
     * @param string $plugin_name The plugin name.
     * @param string $version The plugin version.
     * @return WP_Git_Plugins_Admin The singleton instance.
     */
    public static function get_instance($plugin_name = 'wp-git-plugins', $version = '1.0.0') {
        if (is_null(self::$instance)) {
            self::$instance = new self($plugin_name, $version);
        }
        return self::$instance;
    }

    /**
     * Initialize the class and set its properties.
     *
     * @since 1.0.0
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     */
    private function __construct($plugin_name = 'wp-git-plugins', $version = '1.0.0') {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->settings = new WP_Git_Plugins_Settings($this->plugin_name, $this->version);
        $this->repository = new WP_Git_Plugins_Repository($this->settings);
        $this->register_ajax_handlers();
    }

    /**
     * Verify AJAX nonce and permissions.
     *
     * @since 1.1.1
     */
    private function verify_ajax_request() {
        // Check if it's an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error(['message' => __('Invalid request', 'wp-git-plugins')], 400);
        }

        // Accept both _ajax_nonce and nonce for compatibility
        $nonce = isset($_REQUEST['_ajax_nonce']) ? $_REQUEST['_ajax_nonce'] : (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_git_plugins_ajax')) {
            wp_send_json_error(['message' => __('Security check failed', 'wp-git-plugins')], 403);
        }

        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have sufficient permissions', 'wp-git-plugins')], 403);
        }
    }

    /**
     * Register all AJAX handlers.
     *
     * @since 1.0.0
     */
    private function register_ajax_handlers() {
        // Admin AJAX actions
        add_action('wp_ajax_wp_git_plugins_activate_plugin', array($this, 'ajax_activate_plugin'));
        add_action('wp_ajax_wp_git_plugins_deactivate_plugin', array($this, 'ajax_deactivate_plugin'));
        add_action('wp_ajax_wp_git_plugins_remove_repo', array($this, 'ajax_remove_repo'));
        add_action('wp_ajax_wp_git_plugins_delete_repository', array($this, 'ajax_delete_repository'));
        add_action('wp_ajax_wp_git_plugins_check_updates', array($this, 'ajax_check_updates'));
        add_action('wp_ajax_wp_git_plugins_check_update', array($this, 'ajax_check_update'));
        add_action('wp_ajax_wp_git_plugins_change_branch', array($this, 'ajax_change_branch'));
        add_action('wp_ajax_wp_git_plugins_get_branches', array($this, 'ajax_get_branches'));
        add_action('wp_ajax_wp_git_plugins_add_repo', array($this, 'ajax_add_repo'));
        add_action('wp_ajax_wp_git_plugins_clear_log', array('WP_Git_Plugins_Debug', 'ajax_clear_log'));
        add_action('wp_ajax_wp_git_plugins_check_rate_limit', array('WP_Git_Plugins_Debug', 'ajax_check_rate_limit'));
        
        // Public AJAX actions
        add_action('wp_ajax_nopriv_wp_git_plugins_check_updates', array($this, 'ajax_check_updates_public'));
    }

    public function add_admin_menus() {
        // Add main menu and dashboard page
        add_menu_page(
            __('Git Plugins', 'wp-git-plugins'),
            __('Git Plugins', 'wp-git-plugins'),
            'manage_options',
            'wp-git-plugins',
            [$this, 'display_dashboard_page'],
            'dashicons-git'
        );
        
        // Add Settings submenu
        add_submenu_page(
            'wp-git-plugins',
            __('Settings', 'wp-git-plugins'),
            __('Settings', 'wp-git-plugins'),
            'manage_options',
            'wp-git-plugins-settings',
            [$this, 'display_settings_page']
        );
        
        // Add Debug Log submenu
        add_submenu_page(
            'wp-git-plugins',
            __('Debug Log', 'wp-git-plugins'),
            __('Debug Log', 'wp-git-plugins'),
            'manage_options',
            'wp-git-plugins-debug',
            [$this, 'display_debug_page']
        );
    }

    public function display_dashboard_page() {
        include WP_GIT_PLUGINS_DIR . 'templates/pages/dashboard-page.php';
    }
    
    public function display_settings_page() {
        // The form is now handled by options.php
        include WP_GIT_PLUGINS_DIR . 'templates/pages/settings-page.php';
    }
    
    public function display_debug_page() {
        include WP_GIT_PLUGINS_DIR . 'templates/pages/debug-page.php';
    }  
        
       
        

    public function enqueue_styles($hook) {
        if (strpos($hook, 'wp-git-plugins') === false) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name,
            WP_GIT_PLUGINS_URL . 'assets/css/styles.css',
            [],
            $this->version,
            'all'
        );
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wp-git-plugins') === false) {
            return;
        }
        
        // Enqueue main stylesheet
        wp_enqueue_style(
            $this->plugin_name,
            WP_GIT_PLUGINS_URL . 'assets/css/styles.css',
            [],
            $this->version,
            'all'
        );
        
        // Enqueue main admin script only if needed (not for repo list)
        // scripts.js removed; do not enqueue
        // Enqueue repository list script on dashboard page
        if ($hook === 'toplevel_page_wp-git-plugins') {
            wp_enqueue_script(
                $this->plugin_name . '-repository-list',
                WP_GIT_PLUGINS_URL . 'assets/js/repository-list.js',
                array('jquery'),
                $this->version,
                true
            );
        }
        
        // Localize script with AJAX URL and nonce
        $localized_data = array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('wp_git_plugins_ajax'),
            'i18n' => array(
                'checking' => __('Checking...', 'wp-git-plugins'),
                'checking_all' => __('Checking All...', 'wp-git-plugins'),
                'updating' => __('Updating...', 'wp-git-plugins'),
                'confirm_update' => __('Are you sure you want to update this plugin from version %s to %s?', 'wp-git-plugins'),
                'update_available' => __('Update available: %s (current: %s)', 'wp-git-plugins'),
                'no_updates' => __('This plugin is up to date.', 'wp-git-plugins'),
                'update_success' => __('Plugin updated successfully to version %s.', 'wp-git-plugins'),
                'update_error' => __('An error occurred while updating the plugin.', 'wp-git-plugins'),
                'update_check_error' => __('Failed to check for updates.', 'wp-git-plugins'),
                'error_deactivating' => __('Failed to deactivate the plugin before update.', 'wp-git-plugins'),
                'update_success_reactivate_failed' => __('Plugin updated but could not be reactivated. Please activate it manually.', 'wp-git-plugins'),
                'confirm_delete' => __('Are you sure you want to delete this repository? This will not uninstall the plugin.', 'wp-git-plugins'),
                'deleting' => __('Deleting...', 'wp-git-plugins'),
                'delete_error' => __('Failed to delete the repository.', 'wp-git-plugins'),
                'confirm_branch_change' => __('Are you sure you want to switch to the %s branch? This will update the plugin files.', 'wp-git-plugins'),
                'changing_branch' => __('Switching branch...', 'wp-git-plugins'),
                'branch_change_error' => __('Failed to switch branch.', 'wp-git-plugins'),
                'error' => __('Error', 'wp-git-plugins'),
                'success' => __('Success', 'wp-git-plugins'),
                'confirm_remove' => __('Are you sure you want to remove this repository? This will not delete the plugin files.', 'wp-git-plugins'),
                'updated' => __('Updated!', 'wp-git-plugins'),
                'checking_updates' => __('Checking for updates...', 'wp-git-plugins'),
                'updates_available' => __('Updates available', 'wp-git-plugins'),
                'error_activating_plugin' => __('Error activating plugin', 'wp-git-plugins'),
                'error_deactivating_plugin' => __('Error deactivating plugin', 'wp-git-plugins'),
                'error_removing_repo' => __('Error removing repository', 'wp-git-plugins'),
                'error_deleting_repo' => __('Error deleting repository', 'wp-git-plugins'),
                'error_updating_branch' => __('Error updating branch', 'wp-git-plugins'),
                'error_getting_branches' => __('Error getting branches', 'wp-git-plugins'),
                'error_adding_repo' => __('Error adding repository', 'wp-git-plugins'),
                'error_installing_plugin' => __('Error installing plugin', 'wp-git-plugins'),
            )
        );
        
        // Localize main admin script
        wp_localize_script(
            $this->plugin_name . '-admin',
            'wpGitPlugins',
            $localized_data
        );
        
        // Also localize the repository list script if it's enqueued
        if (wp_script_is($this->plugin_name . '-repository-list', 'enqueued')) {
            wp_localize_script(
                $this->plugin_name . '-repository-list',
                'wpGitPlugins',
                $localized_data
            );
        }
    }

    /**
     * AJAX handler for changing a repository branch.
     *
     * @since 1.0.0
     */
    public function ajax_change_branch() {
        try {
            $this->verify_ajax_request();
            if (!current_user_can('manage_options')) {
                throw new Exception(__('You do not have permission to manage branches.', 'wp-git-plugins'));
            }
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : '';
            if (empty($repo_id) || empty($branch)) {
                throw new Exception(__('Repository ID and branch are required.', 'wp-git-plugins'));
            }
            $repo = $this->repository->get_local_repository($repo_id);
            if (is_wp_error($repo) || empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            $repo_data = [
                'url' => $repo['git_repo_url'],
                'owner' => $repo['gh_owner'],
                'name' => $repo['gh_name']
            ];
            $result = $this->repository->change_repository_branch($repo_data, $branch);
            if (is_wp_error($result)) {
                $error_message = method_exists($result, 'get_error_message') ? $result->get_error_message() : __('Unknown error occurred.', 'wp-git-plugins');
                throw new Exception($error_message);
            }
            $updated_repo = $this->repository->get_local_repository($repo_id);
            wp_send_json_success([
                'message' => __('Branch changed successfully.', 'wp-git-plugins'),
                'repository' => $updated_repo,
                'redirect' => admin_url('admin.php?page=wp-git-plugins&branch_changed=1')
            ]);
        } catch (Exception $e) {
            $error_message = sprintf(__('Error changing branch: %s', 'wp-git-plugins'), $e->getMessage());
            error_log('WP Git Plugins: ' . $error_message);
            wp_send_json_error(['message' => $error_message]);
        }
    }

    /**
     * AJAX handler for adding a new repository.
     *
     * @since 1.0.0
     */
    public function ajax_add_repo() {
        try {
            $this->verify_ajax_request();
            if (!current_user_can('install_plugins') || !current_user_can('activate_plugins')) {
                throw new Exception(__('You do not have permission to install plugins.', 'wp-git-plugins'));
            }
            $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
            $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : 'main';
            $is_private = isset($_POST['is_private']) ? (bool) $_POST['is_private'] : false;
            $github_token = isset($_POST['github_token']) ? sanitize_text_field($_POST['github_token']) : '';
            if (empty($repo_url)) {
                throw new Exception(__('Please enter a valid repository URL.', 'wp-git-plugins'));
            }
            if (!empty($github_token)) {
                $this->settings->set_github_token($github_token);
            }
            $result = $this->repository->add_repository($repo_url, $branch);
            if (is_wp_error($result)) {
                throw new Exception(is_object($result) && method_exists($result, 'get_error_message') ? $result->get_error_message() : __('Unknown error occurred.', 'wp-git-plugins'));
            }
            $added_repo = $this->repository->get_local_repository($result);
            if (!$added_repo) {
                throw new Exception(__('Failed to retrieve the added repository.', 'wp-git-plugins'));
            }
            wp_send_json_success([
                'message' => __('Repository added successfully.', 'wp-git-plugins'),
                'repository' => $added_repo,
                'redirect' => admin_url('admin.php?page=wp-git-plugins&repo_added=1')
            ]);
        } catch (Exception $e) {
            $error_message = sprintf(__('Error adding repository: %s', 'wp-git-plugins'), $e->getMessage());
            error_log('WP Git Plugins: ' . $error_message);
            wp_send_json_error(['message' => $error_message]);
        }
    }

    /**
     * AJAX handler for changing a repository branch.
     *
     * @since 1.0.0
     */

    /**
     * AJAX handler for getting repository branches.
     *
     * @since 1.0.0
     */
    public function ajax_get_branches() {
        try {
            $this->verify_ajax_request();
            if (!current_user_can('manage_options')) {
                throw new Exception(__('You do not have permission to view branches.', 'wp-git-plugins'));
            }
            $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
            $github_token = isset($_POST['github_token']) ? sanitize_text_field($_POST['github_token']) : '';
            $is_private = isset($_POST['is_private']) ? (bool) $_POST['is_private'] : false;
            if (empty($repo_url)) {
                throw new Exception(__('Repository URL is required.', 'wp-git-plugins'));
            }
            if (!empty($github_token)) {
                $this->settings->set_github_token($github_token);
            }
            $parsed = $this->repository->parse_github_url($repo_url);
            if (is_wp_error($parsed)) {
                throw new Exception($parsed->get_error_message());
            }
            $branches = $this->repository->get_github_branches($parsed['owner'], $parsed['name']);
            if (is_wp_error($branches)) {
                throw new Exception($branches->get_error_message());
            }
            wp_send_json_success([
                'branches' => $branches,
                'default_branch' => in_array('main', $branches) ? 'main' : (in_array('master', $branches) ? 'master' : ($branches[0] ?? 'main'))
            ]);
        } catch (Exception $e) {
            $error_message = sprintf(__('Error fetching branches: %s', 'wp-git-plugins'), $e->getMessage());
            error_log('WP Git Plugins: ' . $error_message);
            wp_send_json_error(['message' => $error_message]);
        }
    }
    
    /**
     * AJAX handler for activating a plugin.
     *
     * @since 1.0.0
     */
    public function ajax_activate_plugin() {
        try {
            $this->verify_ajax_request();
            
            if (!current_user_can('activate_plugins')) {
                throw new Exception(__('You do not have permission to activate plugins.', 'wp-git-plugins'));
            }
            
            $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
            
            if (empty($plugin_slug)) {
                throw new Exception(__('Plugin slug is required.', 'wp-git-plugins'));
            }
            
            $result = activate_plugin($plugin_slug);
            
            if (is_wp_error($result)) {
                throw new Exception(is_object($result) && method_exists($result, 'get_error_message') ? $result->get_error_message() : __('Unknown error occurred.', 'wp-git-plugins'));
            }
            
            wp_send_json_success(['message' => __('Plugin activated successfully.', 'wp-git-plugins')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error activating plugin: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }
    
    /**
     * AJAX handler for deactivating a plugin.
     *
     * @since 1.0.0
     */
    public function ajax_deactivate_plugin() {
        try {
            $this->verify_ajax_request();
            
            if (!current_user_can('deactivate_plugins')) {
                throw new Exception(__('You do not have permission to deactivate plugins.', 'wp-git-plugins'));
            }
            
            $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
            
            if (empty($plugin_slug)) {
                throw new Exception(__('Plugin slug is required.', 'wp-git-plugins'));
            }
            
            deactivate_plugins($plugin_slug);
            
            wp_send_json_success(['message' => __('Plugin deactivated successfully.', 'wp-git-plugins')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error deactivating plugin: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }
    
    /**
     * AJAX handler for removing a repository.
     *
     * @since 1.0.0
     */
    public function ajax_remove_repo() {
        try {
            $this->verify_ajax_request();
            if (!current_user_can('delete_plugins')) {
                throw new Exception(__('You do not have permission to remove repositories.', 'wp-git-plugins'));
            }
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            $repo = $this->repository->get_local_repository($repo_id);
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            return $this->ajax_delete_repository();
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error removing repository: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }
    
    /**
     * AJAX handler for deleting a repository.
     *
     * @since 1.0.0
     */
    public function ajax_delete_repository() {
        try {
            $this->verify_ajax_request();
            if (!current_user_can('delete_plugins')) {
                throw new Exception(__('You do not have permission to delete repositories.', 'wp-git-plugins'));
            }
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            $repo = $this->repository->get_local_repository($repo_id);
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            $result = $this->repository->delete_local_repository($repo_id);
            if (is_wp_error($result)) {
                throw new Exception(is_object($result) && method_exists($result, 'get_error_message') ? $result->get_error_message() : __('Unknown error occurred.', 'wp-git-plugins'));
            }
            if (!empty($repo['plugin_slug'])) {
                $this->delete_plugin_files($repo['plugin_slug']);
            }
            wp_send_json_success(['message' => __('Repository deleted successfully.', 'wp-git-plugins')]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error deleting repository: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }

    /**
     * AJAX handler for checking repository updates.
     *
     * @since 1.1.5
     */
    public function ajax_check_update() {
        try {
            $this->verify_ajax_request();
            
            if (!current_user_can('update_plugins')) {
                wp_send_json_error(['message' => __('You do not have permission to check for updates.', 'wp-git-plugins')]);
            }
            
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $repo = $this->repository->get_local_repository($repo_id);
            
            if (!$repo) {
                wp_send_json_error(['message' => __('Repository not found.', 'wp-git-plugins')]);
            }
            
            // Get the installed version
            $installed_version = $repo['local_version'] ?? '0.0.0';
            
            // Check for updates first to ensure we have the latest git_version
            $update_check = $this->repository->check_repository_updates($repo_id);
            if (is_wp_error($update_check)) {
                wp_send_json_error(['message' => $update_check->get_error_message()]);
            }
            
            // Refresh repo data to get the updated git_version
            $repo = $this->repository->get_local_repository($repo_id);
            $latest_version = $repo['git_version'] ?? '0.0.0';
            
            // If we still don't have a git_version, try to get it directly
            if (empty($latest_version) || $latest_version === '0.0.0') {
                $latest_version = $this->repository->get_latest_version_from_github(
                    $repo['gh_owner'],
                    $repo['gh_name'],
                    $repo['branch'] ?? 'main'
                );
                
                if (is_wp_error($latest_version)) {
                    wp_send_json_error(['message' => $latest_version->get_error_message()]);
                }
                
                // Update the git_version in the database
                $this->repository->update_local_repo_version($repo_id, $latest_version, true);
            }
            
            $update_available = version_compare($latest_version, $installed_version, '>');
            
            wp_send_json_success([
                'repo_id' => $repo_id,
                'update_available' => $update_available,
                'current_version' => $installed_version,
                'latest_version' => $latest_version,
                'message' => $update_available 
                    ? sprintf(__('Update available: %s → %s', 'wp-git-plugins'), $installed_version, $latest_version)
                    : __('You have the latest version installed.', 'wp-git-plugins'),
                'repo' => $repo
            ]);
            
        } catch (Exception $e) {
            error_log('WP Git Plugins - Check Update Error: ' . $e->getMessage());
            wp_send_json_error(['message' => sprintf(__('Error checking for updates: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }
    
    /**
     * AJAX handler for checking repository updates
     *
     * @since 1.0.0
     */
    public function ajax_check_repository_updates() {
        try {
            // Verify the AJAX request
            $this->verify_ajax_request();
            
            // Check user capabilities
            if (!current_user_can('update_plugins')) {
                throw new Exception(__('You do not have permission to check for updates.', 'wp-git-plugins'));
            }
            
            // Sanitize input
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            
            // Check for updates
            $result = $this->repository->check_repository_updates($repo_id);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Return success response
            wp_send_json_success([
                'message' => __('Version check completed successfully.', 'wp-git-plugins'),
                'update_info' => $result
            ]);
            
        } catch (Exception $e) {
            // Log the error
            error_log('WP Git Plugins - Check Updates Error: ' . $e->getMessage());
            
            // Return error response
            wp_send_json_error([
                'message' => sprintf(__('Error checking for updates: %s', 'wp-git-plugins'), $e->getMessage())
            ]);
        }
    }
    
    /**
     * AJAX handler for bulk checking repository updates
     *
     * @since 1.0.0
     */
    public function ajax_bulk_check_updates() {
        try {
            // Verify the AJAX request
            $this->verify_ajax_request();
            
            // Check user capabilities
            if (!current_user_can('update_plugins')) {
                throw new Exception(__('You do not have permission to check for updates.', 'wp-git-plugins'));
            }
            
            // Check for updates for all repositories
            $results = $this->repository->check_all_repositories_for_updates();
            
            // Count updates
            $update_count = count(array_filter($results, function($repo) {
                return $repo['update_available'];
            }));
            
            // Return success response
            wp_send_json_success([
                'message' => sprintf(
                    _n(
                        'Checked all repositories. %d update available.',
                        'Checked all repositories. %d updates available.',
                        $update_count,
                        'wp-git-plugins'
                    ),
                    $update_count
                ),
                'results' => $results,
                'update_count' => $update_count
            ]);
            
        } catch (Exception $e) {
            // Log the error
            error_log('WP Git Plugins - Bulk Check Updates Error: ' . $e->getMessage());
            
            // Return error response
            wp_send_json_error([
                'message' => sprintf(__('Error checking for updates: %s', 'wp-git-plugins'), $e->getMessage())
            ]);
        }
    }
    
    /**
     * AJAX handler for updating a repository
     *
     * @since 1.0.0
     */
    public function ajax_update_repository() {
        $repo_id = 0;
        $plugin_slug = '';
        $was_active = false;
        
        try {
            // Verify the AJAX request
            $this->verify_ajax_request();
            
            // Check user capabilities
            if (!current_user_can('update_plugins')) {
                throw new Exception(__('You do not have permission to update plugins.', 'wp-git-plugins'));
            }
            
            // Sanitize input
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $force = isset($_POST['force']) ? (bool) $_POST['force'] : false;
            
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            
            // Get repository by ID
            $repo = $this->repository->get_local_repository($repo_id);
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            
            // Store plugin slug for error handling
            $plugin_slug = $repo['plugin_slug'] ?? '';
            error_log('WP Git Plugins - Starting update for repository ID: ' . $repo_id . ', Plugin: ' . $plugin_slug);
            
            // Check if we need to deactivate the plugin first
            if (!empty($plugin_slug)) {
                // Check if plugin is active
                $was_active = is_plugin_active($plugin_slug);
                
                if ($was_active) {
                    error_log('WP Git Plugins - Deactivating plugin: ' . $plugin_slug);
                    deactivate_plugins($plugin_slug, true);
                    
                    // Double check if deactivation was successful
                    if (is_plugin_active($plugin_slug)) {
                        throw new Exception(__('Failed to deactivate the plugin before update. Please try again.', 'wp-git-plugins'));
                    }
                    
                    // Give WordPress time to process the deactivation
                    sleep(1);
                }
            }
            
            // Update the repository
            error_log('WP Git Plugins - Updating repository ID: ' . $repo_id . ( $force ? ' (force update)' : '' ));
            $result = $this->repository->update_repository($repo_id, ['force' => $force]);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Get updated repository data
            $updated_repo = $this->repository->get_local_repository($repo_id);
            
            // Reactivate the plugin if it was active before
            $reactivation_success = true;
            $reactivation_error = '';
            
            if ($was_active && !empty($plugin_slug) && file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug)) {
                error_log('WP Git Plugins - Reactivating plugin: ' . $plugin_slug);
                $activated = activate_plugin($plugin_slug);
                
                if (is_wp_error($activated)) {
                    $reactivation_success = false;
                    $reactivation_error = $activated->get_error_message();
                    error_log('WP Git Plugins - Reactivation failed: ' . $reactivation_error);
                } else {
                    // Verify reactivation was successful
                    if (!is_plugin_active($plugin_slug)) {
                        $reactivation_success = false;
                        $reactivation_error = __('Plugin did not activate successfully after update.', 'wp-git-plugins');
                        error_log('WP Git Plugins - Reactivation verification failed for: ' . $plugin_slug);
                    }
                }
            }
            
            // Clear plugin update cache
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache();
            }
            
            // Prepare success response
            $response = [
                'message' => $force 
                    ? __('Plugin has been force-updated successfully.', 'wp-git-plugins')
                    : __('Plugin has been updated successfully.', 'wp-git-plugins'),
                'repository' => $updated_repo,
                'was_active' => $was_active,
                'reactivated' => $reactivation_success,
                'plugin_slug' => $plugin_slug,
                'update_info' => $result
            ];
            
            // Add reactivation warning if needed
            if (!$reactivation_success) {
                $response['reactivation_warning'] = sprintf(
                    __('Warning: The plugin could not be reactivated automatically: %s', 'wp-git-plugins'),
                    $reactivation_error
                );
                $response['message'] .= ' ' . __('However, there was an issue reactivating the plugin.', 'wp-git-plugins');
            }
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            // Log the error with more context
            error_log(sprintf(
                'WP Git Plugins - Update Error for repo ID %s, plugin %s: %s',
                $repo_id,
                $plugin_slug,
                $e->getMessage()
            ));
            
            // Try to reactivate the plugin if the update failed after deactivation
            if ($was_active && !empty($plugin_slug) && file_exists(WP_PLUGIN_DIR . '/' . $plugin_slug)) {
                error_log('WP Git Plugins - Attempting to reactivate plugin after error: ' . $plugin_slug);
                $reactivated = activate_plugin($plugin_slug);
                if (is_wp_error($reactivated)) {
                    error_log('WP Git Plugins - Reactivation after error failed: ' . $reactivated->get_error_message());
                }
            }
            
            // Return error response
            wp_send_json_error([
                'message' => sprintf(__('Error updating plugin: %s', 'wp-git-plugins'), $e->getMessage()),
                'plugin_slug' => $plugin_slug,
                'was_active' => $was_active
            ]);
        }
    }

    /**
     * Add action links to the plugins page.
     *
     * @since 1.0.0
     * @param array $links Existing action links.
     * @return array Modified action links.
     */
    /**
     * Helper method to delete plugin files
     *
     * @param string $plugin_slug The plugin slug to delete
     * @return bool True on success, false on failure
     */
    private function delete_plugin_files($plugin_slug) {
        if (empty($plugin_slug)) {
            return false;
        }
        
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        if (!file_exists($plugin_dir)) {
            return true; // Already deleted
        }
        
        // Use WordPress filesystem API to delete files
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
        
        return $wp_filesystem->delete($plugin_dir, true);
    }
    
    /**
     * Add action links to the plugin's admin page
     *
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wp-git-plugins-settings')),
            esc_html__('Settings', 'wp-git-plugins')
        );
        
        $dashboard_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wp-git-plugins')),
            esc_html__('Dashboard', 'wp-git-plugins')
        );
        
        // Add the links to the beginning of the array
        array_unshift($links, $settings_link, $dashboard_link);
        
        return $links;
    }
    
    /**
     * Add admin notices for various actions.
     *
     * @since 1.0.0
     */
    public function admin_notices() {
        // Show success/error messages
        if (isset($_GET['repo_added']) && $_GET['repo_added'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Repository added successfully.', 'wp-git-plugins') . 
                 '</p></div>';
        }
        
        if (isset($_GET['repo_removed']) && $_GET['repo_removed'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Repository removed successfully.', 'wp-git-plugins') . 
                 '</p></div>';
        }
        
        if (isset($_GET['branch_changed']) && $_GET['branch_changed'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Branch changed successfully.', 'wp-git-plugins') . 
                 '</p></div>';
        }
        
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html__('Repository deleted successfully.', 'wp-git-plugins') . 
                 '</p></div>';
        }
        
        // Show error messages
        if (isset($_GET['error']) && !empty($_GET['message'])) {
            $error_message = sanitize_text_field(wp_unslash($_GET['message']));
            echo '<div class="notice notice-error is-dismissible"><p>' . 
                 esc_html($error_message) . 
                 '</p></div>';
        }
    }
}