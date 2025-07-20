<?php
$error_log = get_option('wp_git_plugins_error_log', array());
$error_log = array_reverse($error_log); // Show newest first
?>
<div class="error-log-container">
    <div class="tablenav top">
        <div class="alignleft actions">
            <button type="button" class="button clear-log" data-type="error-log">
                <?php esc_html_e('Clear Log', 'wp-git-plugins'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>
    
    <div class="error-log-content">
        <?php if (empty($error_log)) : ?>
            <p><?php esc_html_e('No errors logged.', 'wp-git-plugins'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date/Time', 'wp-git-plugins'); ?></th>
                        <th><?php esc_html_e('Error', 'wp-git-plugins'); ?></th>
                        <th><?php esc_html_e('Location', 'wp-git-plugins'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($error_log as $error) : ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $error['time'])); ?></td>
                            <td>
                                <div class="error-message"><?php echo esc_html($error['message']); ?></div>
                                <?php if (!empty($error['trace'])) : ?>
                                    <div class="error-trace" style="display: none;">
                                        <pre><?php echo esc_html($error['trace']); ?></pre>
                                    </div>
                                    <button type="button" class="button-link toggle-trace">
                                        <?php esc_html_e('Show trace', 'wp-git-plugins'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($error['file'])) : ?>
                                    <?php echo esc_html(basename($error['file'])); ?>
                                    <?php if (!empty($error['line'])) : ?>
                                        :<?php echo (int) $error['line']; ?>
                                    <?php endif; ?>
                                <?php else : ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
