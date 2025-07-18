<?php
/**
 * Handles error logging for WP Git Plugins
 *
 * @package WP_Git_Plugins
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Error_Handler {
    /**
     * The single instance of the class.
     *
     * @var WP_Git_Plugins_Error_Handler
     */
    private static $instance = null;

    /**
     * Path to the error log file
     *
     * @var string
     */
    private $error_log_file;

    /**
     * Maximum number of log entries to keep
     *
     * @var int
     */
    private $max_entries = 1000;

    /**
     * Get the single instance of the class
     *
     * @return WP_Git_Plugins_Error_Handler
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->error_log_file = ABSPATH . '/wp-content/debug.log';
        
        // Create log file if it doesn't exist
        if (!file_exists($this->error_log_file)) {
            file_put_contents($this->error_log_file, '');
            chmod($this->error_log_file, 0666);
        }
        
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Set custom error handler
        set_error_handler([$this, 'handle_error']);
        
        // Set custom exception handler
        set_exception_handler([$this, 'handle_exception']);
        
        // Set shutdown function to catch fatal errors
        register_shutdown_function([$this, 'handle_shutdown']);
    }

    /**
     * Custom error handler
     *
     * @param int $errno Error level
     * @param string $errstr Error message
     * @param string $errfile File where the error occurred
     * @param int $errline Line number where the error occurred
     * @return bool Whether the error was handled
     */
    public function handle_error($errno, $errstr, $errfile, $errline) {
        // Only handle errors that are in our plugin's directory
        if (strpos($errfile, 'wp-git-plugins') === false) {
            return false;
        }

        $error_type = $this->get_error_type($errno);
        
        // Skip if error reporting is turned off for this type of error
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $message = sprintf(
            '[%s] %s: %s in %s on line %d',
            current_time('mysql'),
            $error_type,
            $errstr,
            $errfile,
            $errline
        );

        $this->log_error($message);
        
        // Don't execute PHP internal error handler
        return true;
    }

    /**
     * Handle uncaught exceptions
     *
     * @param Throwable $exception The exception that was thrown
     */
    public function handle_exception($exception) {
        $message = sprintf(
            '[%s] EXCEPTION: %s in %s on line %d\nStack trace:\n%s',
            current_time('mysql'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );

        $this->log_error($message);
    }

    /**
     * Handle fatal errors
     */
    public function handle_shutdown() {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            // Only handle errors that are in our plugin's directory
            if (strpos($error['file'], 'wp-git-plugins') !== false) {
                $message = sprintf(
                    '[%s] FATAL ERROR: %s in %s on line %d',
                    current_time('mysql'),
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
                
                $this->log_error($message);
            }
        }
    }

    /**
     * Log an error message to the log file
     *
     * @param string $message Error message to log
     */
    public function log_error($message) {
        if (empty($message)) {
            return;
        }

        // Add newline if not present
        $message = rtrim($message) . "\n";
        
        // Log to file
        file_put_contents($this->error_log_file, $message, FILE_APPEND);
        
        // Limit log file size
        $this->rotate_logs();
    }

    /**
     * Get error type from error level
     *
     * @param int $errno Error level
     * @return string Error type name
     */
    private function get_error_type($errno) {
        $error_types = [
            E_ERROR             => 'ERROR',
            E_WARNING           => 'WARNING',
            E_PARSE             => 'PARSING ERROR',
            E_NOTICE            => 'NOTICE',
            E_CORE_ERROR        => 'CORE ERROR',
            E_CORE_WARNING      => 'CORE WARNING',
            E_COMPILE_ERROR     => 'COMPILE ERROR',
            E_COMPILE_WARNING   => 'COMPILE WARNING',
            E_USER_ERROR        => 'USER ERROR',
            E_USER_WARNING      => 'USER WARNING',
            E_USER_NOTICE       => 'USER NOTICE',
            E_STRICT            => 'STRICT NOTICE',
            E_RECOVERABLE_ERROR => 'RECOVERABLE ERROR',
            E_DEPRECATED        => 'DEPRECATED',
            E_USER_DEPRECATED   => 'USER DEPRECATED',
        ];

        return isset($error_types[$errno]) ? $error_types[$errno] : 'UNKNOWN';
    }

    /**
     * Rotate log files to prevent them from getting too large
     */
    private function rotate_logs() {
        // Only rotate if file is larger than 5MB
        if (file_exists($this->error_log_file) && filesize($this->error_log_file) > 5 * 1024 * 1024) {
            $backup_file = $this->error_log_file . '.' . date('Y-m-d-His');
            
            // Rename current log file
            rename($this->error_log_file, $backup_file);
            
            // Create new empty log file
            file_put_contents($this->error_log_file, '');
            chmod($this->error_log_file, 0666);
            
            // Clean up old log files
            $this->cleanup_old_logs();
        }
    }
    
    /**
     * Clean up old log files, keeping only the most recent ones
     */
    private function cleanup_old_logs() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'];
        $log_files = glob(trailingslashit($log_dir) . 'wp-git-plugins-error.log.*');
        
        // Sort files by modification time (newest first)
        usort($log_files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Remove files beyond the max_entries limit
        if (count($log_files) > $this->max_entries) {
            $files_to_remove = array_slice($log_files, $this->max_entries);
            foreach ($files_to_remove as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }
    
    /**
     * Get the error log contents
     * 
     * @param bool $filter_fatal Whether to filter for fatal errors
     * @return string|false The log contents or false on failure
     */
    public function get_error_log($filter_fatal = false) {
        if (!file_exists($this->error_log_file)) {
            return '';
        }
        
        $log_content = file_get_contents($this->error_log_file);
        
        if (!$filter_fatal) {
            return $log_content;
        }
        
        // Filter to show only fatal errors
        $lines = explode("\n", $log_content);
        $fatal_errors = [];
        $current_error = [];
        
        foreach ($lines as $line) {
            if (preg_match('/^\[([^\]]+)\] (PHP (?:Fatal|Parse) error):/i', $line)) {
                // If we have a collected error, add it to the results
                if (!empty($current_error)) {
                    $fatal_errors[] = implode("\n", $current_error);
                    $current_error = [];
                }
                $current_error[] = $line;
            } elseif (!empty($current_error)) {
                // If we're collecting an error, continue adding lines
                $current_error[] = $line;
                
                // If this line ends the error (empty line), add to results
                if (trim($line) === '') {
                    $fatal_errors[] = implode("\n", $current_error);
                    $current_error = [];
                }
            }
        }
        
        // Add any remaining error
        if (!empty($current_error)) {
            $fatal_errors[] = implode("\n", $current_error);
        }
        
        return implode("\n\n", $fatal_errors);
    }
    
    /**
     * Clear the error log
     * 
     * @return bool True on success, false on failure
     */
    public function clear_error_log() {
        if (!file_exists($this->error_log_file)) {
            return true;
        }
        
        return file_put_contents($this->error_log_file, '') !== false;
    }
    
    /**
     * Get the path to the error log file
     * 
     * @return string Path to the error log file
     */
    public function get_error_log_path() {
        return $this->error_log_file;
    }
}

// Initialize the error handler
function wp_git_plugins_error_handler() {
    return WP_Git_Plugins_Error_Handler::instance();
}

// Start the error handler
add_action('plugins_loaded', 'wp_git_plugins_error_handler');
