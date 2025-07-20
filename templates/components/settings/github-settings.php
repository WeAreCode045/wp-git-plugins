<?php
// /templates/components/settings/github-settings.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get current settings
$settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
?>
<div class="wp-git-plugins-card">    
    <form method="post" action="options.php">
        <?php 
        // Output security fields for the registered setting
        settings_fields('wpgp_settings_group');
        
        // Output the settings sections for GitHub settings
        do_settings_sections('wp-git-plugins-github-settings');
        
        // Output save settings button
        $form_strings = WP_Git_Plugins_i18n::get_form_strings();
        submit_button($form_strings['save_settings']);
        ?>
    </form>
</div>
