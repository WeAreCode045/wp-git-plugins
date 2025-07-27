<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<form id="wp-git-plugins-add-repo" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: flex; align-items: flex-end; gap: 12px; flex-wrap: wrap; margin: 0;">
    <?php wp_nonce_field('wp_git_plugins_add_repository', '_wpnonce'); ?>
    <input type="hidden" name="action" value="wp_git_plugins_add_repository">
    <input type="url" name="repo_url" id="repo-url" class="regular-text" required placeholder="<?php esc_attr_e('Repository URL', 'wp-git-plugins'); ?>" style="min-width: 260px;" title="<?php esc_attr_e('Repository URL', 'wp-git-plugins'); ?>">
    <input type="text" name="repo_branch" id="repo-branch" class="regular-text" value="main" placeholder="<?php esc_attr_e('Branch', 'wp-git-plugins'); ?>" style="min-width: 100px;" title="<?php esc_attr_e('Branch', 'wp-git-plugins'); ?>">
    <label for="repo-private" style="margin-bottom: 0; display: flex; align-items: center; gap: 4px;">
        <input type="checkbox" name="repo_private" id="repo-private" value="1">
        <?php esc_html_e('Private', 'wp-git-plugins'); ?>
    </label>
    <button type="submit" class="button button-primary" style="margin-bottom: 0;">
        <span class="dashicons dashicons-plus"></span>
        <?php esc_attr_e('Add Repository', 'wp-git-plugins'); ?>
    </button>
    <span class="spinner" style="float: none; margin-top: 0; display: none;"></span>
</form>
<?php 
// Allow modules to add their own tab content
do_action('wp_git_plugins_add_repository_content'); 
?>
