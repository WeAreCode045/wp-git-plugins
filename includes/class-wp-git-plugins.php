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
}