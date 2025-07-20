<?php
class WP_Git_Plugins_Repository {
    private $db;
    private $error_handler;
    private $settings;
    private $github_token;
    private $github_api;
    
    public function __construct($settings = null) {
        $this->db = WP_Git_Plugins_DB::get_instance();
        $this->error_handler = WP_Git_Plugins_Error_Handler::instance();
        $this->settings = $settings;
        $this->github_token = $this->settings ? $this->settings->get_github_token() : '';
        
        // Initialize GitHub API
        $this->github_api = WP_Git_Plugins_Github_API::get_instance($this->github_token);
        
        // Debug token availability
        error_log('WP Git Plugins - Repository initialized with token: ' . (!empty($this->github_token) ? 'YES' : 'NO'));
        
        // Register AJAX handlers for repository operations
        add_action('wp_ajax_wp_git_plugins_add_repository', array($this, 'ajax_add_repository'));
        add_action('wp_ajax_wp_git_plugins_delete_repository', array($this, 'ajax_delete_repository'));
        add_action('wp_ajax_wp_git_plugins_update_repository', array($this, 'ajax_update_repository'));
        add_action('wp_ajax_wp_git_plugins_check_version', array($this, 'ajax_check_version'));
    }
    
    
    /**
     * Get all local repositories
     * 
     * @return array Array of local repository data
     */    public function get_local_repositories() {
        $rows = $this->db->get_repos();
        $local_repos = [];

        foreach ($rows as $row) {
            $local_repo = $this->db->map_db_to_local_repo((array) $row);
            $local_repos[] = $local_repo;
        }

        return $local_repos;
    }
    
    /**
     * Get a single local repository by ID
     * 
     * @param int $id Local repository ID
     * @return array|false Local repository data or false if not found
     */
    public function get_local_repository($id) {
        $repo = $this->db->get_repo($id);
        return $repo ? $this->db->map_db_to_local_repo((array) $repo) : false;
    }
    
    /**
     * Get local repository by GitHub owner and name
     * 
     * @param string $owner GitHub owner/org name
     * @param string $name Repository name
     * @return array|false Local repository data or false if not found
     */
    public function get_local_repository_by_name($owner, $name) {
        $repo = $this->db->get_repo_by_name($owner, $name);
        return $repo ? $this->db->map_db_to_local_repo((array) $repo) : false;
    }
    
    /**
     * Delete a local repository by ID
     * 
     * @param int $id Local repository ID
     * @return bool True on success, false on failure
     */
    public function delete_local_repository($id) {
        return $this->db->delete_repo($id);
    }
    
    
    /**
     * Add a new repository
     * 
     * @param string $repo_url The full GitHub repository URL
     * @param string $branch The branch to use (default: 'main')
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function add_repository($repo_url, $branch = 'main') {
        // Parse the GitHub URL to get owner and repo name
        $parsed = $this->github_api->parse_github_url($repo_url);
        if (is_wp_error($parsed)) {
            return $parsed;
        }
        
        $owner = $parsed['owner'];
        $name = $parsed['name'];
        
        // Check if repository already exists
        $existing = $this->get_local_repository_by_name($owner, $name);
        if ($existing) {
            return new WP_Error('repo_exists', __('This repository is already added.', 'wp-git-plugins'));
        }

        // Determine if private (check if token is set and URL is GitHub)
        $is_private = (!empty($this->github_token) && strpos($repo_url, 'github.com') !== false) ? 1 : 0;
        
        // Get the latest version from GitHub
        $latest_version = $this->github_api->get_latest_version($owner, $name, $branch);
        if (is_wp_error($latest_version)) {
            $latest_version = '0.0.0'; // Default version if can't determine
        }
        
        // Get the plugin slug (will be updated after installation)
        $plugin_slug = $this->get_plugin_slug($name);

        // Insert into database with all required fields
        $repo_id = $this->db->add_repo([
            'git_repo_url'  => $repo_url,
            'plugin_slug'   => $plugin_slug,
            'gh_owner'      => $owner,
            'gh_name'       => $name,
            'branch'        => $branch,
            'local_version' => '0.0.0', // Will be updated after installation
            'git_version'   => $latest_version,
            'is_private'    => $is_private,
            'created_at'    => current_time('mysql'),
            'updated_at'    => current_time('mysql')
        ]);

        if (!$repo_id) {
            return new WP_Error('db_insert_failed', __('Failed to save repository in the database.', 'wp-git-plugins'));
        }

        // Prepare repository data for installation
        $repo_data = [
            'git_repo_url' => $repo_url,
            'gh_owner' => $owner,
            'gh_name' => $name,
            'branch' => $branch,
            'is_private' => $is_private
        ];
        
        // Attempt to install the plugin
        $local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();
        $install = $local_plugins->install_plugin($repo_data, $this->github_token);
        if (is_wp_error($install)) {
            // Rollback DB record if installation fails
            $this->delete_local_repository($repo_id);
            return $install;
        }
        
        // Update the repository with the actual installed version and slug
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php');
        if (!empty($plugin_data['Version'])) {
            $this->db->update_repo($repo_id, [
                'local_version' => $plugin_data['Version'],
                'updated_at' => current_time('mysql')
            ]);
        }

        return $repo_id; // Return the repository ID instead of true
    }
    
    /**
     * Get the plugin slug for a given repository name
     * 
     * @param string $repo_name The repository name
     * @return string|bool The plugin slug or false if not found
     */
    public function get_plugin_slug($repo_name) {
        // No need to log plugin slug lookup
        $plugins = get_plugins();
        // No need to log all plugins
        
        // Try exact match first
        // No need to log matching process
        foreach ($plugins as $plugin_file => $plugin_data) {
            $dir = dirname($plugin_file);
            // Try exact match or with -main suffix
            if (strtolower($dir) === strtolower($repo_name) || 
                strtolower($dir) === strtolower($repo_name . '-main')) {
                // No need to log exact match
                return $plugin_file;
            }
        }
        
        // Try matching by plugin name
        // No need to log matching process
        $repo_name_lower = strtolower($repo_name);
        foreach ($plugins as $plugin_file => $plugin_data) {
            $plugin_name = isset($plugin_data['Name']) ? strtolower($plugin_data['Name']) : '';
            if (strpos($plugin_name, $repo_name_lower) !== false) {
                // No need to log name match
                return $plugin_file;
            }
        }
        
        // Try case-insensitive match
        // No need to log matching process
        $repo_name_lower = strtolower($repo_name);
        foreach ($plugins as $plugin_file => $plugin_data) {
            $dir = strtolower(dirname($plugin_file));
            if ($dir === $repo_name_lower || $dir === $repo_name_lower . '-main') {
                // No need to log case-insensitive match
                return $plugin_file;
            }
        }
        
        // Try to find any PHP file in the directory that looks like a plugin
        $plugin_dir = WP_PLUGIN_DIR . '/' . $repo_name;
        // No need to log directory check
        
        if (is_dir($plugin_dir)) {
            $files = glob($plugin_dir . '/*.php');
            // No need to log found files
            
            if (!empty($files)) {
                // Look for a file with a plugin header
                foreach ($files as $file) {
                    $file_data = get_plugin_data($file);
                    if (!empty($file_data['Name'])) {
                        $result = $repo_name . '/' . basename($file);
                        // No need to log valid plugin header
                        return $result;
                    }
                }
                
                // If no plugin header found, return the first PHP file
                $result = $repo_name . '/' . basename($files[0]);
                // No need to log fallback to first PHP file
                return $result;
            }
        } else {
            $this->error_handler->log_error('Plugin directory does not exist: ' . $plugin_dir);
        }
        
        // Try the same with -main suffix
        $plugin_dir_main = WP_PLUGIN_DIR . '/' . $repo_name . '-main';
        // No need to log directory check
        
        if (is_dir($plugin_dir_main)) {
            $files = glob($plugin_dir_main . '/*.php');
            // No need to log found files
            
            if (!empty($files)) {
                foreach ($files as $file) {
                    $file_data = get_plugin_data($file);
                    if (!empty($file_data['Name'])) {
                        $result = $repo_name . '-main/' . basename($file);
                        // No need to log valid plugin header
                        return $result;
                    }
                }
                
                $result = $repo_name . '-main/' . basename($files[0]);
                // No need to log fallback to first PHP file
                return $result;
            }
        } else {
            // No need to log non-existent directory
        }
        
        // If still not found, check all plugins for a matching name in the header
        // No need to log search process
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (stripos($plugin_data['Name'], $repo_name) !== false) {
                return $plugin_file;
            }
        }
        
        return false;
    }

    /**
     * AJAX handler for adding a new repository.
     *
     * @since 1.0.0
     */
    public function ajax_add_repository() {
        try {
            WP_Git_Plugins::verify_ajax_request('install_plugins');
            
            $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
            $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : 'main';
            $is_private = isset($_POST['is_private']) ? (bool) $_POST['is_private'] : false;
            $github_token = isset($_POST['github_token']) ? sanitize_text_field($_POST['github_token']) : '';
            
            if (empty($repo_url)) {
                throw new Exception(__('Please enter a valid repository URL.', 'wp-git-plugins'));
            }
            
            if (!empty($github_token) && $this->settings) {
                $this->settings->set_github_token($github_token);
            }
            
            $result = $this->add_repository($repo_url, $branch);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $added_repo = $this->get_local_repository($result);
            if (!$added_repo) {
                throw new Exception(__('Failed to retrieve the added repository.', 'wp-git-plugins'));
            }
            
            wp_send_json_success([
                'message' => __('Repository added successfully.', 'wp-git-plugins'),
                'repository' => $added_repo,
                'redirect' => admin_url('admin.php?page=wp-git-plugins&repo_added=1')
            ]);
            
        } catch (Exception $e) {
            $error_message = sprintf(__('Error adding repository: %s', 'wp-git-plugins'), $e->getMessage());
            error_log('WP Git Plugins: ' . $error_message);
            wp_send_json_error(['message' => $error_message]);
        }
    }

    /**
     * AJAX handler for deleting a repository.
     *
     * @since 1.0.0
     */
    public function ajax_delete_repository() {
        try {
            WP_Git_Plugins::verify_ajax_request('delete_plugins');
            
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $delete_option = isset($_POST['delete_option']) ? sanitize_text_field($_POST['delete_option']) : 'both';
            
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            
            $repo = $this->get_local_repository($repo_id);
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            
            $message = '';
            
            // Handle different delete options
            switch ($delete_option) {
                case 'database':
                    // Delete only database record
                    $result = $this->delete_local_repository($repo_id);
                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }
                    $message = __('Repository removed from list successfully.', 'wp-git-plugins');
                    break;
                    
                case 'files':
                    // Delete only plugin files
                    if (!empty($repo['plugin_slug'])) {
                        $local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();
                        $files_deleted = $local_plugins->delete_plugin_files($repo['plugin_slug']);
                        if (!$files_deleted) {
                            throw new Exception(__('Failed to delete plugin files.', 'wp-git-plugins'));
                        }
                    }
                    $message = __('Plugin files deleted successfully.', 'wp-git-plugins');
                    break;
                    
                case 'both':
                default:
                    // Delete both database record and plugin files
                    $result = $this->delete_local_repository($repo_id);
                    if (is_wp_error($result)) {
                        throw new Exception($result->get_error_message());
                    }
                    
                    if (!empty($repo['plugin_slug'])) {
                        $local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();
                        $local_plugins->delete_plugin_files($repo['plugin_slug']);
                    }
                    $message = __('Repository and plugin files deleted successfully.', 'wp-git-plugins');
                    break;
            }
            
            wp_send_json_success(['message' => $message]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => sprintf(__('Error deleting repository: %s', 'wp-git-plugins'), $e->getMessage())]);
        }
    }

    /**
     * AJAX handler for updating a repository/plugin.
     *
     * @since 1.0.0
     */
    public function ajax_update_repository() {
        try {
            WP_Git_Plugins::verify_ajax_request('install_plugins');

            // Sanitize input
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $force = isset($_POST['force']) ? (bool) $_POST['force'] : false;

            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }

            // Get repository by ID
            $repo = $this->get_local_repository($repo_id);
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }

            // Store plugin slug for error handling
            $plugin_slug = $repo['plugin_slug'] ?? '';
            error_log('WP Git Plugins - Starting update for repository ID: ' . $repo_id . ', Plugin: ' . $plugin_slug);

            $local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();

            // Check if we need to deactivate the plugin first
            $was_active = false;
            if (!empty($plugin_slug)) {
                // Check if plugin is active
                $was_active = $local_plugins->is_plugin_active($plugin_slug);

                if ($was_active) {
                    error_log('WP Git Plugins - Deactivating plugin: ' . $plugin_slug);
                    $deactivate_result = $local_plugins->deactivate_plugin($plugin_slug);

                    if (is_wp_error($deactivate_result)) {
                        throw new Exception(__('Failed to deactivate the plugin before update. Please try again.', 'wp-git-plugins'));
                    }

                    // Give WordPress time to process the deactivation
                    sleep(1);
                }
            }

            // Update the repository by reinstalling the plugin
            error_log('WP Git Plugins - Updating repository ID: ' . $repo_id . ( $force ? ' (force update)' : '' ));
            
            // Prepare repository data for installation/update
            $repo_data = [
                'git_repo_url' => $repo['git_repo_url'],
                'gh_owner' => $repo['gh_owner'],
                'gh_name' => $repo['gh_name'],
                'branch' => $repo['branch'],
                'is_private' => $repo['is_private'],
                'plugin_slug' => $repo['plugin_slug']
            ];
            
            $result = $local_plugins->install_plugin($repo_data, $this->github_token);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            // Get updated repository data
            $updated_repo = $this->get_local_repository($repo_id);

            // Reactivate the plugin if it was active before
            $reactivation_success = true;
            $reactivation_error = '';

            if ($was_active && !empty($plugin_slug) && $local_plugins->is_plugin_installed($plugin_slug)) {
                error_log('WP Git Plugins - Reactivating plugin: ' . $plugin_slug);
                $activate_result = $local_plugins->activate_plugin($plugin_slug);

                if (is_wp_error($activate_result)) {
                    $reactivation_success = false;
                    $reactivation_error = $activate_result->get_error_message();
                    error_log('WP Git Plugins - Reactivation failed: ' . $reactivation_error);
                }
            }

            // Clear plugin update cache
            if (function_exists('wp_clean_plugins_cache')) {
                wp_clean_plugins_cache();
            }

            // Prepare success response
            $response = [
                'message' => $force 
                    ? __('Plugin has been force-updated successfully.', 'wp-git-plugins')
                    : __('Plugin has been updated successfully.', 'wp-git-plugins'),
                'repository' => $updated_repo,
                'was_active' => $was_active,
                'reactivated' => $reactivation_success,
                'plugin_slug' => $plugin_slug,
                'update_info' => $result
            ];

            // Add reactivation warning if needed
            if (!$reactivation_success) {
                $response['reactivation_warning'] = sprintf(
                    __('Warning: The plugin could not be reactivated automatically: %s', 'wp-git-plugins'),
                    $reactivation_error
                );
                $response['message'] .= ' ' . __('However, there was an issue reactivating the plugin.', 'wp-git-plugins');
            }

            wp_send_json_success($response);

        } catch (Exception $e) {
            // Log the error with more context
            $repo_id = isset($repo_id) ? $repo_id : '';
            $plugin_slug = isset($plugin_slug) ? $plugin_slug : '';
            $was_active = isset($was_active) ? $was_active : false;
            error_log(sprintf(
                'WP Git Plugins - Update Error for repo ID %s, plugin %s: %s',
                $repo_id,
                $plugin_slug,
                $e->getMessage()
            ));

            // Try to reactivate the plugin if the update failed after deactivation
            if ($was_active && !empty($plugin_slug)) {
                $local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();
                if ($local_plugins->is_plugin_installed($plugin_slug)) {
                    error_log('WP Git Plugins - Attempting to reactivate plugin after error: ' . $plugin_slug);
                    $reactivated = $local_plugins->activate_plugin($plugin_slug);
                    if (is_wp_error($reactivated)) {
                        error_log('WP Git Plugins - Reactivation after error failed: ' . $reactivated->get_error_message());
                    }
                }
            }

            // Return error response
            wp_send_json_error([
                'message' => sprintf(__('Error updating plugin: %s', 'wp-git-plugins'), $e->getMessage()),
                'plugin_slug' => $plugin_slug,
                'was_active' => $was_active
            ]);
        }
    }
    
    /**
     * AJAX handler for checking version from GitHub
     *
     * @since 1.0.0
     */

    public function ajax_check_version() {
        error_log('WP Git Plugins - ajax_check_version called');
        error_log('WP Git Plugins - POST data: ' . print_r($_POST, true));
        
        try {
            // Verify AJAX request - this will exit with wp_send_json_error if it fails
            WP_Git_Plugins::verify_ajax_request('manage_options');
            
            error_log('WP Git Plugins - Security verification passed');
            
            // Get repository ID
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
        
            if (empty($repo_id)) {
                wp_send_json_error([
                    'message' => __('Invalid repository ID.', 'wp-git-plugins')
                ]);
            }
            
            // Get repository data from database
            $repo_data = $this->db->get_repo($repo_id);
            
            if (!$repo_data) {
                wp_send_json_error([
                    'message' => __('Repository not found.', 'wp-git-plugins')
                ]);
            }
            
            // Check if repository has a valid GitHub URL
            if (empty($repo_data->git_repo_url)) {
                wp_send_json_error([
                    'message' => __('Repository does not have a valid GitHub URL.', 'wp-git-plugins')
                ]);
            }
            
            error_log('WP Git Plugins - Repository data found: ' . print_r($repo_data, true));
            
            // Parse GitHub URL to get owner and repo name
            $parsed_url = $this->github_api->parse_github_url($repo_data->git_repo_url);
            
            if (is_wp_error($parsed_url)) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Invalid GitHub URL: %s', 'wp-git-plugins'),
                        $parsed_url->get_error_message()
                    )
                ]);
            }
            
            error_log('WP Git Plugins - Parsed URL: ' . print_r($parsed_url, true));
            
            $owner = $parsed_url['owner'];
            $repo_name = $parsed_url['name']; // Changed from 'repo' to 'name'
            $branch = !empty($repo_data->branch) ? $repo_data->branch : 'main';
            
            error_log("WP Git Plugins - Checking version for: {$owner}/{$repo_name} (branch: {$branch})");
            
            // Get version from GitHub using the GitHub API
            $git_version = $this->github_api->get_version_from_plugin_header($owner, $repo_name, $branch);
            
            if (is_wp_error($git_version)) {
                wp_send_json_error([
                    'message' => sprintf(
                        __('Failed to get version from GitHub: %s', 'wp-git-plugins'),
                        $git_version->get_error_message()
                    )
                ]);
            }
            
            error_log("WP Git Plugins - Found version from GitHub: {$git_version}");
            
            // Also check and update the local version if plugin is installed
            $local_version = '';
            $plugin_slug = $repo_data->plugin_slug ?? '';
            $local_plugin_main_file = WP_PLUGIN_DIR . '/' . $plugin_slug . '/' . $plugin_slug . '.php';
            
            error_log("WP Git Plugins - Checking local version for local_plugin_main_file: {$local_plugin_main_file}");
            
                
            if (file_exists($local_plugin_main_file)) {
                $plugin_data = get_plugin_data($local_plugin_main_file);
                if (!empty($plugin_data['Version'])) {
                    $local_version = $plugin_data['Version'];
                    error_log("WP Git Plugins - Found local version: {$local_version}");
                } else {
                    error_log('WP Git Plugins - No version found in plugin header.');
                }
            } else {
                error_log('WP Git Plugins - Plugin main file does not exist: ' . $local_plugin_main_file);
            }
            // If local version is not set, use '0.0.0' as default
            if (empty($local_version)) {
                $local_version = '0.0.0';
            }
            
            error_log("WP Git Plugins - Local version: {$local_version}");
            // Log the versions found
            error_log("WP Git Plugins - Versions found - GitHub: {$git_version}, Local: {$local_version}");
            // Check if update is available
            $update_available = version_compare($git_version, $local_version, '>');
            
            // If the local version is newer than the GitHub version, log a warning
            if (version_compare($local_version, $git_version, '>')) {
                error_log("WP Git Plugins - Warning: Local version ({$local_version}) is newer than GitHub version ({$git_version}) for repository ID {$repo_id}");
                wp_send_json_error([
                    'message' => sprintf(
                        __('Warning: Local version (%s) is newer than GitHub version (%s).', 'wp-git-plugins'),
                        $local_version,
                        $git_version
                    ),
                    'git_version' => $git_version,
                    'local_version' => $local_version,  
                    'repo_id' => $repo_id,
                    'update_available' => false
                ]);
            }  
            // Prepare database update data
            $update_data = ['git_version' => $git_version];
            $update_format = ['%s'];
            
            // Include local version if we found one
            if (!empty($local_version)) {
                $update_data['local_version'] = $local_version;
                $update_format[] = '%s';
            }
            
            // Update the git_version (and local_version if found) in the database
            global $wpdb;
            $table_repos = $wpdb->prefix . 'wpgp_repos';
            
            $update_result = $wpdb->update(
                $table_repos,
                $update_data,
                ['id' => $repo_id],
                $update_format,
                ['%d']
            );
            
            if ($update_result === false) {
                error_log('WP Git Plugins - Database update failed: ' . $wpdb->last_error);
                wp_send_json_error([
                    'message' => __('Failed to save version to database.', 'wp-git-plugins')
                ]);
            }
            
            error_log("WP Git Plugins - Successfully updated database with git_version: {$git_version}" . 
                     (!empty($local_version) ? " and local_version: {$local_version}" : ""));
            
            // Prepare success message
            $message = sprintf(
                __('Version check completed. Latest version: %s', 'wp-git-plugins'),
                $git_version
            );
            
            if (!empty($local_version)) {
                $message .= sprintf(
                    __(' (Installed: %s)', 'wp-git-plugins'),
                    $local_version
                );
            }
            
            // Add update available information to message
            if ($update_available) {
                $message = sprintf(
                    __('Update available! New version: %s (Installed: %s)', 'wp-git-plugins'),
                    $git_version,
                    $local_version
                );
            }
            
            // Success response
            wp_send_json_success([
                'message' => $message,
                'git_version' => $git_version,
                'local_version' => $local_version,
                'repo_id' => $repo_id,
                'update_available' => $update_available
            ]);
            
        } catch (Exception $e) {
            error_log('WP Git Plugins - Version check error for repo ID ' . (isset($repo_id) ? $repo_id : 'unknown') . ': ' . $e->getMessage());
            error_log('WP Git Plugins - Exception trace: ' . $e->getTraceAsString());
            
            wp_send_json_error([
                'message' => sprintf(
                    __('Error checking version: %s', 'wp-git-plugins'),
                    $e->getMessage()
                )
            ]);
        }
    }
}