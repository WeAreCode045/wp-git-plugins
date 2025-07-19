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
        add_action('wp_ajax_wp_git_plugins_get_branches', array($this, 'ajax_get_branches'));
    }
    
    
    /**
     * Get all local repositories
     * 
     * @return array Array of local repository data
     */    public function get_local_repositories() {
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
        error_log('WP Git Plugins - get_github_branches called for: ' . $owner . '/' . $repo);
        
        $transient_key = 'wpgp_github_branches_' . md5($owner . $repo);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            error_log('WP Git Plugins - Returning cached branches: ' . json_encode($cached));
            return $cached;
        }
        
        $api_url = $this->get_github_api_url($owner, $repo, 'branches');
        error_log('WP Git Plugins - GitHub API URL: ' . $api_url);
        
        $args = [];
        if (!empty($this->github_token)) {
            $args['headers'] = [
                'Authorization' => 'token ' . $this->github_token,
                'Accept' => 'application/vnd.github.v3+json'
            ];
            error_log('WP Git Plugins - Using GitHub token for authentication');
        } else {
            error_log('WP Git Plugins - No GitHub token available, using public API');
        }
        
        $response = wp_remote_get($api_url, $args);
        
        if (is_wp_error($response)) {
            error_log('WP Git Plugins - wp_remote_get error: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        error_log('WP Git Plugins - GitHub API response code: ' . $response_code);
        error_log('WP Git Plugins - GitHub API response body: ' . substr($response_body, 0, 500) . '...');
        
        if ($response_code !== 200) {
            $error_message = sprintf(
                __('Failed to fetch branches. GitHub API returned status code %d', 'wp-git-plugins'),
                $response_code
            );
            error_log('WP Git Plugins - API Error: ' . $error_message);
            
            // Check for rate limiting
            if ($response_code === 403) {
                $headers = wp_remote_retrieve_headers($response);
                if (isset($headers['x-ratelimit-remaining']) && $headers['x-ratelimit-remaining'] == '0') {
                    $error_message = __('GitHub API rate limit exceeded. Please wait or add a GitHub token.', 'wp-git-plugins');
                }
            }
            
            return new WP_Error('github_api_error', $error_message);
        }
        
        $branches = json_decode($response_body, true);
        $branch_names = [];
        
        if (is_array($branches)) {
            foreach ($branches as $branch) {
                if (isset($branch['name'])) {
                    $branch_names[] = $branch['name'];
                }
            }
            error_log('WP Git Plugins - Extracted branch names: ' . json_encode($branch_names));
        } else {
            error_log('WP Git Plugins - Failed to decode JSON response or no branches found');
        }
        
        // Cache for 1 hour
        if (!empty($branch_names)) {
            set_transient($transient_key, $branch_names, HOUR_IN_SECONDS);
            error_log('WP Git Plugins - Cached branches for 1 hour');
        }
        
        return $branch_names;
    }
    
    /**
     * AJAX handler for getting repository branches.
     *
     * @since 1.0.0
     */
    public function ajax_get_branches() {
        try {
            // Verify AJAX request
            if (!defined('DOING_AJAX') || !DOING_AJAX) {
                wp_send_json_error(['message' => __('Invalid request', 'wp-git-plugins')], 400);
            }

            // Accept both _ajax_nonce and nonce for compatibility
            $nonce = isset($_REQUEST['_ajax_nonce']) ? $_REQUEST['_ajax_nonce'] : (isset($_REQUEST['nonce']) ? $_REQUEST['nonce'] : '');
            if (empty($nonce) || !wp_verify_nonce($nonce, 'wp_git_plugins_ajax')) {
                wp_send_json_error(['message' => __('Security check failed', 'wp-git-plugins')], 403);
            }

            // Check user capabilities
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => __('You do not have permission to view branches', 'wp-git-plugins')], 403);
            }
            
            // Support both repo_url (for add repo form) and repo_id (for branch selector)
            $repo_url = isset($_POST['repo_url']) ? esc_url_raw($_POST['repo_url']) : '';
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $gh_owner = isset($_POST['gh_owner']) ? sanitize_text_field($_POST['gh_owner']) : '';
            $gh_name = isset($_POST['gh_name']) ? sanitize_text_field($_POST['gh_name']) : '';
            $github_token = isset($_POST['github_token']) ? sanitize_text_field($_POST['github_token']) : '';
            
            // Log incoming parameters for debugging
            error_log('WP Git Plugins - AJAX get_branches called with: ' . json_encode([
                'repo_url' => $repo_url,
                'repo_id' => $repo_id,
                'gh_owner' => $gh_owner,
                'gh_name' => $gh_name
            ]));
            
            // Determine owner and repo name
            $owner = '';
            $name = '';
            
            if (!empty($gh_owner) && !empty($gh_name)) {
                // Using data from existing repository
                $owner = $gh_owner;
                $name = $gh_name;
                error_log('WP Git Plugins - Using gh_owner/gh_name: ' . $owner . '/' . $name);
            } elseif (!empty($repo_url)) {
                // Parse repository URL
                $parsed = $this->parse_github_url($repo_url);
                if (is_wp_error($parsed)) {
                    throw new Exception($parsed->get_error_message());
                }
                $owner = $parsed['owner'];
                $name = $parsed['name'];
                error_log('WP Git Plugins - Parsed from URL: ' . $owner . '/' . $name);
            } elseif (!empty($repo_id)) {
                // Get repository data from database
                $repo = $this->get_local_repository($repo_id);
                if (empty($repo)) {
                    throw new Exception(__('Repository not found.', 'wp-git-plugins'));
                }
                $owner = $repo['gh_owner'] ?? '';
                $name = $repo['gh_name'] ?? '';
                error_log('WP Git Plugins - From DB repo_id ' . $repo_id . ': ' . $owner . '/' . $name);
            }
            
            if (empty($owner) || empty($name)) {
                throw new Exception(__('Repository owner and name are required.', 'wp-git-plugins'));
            }
            
            // Update GitHub token if provided
            if (!empty($github_token) && $this->settings) {
                $this->settings->set_github_token($github_token);
                $this->github_token = $github_token;
            }
            
            error_log('WP Git Plugins - Fetching branches for: ' . $owner . '/' . $name);
            $branches = $this->get_github_branches($owner, $name);
            if (is_wp_error($branches)) {
                error_log('WP Git Plugins - Error fetching branches: ' . $branches->get_error_message());
                throw new Exception($branches->get_error_message());
            }
            
            error_log('WP Git Plugins - Successfully fetched branches: ' . json_encode($branches));
            
            wp_send_json_success([
                'branches' => $branches,
                'default_branch' => in_array('main', $branches) ? 'main' : (in_array('master', $branches) ? 'master' : ($branches[0] ?? 'main'))
            ]);
            
        } catch (Exception $e) {
            $error_message = sprintf(__('Error fetching branches: %s', 'wp-git-plugins'), $e->getMessage());
            error_log('WP Git Plugins: ' . $error_message);
            wp_send_json_error(['message' => $error_message]);
        }
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
}
