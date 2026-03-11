import axios from 'axios';
import Swal from 'sweetalert2';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import { getStoredUser } from './auth';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { buildSwalForm, buildSwalOptions } from './shared/swal-forms';

let postsTable;
let currentUserRole = null;
const postsById = new Map();
let categoryOptionsCache = null;
let postsTableMode = null;
let postsViewportBound = false;

export function initPostsModule() {
    const tableEl = document.getElementById('posts-table');
    if (!tableEl) return;

    currentUserRole = getStoredUser()?.role ?? null;
    syncPostsPageActions();

    if (postsTable && postsTableMode === getPostsTableMode()) {
        loadPosts();
        bindPostsContextMenu();
        return;
    }

    initializePostsTable(tableEl).then(() => {
        loadPosts();
        bindPostsContextMenu();
    });

    bindPostsViewportListener(tableEl);
}

export function loadPosts() {
    if (!postsTable) return;

    axios.get('/admin/posts')
        .then((response) => {
            try {
                const payload = response?.data ?? {};
                const collection = Array.isArray(payload.data?.data)
                    ? payload.data.data
                    : Array.isArray(payload.data)
                        ? payload.data
                        : [];

                postsTable.clear();
                postsById.clear();

                collection.forEach((post) => {
                    postsById.set(String(post.id), post);
                    postsTable.row.add(buildPostRowData(post));
                });

                postsTable.draw();
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

export async function promptCreateDraft() {
    const categoryOptions = await loadCategoryOptions();

    if (!categoryOptions) {
        return;
    }

    const result = await openPostEditorSwal({
        title: 'Create Draft Post',
        post: null,
        categoryOptions,
        confirmButtonText: 'Create Draft',
    });

    if (!result.isConfirmed) {
        return;
    }

    createDraftPost(result.value);
}

export async function promptEditPost(postId) {
    const post = postsById.get(String(postId));

    if (!post) {
        await Swal.fire({
            icon: 'error',
            title: 'Post',
            text: 'Unable to load the selected draft.',
            confirmButtonColor: '#CE2028',
        });

        return;
    }

    const categoryOptions = await loadCategoryOptions();

    if (!categoryOptions) {
        return;
    }

    const result = await openPostEditorSwal({
        title: 'Edit Draft Post',
        post,
        categoryOptions,
        confirmButtonText: 'Save Changes',
    });

    if (!result.isConfirmed) {
        return;
    }

    updatePost(postId, result.value);
}

export function createDraftPost(formData) {
    return axios.post('/admin/posts', buildMultipartPayload(formData))
        .then(({ data }) => {
            Swal.fire({ icon: 'success', title: 'Draft created', text: data.message });
            loadPosts();
            return data;
        })
        .catch(({ response }) => {
            Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Failed to create post.' });
        });
}

export function updatePost(postId, formData) {
    return axios.post(`/admin/posts/${postId}`, buildMultipartPayload(formData, { method: 'PUT' }))
        .then(({ data }) => {
            Swal.fire({ icon: 'success', title: 'Post updated', text: data.message });
            loadPosts();
            return data;
        })
        .catch(({ response }) => {
            Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Failed to update post.' });
        });
}

export function submitPostForReview(postId) {
    Swal.fire({
        icon: 'question',
        title: 'Submit for Review?',
        text: 'This will send the post to a Publisher for review.',
        showCancelButton: true,
        confirmButtonText: 'Submit',
        confirmButtonColor: '#055498',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/submit`)
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Submitted', text: data.message });
                loadPosts();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Submission failed.' });
            });
    });
}

export function rejectPost(postId) {
    Swal.fire(buildSwalOptions({
        title: 'Reject Post',
        html: buildSwalForm({
            description: 'Send this draft back with clear review notes for the editor.',
            fields: [
                {
                    id: 'post-review-notes',
                    label: 'Review notes *',
                    type: 'textarea',
                    placeholder: 'Explain why this post is being rejected...',
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Reject',
        preConfirm: () => {
            const notes = document.getElementById('post-review-notes')?.value.trim();

            if (!notes?.trim()) {
                Swal.showValidationMessage('Review notes are required.');

                return false;
            }

            return notes;
        },
    }, { danger: true })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/reject`, { review_notes: value })
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Rejected', text: data.message });
                loadPosts();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Rejection failed.' });
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
                Swal.showValidationMessage('Please pick a publish date and time.');
                return false;
            }
            return val;
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/schedule`, { publish_date: value })
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Scheduled', text: data.message });
                loadPosts();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Schedule failed.' });
            });
    });
}

export function publishNow(postId) {
    Swal.fire({
        icon: 'question',
        title: 'Publish this post now?',
        text: 'This will approve and publish the post immediately.',
        showCancelButton: true,
        confirmButtonText: 'Publish now',
        confirmButtonColor: '#055498',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/publish`)
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Published', text: data.message });
                loadPosts();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Publish failed.' });
            });
    });
}

export function archivePost(postId) {
    Swal.fire({
        icon: 'warning',
        title: 'Archive Post?',
        text: 'This will remove the post from the public feed.',
        showCancelButton: true,
        confirmButtonText: 'Archive',
        confirmButtonColor: '#CE2028',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.put(`/admin/posts/${postId}/archive`)
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Archived', text: data.message });
                loadPosts();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Archive failed.' });
            });
    });
}

export function deletePost(postId) {
    Swal.fire({
        icon: 'error',
        title: 'Delete Post Permanently?',
        text: 'This action cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#CE2028',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/posts/${postId}`)
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Deleted', text: data.message });
                loadPosts();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Delete failed.' });
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
    return `<span class="admin-status-badge ${cls}">${status.replace('_', ' ')}</span>`;
}

function actionButtons(post) {
    return renderPostActions(post, false);
}

function renderPostActions(post, isMobile = false) {
    const actions = getAvailableActions(post);

    if (actions.length === 0) {
        return '<span class="admin-empty-badge">No actions</span>';
    }

    return `<div class="admin-table-actions${isMobile ? ' admin-table-actions-mobile' : ''}">${actions.map((action) => `
        <button onclick="window.Posts.${action.handler}(${post.id})" class="admin-table-action ${isMobile ? '' : 'admin-table-action-icon'} ${action.className ?? ''}" title="${action.label}" aria-label="${action.label}"><i class="${action.icon}"></i><span class="${isMobile ? '' : 'sr-only'}">${action.label}</span></button>
    `).join('')}</div>`;
}

function bindPostsContextMenu() {
    const menu = document.getElementById('posts-context-menu');
    if (!menu) return;

    document.getElementById('posts-table')?.addEventListener('contextmenu', (e) => {
        if (postsTableMode !== 'desktop') {
            return;
        }

        e.preventDefault();
        const row = e.target.closest('tr');
        if (!row) return;
        const rowData = postsTable?.row(row).data();
        if (!rowData) return;
        const postId = rowData[0];
        const post = postsById.get(String(postId));
        const actions = post ? getAvailableActions(post) : [];

        if (actions.length === 0) {
            menu.style.display = 'none';
            return;
        }

        menu.innerHTML = actions.map((action) => `
            <button type="button" class="admin-context-menu-button" data-action="${action.handler}">
                <i class="${action.icon} w-4 ${action.iconClass ?? 'text-[#055498]'}"></i>${action.label}
            </button>
        `).join('');

        menu.style.display = 'block';
        menu.style.top  = `${e.pageY}px`;
        menu.style.left = `${e.pageX}px`;
        menu.dataset.postId = postId;
    });

    menu.addEventListener('click', (event) => {
        const action = event.target.closest('[data-action]')?.getAttribute('data-action');
        const postId = menu.dataset.postId;

        if (!action || !postId) {
            return;
        }

        menu.style.display = 'none';

        if (typeof window.Posts?.[action] === 'function') {
            window.Posts[action](postId);
        }
    });

    document.addEventListener('click', () => {
        if (menu) menu.style.display = 'none';
    });
}

function getPostsTableMode() {
    return window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop';
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
        columns: nextMode === 'mobile'
            ? [
                { title: 'Post', className: 'dt-col-mobile-summary' },
                { title: 'Actions', orderable: false, className: 'dt-col-actions' },
            ]
            : [
                { title: 'ID', className: 'dt-col-id' },
                { title: 'Title', className: 'dt-col-primary dt-col-wide' },
                { title: 'Category', className: 'dt-col-nowrap' },
                { title: 'Status', className: 'dt-col-nowrap' },
                { title: 'Author', className: 'dt-col-nowrap' },
                { title: 'Publish Date', className: 'dt-col-nowrap' },
                { title: 'Actions', orderable: false, className: 'dt-col-actions' },
            ],
    }));
}

function bindPostsViewportListener(tableEl) {
    if (postsViewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 767px)');
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
    if (postsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Post #${post.id}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(post.title)}</p>
                    </div>
                    ${statusBadge(post.status)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Category:</span> ${escapeHtml(post.category?.name ?? 'Uncategorized')}</p>
                    <p><span>Author:</span> ${escapeHtml(post.author?.name ?? 'N/A')}</p>
                    <p><span>Publish Date:</span> ${escapeHtml(formatDateTime(post.publish_date) || 'Not scheduled')}</p>
                </div>
            </div>`,
            renderPostActions(post, true),
        ];
    }

    return [
        post.id,
        escapeHtml(post.title),
        escapeHtml(post.category?.name ?? '—'),
        statusBadge(post.status),
        escapeHtml(post.author?.name ?? '—'),
        escapeHtml(formatDateTime(post.publish_date) || '—'),
        actionButtons(post),
    ];
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
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

function buildPostForm(post = null, categoryOptions = []) {
    return buildSwalForm({
        description: post
            ? 'Update the draft content before the next workflow step.'
            : 'Create a new content draft for review and scheduling.',
        fields: [
            { id: 'post-title', label: 'Title *', placeholder: 'Enter post title', value: post?.title ?? '' },
            { id: 'post-body', label: 'Body / rich text content *', type: 'textarea', placeholder: 'Write the main content', value: post?.body ?? '' },
            {
                id: 'post-category-id',
                label: 'Category *',
                type: 'select',
                value: post?.category_id ?? '',
                options: categoryOptions,
            },
            {
                id: 'post-media-file',
                label: 'Featured Image',
                type: 'file',
                accept: 'image/*',
                hint: post?.media_url
                    ? 'Choose a new image only if you want to replace the current upload.'
                    : 'Upload a JPG, PNG, WEBP, or GIF image up to 5MB.',
            },
            { id: 'post-youtube-url', label: 'YouTube URL', placeholder: 'Paste YouTube link', value: post?.youtube_url ?? '' },
        ],
    });
}

async function openPostEditorSwal({ title, post, categoryOptions, confirmButtonText }) {
    let editorInstance = null;

    return Swal.fire(buildSwalOptions({
        title,
        html: buildPostForm(post, categoryOptions),
        showCancelButton: true,
        confirmButtonText,
        didOpen: async () => {
            const textarea = document.getElementById('post-body');

            if (!textarea) {
                return;
            }

            editorInstance = await ClassicEditor.create(textarea, {
                toolbar: [
                    'heading',
                    '|',
                    'bold',
                    'italic',
                    'link',
                    'bulletedList',
                    'numberedList',
                    '|',
                    'undo',
                    'redo',
                ],
            });
        },
        willClose: () => {
            if (editorInstance) {
                editorInstance.destroy();
                editorInstance = null;
            }
        },
        preConfirm: () => {
            const categoryValue = document.getElementById('post-category-id')?.value ?? '';
            const mediaFile = document.getElementById('post-media-file')?.files?.[0] ?? null;
            const titleValue = document.getElementById('post-title')?.value.trim();
            const youtubeValue = document.getElementById('post-youtube-url')?.value.trim() || null;
            const bodyValue = editorInstance ? editorInstance.getData().trim() : document.getElementById('post-body')?.value.trim();

            const payload = {
                title: titleValue,
                body: bodyValue,
                category_id: Number(categoryValue),
                media_file: mediaFile,
                youtube_url: youtubeValue,
            };

            if (!payload.title || !payload.body || !categoryValue || Number.isNaN(payload.category_id) || payload.category_id < 1) {
                Swal.showValidationMessage('Title, body, and category are required.');

                return false;
            }

            return payload;
        },
    }));
}

function syncPostsPageActions() {
    document.querySelector('[data-admin-action="create-post"]')?.classList.toggle('hidden', !canCreateDraft());

    const insightsLink = document.querySelector('a[href="/admin/analytics"]');
    insightsLink?.classList.toggle('hidden', !['super_admin', 'analytics_viewer'].includes(currentUserRole));
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

    if (method !== 'POST') {
        payload.append('_method', method);
    }

    return payload;
}

function getAvailableActions(post) {
    const actions = [];

    if (canEditPost(post)) {
        actions.push({
            handler: 'editPost',
            label: 'Edit',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
        });
    }

    if (canSubmitPost(post)) {
        actions.push({
            handler: 'submitForReview',
            label: 'Submit',
            icon: 'fas fa-paper-plane',
            className: 'admin-table-action-primary',
        });
    }

    if (canRejectPost(post)) {
        actions.push({
            handler: 'rejectPost',
            label: 'Reject',
            icon: 'fas fa-rotate-left',
            iconClass: 'text-[#123a60]',
            className: 'admin-table-action-warning',
        });
    }

    if (canSchedulePost(post)) {
        actions.push({
            handler: 'schedulePost',
            label: 'Approve & Schedule',
            icon: 'fas fa-calendar-check',
        });
        actions.push({
            handler: 'publishNow',
            label: 'Publish now',
            icon: 'fas fa-circle-check',
            className: 'admin-table-action-primary',
        });
    }

    if (currentUserRole === 'publisher' && post.status === 'scheduled') {
        actions.push({
            handler: 'publishNow',
            label: 'Publish now',
            icon: 'fas fa-circle-check',
            className: 'admin-table-action-primary',
        });
    }

    if (canArchivePost(post)) {
        actions.push({
            handler: 'archivePost',
            label: 'Archive',
            icon: 'fas fa-box-archive',
        });
    }

    if (canDeletePost(post)) {
        actions.push({
            handler: 'deletePost',
            label: 'Delete',
            icon: 'fas fa-trash',
            iconClass: 'text-[#CE2028]',
            className: 'admin-table-action-danger',
        });
    }

    return actions;
}

function canCreateDraft() {
    return currentUserRole === 'editor';
}

function canEditPost(post) {
    return (
        (currentUserRole === 'editor' && post.status === 'draft')
        || currentUserRole === 'super_admin'
    );
}

function canSubmitPost(post) {
    return currentUserRole === 'editor'
        && post.status === 'draft';
}

function canRejectPost(post) {
    return currentUserRole === 'publisher'
        && post.status === 'pending_review';
}

function canSchedulePost(post) {
    return currentUserRole === 'publisher'
        && post.status === 'pending_review';
}

function canArchivePost(post) {
    return currentUserRole === 'super_admin'
        && ['published', 'scheduled'].includes(post.status);
}

function canDeletePost(_post) {
    return currentUserRole === 'super_admin';
}

window.Posts = {
    createDraftPrompt: promptCreateDraft,
    editPost: promptEditPost,
    submitForReview: submitPostForReview,
    schedulePost,
    publishNow,
    rejectPost,
    archivePost,
    deletePost,
};
