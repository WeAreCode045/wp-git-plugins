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
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/dashboard/repository-list.php'; ?>
            <?php
            // Render all active addon modules as vertical tabs
            $modules_manager = WP_Git_Plugins_Modules::get_instance();
            $active_modules = $modules_manager->get_active_modules();
            foreach ($active_modules as $module) {
                // Try to read module.json for type
                $json_path = $module['path'] . '/module.json';
                if (file_exists($json_path)) {
                    $json = json_decode(file_get_contents($json_path), true);
                    if (isset($json['type']) && $json['type'] === 'addon') {
                        // Try to call render_addon if available
                        $class_name = '';
                        // Try to guess class name from main_file or slug
                        if (!empty($json['main_file'])) {
                            $main_file = $json['main_file'];
                            $class_name = 'WP_Git_Plugins_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $json['slug']))) . '_Module';
                        }
                        if (class_exists($class_name) && method_exists($class_name, 'render_addon')) {
                            call_user_func([$class_name, 'render_addon']);
                        } elseif (function_exists('render_addon')) {
                            render_addon();
                        }
                    }
                }
            }
            ?>
        </div>
        <div class="wp-git-plugins-sidebar">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/dashboard/add-repository.php'; ?>
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
            <?php
            // Render all active widget modules in the sidebar
            $modules_manager = WP_Git_Plugins_Modules::get_instance();
            $active_modules = $modules_manager->get_active_modules();
            foreach ($active_modules as $module) {
                $json_path = $module['path'] . '/module.json';
                if (file_exists($json_path)) {
                    $json = json_decode(file_get_contents($json_path), true);
                    if (isset($json['type']) && $json['type'] === 'widget') {
                        $class_name = '';
                        if (!empty($json['main_file'])) {
                            $class_name = 'WPGP_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $json['slug']))) ;
                        }
                        if (class_exists($class_name) && method_exists($class_name, 'render_widget')) {
                            call_user_func([$class_name, 'render_widget']);
                        } elseif (function_exists('render_widget')) {
                            render_widget();
                        }
                    }
                }
            }
            ?>
        </div>
    </div>
</div>
