<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get settings
$settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('wpgp_messages'); ?>
    
    <div class="wp-git-plugins-container">
        <div class="wp-git-plugins-main">
            <!-- Tab Navigation -->
<div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Settings', 'wp-git-plugins'); ?></span></h2>
                        <div class="inside">
                            <h2 class="nav-tab-wrapper">
                                <a href="#github-settings" class="nav-tab nav-tab-active">
                                    <?php esc_html_e('Github Settings', 'wp-git-plugins'); ?>
                                </a>
                                <a href="#modules-manager" class="nav-tab">
                                    <?php esc_html_e('Modules Manager', 'wp-git-plugins'); ?>
                                </a>
                            </h2>
                            
                            <div class="tab-content-wrapper">
                                <!-- Github Settings Tab -->
                                <div id="github-settings" class="tab-content active">
                                    <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/github-settings.php'; ?>
                                </div>
                                
                                <!-- Modules Manager Tab -->
                                <div id="modules-manager" class="tab-content">
                                    <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/module-settings.php'; ?>
                                </div>
                                
                            </div>
                        </div>
                    </div>
                </div>
            </div>