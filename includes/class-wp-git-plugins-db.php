<?php
/**
 * Handles all database operations for WP Git Plugins
 */
class WP_Git_Plugins_DB {
    private static $instance = null;
    private $table_settings;
    private $table_repos;
    private $charset_collate;
    
    /**
     * Error handler instance
     *
     * @var WP_Git_Plugins_Error_Handler
     */
    private $error_handler;
    
    // Keep in sync with WP_Git_Plugins_Loader
    const DB_VERSION = '1.1.0';
    const DB_VERSION_OPTION = 'wpgp_db_version';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        global $wpdb;
        $this->table_settings = $wpdb->prefix . 'wpgp_settings';
        $this->table_repos = $wpdb->prefix . 'wpgp_repos';
        $this->charset_collate = $wpdb->get_charset_collate();
        $this->error_handler = WP_Git_Plugins_Error_Handler::instance();
    }

    /**
     * Check if a table exists in the database
     *
     * @param string $table_name Table name to check
     * @return bool True if table exists, false otherwise
     */
    private function table_exists($table_name) {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Create the database tables if they don't exist
     * This method is idempotent and can be called multiple times safely
     *
     * @return bool True if tables were created or already exist, false on error
     */
    public function create_tables() {
        global $wpdb;
        
        // Check if tables already exist
        $tables_exist = $this->table_exists($this->table_settings) && $this->table_exists($this->table_repos);
        
        // If tables exist and version matches, no need to proceed
        if ($tables_exist && get_option(self::DB_VERSION_OPTION) === self::DB_VERSION) {
            return true;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Create settings table if it doesn't exist
        if (!$this->table_exists($this->table_settings)) {
            $sql = "CREATE TABLE {$this->table_settings} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                setting_key varchar(191) NOT NULL DEFAULT '',
                setting_value longtext NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY setting_key (setting_key)
            ) {$this->charset_collate};";
            
            dbDelta($sql);
            
            if (!empty($wpdb->last_error)) {
                $this->error_handler->log_error('Database error creating settings table: ' . $wpdb->last_error);
                return false;
            }
            
            // Set default options for new installations
            $this->set_default_options();
        }
        
        // Create repositories table if it doesn't exist
        if (!$this->table_exists($this->table_repos)) {
            $sql = "CREATE TABLE {$this->table_repos} (
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
            ) {$this->charset_collate};";
            
            dbDelta($sql);
            
            if (!empty($wpdb->last_error)) {
                $this->error_handler->log_error('Database error creating repos table: ' . $wpdb->last_error);
                return false;
            }
        }
        
        // Update the database version
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        return true;
    }
    
    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'github_token' => '',
            'auto_update' => false,
            'delete_on_uninstall' => false
        );
        
        foreach ($defaults as $key => $value) {
            $this->update_setting($key, $value);
        }
    }
    
    /**
     * Get a setting value
     * 
     * @param string $name Setting name
     * @param mixed $default Default value if not found
     * @return mixed Setting value or default
     */
    public function get_setting($name, $default = '') {
        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM {$this->table_settings} WHERE setting_key = %s",
            $name
        ));
        return $value !== null ? maybe_unserialize($value) : $default;
    }
    
    /**
     * Get all settings as an associative array
     * 
     * @return array Associative array of settings
     */
    public function get_settings() {
        global $wpdb;
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM {$this->table_settings}");
        $settings = [];
        if ($results) {
            foreach ($results as $row) {
                $settings[$row->setting_key] = maybe_unserialize($row->setting_value);
            }
        }
        return $settings;
    }

    /**
     * Save a setting
     * 
     * @param string $name Setting name
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function update_setting($name, $value) {
        global $wpdb;
        
        $data = [
            'setting_key' => $name,
            'setting_value' => maybe_serialize($value),
            'updated_at' => current_time('mysql')
        ];
        
        $format = ['%s', '%s', '%s'];
        
        // Check if setting exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->table_settings} WHERE setting_key = %s",
            $name
        ));
        
        if ($exists) {
            $result = $wpdb->update(
                $this->table_settings,
                ['setting_value' => maybe_serialize($value), 'updated_at' => current_time('mysql')],
                ['setting_key' => $name],
                ['%s', '%s'],
                ['%s']
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $result = $wpdb->insert(
                $this->table_settings,
                $data,
                $format
            );
        }
        
        return $result !== false;
    }
    
    /**
     * Get all repositories
     * 
     * @param array $args Query arguments
     * @return array Array of repository objects
     */
    public function get_repos($args = []) {
        global $wpdb;
        
        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 0,
            'offset' => 0,
            'is_private' => null
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = [];
        $values = [];
        
        if ($args['is_private'] !== null) {
            $where[] = 'is_private = %d';
            $values[] = $args['is_private'] ? 1 : 0;
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_sql = sprintf('ORDER BY %s %s', 
            esc_sql($args['orderby']), 
            strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC'
        );
        
        $limit_sql = '';
        if ($args['limit'] > 0) {
            $limit_sql = $wpdb->prepare('LIMIT %d, %d', 
                absint($args['offset']), 
                absint($args['limit'])
            );
        }
        
        $query = "SELECT * FROM {$this->table_repos} {$where_sql} {$order_sql} {$limit_sql}";
        
        if (!empty($values)) {
            $query = $wpdb->prepare($query, $values);
        }
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Get a single repository by ID
     * 
     * @param int $id Repository ID
     * @return object|null Repository object or null if not found
     */
    public function get_repo($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_repos} WHERE id = %d",
            $id
        ));
    }
    
    /**
     * Get repository by plugin slug
     * 
     * @param string $slug Plugin slug
     * @return object|null Repository object or null if not found
     */
    public function get_repo_by_slug($slug) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_repos} WHERE plugin_slug = %s",
            $slug
        ));
    }
    
    /**
     * Get repository by GitHub owner and name
     * 
     * @param string $owner GitHub owner/org name
     * @param string $name Repository name
     * @return object|null Repository object or null if not found
     */
    public function get_repo_by_name($owner, $name) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_repos} WHERE gh_owner = %s AND gh_name = %s",
            $owner,
            $name
        ));
    }
    
    /**
     * Get repository by GitHub URL
     * 
     * @param string $url GitHub repository URL
     * @return object|null Repository object or null if not found
     */
    public function get_repo_by_url($url) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_repos} WHERE git_repo_url = %s",
            $url
        ));
    }
    
    /**
     * Map database row to local repository format
     * 
     * @param array|object $db_row Database row
     * @return array Local repository data
     */
    public function map_db_to_local_repo($db_row) {
        $db_row = (array) $db_row; // Ensure we're working with an array
        return [
            'id' => $db_row['id'] ?? 0,
            'git_repo_url' => $db_row['git_repo_url'] ?? sprintf('https://github.com/%s/%s', $db_row['gh_owner'] ?? '', $db_row['gh_name'] ?? ''),
            'plugin_slug' => $db_row['plugin_slug'] ?? '',
            'gh_owner' => $db_row['gh_owner'] ?? '',
            'gh_name' => $db_row['gh_name'] ?? '',
            'owner' => $db_row['gh_owner'] ?? '',
            'name' => $db_row['gh_name'] ?? '',
            'url' => $db_row['git_repo_url'] ?? sprintf('https://github.com/%s/%s', $db_row['gh_owner'] ?? '', $db_row['gh_name'] ?? ''),
            'installed_version' => $db_row['local_version'] ?? '',
            'latest_version' => $db_row['git_version'] ?? '',
            'last_updated' => $db_row['updated_at'] ?? '',
            'branch' => $db_row['branch'] ?? 'main',
            'is_private' => (bool) ($db_row['is_private'] ?? false),
            'active' => $db_row['active'] ?? true,
            'created_at' => $db_row['created_at'] ?? current_time('mysql')
        ];
    }
    
    /**
     * Add a new repository
     * 
     * @param array $data Repository data
     * @return int|false The new repository ID or false on failure
     */
    public function add_repo($data) {
        global $wpdb;
        
        $defaults = [
            'git_repo_url' => '',
            'plugin_slug' => '',
            'gh_owner' => '',
            'gh_name' => '',
            'branch' => 'main',
            'local_version' => '0.0.0',
            'git_version' => '0.0.0',
            'is_private' => 0,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Ensure required fields are present
        if (empty($data['git_repo_url']) || empty($data['gh_owner']) || empty($data['gh_name'])) {
            return false;
        }
        
        // Generate plugin slug if not provided
        if (empty($data['plugin_slug'])) {
            $data['plugin_slug'] = sanitize_title($data['gh_name']);
        }
        
        $result = $wpdb->insert(
            $this->table_repos,
            $data,
            [
                '%s', // git_repo_url
                '%s', // plugin_slug
                '%s', // gh_owner
                '%s', // gh_name
                '%s', // branch
                '%s', // local_version
                '%s', // git_version
                '%d', // is_private
                '%s', // created_at
                '%s'  // updated_at
            ]
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Update a repository
     * 
     * @param int $id Repository ID
     * @param array $data Repository data to update
     * @return bool True on success, false on failure
     */
    public function update_repo($id, $data) {
        global $wpdb;
        
        if (empty($id)) {
            return false;
        }
        
        // Don't allow updating these fields directly
        unset($data['id'], $data['created_at']);
        
        // Always update the updated_at timestamp
        $data['updated_at'] = current_time('mysql');
        
        // Prepare format strings based on data types
        $formats = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['is_private'])) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }
        
        $result = $wpdb->update(
            $this->table_repos,
            $data,
            ['id' => $id],
            $formats,
            ['%d'] // Where format
        );
        
        return $result !== false;
    }
    
    /**
     * Update a repository's version
     * 
     * @param int $id Repository ID
     * @param string $version Version string
     * @param bool $is_git_version Whether to update git_version (true) or local_version (false)
     * @return bool True on success, false on failure
     */
    public function update_repo_version($id, $version, $is_git_version = false) {
        $field = $is_git_version ? 'git_version' : 'local_version';
        return $this->update_repo($id, [
            $field => $version,
            'updated_at' => current_time('mysql')
        ]);
    }
    
    /**
     * Delete a repository
     * 
     * @param int $id Repository ID
     * @return bool True on success, false on failure
     */
    public function delete_repo($id) {
        global $wpdb;
        
        if (empty($id)) {
            return false;
        }
        
        $result = $wpdb->delete(
            $this->table_repos,
            ['id' => $id],
            ['%d']
        );
        
        return $result !== false;
    }
    
    /**
     * Migrate data from old versions to the new schema
     */
    public function migrate_data() {
        global $wpdb;
        
        // Migrate from options to settings table (legacy)
        $old_settings = get_option('wpgp_settings', []);
        if (!empty($old_settings)) {
            foreach ($old_settings as $key => $value) {
                $this->update_setting($key, $value);
            }
            // Clean up old option after migration
            delete_option('wpgp_settings');
        }
        
        // Migrate old repositories table structure if needed
        $table_columns = $wpdb->get_col("DESCRIBE {$this->table_repos}", 0);
        
        // Check if we need to migrate from old schema
        if (in_array('repo_url', $table_columns)) {
            // Migrate from old schema to new schema
            $wpdb->query("ALTER TABLE {$this->table_repos} 
                CHANGE COLUMN repo_url git_repo_url varchar(255) NOT NULL,
                ADD COLUMN IF NOT EXISTS plugin_slug varchar(255) NOT NULL AFTER git_repo_url,
                ADD COLUMN IF NOT EXISTS local_version varchar(20) DEFAULT '0.0.0' AFTER branch,
                ADD COLUMN IF NOT EXISTS git_version varchar(20) DEFAULT '0.0.0' AFTER local_version");
            
            // Update existing records to extract owner and name from URL
            $repos = $wpdb->get_results("SELECT id, git_repo_url FROM {$this->table_repos}");
            foreach ($repos as $repo) {
                if (preg_match('#github\.com/([^/]+)/([^/]+?)(?:\.git)?$#i', $repo->git_repo_url, $matches)) {
                    $wpdb->update(
                        $this->table_repos,
                        [
                            'gh_owner' => $matches[1],
                            'gh_name' => rtrim($matches[2], '.git'),
                            'plugin_slug' => sanitize_title($matches[2])
                        ],
                        ['id' => $repo->id],
                        ['%s', '%s', '%s'],
                        ['%d']
                    );
                }
            }
        }
        
        // Migrate from options to repositories table (legacy)
        $old_repos = get_option('wpgp_repos', []);
        if (!empty($old_repos)) {
            foreach ($old_repos as $repo) {
                $this->add_repo([
                    'git_repo_url' => sprintf('https://github.com/%s/%s', $repo['owner'], $repo['name']),
                    'plugin_slug' => $repo['slug'] ?? sanitize_title($repo['name']),
                    'gh_owner' => $repo['owner'],
                    'gh_name' => $repo['name'],
                    'branch' => $repo['branch'] ?? 'main',
                    'local_version' => $repo['version'] ?? '0.0.0',
                    'git_version' => $repo['latest_version'] ?? '0.0.0',
                    'is_private' => $repo['is_private'] ?? 0,
                    'created_at' => $repo['installed_at'] ?? current_time('mysql')
                ]);
            }
            // Clean up old option after migration
            delete_option('wpgp_repos');
        }
        
        // Update database version after successful migration
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        
        return true;
    }
    
    /**
     * Drop all plugin tables and options (for uninstall)
     * 
     * @return bool True on success, false on failure
     */
    public function drop_tables() {
        global $wpdb;
        
        $tables_dropped = true;
        
        // Drop settings table
        $result = $wpdb->query("DROP TABLE IF EXISTS {$this->table_settings}");
        if ($result === false) {
            $this->error_handler->log_error('Failed to drop settings table: ' . $wpdb->last_error);
            $tables_dropped = false;
        }
        
        // Drop repositories table
        $result = $wpdb->query("DROP TABLE IF EXISTS {$this->table_repos}");
        if ($result === false) {
            $this->error_handler->log_error('Failed to drop repositories table: ' . $wpdb->last_error);
            $tables_dropped = false;
        }
        
        // Delete options
        delete_option(self::DB_VERSION_OPTION);
        
        return $tables_dropped;
    }
}
