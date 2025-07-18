jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Remove active class from all tabs and panes
        $('.nav-tab').removeClass('nav-tab-active');
        $('.tab-pane').removeClass('active');
        
        // Add active class to clicked tab and corresponding pane
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).addClass('active');
    });

    $('.debug-tabs .nav-tab').on('click', function(e) {
        e.preventDefault();
        var tabId = $(this).attr('href');
        $('.debug-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.debug-tab-content').hide();
        $(tabId).show();
    });
    
    
    // Toggle error trace
    $('.error-log-container').on('click', '.toggle-trace', function() {
        const $button = $(this);
        const $trace = $button.siblings('.error-trace');
        
        if ($trace.is(':visible')) {
            $trace.hide();
            $button.text(wpGitPluginsDebug.i18n.show_trace || 'Show trace');
        } else {
            $trace.show();
            $button.text(wpGitPluginsDebug.i18n.hide_trace || 'Hide trace');
        }
    });
    
    // Clear logs/history
    $('.clear-log, .clear-history').on('click', function() {
        if (!confirm(wpGitPluginsDebug.i18n.clear_confirm || 'Are you sure you want to clear this log?')) {
            return;
        }
        
        const $button = $(this);
        const $container = $button.closest('.tablenav');
        const logType = $button.data('type');
        const $spinner = $button.siblings('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        $.ajax({
            url: wpGitPluginsDebug.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_clear_log',
                nonce: wpGitPluginsDebug.nonce,
                log_type: logType
            },
            success: function(response) {
                if (response.success) {
                    // Reload the current tab
                    const $activeTab = $('.nav-tab-active');
                    if ($activeTab.length) {
                        $activeTab.trigger('click');
                    }
                } else {
                    alert(wpGitPluginsDebug.i18n.error + ' ' + (response.data || 'Unknown error'));
                }
            },
            error: function(xhr) {
                alert(wpGitPluginsDebug.i18n.error + ' ' + (xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data : 'Unknown error'));
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Check GitHub rate limit
    function checkGitHubRateLimit() {
        const $rateLimit = $('.github-rate-limit');
        const $spinner = $rateLimit.find('.spinner');
        const $text = $rateLimit.find('.rate-limit-text');
        
        $.ajax({
            url: wpGitPluginsDebug.ajax_url,
            type: 'POST',
            data: {
                action: 'wp_git_plugins_check_rate_limit',
                nonce: wpGitPluginsDebug.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    const limit = response.data.limit;
                    const remaining = response.data.remaining;
                    const reset = new Date(response.data.reset * 1000).toLocaleTimeString();
                    const percentage = Math.round((remaining / limit) * 100);
                    
                    let statusClass = 'high';
                    if (percentage < 10) {
                        statusClass = 'critical';
                    } else if (percentage < 30) {
                        statusClass = 'low';
                    }
                    
                    $text.html(`<span class="status-${statusClass}">${remaining}/${limit}</span> (resets at ${reset})`);
                } else {
                    $text.text(wpGitPluginsDebug.i18n.error || 'Error');
                }
            },
            error: function() {
                $text.text(wpGitPluginsDebug.i18n.error || 'Error');
            },
            complete: function() {
                $spinner.remove();
            }
        });
    }
    
    // Only check rate limit if we're on the debug page and GitHub is connected
    if ($('.github-rate-limit').length && $('.github-rate-limit').find('.spinner').length) {
        checkGitHubRateLimit();
    }
});