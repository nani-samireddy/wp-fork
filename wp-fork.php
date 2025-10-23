<?php
/**
 * Plugin Name: WP Fork
 * Plugin URI: https://example.com/wp-fork
 * Description: Fork posts like Git branches - create copies, make changes, compare diffs, and merge back.
 * Version: 1.0.0
 * Author: Nani Samireddy
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-fork
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WP_FORK_VERSION', '1.0.0');
define('WP_FORK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WP_FORK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WP_FORK_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'WPFork\\';
    $base_dir = WP_FORK_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
function wp_fork_init() {
    $plugin = WPFork\Plugin::get_instance();
    $plugin->init();
}
add_action('plugins_loaded', 'wp_fork_init');

// Activation hook
register_activation_hook(__FILE__, function() {
    WPFork\Activator::activate();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    WPFork\Deactivator::deactivate();
});
