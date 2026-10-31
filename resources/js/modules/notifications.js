import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { applyAdminFilterTabCounts, bindAdminFilterTabs, countByKey } from './shared/filter-tabs';
import { enhanceAdminFilterSelects } from './shared/filter-select';
import { buildSwalForm, buildSwalOptions, confirmWithSwal } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
import {
    attachWorkflowWindowHandlers,
    buildWorkflowTableActions,
    ensureWorkflowStatusFilter,
    filterByWorkflowStatus,
    guardWorkflowCreateAction,
    openItemFromQuery,
    renderReviewNotesBanner,
    syncWorkflowCreateButton,
    workflowStatusBadge,
} from './shared/content-workflow';
import { showErrorToast, showSuccessToast } from './shared/toast';

let notifTable;
let linkOptionsCache = {};
let notificationsTableMode = null;
let notificationsViewportBound = false;
let hasBoundFilters = false;
const notificationsCache = new Map();

let topicFilter = '';
let topicCounts = null;
let workflowStatusFilter = '';

const TOPIC_LABELS = {
    all: 'All users',
    android: 'Android',
    ios: 'iOS',
};

const LINK_TYPE_LABELS = {
    post: 'Post',
    hope_event: 'Hope Event',
    contest: 'Contest',
    iec_material: 'IEC Material',
    rehab_center: 'Treatment Rehabilitation Center',
    training: 'Training',
    care_support: 'Care Support',
    hope_directory: 'Hope Directory',
};

const LINK_TYPE_OPTIONS = [
    { value: '', label: 'None' },
    ...Object.entries(LINK_TYPE_LABELS).map(([value, label]) => ({ value, label })),
];

const LINK_OPTION_LOADERS = {
    post: {
        url: '/admin/posts/options',
        placeholder: 'Select a post',
        mapRow: (row) => ({ value: String(row.id), label: row.title }),
    },
    hope_event: {
        url: '/admin/hope-events/options',
        placeholder: 'Select a hope event',
        mapRow: (row) => ({
            value: String(row.id),
            label: row.is_active === false ? `${row.title} (inactive)` : row.title,
        }),
    },
    contest: {
        url: '/admin/contests',
        placeholder: 'Select a contest',
        mapRow: (row) => ({ value: String(row.id), label: row.title || row.name || `#${row.id}` }),
        listPath: (data) => data.data?.data ?? data.data ?? [],
    },
    iec_material: {
        url: '/admin/iec-materials',
        placeholder: 'Select an IEC material',
        mapRow: (row) => ({ value: String(row.id), label: row.title || `#${row.id}` }),
        listPath: (data) => data.data?.data ?? data.data ?? [],
    },
    rehab_center: {
        url: '/admin/rehab-centers',
        placeholder: 'Select a center',
        mapRow: (row) => ({ value: String(row.id), label: row.name || `#${row.id}` }),
        listPath: (data) => data.data?.data ?? data.data ?? [],
    },
    training: {
        url: '/admin/trainings',
        placeholder: 'Select a training',
        mapRow: (row) => ({ value: String(row.id), label: row.title || row.name || `#${row.id}` }),
        listPath: (data) => data.data?.data ?? data.data ?? [],
    },
    care_support: {
        url: '/admin/care-support-resources',
        placeholder: 'Select a Care Support resource',
        mapRow: (row) => ({ value: String(row.id), label: row.title_en || row.title || `#${row.id}` }),
        listPath: (data) => data.data?.data ?? data.data ?? [],
    },
    hope_directory: {
        url: '/admin/hope-directory',
        placeholder: 'Select a directory org',
        mapRow: (row) => ({ value: String(row.id), label: row.name || `#${row.id}` }),
        listPath: (data) => data.data?.data ?? data.data ?? [],
    },
};

export function initNotificationsModule() {
    const tableEl = document.getElementById('notifications-table');
    if (!tableEl) return;

    bindNotificationFilters();
    syncWorkflowCreateButton('[data-admin-action="send-notification"]');

    if (notifTable && notificationsTableMode === getNotificationsTableMode()) {
        ensureWorkflowStatusFilter({
            selectMount: document.querySelector('#notifications-table')?.closest('.admin-table-shell'),
            onChange: (value) => {
                workflowStatusFilter = value;
                loadNotifications();
            },
        });
        openItemFromQuery('item', (id) => viewNotification(id));
        loadNotifications();
        return;
    }

    initializeNotificationsTable(tableEl).then(() => {
        bindAdminActionTooltipSuppression(tableEl);
        attachWorkflowWindowHandlers('Notifications', '/admin/notifications', () => loadNotifications());
        ensureWorkflowStatusFilter({
            selectMount: tableEl.closest('.admin-table-shell'),
            onChange: (value) => {
                workflowStatusFilter = value;
                loadNotifications();
            },
        });
        openItemFromQuery('item', (id) => viewNotification(id));
        loadNotifications();
    });

    bindNotificationsViewportListener(tableEl);
}

function bindNotificationFilters() {
    if (hasBoundFilters) {
        return;
    }

    hasBoundFilters = true;

    bindAdminFilterTabs(document.getElementById('notifications-topic-tabs'), (value) => {
        topicFilter = value;
        loadNotifications();
    });
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
        const rows = filterByWorkflowStatus(data.data?.data ?? [], workflowStatusFilter);
        rows.forEach((n) => {
            notificationsCache.set(String(n.id), n);
            notifTable.row.add(buildNotificationRowData(n));
        });
        notifTable.draw();
        bindNotificationActions();
        const cachedRows = [...notificationsCache.values()];
        if (!topicFilter) {
            topicCounts = countByKey(cachedRows, (row) => row.topic || '');
        }
        applyAdminFilterTabCounts(document.getElementById('notifications-topic-tabs'), topicCounts || {});
    }).catch(() => {
        showErrorToast('Failed to load notifications.', 'Notifications');
    });
}

export async function sendNotification() {
    if (!guardWorkflowCreateAction(() => true)) {
        return;
    }

    const formResult = await Swal.fire(buildSwalOptions({
        title: 'Send Notification',
        html: buildSwalForm({
            description: 'Delivers an in-app inbox alert to the selected mobile audience. Link any published content — posts, events, contests, IEC, Care Support, and more. Device push is skipped until FCM is configured.',
            fields: [
                { id: 'notif-title', label: 'Title *', placeholder: 'Enter notification title' },
                {
                    id: 'notif-body',
                    label: 'Message body *',
                    type: 'textarea',
                    placeholder: 'Write a short plain-text message',
                },
                {
                    id: 'notif-link-type',
                    label: 'Link to',
                    type: 'select',
                    value: '',
                    options: LINK_TYPE_OPTIONS,
                },
                {
                    id: 'notif-link-id',
                    label: 'Linked item',
                    type: 'select',
                    value: '',
                    options: [{ value: '', label: 'Select an item' }],
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
            const typeSelect = popup.querySelector('#notif-link-type');
            const linkSelect = popup.querySelector('#notif-link-id');
            const linkWrap = linkSelect?.closest('.admin-swal-field') || linkSelect?.parentElement;

            const syncLinkTarget = async () => {
                const type = typeSelect?.value || '';
                if (!linkSelect) return;

                if (!type) {
                    linkSelect.innerHTML = '<option value="">Select an item</option>';
                    linkSelect.value = '';
                    linkSelect.disabled = true;
                    if (linkWrap) linkWrap.classList.add('hidden');
                    enhanceAdminFilterSelects(popup);
                    return;
                }

                linkSelect.disabled = true;
                linkSelect.innerHTML = '<option value="">Loading…</option>';
                if (linkWrap) linkWrap.classList.remove('hidden');

                const source = await loadLinkOptions(type);
                if (!source) {
                    linkSelect.innerHTML = '<option value="">Unable to load items</option>';
                    enhanceAdminFilterSelects(popup);
                    return;
                }

                linkSelect.innerHTML = source.map((option) => (
                    `<option value="${escapeHtml(option.value)}">${escapeHtml(option.label)}</option>`
                )).join('');
                linkSelect.disabled = false;
                enhanceAdminFilterSelects(popup);
            };

            typeSelect?.addEventListener('change', () => {
                void syncLinkTarget();
            });
            void syncLinkTarget();
        },
        preConfirm: () => {
            const title = document.getElementById('notif-title')?.value.trim();
            const topic = document.getElementById('notif-topic')?.value || 'all';
            const linkType = document.getElementById('notif-link-type')?.value || '';
            const linkId = document.getElementById('notif-link-id')?.value;
            const bodyValue = document.getElementById('notif-body')?.value.trim();

            if (!title || !bodyValue) {
                Swal.showValidationMessage('Title and body are required.');
                return false;
            }

            if (linkType && !linkId) {
                Swal.showValidationMessage('Choose a linked item, or set Link to None.');
                return false;
            }

            return {
                title,
                body: bodyValue,
                topic,
                link_type: linkType || null,
                link_id: linkType && linkId ? Number(linkId) : null,
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

async function loadLinkOptions(linkType) {
    const config = LINK_OPTION_LOADERS[linkType];
    if (!config) {
        return null;
    }

    if (linkOptionsCache[linkType]?.length > 1) {
        return linkOptionsCache[linkType];
    }

    try {
        const { data } = await axios.get(config.url, { params: { per_page: 200 } });
        const rows = typeof config.listPath === 'function'
            ? config.listPath(data)
            : (data.data ?? []);
        const list = Array.isArray(rows) ? rows : [];

        linkOptionsCache[linkType] = [
            { value: '', label: config.placeholder },
            ...list.map((row) => config.mapRow(row)),
        ];

        return linkOptionsCache[linkType];
    } catch ({ response }) {
        showErrorToast(
            response?.data?.message ?? `Failed to load ${LINK_TYPE_LABELS[linkType] || 'link'} options.`,
            'Notifications',
        );

        return null;
    }
}

function getNotificationsTableMode() {
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
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
                { title: 'Link to', className: 'dt-col-wide' },
                { title: 'Audience', className: 'dt-col-nowrap' },
                { title: 'Recipients', className: 'dt-col-nowrap' },
                { title: 'Sent By', className: 'dt-col-nowrap' },
                { title: 'Workflow', className: 'dt-col-nowrap' },
                { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
            ],
    }));
}

function bindNotificationsViewportListener(tableEl) {
    if (notificationsViewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 1023px)');
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

function linkedTargetCell(notification) {
    const linkType = notification.link_type
        || (notification.post_id || notification.post ? 'post' : null);
    const typeLabel = notification.link_type_label
        || LINK_TYPE_LABELS[linkType]
        || null;
    const target = notification.link_target;
    const linkId = target?.id
        ?? notification.link_id
        ?? notification.hope_event?.id
        ?? notification.post?.id
        ?? notification.post_id;
    const title = target?.title
        ?? notification.hope_event?.title
        ?? notification.post?.title;
    const href = target?.admin_url
        || (linkType === 'hope_event' && linkId
            ? `/admin/hope-events?event=${encodeURIComponent(linkId)}`
            : null)
        || (linkType === 'post' && linkId
            ? `/admin/posts?post=${encodeURIComponent(linkId)}`
            : null);

    if (!linkType || !typeLabel || !linkId || !title) {
        return '<span class="admin-empty-badge">None</span>';
    }

    const label = escapeHtml(typeLabel);
    const titleHtml = escapeHtml(title);
    if (!href) {
        return `<span class="inline-flex flex-wrap items-center gap-1.5">
            <span class="admin-status-badge bg-[#055498]/10 text-[#055498]">${label}</span>
            <span class="font-semibold text-[#123a60]">${titleHtml}</span>
        </span>`;
    }

    return `<span class="inline-flex flex-wrap items-center gap-1.5">
        <span class="admin-status-badge bg-[#055498]/10 text-[#055498]">${label}</span>
        <a
            href="${escapeHtml(href)}"
            class="font-semibold text-[#055498] underline-offset-2 hover:underline"
            title="Open linked ${label.toLowerCase()}"
        >${titleHtml}</a>
    </span>`;
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
    const linkedTarget = linkedTargetCell(notification);
    const recipients = Number(notification.inbox_count ?? notification.recipient_count ?? 0);
    const rowAttrs = `data-notif-row-id="${notification.id}"`;
    const workflowBadge = workflowStatusBadge(notification.workflow_status);

    if (notificationsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card" ${rowAttrs}>
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${sentAt}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(notification.title)}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">${topic}${workflowBadge}</div>
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Preview:</span> ${preview}</p>
                    <p><span>Link to:</span> ${linkedTarget}</p>
                    <p><span>Recipients:</span> ${recipients}</p>
                    <p><span>Sent By:</span> ${sender}</p>
                </div>
                <div class="admin-table-mobile-actions">${buildActionButtons(notification, true)}</div>
            </div>`,
        ];
    }

    return [
        `<span ${rowAttrs}>${sentAt}</span>`,
        escapeHtml(notification.title),
        preview,
        linkedTarget,
        topic,
        String(recipients),
        sender,
        workflowBadge,
        buildActionButtons(notification, false),
    ];
}

function buildActionButtons(notification, isMobile) {
    const viewAction = {
        tooltip: 'View notification',
        icon: 'fas fa-eye',
        className: 'admin-table-action-primary',
        attrs: `data-notif-view="${notification.id}"`,
        label: 'View',
    };
    const workflowActions = buildWorkflowTableActions(notification, 'Notifications', {
        basePath: '/admin/notifications',
        deleteMethod: 'deleteHistory',
    });

    return buildAdminActionButtons([viewAction, ...workflowActions], {
        isMobile: Boolean(isMobile),
        nowrap: true,
    });
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
    const recipients = Number(notification.inbox_count ?? notification.recipient_count ?? 0);
    const linkedHtml = linkedTargetCell(notification);

    Swal.fire(buildSwalOptions({
        title: notification.title || 'Notification',
        subtitle: `${topicLabel} · ${recipients} recipient(s)`,
        html: `
            <div class="admin-swal-form text-left">
                ${renderReviewNotesBanner(notification)}
                <div class="admin-swal-fields">
                    <p class="text-sm text-slate-600"><strong>Sent:</strong> ${escapeHtml(formatDateTime(notification.sent_at) || '—')}</p>
                    <p class="text-sm text-slate-600"><strong>Sent by:</strong> ${escapeHtml(sender)}</p>
                    <p class="text-sm text-slate-600"><strong>Link to:</strong> ${linkedHtml}</p>
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

window.Notifications = {
    send: sendNotification,
    reload: loadNotifications,
    edit: (id) => viewNotification(id),
    deleteHistory: deleteNotification,
};
