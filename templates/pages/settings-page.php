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
            <form method="post" action="options.php">
                <?php 
                // Output security fields
                settings_fields('wpgp_settings_group');
                
                // Output settings sections and their fields
                do_settings_sections('wp-git-plugins-settings');
                
                // Output save settings button
                $form_strings = WP_Git_Plugins_i18n::get_form_strings();
                submit_button($form_strings['save_settings']);
                ?>
            </form>
        </div>
        
        <div class="wp-git-plugins-sidebar">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
        </div>
    </div>
</div>