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
    
    // Ensure default active tab is visible on page load
    $('.tab-content.active').show();
    $('.tab-content:not(.active)').hide();
    
    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        var target = $(this).attr('href');
        console.log('WP Git Plugins: Tab clicked, target:', target);
        
        // Find the parent nav-tab-wrapper to scope our changes
        var $tabWrapper = $(this).closest('.nav-tab-wrapper');
        var $contentWrapper = $tabWrapper.siblings('.tab-content-wrapper');
        
        // If no content wrapper found, try finding it as the next element
        if ($contentWrapper.length === 0) {
            $contentWrapper = $tabWrapper.next('.tab-content-wrapper');
        }
        
        // If still no content wrapper, look for tab content in the same container
        if ($contentWrapper.length === 0) {
            $contentWrapper = $tabWrapper.parent();
        }
        
        console.log('WP Git Plugins: Found content wrapper:', $contentWrapper.length);
        
        // Remove active class from tabs in this wrapper
        $tabWrapper.find('.nav-tab').removeClass('nav-tab-active');
        
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');
        
        // Hide all tab content in this wrapper
        $contentWrapper.find('.tab-content').removeClass('active').hide();
        
        // Show target tab content
        var $targetContent = $contentWrapper.find(target);
        if ($targetContent.length === 0) {
            // Fallback: look for target in the entire document
            $targetContent = $(target);
        }
        
        $targetContent.addClass('active').show();
        console.log('WP Git Plugins: Activated tab content for:', target, 'Found elements:', $targetContent.length);
        
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
