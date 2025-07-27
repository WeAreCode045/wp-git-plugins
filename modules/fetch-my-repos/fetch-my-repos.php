<?php
/**
 * Fetch My Repos Module
 *
 * Module Name: Fetch My Repos
 * Description: Adds a "Fetch My Repos" tab to the Add Repository section that allows fetching all repositories for a GitHub user
 * Version: 1.0.0
 * Author: WeAreCode045
 * Requires: 1.0.0
 * Tested up to: 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Fetch_My_Repos_Module {
    
    /**
     * Initialize the module
     */
    public function init() {
        // Ensure the DB class is loaded
        if (!class_exists('WPGP_Fetch_My_Repos_DB')) {
            require_once __DIR__ . '/class-fetch-my-repos-db.php';
        }
        // No longer add sidebar tab or content. Addon will be rendered in main container.
        add_action('wp_ajax_wpgp_fetch_user_repos', array($this, 'handle_fetch_user_repos'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    

    /**
     * Enqueue module scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook === 'toplevel_page_wp-git-plugins') {
            wp_enqueue_script(
                'wp-git-plugins-fetch-repos',
                WP_GIT_PLUGINS_URL . 'modules/fetch-my-repos/assets/js/fetch-my-repos.js',
                array('jquery'),
                '1.0.0',
                true
            );
            wp_localize_script('wp-git-plugins-fetch-repos', 'wpGitPluginsFetchRepos', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'ajax_nonce' => wp_create_nonce('wp_git_plugins_admin')
            ));
        }
    }
    
    /**
     * Render the Fetch My Repos addon as a vertical tab in the main container
     */
    public static function render_addon() {
        ?>
        <div id="fetch-my-repos-addon" class="wpgp-addon-tab" style="display: none;">
            <h3>Fetch My Repositories</h3>
            <p>This will fetch all repositories for the GitHub user configured in <strong>Settings</strong>.</p>
            <p>
                <button type="button" id="fetch-repos-btn" class="button button-primary">
                    Fetch Repositories
                    <span class="spinner"></span>
                </button>
            </p>
            <div id="fetch-repos-results" style="display: none;">
                <h4>Found Repositories</h4>
                <div id="repos-list"></div>
                <div id="repos-list-table-container"></div>
                <form id="repos-list-form" style="display:none;">
                    <p class="submit">
                        <button type="button" id="add-selected-repos" class="button button-secondary">
                            Add Selected Repositories
                            <span class="spinner"></span>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Handle AJAX request to fetch user repositories
     */
    public function handle_fetch_user_repos() {
        // Check nonce
        if (!wp_verify_nonce($_POST['_ajax_nonce'], 'wp_git_plugins_admin')) {
            wp_die('Security check failed');
        }

        // Get settings
        if (!class_exists('WP_Git_Plugins_Settings')) {
            wp_send_json_error('Settings class not found');
        }
        $settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
        $token = $settings->get_github_token();
        $username = $settings->get_github_username();

        if (empty($token) || empty($username)) {
            wp_send_json_error('GitHub username or token not set in settings.');
        }

        // Use the user's /users/:username/repos endpoint for private/public repos
        $github_api = WP_Git_Plugins_Github_API::get_instance($token);
        if (!method_exists($github_api, 'get_user_repositories')) {
            wp_send_json_error('API method get_user_repositories not found.');
        }
        $repos = $github_api->get_user_repositories($username);

        if (is_wp_error($repos)) {
            wp_send_json_error($repos->get_error_message());
        }

        if (empty($repos)) {
            wp_send_json_error('No repositories found for authenticated user.');
        }

        // Fetch all repos, get branches and latest commit per branch, return for display
        $repos_list = array();
        foreach ($repos as $repo) {
            $branches_url = $repo['branches_url'];
            $branches_url = preg_replace('/\{.*\}/', '', $branches_url); // remove {branch} template
            $branches_response = wp_remote_get($branches_url);
            $branches = json_decode(wp_remote_retrieve_body($branches_response), true);
            $branch_data = array();
            if (is_array($branches)) {
                foreach ($branches as $branch) {
                    $branch_name = $branch['name'];
                    $commit_url = $branch['commit']['url'];
                    $commit_response = wp_remote_get($commit_url);
                    $commit = json_decode(wp_remote_retrieve_body($commit_response), true);
                    $commit_date = isset($commit['commit']['committer']['date']) ? $commit['commit']['committer']['date'] : '';
                    $branch_data[] = array(
                        'name' => $branch_name,
                        'latest_commit' => $commit_date
                    );
                }
            }
            $repos_list[] = array(
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'description' => $repo['description'],
                'branches' => $branch_data,
                'private' => $repo['private'],
                'language' => $repo['language'],
                'html_url' => $repo['html_url']
            );
        }
        wp_send_json_success(array(
            'repositories' => $repos_list,
            'count' => count($repos_list)
        ));

    // End of method
}

    // No longer checking for WP plugin header
// End of class
    /**
     * Enqueue module scripts
     */
}

// Only initialize if this is being loaded as a module (not directly)
if (class_exists('WP_Git_Plugins_Modules')) {
    $wp_git_plugins_fetch_my_repos = new WP_Git_Plugins_Fetch_My_Repos_Module();
    $wp_git_plugins_fetch_my_repos->init();
} else {
    // If not loaded as a module, we can still initialize the class directly
    $wp_git_plugins_fetch_my_repos = new WP_Git_Plugins_Fetch_My_Repos_Module();
    $wp_git_plugins_fetch_my_repos->init();
}