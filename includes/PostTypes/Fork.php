<?php
namespace WPFork\PostTypes;

/**
 * Fork Custom Post Type
 */
class Fork {
    
    /**
     * Initialize hooks
     */
    public function init() {
        add_action('init', array($this, 'register'));
        add_filter('post_row_actions', array($this, 'remove_quick_edit'), 10, 2);
        add_action('admin_head-post.php', array($this, 'hide_publishing_actions'));
        add_action('admin_head-post-new.php', array($this, 'hide_publishing_actions'));
        add_filter('wp_insert_post_data', array($this, 'force_draft_status'), 10, 2);
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_editor_assets'));
    }
    
    /**
     * Register Fork CPT
     */
    public function register() {
        $labels = array(
            'name'                  => __('Forks', 'wp-fork'),
            'singular_name'         => __('Fork', 'wp-fork'),
            'menu_name'             => __('Forks', 'wp-fork'),
            'name_admin_bar'        => __('Fork', 'wp-fork'),
            'add_new'               => __('Add New', 'wp-fork'),
            'add_new_item'          => __('Add New Fork', 'wp-fork'),
            'new_item'              => __('New Fork', 'wp-fork'),
            'edit_item'             => __('Edit Fork', 'wp-fork'),
            'view_item'             => __('View Fork', 'wp-fork'),
            'all_items'             => __('All Forks', 'wp-fork'),
            'search_items'          => __('Search Forks', 'wp-fork'),
            'not_found'             => __('No forks found.', 'wp-fork'),
            'not_found_in_trash'    => __('No forks found in Trash.', 'wp-fork')
        );
        
        $args = array(
            'labels'                => $labels,
            'public'                => false,
            'publicly_queryable'    => false,
            'show_ui'               => true,
            'show_in_menu'          => true,
            'query_var'             => true,
            'rewrite'               => array('slug' => 'fork'),
            'capability_type'       => 'post',
            'has_archive'           => false,
            'hierarchical'          => false,
            'menu_position'         => 25,
            'menu_icon'             => 'dashicons-git',
            'supports'              => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'revisions'),
            'show_in_rest'          => true, // Enable Gutenberg editor
            'rest_base'             => 'forks',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            // Disallow creating forks via the UI; only via our action
            'map_meta_cap'          => true,
            'capabilities'          => array(
                'create_posts' => 'do_not_allow', // removes "Add New" UI and route
            ),
        );
        
        register_post_type('fork', $args);
        
        // Register custom post meta for storing original post ID
        register_post_meta('fork', '_fork_original_post_id', array(
            'type'         => 'integer',
            'single'       => true,
            'show_in_rest' => true,
        ));
        
        // Register custom post meta for storing original post type
        register_post_meta('fork', '_fork_original_post_type', array(
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
        ));
        
        // Register custom post meta for fork state
        register_post_meta('fork', '_fork_state', array(
            'type'         => 'string',
            'single'       => true,
            'show_in_rest' => true,
            'default'      => 'draft',
        ));
    }
    
    /**
     * Remove Quick Edit from fork post list
     */
    public function remove_quick_edit($actions, $post) {
        if ($post->post_type === 'fork') {
            unset($actions['inline hide-if-no-js']);
        }
        return $actions;
    }
    
    /**
     * Hide publishing actions for fork CPT
     */
    public function hide_publishing_actions() {
        global $post;
        
        if ($post && $post->post_type === 'fork') {
            echo '<style>
                #publishing-action,
                #save-action,
                .misc-pub-post-status,
                .misc-pub-visibility {
                    display: none;
                }
            </style>';
        }
    }
    
    /**
     * Force draft status for forks (prevent publishing)
     */
    public function force_draft_status($data, $postarr) {
        if ($data['post_type'] === 'fork') {
            // Only allow draft status, never publish
            if ($data['post_status'] !== 'trash') {
                $data['post_status'] = 'draft';
            }
        }
        return $data;
    }
    
    /**
     * Enqueue block editor assets for fork post type
     */
    public function enqueue_editor_assets() {
        global $post;
        
        // Only load for fork post type
        if (!$post || $post->post_type !== 'fork') {
            return;
        }
        
        // Check if built files exist, otherwise use source
        $script_path = WP_FORK_PLUGIN_DIR . 'build/editor/index.js';
        $style_path = WP_FORK_PLUGIN_DIR . 'build/editor/index.css';
        $asset_file = WP_FORK_PLUGIN_DIR . 'build/editor/index.asset.php';
        
        // Use built files if they exist
        if (file_exists($script_path) && file_exists($asset_file)) {
            $asset_data = include $asset_file;
            
            // Enqueue the built editor script
            wp_enqueue_script(
                'wp-fork-editor',
                WP_FORK_PLUGIN_URL . 'build/editor/index.js',
                $asset_data['dependencies'],
                $asset_data['version'],
                true
            );
            
            // Enqueue the built editor styles
            if (file_exists($style_path)) {
                wp_enqueue_style(
                    'wp-fork-editor',
                    WP_FORK_PLUGIN_URL . 'build/editor/index.css',
                    array('wp-edit-blocks'),
                    $asset_data['version']
                );
            }
        } else {
            // In production we require built assets; if missing, bail.
            return;
        }
        
        // Get fork metadata
        $original_post_id = get_post_meta($post->ID, '_fork_original_post_id', true);
        
        // Localize script with necessary data
        if (wp_script_is('wp-fork-editor', 'enqueued')) {
            wp_localize_script('wp-fork-editor', 'wpForkEditor', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'postId' => $post->ID,
                'originalPostId' => $original_post_id,
                'mergeNonce' => wp_create_nonce('merge_fork'),
                'compareNonce' => wp_create_nonce('compare_fork_' . $post->ID),
            ));
        }
    }
}
