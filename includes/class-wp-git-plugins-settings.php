<?php
class WP_Git_Plugins_Settings {
    private $plugin_name;
    private $version;
    private $options;

    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('wp_git_plugins_options', []);
    }

    public function register_settings() {
        register_setting(
            'wp_git_plugins_options_group',
            'wp_git_plugins_options',
            [$this, 'sanitize_options']
        );

        add_settings_section(
            'wp_git_plugins_github_settings',
            __('GitHub Settings', 'wp-git-plugins'),
            [$this, 'github_settings_section_callback'],
            'wp-git-plugins-settings'
        );

        add_settings_field(
            'github_access_token',
            __('GitHub Access Token', 'wp-git-plugins'),
            [$this, 'github_access_token_callback'],
            'wp-git-plugins-settings',
            'wp_git_plugins_github_settings'
        );

        add_settings_field(
            'check_updates_interval',
            __('Check for Updates', 'wp-git-plugins'),
            [$this, 'check_updates_interval_callback'],
            'wp-git-plugins-settings',
            'wp_git_plugins_github_settings'
        );
    }

    public function github_settings_section_callback() {
        echo '<p>' . esc_html__('Configure your GitHub settings below.', 'wp-git-plugins') . '</p>';
    }

    public function github_access_token_callback() {
        $token = $this->options['github_access_token'] ?? '';
        ?>
        <input type="password" id="github_access_token" name="wp_git_plugins_options[github_access_token]" 
               value="<?php echo esc_attr($token); ?>" class="regular-text" />
        <p class="description">
            <?php esc_html_e('Enter your GitHub Personal Access Token with repo scope for private repositories.', 'wp-git-plugins'); ?>
            <a href="https://github.com/settings/tokens" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('Generate token', 'wp-git-plugins'); ?>
            </a>
        </p>
        <?php
    }

    public function check_updates_interval_callback() {
        $interval = $this->options['check_updates_interval'] ?? 'twicedaily';
        $intervals = [
            'hourly' => __('Hourly', 'wp-git-plugins'),
            'twicedaily' => __('Twice Daily', 'wp-git-plugins'),
            'daily' => __('Daily', 'wp-git-plugins'),
            'weekly' => __('Weekly', 'wp-git-plugins')
        ];
        ?>
        <select id="check_updates_interval" name="wp_git_plugins_options[check_updates_interval]">
            <?php foreach ($intervals as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($interval, $value); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e('How often to check for plugin updates.', 'wp-git-plugins'); ?>
        </p>
        <?php
    }

    public function sanitize_options($input) {
        $sanitized_input = [];

        if (isset($input['github_access_token'])) {
            $sanitized_input['github_access_token'] = sanitize_text_field($input['github_access_token']);
        }

        if (isset($input['check_updates_interval'])) {
            $valid_intervals = ['hourly', 'twicedaily', 'daily', 'weekly'];
            $sanitized_input['check_updates_interval'] = in_array($input['check_updates_interval'], $valid_intervals)
                ? $input['check_updates_interval']
                : 'twicedaily';
        }

        return $sanitized_input;
    }
}
