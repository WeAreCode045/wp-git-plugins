<?php
$console_log = get_option('wp_git_plugins_console_log', array());
$console_log = array_reverse($console_log); // Show newest first
?>
<div class="console-log-container">
    <div class="tablenav top">
        <div class="alignleft actions">
            <button type="button" class="button clear-log" data-type="console-log">
                <?php esc_html_e('Clear Log', 'wp-git-plugins'); ?>
            </button>
            <span class="spinner"></span>
        </div>
    </div>
    
    <div class="console-log-content">
        <?php if (empty($console_log)) : ?>
            <p><?php esc_html_e('No console messages logged.', 'wp-git-plugins'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date/Time', 'wp-git-plugins'); ?></th>
                        <th><?php esc_html_e('Type', 'wp-git-plugins'); ?></th>
                        <th><?php esc_html_e('Message', 'wp-git-plugins'); ?></th>
                        <th><?php esc_html_e('Source', 'wp-git-plugins'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($console_log as $entry) : ?>
                        <tr class="console-<?php echo esc_attr($entry['type']); ?>">
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $entry['time'])); ?></td>
                            <td><span class="log-type log-type-<?php echo esc_attr($entry['type']); ?>"><?php echo esc_html(ucfirst($entry['type'])); ?></span></td>
                            <td><?php echo esc_html($entry['message']); ?></td>
                            <td>
                                <?php if (!empty($entry['file'])) : ?>
                                    <?php echo esc_html(basename($entry['file'])); ?>
                                    <?php if (!empty($entry['line'])) : ?>
                                        :<?php echo (int) $entry['line']; ?>
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
