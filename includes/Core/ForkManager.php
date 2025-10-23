<?php
namespace WPFork\Core;

/**
 * Fork Manager - Handles fork creation and management
 */
class ForkManager {
    
    /**
     * Initialize hooks
     */
    public function init() {
        // Add meta box to fork edit screen
        add_action('add_meta_boxes', array($this, 'add_fork_meta_boxes'));
        add_action('save_post_fork', array($this, 'save_fork_meta'), 10, 2);
    }
    
    /**
     * Create a fork of a post
     *
     * @param int $original_post_id The ID of the post to fork
     * @return int|WP_Error The ID of the created fork or WP_Error on failure
     */
    public function create_fork($original_post_id) {
        // Get the original post
        $original_post = get_post($original_post_id);
        
        if (!$original_post) {
            return new \WP_Error('invalid_post', __('Original post not found.', 'wp-fork'));
        }
        
        // Prepare fork data
        $fork_data = array(
            // Keep the same title as original; no [Fork] prefix
            'post_title'    => $original_post->post_title,
            'post_content'  => $original_post->post_content,
            'post_excerpt'  => $original_post->post_excerpt,
            'post_status'   => 'draft',
            'post_type'     => 'fork',
            'post_author'   => get_current_user_id(),
        );
        
        // Create the fork
        $fork_id = wp_insert_post($fork_data);
        
        if (is_wp_error($fork_id)) {
            return $fork_id;
        }
        
        // Store original post metadata
        update_post_meta($fork_id, '_fork_original_post_id', $original_post_id);
        update_post_meta($fork_id, '_fork_original_post_type', $original_post->post_type);
        update_post_meta($fork_id, '_fork_state', 'draft');
        update_post_meta($fork_id, '_fork_created_date', current_time('mysql'));
        
        // Store base version (snapshot) for 3-way merge conflict detection
        update_post_meta($fork_id, '_fork_base_title', $original_post->post_title);
        update_post_meta($fork_id, '_fork_base_content', $original_post->post_content);
        update_post_meta($fork_id, '_fork_base_excerpt', $original_post->post_excerpt);
        
        // Copy post meta from original (excluding protected meta)
        $this->copy_post_meta($original_post_id, $fork_id);
        
        // Copy taxonomies
        $this->copy_taxonomies($original_post_id, $fork_id, $original_post->post_type);
        
        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($original_post_id);
        if ($thumbnail_id) {
            set_post_thumbnail($fork_id, $thumbnail_id);
        }
        
        return $fork_id;
    }
    
    /**
     * Copy post meta from original to fork
     *
     * @param int $from_post_id Source post ID
     * @param int $to_post_id Destination post ID
     */
    private function copy_post_meta($from_post_id, $to_post_id) {
        $post_meta = get_post_meta($from_post_id);
        
        if (!$post_meta) {
            return;
        }
        
        foreach ($post_meta as $meta_key => $meta_values) {
            // Skip protected meta keys
            if (substr($meta_key, 0, 1) === '_' && $meta_key !== '_thumbnail_id') {
                continue;
            }
            
            foreach ($meta_values as $meta_value) {
                add_post_meta($to_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
    }
    
    /**
     * Copy taxonomies from original to fork
     *
     * @param int $from_post_id Source post ID
     * @param int $to_post_id Destination post ID
     * @param string $post_type Post type
     */
    private function copy_taxonomies($from_post_id, $to_post_id, $post_type) {
        $taxonomies = get_object_taxonomies($post_type);
        
        if (!$taxonomies) {
            return;
        }
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_post_terms($from_post_id, $taxonomy, array('fields' => 'ids'));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($to_post_id, $terms, $taxonomy);
            }
        }
    }
    
    /**
     * Add meta boxes to fork edit screen
     */
    public function add_fork_meta_boxes() {
        add_meta_box(
            'fork_info',
            __('Fork Information', 'wp-fork'),
            array($this, 'render_fork_info_meta_box'),
            'fork',
            'side',
            'high'
        );
        
        add_meta_box(
            'fork_actions',
            __('Fork Actions', 'wp-fork'),
            array($this, 'render_fork_actions_meta_box'),
            'fork',
            'side',
            'high'
        );
    }
    
    /**
     * Render fork info meta box
     */
    public function render_fork_info_meta_box($post) {
        $original_post_id = get_post_meta($post->ID, '_fork_original_post_id', true);
        $original_post_type = get_post_meta($post->ID, '_fork_original_post_type', true);
        $fork_state = get_post_meta($post->ID, '_fork_state', true);
        $created_date = get_post_meta($post->ID, '_fork_created_date', true);
        
        ?>
        <div class="fork-info">
            <?php if ($original_post_id): ?>
                <?php $original_post = get_post($original_post_id); ?>
                <?php if ($original_post): ?>
                    <p>
                        <strong><?php _e('Original Post:', 'wp-fork'); ?></strong><br>
                        <a href="<?php echo esc_url(get_edit_post_link($original_post_id)); ?>">
                            <?php echo esc_html($original_post->post_title); ?>
                        </a>
                    </p>
                    <p>
                        <strong><?php _e('Post Type:', 'wp-fork'); ?></strong><br>
                        <?php echo esc_html($original_post_type); ?>
                    </p>
                <?php else: ?>
                    <p style="color: #d63638;">
                        <?php _e('Original post has been deleted.', 'wp-fork'); ?>
                    </p>
                <?php endif; ?>
            <?php endif; ?>
            
            <p>
                <strong><?php _e('Fork State:', 'wp-fork'); ?></strong><br>
                <?php if ($fork_state === 'merged'): ?>
                    <span style="color: #46b450;">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Merged', 'wp-fork'); ?>
                    </span>
                <?php else: ?>
                    <span style="color: #f0b849;">
                        <span class="dashicons dashicons-edit"></span>
                        <?php _e('Draft', 'wp-fork'); ?>
                    </span>
                <?php endif; ?>
            </p>
            
            <?php if ($created_date): ?>
                <p>
                    <strong><?php _e('Created:', 'wp-fork'); ?></strong><br>
                    <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($created_date))); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Render fork actions meta box
     */
    public function render_fork_actions_meta_box($post) {
        $original_post_id = get_post_meta($post->ID, '_fork_original_post_id', true);
        $fork_state = get_post_meta($post->ID, '_fork_state', true);
        
        wp_nonce_field('fork_actions_nonce', 'fork_actions_nonce');
        
        ?>
        <div class="fork-actions">
            <?php if ($fork_state !== 'merged' && $original_post_id): ?>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?action=compare_fork&fork_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce('compare_fork_' . $post->ID))); ?>" 
                       class="button button-secondary" 
                       style="width: 100%; text-align: center; margin-bottom: 10px;">
                        <span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
                        <?php _e('Compare with Original', 'wp-fork'); ?>
                    </a>
                </p>
                
                <p>
                    <button type="button" 
                            id="merge-fork-button" 
                            class="button button-primary" 
                            style="width: 100%; text-align: center;"
                            data-fork-id="<?php echo esc_attr($post->ID); ?>"
                            data-original-id="<?php echo esc_attr($original_post_id); ?>">
                        <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                        <?php _e('Merge into Original', 'wp-fork'); ?>
                    </button>
                </p>
                
                <p class="description">
                    <?php _e('Merging will update the original post with the content from this fork.', 'wp-fork'); ?>
                </p>
            <?php else: ?>
                <p style="color: #46b450;">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('This fork has been merged.', 'wp-fork'); ?>
                </p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#merge-fork-button').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('<?php echo esc_js(__('Are you sure you want to merge this fork into the original post? This action cannot be undone.', 'wp-fork')); ?>')) {
                    return;
                }
                
                var forkId = $(this).data('fork-id');
                var originalId = $(this).data('original-id');
                var button = $(this);
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Merging...', 'wp-fork')); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'merge_fork',
                        fork_id: forkId,
                        original_id: originalId,
                        nonce: '<?php echo wp_create_nonce('merge_fork'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert(response.data.message);
                            window.location.reload();
                        } else {
                            alert(response.data.message);
                            button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> <?php echo esc_js(__('Merge into Original', 'wp-fork')); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php echo esc_js(__('An error occurred. Please try again.', 'wp-fork')); ?>');
                        button.prop('disabled', false).html('<span class="dashicons dashicons-update" style="vertical-align: middle;"></span> <?php echo esc_js(__('Merge into Original', 'wp-fork')); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Save fork meta
     */
    public function save_fork_meta($post_id, $post) {
        // Verify nonce
        if (!isset($_POST['fork_actions_nonce']) || !wp_verify_nonce($_POST['fork_actions_nonce'], 'fork_actions_nonce')) {
            return;
        }
        
        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
}
