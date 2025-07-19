<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$repository = new WP_Git_Plugins_Repository();
$repositories = $repository->get_local_repositories();
?>
<div class="wp-git-plugins-card">
    <div class="wp-git-plugins-card-header">
        <h2><?php esc_html_e('Repositories List', 'wp-git-plugins'); ?></h2>
        <p class="description"><?php esc_html_e('Manage your Git repositories and plugins.', 'wp-git-plugins'); ?></p>
    </div>
        <span class="spinner check-all-spinner" style="float: none; margin-top: 0; display: none;"></span>
    </div>
    
    <?php if (empty($repositories)) : ?>
        <p><?php esc_html_e('No repositories have been added yet.', 'wp-git-plugins'); ?></p>
    <?php else : ?>
        <?php
if ( isset( $_GET['wpgp_notice'] ) ) {
    $notice = sanitize_text_field( $_GET['wpgp_notice'] );
    if ( $notice === 'deleted' ) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Repository deleted successfully.', 'wp-git-plugins' ) . '</p></div>';
    } elseif ( $notice === 'delete_failed' ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to delete repository.', 'wp-git-plugins' ) . '</p></div>';
    } elseif ( $notice === 'invalid_id' ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Invalid repository ID.', 'wp-git-plugins' ) . '</p></div>';
    }
}
?>

<table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Branch', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Installed Version', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Latest Version', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Status', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Last Updated', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Actions', 'wp-git-plugins'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Get all plugins
                $all_plugins = get_plugins();
                
                foreach ( $repositories as $repo_obj ) :
                    $repo = (array) $repo_obj;
                    // Ensure compatibility with legacy keys
                    if ( empty( $repo['name'] ) && ! empty( $repo['gh_name'] ) ) {
                        $repo['name'] = $repo['gh_name'];
                    }
                    if ( empty( $repo['owner'] ) && ! empty( $repo['gh_owner'] ) ) {
                        $repo['owner'] = $repo['gh_owner'];
                    }
                    if ( empty( $repo['url'] ) && ! empty( $repo['owner'] ) && ! empty( $repo['name'] ) ) {
                        $repo['url'] = sprintf( 'https://github.com/%s/%s', $repo['owner'], $repo['name'] );
                    }
                    // Find the correct plugin slug using get_plugins()
                    $all_plugins = get_plugins();
                    $plugin_slug = '';
                    foreach ($all_plugins as $potential_slug => $plugin_data) {
                        $plugin_dir = dirname($potential_slug);
                        $plugin_dir_lower = strtolower($plugin_dir);
                        $repo_name_lower = strtolower($repo['name']);
                        if (
                            $plugin_dir_lower === $repo_name_lower ||
                            $plugin_dir_lower === $repo_name_lower . '-main' ||
                            strpos($plugin_dir_lower, $repo_name_lower) === 0
                        ) {
                            $plugin_slug = $potential_slug;
                            break;
                        }
                    }
                    // Fallback to default pattern if not found
                    if (empty($plugin_slug)) {
                        $plugin_slug = $repo['name'] . '/' . $repo['name'] . '.php';
                    }
                    $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug;
                    $is_plugin_installed = file_exists($plugin_path);
                    $is_plugin_active = $is_plugin_installed ? is_plugin_active($plugin_slug) : false;
                ?>
                    <tr class="repo-row" data-id="<?php echo esc_attr($repo['id']); ?>">
                        <td class="repo-name">
                            <strong>
                                <a href="<?php echo esc_url($repo['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($repo['name']); ?>
                                </a>
                            </strong>
                            <?php if ($repo['is_private']) : ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Private Repository', 'wp-git-plugins'); ?>"></span>
                            <?php endif; ?>

                        </td>
                        <td class="branch-column">
                            <div class="branch-selector-container" style="display: flex; align-items: center; gap: 5px;" 
                                 data-repo-id="<?php echo esc_attr($repo['id']); ?>"
                                 data-gh-owner="<?php echo esc_attr($repo['gh_owner']); ?>"
                                 data-gh-name="<?php echo esc_attr($repo['gh_name']); ?>">
                                <select class="branch-selector" style="min-width: 150px;" 
                                        data-current-branch="<?php echo esc_attr($repo['branch']); ?>"
                                        data-nonce="<?php echo wp_create_nonce('wp_git_plugins_nonce'); ?>">
                                    <option value="<?php echo esc_attr($repo['branch']); ?>" selected>
                                        <?php echo esc_html($repo['branch']); ?>
                                    </option>
                                    <!-- Branches will be loaded here via AJAX -->
                                </select>
                                <span class="spinner branch-spinner" style="float: none; margin-top: 0; visibility: hidden; margin-left: 5px;"></span>
                            </div>
                        </td>
                        <td class="version-column">
                            <?php if ($is_plugin_installed) : ?>
                                <?php echo esc_html(get_plugin_data($plugin_path)['Version']); ?>
                            <?php else : ?>
                                <span class="dashicons dashicons-dismiss" title="<?php esc_attr_e('Plugin not installed', 'wp-git-plugins'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td class="latest-version-column">
                            <?php
                            $latest_version = $repository->get_latest_version($repo['gh_owner'], $repo['gh_name'], $repo['branch']);
                            if ($latest_version) {
                                echo esc_html($latest_version);
                            } else {
                                echo '<span class="dashicons dashicons-dismiss" title="' . esc_attr__('Latest version not available', 'wp-git-plugins') . '"></span>';
                            }
                            ?>
                        </td>
                        <td class="status-column">
                            <?php if ($is_plugin_active) : ?>
                                <span class="dashicons dashicons-yes" title="<?php esc_attr_e('Plugin is active', 'wp-git-plugins'); ?>"></span>
                            <?php else : ?>
                                <span class="dashicons dashicons-no" title="<?php esc_attr_e('Plugin is inactive', 'wp-git-plugins'); ?>"></span>
                            <?php endif; ?>
                        </td>    
                        <td class="last-updated-column">
                            <?php
                            $last_updated = $repository->get_last_updated($repo['gh_owner'], $repo['gh_name'], $repo['branch']);
                            if ($last_updated) {
                                echo esc_html($last_updated);
                            } else {
                                echo '<span class="dashicons dashicons-dismiss" title="' . esc_attr__('Last updated not available', 'wp-git-plugins') . '"></span>';
                            }
                            ?>
                        </td>
                        <td class="actions-column">
                            <a href="#" class="delete-repo" data-repo-id="<?php echo esc_attr($repo['id']); ?>">
                                <span class="dashicons dashicons-trash" title="<?php esc_attr_e('Delete Repository', 'wp-git-plugins'); ?>"></span>
                            </a>
                            <?php if ($is_plugin_installed) : ?>
                                <a href="<?php echo esc_url(admin_url('plugins.php')); ?>" class="view-plugin" target="_blank">
                                    <span class="dashicons dashicons-admin-plugins" title="<?php esc_attr_e('View Plugin', 'wp-git-plugins'); ?>"></span>
                                </a>
                            <?php endif; ?>
                            <a href="<?php echo esc_url($repo['url']); ?>" class="view-repo" target="_blank">
                                <span class="dashicons dashicons-external" title="<?php esc_attr_e('View Repository', 'wp-git-plugins'); ?>"></span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>      

