import axios from 'axios';
import Swal from 'sweetalert2';
import { getStoredUser } from './auth';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import {
    applyAdminFilterTabCounts,
    bindAdminFilterTabs,
    renderAdminFilterTabs,
    setAdminFilterTab,
} from './shared/filter-tabs';
import {
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
    failSwalValidation,
    formatApiValidationMessage,
} from './shared/swal-forms';
import { getRichTextValue, isRichTextEmpty } from './rich-text-editor';
import { renderRatingBadge, renderStars } from './shared/ratings';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression, buildAdminActionButton } from './shared/table-actions';
import { showSuccessToast, showErrorToast } from './shared/toast';
import {
    getRevisionFieldDiffs,
    humanizeWorkflowAttribute,
} from './shared/workflow-revision-diff';
import { bindPostCommentDeletes, confirmDeletePostComment } from './post-comments';

let postsTable;
let currentUserRole = null;
const postsById = new Map();
let allPostsCache = [];
let categoryOptionsCache = null;
let postsTableMode = null;
let postsViewportBound = false;
let postsActionsBound = false;
let postsFiltersBound = false;
let activeStatusFilter = '';

const STATUS_FILTER_TABS_ALL = [
    { value: '', label: 'All', icon: 'fas fa-layer-group', hint: 'Every status' },
    { value: 'draft', label: 'Draft', icon: 'fas fa-pen', hint: 'Editor drafts & rejected' },
    { value: 'pending_review', label: 'Pending', icon: 'fas fa-clock', hint: 'Awaiting publisher' },
    { value: 'scheduled', label: 'Scheduled', icon: 'fas fa-calendar-check', hint: 'Approved, not live' },
    { value: 'published', label: 'Published', icon: 'fas fa-circle-check', hint: 'Live in the app' },
    { value: 'archived', label: 'Archived', icon: 'fas fa-box-archive', hint: 'Removed from feed' },
];

function postsStatusFilterTabs(role = currentUserRole) {
    if (role === 'publisher') {
        return [
            { value: 'pending_review', label: 'Pending', icon: 'fas fa-clock', hint: 'Awaiting your review' },
        ];
    }

    return STATUS_FILTER_TABS_ALL;
}

export function initPostsModule() {
    const tableEl = document.getElementById('posts-table');
    if (!tableEl) return;

    currentUserRole = getStoredUser()?.role ?? null;
    if (currentUserRole === 'publisher') {
        activeStatusFilter = 'pending_review';
    }
    syncPostsPageActions();
    bindPostsStatusFilters();
    bindPostTableActions(tableEl);

    if (postsTable && postsTableMode === getPostsTableMode()) {
        loadPosts();
        openPostFromQueryParam();

        return;
    }

    initializePostsTable(tableEl).then(() => {
        loadPosts();
        openPostFromQueryParam();
    });

    bindPostsViewportListener(tableEl);
}

function bindPostsStatusFilters() {
    const root = document.getElementById('posts-status-tabs');
    if (!root || postsFiltersBound) {
        return;
    }

    postsFiltersBound = true;
    renderAdminFilterTabs(root, postsStatusFilterTabs().map((tab) => ({ ...tab, count: 0 })));
    setAdminFilterTab(root, activeStatusFilter);
    bindAdminFilterTabs(root, (value) => {
        activeStatusFilter = value || '';
        renderPostsTable();
    });
}

export function loadPosts() {
    if (!postsTable) return;

    axios.get('/admin/posts', { params: { per_page: 200 } })
        .then((response) => {
            try {
                const payload = response?.data ?? {};
                const collection = Array.isArray(payload.data?.data)
                    ? payload.data.data
                    : Array.isArray(payload.data)
                        ? payload.data
                        : [];

                allPostsCache = collection;
                postsById.clear();
                collection.forEach((post) => {
                    postsById.set(String(post.id), post);
                });

                updatePostsStatusCounts();
                renderPostsTable();
                openPostFromQueryParam();
            } catch (renderError) {
                // eslint-disable-next-line no-console
                console.error('Error rendering posts table', renderError);

                Swal.fire({
                    icon: 'error',
                    title: 'Posts',
                    text: 'Posts loaded but could not be rendered in the table. Check the console for details.',
                });
            }
        })
        .catch((error) => {
            // eslint-disable-next-line no-console
            console.error('Failed to load posts', error);

            const response = error?.response;
            const status = response?.status;
            let message = response?.data?.message ?? 'Failed to load posts.';

            if (status === 403) {
                message = response?.data?.message ?? 'You do not have permission to view posts.';
            }

            Swal.fire({
                icon: 'error',
                title: 'Posts',
                text: message,
            });
        });
}

function isPublishedUpdateDraft(post) {
    return String(post?.status) === 'published' && String(post?.revision_status || '') === 'draft';
}

function isPublishedUpdatePending(post) {
    return String(post?.status) === 'published' && String(post?.revision_status || '') === 'pending_review';
}

function hasPendingRevisionPayload(post) {
    return Boolean(post?.pending_revision) && typeof post.pending_revision === 'object';
}

function postForEditorForm(post) {
    if (!hasPendingRevisionPayload(post)) {
        return post;
    }

    return {
        ...post,
        ...post.pending_revision,
        status: post.status,
        revision_status: post.revision_status,
        review_notes: post.review_notes,
        pending_revision: post.pending_revision,
    };
}

function postMatchesStatusFilter(post, filter) {
    if (!filter) {
        return true;
    }

    if (filter === 'pending_review') {
        return String(post.status) === 'pending_review' || isPublishedUpdatePending(post);
    }

    if (filter === 'draft') {
        return String(post.status) === 'draft' || isPublishedUpdateDraft(post);
    }

    return String(post.status) === filter;
}

function updatePostsStatusCounts() {
    const counts = { '': allPostsCache.length };
    allPostsCache.forEach((post) => {
        if (isPublishedUpdatePending(post)) {
            counts.pending_review = (counts.pending_review ?? 0) + 1;
        } else if (isPublishedUpdateDraft(post)) {
            counts.draft = (counts.draft ?? 0) + 1;
        }

        const key = String(post.status || '');
        counts[key] = (counts[key] ?? 0) + 1;
    });
    applyAdminFilterTabCounts(document.getElementById('posts-status-tabs'), counts);
}

function renderPostsTable() {
    if (!postsTable) {
        return;
    }

    const rows = activeStatusFilter
        ? allPostsCache.filter((post) => postMatchesStatusFilter(post, activeStatusFilter))
        : allPostsCache;

    postsTable.clear();
    rows.forEach((post) => {
        postsTable.row.add(buildPostRowData(post));
    });
    postsTable.draw();
}

export async function promptCreateDraft() {
    const categoryOptions = await loadCategoryOptions();

    if (!categoryOptions) {
        return;
    }

    const result = await openPostEditorSwal({
        title: currentUserRole === 'super_admin' ? 'Create Post' : 'Create Draft Post',
        post: null,
        categoryOptions,
        confirmButtonText: currentUserRole === 'super_admin' ? 'Create & Publish' : 'Create Draft',
        showStatus: false,
    });

    if (!result.isConfirmed || !result.value) {
        return;
    }

    showSuccessToast(
        result.value.message,
        currentUserRole === 'super_admin' ? 'Post published' : 'Draft created',
    );
    loadPosts();
}

export async function viewPost(postId, { engagementTab = null } = {}) {
    try {
        const { data } = await axios.get(`/admin/posts/${postId}`);
        const post = data?.data;

        if (!post) {
            showErrorToast('Unable to load the selected post.', 'Post');
            return;
        }

        postsById.set(String(post.id), post);
        openPostViewModal(post, { engagementTab });
    } catch (error) {
        showErrorToast(
            error?.response?.data?.message ?? 'Unable to load the selected post.',
            'Post',
        );
    }
}

function openPostViewModal(post, { engagementTab = null } = {}) {
    const likes = Array.isArray(post.likes) ? post.likes : [];
    const comments = Array.isArray(post.comments) ? post.comments : [];
    const reviews = Array.isArray(post.reviews) ? post.reviews : [];
    const likesCount = Number(post.likes_count ?? likes.length ?? 0);
    const commentsCount = Number(post.comments_count ?? comments.length ?? 0);
    const reviewsCount = Number(post.reviews_count ?? reviews.length ?? 0);
    const averageRating = Number(post.reviews_avg_rating ?? post.average_rating ?? 0);
    const hasEngagement = likesCount > 0 || commentsCount > 0 || reviewsCount > 0;
    const toolbarHtml = renderPostViewToolbar(post);
    const displayPost = (isPublishedUpdatePending(post) || isPublishedUpdateDraft(post))
        ? postForEditorForm(post)
        : post;
    const changedDiffs = hasPendingRevisionPayload(post) ? getRevisionFieldDiffs(post) : [];
    const revisionBanner = hasPendingRevisionPayload(post)
        ? `<div class="post-view-review-notes" role="status">
                <p class="post-view-review-notes-label">${
                    isPublishedUpdatePending(post)
                        ? 'Proposed update awaiting publisher approval'
                        : 'Draft update (live published post is unchanged)'
                }</p>
                <p class="post-view-review-notes-body">The public feed still shows the live version until a publisher approves this update.</p>
                ${changedDiffs.length ? `
                    <p class="post-view-review-notes-label mt-2 text-[#CE2028]">Fields to review</p>
                    <div class="mt-1.5 flex flex-wrap gap-1.5">
                        ${changedDiffs.map((key) => `<span class="admin-swal-changed-chip">${escapeHtml(humanizeWorkflowAttribute(key))}</span>`).join('')}
                    </div>
                ` : ''}
           </div>`
        : '';

    Swal.fire({
        title: '',
        width: '42rem',
        padding: '0',
        html: `
            <div class="post-view">
                <div class="post-view-hero">
                    <div class="post-view-hero-top">
                        ${renderStatusCell(post)}
                        <span class="post-view-id">#${escapeHtml(String(post.id))}</span>
                    </div>
                    <h2 class="post-view-title">${escapeHtml(displayPost.title ?? 'Post')}</h2>
                    <p class="post-view-subtitle">
                        <span>${escapeHtml(post.category?.name ?? 'Uncategorized')}</span>
                        <span class="post-view-dot" aria-hidden="true">·</span>
                        <span>${escapeHtml(post.author?.name ?? 'Unknown author')}</span>
                        <span class="post-view-dot" aria-hidden="true">·</span>
                        <span>${escapeHtml(formatDateTime(post.publish_date) || 'Not scheduled')}</span>
                    </p>
                    ${renderReviewNotesBanner(post)}
                    ${revisionBanner}
                    <div class="post-view-metrics" role="list">
                        <div class="post-view-metric" role="listitem">
                            <i class="fas fa-heart" aria-hidden="true"></i>
                            <span><strong>${likesCount}</strong> ${likesCount === 1 ? 'like' : 'likes'}</span>
                        </div>
                        <div class="post-view-metric" role="listitem">
                            <i class="fas fa-comment" aria-hidden="true"></i>
                            <span><strong>${commentsCount}</strong> ${commentsCount === 1 ? 'comment' : 'comments'}</span>
                        </div>
                        <div class="post-view-metric" role="listitem">
                            <i class="fas fa-star" aria-hidden="true"></i>
                            <span><strong>${reviewsCount > 0 ? averageRating.toFixed(1) : '—'}</strong> · ${reviewsCount} ${reviewsCount === 1 ? 'rating' : 'ratings'}</span>
                        </div>
                        <div class="post-view-metric" role="listitem">
                            <i class="fas fa-${isPostCommentsEnabled(displayPost) ? 'comment-dots' : 'comment-slash'}" aria-hidden="true"></i>
                            <span>${isPostCommentsEnabled(displayPost) ? 'Comments enabled' : 'Comments disabled'}</span>
                        </div>
                        <div class="post-view-metric" role="listitem">
                            <i class="fas fa-star${isPostFeatured(displayPost) ? '' : '-half-stroke'}" aria-hidden="true"></i>
                            <span>${isPostFeatured(displayPost) ? 'Featured post' : 'Not featured'}</span>
                        </div>
                    </div>
                </div>

                <div class="post-view-content">
                    ${displayPost.media_url ? `<img src="${escapeHtml(displayPost.media_url)}" alt="" class="post-view-media">` : ''}
                    ${displayPost.youtube_url ? `
                        <a class="post-view-youtube" href="${escapeHtml(displayPost.youtube_url)}" target="_blank" rel="noopener noreferrer">
                            <i class="fas fa-play" aria-hidden="true"></i>
                            Watch on YouTube
                        </a>
                    ` : ''}
                    <div class="post-view-body diary-entry-preview">
                        ${displayPost.body || '<p class="post-view-empty">No content yet.</p>'}
                    </div>
                    ${hasEngagement ? renderPostEngagementPanel({ likes, comments, reviews, likesCount, commentsCount, reviewsCount, postId: post.id }) : ''}
                    ${toolbarHtml}
                </div>
            </div>
        `,
        showCancelButton: false,
        confirmButtonText: 'Close',
        confirmButtonColor: '#055498',
        customClass: {
            popup: 'post-view-popup',
            htmlContainer: 'post-view-html',
            actions: 'post-view-actions',
            confirmButton: 'post-view-btn-close',
        },
        didOpen: (popup) => {
            bindPostEngagementTabs();
            bindPostCommentDeletes(popup, deletePostComment);
            activatePostEngagementTab(engagementTab);
            bindPostViewToolbar(popup, post.id);
        },
    });
}

function renderPostViewToolbar(post) {
    const actions = getAvailableActions(post).filter((action) => action.handler !== 'viewPost');

    if (!actions.length) {
        return '';
    }

    return `
        <div class="post-view-toolbar" role="toolbar" aria-label="Post actions">
            ${actions.map((action) => {
                const tone = action.className?.includes('danger')
                    ? 'is-danger'
                    : action.className?.includes('warning')
                        ? 'is-warning'
                        : action.handler === 'editPost' || action.handler === 'publishNow' || action.handler === 'schedulePost'
                            ? 'is-primary'
                            : 'is-secondary';

                return `
                    <button
                        type="button"
                        class="post-view-toolbar-btn ${tone}"
                        data-post-view-action="${escapeHtml(action.handler)}"
                        aria-label="${escapeHtml(action.tooltip || action.label)}"
                        title="${escapeHtml(action.tooltip || action.label)}"
                    >
                        <i class="${escapeHtml(action.icon)}" aria-hidden="true"></i>
                        <span>${escapeHtml(action.label)}</span>
                    </button>
                `;
            }).join('')}
        </div>
    `;
}

function bindPostViewToolbar(popup, postId) {
    popup.querySelectorAll('[data-post-view-action]').forEach((button) => {
        button.addEventListener('click', async () => {
            const handlerName = button.getAttribute('data-post-view-action');
            const handler = window.Posts?.[handlerName];

            if (typeof handler !== 'function') {
                return;
            }

            Swal.close();
            await Promise.resolve();
            handler(postId);
        });
    });
}

function renderPostEngagementPanel({ likes, comments, reviews, likesCount, commentsCount, reviewsCount, postId }) {
    const tabs = [];

    if (likesCount > 0) {
        tabs.push({ id: 'likes', label: `Reactions (${likesCount})`, html: renderPostLikesSection(likes) });
    }

    if (commentsCount > 0) {
        tabs.push({ id: 'comments', label: `Comments (${commentsCount})`, html: renderPostCommentsSection(comments, postId) });
    }

    if (reviewsCount > 0) {
        tabs.push({ id: 'ratings', label: `Ratings (${reviewsCount})`, html: renderPostRatingsSection(reviews) });
    }

    if (tabs.length === 0) {
        return '';
    }

    return `
        <section class="post-view-engagement">
            <div class="post-view-tabs" role="tablist" aria-label="Post engagement">
                ${tabs.map((tab, index) => `
                    <button
                        type="button"
                        class="post-view-tab${index === 0 ? ' is-active' : ''}"
                        role="tab"
                        aria-selected="${index === 0 ? 'true' : 'false'}"
                        data-post-tab="${tab.id}"
                    >${tab.label}</button>
                `).join('')}
            </div>
            ${tabs.map((tab, index) => `
                <div
                    class="post-view-tab-panel${index === 0 ? ' is-active' : ''}"
                    role="tabpanel"
                    data-post-tab-panel="${tab.id}"
                    ${index === 0 ? '' : 'hidden'}
                >${tab.html}</div>
            `).join('')}
        </section>
    `;
}

function bindPostEngagementTabs() {
    const root = document.querySelector('.post-view-engagement');

    if (!root) {
        return;
    }

    root.querySelectorAll('[data-post-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            const tabId = button.getAttribute('data-post-tab');

            root.querySelectorAll('[data-post-tab]').forEach((tab) => {
                const active = tab === button;
                tab.classList.toggle('is-active', active);
                tab.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            root.querySelectorAll('[data-post-tab-panel]').forEach((panel) => {
                const active = panel.getAttribute('data-post-tab-panel') === tabId;
                panel.classList.toggle('is-active', active);
                panel.toggleAttribute('hidden', !active);
            });
        });
    });
}

function activatePostEngagementTab(tabId) {
    if (!tabId) {
        return;
    }

    const tab = document.querySelector(`.post-view-engagement [data-post-tab="${tabId}"]`);
    tab?.click();
}

export async function deletePostComment(postId, commentId) {
    const { deleted } = await confirmDeletePostComment(postId, commentId);

    if (deleted) {
        loadPosts();
    }

    await viewPost(postId, { engagementTab: 'comments' });
    return deleted;
}

function reactionEmoji(reaction) {
    switch (String(reaction || 'like')) {
        case 'love':
            return '❤️';
        case 'haha':
            return '😆';
        case 'sad':
            return '😢';
        case 'angry':
            return '😡';
        default:
            return '👍';
    }
}

function renderPostLikesSection(likes) {
    const items = likes.slice(0, 20).map((like) => `
        <li>
            <div class="post-view-avatar" aria-hidden="true">${escapeHtml(initials(like.user?.name))}</div>
            <div>
                <strong>${escapeHtml(like.user?.name ?? 'Unknown user')}</strong>
                <span>${escapeHtml(like.user?.email ?? '')}</span>
            </div>
            <em title="${escapeHtml(like.reaction ?? 'like')}">${reactionEmoji(like.reaction)} ${escapeHtml(formatDateTime(like.created_at) || '')}</em>
        </li>
    `).join('');

    const more = likes.length > 20
        ? `<p class="post-view-more">Showing 20 of ${likes.length}</p>`
        : '';

    return `
        <ul class="post-view-people">${items}</ul>
        ${more}
    `;
}

function renderPostCommentsSection(comments, postId) {
    const byParent = new Map();
    comments.forEach((comment) => {
        const parentKey = comment.parent_id ? String(comment.parent_id) : 'root';
        if (!byParent.has(parentKey)) {
            byParent.set(parentKey, []);
        }
        byParent.get(parentKey).push(comment);
    });

    const renderTree = (parentId = 'root', depth = 0) => {
        const nodes = byParent.get(String(parentId)) ?? [];

        return nodes.map((comment) => {
            const children = renderTree(comment.id, depth + 1);

            const body = String(comment.body ?? '').trim();
            const imageUrl = String(comment.image_url ?? '').trim();
            const deleteButton = buildAdminActionButton({
                tooltip: 'Delete comment',
                label: 'Delete',
                icon: 'fas fa-trash-can',
                className: 'admin-table-action-danger post-view-comment-delete',
                attrs: `data-post-comment-delete="${escapeHtml(String(comment.id))}" data-post-id="${escapeHtml(String(postId))}"`,
                isMobile: true,
            });

            return `
            <li class="${depth > 0 ? 'post-view-comment-reply' : ''}">
                <div class="post-view-comment-head">
                    <div class="post-view-avatar" aria-hidden="true">${escapeHtml(initials(comment.user?.name))}</div>
                    <div class="post-view-comment-meta">
                        <strong>${escapeHtml(comment.user?.name ?? 'Unknown user')}</strong>
                        <em>${escapeHtml(formatDateTime(comment.created_at) || '')}</em>
                    </div>
                    <div class="post-view-comment-delete-wrap">${deleteButton}</div>
                </div>
                ${body ? `<p>${escapeHtml(body)}</p>` : ''}
                ${imageUrl ? `<img class="post-view-comment-media" src="${escapeHtml(imageUrl)}" alt="Comment sticker">` : ''}
                ${children ? `<ul class="post-view-thread">${children}</ul>` : ''}
            </li>
        `;
        }).join('');
    };

    return `<ul class="post-view-thread">${renderTree()}</ul>`;
}

function renderPostRatingsSection(reviews) {
    const items = reviews.map((review) => `
        <li>
            <div class="post-view-comment-head">
                <div class="post-view-avatar" aria-hidden="true">${escapeHtml(initials(review.user?.name))}</div>
                <div class="post-view-comment-meta">
                    <strong>${escapeHtml(review.user?.name ?? 'Unknown user')}</strong>
                    <span class="post-view-stars" aria-label="${Number(review.rating) || 0} stars">${renderStars(review.rating)}</span>
                    <em>${escapeHtml(formatDateTime(review.created_at) || '')}</em>
                </div>
            </div>
            ${review.comment ? `<p>${escapeHtml(review.comment)}</p>` : ''}
        </li>
    `).join('');

    return `<ul class="post-view-thread">${items}</ul>`;
}

function initials(name) {
    const parts = String(name ?? '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2);

    if (parts.length === 0) {
        return '?';
    }

    return parts.map((part) => part.charAt(0).toUpperCase()).join('');
}

export async function promptEditPost(postId) {
    const post = postsById.get(String(postId));

    if (!post) {
        showErrorToast('Unable to load the selected draft.', 'Post');
        return;
    }

    const categoryOptions = await loadCategoryOptions();

    if (!categoryOptions) {
        return;
    }

    const editingPublished = String(post.status) === 'published' && currentUserRole === 'editor';
    const reviewingUpdate = hasPendingRevisionPayload(post);
    const publisherReviewOnly = reviewingUpdate
        && currentUserRole === 'publisher';
    const result = await openPostEditorSwal({
        title: reviewingUpdate
            ? 'Review post changes'
            : (editingPublished ? 'Edit published post' : 'Edit Post'),
        post: postForEditorForm(post),
        categoryOptions,
        confirmButtonText: publisherReviewOnly
            ? 'Done reviewing'
            : (editingPublished ? 'Save update draft' : 'Save Changes'),
        showStatus: currentUserRole === 'super_admin',
        postId,
        editingPublished,
        reviewItem: reviewingUpdate ? post : null,
    });

    if (!result.isConfirmed || !result.value || result.value.reviewedOnly) {
        return;
    }

    showSuccessToast(result.value.message, editingPublished ? 'Update draft saved' : 'Post updated');
    loadPosts();
}

export async function togglePostComments(postId) {
    const post = postsById.get(String(postId));

    if (!post) {
        showErrorToast('Unable to load the selected post.', 'Post');
        return;
    }

    const currentlyEnabled = isPostCommentsEnabled(post);
    const nextEnabled = !currentlyEnabled;
    const actionLabel = nextEnabled ? 'Enable comments' : 'Disable comments';

    const confirmed = await confirmWithSwal({
        icon: 'question',
        title: `${actionLabel}?`,
        text: nextEnabled
            ? 'Users will be able to comment on this post again.'
            : 'Users will no longer be able to add new comments on this post.',
        confirmButtonText: actionLabel,
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const payload = new FormData();
        payload.append('comments_enabled', nextEnabled ? '1' : '0');
        payload.append('_method', 'PUT');

        const { data } = await axios.post(`/admin/posts/${postId}`, payload);
        const updated = data?.data ?? { ...post, comments_enabled: nextEnabled };
        postsById.set(String(postId), { ...post, ...updated });
        showSuccessToast(
            data?.message
                ?? (nextEnabled ? 'Comments enabled.' : 'Comments disabled.'),
            'Comments',
        );
        loadPosts();
    } catch ({ response }) {
        showErrorToast(
            formatApiValidationMessage(response, 'Unable to update comments setting.'),
            'Error',
        );
    }
}

let pendingPostQueryHandled = false;

function openPostFromQueryParam() {
    if (pendingPostQueryHandled) {
        return;
    }

    const postId = new URLSearchParams(window.location.search).get('post');

    if (!postId) {
        return;
    }

    pendingPostQueryHandled = true;
    viewPost(postId);
}

async function submitPostPayload(payload, { postId = null } = {}) {
    const url = postId ? `/admin/posts/${postId}` : '/admin/posts';

    return axios.post(
        url,
        buildMultipartPayload(payload, postId ? { method: 'PUT' } : {}),
    );
}

export function createDraftPost(formData) {
    return submitPostPayload(formData)
        .then(({ data }) => {
            showSuccessToast(
                data.message,
                currentUserRole === 'super_admin' ? 'Post published' : 'Draft created',
            );
            loadPosts();
            return data;
        })
        .catch(({ response }) => {
            showErrorToast(formatApiValidationMessage(response), 'Error');
        });
}

export function updatePost(postId, formData) {
    return submitPostPayload(formData, { postId })
        .then(({ data }) => {
            showSuccessToast(data.message, 'Post updated');
            loadPosts();
            return data;
        })
        .catch(({ response }) => {
            showErrorToast(formatApiValidationMessage(response), 'Error');
        });
}

export function submitPostForReview(postId) {
    const post = postsById.get(String(postId));
    const isPublishedUpdate = isPublishedUpdateDraft(post);

    confirmWithSwal({
        icon: 'question',
        title: isPublishedUpdate ? 'Submit update for review?' : 'Submit for Review?',
        text: isPublishedUpdate
            ? 'Publishers will review this update. The live published post stays unchanged until they approve it.'
            : 'This will send the post to a Publisher for review.',
        confirmButtonText: 'Submit',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/submit`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Submitted');
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Submission failed.', 'Error');
            });
    });
}

export function rejectPost(postId) {
    Swal.fire(buildSwalOptions({
        title: 'Request revision',
        subtitle: 'Return this to draft so the editor can fix it. Your notes will be visible to them.',
        html: buildSwalForm({
            fields: [
                {
                    id: 'post-review-notes',
                    label: 'Revision notes *',
                    type: 'textarea',
                    placeholder: 'Describe what the editor should fix before resubmitting…',
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Return for revision',
        preConfirm: () => {
            const notes = Swal.getPopup()
                ?.querySelector('#post-review-notes')
                ?.value
                ?.trim();

            if (!notes) {
                return failSwalValidation('Revision notes are required.', ['post-review-notes']);
            }

            return notes;
        },
    }, { danger: true })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/reject`, { review_notes: value })
            .then(({ data }) => {
                showSuccessToast(data.message ?? 'Post returned to draft for revision.', 'Revision requested');
                if (currentUserRole !== 'publisher') {
                    activeStatusFilter = 'draft';
                    setAdminFilterTab(document.getElementById('posts-status-tabs'), 'draft');
                }
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Unable to return for revision.', 'Error');
            });
    });
}

export function schedulePost(postId) {
    Swal.fire(buildSwalOptions({
        title: 'Schedule Post',
        html: buildSwalForm({
            description: 'Select when this post should go live.',
            fields: [
                {
                    id: 'swal-publish-date',
                    label: 'Publish Date & Time *',
                    type: 'datetime-local',
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Schedule',
        preConfirm: () => {
            const val = document.getElementById('swal-publish-date')?.value;
            if (!val) {
                return failSwalValidation('Please pick a publish date and time.', ['swal-publish-date']);
            }
            return val;
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/schedule`, { publish_date: value })
            .then(({ data }) => {
                showSuccessToast(data.message, 'Scheduled');
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Schedule failed.', 'Error');
            });
    });
}

export function publishNow(postId) {
    const post = postsById.get(String(postId));
    const isPublishedUpdate = isPublishedUpdatePending(post);
    const changedFields = isPublishedUpdate
        ? getRevisionFieldDiffs(post).map((key) => humanizeWorkflowAttribute(key))
        : [];
    const changedSummary = changedFields.length
        ? ` Changed fields: ${changedFields.join(', ')}.`
        : '';

    confirmWithSwal({
        icon: 'question',
        title: isPublishedUpdate ? 'Approve this update?' : 'Publish this post now?',
        text: isPublishedUpdate
            ? `This will replace the live published post with the proposed update.${changedSummary}`
            : 'This will approve and publish the post immediately.',
        confirmButtonText: isPublishedUpdate ? 'Approve update' : 'Publish now',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/publish`)
            .then(({ data }) => {
                showSuccessToast(data.message, isPublishedUpdate ? 'Update approved' : 'Published');
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Publish failed.', 'Error');
            });
    });
}

export function archivePost(postId) {
    confirmWithSwal({
        icon: 'warning',
        title: 'Archive Post?',
        text: 'This will remove the post from the public feed.',
        confirmButtonText: 'Archive',
        danger: true,
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/archive`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Archived');
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Archive failed.', 'Error');
            });
    });
}

export function restorePost(postId) {
    confirmWithSwal({
        icon: 'question',
        title: 'Restore Post?',
        text: 'This will publish the post again and show it in the public feed.',
        confirmButtonText: 'Restore',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/restore`)
            .then(({ data }) => {
                showSuccessToast(data.message ?? 'Post restored.', 'Restored');
                activeStatusFilter = 'published';
                setAdminFilterTab(document.getElementById('posts-status-tabs'), activeStatusFilter);
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Restore failed.', 'Error');
            });
    });
}

export function deletePost(postId) {
    confirmWithSwal({
        icon: 'warning',
        title: 'Delete Post Permanently?',
        text: 'This action cannot be undone.',
        confirmButtonText: 'Delete',
        danger: true,
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/posts/${postId}`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Deleted');
                loadPosts();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.', 'Error');
            });
    });
}

function statusBadge(status) {
    const colors = {
        draft:          'bg-slate-100 text-slate-700',
        pending_review: 'bg-[#FBD116]/20 text-[#123a60]',
        scheduled:      'bg-[#055498]/10 text-[#055498]',
        published:      'bg-emerald-100 text-emerald-700',
        archived:       'bg-[#CE2028]/10 text-[#CE2028]',
    };
    const cls = colors[status] ?? 'bg-gray-100 text-gray-700';
    return `<span class="admin-status-badge ${cls}">${String(status || 'unknown').replaceAll('_', ' ')}</span>`;
}

function renderReviewNotesBanner(post) {
    const notes = String(post?.review_notes ?? '').trim();
    if (!notes) {
        return '';
    }

    return `
        <div class="post-view-review-notes" role="status">
            <p class="post-view-review-notes-label">Revision notes from publisher</p>
            <p class="post-view-review-notes-body">${escapeHtml(notes)}</p>
        </div>
    `;
}

function renderStatusCell(post) {
    const notes = String(post?.review_notes ?? '').trim();
    const needsRevision = (post.status === 'draft' || isPublishedUpdateDraft(post)) && notes !== '';
    const updateBadge = isPublishedUpdatePending(post)
        ? '<span class="admin-status-badge bg-[#055498]/10 text-[#055498]">Update pending</span>'
        : isPublishedUpdateDraft(post)
            ? '<span class="admin-status-badge bg-[#FBD116]/20 text-[#123a60]">Update draft</span>'
            : '';

    return `
        <div class="flex min-w-0 flex-col gap-1">
            ${statusBadge(post.status)}
            ${updateBadge}
            ${needsRevision ? '<span class="admin-status-badge bg-[#FBD116]/20 text-[#123a60]">Needs revision</span>' : ''}
        </div>
    `;
}

function actionButtons(post) {
    return renderPostActions(post, false);
}

function renderPostActions(post, isMobile = false) {
    const actions = getAvailableActions(post);

    if (actions.length === 0) {
        return '<span class="admin-empty-badge">No actions</span>';
    }

    return buildAdminActionButtons(
        actions.map((action) => ({
            tooltip: action.tooltip ?? action.label,
            label: action.label,
            icon: [action.icon, action.iconClass].filter(Boolean).join(' '),
            className: action.className ?? '',
            attrs: `data-post-action="${action.handler}" data-post-id="${post.id}"`,
        })),
        { isMobile, nowrap: true, overflowAfter: isMobile ? null : 2 },
    );
}

function bindPostTableActions(tableEl) {
    if (postsActionsBound) {
        return;
    }

    bindAdminActionTooltipSuppression(tableEl);

    const handlePostActionClick = (event) => {
        const actionTrigger = event.target.closest('[data-post-action]');
        if (actionTrigger) {
            event.preventDefault();
            const action = actionTrigger.getAttribute('data-post-action');
            const postId = actionTrigger.getAttribute('data-post-id');

            if (action && postId && typeof window.Posts?.[action] === 'function') {
                window.Posts[action](postId);
            }

            return;
        }

        const viewTrigger = event.target.closest('[data-post-view]');
        if (!viewTrigger) {
            return;
        }

        event.preventDefault();
        const postId = viewTrigger.getAttribute('data-post-view');
        if (postId) {
            viewPost(postId);
        }
    };

    tableEl.addEventListener('click', handlePostActionClick);
    document.addEventListener('click', (event) => {
        if (event.target.closest('.admin-action-overflow-menu [data-post-action]')) {
            handlePostActionClick(event);
        }
    });

    postsActionsBound = true;
}

function getPostsTableMode() {
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
}

async function initializePostsTable(tableEl) {
    const nextMode = getPostsTableMode();

    if (postsTable) {
        postsTable.destroy();
        tableEl.innerHTML = '';
    }

    postsTableMode = nextMode;
    postsTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search posts:',
        searchPlaceholder: 'Search posts',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ posts',
        responsive: false,
        pageLength: nextMode === 'mobile' ? 5 : 10,
        scrollX: nextMode !== 'mobile',
        scrollCollapse: nextMode !== 'mobile',
        deferRender: false,
        drawCallback: () => {
            refreshVisiblePostActions();

            if (postsTable && typeof postsTable.columns?.adjust === 'function') {
                postsTable.columns.adjust();
            }
        },
        columns: nextMode === 'mobile'
            ? [
                { title: 'Post', className: 'dt-col-mobile-summary' },
                { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
            ]
            : [
                { title: 'ID', className: 'dt-col-id' },
                { title: 'Title', className: 'dt-col-primary dt-col-wide' },
                { title: 'Category', className: 'dt-col-nowrap' },
                { title: 'Status', className: 'dt-col-nowrap' },
                { title: 'Author', className: 'dt-col-nowrap' },
                { title: 'Publish Date', className: 'dt-col-nowrap' },
                { title: 'Comments', className: 'dt-col-nowrap' },
                { title: 'Rating', className: 'dt-col-nowrap' },
                { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
            ],
    }));
}

function refreshVisiblePostActions() {
    if (!postsTable) {
        return;
    }

    const actionsColIndex = postsTableMode === 'mobile' ? 1 : 8;

    postsTable.rows({ page: 'current' }).every(function refreshRowActions() {
        const rowNode = this.node();
        const rowData = this.data();

        if (!rowNode || !rowData) {
            return;
        }

        const postId = postsTableMode === 'mobile'
            ? (rowNode.querySelector('[data-post-view], [data-post-id]')?.getAttribute('data-post-view')
                ?? rowNode.querySelector('[data-post-id]')?.getAttribute('data-post-id'))
            : rowData[0];

        const post = postsById.get(String(postId));

        if (!post) {
            return;
        }

        const actionsHtml = renderPostActions(post, postsTableMode === 'mobile');
        const cellNode = rowNode.querySelector('td.dt-col-actions')
            ?? rowNode.children[actionsColIndex]
            ?? null;

        if (cellNode && cellNode.innerHTML !== actionsHtml) {
            cellNode.innerHTML = actionsHtml;
        }

        // Keep DataTables cache in sync so later pages/redraws keep the buttons.
        if (Array.isArray(rowData) && rowData[actionsColIndex] !== actionsHtml) {
            rowData[actionsColIndex] = actionsHtml;
        }
    });
}

function bindPostsViewportListener(tableEl) {
    if (postsViewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 1023px)');
    const onChange = async () => {
        const nextMode = getPostsTableMode();

        if (nextMode === postsTableMode) {
            return;
        }

        await initializePostsTable(tableEl);
        loadPosts();
    };

    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', onChange);
    } else {
        query.addListener(onChange);
    }

    postsViewportBound = true;
}

function buildPostRowData(post) {
    const titleLink = `
        <div class="flex min-w-0 flex-wrap items-center gap-2">
            <button type="button" class="admin-table-link-chip text-left" data-post-view="${post.id}" title="View post details">
                ${escapeHtml(post.title)}
            </button>
            ${featuredBadge(post)}
        </div>
    `;

    if (postsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Post #${post.id}${isPostFeatured(post) ? ' · Featured' : ''}</p>
                        <button type="button" class="admin-table-mobile-title text-left" data-post-view="${post.id}" title="View post details">
                            ${escapeHtml(post.title)}
                        </button>
                    </div>
                    ${renderStatusCell(post)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Category:</span> ${escapeHtml(post.category?.name ?? 'Uncategorized')}</p>
                    <p><span>Author:</span> ${escapeHtml(post.author?.name ?? 'N/A')}</p>
                    <p><span>Publish Date:</span> ${escapeHtml(formatDateTime(post.publish_date) || 'Not scheduled')}</p>
                    <p><span>Featured:</span> ${isPostFeatured(post) ? 'Yes' : 'No'}</p>
                    <p><span>Comments:</span> ${isPostCommentsEnabled(post) ? 'Enabled' : 'Disabled'}</p>
                    <p><span>Engagement:</span> ${Number(post.likes_count ?? 0)} reactions · ${Number(post.comments_count ?? 0)} comments</p>
                    <p><span>Rating:</span> ${renderRatingBadge(post, { compact: true })}</p>
                    ${String(post.review_notes ?? '').trim()
                        ? `<p><span>Revision notes:</span> ${escapeHtml(String(post.review_notes).trim())}</p>`
                        : ''}
                </div>
            </div>`,
            renderPostActions(post, true),
        ];
    }

    return [
        post.id,
        titleLink,
        escapeHtml(post.category?.name ?? '—'),
        renderStatusCell(post),
        escapeHtml(post.author?.name ?? '—'),
        escapeHtml(formatDateTime(post.publish_date) || '—'),
        commentsEnabledBadge(post),
        renderRatingBadge(post),
        actionButtons(post),
    ];
}

function isPostCommentsEnabled(post) {
    return !(post?.comments_enabled === false
        || post?.comments_enabled === 0
        || post?.comments_enabled === '0');
}

function isPostFeatured(post) {
    return post?.is_featured === true
        || post?.is_featured === 1
        || post?.is_featured === '1';
}

function featuredBadge(post) {
    if (!isPostFeatured(post)) {
        return '';
    }

    return '<span class="admin-status-badge bg-[#FBD116] text-[#123a60]">Featured</span>';
}

function commentsEnabledBadge(post) {
    const enabled = isPostCommentsEnabled(post);
    const cls = enabled
        ? 'bg-emerald-100 text-emerald-800'
        : 'bg-slate-100 text-slate-600';
    const label = enabled ? 'Enabled' : 'Disabled';

    return `<span class="admin-status-badge ${cls}">${label}</span>`;
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function bindScheduledPublishDateField() {
    const statusEl = document.getElementById('post-status');
    const dateField = document.getElementById('post-publish-date')?.closest('.admin-swal-field');

    if (!statusEl || !dateField) {
        return;
    }

    const sync = () => {
        const scheduled = statusEl.value === 'scheduled';
        dateField.hidden = !scheduled;
        const input = dateField.querySelector('input');
        if (input) {
            input.required = scheduled;
        }
    };

    statusEl.addEventListener('change', sync);
    sync();
}

function toDatetimeLocalValue(value) {
    if (!value) {
        return '';
    }

    const normalized = typeof value === 'string' && value.includes(' ')
        ? value.replace(' ', 'T')
        : value;
    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const pad = (part) => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function formatDateTime(value) {
    if (!value) {
        return '';
    }

    // Support both "YYYY-MM-DD HH:MM:SS" and ISO strings
    const normalized = typeof value === 'string' && value.includes(' ')
        ? value.replace(' ', 'T')
        : value;

    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function buildPostForm(post = null, categoryOptions = [], {
    showStatus = false,
    isSuperAdmin = false,
    editingPublished = false,
    reviewItem = null,
} = {}) {
    const fields = [
        {
            id: 'post-media-file',
            label: 'Featured Image',
            type: 'file',
            accept: 'image/*',
            showPreview: true,
            previewUrl: post?.media_url ?? '',
            attribute: 'media_url',
            hint: post?.media_url
                ? 'Choose a new image only if you want to replace the current upload.'
                : 'Upload a JPG, PNG, WEBP, or GIF image up to 5MB.',
        },
        {
            id: 'post-is-featured',
            label: 'Featured post',
            type: 'checkbox',
            checked: isPostFeatured(post),
            attribute: 'is_featured',
            hint: 'Featured posts appear in the mobile app carousel when more than one is selected.',
        },
        { id: 'post-title', label: 'Title *', placeholder: 'Enter post title', value: post?.title ?? '', attribute: 'title' },
        {
            id: 'post-body',
            label: 'Body / rich text content *',
            type: 'richtext',
            placeholder: 'Write the main content',
            value: post?.body ?? '',
            attribute: 'body',
        },
        {
            id: 'post-category-id',
            label: 'Category *',
            type: 'select',
            value: post?.category_id ?? '',
            options: categoryOptions,
            attribute: 'category_id',
        },
    ];

    if (showStatus) {
        fields.push({
            id: 'post-status',
            label: 'Status *',
            type: 'select',
            value: post?.status ?? 'draft',
            options: [
                { value: 'draft', label: 'Draft' },
                { value: 'pending_review', label: 'Pending review' },
                { value: 'scheduled', label: 'Scheduled' },
                { value: 'published', label: 'Published' },
                { value: 'archived', label: 'Archived' },
            ],
        }, {
            id: 'post-publish-date',
            label: 'Go live date & time *',
            type: 'datetime-local',
            value: toDatetimeLocalValue(post?.publish_date),
            hint: 'Required for Scheduled. The server publishes automatically at this time (Asia/Manila).',
        });
    }

    fields.push(
        {
            id: 'post-comments-enabled',
            label: 'Comments *',
            type: 'select',
            value: isPostCommentsEnabled(post) ? '1' : '0',
            options: [
                { value: '1', label: 'Enabled — users can comment' },
                { value: '0', label: 'Disabled — comments turned off' },
            ],
            attribute: 'comments_enabled',
            hint: 'You can change this anytime from the post actions menu.',
        },
        {
            id: 'post-youtube-url',
            label: 'YouTube URL',
            placeholder: 'Paste YouTube link',
            value: post?.youtube_url ?? '',
            attribute: 'youtube_url',
        },
    );

    return buildSwalForm({
        description: post
            ? (editingPublished
                ? 'Changes are saved as an update draft. The live published post stays unchanged until a publisher approves it.'
                : (isSuperAdmin
                    ? 'Updates from super admin are published automatically unless status is scheduled or archived.'
                    : 'Update the content and workflow details for this post.'))
            : (isSuperAdmin
                ? 'Super admin posts are published immediately after creation.'
                : 'Create a new content draft for review and scheduling.'),
        fields,
        reviewItem,
    });
}

async function openPostEditorSwal({
    title,
    post,
    categoryOptions,
    confirmButtonText,
    showStatus = false,
    postId = null,
    editingPublished = false,
    reviewItem = null,
}) {
    const isSuperAdmin = currentUserRole === 'super_admin';
    const publisherReviewOnly = confirmButtonText === 'Done reviewing';

    return Swal.fire(buildSwalOptions({
        title,
        html: buildPostForm(post, categoryOptions, {
            showStatus,
            isSuperAdmin,
            editingPublished,
            reviewItem,
        }),
        showCancelButton: !publisherReviewOnly,
        confirmButtonText,
        focusConfirm: false,
        didOpen: () => {
            bindScheduledPublishDateField();
        },
        preConfirm: async () => {
            if (publisherReviewOnly) {
                return { reviewedOnly: true };
            }

            const categoryValue = document.getElementById('post-category-id')?.value ?? '';
            const mediaFile = document.getElementById('post-media-file')?.files?.[0] ?? null;
            const titleValue = document.getElementById('post-title')?.value.trim();
            const youtubeValue = document.getElementById('post-youtube-url')?.value.trim() || null;
            const bodyValue = getRichTextValue('post-body');

            const payload = {
                title: titleValue,
                body: bodyValue,
                category_id: Number(categoryValue),
                media_file: mediaFile,
                youtube_url: youtubeValue,
            };

            const statusValue = document.getElementById('post-status')?.value;
            if (statusValue) {
                payload.status = statusValue;
            }

            if (payload.status === 'scheduled') {
                const publishDate = document.getElementById('post-publish-date')?.value;
                if (!publishDate) {
                    return failSwalValidation(
                        'Pick a go-live date and time for a scheduled post.',
                        ['post-publish-date'],
                    );
                }
                payload.publish_date = publishDate;
            }

            const commentsEnabledValue = document.getElementById('post-comments-enabled')?.value;
            if (commentsEnabledValue !== undefined && commentsEnabledValue !== null && commentsEnabledValue !== '') {
                payload.comments_enabled = commentsEnabledValue === '1';
            }

            payload.is_featured = Boolean(document.getElementById('post-is-featured')?.checked);

            if (!payload.title || isRichTextEmpty(payload.body) || !categoryValue || Number.isNaN(payload.category_id) || payload.category_id < 1) {
                const missing = [];
                if (!payload.title) missing.push('post-title');
                if (isRichTextEmpty(payload.body)) missing.push('post-body');
                if (!categoryValue || Number.isNaN(payload.category_id) || payload.category_id < 1) {
                    missing.push('post-category-id');
                }
                return failSwalValidation('Title, body, and category are required.', missing);
            }

            if (payload.youtube_url && !isValidHttpUrl(payload.youtube_url)) {
                return failSwalValidation('The YouTube URL field must be a valid URL.', ['post-youtube-url']);
            }

            try {
                const { data } = await submitPostPayload(payload, { postId });
                return data;
            } catch (error) {
                return failSwalValidation(
                    formatApiValidationMessage(error.response),
                    [],
                    error.response,
                );
            }
        },
    }));
}

function isValidHttpUrl(value) {
    try {
        const url = new URL(value);
        return url.protocol === 'http:' || url.protocol === 'https:';
    } catch {
        return false;
    }
}

function syncPostsPageActions() {
    document.querySelector('[data-admin-action="create-post"]')?.classList.toggle('hidden', !canCreateDraft());

    const createButton = document.querySelector('[data-admin-action="create-post"]');
    if (createButton) {
        const label = currentUserRole === 'super_admin' ? 'Create Post' : 'Create Draft';
        createButton.textContent = label;
        createButton.setAttribute(
            'title',
            currentUserRole === 'super_admin'
                ? 'Create and publish a new post'
                : 'Create a new draft post',
        );
    }

    const canReviewInsights = ['super_admin', 'analytics_viewer'].includes(currentUserRole);
    const insightsLink = document.querySelector('[data-admin-action="review-insights"]');
    if (insightsLink) {
        insightsLink.setAttribute('title', 'Open analytics dashboard');
        insightsLink.classList.toggle('hidden', !canReviewInsights);
        // Editors/publishers have no analytics access — remove so it cannot be focused or crawled.
        if (!canReviewInsights) {
            insightsLink.setAttribute('hidden', 'hidden');
            insightsLink.setAttribute('aria-hidden', 'true');
            insightsLink.tabIndex = -1;
        } else {
            insightsLink.removeAttribute('hidden');
            insightsLink.removeAttribute('aria-hidden');
            insightsLink.removeAttribute('tabindex');
        }
    }
}

async function loadCategoryOptions() {
    if (categoryOptionsCache && categoryOptionsCache.length > 1) {
        return categoryOptionsCache;
    }

    try {
        const { data } = await axios.get('/admin/categories');
        const categories = data.data ?? [];

        categoryOptionsCache = [
            { value: '', label: 'Select a category' },
            ...categories.map((category) => ({
                value: String(category.id),
                label: category.name,
            })),
        ];

        return categoryOptionsCache;
    } catch ({ response }) {
        await Swal.fire({
            icon: 'error',
            title: 'Categories',
            text: response?.data?.message ?? 'Failed to load categories.',
            confirmButtonColor: '#CE2028',
        });

        return null;
    }
}

function buildMultipartPayload(formData, { method = 'POST' } = {}) {
    const payload = new FormData();

    payload.append('title', formData.title);
    payload.append('body', formData.body);
    payload.append('category_id', String(formData.category_id));

    if (formData.youtube_url) {
        payload.append('youtube_url', formData.youtube_url);
    }

    if (formData.media_file) {
        payload.append('media_file', formData.media_file);
    }

    if (formData.status) {
        payload.append('status', formData.status);
    }

    if (formData.publish_date) {
        payload.append('publish_date', formData.publish_date);
    }

    if (typeof formData.comments_enabled === 'boolean') {
        payload.append('comments_enabled', formData.comments_enabled ? '1' : '0');
    }

    if (typeof formData.is_featured === 'boolean') {
        payload.append('is_featured', formData.is_featured ? '1' : '0');
    }

    if (method !== 'POST') {
        payload.append('_method', method);
    }

    return payload;
}

function getAvailableActions(post) {
    const actions = [
        {
            handler: 'viewPost',
            label: 'View',
            tooltip: 'View post details',
            icon: 'fas fa-eye',
            className: 'admin-table-action-primary',
        },
    ];

    if (canEditPost(post)) {
        actions.push({
            handler: 'editPost',
            label: 'Edit',
            tooltip: 'Edit post',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
        });

        const commentsOn = isPostCommentsEnabled(post);
        actions.push({
            handler: 'toggleComments',
            label: commentsOn ? 'Disable comments' : 'Enable comments',
            tooltip: commentsOn ? 'Disable comments' : 'Enable comments',
            icon: commentsOn ? 'fas fa-comment-slash' : 'fas fa-comment-dots',
            className: 'admin-table-action-primary',
        });
    } else if (
        (currentUserRole === 'publisher' || currentUserRole === 'super_admin')
        && (post.status === 'pending_review' || isPublishedUpdatePending(post))
    ) {
        actions.push({
            handler: 'editPost',
            label: 'Review',
            tooltip: isPublishedUpdatePending(post)
                ? 'Review highlighted field changes'
                : 'Review before publishing',
            icon: 'fas fa-clipboard-check',
            className: 'admin-table-action-primary',
        });
    }

    if (canSubmitPost(post)) {
        actions.push({
            handler: 'submitForReview',
            label: 'Submit',
            tooltip: 'Submit for review',
            icon: 'fas fa-paper-plane',
            className: 'admin-table-action-primary',
        });
    }

    if (canRejectPost(post)) {
        actions.push({
            handler: 'rejectPost',
            label: 'Revision',
            tooltip: 'Return to draft with revision notes',
            icon: 'fas fa-rotate-left',
            iconClass: 'text-[#123a60]',
            className: 'admin-table-action-warning',
        });
    }

    if (canSchedulePost(post)) {
        actions.push({
            handler: 'schedulePost',
            label: 'Approve & Schedule',
            tooltip: 'Approve and schedule publish date',
            icon: 'fas fa-calendar-check',
        });
    }

    if (canPublishPost(post)) {
        actions.push({
            handler: 'publishNow',
            label: isPublishedUpdatePending(post) ? 'Approve update' : 'Publish now',
            tooltip: isPublishedUpdatePending(post)
                ? 'Apply update to the live published post'
                : 'Publish immediately',
            icon: 'fas fa-circle-check',
            className: 'admin-table-action-primary',
        });
    }

    if (canArchivePost(post)) {
        actions.push({
            handler: 'archivePost',
            label: 'Archive',
            tooltip: 'Archive post',
            icon: 'fas fa-box-archive',
        });
    }

    if (canRestorePost(post)) {
        actions.push({
            handler: 'restorePost',
            label: 'Restore',
            tooltip: 'Restore to published',
            icon: 'fas fa-rotate-left',
            className: 'admin-table-action-primary',
        });
    }

    if (canDeletePost(post)) {
        actions.push({
            handler: 'deletePost',
            label: 'Delete',
            tooltip: 'Delete permanently',
            icon: 'fas fa-trash',
            iconClass: 'text-[#CE2028]',
            className: 'admin-table-action-danger',
        });
    }

    return actions;
}

function canCreateDraft() {
    return currentUserRole === 'editor' || currentUserRole === 'super_admin';
}

function canEditPost(post) {
    if (currentUserRole === 'super_admin') {
        return ['draft', 'pending_review', 'published', 'scheduled', 'archived'].includes(post.status);
    }

    if (currentUserRole !== 'editor') {
        return false;
    }

    // Draft posts, or published posts (edits become a pending update draft).
    return post.status === 'draft' || post.status === 'published';
}

function canSubmitPost(post) {
    if (!(currentUserRole === 'editor' || currentUserRole === 'super_admin')) {
        return false;
    }

    return post.status === 'draft' || isPublishedUpdateDraft(post);
}

function canRejectPost(post) {
    return (currentUserRole === 'publisher' || currentUserRole === 'super_admin')
        && (post.status === 'pending_review' || isPublishedUpdatePending(post));
}

function canSchedulePost(post) {
    return (currentUserRole === 'publisher' || currentUserRole === 'super_admin')
        && post.status === 'pending_review';
}

function canPublishPost(post) {
    if (!(currentUserRole === 'publisher' || currentUserRole === 'super_admin')) {
        return false;
    }

    return post.status === 'pending_review'
        || post.status === 'scheduled'
        || isPublishedUpdatePending(post);
}

function canArchivePost(post) {
    return currentUserRole === 'super_admin'
        && ['published', 'scheduled'].includes(post.status);
}

function canRestorePost(post) {
    return currentUserRole === 'super_admin' && post.status === 'archived';
}

function canDeletePost(_post) {
    return currentUserRole === 'super_admin';
}

window.Posts = {
    createDraftPrompt: promptCreateDraft,
    viewPost,
    editPost: promptEditPost,
    toggleComments: togglePostComments,
    submitForReview: submitPostForReview,
    schedulePost,
    publishNow,
    rejectPost,
    archivePost,
    restorePost,
    deletePost,
    deleteComment: deletePostComment,
};
