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
    <div class="wp-git-plugins-container">
        <div class="wp-git-plugins-main">
            <h1><?php esc_html_e('Settings', 'wp-git-plugins'); ?></h1>
            <div class="wp-git-plugins-tabs">
                <div class="nav-tab-wrapper">
                    <a href="#git-settings" class="nav-tab active">GitHub</a>
                    <a href="#modules-manager" class="nav-tab">Modules</a>
                </div>
            </div>
            <div class="wp-git-plugins-tabs-content"> 
                <div id="git-settings" class="wp-git-plugins-tab-content active">
               <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/github-settings.php'; ?>
                </div>
                <div id="modules-manager" class="wp-git-plugins-tab-content">
                <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/module-settings.php'; ?>
                </div>
            </div>
        </div>
        <div class="wp-git-plugins-sidebar">
            <div class="wp-git-plugins-card">
                <h2><?php esc_html_e('Debug', 'wp-git-plugins'); ?></h2>
                <p>
                    <?php esc_html_e('For troubleshooting, you can view the debug information.', 'wp-git-plugins'); ?>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('tools.php?page=wp-git-plugins-debug')); ?>" class="button button-secondary">
                        <?php esc_html_e('View Debug Information', 'wp-git-plugins'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>     