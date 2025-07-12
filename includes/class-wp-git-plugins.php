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
        
        $this->repository = new WP_Git_Plugins_Repository();
        $this->settings = new WP_Git_Plugins_Settings($this->plugin_name, $this->version);
        
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
        $plugin_admin = new WP_Git_Plugins_Admin($this->get_plugin_name(), $this->get_version());
        
        // Enqueue scripts and styles
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Add admin menu
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
        
        // Schedule update checks
        if (!wp_next_scheduled('wp_git_plugins_check_updates')) {
            wp_schedule_event(time(), 'twicedaily', 'wp_git_plugins_check_updates');
        }
        $this->loader->add_action('wp_git_plugins_check_updates', $this->repository, 'check_for_updates');
        
        // Add plugin action links
        $this->loader->add_filter('plugin_action_links_' . WP_GIT_PLUGINS_BASENAME, $plugin_admin, 'add_action_links');
    }

    private function define_public_hooks() {
        // Public hooks can be added here
    }


    
    public function display_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include WP_GIT_PLUGINS_DIR . 'templates/pages/dashboard-page.php';
    }
    
    public function display_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include WP_GIT_PLUGINS_DIR . 'templates/pages/settings-page.php';
    }
    
    public function handle_ajax_requests() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'wp-git-plugins')], 403);
        }
        
        $action = $_POST['action'] ?? '';
        $repo_url = $_POST['repo_url'] ?? '';
        
        switch ($action) {
            case 'wp_git_plugins_install':
                $result = $this->repository->install_plugin($repo_url);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                } else {
                    wp_send_json_success(['message' => __('Plugin installed successfully', 'wp-git-plugins')]);
                }
                break;
                
            case 'wp_git_plugins_activate':
                $plugin_slug = sanitize_text_field($_POST['plugin_slug'] ?? '');
                $result = activate_plugin($plugin_slug);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                } else {
                    wp_send_json_success(['message' => __('Plugin activated successfully', 'wp-git-plugins')]);
                }
                break;
                
            case 'wp_git_plugins_deactivate':
                $plugin_slug = sanitize_text_field($_POST['plugin_slug'] ?? '');
                deactivate_plugins($plugin_slug);
                wp_send_json_success(['message' => __('Plugin deactivated successfully', 'wp-git-plugins')]);
                break;
                
            case 'wp_git_plugins_delete':
                $result = $this->repository->remove_repository($repo_url);
                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()]);
                } else {
                    wp_send_json_success(['message' => __('Repository removed successfully', 'wp-git-plugins')]);
                }
                break;
                
            case 'wp_git_plugins_check_updates':
                $updates = $this->repository->check_for_updates();
                wp_send_json_success(['updates' => $updates]);
                break;
                
            case 'wp_git_plugins_get_branches':
                if (!isset($_POST['repo_url']) || empty($_POST['repo_url'])) {
                    wp_send_json_error(['message' => __('Repository URL is required', 'wp-git-plugins')], 400);
                }
                
                $repo_url = esc_url_raw($_POST['repo_url']);
                $branches = $this->repository->get_repository_branches($repo_url);
                
                if (is_wp_error($branches)) {
                    wp_send_json_error([
                        'message' => $branches->get_error_message(),
                        'code' => $branches->get_error_code()
                    ], 400);
                }
                
                wp_send_json_success(['branches' => $branches]);
                break;
                
            default:
                wp_send_json_error(['message' => __('Invalid action', 'wp-git-plugins')], 400);
        }
    }
    
    public function handle_form_submissions() {
        if (!isset($_POST['wp_git_plugins_nonce']) || 
            !wp_verify_nonce($_POST['wp_git_plugins_nonce'], 'wp_git_plugins_add_repo')) {
            return;
        }
        
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wp-git-plugins'));
        }
        
        $repo_url = esc_url_raw($_POST['repo_url'] ?? '');
        $branch = sanitize_text_field($_POST['repo_branch'] ?? 'main');
        $is_private = isset($_POST['is_private']) && $_POST['is_private'] === '1';
        
        if (empty($repo_url)) {
            add_settings_error(
                'wp_git_plugins_messages',
                'empty_url',
                __('Repository URL is required', 'wp-git-plugins'),
                'error'
            );
            return;
        }
        
        $result = $this->repository->add_repository($repo_url, $is_private, $branch);
        
        if (is_wp_error($result)) {
            add_settings_error(
                'wp_git_plugins_messages',
                'add_repo_error',
                $result->get_error_message(),
                'error'
            );
            // Store the error in a transient to display after redirect
            set_transient('wp_git_plugins_notice', [
                'type' => 'error',
                'message' => $result->get_error_message()
            ], 30);
        } else {
            // Store the success message in a transient to display after redirect
            set_transient('wp_git_plugins_notice', [
                'type' => 'success',
                'message' => __('Repository added and plugin installed successfully', 'wp-git-plugins')
            ], 30);
        }
        
        // Redirect back to the dashboard
        wp_redirect(admin_url('admin.php?page=wp-git-plugins'));
        exit;
    }

    public function run() {
        $this->loader->run();
    }

    public function get_plugin_name() {
        return $this->plugin_name;
    }

    public function get_loader() {
        return $this->loader;
    }

    public function get_version() {
        return $this->version;
    }

    public static function activate() {
        // Activation code here
        // Create necessary database tables or options
        add_option('wp_git_plugins_options', [
            'github_access_token' => '',
            'check_updates_interval' => 'twicedaily'
        ]);
        
        add_option('wp_git_plugins_repositories', []);
    }

    public static function deactivate() {
        // Deactivation code here
        // Clean up scheduled events
        wp_clear_scheduled_hook('wp_git_plugins_check_updates');
    }
}
