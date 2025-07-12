jQuery(document).ready(function($) {
    // Branch change UI logic for repo list
    $('.wp-git-plugins-container').on('click', '.change-branch-btn', function(e) {
        e.preventDefault();
        // Try to find the branch select next to the button
        const branchSelect = $(this).siblings('.branch-select');
        if (branchSelect.length) {
            branchSelect.toggle();
        } else {
            // Fallback: find in parent .repo-row
            const repoRow = $(this).closest('.repo-row');
            repoRow.find('.branch-select').toggle();
        }
    });

    // Handle branch selection and change
    $('.wp-git-plugins-container').on('change', '.branch-select', function(e) {
        const select = $(this);
        const repoRow = select.closest('.repo-row');
        const repoUrl = repoRow.data('repo');
        const newBranch = select.val();
        const button = repoRow.find('.change-branch-btn');
        button.prop('disabled', true).html('<span class="spinner is-active"></span>');
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_change_branch',
                nonce: wpGitPlugins.ajax_nonce,
                repo_url: repoUrl,
                branch: newBranch
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', 'Branch changed to ' + newBranch);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotice('error', response.data.message || 'Failed to change branch');
                    button.prop('disabled', false).html('Change Branch');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred. Please try again.');
                button.prop('disabled', false).html('Change Branch');
            }
        });
    });
    // Debounce function to limit API calls
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this, args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
    
    // Handle repository URL input changes
    $('#repo-url').on('input', debounce(function() {
        const repoUrl = $(this).val().trim();
        const branchSelect = $('#repo-branch');
        const branchField = branchSelect.closest('tr');
        
        // Only proceed if we have a valid GitHub URL
        if (!repoUrl.match(/^https?:\/\/github\.com\/[^\/]+\/[^\/]+/)) {
            return;
        }
        
        // Show loading state
        branchField.addClass('loading');
        
        // Fetch branches
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_get_branches',
                nonce: wpGitPlugins.ajax_nonce,
                repo_url: repoUrl
            },
            success: function(response) {
                if (response.success && response.data.branches && response.data.branches.length > 0) {
                    // Clear existing options except the first one (which is the default/loading option)
                    branchSelect.find('option:not(:first)').remove();
                    
                    // Add new branch options
                    response.data.branches.forEach(function(branch) {
                        branchSelect.append(new Option(branch, branch));
                    });
                    
                    // Set default branch (main or master)
                    const defaultBranch = response.data.branches.includes('main') ? 'main' : 
                                        (response.data.branches.includes('master') ? 'master' : response.data.branches[0]);
                    
                    branchSelect.val(defaultBranch);
                }
            },
            error: function(xhr) {
                console.error('Error fetching branches:', xhr.responseText);
            },
            complete: function() {
                branchField.removeClass('loading');
            }
        });
    }, 800));
    
    // Add loading indicator style
    $('<style>')
        .prop('type', 'text/css')
        .html('tr.loading select { background-image: url(\'data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'20\' height=\'20\' viewBox=\'0 0 20 20\' fill=\'%23222222\'><path d=\'M10 3.5A6.5 6.5 0 1 1 3.5 10 .75.75 0 0 1 2 10a8 8 0 1 0 8-8 .75.75 0 0 1 0-1.5Z\' clip-rule=\'evenodd\' /></svg>\'); background-position: right 8px center; background-repeat: no-repeat; background-size: 16px 16px; }')
        .appendTo('head');
        
    // Initialize the branch field
    $('#repo-branch').closest('tr').addClass('loading');
    // Handle repository actions
    $('.wp-git-plugins-container').on('click', '.install-plugin', function(e) {
        e.preventDefault();
        const button = $(this);
        const repoUrl = button.data('repo');
        const isPrivate = $('#is-private').is(':checked') ? 1 : 0;
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_install',
                nonce: wpGitPlugins.ajax_nonce,
                repo_url: repoUrl
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                is_private: isPrivate
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotice('error', response.data.message);
                    button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span>');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span>');
            }
        });
    });

    // Activate plugin
    $('.wp-git-plugins-container').on('click', '.activate-plugin', function(e) {
        e.preventDefault();
        const button = $(this);
        const pluginSlug = button.data('plugin');
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_activate',
                nonce: wpGitPlugins.ajax_nonce,
                plugin_slug: pluginSlug
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotice('error', response.data.message);
                    button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span>');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span>');
            }
        });
    });

    // Deactivate plugin
    $('.wp-git-plugins-container').on('click', '.deactivate-plugin', function(e) {
        e.preventDefault();
        const button = $(this);
        const pluginSlug = button.data('plugin');
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_deactivate',
                nonce: wpGitPlugins.ajax_nonce,
                plugin_slug: pluginSlug
            },
            success: function() {
                showNotice('success', 'Plugin deactivated successfully');
                setTimeout(() => window.location.reload(), 1500);
            },
            error: function() {
                showNotice('error', 'An error occurred. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-controls-pause"></span>');
            }
        });
    });

    // Delete repository
    $('.wp-git-plugins-container').on('click', '.delete-repo, .delete-plugin', function(e) {
        e.preventDefault();
        
        if (!confirm(wpGitPlugins.i18n.confirm_delete)) {
            return;
        }
        
        const button = $(this);
        const repoUrl = button.data('repo');
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span>');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_delete',
                nonce: wpGitPlugins.ajax_nonce,
                repo_url: repoUrl
            },
            success: function(response) {
                if (response.success) {
                    showNotice('success', response.data.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotice('error', response.data.message);
                    button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred. Please try again.');
                button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
            }
        });
    });

    // Check for updates
    $('#wp-git-plugins-check-updates').on('click', function(e) {
        e.preventDefault();
        const button = $(this);
        
        button.prop('disabled', true).html('<span class="spinner is-active"></span> Checking...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_check_updates',
                nonce: wpGitPlugins.ajax_nonce
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.updates && response.data.updates.length > 0) {
                        showNotice('success', 'Updates are available for ' + response.data.updates.length + ' plugins');
                    } else {
                        showNotice('info', 'All plugins are up to date');
                    }
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showNotice('error', response.data.message || 'An error occurred while checking for updates');
                }
            },
            error: function() {
                showNotice('error', 'An error occurred while checking for updates');
            },
            complete: function() {
                button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Check for Updates');
            }
        });
    });

    // Show notice function
    function showNotice(type, message) {
        const notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        $('.wrap h1').after(notice);
        
        // Auto-remove notice after 5 seconds
        setTimeout(() => {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Handle tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        const target = $(this).data('target');
        $('.tab-content').hide();
        $(target).show();
    });

    // Initialize first tab as active
    $('.nav-tab:first').addClass('nav-tab-active');
    $('.tab-content').hide().first().show();
});
