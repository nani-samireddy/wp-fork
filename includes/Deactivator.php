<?php
namespace WPFork;

/**
 * Plugin Deactivator
 */
class Deactivator {
    
    /**
     * Deactivation hook
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
