# WP Fork

A WordPress plugin that allows you to fork posts like Git branches - create copies, make changes, compare differences, and merge back to the original.

## Features

- **Custom Post Type (Fork)**: Dedicated CPT for managing forks
- **Settings Page**: Configure which post types can be forked and post-merge actions
- **Fork Creation**: One-click forking from any enabled post type
- **Gutenberg Support**: Full Gutenberg editor support with custom Merge and Compare buttons
- **Draft/Merge States**: Forks have only draft and merged states (no publish button)
- **Merge Functionality**: Merge fork content back to the original post directly from the editor (like Git merge)
- **Comparison View**: Side-by-side comparison of fork vs original
- **Post-Merge Actions**: Choose to delete or lock (trash) forks after merging
- **Revision Support**: Creates backup revisions before merging

## Installation

1. Upload the `wp-fork` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings > WP Fork to configure the plugin

## Configuration

### Settings Page (Settings > WP Fork)

1. **Enable Fork for Post Types**: Select which post types should have the fork feature
2. **After Merging Fork**: Choose what happens to the fork after merging:
   - **Delete**: Permanently delete the fork
   - **Lock**: Move the fork to trash
