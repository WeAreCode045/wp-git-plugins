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
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="#github-settings" class="nav-tab nav-tab-active" id="github-settings-tab">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('GitHub Settings', 'wp-git-plugins'); ?>
                </a>
                <a href="#modules-management" class="nav-tab" id="modules-management-tab">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php esc_html_e('Module Management', 'wp-git-plugins'); ?>
                </a>
            </nav>
            
            <!-- Tab Content -->
            <div class="tab-content-wrapper">
                <!-- GitHub Settings Tab -->
                <div id="github-settings" class="tab-content active">
                    <form method="post" action="options.php">
                        <?php 
                        // Output security fields
                        settings_fields('wpgp_settings_group');
                        
                        // Output only GitHub settings section
                        do_settings_sections('wp-git-plugins-github-settings');
                        
                        // Output save settings button
                        $form_strings = WP_Git_Plugins_i18n::get_form_strings();
                        submit_button($form_strings['save_settings']);
                        ?>
                    </form>
                </div>
                
                <!-- Modules Management Tab -->
                <div id="modules-management" class="tab-content">
                    <?php
                    // Get modules manager instance
                    $modules_manager = WP_Git_Plugins_Modules::get_instance();
                    $available_modules = $modules_manager->get_available_modules();
                    $active_modules = get_option('wpgp_active_modules', []);
                    ?>
                    
                    <!-- Upload Module Section -->
                    <div class="module-upload-section" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px; background: #fff;">
                        <h3><?php esc_html_e('Upload New Module', 'wp-git-plugins'); ?></h3>
                        <p><?php esc_html_e('Upload a ZIP file containing a module to extend the functionality of WP Git Plugins.', 'wp-git-plugins'); ?></p>
                        
                        <form id="module-upload-form" enctype="multipart/form-data" style="margin: 0;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="module-file"><?php esc_html_e('Module File', 'wp-git-plugins'); ?></label>
                                    </th>
                                    <td>
                                        <input type="file" id="module-file" name="module_file" accept=".zip" required />
                                        <p class="description"><?php esc_html_e('Select a ZIP file containing a module.', 'wp-git-plugins'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" class="button button-primary">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php esc_html_e('Upload Module', 'wp-git-plugins'); ?>
                                </button>
                                <span class="spinner" style="float: none; margin-left: 10px;"></span>
                            </p>
                        </form>
                    </div>

                    <!-- Installed Modules Section -->
                    <div class="installed-modules-section">
                        <h3><?php esc_html_e('Installed Modules', 'wp-git-plugins'); ?></h3>
                        
                        <?php if (!empty($available_modules)) : ?>
                            <table class="wp-list-table widefat fixed striped">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width: 25%;"><?php esc_html_e('Module', 'wp-git-plugins'); ?></th>
                                        <th scope="col" style="width: 40%;"><?php esc_html_e('Description', 'wp-git-plugins'); ?></th>
                                        <th scope="col" style="width: 10%;"><?php esc_html_e('Version', 'wp-git-plugins'); ?></th>
                                        <th scope="col" style="width: 10%;"><?php esc_html_e('Status', 'wp-git-plugins'); ?></th>
                                        <th scope="col" style="width: 15%;"><?php esc_html_e('Actions', 'wp-git-plugins'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_modules as $module_slug => $module_data) : 
                                        $is_active = in_array($module_slug, $active_modules);
                                    ?>
                                        <tr class="<?php echo $is_active ? 'active' : 'inactive'; ?>">
                                            <td>
                                                <strong><?php echo esc_html($module_data['Name']); ?></strong>
                                                <div class="row-actions">
                                                    <span class="author">By <?php echo esc_html($module_data['Author']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <p><?php echo esc_html($module_data['Description']); ?></p>
                                            </td>
                                            <td>
                                                <?php echo esc_html($module_data['Version']); ?>
                                            </td>
                                            <td>
                                                <?php if ($is_active) : ?>
                                                    <span class="module-status active"><?php esc_html_e('Active', 'wp-git-plugins'); ?></span>
                                                <?php else : ?>
                                                    <span class="module-status inactive"><?php esc_html_e('Inactive', 'wp-git-plugins'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($is_active) : ?>
                                                    <button type="button" class="button deactivate-module" data-module="<?php echo esc_attr($module_slug); ?>">
                                                        <?php esc_html_e('Deactivate', 'wp-git-plugins'); ?>
                                                    </button>
                                                <?php else : ?>
                                                    <button type="button" class="button button-primary activate-module" data-module="<?php echo esc_attr($module_slug); ?>">
                                                        <?php esc_html_e('Activate', 'wp-git-plugins'); ?>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <button type="button" class="button delete-module" data-module="<?php echo esc_attr($module_slug); ?>" style="margin-left: 5px;">
                                                    <?php esc_html_e('Delete', 'wp-git-plugins'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <div class="no-modules-message" style="padding: 20px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px; text-align: center;">
                                <p><?php esc_html_e('No modules installed yet.', 'wp-git-plugins'); ?></p>
                                <p><?php esc_html_e('Upload a module above to get started with extending WP Git Plugins functionality.', 'wp-git-plugins'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="wp-git-plugins-sidebar">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
        </div>
    </div>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 0;
    border-bottom: 1px solid #c3c4c7;
}

.nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.nav-tab .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.tab-content-wrapper {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-top: none;
    padding: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.module-status.active {
    color: #00a32a;
    font-weight: 600;
}

.module-status.inactive {
    color: #d63638;
    font-weight: 600;
}

.wp-list-table .row-actions {
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}

.no-modules-message {
    margin: 20px 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.tab-content').removeClass('active');
        
        // Show target tab content
        $(target).addClass('active');
        
        // Update URL hash without scrolling
        if (history.replaceState) {
            history.replaceState(null, null, target);
        }
    });
    
    // Handle hash on page load
    if (window.location.hash) {
        var hash = window.location.hash;
        if ($(hash).length && $('.nav-tab[href="' + hash + '"]').length) {
            $('.nav-tab[href="' + hash + '"]').trigger('click');
        }
    }
});
</script>