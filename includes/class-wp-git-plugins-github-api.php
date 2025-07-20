<?php
/**
 * GitHub API class for WP Git Plugins
 *
 * Handles all GitHub API interactions including repository information,
 * branches, releases, and rate limiting.
 *
 * @package    WP_Git_Plugins
 * @subpackage GitHub_API
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @link       https://code045.nl/plugins/wp-git-plugins
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Github_API {
    
    /**
     * The single instance of the class.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Github_API    $instance    The single instance of the class.
     */
    private static $instance = null;
    
    /**
     * GitHub API base URL
     *
     * @since 1.0.0
     * @var string
     */
    private $api_base = 'https://api.github.com';
    
    /**
     * GitHub token for authentication
     *
     * @since 1.0.0
     * @var string
     */
    private $github_token;
    
    /**
     * Error handler instance
     *
     * @since 1.0.0
     * @var WP_Git_Plugins_Error_Handler
     */
    private $error_handler;

    /**
     * Get the singleton instance of the class.
     *
     * @since 1.0.0
     * @param string $github_token Optional GitHub token
     * @return WP_Git_Plugins_Github_API The singleton instance.
     */
    public static function get_instance($github_token = '') {
        if (is_null(self::$instance)) {
            self::$instance = new self($github_token);
        } elseif (!empty($github_token)) {
            self::$instance->set_github_token($github_token);
        }
        return self::$instance;
    }

    /**
     * Initialize the class.
     *
     * @since 1.0.0
     * @param string $github_token Optional GitHub token
     */
    private function __construct($github_token = '') {
        $this->github_token = $github_token;
        $this->error_handler = WP_Git_Plugins_Error_Handler::instance();
    }
    
    /**
     * Set the GitHub token
     *
     * @since 1.0.0
     * @param string $token GitHub token
     */
    public function set_github_token($token) {
        $this->github_token = sanitize_text_field($token);
    }
    
    /**
     * Get the GitHub token
     *
     * @since 1.0.0
     * @return string GitHub token
     */
    public function get_github_token() {
        return $this->github_token;
    }
    
    /**
     * Build GitHub API URL
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name  
     * @param string $endpoint API endpoint (optional)
     * @return string Full API URL
     */
    public function build_api_url($owner, $repo, $endpoint = '') {
        $url = $this->api_base . '/repos/' . $owner . '/' . $repo;
        if (!empty($endpoint)) {
            $url .= '/' . ltrim($endpoint, '/');
        }
        return $url;
    }
    
    /**
     * Get request headers for API calls
     *
     * @since 1.0.0
     * @param bool $include_auth Whether to include authorization header
     * @return array Request headers
     */
    private function get_request_headers($include_auth = true) {
        $headers = [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WP-Git-Plugins/' . WP_GIT_PLUGINS_VERSION
        ];
        
        if ($include_auth && !empty($this->github_token)) {
            $headers['Authorization'] = 'token ' . $this->github_token;
        }
        
        return $headers;
    }
    
    /**
     * Make a request to GitHub API
     *
     * @since 1.0.0
     * @param string $url API URL
     * @param bool $include_auth Whether to include authorization
     * @param int $timeout Request timeout in seconds
     * @return array|WP_Error Response data or WP_Error on failure
     */
    private function make_request($url, $include_auth = true, $timeout = 30) {
        $args = [
            'headers' => $this->get_request_headers($include_auth),
            'timeout' => $timeout
        ];
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response)) {
            $this->error_handler->log_error('GitHub API request failed: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Handle rate limiting
        if ($response_code === 403) {
            $remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');
            if ($remaining === '0') {
                return new WP_Error(
                    'rate_limit_exceeded',
                    __('GitHub API rate limit exceeded. Please try again later or add a GitHub token.', 'wp-git-plugins')
                );
            }
        }
        
        // Handle other error codes
        if ($response_code !== 200) {
            $error_message = sprintf(
                __('GitHub API returned status code %d: %s', 'wp-git-plugins'),
                $response_code,
                wp_remote_retrieve_response_message($response)
            );
            
            // Try to get more specific error from response body
            $error_data = json_decode($response_body, true);
            if (is_array($error_data) && isset($error_data['message'])) {
                $error_message .= ' - ' . $error_data['message'];
            }
            
            $this->error_handler->log_error('GitHub API error: ' . $error_message);
            return new WP_Error('github_api_error', $error_message);
        }
        
        $data = json_decode($response_body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error(
                'json_decode_error',
                __('Failed to decode GitHub API response.', 'wp-git-plugins')
            );
        }
        
        return $data;
    }
    
    /**
     * Get repository information
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|WP_Error Repository data or WP_Error on failure
     */
    public function get_repository($owner, $repo) {
        $transient_key = 'wpgp_repo_' . md5($owner . $repo);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->build_api_url($owner, $repo);
        $data = $this->make_request($url);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        // Cache for 1 hour
        set_transient($transient_key, $data, HOUR_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Get repository branches
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|WP_Error Array of branch names or WP_Error on failure
     */
    public function get_branches($owner, $repo) {
        $transient_key = 'wpgp_branches_' . md5($owner . $repo);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->build_api_url($owner, $repo, 'branches');
        $data = $this->make_request($url);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        if (!is_array($data) || empty($data)) {
            return new WP_Error(
                'no_branches',
                __('No branches found or invalid response from GitHub.', 'wp-git-plugins')
            );
        }
        
        $branches = array_map(function($branch) {
            return $branch['name'];
        }, $data);
        
        // Cache for 1 hour
        set_transient($transient_key, $branches, HOUR_IN_SECONDS);
        
        return $branches;
    }
    
    /**
     * Get latest release information
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|WP_Error Release data or WP_Error on failure
     */
    public function get_latest_release($owner, $repo) {
        $transient_key = 'wpgp_release_' . md5($owner . $repo);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $url = $this->build_api_url($owner, $repo, 'releases/latest');
        $data = $this->make_request($url);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        // Cache for 30 minutes
        set_transient($transient_key, $data, 30 * MINUTE_IN_SECONDS);
        
        return $data;
    }
    
    /**
     * Get latest version from releases or commit
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch Branch name (default: 'main')
     * @return string|WP_Error Version string or WP_Error on failure
     */
    public function get_latest_version($owner, $repo, $branch = 'main') {
        $transient_key = 'wpgp_version_' . md5($owner . $repo . $branch);
        $cached = get_transient($transient_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // First try to get version from plugin header
        $version = $this->get_version_from_plugin_header($owner, $repo, $branch);
        
        if (!is_wp_error($version)) {
            set_transient($transient_key, $version, HOUR_IN_SECONDS);
            return $version;
        }
        
        // Fallback to latest release
        $release = $this->get_latest_release($owner, $repo);
        
        if (!is_wp_error($release) && isset($release['tag_name'])) {
            $version = ltrim($release['tag_name'], 'v');
            set_transient($transient_key, $version, HOUR_IN_SECONDS);
            return $version;
        }
        
        // Final fallback to commit hash
        $commit = $this->get_latest_commit($owner, $repo, $branch);
        
        if (!is_wp_error($commit) && isset($commit['sha'])) {
            $version = substr($commit['sha'], 0, 7);
            set_transient($transient_key, $version, HOUR_IN_SECONDS);
            return $version;
        }
        
        return new WP_Error(
            'no_version_found',
            __('Could not determine the latest version from GitHub.', 'wp-git-plugins')
        );
    }
    
    /**
     * Get version from plugin header file
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch Branch name
     * @return string|WP_Error Version string or WP_Error on failure
     */

    public function get_version_from_plugin_header($owner, $repo, $branch = 'main') {
        error_log("WP Git Plugins - Getting version for {$owner}/{$repo} on branch {$branch}");
        
        // First, try to get the repository structure to find PHP files
        $repo_contents = $this->get_repository_contents($owner, $repo, '', $branch);
        
        if (!is_wp_error($repo_contents)) {
            error_log('WP Git Plugins - Repository contents found, looking for PHP files');
            
            // Look for PHP files in the root directory
            $php_files = [];
            foreach ($repo_contents as $item) {
                if (isset($item['name']) && substr($item['name'], -4) === '.php') {
                    $php_files[] = $item['name'];
                }
            }
            
            error_log('WP Git Plugins - Found PHP files: ' . implode(', ', $php_files));
            
            // Try each PHP file found in the repository
            foreach ($php_files as $php_file) {
                $version = $this->get_version_from_file($owner, $repo, $php_file, $branch);
                if (!is_wp_error($version)) {
                    error_log("WP Git Plugins - Found version {$version} in file {$php_file}");
                    return $version;
                }
            }
        } else {
            error_log('WP Git Plugins - Could not get repository contents: ' . $repo_contents->get_error_message());
        }
        
        // Fallback to trying common file patterns
        $possible_files = [
            $repo . '.php',                    // repo-name.php
            'index.php',                       // index.php
            str_replace('-', '_', $repo) . '.php', // repo_name.php (underscores)
            'plugin.php',                      // plugin.php
            basename($repo) . '.php',          // Just in case repo has a path
            'main.php',                        // main.php
            strtolower($repo) . '.php'         // lowercase version
        ];
        
        error_log('WP Git Plugins - Trying fallback file patterns: ' . implode(', ', $possible_files));
        
        foreach ($possible_files as $file_name) {
            $version = $this->get_version_from_file($owner, $repo, $file_name, $branch);
            if (!is_wp_error($version)) {
                error_log("WP Git Plugins - Found version {$version} in fallback file {$file_name}");
                return $version;
            }
        }
        
        return new WP_Error(
            'plugin_file_not_found',
            sprintf(
                __('Plugin header file not found in %s/%s on branch %s. Tried repository contents and common file patterns.', 'wp-git-plugins'),
                $owner,
                $repo,
                $branch
            )
        );
    }
    
    /**
     * Get version from a specific file
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name  
     * @param string $file_name File name to check
     * @param string $branch Branch name
     * @return string|WP_Error Version string or WP_Error on failure
     */
    private function get_version_from_file($owner, $repo, $file_name, $branch = 'main') {
        $plugin_file_url = sprintf(
            'https://raw.githubusercontent.com/%s/%s/%s/%s',
            $owner,
            $repo,
            $branch,
            $file_name
        );
        
        error_log("WP Git Plugins - Checking file: {$plugin_file_url}");
        
        $args = [
            'timeout' => 15,
            'user-agent' => 'WP-Git-Plugins/1.0'
        ];
        
        // Add authorization header if we have a token
        if (!empty($this->github_token)) {
            $args['headers'] = [
                'Authorization' => 'token ' . $this->github_token
            ];
        }
        
        $response = wp_remote_get($plugin_file_url, $args);
        
        if (is_wp_error($response)) {
            error_log("WP Git Plugins - Request error for {$file_name}: " . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        error_log("WP Git Plugins - Response code for {$file_name}: {$response_code}");
        
        if ($response_code !== 200) {
            return new WP_Error('file_not_found', "File {$file_name} not found (HTTP {$response_code})");
        }
        
        $content = wp_remote_retrieve_body($response);
        
        if (empty($content)) {
            return new WP_Error('empty_file', "File {$file_name} is empty");
        }
        
        // Look for version in the plugin header - try multiple patterns
        $version_patterns = [
            '/Version:\s*([^\n\r\*]+)/i',           // Standard: Version: 1.0.0
            '/\*\s*Version:\s*([^\n\r\*]+)/i',      // With comment: * Version: 1.0.0
            '/@version\s+([^\n\r\*]+)/i',           // PHPDoc: @version 1.0.0
            '/define\s*\(\s*[\'"].*VERSION[\'"],\s*[\'"]([^\'"]+)[\'"]/i' // define('VERSION', '1.0.0')
        ];
        
        foreach ($version_patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $version = trim($matches[1]);
                error_log("WP Git Plugins - Found version '{$version}' in {$file_name} using pattern: {$pattern}");
                return $version;
            }
        }
        
        error_log("WP Git Plugins - No version found in {$file_name}");
        return new WP_Error('version_not_found', "No version header found in {$file_name}");
    }
    
    /**
     * Get repository contents
     *
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $path Path within repository
     * @param string $branch Branch name
     * @return array|WP_Error Repository contents or WP_Error on failure
     */
    public function get_repository_contents($owner, $repo, $path = '', $branch = 'main') {
        $url = $this->build_api_url($owner, $repo, 'contents/' . $path);
        if ($branch !== 'main') {
            $url .= '?ref=' . $branch;
        }
        
        $response = $this->make_request($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        return $response;
    }
    
    /**
     * Get latest commit information
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch Branch name
     * @return array|WP_Error Commit data or WP_Error on failure
     */
    public function get_latest_commit($owner, $repo, $branch = 'main') {
        $url = $this->build_api_url($owner, $repo, 'commits/' . $branch);
        return $this->make_request($url);
    }
    
    /**
     * Get download URL for repository
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param string $branch Branch name (default: 'main')
     * @param bool $is_private Whether repository is private
     * @return string Download URL
     */
    public function get_download_url($owner, $repo, $branch = 'main', $is_private = false) {
        if ($is_private && !empty($this->github_token)) {
            return sprintf(
                'https://api.github.com/repos/%s/%s/zipball/%s?access_token=%s',
                $owner,
                $repo,
                $branch,
                $this->github_token
            );
        }
        
        return sprintf(
            'https://github.com/%s/%s/archive/refs/heads/%s.zip',
            $owner,
            $repo,
            $branch
        );
    }
    
    /**
     * Get clone URL for repository
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @param bool $is_private Whether repository is private
     * @return string Clone URL
     */
    public function get_clone_url($owner, $repo, $is_private = false) {
        if ($is_private && !empty($this->github_token)) {
            return sprintf(
                'https://%s@github.com/%s/%s.git',
                $this->github_token,
                $owner,
                $repo
            );
        }
        
        return sprintf(
            'https://github.com/%s/%s.git',
            $owner,
            $repo
        );
    }
    
    /**
     * Check GitHub API rate limit
     *
     * @since 1.0.0
     * @return array|WP_Error Rate limit information or WP_Error on failure
     */
    public function check_rate_limit() {
        $url = $this->api_base . '/rate_limit';
        $data = $this->make_request($url);
        
        if (is_wp_error($data)) {
            return $data;
        }
        
        return $data;
    }
    
    /**
     * Validate repository exists and is accessible
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    public function validate_repository($owner, $repo) {
        $repository_data = $this->get_repository($owner, $repo);
        
        if (is_wp_error($repository_data)) {
            return $repository_data;
        }
        
        return true;
    }
    
    /**
     * Parse GitHub URL to extract owner and repository name
     *
     * @since 1.0.0
     * @param string $url GitHub repository URL
     * @return array|WP_Error Array with 'owner' and 'name' keys, or WP_Error on failure
     */
    public static function parse_github_url($url) {
        $pattern = '#^(?:https?://|git@)?(?:www\.)?github\.com[:/]([^/]+)/([^/]+?)(?:\.git)?/?$#i';
        
        if (preg_match($pattern, $url, $matches)) {
            return [
                'owner' => $matches[1],
                'name' => rtrim($matches[2], '.git')
            ];
        }
        
        return new WP_Error(
            'invalid_github_url',
            __('Invalid GitHub repository URL. Please use a valid GitHub URL.', 'wp-git-plugins')
        );
    }
    
    /**
     * Clear all cached GitHub data
     *
     * @since 1.0.0
     * @param string $owner Optional - clear cache for specific owner
     * @param string $repo Optional - clear cache for specific repository
     */
    public function clear_cache($owner = '', $repo = '') {
        global $wpdb;
        
        if (!empty($owner) && !empty($repo)) {
            // Clear cache for specific repository
            $pattern = 'wpgp_' . md5($owner . $repo) . '%';
        } elseif (!empty($owner)) {
            // Clear cache for specific owner
            $pattern = 'wpgp_%' . md5($owner) . '%';
        } else {
            // Clear all GitHub-related cache
            $pattern = 'wpgp_%';
        }
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            $pattern
        ));
    }
    
    /**
     * Get all repositories for a GitHub user
     *
     * @since 1.0.0
     * @param string $username GitHub username
     * @param int $per_page Number of repositories per page (max 100)
     * @param int $page Page number for pagination
     * @return array|WP_Error Array of repositories or error
     */
    public function get_user_repositories($username, $per_page = 100, $page = 1) {
        if (empty($username)) {
            return new WP_Error('invalid_username', 'Username is required');
        }
        
        // Sanitize username
        $username = sanitize_text_field($username);
        
        // Build API URL for user repositories
        $url = $this->api_base . '/users/' . $username . '/repos';
        $url = add_query_arg(array(
            'type' => 'all',
            'sort' => 'updated',
            'direction' => 'desc',
            'per_page' => min($per_page, 100), // GitHub max is 100
            'page' => max($page, 1)
        ), $url);
        
        // Check cache first
        $cache_key = 'wpgp_user_repos_' . md5($username . $per_page . $page);
        $cached_data = get_transient($cache_key);
        
        if (false !== $cached_data) {
            return $cached_data;
        }
        
        // Make API request
        $response = $this->make_request($url, true, 30);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        // Parse response
        $response_body = wp_remote_retrieve_body($response);
        $repositories = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse GitHub API response');
        }
        
        if (!is_array($repositories)) {
            // Check for API error message
            if (isset($repositories['message'])) {
                return new WP_Error('github_api_error', $repositories['message']);
            }
            return new WP_Error('invalid_response', 'Invalid response from GitHub API');
        }
        
        // Format repositories for consistency
        $formatted_repos = array();
        foreach ($repositories as $repo) {
            $formatted_repos[] = array(
                'id' => $repo['id'],
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'description' => $repo['description'],
                'private' => $repo['private'],
                'clone_url' => $repo['clone_url'],
                'ssh_url' => $repo['ssh_url'],
                'html_url' => $repo['html_url'],
                'language' => $repo['language'],
                'default_branch' => $repo['default_branch'],
                'created_at' => $repo['created_at'],
                'updated_at' => $repo['updated_at'],
                'size' => $repo['size'],
                'stargazers_count' => $repo['stargazers_count'],
                'forks_count' => $repo['forks_count'],
                'archived' => $repo['archived'],
                'disabled' => $repo['disabled']
            );
        }
        
        // Cache for 10 minutes
        set_transient($cache_key, $formatted_repos, 600);
        
        return $formatted_repos;
    }
}