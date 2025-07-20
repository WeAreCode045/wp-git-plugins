<?php
/**
 * Branch class for WP Git Plugins
 *
 * Handles all branch-specific functionality including getting GitHub branches,
 * switching branches, and branch management.
 *
 * @package    WP_Git_Plugins
 * @subpackage Branch
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @link       https://code045.nl/plugins/wp-git-plugins
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Branch {
    
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
     * GitHub token for API calls.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $github_token    The GitHub token.
     */
    private $github_token;

    /**
     * The single instance of the class.
     *
     * @since    1.0.0
     * @access   private
     * @var      WP_Git_Plugins_Branch    $instance    The single instance of the class.
     */
    private static $instance = null;

    /**
     * Get the singleton instance of the class.
     *
     * @since 1.0.0
     * @param WP_Git_Plugins_Settings $settings The settings instance
     * @return WP_Git_Plugins_Branch The singleton instance.
     */
    public static function get_instance($settings = null) {
        if (is_null(self::$instance)) {
            self::$instance = new self($settings);
        }
        return self::$instance;
    }

    /**
     * Initialize the class.
     *
     * @since 1.0.0
     * @param WP_Git_Plugins_Settings $settings The settings instance
     */
    private function __construct($settings = null) {
        $this->settings = $settings;
        $this->github_token = $this->settings ? $this->settings->get_github_token() : '';
        
        // Register AJAX handlers for branch operations
        add_action('wp_ajax_wp_git_plugins_get_branches', array($this, 'ajax_get_branches'));
        add_action('wp_ajax_wp_git_plugins_change_branch', array($this, 'ajax_change_branch'));
    }

    /**
     * Set the repository instance.
     *
     * @since 1.0.0
     * @param WP_Git_Plugins_Repository $repository The repository instance
     */
    public function set_repository($repository) {
        $this->repository = $repository;
    }

    /**
     * AJAX handler for getting repository branches.
     *
     * @since 1.0.0
     */
    public function ajax_get_branches() {
        try {
            WP_Git_Plugins::verify_ajax_request();
            
            $owner = isset($_POST['gh_owner']) ? sanitize_text_field($_POST['gh_owner']) : '';
            $repo = isset($_POST['gh_name']) ? sanitize_text_field($_POST['gh_name']) : '';
            
            if (empty($owner) || empty($repo)) {
                throw new Exception(__('Repository owner and name are required.', 'wp-git-plugins'));
            }
            
            $branches = $this->get_github_branches($owner, $repo);
            
            if (is_wp_error($branches)) {
                throw new Exception($branches->get_error_message());
            }
            
            wp_send_json_success(['branches' => $branches]);
            
        } catch (Exception $e) {
            error_log('WP Git Plugins - Error getting branches: ' . $e->getMessage());
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for changing a repository branch.
     *
     * @since 1.0.0
     */
    public function ajax_change_branch() {
        try {
            WP_Git_Plugins::verify_ajax_request();
            
            $repo_id = isset($_POST['repo_id']) ? intval($_POST['repo_id']) : 0;
            $branch = isset($_POST['branch']) ? sanitize_text_field($_POST['branch']) : '';
            
            if (empty($repo_id) || empty($branch)) {
                throw new Exception(__('Repository ID and branch are required.', 'wp-git-plugins'));
            }
            
            if (!$this->repository) {
                throw new Exception(__('Repository instance not available.', 'wp-git-plugins'));
            }
            
            $repo = $this->repository->get_local_repository($repo_id);
            if (is_wp_error($repo) || empty($repo)) {
                throw new Exception(__('Repository not found.', 'wp-git-plugins'));
            }
            
            $repo_data = [
                'url' => $repo['git_repo_url'],
                'owner' => $repo['gh_owner'],
                'name' => $repo['gh_name']
            ];
            
            $result = $this->change_repository_branch($repo_data, $branch);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }
            
            $updated_repo = $this->repository->get_local_repository($repo_id);
            
            wp_send_json_success([
                'message' => __('Branch changed successfully.', 'wp-git-plugins'),
                'repository' => $updated_repo,
                'redirect' => admin_url('admin.php?page=wp-git-plugins&branch_changed=1')
            ]);
            
        } catch (Exception $e) {
            $error_message = sprintf(__('Error changing branch: %s', 'wp-git-plugins'), $e->getMessage());
            error_log('WP Git Plugins: ' . $error_message);
            wp_send_json_error(['message' => $error_message]);
        }
    }

    /**
     * Get branches from GitHub repository.
     *
     * @since 1.0.0
     * @param string $owner Repository owner
     * @param string $repo Repository name
     * @return array|WP_Error Array of branches on success, WP_Error on failure
     */
    public function get_github_branches($owner, $repo) {
        if (empty($owner) || empty($repo)) {
            return new WP_Error('invalid_params', __('Repository owner and name are required.', 'wp-git-plugins'));
        }

        // Log the attempt
        error_log("WP Git Plugins - Getting branches for {$owner}/{$repo}");

        $api_url = "https://api.github.com/repos/{$owner}/{$repo}/branches";
        
        $headers = array(
            'User-Agent' => 'WP-Git-Plugins/1.0'
        );
        
        // Add authorization header if token is available
        if (!empty($this->github_token)) {
            $headers['Authorization'] = 'token ' . $this->github_token;
            error_log("WP Git Plugins - Using GitHub token for branch request");
        } else {
            error_log("WP Git Plugins - No GitHub token available for branch request");
        }

        $response = wp_remote_get($api_url, array(
            'headers' => $headers,
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            error_log("WP Git Plugins - GitHub API request failed: " . $response->get_error_message());
            return $response;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        error_log("WP Git Plugins - GitHub API response code: {$response_code}");

        if ($response_code !== 200) {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unknown error';
            error_log("WP Git Plugins - GitHub API error: {$error_message}");
            return new WP_Error('github_api_error', sprintf(__('GitHub API error: %s', 'wp-git-plugins'), $error_message));
        }

        $branches_data = json_decode($body, true);
        
        if (!is_array($branches_data)) {
            error_log("WP Git Plugins - Invalid branches data received from GitHub");
            return new WP_Error('invalid_response', __('Invalid response from GitHub API.', 'wp-git-plugins'));
        }

        $branches = array();
        foreach ($branches_data as $branch_data) {
            if (isset($branch_data['name'])) {
                $branches[] = $branch_data['name'];
            }
        }

        error_log("WP Git Plugins - Found " . count($branches) . " branches: " . implode(', ', $branches));
        return $branches;
    }

    /**
     * Change repository branch.
     *
     * @since 1.0.0
     * @param array $repo_data Repository data
     * @param string $new_branch New branch name
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function change_repository_branch($repo_data, $new_branch) {
        if (!is_array($repo_data) || empty($repo_data['url']) || empty($repo_data['owner']) || empty($repo_data['name'])) {
            return new WP_Error('invalid_repo_data', __('Invalid repository data provided.', 'wp-git-plugins'));
        }

        if (!$this->repository) {
            return new WP_Error('no_repository', __('Repository instance not available.', 'wp-git-plugins'));
        }

        // Get the repository by owner/name
        $repo = $this->repository->get_local_repository_by_name($repo_data['owner'], $repo_data['name']);
        if (empty($repo)) {
            return new WP_Error('repo_not_found', __('Repository not found in local database.', 'wp-git-plugins'));
        }

        $plugin_dir = WP_PLUGIN_DIR . '/' . $repo['plugin_slug'];

        // Delete the existing plugin folder
        if (is_dir($plugin_dir)) {
            WP_Git_Plugins::rrmdir($plugin_dir);
        }

        // Prepare clone URL
        $clone_url = sprintf('https://github.com/%s/%s.git', $repo_data['owner'], $repo_data['name']);
        if (!empty($repo['is_private']) && !empty($this->github_token)) {
            $clone_url = sprintf('https://%s@github.com/%s/%s.git', $this->github_token, $repo_data['owner'], $repo_data['name']);
        }

        // Clone the selected branch into the plugins folder
        $command = sprintf(
            'git clone --single-branch --branch %s %s %s 2>&1',
            escapeshellarg($new_branch),
            escapeshellarg($clone_url),
            escapeshellarg($plugin_dir)
        );
        $output = shell_exec($command);

        // Check if clone succeeded
        if (!is_dir($plugin_dir) || !file_exists($plugin_dir)) {
            return new WP_Error('git_clone_failed', __('Failed to clone repository branch.', 'wp-git-plugins'));
        }

        // Update the repository record with the new branch - use repository's database access
        $db = WP_Git_Plugins_DB::get_instance();
        $result = $db->update_repo($repo['id'], [
            'branch' => $new_branch,
            'updated_at' => current_time('mysql')
        ]);
        if (!$result) {
            return new WP_Error('db_update_failed', __('Failed to update repository record.', 'wp-git-plugins'));
        }

        return true;
    }

}
