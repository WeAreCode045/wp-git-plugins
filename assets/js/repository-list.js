// Localized strings (should be set via wp_localize_script in PHP)
var wpGitPlugins = wpGitPlugins || {};
wpGitPlugins.i18n = wpGitPlugins.i18n || {
    checking: 'Checking...',
    checking_all: 'Checking All...',
    updating: 'Updating...',
    confirm_update: 'Are you sure you want to update this plugin from version %s to %s?',
    update_available: 'Update available: %s (current: %s)',
    no_updates: 'This plugin is up to date.',
    update_success: 'Plugin updated successfully to version %s.',
    update_error: 'An error occurred while updating the plugin.',
    update_check_error: 'Failed to check for updates.',
    error_deactivating: 'Failed to deactivate the plugin before update.',
    update_success_reactivate_failed: 'Plugin updated but could not be reactivated. Please activate it manually.',
    confirm_delete: 'Are you sure you want to delete this repository? This will not uninstall the plugin.',
    deleting: 'Deleting...',
    delete_error: 'Failed to delete the repository.',
    confirm_branch_change: 'Are you sure you want to switch to the %s branch? This will update the plugin files.',
    changing_branch: 'Switching branch...',
    branch_change_error: 'Failed to switch branch.'
};

jQuery(document).ready(function($) {
    // Show notices
    function showNotice(type, message) {
        // Remove any existing notices
        $('.wp-git-plugins-notice').remove();
        // Create and show new notice
        var notice = $('<div class="notice notice-' + type + ' is-dismissible wp-git-plugins-notice"><p>' + message + '</p></div>');
        $('.wrap > h1').after(notice);
        // Auto-hide after 5 seconds
        setTimeout(function() {
            notice.fadeOut(400, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Handle delete action
    $('.delete-repo').on('click', function(e) {
        e.preventDefault();
        if (!confirm(wpGitPlugins.i18n.confirm_delete)) {
            return;
        }
        var $button = $(this);
        var $row = $button.closest('tr');
        var repoId = $button.data('id');
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + wpGitPlugins.i18n.deleting);
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_delete_repository',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(400, function() {
                        $(this).remove();
                        showNotice('success', response.data.message || wpGitPlugins.i18n.delete_success);
                    });
                } else {
                    showNotice('error', response.data.message || wpGitPlugins.i18n.delete_error);
                    $button.prop('disabled', false).html(wpGitPlugins.i18n.delete);
                }
            },
            error: function() {
                showNotice('error', wpGitPlugins.i18n.delete_error);
                $button.prop('disabled', false).html(wpGitPlugins.i18n.delete);
            }
        });
    });
});

//Handle Activate and Deactivate actions
jQuery(document).ready(function($) {
    $('.activate-plugin, .deactivate-plugin').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var action = $button.hasClass('activate-plugin') ? 'activate' : 'deactivate';
        var pluginId = $button.data('id');
        var confirmMessage = action === 'activate' ? wpGitPlugins.i18n.confirm_activate : wpGitPlugins.i18n.confirm_deactivate;

        if (!confirm(confirmMessage)) {
            return;
        }

        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + (action === 'activate' ? wpGitPlugins.i18n.activating : wpGitPlugins.i18n.deactivating));

        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_' + action + '_plugin',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                plugin_id: pluginId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || (action === 'activate' ? wpGitPlugins.i18n.activate_success : wpGitPlugins.i18n.deactivate_success));
                    // Optionally reload the page or update UI
                    location.reload();
                } else {
                    showNotice('error', response.data.message || (action === 'activate' ? wpGitPlugins.i18n.activate_error : wpGitPlugins.i18n.deactivate_error));
                    $button.prop('disabled', false).html(action === 'activate' ? wpGitPlugins.i18n.activate : wpGitPlugins.i18n.deactivate);
                }
            },
            error: function() {
                showNotice('error', action === 'activate' ? wpGitPlugins.i18n.activate_error : wpGitPlugins.i18n.deactivate_error);
                $button.prop('disabled', false).html(action === 'activate' ? wpGitPlugins.i18n.activate : wpGitPlugins.i18n.deactivate);
            }
        });
    });
});