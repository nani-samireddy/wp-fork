=== WP Fork ===
Contributors: nanisamireddy
Tags: editor, gutenberg, fork, revisions, compare, merge
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Fork posts like Git branches. Create a draft fork, edit in Gutenberg, compare side‑by‑side with the original, and merge back safely.

== Description ==

WP Fork lets teams iterate on content without touching the live post until changes are approved.

- Fork any enabled post type from the list table
- Compare fork vs original in a modal using BlockPreview
- Merge back with an automatic revision backup
- Track fork state (draft or merged)
- Configure enabled post types and post‑merge behavior (delete or trash)

Requirements:

- WordPress 6.0+
- Block Editor (Gutenberg) enabled

== Installation ==

1. Upload the `wp-fork` folder to `/wp-content/plugins/`.
2. Activate “WP Fork” from Plugins in wp‑admin.
3. Go to Forks → Options to select enabled post types and merge behavior.

== Usage ==

- In Posts/Pages, hover a row and click “Fork”.
- Edit the Fork in the block editor.
- Click “Compare with Original” to preview both versions.
- Click “Merge into Original” when ready. A backup revision is created first.

Notes:

- Forks cannot be created via “Add New” and cannot be published directly.
- Built assets are required for the editor integration (files under `build/editor`).

== Frequently Asked Questions ==

= How do I create a fork? =
From the post list table, use the “Fork” row action. Forks are not created via “Add New”.

= Can I merge custom fields and taxonomies? =
Yes. On merge, standard meta (excluding protected meta) and taxonomies are copied from the fork to the original.

= What happens on merge? =
The original post gets a backup revision, then title/content/excerpt (and meta/taxonomies) are updated from the fork. The fork is deleted or trashed based on settings.

== Screenshots ==
1. Compare modal previewing original vs fork
2. “Fork” row action in the post list
3. Merge confirmation and success

== Changelog ==

= 1.0.0 =
Initial public release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.

