<?php
/**
 * Debug page template
 *
 * @package WP_Git_Plugins
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Show admin notices
settings_errors('wp_git_plugins_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="card">
        <h2><?php esc_html_e('Debug Information', 'wp-git-plugins'); ?></h2>
        
        <div class="inside">
            <form method="post" action="">
                <?php wp_nonce_field('clear_debug_log'); ?>
                <p>
                    <input type="submit" name="clear_debug_log" class="button button-secondary" 
                           value="<?php esc_attr_e('Clear Debug Log', 'wp-git-plugins'); ?>">
                </p>
            </form>
            
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
                </tbody>
            </table>
            
            <h3><?php esc_html_e('Debug Log', 'wp-git-plugins'); ?></h3>
            <?php if (empty($debug_log)) : ?>
                <p><?php esc_html_e('No debug entries found.', 'wp-git-plugins'); ?></p>
            <?php else : ?>
                <div class="wp-git-plugins-debug-log">
                    <?php foreach (array_reverse($debug_log) as $entry) : ?>
                        <div class="debug-entry">
                            <div class="debug-header">
                                <span class="debug-time"><?php echo esc_html($entry['time']); ?></span>
                                <strong class="debug-message"><?php echo esc_html($entry['message']); ?></strong>
                            </div>
                            <?php if (!empty($entry['data'])) : ?>
                                <div class="debug-data">
                                    <pre><code><?php echo esc_html(print_r($entry['data'], true)); ?></code></pre>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.wp-git-plugins-debug-log {
    max-height: 600px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 10px;
    background: #f9f9f9;
}

.debug-entry {
    margin-bottom: 15px;
    padding: 10px;
    border-left: 4px solid #0073aa;
    background: #fff;
}

.debug-header {
    margin-bottom: 5px;
}

.debug-time {
    color: #666;
    margin-right: 10px;
    font-size: 0.9em;
}

.debug-message {
    color: #0073aa;
}

.debug-data {
    background: #f1f1f1;
    padding: 5px 10px;
    border-radius: 3px;
    overflow-x: auto;
}

debug-data pre {
    margin: 0;
    white-space: pre-wrap;
}
</style>
