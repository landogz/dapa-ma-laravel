import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { enhanceAdminFilterSelects } from './shared/filter-select';
import { buildSwalForm, buildSwalOptions, confirmWithSwal } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
import { showErrorToast, showSuccessToast } from './shared/toast';

let notifTable;
let postOptionsCache = null;
let notificationsTableMode = null;
let notificationsViewportBound = false;
let hasBoundFilters = false;
let hasBoundContextMenu = false;
let contextNotificationId = null;
const notificationsCache = new Map();

let topicFilter = '';

const TOPIC_LABELS = {
    all: 'All users',
    android: 'Android',
    ios: 'iOS',
};

export function initNotificationsModule() {
    const tableEl = document.getElementById('notifications-table');
    if (!tableEl) return;

    bindNotificationFilters();
    bindNotificationsContextMenu(tableEl);

    if (notifTable && notificationsTableMode === getNotificationsTableMode()) {
        loadNotifications();
        return;
    }

    initializeNotificationsTable(tableEl).then(() => {
        bindAdminActionTooltipSuppression(tableEl);
        loadNotifications();
    });

    bindNotificationsViewportListener(tableEl);
}

function bindNotificationFilters() {
    if (hasBoundFilters) {
        return;
    }

    hasBoundFilters = true;

    const filterEl = document.getElementById('notifications-topic-filter');
    filterEl?.addEventListener('change', (event) => {
        topicFilter = event.target.value || '';
        loadNotifications();
    });

    enhanceAdminFilterSelects(document.querySelector('.admin-shell-card') || document);
}

export function loadNotifications() {
    if (!notifTable) return;

    axios.get('/admin/notifications', {
        params: {
            per_page: 200,
            ...(topicFilter ? { topic: topicFilter } : {}),
        },
    }).then(({ data }) => {
        notifTable.clear();
        notificationsCache.clear();
        (data.data?.data ?? []).forEach((n) => {
            notificationsCache.set(String(n.id), n);
            notifTable.row.add(buildNotificationRowData(n));
        });
        notifTable.draw();
        bindNotificationActions();
    }).catch(() => {
        showErrorToast('Failed to load notifications.', 'Notifications');
    });
}

export async function sendNotification() {
    const postOptions = await loadPostOptions();

    if (!postOptions) {
        return;
    }

    const formResult = await Swal.fire(buildSwalOptions({
        title: 'Send Notification',
        html: buildSwalForm({
            description: 'Delivers an in-app inbox alert to the selected mobile audience. Device push is skipped until FCM is configured.',
            fields: [
                { id: 'notif-title', label: 'Title *', placeholder: 'Enter notification title' },
                {
                    id: 'notif-body',
                    label: 'Message body *',
                    type: 'textarea',
                    placeholder: 'Write a short plain-text message',
                },
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
        confirmButtonText: 'Continue',
        didOpen: (popup) => {
            enhanceAdminFilterSelects(popup);
        },
        preConfirm: () => {
            const title = document.getElementById('notif-title')?.value.trim();
            const topic = document.getElementById('notif-topic')?.value || 'all';
            const postId = document.getElementById('notif-post-id')?.value;
            const bodyValue = document.getElementById('notif-body')?.value.trim();

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
    }));

    if (!formResult.isConfirmed || !formResult.value) {
        return;
    }

    const payload = formResult.value;
    const audienceLabel = TOPIC_LABELS[payload.topic] || 'all users';

    let recipientCount = 0;
    try {
        const { data } = await axios.get('/admin/notifications/audience-count', {
            params: { topic: payload.topic },
        });
        recipientCount = Number(data.data?.recipient_count ?? 0);
    } catch {
        showErrorToast('Unable to estimate audience size.', 'Notifications');
        return;
    }

    if (recipientCount < 1) {
        showErrorToast(
            payload.topic === 'android' || payload.topic === 'ios'
                ? `No ${audienceLabel.toLowerCase()} found. Choose All users, or wait until devices register.`
                : 'No matching app users found for this audience.',
            'Notifications',
        );
        return;
    }

    const confirmed = await confirmWithSwal({
        icon: 'question',
        title: 'Send in-app notification?',
        text: `Send “${payload.title}” to ${recipientCount} ${audienceLabel.toLowerCase()}?`,
        confirmButtonText: 'Send notification',
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const { data, status } = await axios.post('/admin/notifications/send', payload);
        const message = data.message ?? 'In-app notification sent.';

        if (data.status === false || status >= 400) {
            showErrorToast(message, 'Notifications');
        } else {
            showSuccessToast(message, 'Notification sent');
        }

        loadNotifications();
    } catch ({ response }) {
        showErrorToast(
            response?.data?.message ?? 'Notification failed.',
            'Notifications',
        );
        loadNotifications();
    }
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
        showErrorToast(
            response?.data?.message ?? 'Failed to load post options.',
            'Posts',
        );

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
            ? [{ title: 'Notification', className: 'dt-col-mobile-summary', orderable: false }]
            : [
                { title: 'Sent', className: 'dt-col-nowrap' },
                { title: 'Title', className: 'dt-col-primary dt-col-wide' },
                { title: 'Preview', className: 'dt-col-wide' },
                { title: 'Audience', className: 'dt-col-nowrap' },
                { title: 'Recipients', className: 'dt-col-nowrap' },
                { title: 'Sent By', className: 'dt-col-nowrap' },
                { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
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
        bindAdminActionTooltipSuppression(tableEl);
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
    const topicKey = notification.topic ?? 'all';
    const topicLabel = TOPIC_LABELS[topicKey] || topicKey;
    const topic = `<span class="admin-status-badge bg-[#055498]/10 text-[#055498]">${escapeHtml(topicLabel)}</span>`;
    const sender = notification.sender?.name
        ? escapeHtml(notification.sender.name)
        : '<span class="admin-empty-badge">System</span>';
    const sentAt = notification.sent_at
        ? escapeHtml(formatDateTime(notification.sent_at))
        : '<span class="admin-empty-badge">N/A</span>';
    const preview = escapeHtml(notification.preview || notification.body || '—');
    const recipients = Number(notification.inbox_count ?? notification.recipient_count ?? 0);
    const rowAttrs = `data-notif-row-id="${notification.id}"`;

    if (notificationsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card" ${rowAttrs}>
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${sentAt}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(notification.title)}</p>
                    </div>
                    ${topic}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Preview:</span> ${preview}</p>
                    <p><span>Recipients:</span> ${recipients}</p>
                    <p><span>Sent By:</span> ${sender}</p>
                </div>
                <div class="admin-table-mobile-actions">${buildActionButtons(notification.id, true)}</div>
            </div>`,
        ];
    }

    return [
        `<span ${rowAttrs}>${sentAt}</span>`,
        escapeHtml(notification.title),
        preview,
        topic,
        String(recipients),
        sender,
        buildActionButtons(notification.id, false),
    ];
}

function buildActionButtons(notificationId, isMobile) {
    return buildAdminActionButtons([
        {
            tooltip: 'View notification',
            icon: 'fas fa-eye',
            className: 'admin-table-action-primary',
            attrs: `data-notif-view="${notificationId}"`,
            label: 'View',
        },
        {
            tooltip: 'Delete history',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `data-notif-delete="${notificationId}"`,
            label: 'Delete',
        },
    ], { isMobile: Boolean(isMobile), nowrap: true });
}

function bindNotificationActions() {
    document.querySelectorAll('[data-notif-view]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            viewNotification(button.getAttribute('data-notif-view'));
        });
    });

    document.querySelectorAll('[data-notif-delete]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            deleteNotification(button.getAttribute('data-notif-delete'));
        });
    });
}

function bindNotificationsContextMenu(tableElement) {
    if (hasBoundContextMenu) {
        return;
    }

    hasBoundContextMenu = true;
    const menu = document.getElementById('notifications-context-menu');
    if (!menu) {
        return;
    }

    const hideMenu = () => {
        menu.classList.add('hidden');
        menu.setAttribute('hidden', 'hidden');
        contextNotificationId = null;
    };

    tableElement.addEventListener('contextmenu', (event) => {
        const row = event.target.closest('[data-notif-row-id], tr');
        if (!row) {
            return;
        }

        const notificationId = row.getAttribute('data-notif-row-id')
            || row.querySelector('[data-notif-row-id]')?.getAttribute('data-notif-row-id')
            || row.querySelector('[data-notif-view]')?.getAttribute('data-notif-view');

        if (!notificationId || !notificationsCache.has(String(notificationId))) {
            return;
        }

        event.preventDefault();
        contextNotificationId = String(notificationId);
        menu.classList.remove('hidden');
        menu.removeAttribute('hidden');
        menu.style.left = `${Math.min(event.clientX, window.innerWidth - 220)}px`;
        menu.style.top = `${Math.min(event.clientY, window.innerHeight - 120)}px`;
    });

    menu.querySelectorAll('[data-notif-context]').forEach((button) => {
        button.addEventListener('click', async () => {
            const action = button.getAttribute('data-notif-context');
            const notificationId = contextNotificationId;
            hideMenu();
            if (!notificationId) return;

            if (action === 'view') {
                viewNotification(notificationId);
            } else if (action === 'delete') {
                await deleteNotification(notificationId);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!menu.contains(event.target)) {
            hideMenu();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hideMenu();
        }
    });

    hideMenu();
}

async function viewNotification(notificationId) {
    let notification = notificationsCache.get(String(notificationId));

    try {
        const { data } = await axios.get(`/admin/notifications/${notificationId}`);
        notification = data.data ?? notification;
        if (notification) {
            notificationsCache.set(String(notification.id), notification);
        }
    } catch {
        // Fall back to cached row if show fails.
    }

    if (!notification) {
        showErrorToast('Notification not found.', 'Notifications');
        return;
    }

    const topicLabel = TOPIC_LABELS[notification.topic] || notification.topic || 'All users';
    const sender = notification.sender?.name || 'System';
    const linkedPost = notification.post?.title || 'None';
    const recipients = Number(notification.inbox_count ?? notification.recipient_count ?? 0);

    Swal.fire(buildSwalOptions({
        title: notification.title || 'Notification',
        subtitle: `${topicLabel} · ${recipients} recipient(s)`,
        html: `
            <div class="admin-swal-form text-left">
                <div class="admin-swal-fields">
                    <p class="text-sm text-slate-600"><strong>Sent:</strong> ${escapeHtml(formatDateTime(notification.sent_at) || '—')}</p>
                    <p class="text-sm text-slate-600"><strong>Sent by:</strong> ${escapeHtml(sender)}</p>
                    <p class="text-sm text-slate-600"><strong>Related post:</strong> ${escapeHtml(linkedPost)}</p>
                    <div class="diary-entry-preview rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-800 whitespace-pre-wrap">
                        ${escapeHtml(notification.body || '')}
                    </div>
                </div>
            </div>
        `,
        showCancelButton: false,
        confirmButtonText: 'Close',
    }));
}

async function deleteNotification(notificationId) {
    const notification = notificationsCache.get(String(notificationId));
    const title = notification?.title || 'this campaign';

    const result = await confirmWithSwal({
        icon: 'warning',
        title: 'Delete campaign history?',
        text: `Remove “${title}” from admin history? User inboxes will not be changed.`,
        confirmButtonText: 'Delete history',
        danger: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/notifications/${notificationId}`);
        showSuccessToast(data.message ?? 'Campaign history deleted.');
        loadNotifications();
    } catch ({ response }) {
        showErrorToast(
            response?.data?.message ?? 'Unable to delete campaign history.',
            'Notifications',
        );
    }
}

function escapeHtml(value) {
    return String(value ?? '')
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

window.Notifications = { send: sendNotification, reload: loadNotifications };
