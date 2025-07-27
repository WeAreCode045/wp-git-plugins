
<?php
if (!defined('ABSPATH')) exit;

class WPGP_GitHub_Profile_Widget {
    public function __construct() {
        add_action('wp_git_plugins_sidebar_widgets', [$this, 'render_widget']);
    }

    public function render_widget() {
        $settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
        $token = $settings->get_github_token();
        $username = $settings->get_github_username();
        if (empty($token) || empty($username)) {
            echo '<div class="wp-git-plugins-card"><p>GitHub account not connected.</p></div>';
            return;
        }
   /**
 * Get GitHub user profile data (for widget use only)
 *
 * @param string $username
 * @param string $token Optional GitHub token for authenticated requests
 * @return array|WP_Error
 */
function wpgp_github_profile_widget_get_user_profile($username, $token = '') {
    if (empty($username)) {
        return new WP_Error('invalid_username', 'Username is required');
    }
    $api_base = 'https://api.github.com';
    $url = $api_base . '/users/' . urlencode($username);
    $args = [
        'timeout' => 30,
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'WP-Git-Plugins/1.0',
        ],
    ];
    if (!empty($token)) {
        $args['headers']['Authorization'] = 'token ' . $token;
    }
    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        return $response;
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (!is_array($data) || !isset($data['login'])) {
        return new WP_Error('invalid_response', 'Invalid response from GitHub API');
    }
    return $data;
}     
        $profile = wpgp_github_profile_widget_get_user_profile($username, $token);
        if (is_wp_error($profile) || empty($profile['login'])) {
            echo '<div class="wp-git-plugins-card"><p>Could not fetch GitHub profile.</p></div>';
            return;
        }
        ?>
        <div class="wp-git-plugins-card github-profile-widget">
            <div style="text-align:center;">
                <img src="<?php echo esc_url($profile['avatar_url']); ?>" alt="Avatar" style="width:80px;height:80px;border-radius:50%;margin-bottom:10px;">
                <h3 style="margin:0;">@<?php echo esc_html($profile['login']); ?></h3>
                <?php if (!empty($profile['email'])): ?>
                    <p style="margin:0;"><a href="mailto:<?php echo esc_attr($profile['email']); ?>"><?php echo esc_html($profile['email']); ?></a></p>
                <?php endif; ?>
                <p style="margin:0;"><a href="<?php echo esc_url($profile['html_url']); ?>" target="_blank">View on GitHub</a></p>
            </div>
        </div>
        <?php
    }
}

if (class_exists('WP_Git_Plugins_Modules')) {
    new WPGP_GitHub_Profile_Widget();
}
