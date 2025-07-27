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
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/dashboard/add-repository.php'; ?>
            <?php
            // Collect all active addon modules
            $modules_manager = WP_Git_Plugins_Modules::get_instance();
            $active_modules = $modules_manager->get_active_modules();
            $addon_modules = [];
            foreach ($active_modules as $module) {
                $json_path = $module['path'] . '/module.json';
                if (file_exists($json_path)) {
                    $json = json_decode(file_get_contents($json_path), true);
                    if (isset($json['type']) && $json['type'] === 'addon') {
                        $addon_modules[] = [
                            'slug' => $json['slug'],
                            'name' => $json['name'],
                            'class' => !empty($json['main_file']) ? 'WP_Git_Plugins_' . str_replace(' ', '_', ucwords(str_replace('-', ' ', $json['slug']))) . '_Module' : '',
                        ];
                    }
                }
            }
            ?>
            <div class="wpgp-vertical-tabs">
                <ul class="wpgp-tab-list">
                    <li class="wpgp-tab active" data-tab="wpgp-repo-list">
                        <?php esc_html_e('Repositories', 'wp-git-plugins'); ?>
                    </li>
                    <?php foreach ($addon_modules as $i => $addon) : ?>
                        <li class="wpgp-tab" data-tab="wpgp-addon-<?php echo esc_attr($addon['slug']); ?>">
                            <?php echo esc_html($addon['name']); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="wpgp-tab-contents">
                    <div class="wpgp-tab-content active" id="wpgp-repo-list">
                        <?php include WP_GIT_PLUGINS_DIR . 'templates/components/dashboard/repository-list.php'; ?>
                    </div>
                    <?php foreach ($addon_modules as $i => $addon) : ?>
                        <div class="wpgp-tab-content" id="wpgp-addon-<?php echo esc_attr($addon['slug']); ?>">
                            <?php
                            if (!empty($addon['class']) && class_exists($addon['class']) && method_exists($addon['class'], 'render_addon')) {
                                call_user_func([$addon['class'], 'render_addon']);
                            } elseif (function_exists('render_addon')) {
                                render_addon();
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var tabs = document.querySelectorAll('.wpgp-tab');
                var contents = document.querySelectorAll('.wpgp-tab-content');
                tabs.forEach(function(tab, idx) {
                    tab.addEventListener('click', function() {
                        tabs.forEach(function(t) { t.classList.remove('active'); });
                        contents.forEach(function(c) { c.classList.remove('active'); });
                        tab.classList.add('active');
                        var target = document.getElementById(tab.getAttribute('data-tab'));
                        if (target) target.classList.add('active');
                    });
                });
            });
            </script>
            <style>
            .wpgp-vertical-tabs { display: flex; }
            .wpgp-tab-list { list-style: none; margin: 0; padding: 0; width: 200px; border-right: 1px solid #ddd; }
            .wpgp-tab { padding: 12px 16px; cursor: pointer; border-bottom: 1px solid #eee; background: #f9f9f9; }
            .wpgp-tab.active { background: #fff; font-weight: bold; border-right: 2px solid #0073aa; }
            .wpgp-tab-content { display: none; padding: 24px; flex: 1; }
            .wpgp-tab-content.active { display: block; }
            </style>
        </div>
        <div class="wp-git-plugins-sidebar">
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
