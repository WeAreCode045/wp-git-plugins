<?php
class WP_Git_Plugins_Admin {
    private $plugin_name;
    private $version;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
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

        // Add WordPress dashicons
        wp_enqueue_style('dashicons');
    }

    public function enqueue_scripts($hook) {
        if (strpos($hook, 'wp-git-plugins') === false) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name,
            WP_GIT_PLUGINS_URL . 'assets/js/scripts.js',
            ['jquery'],
            $this->version,
            true
        );

        wp_localize_script($this->plugin_name, 'wpGitPlugins', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'ajax_nonce' => wp_create_nonce('wp_git_plugins_ajax'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this repository?', 'wp-git-plugins'),
                'error' => __('An error occurred. Please try again.', 'wp-git-plugins')
            ]
        ]);
    }

    public function add_action_links($links) {
        $settings_link = [
            '<a href="' . admin_url('admin.php?page=wp-git-plugins-settings') . '">' . 
            __('Settings', 'wp-git-plugins') . '</a>',
        ];
        return array_merge($settings_link, $links);
    }
    
    /**
     * Register all the necessary hooks
     */
    public function register_hooks() {
        // Enqueue admin styles and scripts
        add_action('admin_enqueue_scripts', [$this, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // Add plugin action links
        add_filter('plugin_action_links_' . WP_GIT_PLUGINS_BASENAME, [$this, 'add_action_links']);
        
        // Add AJAX handlers
        add_action('wp_ajax_wp_git_plugins_install_plugin', [$this, 'ajax_install_plugin']);
        add_action('wp_ajax_wp_git_plugins_activate_plugin', [$this, 'ajax_activate_plugin']);
        add_action('wp_ajax_wp_git_plugins_deactivate_plugin', [$this, 'ajax_deactivate_plugin']);
        add_action('wp_ajax_wp_git_plugins_delete_plugin', [$this, 'ajax_delete_plugin']);
        add_action('wp_ajax_wp_git_plugins_check_updates', [$this, 'ajax_check_updates']);
        add_action('wp_ajax_wp_git_plugins_get_branches', [$this, 'ajax_get_branches']);
        add_action('wp_ajax_wp_git_plugins_change_branch', [$this, 'ajax_change_branch']);
    }
    
    public function add_admin_menus() {
        // Add main menu and dashboard page
        add_menu_page(
            __('Git Plugins', 'wp-git-plugins'),
            __('Git Plugins', 'wp-git-plugins'),
            'manage_options',
            'wp-git-plugins',
            array($this, 'display_dashboard_page'),
            'dashicons-git'
        );
        
        // Add Settings submenu
        add_submenu_page(
            'wp-git-plugins',
            __('Settings', 'wp-git-plugins'),
            __('Settings', 'wp-git-plugins'),
            'manage_options',
            'wp-git-plugins-settings',
            array($this, 'display_settings_page')
        );
        
        // Add Debug Log submenu
        add_submenu_page(
            'wp-git-plugins',
            __('Debug Log', 'wp-git-plugins'),
            __('Debug Log', 'wp-git-plugins'),
            'manage_options',
            'wp-git-plugins-debug',
            array($this, 'display_debug_page')
        );
    }
    
    public function display_dashboard_page() {
        include WP_GIT_PLUGINS_DIR . 'templates/pages/dashboard-page.php';
    }
    
    public function display_settings_page() {
        include WP_GIT_PLUGINS_DIR . 'templates/pages/settings-page.php';
    }
    
    public function display_debug_page() {
        $repository = new WP_Git_Plugins_Repository();
        $debug_log = $repository->get_debug_log();
        
        if (isset($_POST['clear_debug_log']) && check_admin_referer('clear_debug_log')) {
            $repository->clear_debug_log();
            $debug_log = [];
            add_settings_error(
                'wp_git_plugins_messages',
                'wp_git_plugins_debug_cleared',
                __('Debug log has been cleared.', 'wp-git-plugins'),
                'updated'
            );
        }
        
        include WP_GIT_PLUGINS_DIR . 'templates/pages/debug-page.php';
    }
    
    /**
     * AJAX handler for installing a plugin
     */
    public function ajax_install_plugin() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(__('You do not have sufficient permissions to install plugins.', 'wp-git-plugins'));
        }
        
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        
        if (empty($repo_url)) {
            wp_send_json_error(__('Repository URL is required.', 'wp-git-plugins'));
        }
        
        $repository = new WP_Git_Plugins_Repository();
        $result = $repository->install_plugin($repo_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Plugin installed successfully.', 'wp-git-plugins'));
    }
    
    /**
     * AJAX handler for activating a plugin
     */
    public function ajax_activate_plugin() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('activate_plugins')) {
            wp_send_json_error(__('You do not have sufficient permissions to activate plugins.', 'wp-git-plugins'));
        }
        
        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
        
        if (empty($plugin_slug)) {
            wp_send_json_error(__('Plugin slug is required.', 'wp-git-plugins'));
        }
        
        $result = activate_plugin($plugin_slug);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Plugin activated successfully.', 'wp-git-plugins'));
    }
    
    /**
     * AJAX handler for deactivating a plugin
     */
    public function ajax_deactivate_plugin() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('deactivate_plugins')) {
            wp_send_json_error(__('You do not have sufficient permissions to deactivate plugins.', 'wp-git-plugins'));
        }
        
        $plugin_slug = isset($_POST['plugin_slug']) ? sanitize_text_field($_POST['plugin_slug']) : '';
        
        if (empty($plugin_slug)) {
            wp_send_json_error(__('Plugin slug is required.', 'wp-git-plugins'));
        }
        
        deactivate_plugins($plugin_slug);
        
        wp_send_json_success(__('Plugin deactivated successfully.', 'wp-git-plugins'));
    }
    
    /**
     * AJAX handler for deleting a plugin
     */
    public function ajax_delete_plugin() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('delete_plugins')) {
            wp_send_json_error(__('You do not have sufficient permissions to delete plugins.', 'wp-git-plugins'));
        }
        
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        
        if (empty($repo_url)) {
            wp_send_json_error(__('Repository URL is required.', 'wp-git-plugins'));
        }
        
        $repository = new WP_Git_Plugins_Repository();
        $result = $repository->delete_repository($repo_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Plugin deleted successfully.', 'wp-git-plugins'));
    }
    
    /**
     * AJAX handler for checking plugin updates
     */
    public function ajax_check_updates() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('update_plugins')) {
            wp_send_json_error(__('You do not have sufficient permissions to update plugins.', 'wp-git-plugins'));
        }
        
        $repository = new WP_Git_Plugins_Repository();
        $updates = $repository->check_updates();
        
        if (is_wp_error($updates)) {
            wp_send_json_error($updates->get_error_message());
        }
        
        wp_send_json_success([
            'message' => __('Updates checked successfully.', 'wp-git-plugins'),
            'updates' => $updates
        ]);
    }
    
    /**
     * AJAX handler for getting repository branches
     */
    public function ajax_get_branches() {
        check_ajax_referer('wp_git_plugins_ajax', 'nonce');
        
        if (!current_user_can('install_plugins')) {
            wp_send_json_error(__('You do not have sufficient permissions to install plugins.', 'wp-git-plugins'));
        }
        
        $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
        $is_private = isset($_POST['is_private']) ? intval($_POST['is_private']) : 0;
        
        if (empty($repo_url)) {
            wp_send_json_error(__('Repository URL is required.', 'wp-git-plugins'));
        }
        
        $repository = new WP_Git_Plugins_Repository();
        $branches = $repository->get_repository_branches($repo_url, $is_private);
        
        if (is_wp_error($branches)) {
            wp_send_json_error($branches->get_error_message());
        }
        
        wp_send_json_success([
            'branches' => $branches
        ]);
    }
}
