<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('Add Repository', 'wp-git-plugins'); ?></h2>
    <form id="wp-git-plugins-add-repo" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wp_git_plugins_add_repo', '_wpnonce'); ?>
        <input type="hidden" name="action" value="wp_git_plugins_add_repo">
        
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="repo-url"><?php esc_html_e('Repository URL', 'wp-git-plugins'); ?></label>
                </th>
                <td>
                    <input type="url" name="repo_url" id="repo-url" class="regular-text" required 
                           placeholder="https://github.com/username/repository">
                    <p class="description">
                        <?php esc_html_e('Enter the GitHub repository URL (e.g., https://github.com/username/repository)', 'wp-git-plugins'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="repo-branch"><?php esc_html_e('Branch', 'wp-git-plugins'); ?></label>
                </th>
                <td>
                    <input type="text" name="repo_branch" id="repo-branch" class="regular-text" 
                           value="main" placeholder="main">
                    <p class="description">
                        <?php esc_html_e('Enter the branch name (default: main)', 'wp-git-plugins'); ?>
                    </p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-plus"></span>
                <?php esc_attr_e('Add Repository', 'wp-git-plugins'); ?>
            </button>
            <span class="spinner" style="float: none; margin-top: 0; display: none;"></span>
        </p>
    </form>
</div>
