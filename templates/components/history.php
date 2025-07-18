<?php
$history = get_option('wp_git_plugins_history', array());
$history = array_reverse($history); // Show newest first
?>
<div class="history-container">
    <div class="tablenav top">
        <div class="alignleft actions">
            <button type="button" class="button clear-history" data-type="history">
                <?php esc_html_e('Clear History', 'wp-git-plugins'); ?>
            </button>
            <span class="spinner"></span>
        </div>
        <div class="tablenav-pages">
            <span class="displaying-num">
                <?php echo esc_html(sprintf(_n('%s item', '%s items', count($history), 'wp-git-plugins'), number_format_i18n(count($history)))); ?>
            </span>
        </div>
    </div>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('Date/Time', 'wp-git-plugins'); ?></th>
                <th><?php esc_html_e('Action', 'wp-git-plugins'); ?></th>
                <th><?php esc_html_e('Repository', 'wp-git-plugins'); ?></th>
                <th><?php esc_html_e('Details', 'wp-git-plugins'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($history)) : ?>
                <tr>
                    <td colspan="4"><?php esc_html_e('No history available.', 'wp-git-plugins'); ?></td>
                </tr>
            <?php else : ?>
                <?php foreach ($history as $entry) : ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', $entry['time'])); ?></td>
                        <td><span class="action-<?php echo esc_attr($entry['action']); ?>"><?php echo esc_html(ucfirst($entry['action'])); ?></span></td>
                        <td><?php echo esc_html($entry['repo_name'] ?? 'N/A'); ?></td>
                        <td><?php echo esc_html($entry['message'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>