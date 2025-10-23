/**
 * WP Fork - Gutenberg Editor Integration
 * Modern implementation with async/await and proper React hooks
 */
(function (wp) {
    'use strict';

    // Guard: Ensure wpForkEditor global exists
    if (typeof wpForkEditor === 'undefined') {
        console.error('WP Fork: wpForkEditor global not found. Script may have loaded before localization.');
        return;
    }

    const { registerPlugin } = wp.plugins;
    const { PluginMoreMenuItem } = wp.editPost;
    const { useSelect, useDispatch } = wp.data;
    const { useState } = wp.element;
    const { __ } = wp.i18n;

    /**
     * Fork Editor Menu Items Component
     * Adds Compare and Merge actions to the editor's three-dot menu
     */
    const ForkEditorMenuItems = () => {
        const [isMerging, setIsMerging] = useState(false);

        // Get post data from the editor store
        const { postId, originalPostId, forkState } = useSelect((select) => {
            const { getCurrentPostId, getEditedPostAttribute } = select('core/editor');
            const meta = getEditedPostAttribute('meta') || {};

            return {
                postId: getCurrentPostId(),
                originalPostId: meta._fork_original_post_id || null,
                forkState: meta._fork_state || 'draft',
            };
        }, []);

        // Get savePost action from the editor store
        const { savePost } = useDispatch('core/editor');

        /**
         * Handle Compare action
         * Opens comparison view in a new tab
         */
        const handleCompare = () => {
            const compareUrl = `${wpForkEditor.adminUrl}admin.php?action=compare_fork&fork_id=${postId}&_wpnonce=${wpForkEditor.compareNonce}`;
            window.open(compareUrl, '_blank');
        };

        /**
         * Handle Merge action
         * Saves post, then merges fork into original via AJAX
         */
        const handleMerge = async () => {
            if (!originalPostId) {
                alert(__('Original post not found.', 'wp-fork'));
                return;
            }

            if (!confirm(__('Are you sure you want to merge this fork into the original post? This action cannot be undone.', 'wp-fork'))) {
                return;
            }

            setIsMerging(true);

            try {
                // Save the post first and wait for completion
                await savePost();

                // Perform the merge via AJAX
                const response = await jQuery.ajax({
                    url: wpForkEditor.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'merge_fork',
                        fork_id: postId,
                        original_id: originalPostId,
                        nonce: wpForkEditor.mergeNonce
                    },
                });

                setIsMerging(false);

                if (response.success) {
                    alert(response.data.message);
                    window.location.reload();
                } else {
                    alert(response.data.message || __('Merge failed.', 'wp-fork'));
                }
            } catch (error) {
                setIsMerging(false);
                console.error('WP Fork merge error:', error);
                alert(__('An error occurred. Please try again.', 'wp-fork'));
            }
        };

        const isMerged = forkState === 'merged';

        // Don't render anything if there's no original post
        if (!originalPostId) {
            return null;
        }

        return (
            <>
                <PluginMoreMenuItem
                    icon="visibility"
                    onClick={handleCompare}
                >
                    {__('Compare with Original', 'wp-fork')}
                </PluginMoreMenuItem>

                {!isMerged && (
                    <PluginMoreMenuItem
                        icon="update"
                        onClick={!isMerging ? handleMerge : undefined}
                        disabled={isMerging}
                    >
                        {isMerging ? __('Merging...', 'wp-fork') : __('Merge into Original', 'wp-fork')}
                    </PluginMoreMenuItem>
                )}
            </>
        );
    };

    // Register the plugin
    registerPlugin('wp-fork-editor-menu-items', {
        render: ForkEditorMenuItems,
    });

    /**
     * Hide the Publish button for fork posts
     * Uses wp.data.subscribe() but unsubscribes after first successful hide
     */
    wp.domReady(() => {
        const unsubscribe = wp.data.subscribe(() => {
            const publishButton = document.querySelector('.editor-post-publish-button');
            const saveButton = document.querySelector('.editor-post-save-draft');

            if (publishButton) {
                publishButton.style.display = 'none';
                
                // Keep the save draft button visible
                if (saveButton) {
                    saveButton.style.display = 'inline-flex';
                }
                
                // ✅ Stop observing once we've hidden the button
                unsubscribe();
            }
        });
    });

})(window.wp);
