<?php
namespace WPFork;

/**
 * Plugin Activator
 */
class Activator {
    
    /**
     * Activation hook
     */
    public static function activate() {
        // Set default options
        $default_options = array(
            'enabled_post_types' => array('post', 'page'),
            'merge_action' => 'lock' // 'lock' or 'delete'
        );
        
        if (!get_option('wp_fork_settings')) {
            add_option('wp_fork_settings', $default_options);
        }
        
        // Register the CPT
        $cpt = new PostTypes\Fork();
        $cpt->register();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
