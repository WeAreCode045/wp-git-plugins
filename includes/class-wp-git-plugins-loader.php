<?php
/**
 * Plugin Loader Class
 * 
 * Handles plugin activation, deactivation, and database table management.
 */
class WP_Git_Plugins_Loader {
    /**
     * Current database version
     */
    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION = 'wpgp_db_version';

    /**
     * Plugin activation hook
     */
    public static function activate() {
        self::create_tables();
        // Removed direct DB queries
    }

    /**
     * Create or update database tables
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Settings table
        $settings_table = $wpdb->prefix . 'wpgp_settings';
        $sql_settings = "CREATE TABLE $settings_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            setting_key varchar(191) NOT NULL,
            setting_value longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";

        // Repositories table
        $repos_table = $wpdb->prefix . 'wpgp_repos';
        $sql_repos = "CREATE TABLE $repos_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            git_repo_url varchar(255) NOT NULL,
            plugin_slug varchar(255) NOT NULL,
            gh_owner varchar(100) NOT NULL,
            gh_name varchar(100) NOT NULL,
            branch varchar(100) NOT NULL DEFAULT 'main',
            local_version varchar(20) DEFAULT '0.0.0',
            git_version varchar(20) DEFAULT '0.0.0',
            is_private tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY git_repo_url (git_repo_url),
            KEY plugin_slug (plugin_slug)
        ) $charset_collate;";

        // Execute table creation/update
        dbDelta($sql_settings);
        dbDelta($sql_repos);

        // Check if we need to upgrade from an older version
        // Removed upgrade logic to simplify the loader
    }

    /**
     * Check if database needs to be upgraded
     */
    private static function maybe_upgrade_database() {
        $current_version = get_option(self::DB_VERSION_OPTION, '0.1.0');
        
        if (version_compare($current_version, '1.0.0', '<')) {
            self::upgrade_to_1_0_0();
        }
    }

    /**
     * Upgrade database to version 1.0.0
     */
    private static function upgrade_to_1_0_0() {
        global $wpdb;
        $repos_table = $wpdb->prefix . 'wpgp_repos';
        
        // Check if we need to migrate from old schema
        // Removed migration logic to simplify the loader
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        // Cleanup tasks if needed
    }

    /**
     * Plugin uninstall hook
     */
    public static function uninstall() {
        // Remove database tables and options
        if (defined('WP_UNINSTALL_PLUGIN')) {
            global $wpdb;
            
            // Delete tables
            $tables = [
                $wpdb->prefix . 'wpgp_settings',
                $wpdb->prefix . 'wpgp_repos'
            ];
            
            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS $table");
            }
            
            // Delete options
            delete_option(self::DB_VERSION_OPTION);
        }
    }
    protected $actions;
    protected $filters;

    public function __construct() {
        $this->actions = [];
        $this->filters = [];
    }

    public function add_action($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->actions = $this->add($this->actions, $hook, $component, $callback, $priority, $accepted_args);
    }

    public function add_filter($hook, $component, $callback, $priority = 10, $accepted_args = 1) {
        $this->filters = $this->add($this->filters, $hook, $component, $callback, $priority, $accepted_args);
    }

    private function add($hooks, $hook, $component, $callback, $priority, $accepted_args) {
        $hooks[] = [
            'hook'          => $hook,
            'component'     => $component,
            'callback'      => $callback,
            'priority'      => $priority,
            'accepted_args' => $accepted_args
        ];

        return $hooks;
    }

    public function run() {
        foreach ($this->filters as $hook) {
            add_filter(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }

        foreach ($this->actions as $hook) {
            add_action(
                $hook['hook'],
                [$hook['component'], $hook['callback']],
                $hook['priority'],
                $hook['accepted_args']
            );
        }
    }
}
