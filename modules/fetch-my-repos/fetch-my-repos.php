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
        add_action('wp_git_plugins_add_repository_tabs', array($this, 'add_fetch_tab'));
        add_action('wp_git_plugins_add_repository_content', array($this, 'add_fetch_content'));
        add_action('wp_ajax_wpgp_fetch_user_repos', array($this, 'handle_fetch_user_repos'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add the "Fetch My Repos" tab
     */
    public function add_fetch_tab() {
        echo '<li><a href="#fetch-repos" class="nav-tab">Fetch My Repos</a></li>';
    }
    
    /**
     * Add the "Fetch My Repos" content
     */
    public function add_fetch_content() {
        ?>
        <div id="fetch-repos" class="tab-content" style="display: none;">
            <h3>Fetch My Repositories</h3>
            <p>Enter a GitHub username to fetch all public repositories for that user.</p>
            
            <form id="fetch-repos-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="github-username">GitHub Username</label>
                        </th>
                        <td>
                            <input type="text" id="github-username" name="github_username" class="regular-text" required />
                            <p class="description">Enter the GitHub username to fetch repositories for</p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        Fetch Repositories
                        <span class="spinner"></span>
                    </button>
                </p>
            </form>
            
            <div id="fetch-repos-results" style="display: none;">
                <h4>Found Repositories</h4>
                <div id="repos-list"></div>
                <p class="submit">
                    <button type="button" id="add-selected-repos" class="button button-secondary">
                        Add Selected Repositories
                        <span class="spinner"></span>
                    </button>
                </p>
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

        // Use the authenticated user's /user/repos endpoint for private/public repos
        $github_api = WP_Git_Plugins_Github_API::get_instance($token);
        if (!method_exists($github_api, 'get_authenticated_user_repositories')) {
            wp_send_json_error('API method get_authenticated_user_repositories not found.');
        }
        $repos = $github_api->get_authenticated_user_repositories();

        if (is_wp_error($repos)) {
            wp_send_json_error($repos->get_error_message());
        }

        if (empty($repos)) {
            wp_send_json_error('No repositories found for authenticated user.');
        }

        // Format repositories for response
        $formatted_repos = array();
        foreach ($repos as $repo) {
            $formatted_repos[] = array(
                'name' => $repo['name'],
                'full_name' => $repo['full_name'],
                'description' => $repo['description'],
                'clone_url' => $repo['clone_url'],
                'private' => $repo['private'],
                'language' => $repo['language'],
                'updated_at' => $repo['updated_at']
            );
        }

        wp_send_json_success(array(
            'repositories' => $formatted_repos,
            'count' => count($formatted_repos)
        ));
    }
    
    /**
     * Enqueue module scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook === 'toplevel_page_wp-git-plugins') {
            wp_enqueue_script(
                'wp-git-plugins-fetch-repos',
                WP_GIT_PLUGINS_URL . 'modules/fetch-my-repos/fetch-repos.js',
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
}

// Only initialize if this is being loaded as a module (not directly)
if (class_exists('WP_Git_Plugins_Modules')) {
    $wp_git_plugins_fetch_my_repos = new WP_Git_Plugins_Fetch_My_Repos_Module();
    $wp_git_plugins_fetch_my_repos->init();
}
