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
        
        // Initialize GitHub API with token from settings
        WP_Git_Plugins_Github_API::get_instance($this->settings->get_github_token());
        
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
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-error-handler.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-db.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-settings.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-github-api.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-local-plugins.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-branch.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-repository.php';
        require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-admin.php';
        
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
        
        // Track plugin activation to update local versions
        $this->loader->add_action('activated_plugin', $this, 'on_plugin_activated');
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

    /**
     * Handle plugin activation to update local version in database
     *
     * @since 1.0.0
     * @param string $plugin Plugin basename (plugin-folder/plugin-file.php)
     */
    public function on_plugin_activated($plugin) {
        // Don't track activation of our own plugin
        if ($plugin === WP_GIT_PLUGINS_BASENAME) {
            return;
        }
        
        error_log("WP Git Plugins - Plugin activated: {$plugin}");
        
        // Check if this plugin is managed by our system
        $db = WP_Git_Plugins_DB::get_instance();
        $repo = $db->get_repo_by_slug($plugin);
        
        if (!$repo) {
            // Plugin is not managed by our system
            return;
        }
        
        error_log("WP Git Plugins - Activated plugin is managed by our system: {$plugin}");
        
        // Get the plugin data to extract version
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin;
        
        if (!file_exists($plugin_path)) {
            error_log("WP Git Plugins - Plugin file not found: {$plugin_path}");
            return;
        }
        
        $plugin_data = get_plugin_data($plugin_path, false, false);
        $current_version = $plugin_data['Version'] ?? '';
        
        if (empty($current_version)) {
            error_log("WP Git Plugins - Could not determine version for plugin: {$plugin}");
            return;
        }
        
        error_log("WP Git Plugins - Found version {$current_version} for plugin {$plugin}");
        
        // Update the local_version in the database
        global $wpdb;
        $table_repos = $wpdb->prefix . 'wpgp_repos';
        
        $update_result = $wpdb->update(
            $table_repos,
            ['local_version' => $current_version],
            ['plugin_slug' => $plugin],
            ['%s'],
            ['%s']
        );
        
        if ($update_result === false) {
            error_log("WP Git Plugins - Failed to update local_version for {$plugin}: " . $wpdb->last_error);
        } else {
            error_log("WP Git Plugins - Successfully updated local_version to {$current_version} for {$plugin}");
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
     * Log an error message.
     * This is a global utility that can be used by any class.
     *
     * @since 1.0.0
     * @param string $error_message The error message to log
     * @param string $errfile The file where the error occurred
     * @param int $errline The line number where the error occurred
     * @param string $backtrace Optional backtrace information
     */
    public static function log_error($error_message, $errfile = '', $errline = 0, $backtrace = '') {
        $error_log = get_option('wp_git_plugins_error_log', array());
        if (count($error_log) >= 500) {
            $error_log = array_slice($error_log, -499, 499);
        }
        $error_log[] = array(
            'time' => current_time('timestamp'),
            'message' => $error_message,
            'file' => $errfile,
            'line' => $errline,
            'backtrace' => $backtrace,
        );
        update_option('wp_git_plugins_error_log', $error_log);
    }
}