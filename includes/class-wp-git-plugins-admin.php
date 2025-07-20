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
    
    /**
     * The repository instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Repository    $repository    The repository instance.
     */
    private $repository;

    /**
     * The local plugins instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Local_Plugins    $local_plugins    The local plugins instance.
     */
    private $local_plugins;

    /**
     * The branch instance.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Branch    $branch    The branch instance.
     */
    private $branch;

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
        $this->local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();
        $this->branch = WP_Git_Plugins_Branch::get_instance($this->settings);
        $this->branch->set_repository($this->repository);
        $this->register_ajax_handlers();
    }

    /**
     * Register all AJAX handlers.
     *
     * @since 1.0.0
     */
    private function register_ajax_handlers() {
        // Only register remaining admin-specific AJAX actions
        add_action('wp_ajax_wp_git_plugins_clear_log', array('WP_Git_Plugins_Debug', 'ajax_clear_log'));
        add_action('wp_ajax_wp_git_plugins_check_rate_limit', array('WP_Git_Plugins_Debug', 'ajax_check_rate_limit'));
        
        // Public AJAX actions (if needed)
        add_action('wp_ajax_wp_git_plugins_get_plugins', array($this, 'ajax_get_plugins'));
        add_action('wp_ajax_wp_git_plugins_get_plugin', array($this, 'ajax_get_plugin'));
        add_action('wp_ajax_wp_git_plugins_get_plugin_info', array($this, 'ajax_get_plugin_info'));
    }

    public function add_admin_menus() {
        $menu_strings = WP_Git_Plugins_i18n::get_menu_strings();
        
        // Add main menu and dashboard page
        add_menu_page(
            page_title: $menu_strings['git_plugins'],
            menu_title: $menu_strings['git_plugins'],
            capability: 'manage_options',
            menu_slug: 'wp-git-plugins',
            callback:  [$this, 'display_dashboard_page'],
            icon_url: 'dashicons-git'
        );
        
        // Add Settings submenu
        add_submenu_page(
            parent_slug: 'wp-git-plugins',
            page_title: $menu_strings['settings'],
            menu_title: $menu_strings['settings'],
            capability: 'manage_options',
            menu_slug: 'wp-git-plugins-settings',
            callback: [$this, 'display_settings_page']
        );
        
        // Add Debug Log submenu
        add_submenu_page(
            parent_slug: 'wp-git-plugins',
            page_title: $menu_strings['debug_log'],
            menu_title: $menu_strings['debug_log'],
            capability: 'manage_options',
            menu_slug: 'wp-git-plugins-debug',
            callback: [$this, 'display_debug_page']
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
        
        // Enqueue modules script on settings page
        if ($hook === 'wp-git-plugins_page_wp-git-plugins-settings') {
            wp_enqueue_script(
                $this->plugin_name . '-modules',
                WP_GIT_PLUGINS_URL . 'assets/js/modules.js',
                array('jquery'),
                $this->version,
                true
            );
        }
        
        // Localize script with AJAX URL and nonce
        // Use centralized localization class
        WP_Git_Plugins_i18n::localize_script(
            $this->plugin_name . '-admin'
        );
        
        // Also localize the repository list script if it's enqueued
        if (wp_script_is($this->plugin_name . '-repository-list', 'enqueued')) {
            WP_Git_Plugins_i18n::localize_script(
                $this->plugin_name . '-repository-list'
            );
        }
        
        // Also localize the modules script if it's enqueued
        if (wp_script_is($this->plugin_name . '-modules', 'enqueued')) {
            WP_Git_Plugins_i18n::localize_script(
                $this->plugin_name . '-modules'
            );
        }
    }

    /**
     * Add action links to the plugin's admin page
     *
     * @param array $links Existing action links
     * @return array Modified action links
     */
    public function add_action_links($links) {
        $menu_strings = WP_Git_Plugins_i18n::get_menu_strings();
        
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wp-git-plugins-settings')),
            esc_html($menu_strings['settings'])
        );
        
        $dashboard_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('admin.php?page=wp-git-plugins')),
            esc_html($menu_strings['dashboard'])
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
        $notice_strings = WP_Git_Plugins_i18n::get_notice_strings();
        
        // Show success/error messages
        if (isset($_GET['repo_added']) && $_GET['repo_added'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html($notice_strings['repository_added']) . 
                 '</p></div>';
        }
        
        if (isset($_GET['repo_removed']) && $_GET['repo_removed'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html($notice_strings['repository_removed']) . 
                 '</p></div>';
        }
        
        if (isset($_GET['branch_changed']) && $_GET['branch_changed'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html($notice_strings['branch_changed']) . 
                 '</p></div>';
        }
        
        if (isset($_GET['deleted']) && $_GET['deleted'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                 esc_html($notice_strings['repository_deleted']) . 
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