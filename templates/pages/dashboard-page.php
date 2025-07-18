<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Display any transient notices
$notice = get_transient('wp_git_plugins_notice');
if ($notice) {
    delete_transient('wp_git_plugins_notice');
    ?>
    <div class="notice notice-<?php echo esc_attr($notice['type']); ?> is-dismissible">
        <p><?php echo esc_html($notice['message']); ?></p>
    </div>
    <?php
}
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('wp_git_plugins_messages'); ?>
    
    <div class="wp-git-plugins-container">
        <div class="wp-git-plugins-main">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/repository-list.php'; ?>
        </div>
        
        <div class="wp-git-plugins-sidebar">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/add-repository.php'; ?>
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
        </div>
    </div>
</div>
