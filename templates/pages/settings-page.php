<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Settings Page Template for WP Git Plugins
 *
 * This file is part of the WP Git Plugins project.
 *
 * @package WP_Git_Plugins
 * @since 1.0.0
 */

// Get settings
$settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
?>
<div class="wrap">
    <h1><?php esc_html_e('WP Git Plugins - Settings', 'wp-git-plugins'); ?></h1>
    <div class="wp-git-plugins-container">
        <div class="wp-git-plugins-main">
            <h2 class="nav-tab-wrapper">
                <a href="#git-settings" class="nav-tab nav-tab-active">
                    <?php esc_html_e('Github Settings', 'wp-git-plugins'); ?>
                </a>
                <a href="#modules-manager" class="nav-tab">
                    <?php esc_html_e('Modules Manager Settings', 'wp-git-plugins'); ?>
                </a>
            </h2>
                            
            <div class="tab-content-wrapper">
                                <!-- Github Settings Tab -->
                <div id="git-settings" class="tab-content active">
                    <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/github-settings.php'; ?>
                </div>
                                
                                <!-- Modules Manager Tab -->
                <div id="modules-manager" class="tab-content">
                    <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/module-settings.php'; ?>
                </div>
        </div>
    </div>             
    <!-- Sidebar -->
    <div class="wp-git-plugins-sidebar">
        <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
    </div>
</div>