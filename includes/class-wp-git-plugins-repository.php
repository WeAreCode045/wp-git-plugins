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
        
        // Add AJAX handlers for branch operations
        add_action('wp_ajax_wp_git_plugins_get_branches', array($this, 'ajax_get_branches'));
        add_action('wp_ajax_wp_git_plugins_switch_branch', array($this, 'ajax_switch_branch'));
    }
    
    /**
     * Get all local repositories
     * 
     * @return array Array of local repository data
     */
    /**
     * Get all branches for a GitHub repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|WP_Error Array of branch names or WP_Error on failure
     */
    public function get_repository_branches($owner, $repo) {
        $transient_key = 'wp_git_plugins_branches_' . md5($owner . $repo);
        $branches = get_transient($transient_key);
        
        if (false === $branches) {
            $api_url = "https://api.github.com/repos/{$owner}/{$repo}/branches";
            $args = array(
                'headers' => array(
                    'Accept' => 'application/vnd.github.v3+json',
                    'Authorization' => $this->github_token ? 'token ' . $this->github_token : ''
                )
            );
            
            $response = wp_remote_get($api_url, $args);
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (empty($data) || !is_array($data)) {
                return new WP_Error('no_branches', __('No branches found or invalid response from GitHub.', 'wp-git-plugins'));
            }
            
            $branches = array_map(function($branch) {
                return $branch['name'];
            }, $data);
            
            // Cache for 1 hour
            set_transient($transient_key, $branches, HOUR_IN_SECONDS);
        }
        
        return $branches;
    }
    
    /**
     * Switch a repository to a different branch
     * 
     * @param int $repo_id Repository ID
     * @param string $new_branch Branch to switch to
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function switch_repository_branch($repo_id, $new_branch) {
        // Get repository data
        $repo = $this->db->get_repo($repo_id);
        if (!$repo) {
            return new WP_Error('repo_not_found', __('Repository not found.', 'wp-git-plugins'));
        }
        
        // Get plugin slug and path
        $plugin_slug = $repo->plugin_slug;
        $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        // Check if plugin is active
        if (is_plugin_active($plugin_slug)) {
            deactivate_plugins($plugin_slug);
        }
        
        // Remove the existing plugin
        $this->delete_plugin($plugin_path);
        
        // Clone the new branch
        $clone_url = sprintf('https://github.com/%s/%s.git', $repo->gh_owner, $repo->gh_name);
        $temp_dir = WP_CONTENT_DIR . '/wp-git-plugins-temp/' . uniqid();
        
        // Create temp directory
        if (!wp_mkdir_p($temp_dir)) {
            return new WP_Error('temp_dir_failed', __('Failed to create temporary directory.', 'wp-git-plugins'));
        }
        
        // Clone the repository
        $command = sprintf(
            'git clone --depth 1 --branch %s %s %s',
            escapeshellarg($new_branch),
            escapeshellarg($clone_url),
            escapeshellarg($temp_dir)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var !== 0) {
            $this->delete_plugin($temp_dir);
            return new WP_Error('git_clone_failed', sprintf(__('Failed to clone repository: %s', 'wp-git-plugins'), implode("\n", $output)));
        }
        
        // Move the plugin to the plugins directory
        $plugin_dir = dirname($plugin_path);
        if (!wp_mkdir_p($plugin_dir)) {
            $this->delete_plugin($temp_dir);
            return new WP_Error('plugin_dir_failed', __('Failed to create plugin directory.', 'wp-git-plugins'));
        }
        
        // Move files from temp to plugins directory
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($temp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
            $target = $plugin_dir . DIRECTORY_SEPARATOR . $files->getSubPathName();
            if ($file->isDir()) {
                wp_mkdir_p($target);
            } else {
                copy($file, $target);
            }
        }
        
        // Clean up temp directory
        $this->delete_plugin($temp_dir);
        
        // Update repository branch in database
        $this->db->update_repo($repo_id, array('branch' => $new_branch));
        
        // Clear any cached data
        delete_transient('wp_git_plugins_branches_' . md5($repo->gh_owner . $repo->gh_name));
        
        return true;
    }
    
    /**
     * Helper function to delete a plugin directory
     */
    private function delete_plugin($path) {
        if (!file_exists($path)) {
            return true;
        }
        
        if (is_dir($path)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            
            foreach ($files as $file) {
                if ($file->isDir()) {
                    rmdir($file->getRealPath());
                } else {
                    unlink($file->getRealPath());
                }
            }
            
            return rmdir($path);
        }
        
        return unlink($path);
    }
    
    /**
     * AJAX handler to get branches for a repository
     */

public function ajax_get_branches() {
    check_ajax_referer('wp_git_plugins_nonce', 'nonce');
    
if (!current_user_can('edit_plugins')) {
    wp_send_json_error(array('message' => __('You do not have permission to view branches.', 'wp-git-plugins')));
}

        
        $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
        $owner = isset($_POST['gh_owner']) ? sanitize_text_field($_POST['gh_owner']) : '';
        $repo = isset($_POST['gh_name']) ? sanitize_text_field($_POST['gh_name']) : '';
        
        if (empty($owner) || empty($repo)) {
            $repo_data = $this->db->get_repo($repo_id);
            if ($repo_data) {
                $owner = $repo_data->gh_owner;
                $repo = $repo_data->gh_name;
            }
        }
        
        if (empty($owner) || empty($repo)) {
            wp_send_json_error(array('message' => __('Invalid repository information.', 'wp-git-plugins')));
        }
        
        $branches = $this->get_repository_branches($owner, $repo);
        
        if (is_wp_error($branches)) {
            wp_send_json_error(array('message' => $branches->get_error_message()));
        }
        
        wp_send_json_success(array('branches' => $branches));
    }
    
    /**
     * AJAX handler to switch repository branch
     */
    public function ajax_switch_branch() {
        check_ajax_referer('wp_git_plugins_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'wp-git-plugins')));
        }
        
        $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
        $new_branch = isset($_POST['new_branch']) ? sanitize_text_field($_POST['new_branch']) : '';
        
        if (empty($repo_id) || empty($new_branch)) {
            wp_send_json_error(array('message' => __('Missing required parameters.', 'wp-git-plugins')));
        }
        
        // Get repository data
        $repo = $this->db->get_repo($repo_id);
        if (!$repo) {
            wp_send_json_error(array('message' => __('Repository not found.', 'wp-git-plugins')));
        }
        
        // Prepare repository data for change_repository_branch
        $repo_data = array(
            'url' => $repo->git_repo_url,
            'owner' => $repo->gh_owner,
            'name' => $repo->gh_name,
            'repo_name' => $repo->name
        );
        
        // Switch the branch
        $result = $this->change_repository_branch($repo_data, $new_branch);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
        
        // Reactivate the plugin if it was active
        if (is_plugin_active($repo->plugin_slug)) {
            activate_plugin($repo->plugin_slug);
        }
        
        wp_send_json_success(array(
            'message' => sprintf(__('Successfully switched to branch: %s', 'wp-git-plugins'), $new_branch)
        ));
    }
    
    public function get_local_repositories() {
        $rows = $this->db->get_repos();
        $local_repos = [];

        foreach ($rows as $row) {
            $local_repo = $this->map_db_to_local_repo((array) $row);
            $local_repos[] = $local_repo;
        }

        return $local_repos;
    }
    
    /**
     * Map database row to local repository format
     * 
     * @param array $db_row Database row
     * @return array Local repository data
     */
    private function map_db_to_local_repo($db_row) {
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
     * Get a single local repository by ID
     * 
     * @param int $id Local repository ID
     * @return array|false Local repository data or false if not found
     */
    public function get_local_repository($id) {
        $repo = $this->db->get_repo($id);
        return $repo ? $this->map_db_to_local_repo((array) $repo) : false;
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
        return $repo ? $this->map_db_to_local_repo((array) $repo) : false;
    }
    
    /**
     * Get the latest version of a GitHub repository
     * 
     * @param array $git_repo GitHub repository data
     * @return string|WP_Error Version number or WP_Error on failure
     */
    
    /**
     * Update the version of a local repository
     *
     * @param int $local_repo_id The local repository ID
     * @param string $version The new version
     * @param bool $is_git_version Whether this is the git version (true) or local version (false)
     * @return bool|int Number of rows updated or false on failure
     */
    public function update_local_repo_version($local_repo_id, $version, $is_git_version = false) {
        if (empty($local_repo_id) || empty($version)) {
            return false;
        }
        
        $version = sanitize_text_field($version);
        $update_data = [
            'updated_at' => current_time('mysql')
        ];
        
        if ($is_git_version) {
            $update_data['git_version'] = $version;
        } else {
            $update_data['local_version'] = $version;
        }
        
        // Update the repository in the database using the DB class
        return $this->db->update_repo($local_repo_id, $update_data);
    }
    
    /**
     * Get the latest version of a plugin from GitHub by parsing the plugin header
     * 
     * @param string $owner GitHub repository owner
     * @param string $repo GitHub repository name
     * @param string $branch Branch name (default: 'main')
     * @param int $repo_id The ID of the repository
     * @return string|WP_Error Version string on success, WP_Error on failure
     */
 
public function get_latest_version_from_github($owner, $repo, $branch = 'main', $repo_id = null) {
    $transient_key = 'wp_git_plugins_latest_version_' . md5($owner . $repo . $branch);
    $cached = get_transient($transient_key);
    if (false !== $cached) {
        return $cached;
    }

    // Fetch the plugin file from GitHub
    $plugin_file_url = sprintf('https://raw.githubusercontent.com/%s/%s/%s/%s.php', $owner, $repo, $branch, $repo);
    $response = wp_remote_get($plugin_file_url);
    if (!is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response)) {
        $file_contents = wp_remote_retrieve_body($response);
        if (preg_match('/^\\s*Version:\\s*(.+)$/mi', $file_contents, $matches)) {
            $version = trim($matches[1]);
            set_transient($transient_key, $version, HOUR_IN_SECONDS);
            // Save to database
            if ($repo_id !== null) {
                $repo_row = $this->db->get_repo($repo_id);
                if ($repo_row && !empty($repo_row->id)) {
                    $this->db->update_repo($repo_row->id, [
                        'git_version' => $version,
                        'updated_at' => current_time('mysql')
                    ]);
                }
            }
            return $version;
        }
    }
}

/**
 * Check for updates for a specific repository
 * 
 * @param int $repo_id The repository ID
 * @return array|WP_Error Array with update info or WP_Error on failure
 */
public function check_repository_updates($repo_id) {
        $repo = $this->get_local_repository($repo_id);
        
        if (!$repo) {
            return new WP_Error('repo_not_found', __('Repository not found.', 'wp-git-plugins'));
        }
        $latest_version = $this->get_latest_version_from_github(
            $repo['gh_owner'],
            $repo['gh_name'],
            $repo['branch'],
            $repo_id
        );
        
        if (is_wp_error($latest_version)) {
            return $latest_version;
        }
        
        // Update the git_version in the database
        $this->db->update_repo($repo_id, [
            'git_version' => $latest_version,
            'updated_at' => current_time('mysql')
        ]);
        
        return [
            'repo_id' => $repo_id,
            'local_version' => $repo['local_version'],
            'latest_version' => $latest_version,
            'update_available' => version_compare($latest_version, $repo['local_version'], '>')
        ];
    }
    
    /**
     * Check for updates for all repositories
     * 
     * @return array Array of repositories with update information
     */
    public function check_all_repositories_for_updates() {
        $local_repos = $this->get_local_repositories();
        $results = [];
        
        foreach ($local_repos as $repo) {
            $result = $this->check_repository_updates($repo['id']);
            if (!is_wp_error($result)) {
                $results[] = $result;
            }
        }
        
        return $results;
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
        $parsed = $this->parse_github_url($repo_url);
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
        $latest_version = $this->get_latest_version_from_github($owner, $name, $branch, $repo_id ?? null);
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
        $install = $this->install_plugin($repo_data);
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
     * Update a repository to the latest version
     * 
     * @param int $id Repository ID
     * @param array $data Update data
     * @return array|WP_Error Updated repository data or WP_Error on failure
     */
    public function update_repository($id, $data = []) {
        // First, get the current repository data
        $repo = $this->get_local_repository($id);
        if (empty($repo)) {
            return new WP_Error('repo_not_found', __('Repository not found.', 'wp-git-plugins'));
        }
        
        // If git_version is not set, check for updates first
        if (empty($repo['git_version'])) {
            $update_check = $this->check_repository_updates($id);
            if (is_wp_error($update_check)) {
                return $update_check;
            }
            $repo = $this->get_local_repository($id); // Refresh repo data
        }
        
        // If no update is available and we're not forcing
        if (empty($data['force']) && !empty($repo['git_version']) && 
            version_compare($repo['git_version'], $repo['local_version'], '<=')) {
            return new WP_Error('already_updated', __('The plugin is already up to date.', 'wp-git-plugins'));
        }
        
        // Determine target directory
        $target_dir = WP_PLUGIN_DIR . '/' . $repo['plugin_slug'];
        
        // Remove existing plugin directory if it exists
        if (file_exists($target_dir)) {
            $this->rrmdir($target_dir);
        }
        
        // Prepare repository data for installation
        $repo_data = [
            'id' => $repo['id'],
            'git_repo_url' => $repo['git_repo_url'],
            'gh_owner' => $repo['gh_owner'],
            'gh_name' => $repo['gh_name'],
            'branch' => $repo['branch'] ?? 'main',
            'is_private' => $repo['is_private'] ?? 0,
            'plugin_slug' => $repo['plugin_slug']
        ];
        
        // Install the plugin
        $result = $this->install_plugin($repo_data);
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Get the installed version from the plugin header
        $plugin_file = $target_dir . '/' . $repo['gh_name'] . '.php';
        if (file_exists($plugin_file)) {
            $plugin_data = get_plugin_data($plugin_file, false, false);
            if (!empty($plugin_data['Version'])) {
                $installed_version = $plugin_data['Version'];
            }
        }
        
        // Fallback to git_version if we couldn't get the version from the plugin header
        $installed_version = $installed_version ?? $repo['git_version'];
        
        // Update the repository version in the database
        $update_data = [
            'local_version' => $installed_version,
            'updated_at' => current_time('mysql')
        ];
        
        $updated = $this->db->update_repo($id, $update_data);
        
        if (!$updated) {
            return new WP_Error('update_failed', __('Failed to update repository version in the database.', 'wp-git-plugins'));
        }
        
        // Get the updated repository data
        $updated_repo = $this->get_local_repository($id);
        
        return [
            'repo_id' => $id,
            'local_version' => $installed_version,
            'previous_version' => $repo['local_version'],
            'success' => true,
            'message' => __('Plugin updated successfully.', 'wp-git-plugins')
        ];
    }
    
    // Back-compat wrapper
    public function remove_repository($id_or_url) {
        if (is_numeric($id_or_url)) {
            return $this->delete_local_repository($id_or_url);
        }
        
        $parsed = $this->parse_github_url($id_or_url);
        if (is_wp_error($parsed)) {
            return false;
        }
        $repo = $this->get_local_repository_by_name($parsed['owner'], $parsed['name']);
        if ($repo) {
            return $this->delete_local_repository($repo['id']);
        }
        
        return false;
    }
    
    /**
     * Delete a local repository by ID
     * 
     * @param int $local_repo_id Local repository ID
     * @return bool True on success, false on failure
     */
    public function delete_local_repository($local_repo_id) {
        return $this->db->delete_repo($local_repo_id);
    }
    
    /**
     * Get a setting
     * 
     * @param string $name Setting name
     * @param mixed $default Default value if setting doesn't exist
     * @return mixed Setting value or default
     */
    public function get_setting($name, $default = '') {
        return $this->db->get_setting($name, $default);
    }
    
    /**
     * Update a setting
     * 
     * @param string $name Setting name
     * @param mixed $value Setting value
     * @return bool True on success, false on failure
     */
    public function update_setting($name, $value) {
        return $this->db->update_setting($name, $value);
    }
    
    /**
     * Parse a GitHub URL into owner and name components
     * 
     * @param string $url GitHub repository URL
     * @return array|WP_Error Array with 'owner' and 'name' keys, or WP_Error on failure
     */
    public function parse_github_url($url) {
        $pattern = '#^(?:https?://|git@)?(?:www\.)?github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#';
        
        if (preg_match($pattern, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name' => rtrim($matches[2], '.git')
            ];
        }
        
        return new WP_Error('invalid_url', __('Invalid GitHub repository URL', 'wp-git-plugins'));
    }
    
    /**
     * Get the download URL for a GitHub repository
     * 
     * @param array $git_repo GitHub repository data
     * @return string Download URL
     */
    private function get_download_url($git_repo) {
        if (!empty($git_repo['is_private']) && !empty($this->github_token)) {
            return sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s?access_token=%s',
                $git_repo['gh_owner'],
                $git_repo['gh_name'],
                $git_repo['branch'],
                $this->github_token
            );
        }
        
        return sprintf(
            'https://github.com/%s/%s/archive/refs/heads/%s.zip',
            $git_repo['gh_owner'],
            $git_repo['gh_name'],
            $git_repo['branch']
        );
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
        $url = 'https://api.github.com/repos/' . $owner . '/' . $repo;
        if (!empty($endpoint)) {
            $url .= '/' . ltrim($endpoint, '/');
        }
        
        // Add authentication token if available
        if (!empty($this->github_token)) {
            $url = add_query_arg('access_token', $this->github_token, $url);
        }
        
        return $url;
    }
    
    /**
     * Install a plugin from a GitHub repository
     * 
     * @param array $git_repo GitHub repository data
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function install_plugin($git_repo) {
        // Ensure we have required fields
        if (empty($git_repo['gh_owner']) || empty($git_repo['gh_name'])) {
            return new WP_Error('invalid_repo_data', __('Invalid repository data. Missing owner or repository name.', 'wp-git-plugins'));
        }
        
        $this->error_handler->log_error(sprintf('Starting plugin installation: %s/%s', 
            $git_repo['gh_owner'],
            $git_repo['gh_name']
        ));

        // Use the is_private flag from repo data if available, otherwise determine it
        $is_private = isset($git_repo['is_private']) ? (bool)$git_repo['is_private'] : 
                     (!empty($this->github_token) && 
                      !empty($git_repo['git_repo_url']) && 
                      strpos($git_repo['git_repo_url'], 'github.com') !== false);

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        
        WP_Filesystem();
        
        // Ensure we have the repository URL
        if (empty($git_repo['git_repo_url'])) {
            $git_repo['git_repo_url'] = sprintf('https://github.com/%s/%s', 
                $git_repo['gh_owner'], 
                $git_repo['gh_name']
            );
        }
        
        // Get branch from repo data or default to 'main'
        $branch = !empty($git_repo['branch']) ? $git_repo['branch'] : 'main';
        
        // Determine target directory using plugin slug if available, otherwise use repo name
        $plugin_slug = !empty($git_repo['plugin_slug']) ? $git_repo['plugin_slug'] : $git_repo['gh_name'];
        $target_dir = WP_PLUGIN_DIR . '/' . $plugin_slug;
        
        // Determine if this is a private repo and format clone URL accordingly
        $clone_url = $is_private 
            ? sprintf('https://%s@github.com/%s/%s.git', $this->github_token, $git_repo['gh_owner'], $git_repo['gh_name'])
            : sprintf('https://github.com/%s/%s.git', $git_repo['gh_owner'], $git_repo['gh_name']);
                
        $this->error_handler->log_error(sprintf('Preparing to clone repository: %s/%s (branch: %s) to %s', 
            $git_repo['gh_owner'],
            $git_repo['gh_name'],
            $branch,
            $target_dir
        ));
        
        // Check if Git is available
        if (!function_exists('shell_exec')) {
            $error = new WP_Error('shell_exec_disabled', __('The shell_exec() function is disabled on this server.', 'wp-git-plugins'));
            $this->error_handler->log_error('shell_exec is disabled: ' . $error->get_error_message());
            return $error;
        }
        
        $git_path = shell_exec('which git');
        if (!$git_path) {
            $error = new WP_Error('git_not_available', __('Git is not available on this server. Please install Git to use this feature.', 'wp-git-plugins'));
            $this->error_handler->log_error('Git not found: ' . $error->get_error_message());
            return $error;
        }
        
        // Create plugins directory if it doesn't exist
        if (!file_exists(WP_PLUGIN_DIR)) {
            wp_mkdir_p(WP_PLUGIN_DIR);
        }
        
        // Check if directory exists and is a git repository
        if (file_exists($target_dir)) {
            $this->error_handler->log_error('Target directory exists: ' . $target_dir);
            
            if (file_exists($target_dir . '/.git')) {
                $this->error_handler->log_error(sprintf('Updating existing Git repository at %s (branch: %s)', 
                    $target_dir,
                    $git_repo['branch']
                ));
                
                $command = sprintf(
                    'cd %s && git fetch origin %s && git checkout %s && git pull origin %s 2>&1',
                    escapeshellarg($target_dir),
                    escapeshellarg($branch),
                    escapeshellarg($branch),
                    escapeshellarg($branch)
                );
                // No need to log git commands for security reasons
                
                $output = shell_exec($command);
                if (!empty($output)) {
                    $this->error_handler->log_error('Git command output: ' . $output);
                }
            }
            
            // If we get here, the update was successful
            return true;
        } else {
            // Directory doesn't exist, clone the repository
            $command = sprintf(
                'git clone --branch %s --single-branch --depth 1 %s %s 2>&1',
                escapeshellarg($branch),
                escapeshellarg($clone_url),
                escapeshellarg($target_dir)
            );
            
            $output = shell_exec($command);
            if (!empty($output)) {
                $this->error_handler->log_error('Git clone output: ' . $output);
            }
            
            if (!is_dir($target_dir . '/.git')) {
                return new WP_Error('git_clone_failed', __('Failed to clone repository.', 'wp-git-plugins'));
            }
            
            return true;
        }
    }
    
    /**
     * Change the branch of a repository by deleting the existing plugin folder
     * and cloning the selected branch using git clone --single-branch.
     * 
     * @param array $repo_data Repository data including URL, owner, and name
     * @param string $branch Branch name to switch to
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function change_repository_branch($repo_data, $branch) {
        if (!is_array($repo_data) || empty($repo_data['url']) || empty($repo_data['owner']) || empty($repo_data['name'])) {
            return new WP_Error('invalid_repo_data', __('Invalid repository data provided.', 'wp-git-plugins'));
        }

        // Get the repository by owner/name
        $repo = $this->get_local_repository_by_name($repo_data['owner'], $repo_data['name']);
        if (empty($repo)) {
            return new WP_Error('repo_not_found', __('Repository not found in local database.', 'wp-git-plugins'));
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $repo['plugin_slug'];

        // Delete the existing plugin folder
        if (is_dir($plugin_dir)) {
            $this->rrmdir($plugin_dir);
        }

        // Prepare clone URL
        $clone_url = sprintf('https://github.com/%s/%s.git', $repo_data['owner'], $repo_data['name']);
        if (!empty($repo['is_private']) && !empty($this->github_token)) {
            $clone_url = sprintf('https://%s@github.com/%s/%s.git', $this->github_token, $repo_data['owner'], $repo_data['name']);
        }

        // Clone the selected branch into the plugins folder
        $command = sprintf(
            'git clone --single-branch --branch %s %s %s 2>&1',
            escapeshellarg($branch),
            escapeshellarg($clone_url),
            escapeshellarg($plugin_dir)
        );
        $output = shell_exec($command);

        // Check if clone succeeded
        if (!is_dir($plugin_dir) || !file_exists($plugin_dir)) {
            return new WP_Error('git_clone_failed', __('Failed to clone repository branch.', 'wp-git-plugins'));
        }

        // Update the repository record with the new branch
        $result = $this->db->update_repo($repo['id'], [
            'branch' => $branch,
            'updated_at' => current_time('mysql')
        ]);
        if (!$result) {
            return new WP_Error('db_update_failed', __('Failed to update repository record.', 'wp-git-plugins'));
        }

        return true;
    }
    
    /**
     * Recursively remove a directory
     * 
     * @param string $dir Directory path to remove
     * @return bool True on success, false on failure
     */
    private function rrmdir($dir) {
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
                $success = $this->rrmdir($path) && $success;
            } else {
                $success = unlink($path) && $success;
            }
        }
        
        return rmdir($dir) && $success;
    }
    
    public function get_github_token() {
        return $this->github_token;
    }
    
    /**
     * Get all branches for a GitHub repository
     * 
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|WP_Error Array of branch names or WP_Error on failure
     */
    public function get_github_branches($owner, $repo) {
        $transient_key = 'wpgp_github_branches_' . md5($owner . $repo);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $api_url = $this->get_github_api_url($owner, $repo, 'branches');
        
        $args = [];
        if (!empty($this->github_token)) {
            $args['headers'] = [
                'Authorization' => 'token ' . $this->github_token,
                'Accept' => 'application/vnd.github.v3+json'
            ];
        }
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error(
                'github_api_error',
                sprintf(
                    __('Failed to fetch branches. GitHub API returned status code %d', 'wp-git-plugins'),
                    $response_code
                )
            );
        }
        
        $branches = json_decode(wp_remote_retrieve_body($response), true);
        $branch_names = [];
        
        if (is_array($branches)) {
            foreach ($branches as $branch) {
                if (isset($branch['name'])) {
                    $branch_names[] = $branch['name'];
                }
            }
        }
        
        // Cache for 1 hour
        if (!empty($branch_names)) {
            set_transient($transient_key, $branch_names, HOUR_IN_SECONDS);
        }
        
        return $branch_names;
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
}
