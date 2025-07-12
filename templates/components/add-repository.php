<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('Add Repository', 'wp-git-plugins'); ?></h2>
    <form id="wp-git-plugins-add-repo" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wp_git_plugins_add_repo', 'wp_git_plugins_nonce'); ?>
        <input type="hidden" name="action" value="wp_git_plugins_add_repo">
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="repo-url"><?php esc_html_e('Repository URL', 'wp-git-plugins'); ?></label>
                </th>
                <td>
                    <input type="url" name="repo_url" id="repo-url" class="regular-text" required 
                           placeholder="https://github.com/username/repository">
                    <p class="description"><?php esc_html_e('Enter the Git repository URL', 'wp-git-plugins'); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e('Private Repository', 'wp-git-plugins'); ?></th>
                <td>
                    <label for="is-private">
                        <input type="checkbox" name="is_private" id="is-private" value="1">
                        <?php esc_html_e('This is a private repository', 'wp-git-plugins'); ?>
                    </label>
                    <p class="description">
                        <?php esc_html_e('A GitHub access token is required for private repositories.', 'wp-git-plugins'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary">
                <?php esc_attr_e('Add Repository', 'wp-git-plugins'); ?>
            </button>
        </p>
    </form>
</div>
