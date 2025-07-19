<?php
// Simple debug script to test class instantiation
require_once __DIR__ . '/includes/class-wp-git-plugins-db.php';
require_once __DIR__ . '/includes/class-wp-git-plugins-settings.php';
require_once __DIR__ . '/includes/class-wp-git-plugins-repository.php';

echo "Testing class instantiation...\n";

try {
    echo "1. Creating Settings instance...\n";
    $settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
    echo "   Settings created successfully\n";
    
    echo "2. Creating Repository instance...\n";
    $repository = new WP_Git_Plugins_Repository($settings);
    echo "   Repository created successfully\n";
    
    echo "3. Testing get_local_repositories method...\n";
    $repos = $repository->get_local_repositories();
    echo "   Method executed, returned " . count($repos) . " repositories\n";
    
    echo "All tests passed!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
