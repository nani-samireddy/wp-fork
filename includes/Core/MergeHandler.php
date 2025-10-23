<?php
namespace WPFork\Core;

/**
 * Merge Handler - Handles merging forks back to original posts
 */
class MergeHandler {
    
    /**
     * Initialize hooks
     */
    public function init() {
        add_action('wp_ajax_merge_fork', array($this, 'ajax_merge_fork'));
    }
    
    /**
     * AJAX handler for merging fork
     */
    public function ajax_merge_fork() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'merge_fork')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'wp-fork')
            ));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array(
                'message' => __('You do not have permission to merge forks.', 'wp-fork')
            ));
        }
        
        // Get fork and original IDs
        $fork_id = isset($_POST['fork_id']) ? absint($_POST['fork_id']) : 0;
        $original_id = isset($_POST['original_id']) ? absint($_POST['original_id']) : 0;
        
        if (!$fork_id || !$original_id) {
            wp_send_json_error(array(
                'message' => __('Invalid fork or original post ID.', 'wp-fork')
            ));
        }
        
        // Perform the merge
        $result = $this->merge_fork($fork_id, $original_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array(
                'message' => $result->get_error_message()
            ));
        }
        
        // Return detailed merge result
        wp_send_json_success($result);
    }
    
    /**
     * Merge fork into original post with 3-way merge logic
     *
     * @param int $fork_id Fork post ID
     * @param int $original_id Original post ID
     * @return array|WP_Error Array with merge result on success, WP_Error on failure
     */
    public function merge_fork($fork_id, $original_id) {
        // Get fork post
        $fork = get_post($fork_id);
        if (!$fork || $fork->post_type !== 'fork') {
            return new \WP_Error('invalid_fork', __('Invalid fork post.', 'wp-fork'));
        }
        
        // Get original post
        $original = get_post($original_id);
        if (!$original) {
            return new \WP_Error('invalid_original', __('Original post not found.', 'wp-fork'));
        }
        
        // Verify this fork belongs to this original post
        $stored_original_id = get_post_meta($fork_id, '_fork_original_post_id', true);
        if ($stored_original_id != $original_id) {
            return new \WP_Error('mismatch', __('Fork does not belong to this original post.', 'wp-fork'));
        }
        
        // Check if already merged
        $fork_state = get_post_meta($fork_id, '_fork_state', true);
        if ($fork_state === 'merged') {
            return new \WP_Error('already_merged', __('This fork has already been merged.', 'wp-fork'));
        }
        
        // Get the base version (original at fork creation time)
        $base_content = get_post_meta($fork_id, '_fork_base_content', true);
        $base_title = get_post_meta($fork_id, '_fork_base_title', true);
        $base_excerpt = get_post_meta($fork_id, '_fork_base_excerpt', true);
        
        // Detect conflicts using 3-way merge
        $conflicts = array();
        $merged_content = $fork->post_content;
        $merged_title = $fork->post_title;
        $merged_excerpt = $fork->post_excerpt;
        
        // Check if original changed since fork was created
        if ($base_content && $base_content !== $original->post_content) {
            // Original was modified after fork
            if ($fork->post_content !== $base_content) {
                // Both fork and original modified - CONFLICT
                $conflicts[] = array(
                    'field' => 'content',
                    'message' => __('Content was modified in both fork and original post.', 'wp-fork'),
                    'base' => $base_content,
                    'original' => $original->post_content,
                    'fork' => $fork->post_content,
                );
                // Use fork version but mark as conflict
                $merged_content = $fork->post_content;
            } else {
                // Only original modified - keep original changes
                $merged_content = $original->post_content;
            }
        }
        
        if ($base_title && $base_title !== $original->post_title) {
            if ($fork->post_title !== $base_title) {
                $conflicts[] = array(
                    'field' => 'title',
                    'message' => __('Title was modified in both fork and original post.', 'wp-fork'),
                    'base' => $base_title,
                    'original' => $original->post_title,
                    'fork' => $fork->post_title,
                );
                $merged_title = $fork->post_title;
            } else {
                $merged_title = $original->post_title;
            }
        }
        
        if ($base_excerpt && $base_excerpt !== $original->post_excerpt) {
            if ($fork->post_excerpt !== $base_excerpt) {
                $conflicts[] = array(
                    'field' => 'excerpt',
                    'message' => __('Excerpt was modified in both fork and original post.', 'wp-fork'),
                    'base' => $base_excerpt,
                    'original' => $original->post_excerpt,
                    'fork' => $fork->post_excerpt,
                );
                $merged_excerpt = $fork->post_excerpt;
            } else {
                $merged_excerpt = $original->post_excerpt;
            }
        }
        
        // Create a backup revision of the original post before merging
        wp_save_post_revision($original_id);
        
        // Update original post with merged content
        $update_data = array(
            'ID'           => $original_id,
            'post_title'   => $merged_title,
            'post_content' => $merged_content,
            'post_excerpt' => $merged_excerpt,
        );
        
        $updated = wp_update_post($update_data, true);
        
        if (is_wp_error($updated)) {
            return $updated;
        }
        
        // Merge custom fields (excluding protected meta)
        $this->merge_post_meta($fork_id, $original_id);
        
        // Merge taxonomies
        $this->merge_taxonomies($fork_id, $original_id, $original->post_type);
        
        // Update featured image if changed
        $fork_thumbnail = get_post_thumbnail_id($fork_id);
        if ($fork_thumbnail) {
            set_post_thumbnail($original_id, $fork_thumbnail);
        }
        
        // Update fork state to merged
        update_post_meta($fork_id, '_fork_state', 'merged');
        update_post_meta($fork_id, '_fork_merged_date', current_time('mysql'));
        update_post_meta($fork_id, '_fork_merged_by', get_current_user_id());
        
        // Handle post-merge action based on settings
        $this->handle_post_merge_action($fork_id);
        
        // Add a note to the original post
        $this->add_merge_note($original_id, $fork_id);
        
        // Return detailed merge result
        return array(
            'success' => true,
            'conflicts' => $conflicts,
            'has_conflicts' => !empty($conflicts),
            'original_id' => $original_id,
            'original_url' => get_edit_post_link($original_id, 'raw'),
            'message' => empty($conflicts) 
                ? __('Fork merged successfully!', 'wp-fork')
                : __('Fork merged with conflicts. Please review the changes.', 'wp-fork'),
        );
    }
    
    /**
     * Merge post meta from fork to original
     *
     * @param int $fork_id Fork post ID
     * @param int $original_id Original post ID
     */
    private function merge_post_meta($fork_id, $original_id) {
        $fork_meta = get_post_meta($fork_id);
        
        if (!$fork_meta) {
            return;
        }
        
        foreach ($fork_meta as $meta_key => $meta_values) {
            // Skip fork-specific and protected meta keys
            if (substr($meta_key, 0, 6) === '_fork_' || 
                (substr($meta_key, 0, 1) === '_' && $meta_key !== '_thumbnail_id')) {
                continue;
            }
            
            // Delete existing meta
            delete_post_meta($original_id, $meta_key);
            
            // Add new meta from fork
            foreach ($meta_values as $meta_value) {
                add_post_meta($original_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
    }
    
    /**
     * Merge taxonomies from fork to original
     *
     * @param int $fork_id Fork post ID
     * @param int $original_id Original post ID
     * @param string $post_type Post type
     */
    private function merge_taxonomies($fork_id, $original_id, $post_type) {
        $taxonomies = get_object_taxonomies($post_type);
        
        if (!$taxonomies) {
            return;
        }
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($fork_id, $taxonomy, array('fields' => 'ids'));
            
            if (!is_wp_error($terms)) {
                wp_set_object_terms($original_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Handle post-merge action based on settings
     *
     * @param int $fork_id Fork post ID
     */
    private function handle_post_merge_action($fork_id) {
        $settings = get_option('wp_fork_settings');
        $merge_action = isset($settings['merge_action']) ? $settings['merge_action'] : 'lock';
        
        if ($merge_action === 'delete') {
            // Permanently delete the fork
            wp_delete_post($fork_id, true);
        } else {
            // Lock the fork by moving to trash
            wp_trash_post($fork_id);
        }
    }
    
    /**
     * Add a merge note to the original post
     *
     * @param int $original_id Original post ID
     * @param int $fork_id Fork post ID
     */
    private function add_merge_note($original_id, $fork_id) {
        $user = wp_get_current_user();
        $fork_title = get_the_title($fork_id);
        
        $comment_data = array(
            'comment_post_ID'      => $original_id,
            'comment_author'       => $user->display_name,
            'comment_author_email' => $user->user_email,
            'comment_content'      => sprintf(
                __('Fork "%s" (ID: %d) was merged into this post.', 'wp-fork'),
                $fork_title,
                $fork_id
            ),
            'comment_type'         => 'fork_merge',
            'comment_approved'     => 1,
            'user_id'              => $user->ID,
        );
        
        wp_insert_comment($comment_data);
    }
}
