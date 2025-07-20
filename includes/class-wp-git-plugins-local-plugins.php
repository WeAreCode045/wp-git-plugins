<?php
/**
 * Local Plugins class for WP Git Plugins
 *
 * Handles all plugin-specific functionality including activation, deactivation, 
 * installation, and plugin file management.
 *
 * @package    WP_Git_Plugins
 * @subpackage Local_Plugins
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @link       https://code045.nl/plugins/wp-git-plugins
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Local_Plugins {
    
    /**
     * The single instance of the class.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Local_Plugins    $instance    The single instance of the class.
     */
    private static $instance = null;
    
    /**
     * GitHub API instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Github_API    $github_api    The GitHub API instance.
     */
    private $github_api;

    /**
     * Get the singleton instance of the class.
     *
     * @since 1.0.0
     * @return WP_Git_Plugins_Local_Plugins The singleton instance.
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the class.
     *
     * @since 1.0.0
     */
    private function __construct() {
        // Initialize GitHub API instance
        $this->github_api = WP_Git_Plugins_Github_API::get_instance();
        
        // Register AJAX handlers for plugin operations
        add_action('wp_ajax_wp_git_plugins_activate_plugin', array($this, 'ajax_activate_plugin'));
        add_action('wp_ajax_wp_git_plugins_deactivate_plugin', array($this, 'ajax_deactivate_plugin'));
        add_action('wp_ajax_wp_git_plugins_reinstall_plugin', array($this, 'ajax_reinstall_plugin'));
    }

    /**
     * AJAX handler for activating a plugin.
     *
     * @since 1.0.0
     */
    public function ajax_activate_plugin() {
        try {
            WP_Git_Plugins::verify_ajax_request('activate_plugins');
            
            $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
            
            if (empty($plugin_slug)) {
                throw new Exception(__('Plugin slug is required.', 'wp-git-plugins'));
            }
            
            $result = $this->activate_plugin($plugin_slug);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
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
            WP_Git_Plugins::verify_ajax_request('deactivate_plugins');
            
            $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
            
            if (empty($plugin_slug)) {
                throw new Exception(__('Plugin slug is required.', 'wp-git-plugins'));
            }
            
            $result = $this->deactivate_plugin($plugin_slug);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            wp_send_json_success(['message' => __('Plugin deactivated successfully.', 'wp-git-plugins')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error deactivating plugin: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }

    /**
     * AJAX handler for reinstalling a plugin.
     *
     * @since 1.0.0
     */
    public function ajax_reinstall_plugin() {
        try {
            WP_Git_Plugins::verify_ajax_request('install_plugins');
            
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            
            // Get repository data from the database
            $db = WP_Git_Plugins_DB::get_instance();
            $repo = $db->get_repo($repo_id);
            
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            
            // Convert to array if it's an object
            if (is_object($repo)) {
                $repo = (array) $repo;
            }
            
            // Get GitHub token from settings
            $settings = new WP_Git_Plugins_Settings('wp-git-plugins', WP_GIT_PLUGINS_VERSION);
            $github_token = $settings->get_github_token();
            
            // Use the install_plugin method to reinstall
            $result = $this->install_plugin($repo, $github_token);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            wp_send_json_success(['message' => __('Plugin reinstalled successfully.', 'wp-git-plugins')]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error reinstalling plugin: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }

    /**
     * Activate a plugin.
     *
     * @since 1.0.0
     * @param string $plugin_slug The plugin slug to activate
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function activate_plugin($plugin_slug) {
        if (empty($plugin_slug)) {
            return new WP_Error('invalid_plugin', __('Plugin slug is required.', 'wp-git-plugins'));
        }

        // Check if plugin file exists
        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
        if (!file_exists($plugin_file)) {
            return new WP_Error('plugin_not_found', __('Plugin file not found.', 'wp-git-plugins'));
        }

        // Check if plugin is already active
        if (is_plugin_active($plugin_slug)) {
            return new WP_Error('already_active', __('Plugin is already active.', 'wp-git-plugins'));
        }

        $result = activate_plugin($plugin_slug);
        
        if (is_wp_error($result)) {
            return $result;
        }

        // Verify activation was successful
        if (!is_plugin_active($plugin_slug)) {
            return new WP_Error('activation_failed', __('Plugin activation verification failed.', 'wp-git-plugins'));
        }

        return true;
    }

    /**
     * Deactivate a plugin.
     *
     * @since 1.0.0
     * @param string $plugin_slug The plugin slug to deactivate
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function deactivate_plugin($plugin_slug) {
        if (empty($plugin_slug)) {
            return new WP_Error('invalid_plugin', __('Plugin slug is required.', 'wp-git-plugins'));
        }

        // Check if plugin is active
        if (!is_plugin_active($plugin_slug)) {
            return new WP_Error('not_active', __('Plugin is not active.', 'wp-git-plugins'));
        }

        deactivate_plugins($plugin_slug);

        // Verify deactivation was successful
        if (is_plugin_active($plugin_slug)) {
            return new WP_Error('deactivation_failed', __('Plugin deactivation verification failed.', 'wp-git-plugins'));
        }

        return true;
    }

    /**
     * Check if a plugin is installed.
     *
     * @since 1.0.0
     * @param string $plugin_slug The plugin slug to check
     * @return bool True if installed, false otherwise
     */
    public function is_plugin_installed($plugin_slug) {
        if (empty($plugin_slug)) {
            return false;
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
        return file_exists($plugin_file);
    }

    /**
     * Check if a plugin is active.
     *
     * @since 1.0.0
     * @param string $plugin_slug The plugin slug to check
     * @return bool True if active, false otherwise
     */
    public function is_plugin_active($plugin_slug) {
        if (empty($plugin_slug)) {
            return false;
        }

        return is_plugin_active($plugin_slug);
    }

    /**
     * Get plugin data.
     *
     * @since 1.0.0
     * @param string $plugin_slug The plugin slug
     * @return array|false Plugin data on success, false on failure
     */
    public function get_plugin_data($plugin_slug) {
        if (empty($plugin_slug) || !$this->is_plugin_installed($plugin_slug)) {
            return false;
        }

        $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
        return get_plugin_data($plugin_file, false, true);
    }

    /**
     * Delete plugin files from the filesystem.
     *
     * @since 1.0.0
     * @param string $plugin_slug The plugin slug to delete
     * @return bool True on success, false on failure
     */
    public function delete_plugin_files($plugin_slug) {
        if (empty($plugin_slug)) {
            return false;
        }
        
        // Make sure plugin is deactivated first
        if ($this->is_plugin_active($plugin_slug)) {
            $this->deactivate_plugin($plugin_slug);
        }
        
        // Extract just the plugin directory name from the plugin slug
        // Plugin slug could be 'plugin-dir/plugin-file.php' or just 'plugin-dir'
        $plugin_dir_name = '';
        if (strpos($plugin_slug, '/') !== false) {
            // If plugin slug contains '/', take the first part (directory name)
            $plugin_dir_name = dirname($plugin_slug);
        } else {
            // If no '/', assume it's already the directory name
            $plugin_dir_name = $plugin_slug;
        }
        
        $plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_dir_name;
        
        // Safety check: make sure we're not trying to delete the entire plugins directory
        if ($plugin_dir === WP_PLUGIN_DIR || empty($plugin_dir_name)) {
            error_log('WP Git Plugins - CRITICAL: Attempted to delete plugins directory or invalid path: ' . $plugin_dir);
            return false;
        }
        
        if (!file_exists($plugin_dir)) {
            return true; // Already deleted
        }
        
        // Use WordPress filesystem API to delete files
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            WP_Filesystem();
        }
        
        $result = $wp_filesystem->delete($plugin_dir, true);
        
        if ($result) {
            error_log('WP Git Plugins - Successfully deleted plugin directory: ' . $plugin_dir);
        } else {
            error_log('WP Git Plugins - Failed to delete plugin directory: ' . $plugin_dir);
        }
        
        return $result;
    }

    /**
     * Find plugin slug by repository name.
     *
     * @since 1.0.0
     * @param string $repo_name The repository name
     * @return string|false Plugin slug on success, false on failure
     */
    public function find_plugin_slug_by_repo_name($repo_name) {
        if (empty($repo_name)) {
            return false;
        }

        // Get all plugins
        $all_plugins = get_plugins();
        
        // First try the default pattern: repo-name/repo-name.php
        $default_slug = $repo_name . '/' . $repo_name . '.php';
        if (isset($all_plugins[$default_slug])) {
            return $default_slug;
        }

        // If not found, search through all plugins to find a matching directory
        $possible_dirs = [
            $repo_name, // Exact match
            $repo_name . '-main', // GitHub default branch download
            str_replace('_', '-', $repo_name), // Handle underscores
            str_replace('-', '_', $repo_name) // Handle dashes
        ];
        
        foreach ($all_plugins as $plugin_slug => $plugin_data) {
            $plugin_dir = dirname($plugin_slug);
            $plugin_dir_lower = strtolower($plugin_dir);
            $repo_name_lower = strtolower($repo_name);
            
            // Check for exact match or variations
            if (in_array($plugin_dir_lower, array_map('strtolower', $possible_dirs)) || 
                $plugin_dir_lower === $repo_name_lower . '-main' ||
                strpos($plugin_dir_lower, $repo_name_lower) === 0) { // Starts with repo name
                return $plugin_slug;
            }
        }

        return false;
    }

    /**
     * Install a plugin from a GitHub repository
     * 
     * @param array $git_repo GitHub repository data
     * @param string $github_token GitHub token for private repositories
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function install_plugin($git_repo, $github_token = '') {
        // Ensure we have required fields
        if (empty($git_repo['gh_owner']) || empty($git_repo['gh_name'])) {
            return new WP_Error('invalid_repo_data', __('Invalid repository data. Missing owner or repository name.', 'wp-git-plugins'));
        }
        
        // Update GitHub API instance with token if provided
        if (!empty($github_token)) {
            $this->github_api->set_github_token($github_token);
        }
        
        $error_handler = WP_Git_Plugins_Error_Handler::instance();
        $error_handler->log_error(sprintf('Starting plugin installation: %s/%s', 
            $git_repo['gh_owner'],
            $git_repo['gh_name']
        ));

        // Use the is_private flag from repo data if available, otherwise determine it
        $is_private = isset($git_repo['is_private']) ? (bool)$git_repo['is_private'] : 
                     (!empty($github_token) && 
                      !empty($git_repo['git_repo_url']) && 
                      strpos($git_repo['git_repo_url'], 'github.com') !== false);

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        
        WP_Filesystem();
        
        // Ensure we have the repository URL
        if (empty($git_repo['git_repo_url'])) {
            $git_repo['git_repo_url'] = sprintf('https://github.com/%s/%s', 
                $git_repo['gh_owner'], 
                $git_repo['gh_name']
            );
        }
        
        // Get branch from repo data or default to 'main'
        $branch = !empty($git_repo['branch']) ? $git_repo['branch'] : 'main';
        
        // Determine target directory using plugin slug if available, otherwise use repo name
        $plugin_slug = !empty($git_repo['plugin_slug']) ? $git_repo['plugin_slug'] : $git_repo['gh_name'];
        $target_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        // Get clone URL using GitHub API class
        $clone_url = $this->github_api->get_clone_url(
            $git_repo['gh_owner'], 
            $git_repo['gh_name'], 
            $is_private
        );
                
        $error_handler->log_error(sprintf('Preparing to clone repository: %s/%s (branch: %s) to %s', 
            $git_repo['gh_owner'],
            $git_repo['gh_name'],
            $branch,
            $target_dir
        ));
        
        // Check if Git is available
        if (!function_exists('shell_exec')) {
            $error = new WP_Error('shell_exec_disabled', __('The shell_exec() function is disabled on this server.', 'wp-git-plugins'));
            $error_handler->log_error('shell_exec is disabled: ' . $error->get_error_message());
            return $error;
        }
        
        $git_path = shell_exec('which git');
        if (!$git_path) {
            $error = new WP_Error('git_not_available', __('Git is not available on this server. Please install Git to use this feature.', 'wp-git-plugins'));
            $error_handler->log_error('Git not found: ' . $error->get_error_message());
            return $error;
        }
        
        // Create plugins directory if it doesn't exist
        if (!file_exists(WP_PLUGIN_DIR)) {
            wp_mkdir_p(WP_PLUGIN_DIR);
        }
        
        // Check if directory exists and is a git repository
        if (file_exists($target_dir)) {
            $error_handler->log_error('Target directory exists: ' . $target_dir);
            
            if (file_exists($target_dir . '/.git')) {
                $error_handler->log_error(sprintf('Updating existing Git repository at %s (branch: %s)', 
                    $target_dir,
                    $git_repo['branch']
                ));
                
                $command = sprintf(
                    'cd %s && git fetch origin %s && git checkout %s && git pull origin %s 2>&1',
                    escapeshellarg($target_dir),
                    escapeshellarg($branch),
                    escapeshellarg($branch),
                    escapeshellarg($branch)
                );
                // No need to log git commands for security reasons
                
                $output = shell_exec($command);
                if (!empty($output)) {
                    $error_handler->log_error('Git command output: ' . $output);
                }
            }
            
            // If we get here, the update was successful
            return true;
        } else {
            // Directory doesn't exist, clone the repository
            $command = sprintf(
                'git clone --branch %s --single-branch --depth 1 %s %s 2>&1',
                escapeshellarg($branch),
                escapeshellarg($clone_url),
                escapeshellarg($target_dir)
            );
            
            $output = shell_exec($command);
            if (!empty($output)) {
                $error_handler->log_error('Git clone output: ' . $output);
            }
            
            if (!is_dir($target_dir . '/.git')) {
                return new WP_Error('git_clone_failed', __('Failed to clone repository.', 'wp-git-plugins'));
            }
            
            return true;
        }
    }
}
