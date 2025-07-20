<?php
// /templates/components/settings/module-settings.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('Module Management', 'wp-git-plugins'); ?></h2>
    
    <div style="background: #f0f0f0; padding: 20px; border: 1px solid #ccc; margin: 20px 0;">
        <h3>Module Management Test</h3>
        <p>This tab is now working!</p>
        <p><strong>Current time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <!-- Module Upload Section -->
    <div class="module-upload-section">
        <h3><?php esc_html_e('Upload Module', 'wp-git-plugins'); ?></h3>
        
        <form id="module-upload-form" enctype="multipart/form-data">
            <?php wp_nonce_field('wpgp_upload_module', '_ajax_nonce'); ?>
            
            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="module-file">
                                <?php esc_html_e('Module ZIP File', 'wp-git-plugins'); ?>
                            </label>
                        </th>
                        <td>
                            <input type="file" 
                                   id="module-file" 
                                   name="module_file" 
                                   accept=".zip" 
                                   required />
                            <p class="description">
                                <?php esc_html_e('Select a ZIP file containing the module to upload.', 'wp-git-plugins'); ?>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <p class="submit">
                <button type="submit" class="button button-primary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php esc_html_e('Upload Module', 'wp-git-plugins'); ?>
                </button>
                <span class="spinner" style="display: none;"></span>
            </p>
        </form>
    </div>
    
    <!-- Modules List Section -->
    <div class="installed-modules-section">
        <h3><?php esc_html_e('Available Modules', 'wp-git-plugins'); ?></h3>
        
        <p>Module management functionality will be added here once the tab is working properly.</p>
    </div>
</div>
