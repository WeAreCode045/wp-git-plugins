<?php
class WP_Git_Plugins_Repository {
    private $github_token;
    private $repositories_option = 'wp_git_plugins_repositories';
    private $debug_log = [];
    
    private function log($message, $data = null) {
        $log_entry = [
            'time' => current_time('mysql'),
            'message' => $message,
            'data' => $data
        ];
        $this->debug_log[] = $log_entry;
        
        // Also log to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('WP Git Plugins: ' . $message);
            if ($data !== null) {
                error_log(print_r($data, true));
            }
        }
    }
    
    public function __construct() {
        $this->github_token = get_option('wp_git_plugins_github_token', '');
    }
    
    public function add_repository($repo_url, $is_private = false, $branch = 'main') {
        $repositories = $this->get_repositories();
        
        // Check if repository already exists
        foreach ($repositories as $repo) {
            if ($repo['url'] === $repo_url) {
                return new WP_Error('repo_exists', __('This repository is already added.', 'wp-git-plugins'));
            }
        }
        
        $repo_data = $this->parse_github_url($repo_url);
        if (is_wp_error($repo_data)) {
            return $repo_data;
        }
        
        $repository = [
            'url' => $repo_data['url'],
            'name' => $repo_data['name'],
            'owner' => $repo_data['owner'],
            'is_private' => $is_private,
            'provider' => 'github',
            'added_date' => current_time('mysql'),
            'installed_version' => '',
            'latest_version' => '',
            'branch' => $branch,
            'last_checked' => current_time('mysql'),
            'last_updated' => ''
        ];
        
        // Add to repositories list
        $repositories[] = $repository;
        update_option($this->repositories_option, $repositories);
        
        // Install and activate the plugin
        $install_result = $this->install_plugin($repo_url);
        
        if (is_wp_error($install_result)) {
            // If installation fails, remove the repository from the list
            $this->remove_repository($repo_url);
            return $install_result;
        }
        
        return true;
    }
    
    public function get_repositories() {
        return get_option($this->repositories_option, []);
    }
    
    public function get_repository($url) {
        $repositories = $this->get_repositories();
        
        foreach ($repositories as $repo) {
            if ($repo['url'] === $url) {
                return $repo;
            }
        }
        
        return false;
    }
    
    public function update_repository($url, $data) {
        $repositories = $this->get_repositories();
        $updated = false;
        
        foreach ($repositories as &$repo) {
            if ($repo['url'] === $url) {
                $repo = array_merge($repo, $data);
                $updated = true;
                break;
            }
        }
        
        if ($updated) {
            update_option($this->repositories_option, $repositories);
        }
        
        return $updated;
    }
    
    public function remove_repository($url) {
        $repositories = $this->get_repositories();
        $count = count($repositories);
        
        $repositories = array_filter($repositories, function($repo) use ($url) {
            return $repo['url'] !== $url;
        });
        
        if (count($repositories) < $count) {
            update_option($this->repositories_option, array_values($repositories));
            return true;
        }
        
        return false;
    }
    
    public function parse_github_url($url) {
        $pattern = '#^(?:https?://|git@)?(?:www\.)?github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?$#';
        
        if (preg_match($pattern, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name' => rtrim($matches[2], '.git'),
                'url' => 'https://github.com/' . $matches[1] . '/' . rtrim($matches[2], '.git')
            ];
        }
        
        return new WP_Error('invalid_url', __('Invalid GitHub repository URL', 'wp-git-plugins'));
    }
    
    public function check_for_updates() {
        $repositories = $this->get_repositories();
        $updates = [];
        
        foreach ($repositories as $repo) {
            $latest_version = $this->get_github_latest_version($repo);
            
            if (!is_wp_error($latest_version) && version_compare($latest_version, $repo['installed_version'], '>')) {
                $updates[] = [
                    'name' => $repo['name'],
                    'current_version' => $repo['installed_version'],
                    'new_version' => $latest_version,
                    'url' => $repo['url']
                ];
            }
            
            // Update last checked time
            $this->update_repository($repo['url'], [
                'latest_version' => $latest_version,
                'last_checked' => current_time('mysql')
            ]);
        }
        
        return $updates;
    }
    
    private function get_github_latest_version($repo) {
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            $repo['owner'],
            $repo['name']
        );
        
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
        if ($response_code === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            return isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : '0.0.1';
        } elseif ($response_code === 404) {
            // If no releases found, return the branch name
            return !empty($repo['branch']) ? $repo['branch'] : 'main';
        }
        
        return new WP_Error('github_api_error', sprintf(
            __('GitHub API error: %s', 'wp-git-plugins'),
            wp_remote_retrieve_response_message($response)
        ));
    }
    
    public function install_plugin($repo_url) {
        $this->log('Starting plugin installation', ['repo_url' => $repo_url]);
        
        $repo = $this->get_repository($repo_url);
        if (!$repo) {
            $error = new WP_Error('repo_not_found', __('Repository not found', 'wp-git-plugins'));
            $this->log('Repository not found', ['error' => $error->get_error_message()]);
            return $error;
        }
        
        $this->log('Repository found', ['repo' => $repo]);

        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        
        WP_Filesystem();
        
        $repo_data = $this->parse_github_url($repo_url);
        if (is_wp_error($repo_data)) {
            return $repo_data;
        }
        
        $branch = !empty($repo['branch']) ? $repo['branch'] : 'main';
        $target_dir = WP_PLUGIN_DIR . '/' . $repo_data['name'];
        $clone_url = !empty($repo['is_private']) 
            ? sprintf('https://%s@github.com/%s/%s.git', $this->github_token, $repo_data['owner'], $repo_data['name'])
            : sprintf('https://github.com/%s/%s.git', $repo_data['owner'], $repo_data['name']);
            
        $this->log('Preparing to clone repository', [
            'branch' => $branch,
            'target_dir' => $target_dir,
            'clone_url' => $clone_url,
            'is_private' => !empty($repo['is_private'])
        ]);
        
        // Check if Git is available
        if (!function_exists('shell_exec')) {
            $error = new WP_Error('shell_exec_disabled', __('The shell_exec() function is disabled on this server.', 'wp-git-plugins'));
            $this->log('shell_exec is disabled', ['error' => $error->get_error_message()]);
            return $error;
        }
        
        $git_path = shell_exec('which git');
        if (!$git_path) {
            $error = new WP_Error('git_not_available', __('Git is not available on this server. Please install Git to use this feature.', 'wp-git-plugins'));
            $this->log('Git not found', ['error' => $error->get_error_message()]);
            return $error;
        }
        
        $this->log('Git found', ['path' => trim($git_path)]);
        
        // Create plugins directory if it doesn't exist
        if (!file_exists(WP_PLUGIN_DIR)) {
            wp_mkdir_p(WP_PLUGIN_DIR);
        }
        
        // Check if directory exists and is a git repository
        if (file_exists($target_dir)) {
            $this->log('Target directory exists', ['path' => $target_dir]);
            
            if (file_exists($target_dir . '/.git')) {
                $this->log('Updating existing Git repository', [
                    'target_dir' => $target_dir,
                    'branch' => $branch
                ]);
                
                $command = sprintf(
                    'cd %s && git fetch origin %s && git checkout %s && git pull origin %s 2>&1',
                    escapeshellarg($target_dir),
                    escapeshellarg($branch),
                    escapeshellarg($branch),
                    escapeshellarg($branch)
                );
                $this->log('Executing Git command', ['command' => $command]);
                
                $output = shell_exec($command);
                $this->log('Git command output', ['output' => $output]);
            } else {
                $error = new WP_Error('directory_exists', 
                    sprintf(__('Directory %s already exists and is not a Git repository.', 'wp-git-plugins'), $target_dir)
                );
                $this->log('Directory exists but is not a Git repository', [
                    'error' => $error->get_error_message(),
                    'target_dir' => $target_dir
                ]);
                return $error;
            }
        } else {
            $this->log('Cloning new repository', [
                'clone_url' => $clone_url,
                'branch' => $branch,
                'target_dir' => $target_dir
            ]);
            
            $command = sprintf(
                'git clone --branch %s --single-branch --depth 1 %s %s 2>&1',
                escapeshellarg($branch),
                escapeshellarg($clone_url),
                escapeshellarg($target_dir)
            );
            $this->log('Executing Git clone command', ['command' => $command]);
            
            $output = shell_exec($command);
            $this->log('Git clone output', ['output' => $output]);
        }
        
        if ($output === null) {
            $error = new WP_Error('git_error', __('Failed to execute Git command', 'wp-git-plugins'));
            $this->log('Git command failed', [
                'error' => $error->get_error_message(),
                'output' => $output
            ]);
            return $error;
        }
        
        $this->log('Git operation completed', [
            'output' => $output,
            'target_dir' => $target_dir
        ]);
        
        // Find the main plugin file
        $this->log('Looking for main plugin file', ['repo_name' => $repo_data['name']]);
        $plugin_slug = $this->get_plugin_slug($repo_data['name']);
        
        if ($plugin_slug) {
            $this->log('Found main plugin file', ['plugin_slug' => $plugin_slug]);
            
            // Get the current version from the plugin file
            $plugin_file = WP_PLUGIN_DIR . '/' . $plugin_slug;
            $this->log('Loading plugin data', ['plugin_file' => $plugin_file]);
            
            $plugin_data = get_plugin_data($plugin_file);
            $version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.1';
            
            $this->log('Plugin data loaded', [
                'version' => $version,
                'plugin_data' => $plugin_data
            ]);
            
            // Activate the plugin
            $this->log('Activating plugin', ['plugin_slug' => $plugin_slug]);
            $activation_result = activate_plugin($plugin_slug);
            
            if (is_wp_error($activation_result)) {
                $this->log('Failed to activate plugin', [
                    'error' => $activation_result->get_error_message(),
                    'plugin_slug' => $plugin_slug
                ]);
            } else {
                $this->log('Plugin activated successfully', ['plugin_slug' => $plugin_slug]);
            }
            
            // Update repository data
            $update_data = [
                'installed_version' => $version,
                'last_updated' => current_time('mysql')
            ];
            
            $this->log('Updating repository data', $update_data);
            $this->update_repository($repo_url, $update_data);
            
            $this->log('Installation completed successfully');
            return true;
        }
        
        return new WP_Error('plugin_not_found', __('Could not determine the main plugin file.', 'wp-git-plugins'));
    }
    
    public function get_repository_branches($repo_url) {
        $repo_data = $this->parse_github_url($repo_url);
        if (is_wp_error($repo_data)) {
            return $repo_data;
        }

        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/branches',
            $repo_data['owner'],
            $repo_data['name']
        );

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
                sprintf(__('GitHub API error: %s', 'wp-git-plugins'), wp_remote_retrieve_response_message($response))
            );
        }

        $branches = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($branches)) {
            return new WP_Error('invalid_response', __('Invalid response from GitHub API', 'wp-git-plugins'));
        }

        $branch_list = [];
        foreach ($branches as $branch) {
            if (isset($branch['name'])) {
                $branch_list[] = $branch['name'];
            }
        }

        return $branch_list;
    }

    public function get_debug_log() {
        return $this->debug_log;
    }
    
    public function clear_debug_log() {
        $this->debug_log = [];
        return true;
    }
    
    private function get_plugin_slug($repo_name) {
        $this->log('Finding plugin slug', ['repo_name' => $repo_name]);
        $plugins = get_plugins();
        $this->log('All plugins', array_keys($plugins));
        $plugins = get_plugins();
        
        // Try exact match first
        $this->log('Trying exact match');
        foreach ($plugins as $plugin_file => $plugin_data) {
            $dir = dirname($plugin_file);
            if ($dir === $repo_name || $dir === $repo_name . '-main') {
                $this->log('Exact match found', [
                    'plugin_file' => $plugin_file,
                    'dir' => $dir,
                    'repo_name' => $repo_name
                ]);
                return $plugin_file;
            }
        }
        
        // Try case-insensitive match
        $this->log('Trying case-insensitive match');
        $repo_name_lower = strtolower($repo_name);
        foreach ($plugins as $plugin_file => $plugin_data) {
            $dir = strtolower(dirname($plugin_file));
            if ($dir === $repo_name_lower || $dir === $repo_name_lower . '-main') {
                $this->log('Case-insensitive match found', [
                    'plugin_file' => $plugin_file,
                    'dir' => $dir,
                    'repo_name_lower' => $repo_name_lower
                ]);
                return $plugin_file;
            }
        }
        
        // Try to find any PHP file in the directory that looks like a plugin
        $plugin_dir = WP_PLUGIN_DIR . '/' . $repo_name;
        $this->log('Checking plugin directory', ['path' => $plugin_dir]);
        
        if (is_dir($plugin_dir)) {
            $files = glob($plugin_dir . '/*.php');
            $this->log('Found PHP files in directory', $files);
            
            if (!empty($files)) {
                // Look for a file with a plugin header
                foreach ($files as $file) {
                    $file_data = get_plugin_data($file);
                    if (!empty($file_data['Name'])) {
                        $result = $repo_name . '/' . basename($file);
                        $this->log('Found plugin with valid header', [
                            'file' => $file,
                            'result' => $result,
                            'plugin_data' => $file_data
                        ]);
                        return $result;
                    }
                }
                
                // If no plugin header found, return the first PHP file
                $result = $repo_name . '/' . basename($files[0]);
                $this->log('No valid plugin header found, using first PHP file', [
                    'result' => $result
                ]);
                return $result;
            }
        } else {
            $this->log('Plugin directory does not exist', ['path' => $plugin_dir]);
        }
        
        // Try the same with -main suffix
        $plugin_dir_main = WP_PLUGIN_DIR . '/' . $repo_name . '-main';
        $this->log('Checking plugin directory with -main suffix', ['path' => $plugin_dir_main]);
        
        if (is_dir($plugin_dir_main)) {
            $files = glob($plugin_dir_main . '/*.php');
            $this->log('Found PHP files in -main directory', $files);
            
            if (!empty($files)) {
                foreach ($files as $file) {
                    $file_data = get_plugin_data($file);
                    if (!empty($file_data['Name'])) {
                        $result = $repo_name . '-main/' . basename($file);
                        $this->log('Found plugin with valid header in -main directory', [
                            'file' => $file,
                            'result' => $result,
                            'plugin_data' => $file_data
                        ]);
                        return $result;
                    }
                }
                
                $result = $repo_name . '-main/' . basename($files[0]);
                $this->log('No valid plugin header found in -main directory, using first PHP file', [
                    'result' => $result
                ]);
                return $result;
            }
        } else {
            $this->log('Plugin directory with -main suffix does not exist', ['path' => $plugin_dir_main]);
        }
        
        // If still not found, check all plugins for a matching name in the header
        $this->log('Searching all plugins for matching name in header');
        foreach ($plugins as $plugin_file => $plugin_data) {
            if (stripos($plugin_data['Name'], $repo_name) !== false) {
                $this->log('Found matching plugin by name in header', [
                    'plugin_file' => $plugin_file,
                    'plugin_name' => $plugin_data['Name'],
                    'repo_name' => $repo_name
                ]);
                return $plugin_file;
            }
        }
        
        return false;
    }
}
