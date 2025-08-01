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
}

// Set the error handler
set_error_handler(array('WP_Git_Plugins_Debug', 'error_handler'));