// Common utility functions for WP Git Plugins

/**
 * Show a WordPress-style notice message.
 * @param {string} type - 'success', 'error', 'info', etc.
 * @param {string} message - The message to display.
 */
function showNotice(type, message) {
    const $ = window.jQuery;
    const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
    $('.wrap h1').after(notice);
    setTimeout(() => {
        notice.fadeOut(300, function() {
            $(this).remove();
        });
    }, 5000);
}

/**
 * Initialize tab switching functionality
 */
function initTabSwitching() {
    const $ = window.jQuery;
    
    console.log('WP Git Plugins: Initializing tab switching...');
    console.log('WP Git Plugins: Found', $('.nav-tab').length, 'tabs');
    console.log('WP Git Plugins: Found', $('.tab-content').length, 'tab content areas');
    
    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        console.log('WP Git Plugins: Tab clicked, target:', target);
        
        // Remove active class from all tabs
        $('.nav-tab').removeClass('nav-tab-active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content
        $('.tab-content').removeClass('active');
        
        // Show target tab content
        $(target).addClass('active');
        console.log('WP Git Plugins: Activated tab content for:', target);
        
        // Update URL hash without scrolling
        if (history.replaceState) {
            history.replaceState(null, null, target);
        }
    });
    
    // Handle hash on page load
    if (window.location.hash) {
        var hash = window.location.hash;
        console.log('WP Git Plugins: Page loaded with hash:', hash);
        if ($(hash).length && $('.nav-tab[href="' + hash + '"]').length) {
            $('.nav-tab[href="' + hash + '"]').trigger('click');
        }
    }
    
    console.log('WP Git Plugins: Tab switching initialization complete');
}

// Initialize tab switching when document is ready
jQuery(document).ready(function() {
    initTabSwitching();
});

// Export for use in other scripts
window.showNotice = showNotice;
window.initTabSwitching = initTabSwitching;
