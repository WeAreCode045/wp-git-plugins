<?php
/**
 * Debug Tabs Component
 *
 * @package WP_Git_Plugins
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Get error handler instance
$error_handler = WP_Git_Plugins_Error_Handler::instance();
$error_log = $error_handler->get_error_log();
$error_log_path = $error_handler->get_error_log_path();
?>

<div class="nav-tab-wrapper" id="debug-tabs">
    <a href="#history-tab" class="nav-tab nav-tab-active"><?php esc_html_e('History', 'wp-git-plugins'); ?></a>
    <a href="#error-log-tab" class="nav-tab"><?php esc_html_e('Error Log', 'wp-git-plugins'); ?></a>
</div>

<div id="history-tab" class="tab-content" style="display: block;">
    <div class="debug-actions">
        <form method="post" action="" class="clear-form" style="display: inline-block; margin: 0 0 15px 0;">
            <?php wp_nonce_field('clear_debug_log'); ?>
            <button type="submit" name="clear_debug_log" class="button button-secondary">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear History', 'wp-git-plugins'); ?>
            </button>
        </form>
    </div>
    
    <div class="debug-log-entries">
        <?php if (!empty($debug_log)) : ?>
            <?php 
            // Group entries by plugin and action
            $grouped_entries = [];
            foreach (array_reverse($debug_log) as $entry) {
                // Extract plugin name and action from message if possible
                $plugin_name = '';
                $action = '';
                
                if (preg_match('/Plugin \"([^\"]+)\" (installed|updated|activated|deleted|removed)/i', $entry['message'], $matches)) {
                    $plugin_name = $matches[1];
                    $action = $matches[2];
                    $group_key = $plugin_name . '|' . $action;
                    
                    if (!isset($grouped_entries[$group_key])) {
                        $grouped_entries[$group_key] = [
                            'plugin' => $plugin_name,
                            'action' => $action,
                            'entries' => [],
                            'first_seen' => $entry['time'],
                            'last_seen' => $entry['time']
                        ];
                    }
                    
                    $grouped_entries[$group_key]['entries'][] = $entry;
                    
                    // Update first and last seen times
                    if (strtotime($entry['time']) < strtotime($grouped_entries[$group_key]['first_seen'])) {
                        $grouped_entries[$group_key]['first_seen'] = $entry['time'];
                    }
                    if (strtotime($entry['time']) > strtotime($grouped_entries[$group_key]['last_seen'])) {
                        $grouped_entries[$group_key]['last_seen'] = $entry['time'];
                    }
                } else {
                    // Add non-matching entries as-is
                    $grouped_entries[] = ['single' => true, 'entry' => $entry];
                }
            }
            ?>
            
            <?php foreach ($grouped_entries as $group) : ?>
                <?php if (isset($group['single'])) : ?>
                    <div class="debug-entry">
                        <div class="debug-time">
                            <span class="dashicons dashicons-clock"></span> 
                            <?php echo esc_html($group['entry']['time']); ?>
                        </div>
                        <div class="debug-message">
                            <?php echo wp_kses_post(nl2br(esc_html($group['entry']['message']))); ?>
                        </div>
                        <?php if (!empty($group['entry']['data'])) : ?>
                            <div class="debug-data">
                                <pre><code><?php echo esc_html(print_r($group['entry']['data'], true)); ?></code></pre>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="debug-entry grouped-entry">
                        <div class="debug-header">
                            <div class="debug-time">
                                <span class="dashicons dashicons-update"></span>
                                <?php 
                                $timespan = $group['first_seen'] === $group['last_seen'] 
                                    ? esc_html($group['first_seen'])
                                    : sprintf(
                                        __('%1$s - %2$s', 'wp-git-plugins'),
                                        esc_html($group['first_seen']),
                                        esc_html($group['last_seen'])
                                      );
                                echo $timespan;
                                ?>
                                <span class="entry-count">
                                    <?php 
                                    $count = count($group['entries']);
                                    printf(
                                        _n('(%d occurrence)', '(%d occurrences)', $count, 'wp-git-plugins'),
                                        $count
                                    );
                                    ?>
                                </span>
                            </div>
                            <div class="debug-message">
                                <?php 
                                $action_label = ucfirst($group['action']);
                                $plugin_name = esc_html($group['plugin']);
                                echo "<strong>{$action_label} plugin:</strong> {$plugin_name}";
                                ?>
                            </div>
                            <button class="toggle-details button button-small" aria-expanded="false">
                                <span class="show-details"><?php esc_html_e('Show Details', 'wp-git-plugins'); ?></span>
                                <span class="hide-details" style="display:none;"><?php esc_html_e('Hide Details', 'wp-git-plugins'); ?></span>
                            </button>
                        </div>
                        <div class="debug-details" style="display:none;">
                            <?php foreach ($group['entries'] as $entry) : ?>
                                <div class="debug-entry-detail">
                                    <div class="debug-time">
                                        <span class="dashicons dashicons-clock"></span> 
                                        <?php echo esc_html($entry['time']); ?>
                                    </div>
                                    <div class="debug-message">
                                        <?php echo wp_kses_post(nl2br(esc_html($entry['message']))); ?>
                                    </div>
                                    <?php if (!empty($entry['data'])) : ?>
                                        <div class="debug-data">
                                            <pre><code><?php echo esc_html(print_r($entry['data'], true)); ?></code></pre>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('No debug history available.', 'wp-git-plugins'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="error-log-tab" class="tab-content" style="display: none;">
    <div class="debug-actions">
        <div class="log-file-info" style="margin-bottom: 15px;">
            <p style="margin: 0 0 10px 0;">
                <strong><?php esc_html_e('Log file location:', 'wp-git-plugins'); ?></strong><br>
                <code style="word-break: break-all; display: inline-block; max-width: 100%;"><?php echo esc_html($error_log_path); ?></code>
            </p>
        </div>
        <div class="log-filters" style="margin-bottom: 15px;">
            <label>
                <input type="checkbox" id="filter-fatal-errors" name="filter_fatal_errors" value="1">
                <?php esc_html_e('Show only fatal errors', 'wp-git-plugins'); ?>
            </label>
        </div>
        <form method="post" action="" class="clear-form" style="display: inline-block; margin: 0 0 15px 0;">
            <?php wp_nonce_field('clear_error_log'); ?>
            <button type="submit" name="clear_error_log" class="button button-secondary">
                <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Error Log', 'wp-git-plugins'); ?>
            </button>
        </form>
    </div>
    
    <div class="code-block" id="error-log-content">
        <?php if (!empty($error_log)) : ?>
            <pre><code><?php echo esc_html($error_log); ?></code></pre>
        <?php else : ?>
            <div class="notice notice-info">
                <p><?php esc_html_e('No errors logged yet.', 'wp-git-plugins'); ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>
