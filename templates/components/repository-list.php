<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Create repository instance with settings for GitHub token access
$settings = new WP_Git_Plugins_Settings();
$repository = new WP_Git_Plugins_Repository($settings);
$repositories = $repository->get_local_repositories();
?>
<div class="wp-git-plugins-card">
    <div class="wp-git-plugins-card-header">
        <h2><?php esc_html_e('Repositories List', 'wp-git-plugins'); ?></h2>
        <button class="button button-secondary check-all-updates">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Check All Versions', 'wp-git-plugins'); ?>
            <span id="update-count" class="update-count" style="display: none;"></span>
        </button>
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
                    // First try the default pattern: repo-name/repo-name.php
                    $plugin_slug = $repo['name'] . '/' . $repo['name'] . '.php';
                    $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug;
                    $is_plugin_installed = file_exists($plugin_path);
                    
                    // If not found, search through all plugins to find a matching directory
                    if (!$is_plugin_installed) {
                        $possible_dirs = [
                            $repo['name'], // Exact match
                            $repo['name'] . '-main', // GitHub default branch download
                            str_replace('_', '-', $repo['name']), // Handle underscores
                            str_replace('-', '_', $repo['name']) // Handle dashes
                        ];
                        
                        foreach ($all_plugins as $potential_slug => $plugin_data) {
                            $plugin_dir = dirname($potential_slug);
                            $plugin_dir_lower = strtolower($plugin_dir);
                            $repo_name_lower = strtolower($repo['name']);
                            
                            // Check for exact match or variations with -main suffix
                            if (in_array($plugin_dir_lower, array_map('strtolower', $possible_dirs)) || 
                                $plugin_dir_lower === $repo_name_lower . '-main' ||
                                strpos($plugin_dir_lower, $repo_name_lower) === 0) { // Starts with repo name
                                $plugin_slug = $potential_slug;
                                $plugin_path = WP_PLUGIN_DIR . '/' . $plugin_slug;
                                $is_plugin_installed = true;
                                break;
                            }
                        }
                    }
                    
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
                                        data-current-branch="<?php echo esc_attr($repo['branch']); ?>">
                                    <option value="<?php echo esc_attr($repo['branch']); ?>" selected>
                                        <?php echo esc_html($repo['branch']); ?>
                                    </option>
                                    <!-- Branches will be loaded here via AJAX -->
                                </select>
                                <span class="spinner branch-spinner" style="float: none; margin-top: 0; visibility: hidden; margin-left: 5px;"></span>
                            </div>
                        </td>
                        <td class="version-column">
                            <?php 
                            $installed_version = $is_plugin_installed ? get_plugin_data($plugin_path, false, true) : '';
                            $installed_version = $installed_version['Version'] ?? '';
                            ?>
                            <?php echo esc_html($installed_version ?: '—'); ?>
                        </td>
                        <td class="latest-version">
                            <?php 
                            $git_version = $repo['git_version'] ?? ($repo['local_version'] ?? '');
                            $update_available = $is_plugin_installed && !empty($installed_version) && !empty($git_version) && 
                                              version_compare($git_version, $installed_version, '>');
                            
                            if ($update_available) {
                                echo '<span class="update-available" style="color: #d63638; font-weight: 500;">' . esc_html($git_version) . '</span>';
                            } else {
                                echo esc_html($git_version ?: '—');
                            }
                            ?>
                        </td>
                        <td class="status-column">
                            <?php if ($is_plugin_installed) : ?>
                                <?php if ($is_plugin_active) : ?>
                                    <span class="status-active" style="color: #00a32a; font-weight: 500;">
                                        <span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e('Active', 'wp-git-plugins'); ?>
                                    </span>
                                <?php else : ?>
                                    <span class="status-inactive" style="color: #dba617;">
                                        <span class="dashicons dashicons-controls-pause"></span> <?php esc_html_e('Inactive', 'wp-git-plugins'); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="status-not-installed" style="color: #646970;">
                                    <span class="dashicons dashicons-warning"></span> <?php esc_html_e('Not Installed', 'wp-git-plugins'); ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="last-updated">
                            <?php 
                            if (!empty($repo['last_updated'])) {
                                $time_diff = human_time_diff(strtotime($repo['last_updated']), current_time('timestamp'));
                                echo '<span title="' . esc_attr(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($repo['last_updated']))) . '">';
                                echo esc_html(sprintf(__('%s ago', 'wp-git-plugins'), $time_diff));
                                echo '</span>';
                            } else {
                                echo '—';
                            }
                            ?>
                        </td>
                        <td class="actions" style="white-space: nowrap;">
                            <div class="action-buttons" style="display: flex; gap: 5px;">
                                <?php 
                                // Check if update is available
                                $update_available = false;
                                if (!empty($repo['git_version']) && !empty($installed_version)) {
                                    $update_available = version_compare($repo['git_version'], $installed_version, '>');
                                }
                                ?>
                                
                                <?php if ($update_available) : ?>
                                    <button class="button button-primary button-small update-plugin" 
                                            data-id="<?php echo esc_attr($repo['id']); ?>"
                                            data-plugin="<?php echo esc_attr($plugin_slug); ?>"
                                            data-current-version="<?php echo esc_attr($installed_version); ?>"
                                            data-new-version="<?php echo esc_attr($repo['git_version']); ?>"
                                            title="<?php esc_attr_e('Update plugin', 'wp-git-plugins'); ?>">
                                        <span class="dashicons dashicons-update"></span>
                                        <span class="spinner" style="margin-top: -4px; float: none; display: none;"></span>
                                    </button>
                                <?php else : ?>
                                    <button class="button button-small check-version" 
                                            data-id="<?php echo esc_attr($repo['id']); ?>" 
                                            title="<?php esc_attr_e('Check for updates', 'wp-git-plugins'); ?>">
                                        <span class="dashicons dashicons-update"></span>
                                        <span class="spinner" style="margin-top: -4px; float: none; display: none;"></span>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($is_plugin_installed) : ?>
                                    <?php if ($is_plugin_active) : ?>
                                        <button class="button button-small deactivate-plugin" 
                                                data-plugin="<?php echo esc_attr($plugin_slug); ?>" 
                                                title="<?php esc_attr_e('Deactivate plugin', 'wp-git-plugins'); ?>">
                                            <span class="dashicons dashicons-controls-pause"></span>
                                        </button>
                                    <?php else : ?>
                                        <button class="button button-small activate-plugin" 
                                                data-plugin="<?php echo esc_attr($plugin_slug); ?>" 
                                                title="<?php esc_attr_e('Activate plugin', 'wp-git-plugins'); ?>">
                                            <span class="dashicons dashicons-controls-play"></span>
                                        </button>
                                    <?php endif; ?>
                                <?php else : ?>
                                    <button class="button button-small install-plugin" 
                                            data-repo-id="<?php echo esc_attr($repo['id']); ?>" 
                                            title="<?php esc_attr_e('Install plugin', 'wp-git-plugins'); ?>">
                                        <span class="dashicons dashicons-download"></span>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-small delete-repo" 
                                        data-id="<?php echo esc_attr($repo['id']); ?>" 
                                        data-name="<?php echo esc_attr($repo['name']); ?>" 
                                        title="<?php esc_attr_e('Remove repository', 'wp-git-plugins'); ?>">
                                    <span class="dashicons dashicons-trash"></span>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div id="update-results" style="margin-top: 20px; display: none;">
    <h3><?php esc_html_e('Update Check Results', 'wp-git-plugins'); ?></h3>
    <div id="update-results-content"></div>
</div>

