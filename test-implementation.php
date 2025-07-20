<?php
/**
 * Test Implementation - Check current status of button functionality
 * This file helps verify that our recent changes are working correctly
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "<h3>WP Git Plugins - Implementation Test</h3>";

// Test 1: Check if database mapping includes required fields
echo "<h4>Test 1: Database Mapping</h4>";
if (class_exists('WP_Git_Plugins_DB')) {
    $db = WP_Git_Plugins_DB::get_instance();
    $repos = $db->get_all_repositories();
    
    if (!empty($repos)) {
        $first_repo = reset($repos);
        $required_fields = ['git_version', 'local_version', 'id', 'gh_owner', 'gh_name'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $first_repo)) {
                $missing_fields[] = $field;
            }
        }
        
        if (empty($missing_fields)) {
            echo "✅ Database mapping includes all required fields<br>";
            echo "Sample repo data keys: " . implode(', ', array_keys($first_repo)) . "<br>";
        } else {
            echo "❌ Missing fields in database mapping: " . implode(', ', $missing_fields) . "<br>";
        }
    } else {
        echo "ℹ️ No repositories found in database<br>";
    }
} else {
    echo "❌ WP_Git_Plugins_DB class not found<br>";
}

// Test 2: Check if AJAX handlers are registered
echo "<h4>Test 2: AJAX Handlers</h4>";
global $wp_filter;

$handlers = [
    'wp_ajax_wp_git_plugins_check_version',
    'wp_ajax_wp_git_plugins_update_repository'
];

foreach ($handlers as $handler) {
    if (isset($wp_filter[$handler])) {
        echo "✅ {$handler} is registered<br>";
    } else {
        echo "❌ {$handler} is NOT registered<br>";
    }
}

// Test 3: Check if repository class exists and methods are available
echo "<h4>Test 3: Repository Class Methods</h4>";
if (class_exists('WP_Git_Plugins_Repository')) {
    $repo = WP_Git_Plugins_Repository::get_instance();
    
    $required_methods = ['ajax_check_version', 'ajax_update_repository'];
    foreach ($required_methods as $method) {
        if (method_exists($repo, $method)) {
            echo "✅ {$method} method exists<br>";
        } else {
            echo "❌ {$method} method NOT found<br>";
        }
    }
} else {
    echo "❌ WP_Git_Plugins_Repository class not found<br>";
}

// Test 4: Check if required JavaScript variables are available
echo "<h4>Test 4: JavaScript Environment</h4>";
echo "wpGitPlugins object should be available on repository list page<br>";
echo "AJAX URL: " . admin_url('admin-ajax.php') . "<br>";
echo "Nonce: " . wp_create_nonce('wp_git_plugins_ajax') . "<br>";
?>
