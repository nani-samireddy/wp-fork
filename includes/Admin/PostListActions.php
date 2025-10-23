<?php
namespace WPFork\Admin;

/**
 * Post List Actions Handler
 */
class PostListActions {
    
    /**
     * Initialize hooks
     */
    public function init() {
        add_filter('post_row_actions', array($this, 'add_fork_action'), 10, 2);
        add_filter('page_row_actions', array($this, 'add_fork_action'), 10, 2);
        add_action('admin_action_fork_post', array($this, 'handle_fork_action'));
        
        // Add custom column to fork post list
        add_filter('manage_fork_posts_columns', array($this, 'add_fork_columns'));
        add_action('manage_fork_posts_custom_column', array($this, 'render_fork_columns'), 10, 2);
    }
    
    /**
     * Add fork action to post row actions
     */
    public function add_fork_action($actions, $post) {
        // Get enabled post types from settings
        $settings = get_option('wp_fork_settings');
        $enabled_post_types = isset($settings['enabled_post_types']) ? $settings['enabled_post_types'] : array();
        
        // Check if this post type is enabled for forking
        if (!in_array($post->post_type, $enabled_post_types)) {
            return $actions;
        }
        
        // Check if user has permission
        if (!current_user_can('edit_posts')) {
            return $actions;
        }
        
        // Create fork URL
        $fork_url = wp_nonce_url(
            admin_url('admin.php?action=fork_post&post=' . $post->ID),
            'fork_post_' . $post->ID
        );
        
        // Add fork action
        $actions['fork'] = sprintf(
            '<a href="%s" title="%s">%s</a>',
            esc_url($fork_url),
            esc_attr__('Create a fork of this post', 'wp-fork'),
            esc_html__('Fork', 'wp-fork')
        );
        
        return $actions;
    }
    
    /**
     * Handle fork action
     */
    public function handle_fork_action() {
        // Check if post ID is provided
        if (!isset($_GET['post'])) {
            wp_die(__('No post to fork has been specified.', 'wp-fork'));
        }
        
        $post_id = absint($_GET['post']);
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'fork_post_' . $post_id)) {
            wp_die(__('Security check failed.', 'wp-fork'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to fork posts.', 'wp-fork'));
        }
        
        // Get the original post
        $original_post = get_post($post_id);
        
        if (!$original_post) {
            wp_die(__('Post not found.', 'wp-fork'));
        }
        
        // Create the fork using ForkManager
        $fork_manager = new \WPFork\Core\ForkManager();
        $fork_id = $fork_manager->create_fork($post_id);
        
        if (is_wp_error($fork_id)) {
            wp_die($fork_id->get_error_message());
        }
        
        // Redirect to edit the fork
        wp_redirect(admin_url('post.php?action=edit&post=' . $fork_id));
        exit;
    }
    
    /**
     * Add custom columns to fork post list
     */
    public function add_fork_columns($columns) {
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add custom columns after title
            if ($key === 'title') {
                $new_columns['original_post'] = __('Original Post', 'wp-fork');
                $new_columns['fork_state'] = __('State', 'wp-fork');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom columns for fork post list
     */
    public function render_fork_columns($column, $post_id) {
        switch ($column) {
            case 'original_post':
                $original_post_id = get_post_meta($post_id, '_fork_original_post_id', true);
                $original_post_type = get_post_meta($post_id, '_fork_original_post_type', true);
                
                if ($original_post_id) {
                    $original_post = get_post($original_post_id);
                    if ($original_post) {
                        $edit_link = get_edit_post_link($original_post_id);
                        echo '<a href="' . esc_url($edit_link) . '">' . esc_html($original_post->post_title) . '</a>';
                        echo '<br><small>' . esc_html($original_post_type) . ' #' . $original_post_id . '</small>';
                    } else {
                        echo '<span style="color: #999;">' . __('Original post deleted', 'wp-fork') . '</span>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'fork_state':
                $state = get_post_meta($post_id, '_fork_state', true);
                $state = $state ? $state : 'draft';
                
                if ($state === 'draft') {
                    echo '<span class="dashicons dashicons-edit" style="color: #f0b849;"></span> ';
                    echo '<strong>' . __('Draft', 'wp-fork') . '</strong>';
                } else {
                    echo '<span class="dashicons dashicons-yes" style="color: #46b450;"></span> ';
                    echo '<strong>' . __('Merged', 'wp-fork') . '</strong>';
                }
                break;
        }
    }
}
