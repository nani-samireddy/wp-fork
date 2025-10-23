<?php
namespace WPFork;

/**
 * Main Plugin Class
 */
class Plugin {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Initialize CPT
        $cpt = new PostTypes\Fork();
        $cpt->init();
        
        // Initialize Admin Settings
        $settings = new Admin\Settings();
        $settings->init();
        
        // Initialize Fork Manager
        $fork_manager = new Core\ForkManager();
        $fork_manager->init();
        
        // Initialize Post List Actions
        $post_actions = new Admin\PostListActions();
        $post_actions->init();
        
        // Initialize Merge Handler
        $merge_handler = new Core\MergeHandler();
        $merge_handler->init();
        
        // Initialize Comparison
        $comparison = new Core\Comparison();
        $comparison->init();
    }
}
