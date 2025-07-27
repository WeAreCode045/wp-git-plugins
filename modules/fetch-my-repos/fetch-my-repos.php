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

        add_action('wp_git_plugins_add_repository_tabs', array($this, 'add_fetch_tab'));
        add_action('wp_git_plugins_add_repository_content', array($this, 'add_fetch_content'));
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
     * Add the "Fetch My Repos" tab
     */public function enqueue_styles($hook) {
        if ($hook === 'toplevel_page_wp-git-plugins') {
            wp_enqueue_style(
                'wp-git-plugins-fetch-repos',
                WP_GIT_PLUGINS_URL . 'modules/fetch-my-repos/assets/css/fetch-repos.css',
                array(),
                '1.0.0'
            );
        }
    }
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
                <p class="submit">
                    <button type="button" id="add-selected-repos" class="button button-secondary">
                        Add Selected Repositories
                        <span class="spinner"></span>
                    </button>
                </p>
            </div>
            <?php
            // Show fetched repos from DB
            if (class_exists('WPGP_Fetch_My_Repos_DB')) {
                global $wpdb;
                $table = WPGP_Fetch_My_Repos_DB::get_instance()->get_table_name();
                $repos = $wpdb->get_results("SELECT * FROM $table ORDER BY most_recent_fetch DESC");
                if ($repos) {
                    echo '<h4>Fetched Plugin Repositories</h4>';
                    echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
                    echo '<th>Plugin Name</th><th>Owner</th><th>Branches</th><th>First Fetch</th><th>Last Fetch</th><th>Installed</th><th>Action</th>';
                    echo '</tr></thead><tbody>';
                    foreach ($repos as $repo) {
                        echo '<tr>';
                        echo '<td>' . esc_html($repo->plugin_name) . '</td>';
                        echo '<td>' . esc_html($repo->owner) . '</td>';
                        echo '<td>' . esc_html($repo->branches) . '</td>';
                        echo '<td>' . esc_html($repo->first_fetch) . '</td>';
                        echo '<td>' . esc_html($repo->most_recent_fetch) . '</td>';
                        echo '<td>' . ($repo->is_installed ? '<span style="color:green">Yes</span>' : '<span style="color:#d63638">No</span>') . '</td>';
                        echo '<td>';
                        if (!$repo->is_installed) {
                            echo '<button class="button add-to-repo-list" data-repo-url="' . esc_attr($repo->repo_url) . '">Add to repo list</button>';
                        } else {
                            echo '<span style="color:gray">Already added</span>';
                        }
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                }
            }
            ?>
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

        // Only keep repos that are valid WP plugins (by checking for plugin header in main file)
        $valid_repos = array();
        foreach ($repos as $repo) {
            // Try to fetch the main plugin file from the repo (assume repo name matches folder and main file)
            $main_file = $repo['name'] . '.php';
            $raw_url = sprintf('https://raw.githubusercontent.com/%s/%s/%s/%s', $repo['owner']['login'] ?? $username, $repo['name'], $repo['default_branch'] ?? 'main', $main_file);
            $file_contents = wp_remote_retrieve_body(wp_remote_get($raw_url));
            if ($this->is_valid_wp_plugin($file_contents)) {
                $repo_data = array(
                    'plugin_name' => $repo['name'],
                    'repo_url' => $repo['html_url'],
                    'owner' => $repo['owner']['login'] ?? $username,
                    'branches' => json_encode([$repo['default_branch'] ?? 'main']),
                    'first_fetch' => current_time('mysql'),
                    'most_recent_fetch' => current_time('mysql'),
                    'is_installed' => 0
                );
                // Save to DB
                WPGP_Fetch_My_Repos_DB::get_instance()->upsert_repo($repo_data);
                $valid_repos[] = array(
                    'name' => $repo['name'],
                    'full_name' => $repo['full_name'],
                    'description' => $repo['description'],
                    'clone_url' => $repo['clone_url'],
                    'private' => $repo['private'],
                    'language' => $repo['language'],
                    'updated_at' => $repo['updated_at']
                );
            }
        }

        wp_send_json_success(array(
            'repositories' => $valid_repos,
            'count' => count($valid_repos)
        ));

    // End of method
}

    /**
     * Check if file contents contain a valid WP plugin header
     */
    private function is_valid_wp_plugin($file_contents) {
        if (empty($file_contents)) return false;
        $pattern = '/^\s*Plugin Name\s*:/mi';
        return preg_match($pattern, $file_contents);
    }
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