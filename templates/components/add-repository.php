<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>
<div class="wp-git-plugins-card">
    <h2><?php esc_html_e('Add Repository', 'wp-git-plugins'); ?></h2>
    
    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <ul class="nav-tabs">
            <li><a href="#manual-add" class="nav-tab nav-tab-active">Manual Add</a></li>
            <?php 
            // Allow modules to add their own tabs
            do_action('wp_git_plugins_add_repository_tabs'); 
            ?>
        </ul>
    </nav>
    
    <!-- Tab Content -->
    <div class="tab-content-wrapper">
        <!-- Manual Add Tab -->
        <div id="manual-add" class="tab-content">
            <form id="wp-git-plugins-add-repo" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wp_git_plugins_add_repository', '_wpnonce'); ?>
                <input type="hidden" name="action" value="wp_git_plugins_add_repository">
                
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
                            <label for="repo-private" style="display:block; margin-top:10px;">
                                <input type="checkbox" name="repo_private" id="repo-private" value="1">
                                <?php esc_html_e('This repository is private', 'wp-git-plugins'); ?>
                            </label>
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
        
        <?php 
        // Allow modules to add their own tab content
        do_action('wp_git_plugins_add_repository_content'); 
        ?>
    </div>
</div>

<style>
.nav-tab-wrapper .nav-tabs {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
}

.nav-tab-wrapper .nav-tabs li {
    margin: 0;
}

.tab-content-wrapper {
    border: 1px solid #c3c4c7;
    border-top: none;
    padding: 20px;
    background: #fff;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

#manual-add {
    display: block; /* Default tab is shown */
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.tab-content').hide();
        
        // Show target tab content
        $(target).show();
    });
});
</script>
