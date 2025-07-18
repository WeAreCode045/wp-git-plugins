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
</div>
