<?php
// Simple debug script to check repository data structure
// Place this in wp-content/plugins/wp-git-plugins/ and access via browser

if (!defined('ABSPATH')) {
    // Load WordPress if accessing directly
    require_once('../../../wp-config.php');
}

if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

echo '<h2>WP Git Plugins - Repository Data Debug</h2>';

$repository = new WP_Git_Plugins_Repository();
$repositories = $repository->get_local_repositories();

echo '<h3>Repository Data Structure:</h3>';
echo '<pre>';
foreach ($repositories as $index => $repo) {
    echo "Repository #{$index}:\n";
    echo "ID: " . ($repo['id'] ?? 'N/A') . "\n";
    echo "Name: " . ($repo['name'] ?? 'N/A') . "\n";
    echo "Plugin Slug: " . ($repo['plugin_slug'] ?? 'N/A') . "\n";
    echo "Installed Version: " . ($repo['installed_version'] ?? 'N/A') . "\n";
    echo "Latest Version: " . ($repo['latest_version'] ?? 'N/A') . "\n";
    echo "Git Version: " . ($repo['git_version'] ?? 'N/A') . "\n";
    echo "Local Version: " . ($repo['local_version'] ?? 'N/A') . "\n";
    
    // Test version comparison
    $installed = $repo['installed_version'] ?? '';
    $latest = $repo['latest_version'] ?? '';
    $git = $repo['git_version'] ?? '';
    
    echo "Version Comparison Tests:\n";
    if (!empty($installed) && !empty($latest)) {
        $update_available = version_compare($latest, $installed, '>');
        echo "  latest > installed: " . ($update_available ? 'TRUE' : 'FALSE') . "\n";
    }
    if (!empty($installed) && !empty($git)) {
        $update_available_git = version_compare($git, $installed, '>');
        echo "  git > installed: " . ($update_available_git ? 'TRUE' : 'FALSE') . "\n";
    }
    
    echo "---\n\n";
}
echo '</pre>';
?>
