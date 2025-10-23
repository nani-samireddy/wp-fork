/**
 * WP Fork - Gutenberg Editor Integration
 * Modern implementation with async/await and proper React hooks
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginPostStatusInfo } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect, useRef } from '@wordpress/element';
import { Button, Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { BlockPreview } from '@wordpress/block-editor';
import { parse, rawHandler, serialize } from '@wordpress/blocks';

// Import styles
import './editor.scss';

/**
 * Fork Actions Component
 * Adds Compare and Merge buttons in the status section above the trash button
 */
const ForkActionsPanel = () => {
    const [isMerging, setIsMerging] = useState(false);
    const [showMergeModal, setShowMergeModal] = useState(false);
    const [showCompareModal, setShowCompareModal] = useState(false);
    const [showMergeResultModal, setShowMergeResultModal] = useState(false);
    const [mergeResult, setMergeResult] = useState(null);
    const [comparisonData, setComparisonData] = useState(null);
    const [isLoadingComparison, setIsLoadingComparison] = useState(false);

    // Get post data from the editor store
    const { postId, originalPostId, forkState, editedTitle, editedContent, editedExcerpt } = useSelect((select) => {
        const { getCurrentPostId, getEditedPostAttribute } = select('core/editor');
        const meta = getEditedPostAttribute('meta') || {};

        return {
            postId: getCurrentPostId(),
            originalPostId: meta._fork_original_post_id || null,
            forkState: meta._fork_state || 'draft',
            editedTitle: getEditedPostAttribute('title'),
            editedContent: getEditedPostAttribute('content'),
            editedExcerpt: getEditedPostAttribute('excerpt'),
        };
    });

    // Get savePost action from the editor store
    const { savePost } = useDispatch('core/editor');

    // Convert post content string into blocks for preview
    const toBlocks = (content) => {
        if (!content || typeof content !== 'string' || !content.trim()) {
            return [];
        }
        try {
            // If content includes block serialization markers, parse it
            const hasBlocks = content.includes('<!-- wp:');
            if (hasBlocks) {
                const blocks = parse(content);
                if (Array.isArray(blocks) && blocks.length) return blocks;
            }
            // Fallback: convert arbitrary HTML to blocks
            const blocksFromRaw = rawHandler({ HTML: content });
            return Array.isArray(blocksFromRaw) ? blocksFromRaw : [];
        } catch (e) {
            // Last resort: empty preview
            return [];
        }
    };

    // Convert blocks (or string) to HTML as a reliable fallback
    const toHTML = (content, fallbackHTML = '') => {
        // Prefer server-rendered HTML if provided (proper shortcode/theme context)
        if (fallbackHTML) return fallbackHTML;
        const blocks = toBlocks(content);
        if (blocks && blocks.length) {
            try {
                return serialize(blocks);
            } catch (e) {
                // no-op
            }
        }
        return '';
    };

    // PreviewPane renders BlockPreview sized to container width, with HTML fallback
    const PreviewPane = ({ contentRaw, fallbackHTML }) => {
        const hostRef = useRef(null);
        const [width, setWidth] = useState(0);

        useEffect(() => {
            const el = hostRef.current;
            if (!el) return;
            const ro = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    const w = entry.contentRect?.width || el.clientWidth || 0;
                    if (w && Math.abs(w - width) > 1) {
                        setWidth(w);
                    }
                }
            });
            ro.observe(el);
            // initial
            setWidth(el.clientWidth || 0);
            return () => ro.disconnect();
        }, []);

        const blocks = toBlocks(contentRaw);
        const showBlocks = blocks.length > 0;
        const vpWidth = width > 0 ? Math.floor(width) : undefined;

        return (
            <div className="wp-fork-preview-host" ref={hostRef}>
                {showBlocks ? (
                    <BlockPreview blocks={blocks} viewportWidth={vpWidth} />
                ) : (
                    <div
                        className="wp-fork-preview-fallback"
                        dangerouslySetInnerHTML={{ __html: toHTML(contentRaw, fallbackHTML) }}
                    />
                )}
            </div>
        );
    };

    /**
     * Handle Compare action
     * Fetches comparison data and shows modal
     */
    const handleCompare = async () => {
        const { ajaxUrl, compareNonce } = window.wpForkEditor || {};
        
        if (!ajaxUrl || !compareNonce) {
            console.error('WP Fork: Missing configuration data');
            return;
        }

        setShowCompareModal(true);
        setIsLoadingComparison(true);

        try {
            const response = await jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'get_fork_comparison',
                    fork_id: postId,
                    nonce: compareNonce
                },
            });

            if (response?.success) {
                setComparisonData(response.data);
            } else {
                const message = response?.data?.message || __('Failed to load comparison.', 'wp-fork');
                setComparisonData({ error: message });
            }
            setIsLoadingComparison(false);
        } catch (error) {
            console.error('WP Fork comparison error:', error);
            setComparisonData({ error: __('An error occurred while loading comparison.', 'wp-fork') });
            setIsLoadingComparison(false);
        }
    };

    /**
     * Handle Merge action - Show confirmation modal
     */
    const handleMergeClick = () => {
        if (!originalPostId) {
            alert(__('Original post not found.', 'wp-fork'));
            return;
        }
        setShowMergeModal(true);
    };

    /**
     * Perform the actual merge
     * Saves post, then merges fork into original via AJAX
     */
    const performMerge = async () => {
        const { ajaxUrl, mergeNonce } = window.wpForkEditor || {};

        if (!ajaxUrl || !mergeNonce) {
            console.error('WP Fork: Missing configuration data');
            return;
        }

        setIsMerging(true);
        setShowMergeModal(false);

        try {
            // Save the post first and wait for completion
            await savePost();

            // Perform the merge via AJAX
            const response = await wp.apiFetch({
                path: '/wp/v2/wp-fork/merge',
                method: 'POST',
                data: {
                    fork_id: postId,
                    original_id: originalPostId,
                    nonce: mergeNonce
                },
            }).catch(() => {
                // Fallback to jQuery AJAX if REST endpoint not available
                return jQuery.ajax({
                    url: ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'merge_fork',
                        fork_id: postId,
                        original_id: originalPostId,
                        nonce: mergeNonce
                    },
                });
            });

            setIsMerging(false);

            if (response.success || response.data?.success) {
                // Show success modal with result
                const data = response.data || response;
                setMergeResult(data);
                setShowMergeResultModal(true);
            } else {
                const message = response.data?.message || response.message || __('Merge failed.', 'wp-fork');
                setMergeResult({
                    success: false,
                    message: message,
                    error: true
                });
                setShowMergeResultModal(true);
            }
        } catch (error) {
            setIsMerging(false);
            console.error('WP Fork merge error:', error);
            setMergeResult({
                success: false,
                message: __('An error occurred. Please try again.', 'wp-fork'),
                error: true
            });
            setShowMergeResultModal(true);
        }
    };

    const isMerged = forkState === 'merged';

    // Don't render anything if there's no original post
    if (!originalPostId) {
        return null;
    }

    return (
        <>
            <PluginPostStatusInfo className="wp-fork-actions-status">
                <div className="wp-fork-actions-content">
                    {isMerged ? (
                        <div className="wp-fork-merged-notice">
                            <span className="dashicons dashicons-yes-alt"></span>
                            <p>{__('This fork has been merged.', 'wp-fork')}</p>
                        </div>
                    ) : (
                        <>  
                            <Button
                                __next40pxDefaultSize
                                variant="secondary"
                                onClick={handleCompare}
                                className="wp-fork-compare-button"
                                style={{ width: '100%', justifyContent: 'center', marginBottom: '8px' }}
                            >
                                {__('Compare with Original', 'wp-fork')}
                            </Button>

                            <Button
                                __next40pxDefaultSize
                                variant="primary"
                                onClick={handleMergeClick}
                                disabled={isMerging}
                                isBusy={isMerging}
                                className="wp-fork-merge-button"
                                style={{ width: '100%', justifyContent: 'center' }}
                            >
                                {isMerging ? __('Merging...', 'wp-fork') : __('Merge into Original', 'wp-fork')}
                            </Button>
                        </>
                    )}
                </div>
            </PluginPostStatusInfo>

            {/* Merge Confirmation Modal */}
            {showMergeModal && (
                <Modal
                    title={__('Confirm Merge', 'wp-fork')}
                    onRequestClose={() => setShowMergeModal(false)}
                    className="wp-fork-merge-modal"
                >
                    <p>
                        {__('Are you sure you want to merge this fork into the original post?', 'wp-fork')}
                    </p>
                    <p>
                        <strong>{__('This action cannot be undone.', 'wp-fork')}</strong>
                    </p>
                    <p className="wp-fork-modal-info">
                        {__('A backup revision of the original post will be created before merging.', 'wp-fork')}
                    </p>
                    <div className="wp-fork-modal-actions">
                        <Button
                            variant="secondary"
                            onClick={() => setShowMergeModal(false)}
                        >
                            {__('Cancel', 'wp-fork')}
                        </Button>
                        <Button
                            variant="primary"
                            onClick={performMerge}
                        >
                            {__('Merge Fork', 'wp-fork')}
                        </Button>
                    </div>
                </Modal>
            )}

            {/* Comparison Modal */}
            {showCompareModal && (
                <Modal
                    title={__('Compare Fork with Original', 'wp-fork')}
                    onRequestClose={() => setShowCompareModal(false)}
                    className="wp-fork-compare-modal"
                    size="large"
                >
                    {isLoadingComparison ? (
                        <div className="wp-fork-modal-loading">
                            <Spinner />
                            <p>{__('Loading comparison...', 'wp-fork')}</p>
                        </div>
                    ) : comparisonData?.error ? (
                        <div className="wp-fork-modal-error">
                            <p>{comparisonData.error}</p>
                        </div>
                    ) : comparisonData ? (
                        <div className="wp-fork-comparison-content">
                            {/* Title Comparison */}
                            <div className="wp-fork-comparison-section">
                                <h3>{__('Title', 'wp-fork')}</h3>
                                <div className="wp-fork-comparison-grid">
                                    <div className="wp-fork-comparison-column original">
                                        <h4>{__('Original', 'wp-fork')}</h4>
                                        <div className="wp-fork-field-value">
                                            {comparisonData.original?.title || ''}
                                        </div>
                                    </div>
                                    <div className="wp-fork-comparison-column fork">
                                        <h4>{__('Fork', 'wp-fork')}</h4>
                                        <div className="wp-fork-field-value">
                                            {editedTitle ?? comparisonData.fork?.title}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* Content Comparison */}
                            <div className="wp-fork-comparison-section">
                                <h3>{__('Content', 'wp-fork')}</h3>
                                <div className="wp-fork-comparison-grid">
                                    <div className="wp-fork-comparison-column original">
                                        <h4>{__('Original', 'wp-fork')}</h4>
                                        <div className="wp-fork-field-value content preview">
                                            <PreviewPane
                                                contentRaw={comparisonData.original?.content_raw || ''}
                                                fallbackHTML={comparisonData.original?.content || ''}
                                            />
                                        </div>
                                    </div>
                                    <div className="wp-fork-comparison-column fork">
                                        <h4>{__('Fork', 'wp-fork')}</h4>
                                        <div className="wp-fork-field-value content preview">
                                            <PreviewPane
                                                contentRaw={(editedContent && editedContent.length ? editedContent : comparisonData.fork?.content_raw) || ''}
                                                fallbackHTML={comparisonData.fork?.content || ''}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    ) : null}
                </Modal>
            )}

            {/* Merge Result Modal */}
            {showMergeResultModal && mergeResult && (
                <Modal
                    title={mergeResult.error ? __('Merge Failed', 'wp-fork') : __('Merge Complete', 'wp-fork')}
                    onRequestClose={() => {
                        setShowMergeResultModal(false);
                        if (mergeResult.success && mergeResult.original_url) {
                            window.location.href = mergeResult.original_url;
                        }
                    }}
                    className="wp-fork-merge-result-modal"
                >
                    {mergeResult.error ? (
                        <div className="wp-fork-merge-error">
                            <span className="dashicons dashicons-warning" style={{ fontSize: '48px', color: '#d63638' }}></span>
                            <p style={{ fontSize: '16px', marginTop: '16px' }}>{mergeResult.message}</p>
                        </div>
                    ) : (
                        <>
                            <div className="wp-fork-merge-success">
                                <span className="dashicons dashicons-yes-alt" style={{ fontSize: '48px', color: '#00a32a' }}></span>
                                <p style={{ fontSize: '16px', marginTop: '16px' }}>{mergeResult.message}</p>
                            </div>

                            {mergeResult.has_conflicts && mergeResult.conflicts && mergeResult.conflicts.length > 0 && (
                                <div className="wp-fork-conflicts-warning">
                                    <h3>{__('⚠️ Conflicts Detected', 'wp-fork')}</h3>
                                    <p>{__('The following fields were modified in both the fork and original post:', 'wp-fork')}</p>
                                    <ul>
                                        {mergeResult.conflicts.map((conflict, index) => (
                                            <li key={index}>
                                                <strong>{conflict.field}:</strong> {conflict.message}
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="wp-fork-conflict-resolution">
                                        {__('The fork version was used. Please review the merged content.', 'wp-fork')}
                                    </p>
                                </div>
                            )}

                            <div className="wp-fork-modal-actions">
                                <Button
                                    variant="primary"
                                    onClick={() => {
                                        if (mergeResult.original_url) {
                                            window.location.href = mergeResult.original_url;
                                        }
                                    }}
                                >
                                    {__('View Original Post', 'wp-fork')}
                                </Button>
                            </div>
                        </>
                    )}
                </Modal>
            )}
        </>
    );
};

// Register the plugin
registerPlugin('wp-fork-actions-panel', {
    render: ForkActionsPanel,
    icon: 'git',
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
