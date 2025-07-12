<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('wp_git_plugins_messages'); ?>
    
    <div class="wp-git-plugins-container">
        <div class="wp-git-plugins-main">
            <div class="wp-git-plugins-card">
                <h2><?php esc_html_e('Settings', 'wp-git-plugins'); ?></h2>
                
                <form method="post" action="options.php">
                    <?php
                    settings_fields('wp_git_plugins_options_group');
                    do_settings_sections('wp-git-plugins-settings');
                    submit_button();
                    ?>
                </form>
            </div>
        </div>
        
        <div class="wp-git-plugins-sidebar">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/github-settings.php'; ?>
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
        </div>
    </div>
</div>
