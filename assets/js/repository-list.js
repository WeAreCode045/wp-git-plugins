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
        console.log('showReinstallButton called with repo ID:', repoId);
        
        var $actionCell = $row.find('.action-buttons');
        var $deleteButton = $actionCell.find('.delete-repo');
        
        console.log('Found action cell:', $actionCell.length);
        console.log('Found delete button:', $deleteButton.length);
        
        // Replace delete button with reinstall button
        $deleteButton.removeClass('delete-repo')
                    .addClass('reinstall-plugin')
                    .attr('data-repo-id', repoId)
                    .attr('title', wpGitPlugins.i18n.reinstall_plugin || 'Reinstall plugin')
                    .prop('disabled', false)
                    .html('<span class="dashicons dashicons-download"></span>');
        
        console.log('Button classes after change:', $deleteButton.attr('class'));
        console.log('Button data-repo-id:', $deleteButton.attr('data-repo-id'));
        
        // Update status to show files are missing
        var $statusCell = $row.find('.plugin-status');
        $statusCell.html('<span class="status-missing">' + (wpGitPlugins.i18n.files_missing || 'Files missing') + '</span>');
        
        console.log('Status updated to files missing');
    }

    // Handle reinstall action
    $(document).on('click', '.install-plugin', function(e) {
        e.preventDefault();
        console.log('Reinstall button clicked');
        
        var $button = $(this);
        var repoId = $button.data('repo-id');
        
        console.log('Repo ID:', repoId);
        console.log('AJAX URL:', wpGitPlugins.ajax_url);
        console.log('Nonce:', wpGitPlugins.ajax_nonce);
        
        if (!repoId) {
            console.error('No repo ID found');
            showNotice('error', 'Repository ID not found');
            return;
        }
        
        if (!confirm(wpGitPlugins.i18n.confirm_reinstall || 'Are you sure you want to reinstall this plugin?')) {
            return;
        }
        
        $button.prop('disabled', true).html('<span class="spinner is-active"></span> ' + (wpGitPlugins.i18n.installing || 'Installing...'));
        
        console.log('Sending AJAX request for reinstall');
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_reinstall_plugin',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId
            },
            success: function(response) {
                console.log('Reinstall response:', response);
                if (response.success) {
                    showNotice('success', response.data.message || (wpGitPlugins.i18n.plugin_installed || 'Plugin installed successfully'));
                    // Reload page to update status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    console.error('Reinstall failed:', response);
                    showNotice('error', response.data.message || (wpGitPlugins.i18n.plugin_installation_failed || 'Plugin installation failed'));
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                console.error('Response text:', xhr.responseText);
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

    // Handle check version action
    $(document).on('click', '.check-version', function(e) {
        e.preventDefault();
        var $button = $(this);
        var repoId = $button.data('id');

        if (!repoId) {
            showNotice('error', 'Repository ID not found');
            return;
        }

        // Disable button and show loading state
        $button.prop('disabled', true);
        var $spinner = $button.find('.spinner');
        var $icon = $button.find('.dashicons');
        
        // Hide icon and show spinner
        $icon.hide();
        if ($spinner.length === 0) {
            $button.append('<span class="spinner" style="margin-top: -4px; float: none; display: inline-block;"></span>');
            $spinner = $button.find('.spinner');
        }
        $spinner.show();

        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_check_version',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId
            },
            success: function(response) {
                console.log('Version check response:', response);
                console.log('Response type:', typeof response);
                console.log('Response.data:', response.data);
                
                if (response && response.success) {
                    var message = (response.data && response.data.message) || wpGitPlugins.i18n.version_check_completed;
                    showNotice('success', message);
                    
                    // Debug version comparison
                    if (response.data) {
                        console.log('Version comparison debug:');
                        console.log('Git version:', response.data.git_version);
                        console.log('Local version:', response.data.local_version);
                        console.log('Update available:', response.data.update_available);
                    }
                    
                    // Update the version display in the table if needed
                    var $row = $button.closest('tr');
                    var $versionCell = $row.find('td:nth-child(4)'); // Latest Version column
                    if ($versionCell.length && response.data && response.data.git_version) {
                        $versionCell.text(response.data.git_version);
                    }
                    
                    // Check if update is available and replace check button with update button
                    if (response.data && response.data.update_available) {
                        var installedVersion = response.data.local_version || '0.0.0';
                        var latestVersion = response.data.git_version || '0.0.0';
                        
                        console.log('Update available! Replacing button...');
                        console.log('Installed:', installedVersion, 'Latest:', latestVersion);
                        
                        // Replace the check version button with update button
                        var $actionContainer = $button.closest('.action-buttons');
                        $button.remove();
                        
                        var updateButton = $('<button class="button button-primary button-small update-plugin" ' +
                                           'data-id="' + repoId + '" ' +
                                           'data-current-version="' + installedVersion + '" ' +
                                           'data-new-version="' + latestVersion + '" ' +
                                           'title="Update plugin">' +
                                           '<span class="dashicons dashicons-update"></span>' +
                                           '<span class="spinner" style="margin-top: -4px; float: none; display: none;"></span>' +
                                           '</button>');
                        
                        $actionContainer.prepend(updateButton);
                        
                        // Update the latest version cell with update-available styling
                        $versionCell.html('<span class="update-available" style="color: #d63638; font-weight: 500;">' + latestVersion + '</span>');
                    } else {
                        console.log('No update available or same version');
                        // No update available, just show success message without reloading
                        // The reload is not necessary if no update is available
                    }
                } else {
                    var errorMessage = (response && response.data && response.data.message) || wpGitPlugins.i18n.version_check_failed;
                    showNotice('error', errorMessage);
                }
                
                // Restore button state
                $button.prop('disabled', false);
                $spinner.hide();
                $icon.show();
            },
            error: function(xhr, status, error) {
                console.error('Version check AJAX error:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                // Try to parse error response
                var errorMessage = wpGitPlugins.i18n.version_check_failed;
                try {
                    if (xhr.responseText) {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse && errorResponse.data && errorResponse.data.message) {
                            errorMessage = errorResponse.data.message;
                        }
                    }
                } catch (parseError) {
                    console.log('Could not parse error response:', parseError);
                }
                
                showNotice('error', errorMessage);
                
                // Restore button state
                $button.prop('disabled', false);
                $spinner.hide();
                $icon.show();
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

    // Handle Update action
    $(document).on('click', '.update-plugin', function(e) {
        e.preventDefault();
        var $button = $(this);
        var repoId = $button.data('id'); // Changed from data-repo-id to data-id
        var currentVersion = $button.data('current-version');
        var newVersion = $button.data('new-version');
        
        if (!repoId) {
            showNotice('error', 'Repository ID not found');
            return;
        }
        
        // Show confirmation with version details
        var confirmMessage = wpGitPlugins.i18n.confirm_update 
            ? wpGitPlugins.i18n.confirm_update.replace('%s', currentVersion).replace('%s', newVersion)
            : 'Are you sure you want to update this plugin from version ' + currentVersion + ' to ' + newVersion + '?';
            
        if (!confirm(confirmMessage)) {
            return;
        }
        
        $button.prop('disabled', true);
        $button.find('.spinner').show();
        
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_update_repository', // Use existing update handler
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                repo_id: repoId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message || (wpGitPlugins.i18n.update_success || 'Plugin updated successfully'));
                    // Reload page to update status
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    showNotice('error', response.data.message || (wpGitPlugins.i18n.update_error || 'Plugin update failed'));
                    $button.prop('disabled', false);
                    $button.find('.spinner').hide();
                }
            },
            error: function() {
                showNotice('error', wpGitPlugins.i18n.update_error || 'Plugin update failed');
                $button.prop('disabled', false);
                $button.find('.spinner').hide();
            }
        });
    });

});