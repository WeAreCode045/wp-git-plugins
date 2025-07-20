<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get settings
$settings = new WP_Git_Plugins_Settings('wp-git-plugins', '1.0.0');
?>
<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <?php settings_errors('wpgp_messages'); ?>
    
    <div class="wp-git-plugins-container">
        <div class="wp-git-plugins-main">
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper wp-clearfix">
                <a href="#github-settings" class="nav-tab nav-tab-active" id="github-settings-tab">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('GitHub Settings', 'wp-git-plugins'); ?>
                </a>
                <a href="#modules-management" class="nav-tab" id="modules-management-tab">
                    <span class="dashicons dashicons-admin-plugins"></span>
                    <?php esc_html_e('Module Management', 'wp-git-plugins'); ?>
                </a>
            </nav>
            
            <!-- Tab Content -->
            <div class="tab-content-wrapper">
                <!-- GitHub Settings Tab -->
                <div id="github-settings" class="tab-content active">
                    <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/github-settings.php'; ?>
                </div>
                
                <!-- Modules Management Tab -->
                <div id="modules-management" class="tab-content">
                    <?php include WP_GIT_PLUGINS_DIR . 'templates/components/settings/module-settings.php'; ?>
                </div>
            </div>
        </div>
        
        <div class="wp-git-plugins-sidebar">
            <?php include WP_GIT_PLUGINS_DIR . 'templates/components/plugin-info.php'; ?>
        </div>
    </div>
</div>

<style>
.nav-tab-wrapper {
    margin-bottom: 0;
    border-bottom: 1px solid #c3c4c7;
}

.nav-tab {
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.nav-tab .dashicons {
    font-size: 16px;
    width: 16px;
    height: 16px;
}

.tab-content-wrapper {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-top: none;
    padding: 20px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.module-status.active {
    color: #00a32a;
    font-weight: 600;
}

.module-status.inactive {
    color: #d63638;
    font-weight: 600;
}

.wp-list-table .row-actions {
    color: #666;
    font-size: 13px;
    margin-top: 5px;
}

.no-modules-message {
    margin: 20px 0;
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
        $('.tab-content').removeClass('active');
        
        // Show target tab content
        $(target).addClass('active');
        
        // Update URL hash without scrolling
        if (history.replaceState) {
            history.replaceState(null, null, target);
        }
    });
    
    // Handle hash on page load
    if (window.location.hash) {
        var hash = window.location.hash;
        if ($(hash).length && $('.nav-tab[href="' + hash + '"]').length) {
            $('.nav-tab[href="' + hash + '"]').trigger('click');
        }
    }
});
</script>
