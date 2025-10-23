WP Fork

Fork posts like Git branches. Create a draft fork, edit in Gutenberg, compare side‑by‑side, and merge back safely.

Description
- Fork any enabled post type from the list table
- Compare fork vs original in a modal using BlockPreview
- Merge back with an automatic revision backup
- Track fork state (draft or merged)
- Configure enabled post types and post‑merge behavior (delete or trash)

Requirements
- WordPress 6.0+
- Block Editor (Gutenberg) enabled

Installation
1. Copy the `wp-fork` folder to `wp-content/plugins/`
2. Activate “WP Fork” in Plugins
3. Forks → Options: choose enabled post types and merge behavior

Usage
- From Posts/Pages list: hover a row → Fork
- Edit the Fork (Gutenberg). Use “Compare with Original” to preview both versions.
- Click “Merge into Original” when ready. A revision of the original is created first.

Notes
- Forks cannot be created via “Add New” and cannot be published directly.
- Built assets are required (build/editor). For development run `npm install && npm run build`.

Changelog
- 1.0.0: Initial public release
