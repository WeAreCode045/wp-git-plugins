# Update Method Changed to Git Clone

## ✅ Changes Made

The update plugin functionality has been modified to use the same git clone method as the branch change feature, but without changing the branch.

### **Key Changes to `ajax_update_repository` method:**

1. **Removed old installation method**: No longer uses `$local_plugins->install_plugin()`
2. **Added git clone approach**: Same method as branch switching
3. **Preserves current branch**: Uses existing branch from database
4. **Direct file operations**: Deletes old plugin directory and clones fresh copy
5. **Updates local version**: Reads version from newly cloned plugin files

### **Step-by-step Process:**

1. **Deactivate plugin** (if active)
2. **Delete existing plugin directory** using `WP_Git_Plugins::rrmdir()`
3. **Clone repository** with current branch using `git clone --single-branch --branch`
4. **Update database** with new timestamps and detected plugin version
5. **Reactivate plugin** (if was previously active)

### **Benefits:**

- ✅ **Consistent method**: Same reliable git clone approach used for branch changes
- ✅ **Fresh installation**: Completely clean copy from repository
- ✅ **No dependency issues**: Direct git operations instead of WordPress plugin installer
- ✅ **Branch preservation**: Keeps current branch selection
- ✅ **Version detection**: Automatically updates local version from plugin headers

### **Technical Details:**

- Uses `git clone --single-branch --branch {current_branch}` command
- Supports both public and private repositories (with GitHub token)
- Includes comprehensive error handling and logging
- Updates database with fresh timestamps and version info
- Maintains plugin activation state through the update process

The update button now works exactly like the branch change feature but keeps the same branch, providing a reliable and consistent update mechanism.
