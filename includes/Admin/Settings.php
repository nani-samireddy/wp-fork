<?php
namespace WPFork\Admin;

/**
 * Settings Page Handler
 */
class Settings {
    
    private $option_name = 'wp_fork_settings';
    
    /**
     * Initialize hooks
     */
    public function init() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __('WP Fork Settings', 'wp-fork'),
            __('WP Fork', 'wp-fork'),
            'manage_options',
            'wp-fork-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wp_fork_settings_group',
            $this->option_name,
            array($this, 'sanitize_settings')
        );
        
        // Add settings section
        add_settings_section(
            'wp_fork_main_section',
            __('Fork Settings', 'wp-fork'),
            array($this, 'section_callback'),
            'wp-fork-settings'
        );
        
        // Add enabled post types field
        add_settings_field(
            'enabled_post_types',
            __('Enable Fork for Post Types', 'wp-fork'),
            array($this, 'enabled_post_types_callback'),
            'wp-fork-settings',
            'wp_fork_main_section'
        );
        
        // Add merge action field
        add_settings_field(
            'merge_action',
            __('After Merging Fork', 'wp-fork'),
            array($this, 'merge_action_callback'),
            'wp-fork-settings',
            'wp_fork_main_section'
        );
    }
    
    /**
     * Section callback
     */
    public function section_callback() {
        echo '<p>' . __('Configure which post types can be forked and what happens after merging.', 'wp-fork') . '</p>';
    }
    
    /**
     * Enabled post types field callback
     */
    public function enabled_post_types_callback() {
        $options = get_option($this->option_name);
        $enabled_post_types = isset($options['enabled_post_types']) ? $options['enabled_post_types'] : array();
        
        // Get all public post types except 'fork'
        $post_types = get_post_types(array('public' => true), 'objects');
        
        echo '<fieldset>';
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'fork' || $post_type->name === 'attachment') {
                continue;
            }
            
            $checked = in_array($post_type->name, $enabled_post_types) ? 'checked="checked"' : '';
            
            echo '<label style="display: block; margin-bottom: 8px;">';
            echo '<input type="checkbox" name="' . $this->option_name . '[enabled_post_types][]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' />';
            echo ' ' . esc_html($post_type->labels->name);
            echo '</label>';
        }
        echo '</fieldset>';
        echo '<p class="description">' . __('Select which post types should have the fork feature enabled.', 'wp-fork') . '</p>';
    }
    
    /**
     * Merge action field callback
     */
    public function merge_action_callback() {
        $options = get_option($this->option_name);
        $merge_action = isset($options['merge_action']) ? $options['merge_action'] : 'lock';
        
        echo '<fieldset>';
        
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="radio" name="' . $this->option_name . '[merge_action]" value="delete" ' . checked($merge_action, 'delete', false) . ' />';
        echo ' ' . __('Delete the fork after merging', 'wp-fork');
        echo '</label>';
        
        echo '<label style="display: block; margin-bottom: 8px;">';
        echo '<input type="radio" name="' . $this->option_name . '[merge_action]" value="lock" ' . checked($merge_action, 'lock', false) . ' />';
        echo ' ' . __('Lock the fork after merging (move to trash)', 'wp-fork');
        echo '</label>';
        
        echo '</fieldset>';
        echo '<p class="description">' . __('Choose what happens to the fork after it has been merged into the original post.', 'wp-fork') . '</p>';
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Sanitize enabled post types
        if (isset($input['enabled_post_types']) && is_array($input['enabled_post_types'])) {
            $sanitized['enabled_post_types'] = array_map('sanitize_text_field', $input['enabled_post_types']);
        } else {
            $sanitized['enabled_post_types'] = array();
        }
        
        // Sanitize merge action
        if (isset($input['merge_action']) && in_array($input['merge_action'], array('delete', 'lock'))) {
            $sanitized['merge_action'] = $input['merge_action'];
        } else {
            $sanitized['merge_action'] = 'lock';
        }
        
        return $sanitized;
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if settings were updated
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'wp_fork_messages',
                'wp_fork_message',
                __('Settings Saved', 'wp-fork'),
                'updated'
            );
        }
        
        settings_errors('wp_fork_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wp_fork_settings_group');
                do_settings_sections('wp-fork-settings');
                submit_button(__('Save Settings', 'wp-fork'));
                ?>
            </form>
        </div>
        <?php
    }
}
