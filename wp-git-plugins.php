<?php
/**
 * Plugin Name: WP Git Plugins
 * Plugin URI: https://code045.nl/plugins/wp-git-plugins/
 * Description: Manage and install WordPress plugins directly from GitHub repositories with branch management and automatic updates.
 * Version: 1.0.14
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: WeAreCode045
 * Author URI: https://code045.nl/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-git-plugins
 * Domain Path: /languages
 * 
 * @package WP_Git_Plugins
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
if (!defined('WP_GIT_PLUGINS_VERSION')) {
    define('WP_GIT_PLUGINS_VERSION', '1.0.14');
}
if (!defined('WP_GIT_PLUGINS_DIR')) {
    define('WP_GIT_PLUGINS_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WP_GIT_PLUGINS_URL')) {
    define('WP_GIT_PLUGINS_URL', plugin_dir_url(__FILE__));
}
if (!defined('WP_GIT_PLUGINS_BASENAME')) {
    define('WP_GIT_PLUGINS_BASENAME', plugin_basename(__FILE__));
}
if (!defined('WP_GIT_PLUGINS_FILE')) {
    define('WP_GIT_PLUGINS_FILE', __FILE__);
}

// Include required files
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-loader.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-i18n.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-error-handler.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-db.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-settings.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-github-api.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-local-plugins.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-branch.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-modules.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-debug.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-repository.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-admin.php';

// No activation or deactivation hooks - we'll handle table creation in the main class

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function wp_git_plugins_init() {
    static $wp_git_plugins = null;
    
    if (is_null($wp_git_plugins)) {
        // Initialize the main plugin class
        $wp_git_plugins = new WP_Git_Plugins();
        $wp_git_plugins->run();
        
        // Initialize database tables if they don't exist
        if (class_exists('WP_Git_Plugins_DB')) {
            $db = WP_Git_Plugins_DB::get_instance();
            $db->create_tables();
        }
    }
    
    return $wp_git_plugins;
}

// Initialize the plugin
add_action('plugins_loaded', 'wp_git_plugins_init');