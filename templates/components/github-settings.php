<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('GitHub Settings', 'wp-git-plugins'); ?></h2>
    
    <div class="github-token-help">
        <h3><?php esc_html_e('How to create a GitHub Personal Access Token', 'wp-git-plugins'); ?></h3>
        <ol>
            <li><?php esc_html_e('Go to GitHub and log in to your account', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Click on your profile picture in the top right corner and select "Settings"', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('In the left sidebar, click on "Developer settings"', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Click on "Personal access tokens" and then "Tokens (classic)"', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Click on "Generate new token" and then "Generate new token (classic)"', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Give your token a descriptive name', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Select the "repo" scope (for private repositories)', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Click "Generate token"', 'wp-git-plugins'); ?></li>
            <li><?php esc_html_e('Copy the generated token and paste it into the field above', 'wp-git-plugins'); ?></li>
        </ol>
        <p>
            <strong><?php esc_html_e('Important:', 'wp-git-plugins'); ?></strong> 
            <?php esc_html_e('Treat your access token like a password and keep it secure. Never share it or commit it to version control.', 'wp-git-plugins'); ?>
        </p>
    </div>
</div>
