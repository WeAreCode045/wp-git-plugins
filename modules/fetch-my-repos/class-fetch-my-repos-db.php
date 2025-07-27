<?php
/**
 * DB handler for Fetch My Repos module
 */
if (!defined('ABSPATH')) exit;

class WPGP_Fetch_My_Repos_DB {
    private static $instance = null;
    private $table_name;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpgp_fetched_repos';
        $this->create_table();
    }

    public function create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            plugin_name VARCHAR(255) NOT NULL,
            repo_url VARCHAR(255) NOT NULL,
            owner VARCHAR(100) NOT NULL,
            branches TEXT,
            first_fetch DATETIME DEFAULT NULL,
            most_recent_fetch DATETIME DEFAULT NULL,
            is_installed TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY repo_url (repo_url)
        ) $charset_collate;";
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function upsert_repo($data) {
        global $wpdb;
        $defaults = [
            'plugin_name' => '',
            'repo_url' => '',
            'owner' => '',
            'branches' => '',
            'first_fetch' => current_time('mysql'),
            'most_recent_fetch' => current_time('mysql'),
            'is_installed' => 0
        ];
        $data = wp_parse_args($data, $defaults);
        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE repo_url = %s", $data['repo_url']));
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                [
                    'plugin_name' => $data['plugin_name'],
                    'owner' => $data['owner'],
                    'branches' => $data['branches'],
                    'most_recent_fetch' => $data['most_recent_fetch'],
                    'is_installed' => $data['is_installed']
                ],
                ['id' => $existing]
            );
        } else {
            $wpdb->insert($this->table_name, $data);
        }
    }

    public function get_table_name() {
        return $this->table_name;
    }
}
