<?php
/**
 * System Information Component
 *
 * @package WP_Git_Plugins
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<h3><?php esc_html_e('System Information', 'wp-git-plugins'); ?></h3>
<table class="widefat striped">
    <tbody>
        <tr>
            <td><strong>PHP Version:</strong></td>
            <td><?php echo phpversion(); ?></td>
        </tr>
        <tr>
            <td><strong>WordPress Version:</strong></td>
            <td><?php echo get_bloginfo('version'); ?></td>
        </tr>
        <tr>
            <td><strong>Git Version:</strong></td>
            <td>
                <?php 
                $git_version = shell_exec('git --version 2>&1');
                echo $git_version ? esc_html($git_version) : 'Not available';
                ?>
            </td>
        </tr>
        <tr>
            <td><strong>shell_exec enabled:</strong></td>
            <td><?php echo function_exists('shell_exec') ? 'Yes' : 'No'; ?></td>
        </tr>
        <tr>
            <td><strong>PHP Memory Limit:</strong></td>
            <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
        </tr>
        <tr>
            <td><strong>PHP Time Limit:</strong></td>
            <td><?php echo esc_html(ini_get('max_execution_time')); ?> seconds</td>
        </tr>
        <tr>
            <td><strong>PHP Upload Max Filesize:</strong></td>
            <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
        </tr>
        <tr>
            <td><strong>PHP Post Max Size:</strong></td>
            <td><?php echo esc_html(ini_get('post_max_size')); ?></td>
        </tr>
        <tr>
            <td><strong>PHP Max Input Vars:</strong></td>
            <td><?php echo esc_html(ini_get('max_input_vars')); ?></td>
        </tr>
        <tr>
            <td><strong>PHP Display Errors:</strong></td>
            <td><?php echo esc_html(ini_get('display_errors')); ?></td>
        </tr>
    </tbody>
</table>
