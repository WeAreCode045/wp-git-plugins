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

        // Add settings section for GitHub tab
        add_settings_section(
            'wpgp_github_settings_section',
            __('GitHub Settings', 'wp-git-plugins'),
            [$this, 'github_settings_section_callback'],
            'wp-git-plugins-github-settings'
        );

        // Add settings fields
        add_settings_field(
            'github_username',
            __('GitHub Username', 'wp-git-plugins'),
            [$this, 'github_username_field_callback'],
            'wp-git-plugins-github-settings',
            'wpgp_github_settings_section'
        );

        add_settings_field(
            'github_token',
            __('GitHub Access Token', 'wp-git-plugins'),
            [$this, 'github_token_field_callback'],
            'wp-git-plugins-github-settings',
            'wpgp_github_settings_section'
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