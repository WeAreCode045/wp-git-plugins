<?php
class WP_Git_Plugins_Repository {
    private $db;
    private $error_handler;
    private $settings;
    private $github_token;
    
    public function __construct($settings = null) {
        $this->db = WP_Git_Plugins_DB::get_instance();
        $this->error_handler = WP_Git_Plugins_Error_Handler::instance();
        $this->settings = $settings;
        $this->github_token = $this->settings ? $this->settings->get_github_token() : '';
        
        // Debug token availability
        error_log('WP Git Plugins - Repository initialized with token: ' . (!empty($this->github_token) ? 'YES' : 'NO'));
        
        // Register AJAX handlers for repository operations
        add_action('wp_ajax_wp_git_plugins_add_repository', array($this, 'ajax_add_repository'));
        add_action('wp_ajax_wp_git_plugins_delete_repository', array($this, 'ajax_delete_repository'));
        add_action('wp_ajax_wp_git_plugins_update_repository', array($this, 'ajax_update_repository'));
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
        $parsed = WP_Git_Plugins::parse_github_url($repo_url);
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
        $latest_version = $this->get_latest_version_from_github($owner, $name, $branch);
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
     * Get the download URL for a GitHub repository
     * 
     * @param array $git_repo GitHub repository data
     * @return string Download URL
     */
    private function get_download_url($git_repo) {
        return WP_Git_Plugins::get_download_url($git_repo, $this->github_token);
    }

    /**
     * Get the GitHub API URL for a repository
     * 
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $endpoint API endpoint (default: '')
     * @return string GitHub API URL
     */
    private function get_github_api_url($owner, $repo, $endpoint = '') {
        return WP_Git_Plugins::get_github_api_url($owner, $repo, $endpoint);
    }
    
    
    /**
     * Get the latest version from GitHub for a repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch Branch name
     * @return string|WP_Error Version string or WP_Error on failure
     */
    public function get_latest_version_from_github($owner, $repo, $branch = 'main') {
        // Try to get version from the plugin file in the repository
        $api_url = sprintf('https://api.github.com/repos/%s/%s/contents/%s.php?ref=%s', 
            $owner, $repo, $repo, $branch);
        
        $args = [];
        if (!empty($this->github_token)) {
            $args['headers'] = [
                'Authorization' => 'token ' . $this->github_token,
                'Accept' => 'application/vnd.github.v3+json'
            ];
        }
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            return '0.0.0'; // Return default version instead of error
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code === 200) {
            $content = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($content['content'])) {
                $decoded_content = base64_decode($content['content']);
                // Look for version in the plugin header
                if (preg_match('/Version:\s*([^\n\r]+)/i', $decoded_content, $matches)) {
                    return trim($matches[1]);
                }
            }
        }
        
        // Fallback: try to get latest release tag
        $api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest', $owner, $repo);
        $response = wp_remote_get($api_url, $args);
        
        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $release = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($release['tag_name'])) {
                return ltrim($release['tag_name'], 'v'); // Remove 'v' prefix if present
            }
        }
        
        return '0.0.0'; // Default version
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
            
            if (empty($repo_id)) {
                throw new Exception(__('Repository ID is required.', 'wp-git-plugins'));
            }
            
            $repo = $this->get_local_repository($repo_id);
            if (empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            
            $result = $this->delete_local_repository($repo_id);
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            // Delete plugin files if plugin slug exists
            if (!empty($repo['plugin_slug'])) {
                $local_plugins = WP_Git_Plugins_Local_Plugins::get_instance();
                $local_plugins->delete_plugin_files($repo['plugin_slug']);
            }
            
            wp_send_json_success(['message' => __('Repository deleted successfully.', 'wp-git-plugins')]);
            
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
}
