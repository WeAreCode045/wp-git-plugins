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
        var $tableContainer = $('#repos-list-table-container');
        $tableContainer.empty();
        if (repositories.length === 0) {
            $tableContainer.html('<table class="wp-list-table widefat fixed striped"><tbody><tr><td colspan="8">No repositories found.</td></tr></tbody></table>');
            return;
        }
        var html = '';
        html += '<table id="repos-list-table" class="wp-list-table widefat fixed striped">';
        html += '<thead><tr>';
        html += '<th><input type="checkbox" id="select-all-repos"></th>';
        html += '<th>Name</th>';
        html += '<th>Branches</th>';
        html += '<th>Most Recent Commit (per branch)</th>';
        html += '<th>Description</th>';
        html += '<th>Language</th>';
        html += '<th>Private</th>';
        html += '<th>Link</th>';
        html += '</tr></thead><tbody>';
        repositories.forEach(function(repo, idx) {
            var languageHtml = repo.language ? repo.language : '';
            var privateHtml = repo.private ? 'Private' : 'Public';
            var description = repo.description || '';
            var branchNames = '';
            var branchCommits = '';
            if (Array.isArray(repo.branches) && repo.branches.length) {
                branchNames = repo.branches.map(function(b){ return b.name; }).join('<br>');
                branchCommits = repo.branches.map(function(b){ return b.latest_commit ? formatDate(b.latest_commit) : '-'; }).join('<br>');
            }
            html += '<tr>';
            html += '<td><input type="checkbox" data-repo="' + encodeURIComponent(JSON.stringify(repo)) + '" /></td>';
            html += '<td>' + repo.name + '</td>';
            html += '<td>' + branchNames + '</td>';
            html += '<td>' + branchCommits + '</td>';
            html += '<td>' + description + '</td>';
            html += '<td>' + languageHtml + '</td>';
            html += '<td>' + privateHtml + '</td>';
            html += '<td><a href="' + repo.html_url + '" target="_blank">View</a></td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        $tableContainer.html(html);
        // Select all functionality
        $tableContainer.on('click', '#select-all-repos', function() {
            var checked = $(this).is(':checked');
            $tableContainer.find('input[type="checkbox"]').prop('checked', checked);
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
