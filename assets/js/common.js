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

// Export for use in other scripts
window.showNotice = showNotice;
