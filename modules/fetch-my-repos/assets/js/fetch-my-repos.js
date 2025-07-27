/**
 * Fetch My Repos Module JavaScript
 * Handles fetching and displaying user repositories
 */

jQuery(document).ready(function($) {
    
    // Handle fetch repos button click
    $('#fetch-repos-btn').on('click', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $spinner = $button.find('.spinner');
        var $results = $('#fetch-repos-results');
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        $results.hide();
        $.ajax({
            url: wpGitPluginsFetchRepos.ajax_url,
            type: 'POST',
            data: {
                action: 'wpgp_fetch_user_repos',
                _ajax_nonce: wpGitPluginsFetchRepos.ajax_nonce
            },
            success: function(response) {
                if (response.success) {
                    displayRepositories(response.data.repositories);
                    $results.show();
                } else {
                    alert('Error: ' + (response.data || 'Failed to fetch repositories'));
                }
            },
            error: function() {
                alert('Failed to fetch repositories. Please try again.');
            },
            complete: function() {
                $button.prop('disabled', false);
                $spinner.removeClass('is-active');
            }
        });
    });
    
    // Handle adding selected repositories
    $('#add-selected-repos').on('click', function(e) {
        e.preventDefault();
        
        var selectedRepos = [];
        $('#repos-list input[type="checkbox"]:checked').each(function() {
            var repoData = $(this).data('repo');
            selectedRepos.push(repoData);
        });
        
        if (selectedRepos.length === 0) {
            alert('Please select at least one repository');
            return;
        }
        
        var $button = $(this);
        var $spinner = $button.find('.spinner');
        
        $button.prop('disabled', true);
        $spinner.addClass('is-active');
        
        // Add repositories one by one
        addRepositoriesSequentially(selectedRepos, 0, function() {
            $button.prop('disabled', false);
            $spinner.removeClass('is-active');
            
            // Reload the page to refresh the repository list
            alert('Selected repositories have been added successfully!');
            location.reload();
        });
    });
    
    // Display repositories in a selectable list
    function displayRepositories(repositories) {
        var $reposList = $('#repos-list');
        $reposList.empty();
        
        if (repositories.length === 0) {
            $reposList.html('<p>No repositories found.</p>');
            return;
        }
        
        var html = '<div class="repos-grid">';
        
        repositories.forEach(function(repo) {
            var languageHtml = repo.language ? '<span class="language">' + repo.language + '</span>' : '';
            var privateHtml = repo.private ? '<span class="private">Private</span>' : '<span class="public">Public</span>';
            var description = repo.description || 'No description available';
            
            html += '<div class="repo-item">';
            html += '<label>';
            html += '<input type="checkbox" data-repo="' + encodeURIComponent(JSON.stringify(repo)) + '" />';
            html += '<div class="repo-details">';
            html += '<h4>' + repo.name + ' ' + privateHtml + '</h4>';
            html += '<p class="description">' + description + '</p>';
            html += '<div class="repo-meta">';
            html += languageHtml;
            html += '<span class="updated">Updated: ' + formatDate(repo.updated_at) + '</span>';
            html += '</div>';
            html += '</div>';
            html += '</label>';
            html += '</div>';
        });
        
        html += '</div>';
        html += '<div class="select-actions">';
        html += '<button type="button" id="select-all-repos" class="button">Select All</button>';
        html += '<button type="button" id="select-none-repos" class="button">Select None</button>';
        html += '</div>';
        
        $reposList.html(html);
        
        // Add select all/none functionality
        $('#select-all-repos').on('click', function() {
            $('#repos-list input[type="checkbox"]').prop('checked', true);
        });
        
        $('#select-none-repos').on('click', function() {
            $('#repos-list input[type="checkbox"]').prop('checked', false);
        });
    }
    
    // Add repositories sequentially to avoid overwhelming the server
    function addRepositoriesSequentially(repos, index, callback) {
        if (index >= repos.length) {
            callback();
            return;
        }
        
        var repo = repos[index];
        
        // Use the existing add repository AJAX endpoint
        $.ajax({
            url: wpGitPlugins.ajax_url,
            type: 'POST',
            data: {
                action: 'wpgp_add_repository',
                _ajax_nonce: wpGitPlugins.ajax_nonce,
                url: repo.clone_url,
                name: repo.name,
                description: repo.description
            },
            success: function(response) {
                console.log('Added repository: ' + repo.name);
                // Continue with next repository after a short delay
                setTimeout(function() {
                    addRepositoriesSequentially(repos, index + 1, callback);
                }, 500);
            },
            error: function() {
                console.error('Failed to add repository: ' + repo.name);
                // Continue with next repository even if this one failed
                setTimeout(function() {
                    addRepositoriesSequentially(repos, index + 1, callback);
                }, 500);
            }
        });
    }
    
    // Format date for display
    function formatDate(dateString) {
        var date = new Date(dateString);
        return date.toLocaleDateString();
    }
    
    // Handle tab switching to show/hide fetch repos tab
    $(document).on('click', '.nav-tab', function(e) {
        if ($(this).attr('href') === '#fetch-repos') {
            e.preventDefault();
            
            // Hide all tab content
            $('.tab-content').hide();
            
            // Remove active class from all tabs
            $('.nav-tab').removeClass('nav-tab-active');
            
            // Show fetch repos content and make tab active
            $('#fetch-repos').show();
            $(this).addClass('nav-tab-active');
        }
    });
});
