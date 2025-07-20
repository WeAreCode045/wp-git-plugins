<?php
// /templates/components/settings/module-settings.php
if (!defined("ABSPATH")) {
    exit; // Exit if accessed directly
}

// Get modules manager instance if available
$modules_manager = null;
$available_modules = [];

if (class_exists("WP_Git_Plugins_Modules")) {
    try {
        $modules_manager = WP_Git_Plugins_Modules::get_instance();
        $modules_manager->scan_modules();
        $available_modules = $modules_manager->get_available_modules();
    } catch (Exception $e) {
        $available_modules = [];
    }
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e("Module Management", "wp-git-plugins"); ?></h2>
    
    <!-- Module Status -->
    <div style="background: #e7f3ff; padding: 15px; border: 1px solid #0073aa; margin: 20px 0; border-radius: 4px;">
        <h3>Module System Status</h3>
        <p><strong>Modules Manager:</strong> <?php echo class_exists("WP_Git_Plugins_Modules") ? "✓ Available" : "✗ Not Available"; ?></p>
        <p><strong>Available Modules:</strong> <?php echo count($available_modules); ?></p>
        <?php if (!empty($available_modules)) : ?>
            <p><strong>Found Modules:</strong> <?php echo implode(", ", array_keys($available_modules)); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Module Upload Section -->
    <div class="module-upload-section">
        <h3><?php esc_html_e("Upload Module", "wp-git-plugins"); ?></h3>
        <p>Module upload functionality will be implemented here.</p>
    </div>
    
    <!-- Available Modules Section -->
    <div class="installed-modules-section">
        <h3><?php esc_html_e("Available Modules", "wp-git-plugins"); ?></h3>
        
        <?php if (empty($available_modules)) : ?>
            <div class="no-modules-message">
                <p><?php esc_html_e("No modules found.", "wp-git-plugins"); ?></p>
            </div>
        <?php else : ?>
            <ul>
                <?php foreach ($available_modules as $module_slug => $module_info) : ?>
                    <li><strong><?php echo esc_html($module_info["name"] ?? $module_slug); ?></strong> - <?php echo esc_html($module_info["version"] ?? "1.0.0"); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
