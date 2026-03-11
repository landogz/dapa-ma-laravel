import axios from 'axios';
import Swal from 'sweetalert2';
import ClassicEditor from '@ckeditor/ckeditor5-build-classic';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { buildSwalForm, buildSwalOptions } from './shared/swal-forms';

let notifTable;
let postOptionsCache = null;
let notificationsTableMode = null;
let notificationsViewportBound = false;

export function initNotificationsModule() {
    const tableEl = document.getElementById('notifications-table');
    if (!tableEl) return;

    if (notifTable && notificationsTableMode === getNotificationsTableMode()) {
        loadNotifications();
        return;
    }

    initializeNotificationsTable(tableEl).then(() => {
        loadNotifications();
    });

    bindNotificationsViewportListener(tableEl);
}

export function loadNotifications() {
    if (!notifTable) return;
    axios.get('/admin/notifications').then(({ data }) => {
        notifTable.clear();
        (data.data?.data ?? []).forEach((n) => {
            notifTable.row.add(buildNotificationRowData(n));
        });
        notifTable.draw();
    }).catch(() => {
        Swal.fire({ icon: 'error', title: 'Notifications', text: 'Failed to load notifications.' });
    });
}

export async function sendNotification() {
    const postOptions = await loadPostOptions();

    if (!postOptions) {
        return;
    }

    let editorInstance = null;

    Swal.fire(buildSwalOptions({
        title: 'Send Push Notification',
        html: buildSwalForm({
            description: 'Prepare a clean push message for the selected audience segment.',
            fields: [
                { id: 'notif-title', label: 'Title *', placeholder: 'Enter notification title' },
                { id: 'notif-body', label: 'Message body *', type: 'textarea', placeholder: 'Write the push message' },
                {
                    id: 'notif-post-id',
                    label: 'Related Post',
                    type: 'select',
                    value: '',
                    options: postOptions,
                },
                {
                    id: 'notif-topic',
                    label: 'Audience *',
                    type: 'select',
                    value: 'all',
                    options: [
                        { value: 'all', label: 'All Users' },
                        { value: 'android', label: 'Android Users' },
                        { value: 'ios', label: 'iOS Users' },
                    ],
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Send',
        didOpen: async () => {
            const textarea = document.getElementById('notif-body');

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
            const title = document.getElementById('notif-title')?.value.trim();
            const topic = document.getElementById('notif-topic')?.value;
            const postId = document.getElementById('notif-post-id')?.value;
            const bodyValue = editorInstance ? editorInstance.getData().trim() : document.getElementById('notif-body')?.value.trim();

            if (!title || !bodyValue) {
                Swal.showValidationMessage('Title and body are required.');
                return false;
            }

            return {
                title,
                body: bodyValue,
                topic,
                post_id: postId ? Number(postId) : null,
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;
        axios.post('/admin/notifications/send', value)
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Sent!', text: data.message });
                loadNotifications();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Notification failed.' });
            });
    });
}

async function loadPostOptions() {
    if (postOptionsCache && postOptionsCache.length > 1) {
        return postOptionsCache;
    }

    try {
        const { data } = await axios.get('/admin/posts/options');
        const posts = data.data ?? [];

        postOptionsCache = [
            { value: '', label: 'No linked post' },
            ...posts.map((post) => ({
                value: String(post.id),
                label: post.title,
            })),
        ];

        return postOptionsCache;
    } catch ({ response }) {
        await Swal.fire({
            icon: 'error',
            title: 'Posts',
            text: response?.data?.message ?? 'Failed to load post options.',
            confirmButtonColor: '#CE2028',
        });

        return null;
    }
}

function getNotificationsTableMode() {
    return window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop';
}

async function initializeNotificationsTable(tableEl) {
    const nextMode = getNotificationsTableMode();

    if (notifTable) {
        notifTable.destroy();
        tableEl.innerHTML = '';
    }

    notificationsTableMode = nextMode;
    notifTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search notifications:',
        searchPlaceholder: 'Search notifications',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ notifications',
        pageLength: nextMode === 'mobile' ? 5 : 10,
        scrollX: nextMode !== 'mobile',
        scrollCollapse: nextMode !== 'mobile',
        columns: nextMode === 'mobile'
            ? [{ title: 'Notification', className: 'dt-col-mobile-summary' }]
            : [
                { title: 'ID', className: 'dt-col-id' },
                { title: 'Title', className: 'dt-col-primary dt-col-wide' },
                { title: 'Post', className: 'dt-col-wide' },
                { title: 'Topic', className: 'dt-col-nowrap' },
                { title: 'Sent By', className: 'dt-col-nowrap' },
                { title: 'Sent At', className: 'dt-col-nowrap' },
            ],
    }));
}

function bindNotificationsViewportListener(tableEl) {
    if (notificationsViewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 767px)');
    const onChange = async () => {
        const nextMode = getNotificationsTableMode();

        if (nextMode === notificationsTableMode) {
            return;
        }

        await initializeNotificationsTable(tableEl);
        loadNotifications();
    };

    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', onChange);
    } else {
        query.addListener(onChange);
    }

    notificationsViewportBound = true;
}

function buildNotificationRowData(notification) {
    const topic = `<span class="admin-status-badge bg-[#055498]/10 text-[#055498]">${escapeHtml(notification.topic ?? 'all')}</span>`;
    const linkedPost = notification.post?.title
        ? escapeHtml(notification.post.title)
        : '<span class="admin-empty-badge">No linked post</span>';
    const sender = notification.sender?.name
        ? escapeHtml(notification.sender.name)
        : '<span class="admin-empty-badge">System</span>';
    const sentAt = notification.sent_at
        ? escapeHtml(formatDateTime(notification.sent_at))
        : '<span class="admin-empty-badge">N/A</span>';

    if (notificationsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Notification #${notification.id}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(notification.title)}</p>
                    </div>
                    ${topic}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Post:</span> ${linkedPost}</p>
                    <p><span>Sent By:</span> ${sender}</p>
                    <p><span>Sent At:</span> ${sentAt}</p>
                </div>
            </div>`,
        ];
    }

    return [
        notification.id,
        escapeHtml(notification.title),
        linkedPost,
        topic,
        sender,
        sentAt,
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

window.Notifications = { send: sendNotification };
