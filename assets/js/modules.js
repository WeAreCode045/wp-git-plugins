/**
 * Module Management JavaScript
 * Handles module upload, activation, deactivation, and deletion
 */

jQuery(document).ready(function($) {
    
    // Enhanced debugging and initialization
    console.log('=== WP Git Plugins Module Management ===');
    console.log('jQuery version:', $.fn.jquery);
    console.log('Document ready state:', document.readyState);
    
    // Check if wpGitPlugins is available, if not create a fallback
    if (typeof wpGitPlugins === 'undefined') {
        console.warn('wpGitPlugins object not found. Creating fallback.');
        window.wpGitPlugins = {
            ajax_url: ajaxurl || '/wp-admin/admin-ajax.php',
            ajax_nonce: ''
        };
    }
    
    console.log('wpGitPlugins object:', wpGitPlugins);
    console.log('Looking for activate buttons:', $('.activate-module').length);
    console.log('All module buttons:', $('.activate-module, .deactivate-module, .delete-module').length);
    
    // Test button existence on page load
    setTimeout(function() {
        console.log('After timeout - activate buttons:', $('.activate-module').length);
        $('.activate-module').each(function(i, btn) {
            console.log('Activate button ' + i + ':', btn, 'data-module:', $(btn).data('module'));
        });
    }, 1000);
    
    // Handle module upload
    $('#module-upload-form').on('submit', function(e) {
        e.preventDefault();
        
        var formData = new FormData();
        var fileInput = $('#module-file')[0];
        
        if (!fileInput.files[0]) {
            showModuleNotice('error', 'Please select a file to upload.');
            return;
        }
        
        formData.append('action', 'wpgp_upload_module');
        formData.append('_ajax_nonce', wpGitPlugins.ajax_nonce);
        formData.append('module_file', fileInput.files[0]);
        
        var $button = $(this).find('button[type="submit"]');
        var $spinner = $(this).find('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showModuleNotice('success', response.data.message);
                    // Reload page to show new module
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showModuleNotice('error', response.data.message || 'Upload failed');
                }
            },
            error: function() {
                showModuleNotice('error', 'Upload failed. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
                fileInput.value = '';
            }
        });
    });
    
    // Handle module activation
    $(document).on('click', '.activate-module', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var moduleSlug = $button.data('module');
        var nonce = $('#_ajax_nonce').val();
        
        if (!moduleSlug) {
            alert('Module slug is missing.');
            return;
        }
        
        // Debugging: Log the button click and data
        console.log('Activate button clicked for module:', moduleSlug);
        
        $button.prop('disabled', true).text('Activating...');
        
        $.ajax({
            url: ajaxurl, // WordPress AJAX URL
            type: 'POST',
            data: {
                action: 'wpgp_activate_module',
                module_slug: moduleSlug,
                _ajax_nonce: nonce
            },
            success: function(response) {
                console.log('AJAX success:', response);
                if (response.success) {
                    alert(response.data.message || 'Module activated successfully.');
                    location.reload(); // Reload the page to reflect changes
                } else {
                    console.error('AJAX error response:', response);
                    alert(response.data.message || 'Activation failed.');
                    $button.prop('disabled', false).text('Activate');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', status, error);
                alert('AJAX error occurred.');
                $button.prop('disabled', false).text('Activate');
            }
        });
    });
    
    // Handle module deactivation
    $(document).on('click', '.deactivate-module', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var moduleSlug = $button.data('module');
        
        if (!moduleSlug) {
            showModuleNotice('error', 'Module slug not found');
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wpgp_deactivate_module',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                module_slug: moduleSlug
            },
            success: function(response) {
                if (response.success) {
                    showModuleNotice('success', response.data.message);
                    // Reload page to update status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showModuleNotice('error', response.data.message || 'Deactivation failed');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                showModuleNotice('error', 'Deactivation failed. Please try again.');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Handle module deletion
    $(document).on('click', '.delete-module', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var moduleSlug = $button.data('module');
        
        if (!moduleSlug) {
            showModuleNotice('error', 'Module slug not found');
            return;
        }
        
        if (!confirm('Are you sure you want to delete this module? This action cannot be undone.')) {
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wpgp_delete_module',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                module_slug: moduleSlug
            },
            success: function(response) {
                if (response.success) {
                    showModuleNotice('success', response.data.message);
                    // Reload page to update list
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showModuleNotice('error', response.data.message || 'Deletion failed');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                showModuleNotice('error', 'Deletion failed. Please try again.');
                $button.prop('disabled', false);
            }
        });
    });
    
    // Show module notices
    function showModuleNotice(type, message) {
        // Remove any existing notices
        $('.wp-git-plugins-module-notice').remove();
        
        // Create and show new notice
        var notice = $('<div class="notice notice-' + type + ' is-dismissible wp-git-plugins-module-notice"><p>' + message + '</p></div>');
        $('#modules-management').prepend(notice);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notice.fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);
    }
});
