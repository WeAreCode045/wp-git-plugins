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

    // Handle branch selector change
    $(document).on('change', '.branch-selector', function() {
        var $select = $(this);
        var $container = $select.closest('.branch-selector-container');
        var $spinner = $container.find('.branch-spinner');
        var currentBranch = $select.data('current-branch');
        var newBranch = $select.val();
        var repoId = $container.data('repo-id');

        // If user selected the same branch, do nothing
        if (newBranch === currentBranch) {
            return;
        }

        // Confirm branch change
        var confirmMessage = wpGitPlugins.i18n.confirm_branch_change.replace('%s', newBranch);
        if (!confirm(confirmMessage)) {
            $select.val(currentBranch);
            return;
        }

        // Show spinner and disable select
        $spinner.css('visibility', 'visible').addClass('is-active');
        $select.prop('disabled', true);

        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_change_branch',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId,
                branch: newBranch
            },
            success: function(response) {
                if (response.success) {
                    $select.data('current-branch', newBranch);
                    showNotice('success', 'Branch switched successfully! Reloading...');
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                } else {
                    $select.val(currentBranch);
                    showNotice('error', 'Failed to switch branch: ' + (response.data && response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                $select.val(currentBranch);
                showNotice('error', 'Failed to switch branch: ' + error);
            },
            complete: function() {
                $spinner.css('visibility', 'hidden').removeClass('is-active');
                $select.prop('disabled', false);
            }
        });
    });

    // Load branches when branch selector is clicked/focused
    $(document).on('focus', '.branch-selector', function() {
        var $select = $(this);
        var $container = $select.closest('.branch-selector-container');
        
        // If branches already loaded, do nothing
        if ($select.data('branches-loaded')) {
            return;
        }
        
        var $spinner = $container.find('.branch-spinner');
        $spinner.css('visibility', 'visible').addClass('is-active');
        
        var repoId = $container.data('repo-id');
        var ghOwner = $container.data('gh-owner');
        var ghName = $container.data('gh-name');
        var currentBranch = $select.data('current-branch');
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_get_branches',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId,
                gh_owner: ghOwner,
                gh_name: ghName
            },
            success: function(response) {
                if (response.success && response.data && response.data.branches) {
                    // Clear existing options
                    $select.empty();
                    
                    // Add all branches
                    $.each(response.data.branches, function(i, branch) {
                        $select.append($('<option>', {
                            value: branch,
                            text: branch,
                            selected: (branch === currentBranch)
                        }));
                    });
                    
                    // Mark as loaded
                    $select.data('branches-loaded', true);
                } else {
                    console.error('Failed to load branches:', response);
                    showNotice('error', 'Failed to load branches: ' + (response.data && response.data.message || 'Unknown error'));
                    
                    // Keep current branch as only option
                    $select.empty().append($('<option>', {
                        value: currentBranch,
                        text: currentBranch,
                        selected: true
                    }));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading branches:', error);
                showNotice('error', 'Error loading branches: ' + error);
                
                // Keep current branch as only option
                $select.empty().append($('<option>', {
                    value: currentBranch,
                    text: currentBranch,
                    selected: true
                }));
            },
            complete: function() {
                $spinner.css('visibility', 'hidden').removeClass('is-active');
            }
        });
    });
});