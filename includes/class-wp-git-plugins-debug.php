<?php
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Debug {
    /**
     * Log an action to the history
     */
    public static function log_history($action, $repo_name, $message = '', $repo_id = 0) {
        $history = get_option('wp_git_plugins_history', array());
        if (count($history) >= 1000) {
            $history = array_slice($history, -999, 999);
        }
        $history[] = array(
            'time' => current_time('timestamp'),
            'action' => $action,
            'repo_id' => $repo_id,
            'repo_name' => $repo_name,
            'message' => $message,
        );
        update_option('wp_git_plugins_history', $history);
    }

    /**
     * Log an error
     */
    public static function log_error($message, $file = '', $line = 0, $trace = '') {
        $error_log = get_option('wp_git_plugins_error_log', array());
        if (count($error_log) >= 500) {
            $error_log = array_slice($error_log, -499, 499);
        }
        $error_log[] = array(
            'time' => current_time('timestamp'),
            'message' => $message,
            'file' => $file,
            'line' => $line,
            'trace' => $trace,
        );
        update_option('wp_git_plugins_error_log', $error_log);
    }

    /**
     * Log a console message
     */
    public static function log_console($type, $message, $file = '', $line = 0) {
        $console_log = get_option('wp_git_plugins_console_log', array());
        if (count($console_log) >= 1000) {
            $console_log = array_slice($console_log, -999, 999);
        }
        $console_log[] = array(
            'time' => current_time('timestamp'),
            'type' => $type,
            'message' => $message,
            'file' => $file,
            'line' => $line,
        );
        update_option('wp_git_plugins_console_log', $console_log);
    }

    /**
     * Error handler to catch PHP errors
     */
    public static function error_handler($errno, $errstr, $errfile = '', $errline = 0, $errcontext = null) {
        if (!(error_reporting() & $errno)) {
            return false;
        }
        $error_types = array(
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        );
        $error_type = isset($error_types[$errno]) ? $error_types[$errno] : 'Unknown Error';
        $error_message = "[$error_type] $errstr in $errfile on line $errline";
        self::log_error($error_message, $errfile, $errline, function_exists('wp_debug_backtrace_summary') ? wp_debug_backtrace_summary() : '');
        return false;
    }

    /**
     * AJAX: Clear log/history
     */
    public static function ajax_clear_log() {
        check_ajax_referer('wp_git_plugins_debug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wp-git-plugins'));
            return;
        }
        $log_type = isset($_POST['log_type']) ? sanitize_text_field($_POST['log_type']) : '';
        switch ($log_type) {
            case 'history':
                update_option('wp_git_plugins_history', array());
                break;
            case 'error-log':
                update_option('wp_git_plugins_error_log', array());
                break;
            case 'console-log':
                update_option('wp_git_plugins_console_log', array());
                break;
            default:
                wp_send_json_error(__('Invalid log type.', 'wp-git-plugins'));
                return;
        }
        wp_send_json_success();
    }

    /**
     * AJAX: Check GitHub rate limit
     */
    public static function ajax_check_rate_limit() {
        check_ajax_referer('wp_git_plugins_debug_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('You do not have permission to perform this action.', 'wp-git-plugins'));
            return;
        }
        $settings = get_option('wp_git_plugins_settings', array());
        $github_token = isset($settings['github_token']) ? $settings['github_token'] : '';
        if (empty($github_token)) {
            wp_send_json_error(__('GitHub token not configured.', 'wp-git-plugins'));
            return;
        }
        
        // Use the GitHub API class
        $github_api = WP_Git_Plugins_Github_API::get_instance($github_token);
        $rate_limit_data = $github_api->check_rate_limit();
        
        if (is_wp_error($rate_limit_data)) {
            wp_send_json_error($rate_limit_data->get_error_message());
            return;
        }
        
        if (isset($rate_limit_data['resources']['core'])) {
            wp_send_json_success(array(
                'limit' => $rate_limit_data['resources']['core']['limit'],
                'remaining' => $rate_limit_data['resources']['core']['remaining'],
                'reset' => $rate_limit_data['resources']['core']['reset'],
            ));
        } else {
            wp_send_json_error(__('Could not retrieve rate limit information.', 'wp-git-plugins'));
        }
    }
}

// Set the error handler
set_error_handler(array('WP_Git_Plugins_Debug', 'error_handler'));