<?php
namespace WPFork\Core;

/**
 * Comparison Handler - Compares fork with original post
 */
class Comparison {
    
    /**
     * Initialize hooks
     */
    public function init() {
        add_action('admin_action_compare_fork', array($this, 'handle_compare_action'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_comparison_styles'));
        add_action('wp_ajax_get_fork_comparison', array($this, 'ajax_get_comparison'));
    }
    
    /**
     * Enqueue comparison styles
     */
    public function enqueue_comparison_styles($hook) {
        if ($hook === 'admin_page_wp-fork-compare') {
            wp_enqueue_style(
                'wp-fork-comparison',
                WP_FORK_PLUGIN_URL . 'assets/css/comparison.css',
                array(),
                WP_FORK_VERSION
            );
        }
    }
    
    /**
     * Handle compare action
     */
    public function handle_compare_action() {
        // Check if fork ID is provided
        if (!isset($_GET['fork_id'])) {
            wp_die(__('No fork to compare has been specified.', 'wp-fork'));
        }
        
        $fork_id = absint($_GET['fork_id']);
        
        // Verify nonce
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'compare_fork_' . $fork_id)) {
            wp_die(__('Security check failed.', 'wp-fork'));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('You do not have permission to compare forks.', 'wp-fork'));
        }
        
        // Get the fork
        $fork = get_post($fork_id);
        
        if (!$fork || $fork->post_type !== 'fork') {
            wp_die(__('Fork not found.', 'wp-fork'));
        }
        
        // Get original post ID
        $original_id = get_post_meta($fork_id, '_fork_original_post_id', true);
        
        if (!$original_id) {
            wp_die(__('Original post not found.', 'wp-fork'));
        }
        
        // Get original post
        $original = get_post($original_id);
        
        if (!$original) {
            wp_die(__('Original post has been deleted.', 'wp-fork'));
        }
        
        // Render comparison page
        $this->render_comparison_page($fork, $original);
    }
    
    /**
     * Render comparison page
     *
     * @param WP_Post $fork Fork post object
     * @param WP_Post $original Original post object
     */
    private function render_comparison_page($fork, $original) {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php _e('Compare Fork with Original', 'wp-fork'); ?></title>
            <?php
            wp_enqueue_style('common');
            wp_enqueue_style('forms');
            wp_enqueue_style('admin-menu');
            wp_enqueue_style('dashboard');
            wp_enqueue_style('wp-admin');
            do_action('admin_print_styles');
            do_action('admin_head');
            ?>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    margin: 0;
                    padding: 20px;
                    background: #f0f0f1;
                }
                .comparison-container {
                    max-width: 1400px;
                    margin: 0 auto;
                    background: #fff;
                    border: 1px solid #c3c4c7;
                    border-radius: 4px;
                }
                .comparison-header {
                    padding: 20px;
                    border-bottom: 1px solid #c3c4c7;
                    background: #fff;
                }
                .comparison-header h1 {
                    margin: 0 0 10px 0;
                    font-size: 23px;
                    font-weight: 400;
                    line-height: 1.3;
                }
                .comparison-header .actions {
                    margin-top: 15px;
                }
                .comparison-header .button {
                    margin-right: 10px;
                }
                .comparison-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 0;
                }
                .comparison-column {
                    padding: 20px;
                    border-right: 1px solid #c3c4c7;
                }
                .comparison-column:last-child {
                    border-right: none;
                }
                .comparison-column h2 {
                    margin: 0 0 15px 0;
                    font-size: 18px;
                    font-weight: 600;
                    padding-bottom: 10px;
                    border-bottom: 2px solid #2271b1;
                }
                .comparison-column.original h2 {
                    border-bottom-color: #d63638;
                }
                .comparison-column.fork h2 {
                    border-bottom-color: #00a32a;
                }
                .field-comparison {
                    margin-bottom: 25px;
                }
                .field-label {
                    font-weight: 600;
                    margin-bottom: 8px;
                    color: #1d2327;
                    font-size: 14px;
                }
                .field-value {
                    padding: 12px;
                    background: #f6f7f7;
                    border: 1px solid #dcdcde;
                    border-radius: 4px;
                    min-height: 50px;
                    word-wrap: break-word;
                }
                .field-value.changed {
                    background: #fff8e5;
                    border-color: #f0b849;
                }
                .field-value.content {
                    max-height: 400px;
                    overflow-y: auto;
                    font-family: Consolas, Monaco, monospace;
                    font-size: 13px;
                    line-height: 1.6;
                }
                .diff-indicator {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 3px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    margin-left: 8px;
                }
                .diff-indicator.changed {
                    background: #f0b849;
                    color: #fff;
                }
                .diff-indicator.same {
                    background: #00a32a;
                    color: #fff;
                }
                .meta-info {
                    font-size: 13px;
                    color: #646970;
                    margin-top: 5px;
                }
                @media (max-width: 768px) {
                    .comparison-grid {
                        grid-template-columns: 1fr;
                    }
                    .comparison-column {
                        border-right: none;
                        border-bottom: 1px solid #c3c4c7;
                    }
                    .comparison-column:last-child {
                        border-bottom: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="comparison-container">
                <div class="comparison-header">
                    <h1>
                        <span class="dashicons dashicons-visibility" style="vertical-align: middle;"></span>
                        <?php _e('Compare Fork with Original', 'wp-fork'); ?>
                    </h1>
                    <div class="actions">
                        <a href="<?php echo esc_url(get_edit_post_link($fork->ID)); ?>" class="button button-secondary">
                            <?php _e('Edit Fork', 'wp-fork'); ?>
                        </a>
                        <a href="<?php echo esc_url(get_edit_post_link($original->ID)); ?>" class="button button-secondary">
                            <?php _e('Edit Original', 'wp-fork'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=fork')); ?>" class="button">
                            <?php _e('Back to Forks', 'wp-fork'); ?>
                        </a>
                    </div>
                </div>
                
                <div class="comparison-grid">
                    <!-- Original Post Column -->
                    <div class="comparison-column original">
                        <h2>
                            <span class="dashicons dashicons-admin-post" style="vertical-align: middle;"></span>
                            <?php _e('Original Post', 'wp-fork'); ?>
                        </h2>
                        
                        <?php $this->render_field_comparison('title', __('Title', 'wp-fork'), $original->post_title, $fork->post_title, 'original'); ?>
                        <?php $this->render_field_comparison('content', __('Content', 'wp-fork'), $original->post_content, $fork->post_content, 'original'); ?>
                        <?php $this->render_field_comparison('excerpt', __('Excerpt', 'wp-fork'), $original->post_excerpt, $fork->post_excerpt, 'original'); ?>
                        
                        <div class="field-comparison">
                            <div class="field-label"><?php _e('Last Modified', 'wp-fork'); ?></div>
                            <div class="field-value">
                                <?php echo esc_html(get_the_modified_date('', $original) . ' ' . get_the_modified_time('', $original)); ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fork Column -->
                    <div class="comparison-column fork">
                        <h2>
                            <span class="dashicons dashicons-git" style="vertical-align: middle;"></span>
                            <?php _e('Fork', 'wp-fork'); ?>
                        </h2>
                        
                        <?php $this->render_field_comparison('title', __('Title', 'wp-fork'), $original->post_title, $fork->post_title, 'fork'); ?>
                        <?php $this->render_field_comparison('content', __('Content', 'wp-fork'), $original->post_content, $fork->post_content, 'fork'); ?>
                        <?php $this->render_field_comparison('excerpt', __('Excerpt', 'wp-fork'), $original->post_excerpt, $fork->post_excerpt, 'fork'); ?>
                        
                        <div class="field-comparison">
                            <div class="field-label"><?php _e('Last Modified', 'wp-fork'); ?></div>
                            <div class="field-value">
                                <?php echo esc_html(get_the_modified_date('', $fork) . ' ' . get_the_modified_time('', $fork)); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * Render field comparison
     *
     * @param string $field_id Field ID
     * @param string $label Field label
     * @param string $original_value Original value
     * @param string $fork_value Fork value
     * @param string $column Which column (original or fork)
     */
    private function render_field_comparison($field_id, $label, $original_value, $fork_value, $column) {
        $is_changed = $original_value !== $fork_value;
        $value = $column === 'original' ? $original_value : $fork_value;
        
        ?>
        <div class="field-comparison">
            <div class="field-label">
                <?php echo esc_html($label); ?>
                <?php if ($is_changed): ?>
                    <span class="diff-indicator changed"><?php _e('Changed', 'wp-fork'); ?></span>
                <?php else: ?>
                    <span class="diff-indicator same"><?php _e('Same', 'wp-fork'); ?></span>
                <?php endif; ?>
            </div>
            <div class="field-value <?php echo $is_changed ? 'changed' : ''; ?> <?php echo $field_id === 'content' ? 'content' : ''; ?>">
                <?php 
                if ($field_id === 'content') {
                    echo nl2br(esc_html($value));
                } else {
                    echo esc_html($value);
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Calculate text difference (simple implementation)
     *
     * @param string $old Old text
     * @param string $new New text
     * @return array Diff array
     */
    private function calculate_diff($old, $new) {
        // Simple line-by-line comparison
        $old_lines = explode("\n", $old);
        $new_lines = explode("\n", $new);
        
        $diff = array();
        $max_lines = max(count($old_lines), count($new_lines));
        
        for ($i = 0; $i < $max_lines; $i++) {
            $old_line = isset($old_lines[$i]) ? $old_lines[$i] : '';
            $new_line = isset($new_lines[$i]) ? $new_lines[$i] : '';
            
            if ($old_line === $new_line) {
                $diff[] = array('type' => 'same', 'line' => $old_line);
            } else {
                if ($old_line !== '') {
                    $diff[] = array('type' => 'removed', 'line' => $old_line);
                }
                if ($new_line !== '') {
                    $diff[] = array('type' => 'added', 'line' => $new_line);
                }
            }
        }
        
        return $diff;
    }
    
    /**
     * AJAX handler for getting comparison data
     */
    public function ajax_get_comparison() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'compare_fork_' . absint($_POST['fork_id']))) {
            wp_send_json_error(array('message' => __('Security check failed.', 'wp-fork')));
        }
        
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('You do not have permission to compare forks.', 'wp-fork')));
        }
        
        $fork_id = absint($_POST['fork_id']);
        
        // Get the fork
        $fork = get_post($fork_id);
        
        if (!$fork || $fork->post_type !== 'fork') {
            wp_send_json_error(array('message' => __('Fork not found.', 'wp-fork')));
        }
        
        // Get original post ID
        $original_id = get_post_meta($fork_id, '_fork_original_post_id', true);
        
        if (!$original_id) {
            wp_send_json_error(array('message' => __('Original post not found.', 'wp-fork')));
        }
        
        // Get original post
        $original = get_post($original_id);
        
        if (!$original) {
            wp_send_json_error(array('message' => __('Original post has been deleted.', 'wp-fork')));
        }
        
        // Prepare comparison data
        $comparison_data = array(
            'original' => array(
                'id' => $original->ID,
                'title' => $original->post_title,
                'content' => apply_filters('the_content', $original->post_content),
                'content_raw' => $original->post_content, // Raw content for block parsing
                'excerpt' => $original->post_excerpt,
                'modified' => get_the_modified_date('', $original) . ' ' . get_the_modified_time('', $original),
            ),
            'fork' => array(
                'id' => $fork->ID,
                'title' => $fork->post_title,
                'content' => apply_filters('the_content', $fork->post_content),
                'content_raw' => $fork->post_content, // Raw content for block parsing
                'excerpt' => $fork->post_excerpt,
                'modified' => get_the_modified_date('', $fork) . ' ' . get_the_modified_time('', $fork),
            ),
            'changes' => array(
                'title' => $original->post_title !== $fork->post_title,
                'content' => $original->post_content !== $fork->post_content,
                'excerpt' => $original->post_excerpt !== $fork->post_excerpt,
            ),
        );
        
        wp_send_json_success($comparison_data);
    }
}
