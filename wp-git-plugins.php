<?php
/**
 * Plugin Name: WP Git Plugins
 * Plugin URI: https://code045.nl/plugins/wp-git-plugins/
 * Description: Manage and install WordPress plugins directly from Git repositories.
 * Version: 1.0.8
 * Author: WeAreCode045
 * Author URI: https://code045.nl/
 * License: GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-git-plugins
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define plugin constants
define('WP_GIT_PLUGINS_VERSION', '1.0.8');
define('WP_GIT_PLUGINS_DIR', plugin_dir_path(__FILE__));
define('WP_GIT_PLUGINS_URL', plugin_dir_url(__FILE__));
define('WP_GIT_PLUGINS_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-loader.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-i18n.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-admin.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-settings.php';
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins-repository.php';

// Load the main plugin class
require_once WP_GIT_PLUGINS_DIR . 'includes/class-wp-git-plugins.php';

// Initialize plugin classes
$plugin_name = 'WP Git Plugins';
$version = WP_GIT_PLUGINS_VERSION;

// Initialize admin class
$plugin_admin = new WP_Git_Plugins_Admin($plugin_name, $version);

// Initialize public class if it exists
$plugin_public = null;
if (class_exists('WP_Git_Plugins_Public')) {
    $plugin_public = new WP_Git_Plugins_Public($plugin_name, $version);
}

// Initialize repository
$plugin_repository = new WP_Git_Plugins_Repository();

// Register admin hooks
$plugin_admin->register_hooks();

// Register public hooks if class exists
if ($plugin_public) {
    $plugin_public->register_hooks();
}

// Add admin menus

// Initialize the plugin
function run_wp_git_plugins() {
    $plugin = new WP_Git_Plugins();
    $plugin->run();
}
run_wp_git_plugins();