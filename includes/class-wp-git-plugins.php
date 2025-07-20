<?php
class WP_Git_Plugins {
    protected $loader;
    protected $plugin_name;
    protected $version;
    protected $repository;
    protected $settings;

    public function __construct() {
        $this->plugin_name = 'wp-git-plugins';
        $this->version = WP_GIT_PLUGINS_VERSION;
        
        $this->load_dependencies();
        $this->set_locale();
        
        // Initialize settings first
        $this->settings = new WP_Git_Plugins_Settings($this->plugin_name, $this->version);
        
        // Pass settings to repository
        $this->repository = new WP_Git_Plugins_Repository($this->settings);
        
        // Initialize Local_Plugins to ensure AJAX handlers are registered
        WP_Git_Plugins_Local_Plugins::get_instance();
        
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    private function load_dependencies() {
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-loader.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-i18n.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-repository.php';
        
        $this->loader = new WP_Git_Plugins_Loader();
    }

    private function set_locale() {
        $plugin_i18n = new WP_Git_Plugins_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    private function define_admin_hooks() {
        // Use the singleton instance of WP_Git_Plugins_Admin
        $plugin_admin = WP_Git_Plugins_Admin::get_instance($this->get_plugin_name(), $this->get_version());
        
        // Enqueue scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Register admin menu
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_admin_menus');
        
        // Register settings
        $this->loader->add_action('admin_init', $this->settings, 'register_settings');
        
        // Handle form submissions
        $this->loader->add_action('admin_post_wp_git_plugins_add_repository', $this, 'handle_form_submissions');
        
        // Add plugin action links
        $this->loader->add_filter('plugin_action_links_' . WP_GIT_PLUGINS_BASENAME, $plugin_admin, 'add_action_links');
    }

    private function define_public_hooks() {
        // Public hooks can be added here
    }

    /**
     * Run the plugin
     */
    public function run() {
        $this->loader->run();
    }

  
    /**
     * Get the plugin name
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * Get the plugin version
     */
    public function get_version() {
        return $this->version;
    }
    
    /**
     * Handle form submissions
     *
     * @since 1.0.0
     */
    public function handle_form_submissions() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_git_plugins_add_repository')) {
            $notice_strings = WP_Git_Plugins_i18n::get_notice_strings();
            wp_die($notice_strings['security_check_failed']);
        }

        // Verify user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions', 'wp-git-plugins'));
        }

        // Process the form data
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        $branch = isset($_POST['repo_branch']) ? sanitize_text_field($_POST['repo_branch']) : 'main';

        if (empty($repo_url)) {
            wp_die(__('Repository URL is required', 'wp-git-plugins'));
        }

        try {
            // Add the repository using the repository class
            $result = $this->repository->add_repository($repo_url, $branch);

            // Redirect back with success message
            wp_redirect(add_query_arg('message', 'repository_added', wp_get_referer()));
            exit;
        } catch (Exception $e) {
            // Redirect back with error message
            wp_redirect(add_query_arg('error', urlencode($e->getMessage()), wp_get_referer()));
            exit;
        }
    }

    // ===========================================
    // UTILITY METHODS - Global helper functions
    // ===========================================

    /**
     * Verify AJAX nonce and permissions for AJAX requests.
     * This is a global utility that can be used by any class.
     *
     * @since 1.0.0
     * @param string $required_capability The required capability for this action
     * @throws Exception When verification fails
     */
    public static function verify_ajax_request($required_capability = 'manage_options') {
        // Check if it's an AJAX request
        if (!defined('DOING_AJAX') || !DOING_AJAX) {
            wp_send_json_error(['message' => __('Invalid request', 'wp-git-plugins')], 400);
        }

        // Accept both _ajax_nonce and nonce for compatibility
        $nonce = isset($_REQUEST['_ajax_nonce']) ? $_REQUEST['_ajax_nonce'] : (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '');
        if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_git_plugins_ajax')) {
            $notice_strings = WP_Git_Plugins_i18n::get_notice_strings();
            wp_send_json_error(['message' => $notice_strings['security_check_failed']], 403);
        }

        // Check user capabilities
        if (!current_user_can($required_capability)) {
            wp_send_json_error(['message' => __('You do not have sufficient permissions', 'wp-git-plugins')], 403);
        }
    }

    /**
     * Recursively remove a directory and all its contents.
     * This is a global utility that can be used by any class.
     * 
     * @since 1.0.0
     * @param string $dir Directory path to remove
     * @return bool True on success, false on failure
     */
    public static function rrmdir($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $objects = scandir($dir);
        if ($objects === false) {
            return false;
        }
        
        $success = true;
        foreach ($objects as $object) {
            if ($object == "." || $object == "..") {
                continue;
            }
            
            $path = $dir . "/" . $object;
            if (is_dir($path) && !is_link($path)) {
                $success = self::rrmdir($path) && $success;
            } else {
                $success = unlink($path) && $success;
            }
        }
        
        return rmdir($dir) && $success;
    }

    /**
     * Parse a GitHub repository URL to extract owner and repository name.
     * This is a global utility that can be used by any class.
     * 
     * @since 1.0.0
     * @param string $url GitHub repository URL
     * @return array|WP_Error Array with 'owner' and 'name' keys, or WP_Error on failure
     */
    public static function parse_github_url($url) {
        $pattern = '#^(?:https?://|git@)?(?:www\.)?github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#';
        
        if (preg_match($pattern, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name' => rtrim($matches[2], '.git')
            ];
        }
        
        return new WP_Error('invalid_url', __('Invalid GitHub repository URL', 'wp-git-plugins'));
    }

    /**
     * Get the GitHub API URL for a repository endpoint.
     * This is a global utility that can be used by any class.
     * 
     * @since 1.0.0
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $endpoint API endpoint (default: '')
     * @return string GitHub API URL
     */
    public static function get_github_api_url($owner, $repo, $endpoint = '') {
        $url = 'https://api.github.com/repos/' . $owner . '/' . $repo;
        if (!empty($endpoint)) {
            $url .= '/' . ltrim($endpoint, '/');
        }
        
        return $url;
    }

    /**
     * Get the download URL for a GitHub repository.
     * This is a global utility that can be used by any class.
     * 
     * @since 1.0.0
     * @param array $git_repo GitHub repository data
     * @param string $github_token Optional GitHub token for private repos
     * @return string Download URL
     */
    public static function get_download_url($git_repo, $github_token = '') {
        if (!empty($git_repo['is_private']) && !empty($github_token)) {
            return sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s?access_token=%s',
                $git_repo['gh_owner'],
                $git_repo['gh_name'],
                $git_repo['branch'],
                $github_token
            );
        }
        
        return sprintf(
            'https://github.com/%s/%s/archive/refs/heads/%s.zip',
            $git_repo['gh_owner'],
            $git_repo['gh_name'],
            $git_repo['branch']
        );
    }
}