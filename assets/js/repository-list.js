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

    // Handle branch selection
    $(document).on('change', '.branch-selector', function() {
        var $select = $(this);
        var $container = $select.closest('.branch-selector-container');
        var $spinner = $container.find('.branch-spinner');
        var repoId = $container.data('repo-id');
        var ghOwner = $container.data('gh-owner');
        var ghName = $container.data('gh-name');
        var currentBranch = $select.data('current-branch');
        var newBranch = $select.val();
        var nonce = $select.data('nonce');
        if (currentBranch === newBranch) {
            return;
        }
        if (!confirm('Are you sure you want to switch to branch "' + newBranch + '"? This will delete the current plugin files and install the selected branch.')) {
            $select.val(currentBranch);
            return;
        }
        $spinner.css('visibility', 'visible').addClass('is-active');
        $select.prop('disabled', true);
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_switch_branch',
                nonce: nonce,
                repo_id: repoId,
                new_branch: newBranch
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

    // Load branches when branch selector is clicked
    $(document).on('focus', '.branch-selector', function() {
        var $select = $(this);
        var $container = $select.closest('.branch-selector-container');
        if ($select.data('branches-loaded')) {
            return;
        }
        var $spinner = $container.find('.branch-spinner');
        $spinner.css('visibility', 'visible');
        var repoId = $container.data('repo-id');
        var ghOwner = $container.data('gh-owner');
        var ghName = $container.data('gh-name');
        var currentBranch = $select.data('current-branch');
        var nonce = $select.data('nonce');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_get_branches',
                nonce: nonce,
                repo_id: repoId,
                gh_owner: ghOwner,
                gh_name: ghName
            },
            success: function(response) {
                if (response.success && response.data && response.data.branches) {
                    $select.empty();
                    $.each(response.data.branches, function(i, branch) {
                        $select.append($('<option>', {
                            value: branch,
                            text: branch,
                            selected: (branch === currentBranch)
                        }));
                    });
                    $select.data('branches-loaded', true);
                } else {
                    console.error('Failed to load branches:', response);
                    showNotice('error', 'Failed to load branches: ' + (response.data && response.data.message || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('Error loading branches:', error);
                showNotice('error', 'Error loading branches: ' + error);
                $select.empty().append(
                    $('<option>', {
                        value: currentBranch,
                        text: currentBranch,
                        selected: true
                    })
                );
            },
            complete: function() {
                $spinner.css('visibility', 'hidden');
            }
        });
    });
});