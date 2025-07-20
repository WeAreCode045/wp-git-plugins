<?php
// /templates/components/settings/module-settings.php
if (!defined("ABSPATH")) {
    exit; // Exit if accessed directly
}

// Get modules manager instance if available
$modules_manager = null;
$available_modules = [];

if (class_exists("WP_Git_Plugins_Modules")) {
    try {
        $modules_manager = WP_Git_Plugins_Modules::get_instance();
        $modules_manager->scan_modules();
        $available_modules = $modules_manager->get_available_modules();
    } catch (Exception $e) {
        $available_modules = [];
    }
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e("Module Management", "wp-git-plugins"); ?></h2>
    
    <!-- Module Status -->
    <div style="background: #e7f3ff; padding: 15px; border: 1px solid #0073aa; margin: 20px 0; border-radius: 4px;">
        <h3>Module System Status</h3>
        <p><strong>Modules Manager:</strong> <?php echo class_exists("WP_Git_Plugins_Modules") ? "✓ Available" : "✗ Not Available"; ?></p>
        <p><strong>Available Modules:</strong> <?php echo count($available_modules); ?></p>
        <?php if (!empty($available_modules)) : ?>
            <p><strong>Found Modules:</strong> <?php echo implode(", ", array_keys($available_modules)); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Module Upload Section -->
    <div class="module-upload-section">
        <h3><?php esc_html_e("Upload Module", "wp-git-plugins"); ?></h3>
        
        <form id="module-upload-form" enctype="multipart/form-data">
            <?php wp_nonce_field('wpgp_upload_module', '_ajax_nonce'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="module-file">
                                <?php esc_html_e("Module ZIP File", "wp-git-plugins"); ?>
                            </label>
                        </th>
                        <td>
                            <input type="file" 
                                   id="module-file" 
                                   name="module_file" 
                                   accept=".zip" 
                                   required />
                            <p class="description">
                                <?php esc_html_e("Select a ZIP file containing the module to upload.", "wp-git-plugins"); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e("Upload Module", "wp-git-plugins"); ?>
                </button>
                <span class="spinner" style="display: none;"></span>
            </p>
        </form>
    </div>
    
    <!-- Available Modules Section -->
    <div class="installed-modules-section">
        <h3><?php esc_html_e("Available Modules", "wp-git-plugins"); ?></h3>
        
        <?php if (empty($available_modules)) : ?>
            <div class="no-modules-message">
                <p><?php esc_html_e("No modules found.", "wp-git-plugins"); ?></p>
            </div>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col"><?php esc_html_e("Module", "wp-git-plugins"); ?></th>
                        <th scope="col"><?php esc_html_e("Version", "wp-git-plugins"); ?></th>
                        <th scope="col"><?php esc_html_e("Description", "wp-git-plugins"); ?></th>
                        <th scope="col"><?php esc_html_e("Status", "wp-git-plugins"); ?></th>
                        <th scope="col"><?php esc_html_e("Actions", "wp-git-plugins"); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($available_modules as $module_slug => $module_info) : 
                        $is_active = $modules_manager ? $modules_manager->is_module_active($module_slug) : false;
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($module_info["name"] ?? $module_slug); ?></strong>
                                <?php if (!empty($module_info["author"])) : ?>
                                    <br><span class="description"><?php printf(__("by %s", "wp-git-plugins"), esc_html($module_info["author"])); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo esc_html($module_info["version"] ?? "1.0.0"); ?>
                            </td>
                            <td>
                                <?php echo esc_html($module_info["description"] ?? ""); ?>
                            </td>
                            <td>
                                <?php if ($is_active) : ?>
                                    <span class="module-status active"><?php esc_html_e("Active", "wp-git-plugins"); ?></span>
                                <?php else : ?>
                                    <span class="module-status inactive"><?php esc_html_e("Inactive", "wp-git-plugins"); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="module-actions">
                                <?php if ($is_active) : ?>
                                    <button type="button" class="button deactivate-module" data-module="<?php echo esc_attr($module_slug); ?>">
                                        <?php esc_html_e("Deactivate", "wp-git-plugins"); ?>
                                    </button>
                                <?php else : ?>
                                    <button type="button" class="button button-primary activate-module" data-module="<?php echo esc_attr($module_slug); ?>">
                                        <?php esc_html_e("Activate", "wp-git-plugins"); ?>
                                    </button>
                                <?php endif; ?>
                                
                                <button type="button" class="button delete-module" data-module="<?php echo esc_attr($module_slug); ?>">
                                    <?php esc_html_e("Delete", "wp-git-plugins"); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
