<?php
/**
 * WP Git Plugins Modules Class
 *
 * Handles module management including loading, activating, and deactivating modules.
 *
 * @package    WP_Git_Plugins
 * @subpackage Modules
 * @author     WeAreCode045 

 * WP Git Plugins Modules Manager
 *
 * Handles module/addon system including upload, extraction, and loading of modules
 *
 * @package    WP_Git_Plugins
 * @subpackage Modules
 * @author     WeAreCode045 <info@code045.nl>
 * @license    GPL-2.0+
 * @since      1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Git_Plugins_Modules {

    /**
     * Single instance of the class
     */
    private static $instance = null;

    /**
     * Modules directory path
     */
    private $modules_dir;

    /**
     * Active modules
     */
    private $active_modules = [];

    /**
     * Available modules
     */
    private $available_modules = [];

    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->modules_dir = WP_GIT_PLUGINS_DIR . 'modules';
        $this->init();
    }

    /**
     * Initialize modules system
     */
    private function init() {
        // Ensure modules directory exists
        if (!file_exists($this->modules_dir)) {
            wp_mkdir_p($this->modules_dir);
        }

        // Register AJAX handlers
        add_action('wp_ajax_wpgp_upload_module', array($this, 'ajax_upload_module'));
        add_action('wp_ajax_wpgp_activate_module', function () {
            error_log('AJAX handler called for module activation.');

            // Verify the nonce
            if (!check_ajax_referer('wpgp_module_nonce', '_ajax_nonce', false)) {
                error_log('Nonce verification failed.');
                wp_send_json_error(['message' => 'Invalid nonce.']);
            }

            $module_slug = sanitize_text_field($_POST['module_slug'] ?? '');
            error_log('Module slug: ' . $module_slug);

            if (empty($module_slug)) {
                error_log('Module slug is missing.');
                wp_send_json_error(['message' => 'Module slug is missing.']);
            }

            if (!class_exists('WP_Git_Plugins_Modules')) {
                error_log('Modules manager class not found.');
                wp_send_json_error(['message' => 'Modules manager not found.']);
            }

            $modules_manager = WP_Git_Plugins_Modules::get_instance();
            $result = $modules_manager->activate_module($module_slug);

            if ($result === true) {
                error_log("Module '{$module_slug}' activated successfully.");
                wp_send_json_success(['message' => "Module '{$module_slug}' activated successfully."]);
            } else {
                error_log('Activation failed.');
                wp_send_json_error(['message' => is_string($result) ? $result : 'Activation failed.']);
            }
        });
        add_action('wp_ajax_wpgp_deactivate_module', array($this, 'ajax_deactivate_module'));
        add_action('wp_ajax_wpgp_delete_module', array($this, 'ajax_delete_module'));

        // Load modules on init
        add_action('init', array($this, 'load_modules'));
    }

    /**
     * Get modules directory path
     */
    public function get_modules_dir() {
        return $this->modules_dir;
    }

    /**
     * Scan for available modules
     */
    public function scan_modules() {
        $this->available_modules = [];
        
        if (!is_dir($this->modules_dir)) {
            return $this->available_modules;
        }

        $module_dirs = glob($this->modules_dir . '/*', GLOB_ONLYDIR);
        
        foreach ($module_dirs as $module_dir) {
            $module_name = basename($module_dir);
            $module_file = $module_dir . '/' . $module_name . '.php';
            
            if (file_exists($module_file)) {
                $module_data = $this->get_module_data($module_file);
                if ($module_data) {
                    $this->available_modules[$module_name] = array_merge($module_data, [
                        'path' => $module_dir,
                        'file' => $module_file,
                        'slug' => $module_name
                    ]);
                }
            }
        }

        return $this->available_modules;
    }

    /**
     * Get module data from module file header
     */
    private function get_module_data($module_file) {
        $default_headers = [
            'Name' => 'Module Name',
            'Description' => 'Description',
            'Version' => 'Version',
            'Author' => 'Author',
            'Requires' => 'Requires',
            'Tested' => 'Tested up to',
        ];

        return get_file_data($module_file, $default_headers);
    }

    /**
     * Load active modules
     */
    public function load_modules() {
        $this->scan_modules();
        $active_modules = get_option('wpgp_active_modules', []);

        foreach ($active_modules as $module_slug) {
            if (isset($this->available_modules[$module_slug])) {
                $module = $this->available_modules[$module_slug];
                
                if (file_exists($module['file'])) {
                    include_once $module['file'];
                    $this->active_modules[$module_slug] = $module;
                    
                    // Trigger module activation hook
                    do_action('wpgp_module_loaded', $module_slug, $module);
                }
            }
        }
    }

    /**
     * Activate a module
     */
    public function activate_module($module_slug) {
        if (!isset($this->available_modules[$module_slug])) {
            return new WP_Error('module_not_found', __('Module not found.', 'wp-git-plugins'));
        }

        $active_modules = get_option('wpgp_active_modules', []);
        
        if (!in_array($module_slug, $active_modules)) {
            $active_modules[] = $module_slug;
            update_option('wpgp_active_modules', $active_modules);
            
            // Load the module immediately
            $module = $this->available_modules[$module_slug];
            if (file_exists($module['file'])) {
                include_once $module['file'];
                $this->active_modules[$module_slug] = $module;
                
                // Trigger activation hook
                do_action('wpgp_module_activated', $module_slug, $module);
            }
        }

        return true;
    }

    /**
     * Deactivate a module
     */
    public function deactivate_module($module_slug) {
        $active_modules = get_option('wpgp_active_modules', []);
        $active_modules = array_diff($active_modules, [$module_slug]);
        update_option('wpgp_active_modules', $active_modules);
        
        // Remove from active modules
        unset($this->active_modules[$module_slug]);
        
        // Trigger deactivation hook
        do_action('wpgp_module_deactivated', $module_slug);
        
        return true;
    }

    /**
     * Delete a module
     */
    public function delete_module($module_slug) {
        // First deactivate if active
        $this->deactivate_module($module_slug);
        
        if (!isset($this->available_modules[$module_slug])) {
            return new WP_Error('module_not_found', __('Module not found.', 'wp-git-plugins'));
        }

        $module = $this->available_modules[$module_slug];
        $result = WP_Git_Plugins::rrmdir($module['path']);
        
        if ($result) {
            unset($this->available_modules[$module_slug]);
            do_action('wpgp_module_deleted', $module_slug);
        }
        
        return $result;
    }

    /**
     * Upload and extract module
     */
    public function upload_module($file_array) {
        // Validate file upload
        if (!isset($file_array['tmp_name']) || !is_uploaded_file($file_array['tmp_name'])) {
            return new WP_Error('upload_error', __('File upload failed.', 'wp-git-plugins'));
        }

        // Check file type
        $file_type = wp_check_filetype($file_array['name']);
        if ($file_type['ext'] !== 'zip') {
            return new WP_Error('invalid_file_type', __('Only ZIP files are allowed.', 'wp-git-plugins'));
        }

        // Extract ZIP file
        WP_Filesystem();
        global $wp_filesystem;
        
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wpgp-temp-' . uniqid();
        
        // Create temp directory
        if (!wp_mkdir_p($temp_dir)) {
            return new WP_Error('temp_dir_failed', __('Failed to create temporary directory.', 'wp-git-plugins'));
        }

        // Unzip file
        $result = unzip_file($file_array['tmp_name'], $temp_dir);
        if (is_wp_error($result)) {
            WP_Git_Plugins::rrmdir($temp_dir);
            return $result;
        }

        // Find module directory (should contain a PHP file with module header)
        $module_dir = $this->find_module_directory($temp_dir);
        if (!$module_dir) {
            WP_Git_Plugins::rrmdir($temp_dir);
            return new WP_Error('invalid_module', __('Invalid module structure. Module must contain a main PHP file with module headers.', 'wp-git-plugins'));
        }

        $module_name = basename($module_dir);
        $target_dir = $this->modules_dir . '/' . $module_name;

        // Check if module already exists
        if (file_exists($target_dir)) {
            WP_Git_Plugins::rrmdir($temp_dir);
            return new WP_Error('module_exists', sprintf(__('Module "%s" already exists.', 'wp-git-plugins'), $module_name));
        }

        // Move module to modules directory
        if (!rename($module_dir, $target_dir)) {
            WP_Git_Plugins::rrmdir($temp_dir);
            return new WP_Error('move_failed', __('Failed to move module to modules directory.', 'wp-git-plugins'));
        }

        // Clean up temp directory
        WP_Git_Plugins::rrmdir($temp_dir);

        // Rescan modules
        $this->scan_modules();

        return $module_name;
    }

    /**
     * Find module directory in extracted files
     */
    private function find_module_directory($temp_dir) {
        $items = scandir($temp_dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $item_path = $temp_dir . '/' . $item;
            
            if (is_dir($item_path)) {
                // Check if this directory contains a valid module
                $module_file = $item_path . '/' . $item . '.php';
                if (file_exists($module_file)) {
                    $module_data = $this->get_module_data($module_file);
                    if (!empty($module_data['Name'])) {
                        return $item_path;
                    }
                }
            }
        }
        
        return false;
    }

    /**
     * Get list of available modules
     */
    public function get_available_modules() {
        return $this->available_modules;
    }

    /**
     * Get list of active modules
     */
    public function get_active_modules() {
        return $this->active_modules;
    }

    /**
     * Check if module is active
     */
    public function is_module_active($module_slug) {
        return isset($this->active_modules[$module_slug]);
    }

    /**
     * AJAX handler for module upload
     */
    public function ajax_upload_module() {
        try {
            WP_Git_Plugins::verify_ajax_request();

            if (!isset($_FILES['module_file'])) {
                throw new Exception(__('No file uploaded.', 'wp-git-plugins'));
            }

            $result = $this->upload_module($_FILES['module_file']);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_send_json_success([
                'message' => sprintf(__('Module "%s" uploaded successfully.', 'wp-git-plugins'), $result),
                'module_slug' => $result
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for module activation
     */
    public function ajax_activate_module() {
        try {
            WP_Git_Plugins::verify_ajax_request();

            $module_slug = isset($_POST['module_slug']) ? sanitize_text_field($_POST['module_slug']) : '';
            
            if (empty($module_slug)) {
                throw new Exception(__('Module slug is required.', 'wp-git-plugins'));
            }

            $result = $this->activate_module($module_slug);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_send_json_success([
                'message' => sprintf(__('Module "%s" activated successfully.', 'wp-git-plugins'), $module_slug)
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for module deactivation
     */
    public function ajax_deactivate_module() {
        try {
            WP_Git_Plugins::verify_ajax_request();

            $module_slug = isset($_POST['module_slug']) ? sanitize_text_field($_POST['module_slug']) : '';
            
            if (empty($module_slug)) {
                throw new Exception(__('Module slug is required.', 'wp-git-plugins'));
            }

            $result = $this->deactivate_module($module_slug);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_send_json_success([
                'message' => sprintf(__('Module "%s" deactivated successfully.', 'wp-git-plugins'), $module_slug)
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * AJAX handler for module deletion
     */
    public function ajax_delete_module() {
        try {
            WP_Git_Plugins::verify_ajax_request();

            $module_slug = isset($_POST['module_slug']) ? sanitize_text_field($_POST['module_slug']) : '';
            
            if (empty($module_slug)) {
                throw new Exception(__('Module slug is required.', 'wp-git-plugins'));
            }

            $result = $this->delete_module($module_slug);
            
            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message());
            }

            wp_send_json_success([
                'message' => sprintf(__('Module "%s" deleted successfully.', 'wp-git-plugins'), $module_slug)
            ]);

        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
}
