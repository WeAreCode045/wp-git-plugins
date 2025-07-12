<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('System Information', 'wp-git-plugins'); ?></h2>
    
    <table class="widefat striped">
        <tbody>
            <tr>
                <th><?php esc_html_e('WordPress Version', 'wp-git-plugins'); ?></th>
                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('PHP Version', 'wp-git-plugins'); ?></th>
                <td><?php echo esc_html(phpversion()); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Server IP', 'wp-git-plugins'); ?></th>
                <td><?php echo esc_html($_SERVER['SERVER_ADDR'] ?? 'N/A'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Memory Limit', 'wp-git-plugins'); ?></th>
                <td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Git Installed', 'wp-git-plugins'); ?></th>
                <td>
                    <?php
                    $git_installed = (bool) exec('git --version');
                    if ($git_installed) {
                        echo '<span class="dashicons dashicons-yes-alt" style="color: #46b450;"></span> ';
                        esc_html_e('Yes', 'wp-git-plugins');
                    } else {
                        echo '<span class="dashicons dashicons-warning" style="color: #dc3232;"></span> ';
                        esc_html_e('No - Some features may be limited', 'wp-git-plugins');
                    }
                    ?>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Plugin Version', 'wp-git-plugins'); ?></th>
                <td><?php echo esc_html(WP_GIT_PLUGINS_VERSION); ?></td>
            </tr>
        </tbody>
    </table>
    
    <div class="wp-git-plugins-actions" style="margin-top: 15px;">
        <button id="wp-git-plugins-check-updates" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Check for Updates', 'wp-git-plugins'); ?>
        </button>
        
        <a href="<?php echo esc_url(admin_url('admin.php?page=wp-git-plugins-settings')); ?>" 
           class="button button-secondary" style="float: right;">
            <span class="dashicons dashicons-admin-generic"></span>
            <?php esc_html_e('Settings', 'wp-git-plugins'); ?>
        </a>
    </div>
</div>
