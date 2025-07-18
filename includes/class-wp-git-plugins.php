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
        $this->loader->add_action('admin_post_wp_git_plugins_add_repo', $this, 'handle_form_submissions');
        
        // AJAX handlers
        $this->loader->add_action('wp_ajax_wp_git_plugins_install', $this, 'handle_ajax_requests');
        $this->loader->add_action('wp_ajax_wp_git_plugins_activate', $this, 'handle_ajax_requests');
        $this->loader->add_action('wp_ajax_wp_git_plugins_deactivate', $this, 'handle_ajax_requests');
        $this->loader->add_action('wp_ajax_wp_git_plugins_delete', $this, 'handle_ajax_requests');
        $this->loader->add_action('wp_ajax_wp_git_plugins_check_updates', $this, 'handle_ajax_requests');
        $this->loader->add_action('wp_ajax_wp_git_plugins_get_branches', $this, 'handle_ajax_requests');
        
      
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
     * Handle AJAX requests
     *
     * @since 1.0.0
     */
    /**
     * Handle form submissions
     *
     * @since 1.0.0
     */
    public function handle_form_submissions() {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'wp_git_plugins_add_repo')) {
            wp_die(__('Security check failed', 'wp-git-plugins'));
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

    public function handle_ajax_requests() {
        // Verify nonce and permissions
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wp_git_plugins_ajax')) {
            wp_send_json_error(['message' => __('Invalid nonce', 'wp-git-plugins')], 403);
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'wp-git-plugins')], 403);
        }
        
        $action = isset($_POST['action']) ? $_POST['action'] : '';
        $data = isset($_POST['data']) ? $_POST['data'] : [];
        
        try {
            switch ($action) {
                case 'wp_git_plugins_install':
                    // Handle installation
                    $result = $this->repository->install_plugin($data);
                    wp_send_json_success($result);
                    break;
                    
                case 'wp_git_plugins_activate':
                    // Handle activation
                    $result = $this->repository->activate_plugin($data);
                    wp_send_json_success($result);
                    break;
                    
                case 'wp_git_plugins_deactivate':
                    // Handle deactivation
                    $result = $this->repository->deactivate_plugin($data);
                    wp_send_json_success($result);
                    break;
                    
                case 'wp_git_plugins_delete':
                    // Handle deletion
                    $result = $this->repository->delete_plugin($data);
                    wp_send_json_success($result);
                    break;
                    
                case 'wp_git_plugins_check_updates':
                    // Handle update check
                    $result = $this->repository->check_updates($data);
                    wp_send_json_success($result);
                    break;
                    
                case 'wp_git_plugins_get_branches':
                    // Handle branch listing
                    $result = $this->repository->get_branches($data);
                    wp_send_json_success($result);
                    break;
                    
                default:
                    wp_send_json_error(['message' => __('Invalid action', 'wp-git-plugins')], 400);
            }
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()], 400);
        }
    }
}

// Add to your main plugin file or AJAX handler

// Register AJAX actions
add_action('wp_ajax_wp_git_plugins_clear_log', 'wp_git_plugins_clear_log_callback');
add_action('wp_ajax_wp_git_plugins_check_rate_limit', 'wp_git_plugins_check_rate_limit_callback');

// Clear log/history
function wp_git_plugins_clear_log_callback() {
    check_ajax_referer('wp_git_plugins_debug_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'wp-git-plugins'));
        return;
    }
    
    $log_type = isset($_POST['log_type']) ? sanitize_text_field($_POST['log_type']) : '';
    
    switch ($log_type) {
        case 'history':
            update_option('wp_git_plugins_history', array());
            break;
        case 'error-log':
            update_option('wp_git_plugins_error_log', array());
            break;
        case 'console-log':
            update_option('wp_git_plugins_console_log', array());
            break;
        default:
            wp_send_json_error(__('Invalid log type.', 'wp-git-plugins'));
            return;
    }
    
    wp_send_json_success();
}

// Check GitHub rate limit
function wp_git_plugins_check_rate_limit_callback() {
    check_ajax_referer('wp_git_plugins_debug_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('You do not have permission to perform this action.', 'wp-git-plugins'));
        return;
    }
    
    $settings = get_option('wp_git_plugins_settings', array());
    $github_token = isset($settings['github_token']) ? $settings['github_token'] : '';
    
    if (empty($github_token)) {
        wp_send_json_error(__('GitHub token not configured.', 'wp-git-plugins'));
        return;
    }
    
    $response = wp_remote_get('https://api.github.com/rate_limit', array(
        'headers' => array(
            'Authorization' => 'token ' . $github_token,
            'Accept' => 'application/vnd.github.v3+json',
        ),
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        return;
    }
    
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['resources']['core'])) {
        wp_send_json_success(array(
            'limit' => $body['resources']['core']['limit'],
            'remaining' => $body['resources']['core']['remaining'],
            'reset' => $body['resources']['core']['reset'],
        ));
    } else {
        wp_send_json_error(__('Could not retrieve rate limit information.', 'wp-git-plugins'));
    }
}