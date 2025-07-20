// wpGitPlugins object is populated via wp_localize_script in PHP
// All translation strings and AJAX configuration come from WP_Git_Plugins_i18n class

// Show notices function - global scope so it can be accessed from AJAX callbacks
function showNotice(type, message) {
    // Remove any existing notices
    jQuery('.wp-git-plugins-notice').remove();
    // Create and show new notice
    var notice = jQuery('<div class="notice notice-' + type + ' is-dismissible wp-git-plugins-notice"><p>' + message + '</p></div>');
    jQuery('.wrap > h1').after(notice);
    // Auto-hide after 5 seconds
    setTimeout(function() {
        notice.fadeOut(400, function() {
            jQuery(this).remove();
        });
    }, 5000);
}

jQuery(document).ready(function($) {
    // Store repository data for deletion
    var deleteRepositoryData = null;

    // Handle delete action - show modal instead of immediate deletion
    $('.delete-repo').on('click', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var repoId = $button.data('id');
        var repoName = $button.data('name');
        
        // Store repository data
        deleteRepositoryData = {
            id: repoId,
            name: repoName,
            button: $button
        };
        
        // Show modal
        showDeleteModal();
    });

    // Modal functions
    function showDeleteModal() {
        $('#delete-repo-modal, #wp-git-plugins-modal-backdrop').show();
        $('body').addClass('modal-open').css('overflow', 'hidden');
    }

    function hideDeleteModal() {
        $('#delete-repo-modal, #wp-git-plugins-modal-backdrop').hide();
        $('body').removeClass('modal-open').css('overflow', '');
        deleteRepositoryData = null;
    }

    // Modal close handlers
    $('.wp-git-plugins-modal-close, #wp-git-plugins-modal-backdrop').on('click', function(e) {
        e.preventDefault();
        hideDeleteModal();
    });

    // Confirm delete button handler
    $('#confirm-delete-repo').on('click', function(e) {
        e.preventDefault();
        
        if (!deleteRepositoryData) {
            return;
        }
        
        var deleteOption = $('input[name="delete_option"]:checked').val();
        performDelete(deleteRepositoryData, deleteOption);
        hideDeleteModal();
    });

    // Perform the actual deletion based on selected option
    function performDelete(repoData, deleteOption) {
        var $button = repoData.button;
        var $row = $button.closest('tr');
        
        // Disable button and show loading
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + wpGitPlugins.i18n.deleting);
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_delete_repository',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoData.id,
                delete_option: deleteOption
            },
            success: function(response) {
                if (response.success) {
                    if (deleteOption === 'files') {
                        // If only files were deleted, show reinstall button
                        showReinstallButton($row, repoData.id);
                        showNotice('success', response.data.message || wpGitPlugins.i18n.delete_success);
                    } else if (deleteOption === 'database' || deleteOption === 'both') {
                        // If database record was deleted, remove the row
                        $row.fadeOut(400, function() {
                            $(this).remove();
                            showNotice('success', response.data.message || wpGitPlugins.i18n.delete_success);
                        });
                    }
                } else {
                    showNotice('error', response.data.message || wpGitPlugins.i18n.delete_error);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
                }
            },
            error: function() {
                showNotice('error', wpGitPlugins.i18n.delete_error);
                $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
            }
        });
    }

    // Show reinstall button when only files were deleted
    function showReinstallButton($row, repoId) {
        var $actionCell = $row.find('.action-buttons');
        var $deleteButton = $actionCell.find('.delete-repo');
        
        // Replace delete button with reinstall button
        $deleteButton.removeClass('delete-repo')
                    .addClass('reinstall-plugin')
                    .attr('data-repo-id', repoId)
                    .attr('title', wpGitPlugins.i18n.reinstall_plugin || 'Reinstall plugin')
                    .prop('disabled', false)
                    .html('<span class="dashicons dashicons-download"></span>');
        
        // Update status to show files are missing
        var $statusCell = $row.find('.plugin-status');
        $statusCell.html('<span class="status-missing">' + (wpGitPlugins.i18n.files_missing || 'Files missing') + '</span>');
    }

    // Handle reinstall action
    $(document).on('click', '.reinstall-plugin', function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var repoId = $button.data('repo-id');
        
        if (!confirm(wpGitPlugins.i18n.confirm_reinstall || 'Are you sure you want to reinstall this plugin?')) {
            return;
        }
        
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + (wpGitPlugins.i18n.installing || 'Installing...'));
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_reinstall_plugin',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || (wpGitPlugins.i18n.plugin_installed || 'Plugin installed successfully'));
                    // Reload page to update status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message || (wpGitPlugins.i18n.plugin_installation_failed || 'Plugin installation failed'));
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span>');
                }
            },
            error: function() {
                showNotice('error', wpGitPlugins.i18n.plugin_installation_failed || 'Plugin installation failed');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span>');
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
        var pluginSlug = $button.data('plugin'); // Use plugin slug instead of ID
        var confirmMessage = action === 'activate' ? wpGitPlugins.i18n.confirm_activate : wpGitPlugins.i18n.confirm_deactivate;

        if (!pluginSlug) {
            showNotice('error', 'Plugin slug not found');
            return;
        }

        if (!confirm(confirmMessage)) {
            return;
        }

        $button.prop('disabled', true);
        var $spinner = $button.find('.spinner');
        var $icon = $button.find('.dashicons');
        
        // Hide icon and show spinner
        $icon.hide();
        if ($spinner.length === 0) {
            $button.append('<span class="spinner" style="margin-top: -4px; float: none; display: inline-block;"></span>');
        } else {
            $spinner.show();
        }

        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_' + action + '_plugin',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                plugin_slug: pluginSlug // Use plugin_slug instead of plugin_id
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || (action === 'activate' ? wpGitPlugins.i18n.activate_success : wpGitPlugins.i18n.deactivate_success));
                    // Optionally reload the page or update UI
                    location.reload();
                } else {
                    showNotice('error', response.data.message || (action === 'activate' ? wpGitPlugins.i18n.activate_error : wpGitPlugins.i18n.deactivate_error));
                    // Restore button state
                    $button.prop('disabled', false);
                    $button.find('.spinner').hide();
                    $button.find('.dashicons').show();
                }
            },
            error: function() {
                showNotice('error', action === 'activate' ? wpGitPlugins.i18n.activate_error : wpGitPlugins.i18n.deactivate_error);
                // Restore button state
                $button.prop('disabled', false);
                $button.find('.spinner').hide();
                $button.find('.dashicons').show();
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
            console.log('Branches already loaded for this selector');
            return;
        }
        
        var $spinner = $container.find('.branch-spinner');
        $spinner.css('visibility', 'visible').addClass('is-active');
        
        var repoId = $container.data('repo-id');
        var ghOwner = $container.data('gh-owner');
        var ghName = $container.data('gh-name');
        var currentBranch = $select.data('current-branch');
        
        console.log('Loading branches for:', {
            repoId: repoId,
            ghOwner: ghOwner,
            ghName: ghName,
            currentBranch: currentBranch,
            ajaxUrl: wpGitPlugins.ajax_url,
            nonce: wpGitPlugins.ajax_nonce
        });
        
        // Validate required data
        if (!repoId || !ghOwner || !ghName) {
            console.error('Missing required data for branch loading:', {
                repoId: repoId,
                ghOwner: ghOwner,
                ghName: ghName
            });
            showNotice('error', 'Missing repository information for loading branches');
            $spinner.css('visibility', 'hidden').removeClass('is-active');
            return;
        }
        
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
                console.log('Branch loading response:', response);
                
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
                    console.log('Branches loaded successfully:', response.data.branches);
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
                console.error('Error loading branches:', {xhr: xhr, status: status, error: error});
                console.error('Response text:', xhr.responseText);
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