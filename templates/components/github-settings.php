<?php
// /templates/components/github-settings.php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wpgp_github_nonce'])) {
    $settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
    $settings->save_github_settings();
}

// Get current settings
$settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
$github_username = $settings->get_github_username();
$github_token = $settings->get_github_token();
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('GitHub Settings', 'wp-git-plugins'); ?></h2>
    
    <form method="post" action="">
        <?php wp_nonce_field('wpgp_save_github_settings', 'wpgp_github_nonce'); ?>
        
        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="wpgp_github_username">
                            <?php esc_html_e('GitHub Username', 'wp-git-plugins'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text" 
                               id="wpgp_github_username" 
                               name="wpgp_github_username" 
                               value="<?php echo esc_attr($github_username); ?>" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e('Your GitHub username', 'wp-git-plugins'); ?>" />
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="wpgp_github_token">
                            <?php esc_html_e('GitHub Access Token', 'wp-git-plugins'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="password" 
                               id="wpgp_github_token" 
                               name="wpgp_github_token" 
                               value="<?php echo esc_attr($settings->mask_token($github_token)); ?>" 
                               class="regular-text" 
                               placeholder="<?php esc_attr_e('Your GitHub access token', 'wp-git-plugins'); ?>" 
                               autocomplete="off" />
                        <p class="description">
                            <?php 
                            printf(
                                __('<a href="%s" target="_blank">Generate a new token</a> with the <code>repo</code> scope.', 'wp-git-plugins'),
                                'https://github.com/settings/tokens/new?scopes=repo&description=WP%20Git%20Plugins'
                            );
                            ?><br>
                            <?php _e('For security, the token is masked. Only enter a new token to update it.', 'wp-git-plugins'); ?>
                        </p>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <?php submit_button(__('Save GitHub Settings', 'wp-git-plugins')); ?>
    </form>
</div>