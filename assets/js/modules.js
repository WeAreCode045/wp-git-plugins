/**
 * Module Management JavaScript
 * Handles module upload, activation, deactivation, and deletion
 */

jQuery(document).ready(function($) {
    
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
        
        if (!moduleSlug) {
            showModuleNotice('error', 'Module slug not found');
            return;
        }
        
        $button.prop('disabled', true);
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wpgp_activate_module',
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
                    showModuleNotice('error', response.data.message || 'Activation failed');
                    $button.prop('disabled', false);
                }
            },
            error: function() {
                showModuleNotice('error', 'Activation failed. Please try again.');
                $button.prop('disabled', false);
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
