<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$repository = new WP_Git_Plugins_Repository();
$repositories = $repository->get_repositories();
?>
<div class="wp-git-plugins-card">
    <div class="wp-git-plugins-card-header">
        <h2><?php esc_html_e('Managed Repositories', 'wp-git-plugins'); ?></h2>
        <button id="wp-git-plugins-check-updates" class="button button-secondary">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Check for Updates', 'wp-git-plugins'); ?>
        </button>
    </div>
    
    <?php if (empty($repositories)) : ?>
        <p><?php esc_html_e('No repositories have been added yet.', 'wp-git-plugins'); ?></p>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('Plugin', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Version', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Status', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Last Updated', 'wp-git-plugins'); ?></th>
                    <th><?php esc_html_e('Actions', 'wp-git-plugins'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Get all plugins
                $all_plugins = get_plugins();
                
                foreach ($repositories as $repo) : 
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
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url($repo['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($repo['name']); ?>
                                </a>
                            </strong>
                            <?php if ($repo['is_private']) : ?>
                                <span class="dashicons dashicons-lock" title="<?php esc_attr_e('Private Repository', 'wp-git-plugins'); ?>"></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_plugin_installed) : ?>
                                <?php 
                                $plugin_data = get_plugin_data($plugin_path);
                                $installed_version = $plugin_data['Version'] ?? '';
                                ?>
                                <?php echo esc_html($installed_version); ?>
                                
                                <?php if (!empty($repo['latest_version']) && version_compare($repo['latest_version'], $installed_version, '>')) : ?>
                                    <span class="update-available" title="<?php esc_attr_e('Update available', 'wp-git-plugins'); ?>">
                                        → <?php echo esc_html($repo['latest_version']); ?>
                                    </span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="not-installed"><?php esc_html_e('Not installed', 'wp-git-plugins'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($is_plugin_installed) : ?>
                                <?php if ($is_plugin_active) : ?>
                                    <span class="status-active"><?php esc_html_e('Active', 'wp-git-plugins'); ?></span>
                                <?php else : ?>
                                    <span class="status-inactive"><?php esc_html_e('Inactive', 'wp-git-plugins'); ?></span>
                                <?php endif; ?>
                            <?php else : ?>
                                <span class="status-not-installed"><?php esc_html_e('Not Installed', 'wp-git-plugins'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo !empty($repo['last_updated']) ? 
                                esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($repo['last_updated']))) : 
                                '—'; 
                            ?>
                        </td>
                        <td class="actions">
                            <?php if ($is_plugin_installed) : ?>
                                <?php if (!empty($repo['latest_version']) && version_compare($repo['latest_version'], $installed_version, '>')) : ?>
                                    <button class="button button-small update-plugin" data-repo="<?php echo esc_attr($repo['url']); ?>">
                                        <span class="dashicons dashicons-update"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Update', 'wp-git-plugins'); ?></span>
                                    </button>
                                <?php else : ?>
                                    <button class="button button-small check-update" data-repo="<?php echo esc_attr($repo['url']); ?>">
                                        <span class="dashicons dashicons-update" title="<?php esc_attr_e('Check for updates', 'wp-git-plugins'); ?>"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Check for updates', 'wp-git-plugins'); ?></span>
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($is_plugin_active) : ?>
                                    <button class="button button-small deactivate-plugin" data-plugin="<?php echo esc_attr($plugin_slug); ?>">
                                        <span class="dashicons dashicons-controls-pause" title="<?php esc_attr_e('Deactivate', 'wp-git-plugins'); ?>"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Deactivate', 'wp-git-plugins'); ?></span>
                                    </button>
                                <?php else : ?>
                                    <button class="button button-small activate-plugin" data-plugin="<?php echo esc_attr($plugin_slug); ?>">
                                        <span class="dashicons dashicons-controls-play" title="<?php esc_attr_e('Activate', 'wp-git-plugins'); ?>"></span>
                                        <span class="screen-reader-text"><?php esc_html_e('Activate', 'wp-git-plugins'); ?></span>
                                    </button>
                                <?php endif; ?>
                                
                                <button class="button button-small delete-plugin" data-repo="<?php echo esc_attr($repo['url']); ?>">
                                    <span class="dashicons dashicons-trash" title="<?php esc_attr_e('Delete', 'wp-git-plugins'); ?>"></span>
                                    <span class="screen-reader-text"><?php esc_html_e('Delete', 'wp-git-plugins'); ?></span>
                                </button>
                            <?php else : ?>
                                <button class="button button-small delete-repo" data-repo="<?php echo esc_attr($repo['url']); ?>">
                                    <span class="dashicons dashicons-trash" title="<?php esc_attr_e('Remove', 'wp-git-plugins'); ?>"></span>
                                    <span class="screen-reader-text"><?php esc_html_e('Remove', 'wp-git-plugins'); ?></span>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
