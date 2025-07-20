<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue tools page styles and scripts
add_action('admin_enqueue_scripts', function($hook) {
    if ('tools_page_wp-git-plugins-tools' !== $hook) {
        return;
    }
    
    wp_enqueue_style(
        'wp-git-plugins-debug',
        plugins_url('assets/css/debug.css', dirname(__FILE__)),
        array(),
        filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/css/debug.css')
    );
    
    wp_enqueue_script(
        'wp-git-plugins-debug',
        plugins_url('assets/js/debug.js', dirname(__FILE__)),
        array('jquery'),
        filemtime(plugin_dir_path(dirname(__FILE__)) . 'assets/js/debug.js'),
        true
    );
    
    // Use centralized localization
    WP_Git_Plugins_i18n::localize_debug_script('wp-git-plugins-debug');
});
?>
<div class="wrap wp-git-plugins-debug">
    <h1><?php esc_html_e('WP Git Plugins - Tools', 'wp-git-plugins'); ?></h1>
    
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <!-- Main content -->
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Debug Tools', 'wp-git-plugins'); ?></span></h2>
                        <div class="inside">
                            <h2 class="nav-tab-wrapper">
                                <a href="#history" class="nav-tab nav-tab-active" data-tab="history">
                                    <?php esc_html_e('History', 'wp-git-plugins'); ?>
                                </a>
                                <a href="#error-log" class="nav-tab" data-tab="error-log">
                                    <?php esc_html_e('Error Log', 'wp-git-plugins'); ?>
                                </a>
                                <a href="#console-log" class="nav-tab" data-tab="console-log">
                                    <?php esc_html_e('Console Log', 'wp-git-plugins'); ?>
                                </a>
                            </h2>
                            
                            <!-- History Tab -->
                            <div id="history-tab" class="tab-content active">
                                <?php include WP_GIT_PLUGINS_PATH . 'templates/components/tools/logging/history-log.php'; ?>
                            </div>
                            
                            <!-- Error Log Tab -->
                            <div id="error-log-tab" class="tab-content">
                                <?php include WP_GIT_PLUGINS_PATH . 'templates/components/tools/logging/debug-log.php'; ?>
                            </div>
                            
                            <!-- Console Log Tab -->
                            <div id="console-log-tab" class="tab-content">
                                <?php include WP_GIT_PLUGINS_PATH . 'templates/components/tools/logging/console-log.php'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables ui-sortable">
                    <!-- Quick Actions -->
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Quick Actions', 'wp-git-plugins'); ?></span></h2>
                        <div class="inside">
                            <div class="clear-form">
                                <form method="post" action="">
                                    <?php wp_nonce_field('wpgp_clear_debug_log', 'wpgp_clear_nonce'); ?>
                                    <input type="hidden" name="clear_debug_log" value="1">
                                    <button type="submit" class="button button-secondary" style="width: 100%; margin-bottom: 10px;">
                                        <?php esc_html_e('Clear Debug Log', 'wp-git-plugins'); ?>
                                    </button>
                                </form>
                                
                                <form method="post" action="">
                                    <?php wp_nonce_field('wpgp_clear_history', 'wpgp_clear_nonce'); ?>
                                    <input type="hidden" name="clear_history" value="1">
                                    <button type="submit" class="button button-secondary" style="width: 100%;">
                                        <?php esc_html_e('Clear History', 'wp-git-plugins'); ?>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
