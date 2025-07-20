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

<script>
console.log('Settings page loaded');
jQuery(document).ready(function($) {
    console.log('Settings page jQuery ready');
    console.log('Found tabs:', $('.nav-tab').length);
    console.log('Found tab content:', $('.tab-content').length);
    
    // Ensure initial state is correct
    $('.tab-content').hide();
    $('.tab-content.active').show();
    
    // Test direct tab click handler
    $('.nav-tab').on('click', function(e) {
        console.log('Tab clicked:', $(this).attr('href'));
        e.preventDefault();
        
        var target = $(this).attr('href');
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.tab-content').removeClass('active').hide();
        
        // Show target tab content
        $(target).addClass('active').show();
        
        console.log('Tab switched to:', target);
    });
});
</script>

<style>
/* Ensure tab content is properly controlled */
.tab-content {
    display: none !important;
}

.tab-content.active {
    display: block !important;
}
</style>
