<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WP_Git_Plugins_Settings {
    private $plugin_name;
    private $version;
    private $db;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->db = WP_Git_Plugins_DB::get_instance();
    }

    /**
     * Register settings with WordPress
     */
    public function register_settings() {
        // Register the settings group
        register_setting(
            'wpgp_settings_group', // Option group
            'wpgp_settings',      // Option name
            [$this, 'sanitize_settings'] // Sanitize callback
        );

        // Add settings section
        add_settings_section(
            'wpgp_github_settings_section',
            __('GitHub Settings', 'wp-git-plugins'),
            [$this, 'github_settings_section_callback'],
            'wp-git-plugins-settings'
        );

        // Add settings fields
        add_settings_field(
            'github_username',
            __('GitHub Username', 'wp-git-plugins'),
            [$this, 'github_username_field_callback'],
            'wp-git-plugins-settings',
            'wpgp_github_settings_section'
        );

        add_settings_field(
            'github_token',
            __('GitHub Access Token', 'wp-git-plugins'),
            [$this, 'github_token_field_callback'],
            'wp-git-plugins-settings',
            'wpgp_github_settings_section'
        );

        // Add modules section
        add_settings_section(
            'wpgp_modules_settings_section',
            __('Modules & Addons', 'wp-git-plugins'),
            [$this, 'modules_settings_section_callback'],
            'wp-git-plugins-settings'
        );

        // Add modules management field
        add_settings_field(
            'modules_management',
            __('Module Management', 'wp-git-plugins'),
            [$this, 'modules_management_field_callback'],
            'wp-git-plugins-settings',
            'wpgp_modules_settings_section'
        );
    }

    /**
     * Sanitize settings before saving
     */
    public function sanitize_settings($input) {
        $current_settings = get_option('wpgp_settings', []);
        $sanitized = [];
        
        if (isset($input['github_username'])) {
            $sanitized['github_username'] = sanitize_text_field($input['github_username']);
            $this->set_github_username($sanitized['github_username']);
        }
        
        if (isset($input['github_token'])) {
            // Only update token if it's not just the masked value
            $current_token = $this->get_github_token();
            if ($input['github_token'] !== $this->mask_token($current_token)) {
                $sanitized['github_token'] = sanitize_text_field($input['github_token']);
                $this->set_github_token($sanitized['github_token']);
            } elseif (isset($current_settings['github_token'])) {
                // retain existing masked token value
                $sanitized['github_token'] = $current_settings['github_token'];
            }
        }

        // Add success notice
        add_settings_error(
            'wpgp_messages',
            'wpgp_message_saved',
            __('Settings saved.', 'wp-git-plugins'),
            'updated'
        );

        return $sanitized;
    }

    /**
     * GitHub settings section callback
     */
    public function github_settings_section_callback() {
        echo '<p>' . esc_html__('Configure your GitHub settings below.', 'wp-git-plugins') . '</p>';
    }

    /**
     * GitHub username field callback
     */
    public function github_username_field_callback() {
        $username = $this->get_github_username();
        echo '<input type="text" id="github_username" name="wpgp_settings[github_username]" value="' . esc_attr($username) . '" class="regular-text" />';
    }

    /**
     * GitHub token field callback
     */
    public function github_token_field_callback() {
        $token = $this->get_github_token();
        echo '<input type="password" id="github_token" name="wpgp_settings[github_token]" value="' . esc_attr($this->mask_token($token)) . '" class="regular-text" autocomplete="off" />';
        echo '<p class="description">' . sprintf(
            __('<a href="%s" target="_blank">Generate a new token</a> with the <code>repo</code> scope.', 'wp-git-plugins'),
            'https://github.com/settings/tokens/new?scopes=repo&description=WP%20Git%20Plugins'
        ) . '</p>';
        echo '<p class="description">' . __('For security, the token is masked. Only enter a new token to update it.', 'wp-git-plugins') . '</p>';
    }

    /**
     * Get all settings as an associative array
     */
    public function get_all_settings() {
        return $this->db->get_settings();
    }

    /**
     * Get a single setting
     */
    public function get_setting($key, $default = '') {
        return $this->db->get_setting($key, $default);
    }

    /**
     * Get GitHub username
     */
    public function get_github_username() {
        return $this->get_setting('github_username');
    }

    /**
     * Set GitHub username
     */
    public function set_github_username($username) {
        return $this->db->update_setting('github_username', sanitize_text_field($username));
    }

    /**
     * Get GitHub access token
     */
    public function get_github_token() {
        return $this->get_setting('github_token');
    }

    /**
     * Set GitHub access token
     */
    public function set_github_token($token) {
        return $this->db->update_setting('github_token', sanitize_text_field($token));
    }

    /**
     * Modules settings section callback
     */
    public function modules_settings_section_callback() {
        echo '<p>' . esc_html__('Upload and manage plugin modules to extend functionality.', 'wp-git-plugins') . '</p>';
    }

    /**
     * Modules management field callback
     */
    public function modules_management_field_callback() {
        $modules_manager = WP_Git_Plugins_Modules::get_instance();
        $available_modules = $modules_manager->get_available_modules();
        $active_modules = get_option('wpgp_active_modules', []);
        ?>
        <div id="modules-management">
            <!-- Upload Module Section -->
            <div class="module-upload-section" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">
                <h4><?php esc_html_e('Upload New Module', 'wp-git-plugins'); ?></h4>
                <form id="module-upload-form" enctype="multipart/form-data" style="margin: 0;">
                    <p>
                        <input type="file" id="module-file" name="module_file" accept=".zip" required />
                        <span class="description"><?php esc_html_e('Upload a ZIP file containing a module.', 'wp-git-plugins'); ?></span>
                    </p>
                    <p>
                        <button type="submit" class="button button-secondary">
                            <span class="dashicons dashicons-upload"></span>
                            <?php esc_html_e('Upload Module', 'wp-git-plugins'); ?>
                        </button>
                        <span class="spinner" style="float: none; margin-left: 10px;"></span>
                    </p>
                </form>
            </div>

            <!-- Installed Modules Section -->
            <div class="installed-modules-section">
                <h4><?php esc_html_e('Installed Modules', 'wp-git-plugins'); ?></h4>
                <?php if (!empty($available_modules)) : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th scope="col"><?php esc_html_e('Module', 'wp-git-plugins'); ?></th>
                                <th scope="col"><?php esc_html_e('Description', 'wp-git-plugins'); ?></th>
                                <th scope="col"><?php esc_html_e('Version', 'wp-git-plugins'); ?></th>
                                <th scope="col"><?php esc_html_e('Status', 'wp-git-plugins'); ?></th>
                                <th scope="col"><?php esc_html_e('Actions', 'wp-git-plugins'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($available_modules as $module_slug => $module) : ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($module['Name'] ?? $module_slug); ?></strong>
                                        <?php if (!empty($module['Author'])) : ?>
                                            <br><small><?php printf(__('By %s', 'wp-git-plugins'), esc_html($module['Author'])); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($module['Description'] ?? __('No description available.', 'wp-git-plugins')); ?>
                                    </td>
                                    <td>
                                        <?php echo esc_html($module['Version'] ?? '1.0.0'); ?>
                                    </td>
                                    <td>
                                        <?php if (in_array($module_slug, $active_modules)) : ?>
                                            <span class="module-status active" style="color: #00a32a; font-weight: 500;">
                                                <span class="dashicons dashicons-yes-alt"></span>
                                                <?php esc_html_e('Active', 'wp-git-plugins'); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="module-status inactive" style="color: #646970;">
                                                <span class="dashicons dashicons-minus"></span>
                                                <?php esc_html_e('Inactive', 'wp-git-plugins'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="module-actions" style="white-space: nowrap;">
                                            <?php if (in_array($module_slug, $active_modules)) : ?>
                                                <button class="button button-small deactivate-module" 
                                                        data-module="<?php echo esc_attr($module_slug); ?>"
                                                        title="<?php esc_attr_e('Deactivate module', 'wp-git-plugins'); ?>">
                                                    <?php esc_html_e('Deactivate', 'wp-git-plugins'); ?>
                                                </button>
                                            <?php else : ?>
                                                <button class="button button-primary button-small activate-module" 
                                                        data-module="<?php echo esc_attr($module_slug); ?>"
                                                        title="<?php esc_attr_e('Activate module', 'wp-git-plugins'); ?>">
                                                    <?php esc_html_e('Activate', 'wp-git-plugins'); ?>
                                                </button>
                                            <?php endif; ?>
                                            <button class="button button-small button-link-delete delete-module" 
                                                    data-module="<?php echo esc_attr($module_slug); ?>"
                                                    title="<?php esc_attr_e('Delete module', 'wp-git-plugins'); ?>">
                                                <?php esc_html_e('Delete', 'wp-git-plugins'); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p><?php esc_html_e('No modules installed yet.', 'wp-git-plugins'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Mask a token for display (shows only first and last 4 characters)
     */
    public function mask_token($token) {
        if (empty($token)) {
            return '';
        }
        
        $length = strlen($token);
        if ($length <= 8) {
            return str_repeat('•', $length);
        }
        
        return substr($token, 0, 4) . str_repeat('•', $length - 8) . substr($token, -4);
    }
}