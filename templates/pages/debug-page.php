<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue debug page styles and scripts
add_action('admin_enqueue_scripts', function($hook) {
    if ('tools_page_wp-git-plugins-debug' !== $hook) {
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
    
    wp_localize_script('wp-git-plugins-debug', 'wpGitPluginsDebug', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('wp_git_plugins_debug_nonce'),
        'i18n' => array(
            'refreshing' => __('Refreshing...', 'wp-git-plugins'),
            'error' => __('Error:', 'wp-git-plugins'),
            'clear_confirm' => __('Are you sure you want to clear the log?', 'wp-git-plugins'),
        )
    ));
});
?>
<div class="wrap wp-git-plugins-debug">
    <h1><?php esc_html_e('WP Git Plugins - Debug', 'wp-git-plugins'); ?></h1>
    
    <div id="poststuff">
        <div id="post-body" class="metabox-holder columns-2">
            <!-- Main content -->
            <div id="post-body-content">
                <div class="meta-box-sortables ui-sortable">
                    <div class="postbox">
                        <h2 class="hndle"><span><?php esc_html_e('Debug Information', 'wp-git-plugins'); ?></span></h2>
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
                            
                            <div class="tab-content">
                                <div id="history" class="tab-pane active">
                                    <?php include_once WP_GIT_PLUGINS_DIR . 'templates/components/history.php'; ?>
                                </div>
                                <div id="error-log" class="tab-pane">
                                    <?php include_once WP_GIT_PLUGINS_DIR . 'templates/components/error-log.php'; ?>
                                </div>
                                <div id="console-log" class="tab-pane">
                                    <?php include_once WP_GIT_PLUGINS_DIR . 'templates/components/console-log.php'; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div id="postbox-container-1" class="postbox-container">
                <div class="meta-box-sortables">
                    <?php include_once plugin_dir_path(dirname(__FILE__)) . 'components/plugin-info.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>