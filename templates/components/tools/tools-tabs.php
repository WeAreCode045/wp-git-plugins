<?php
/**
 * Tools Tabs Component
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

<div class="nav-tab-wrapper" id="tools-tabs">
    <a href="#history-tab" class="nav-tab nav-tab-active"><?php esc_html_e('History', 'wp-git-plugins'); ?></a>
    <a href="#error-log-tab" class="nav-tab"><?php esc_html_e('Error Log', 'wp-git-plugins'); ?></a>
    <a href="#console-log-tab" class="nav-tab"><?php esc_html_e('Console Log', 'wp-git-plugins'); ?></a>
</div>
