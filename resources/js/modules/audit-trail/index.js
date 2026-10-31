import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import {
    applyAdminFilterTabCounts,
    bindAdminFilterTabs,
    renderAdminFilterTabs,
    setAdminFilterTab,
} from '../shared/filter-tabs';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let auditTable;
let auditTableMode = null;
let viewportBound = false;
let filtersBound = false;
let actionsBound = false;
let searchBound = false;
let searchDebounceTimer = null;
let loadRequestId = 0;

let activeCategory = 'all';
let activeAction = 'all';
let activeSearch = '';
let activeFrom = '';
let activeTo = '';

let cachedSummary = {
    today: 0,
    this_week: 0,
    unique_actors: 0,
    mutations: 0,
    categories: {},
    actions: {},
};

const CATEGORY_TABS = [
    { value: 'all', label: 'All', icon: 'fas fa-layer-group' },
    { value: 'auth', label: 'Auth', icon: 'fas fa-shield-halved' },
    { value: 'users', label: 'Users', icon: 'fas fa-users' },
    { value: 'posts', label: 'Posts', icon: 'fas fa-newspaper' },
    { value: 'notifications', label: 'Notifications', icon: 'fas fa-bell' },
    { value: 'settings', label: 'Settings', icon: 'fas fa-gear' },
    { value: 'contests', label: 'Contests', icon: 'fas fa-trophy' },
    { value: 'word-blocking', label: 'Word Blocking', icon: 'fas fa-ban' },
    { value: 'rehab-centers', label: 'Treatment Rehabilitation Center', icon: 'fas fa-hospital' },
    { value: 'care-toolkit', label: 'Care Toolkit', icon: 'fas fa-heart' },
    { value: 'diary', label: 'Journal', icon: 'fas fa-book' },
];

const ACTION_TABS = [
    { value: 'all', label: 'All', icon: 'fas fa-list' },
    { value: 'create', label: 'Create', icon: 'fas fa-plus' },
    { value: 'update', label: 'Update', icon: 'fas fa-pen' },
    { value: 'delete', label: 'Delete', icon: 'fas fa-trash' },
    { value: 'security', label: 'Security', icon: 'fas fa-lock' },
];

const CATEGORY_TONES = {
    auth: 'navy',
    users: 'blue',
    posts: 'blue',
    notifications: 'gold',
    settings: 'slate',
    contests: 'gold',
    'word-blocking': 'danger',
    'rehab-centers': 'blue',
    'care-toolkit': 'blue',
    diary: 'slate',
};

const ACTION_TONES = {
    created: 'success',
    updated: 'blue',
    deleted: 'danger',
    login: 'success',
    login_failed: 'danger',
    logout: 'slate',
    password_changed: 'gold',
    force_password: 'gold',
    suspended: 'danger',
    activated: 'success',
    role_changed: 'navy',
    published: 'success',
    archived: 'slate',
    sent: 'blue',
};

export function initAuditTrailModule() {
    const tableElement = document.getElementById('audit-trail-table');
    if (!tableElement) {
        return;
    }

    bindFilters();
    bindExport();

    if (auditTable && auditTableMode === getTableMode()) {
        loadAuditLogs();
        return;
    }

    initializeTable(tableElement).then(() => {
        bindAdminActionTooltipSuppression(tableElement);
        bindTableActions(tableElement);
        bindSearch();
        loadAuditLogs();
    });

    bindViewportListener(tableElement);
}

function bindFilters() {
    if (filtersBound) {
        return;
    }

    const categoryRoot = document.getElementById('audit-category-tabs');
    const actionRoot = document.getElementById('audit-action-tabs');
    if (!categoryRoot || !actionRoot) {
        return;
    }

    filtersBound = true;

    renderAdminFilterTabs(
        categoryRoot,
        CATEGORY_TABS.map((tab) => ({ ...tab, count: 0, hint: tab.label })),
    );
    renderAdminFilterTabs(
        actionRoot,
        ACTION_TABS.map((tab) => ({ ...tab, count: 0, hint: tab.label })),
    );

    setAdminFilterTab(categoryRoot, activeCategory);
    setAdminFilterTab(actionRoot, activeAction);

    bindAdminFilterTabs(categoryRoot, (value) => {
        activeCategory = value || 'all';
        loadAuditLogs();
    });

    bindAdminFilterTabs(actionRoot, (value) => {
        activeAction = value || 'all';
        loadAuditLogs();
    });

    const fromInput = document.getElementById('audit-from');
    const toInput = document.getElementById('audit-to');
    const clearDates = document.getElementById('audit-clear-dates');

    fromInput?.addEventListener('change', () => {
        activeFrom = fromInput.value || '';
        loadAuditLogs();
    });
    toInput?.addEventListener('change', () => {
        activeTo = toInput.value || '';
        loadAuditLogs();
    });
    clearDates?.addEventListener('click', () => {
        activeFrom = '';
        activeTo = '';
        if (fromInput) {
            fromInput.value = '';
        }
        if (toInput) {
            toInput.value = '';
        }
        loadAuditLogs();
    });
}

function bindExport() {
    document.querySelector('[data-admin-action="export-audit-trail"]')?.addEventListener('click', () => {
        exportCsv();
    });
}

function currentParams() {
    return {
        per_page: 100,
        category: activeCategory !== 'all' ? activeCategory : undefined,
        action: activeAction !== 'all' ? activeAction : undefined,
        q: activeSearch || undefined,
        from: activeFrom || undefined,
        to: activeTo || undefined,
    };
}

export function loadAuditLogs() {
    if (!auditTable) {
        return;
    }

    const requestId = ++loadRequestId;

    axios
        .get('/admin/audit-logs', { params: currentParams() })
        .then((response) => {
            if (requestId !== loadRequestId) {
                return;
            }

            const page = response.data.data;
            const rows = Array.isArray(page?.data) ? page.data : (Array.isArray(page) ? page : []);
            cachedSummary = {
                today: 0,
                this_week: 0,
                unique_actors: 0,
                mutations: 0,
                categories: {},
                actions: {},
                ...(response.data.summary ?? {}),
            };

            renderMetrics();
            updateFilterCounts();
            renderRows(rows);
        })
        .catch((error) => {
            if (requestId !== loadRequestId) {
                return;
            }
            showErrorToast(error.response?.data?.message ?? 'Unable to load audit trail.');
        });
}

function renderMetrics() {
    const map = {
        today: cachedSummary.today,
        this_week: cachedSummary.this_week,
        unique_actors: cachedSummary.unique_actors,
        mutations: cachedSummary.mutations,
    };

    Object.entries(map).forEach(([key, value]) => {
        const el = document.querySelector(`[data-audit-metric="${key}"]`);
        if (el) {
            el.textContent = formatCount(value);
        }
    });
}

function updateFilterCounts() {
    const categoryCounts = { all: cachedSummary.mutations || 0, ...(cachedSummary.categories || {}) };
    applyAdminFilterTabCounts(document.getElementById('audit-category-tabs'), categoryCounts);

    const actions = cachedSummary.actions || {};
    const actionCounts = {
        all: cachedSummary.mutations || 0,
        create: actions.created || 0,
        update: (actions.updated || 0)
            + (actions.published || 0)
            + (actions.scheduled || 0)
            + (actions.submitted || 0)
            + (actions.archived || 0)
            + (actions.rejected || 0)
            + (actions.sent || 0),
        delete: actions.deleted || 0,
        security: (actions.login || 0)
            + (actions.login_failed || 0)
            + (actions.logout || 0)
            + (actions.password_changed || 0)
            + (actions.force_password || 0)
            + (actions.suspended || 0)
            + (actions.activated || 0)
            + (actions.role_changed || 0),
    };
    applyAdminFilterTabCounts(document.getElementById('audit-action-tabs'), actionCounts);
}

function renderRows(rows) {
    if (!auditTable) {
        return;
    }

    auditTable.clear();
    rows.forEach((row) => auditTable.row.add(buildRowData(row)));
    auditTable.draw();
}

function getTableMode() {
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
}

async function initializeTable(tableElement) {
    const nextMode = getTableMode();
    if (auditTable && auditTableMode === nextMode) {
        return;
    }

    if (auditTable) {
        auditTable.destroy();
        tableElement.innerHTML = '';
        searchBound = false;
    }

    auditTableMode = nextMode;
    auditTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: buildColumns(nextMode),
            searchPlaceholder: 'Search actor, subject, action…',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ events',
            pageLength: nextMode === 'mobile' ? 8 : 25,
            scrollX: nextMode !== 'mobile',
            scrollCollapse: nextMode !== 'mobile',
            order: [],
        }),
    );
}

function bindViewportListener(tableElement) {
    if (viewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 1023px)');
    const onChange = async () => {
        if (getTableMode() === auditTableMode) {
            return;
        }
        await initializeTable(tableElement);
        bindAdminActionTooltipSuppression(tableElement);
        bindSearch();
        loadAuditLogs();
    };

    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', onChange);
    } else {
        query.addListener(onChange);
    }

    viewportBound = true;
}

function buildColumns(mode) {
    if (mode === 'mobile') {
        return [
            { title: 'Event', className: 'dt-col-mobile-summary', orderable: false },
            { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions dt-col-mobile-actions' },
        ];
    }

    return [
        { title: 'Actor', className: 'dt-col-primary', orderable: false },
        { title: 'Category', className: 'dt-col-nowrap', orderable: false },
        { title: 'Action', className: 'dt-col-nowrap', orderable: false },
        { title: 'Subject', className: 'dt-col-name', orderable: false },
        { title: 'When', className: 'dt-col-nowrap', orderable: false },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(row) {
    const whenAbsolute = formatAbsolute(row.created_at);
    const whenRelative = formatRelative(row.created_at);
    const subject = escapeHtml(row.subject_label || '—');
    const description = escapeHtml(row.description || '');

    if (auditTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card audit-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div class="flex min-w-0 items-center gap-3">
                        ${renderActorAvatar(row)}
                        <div class="min-w-0">
                            <p class="admin-table-mobile-kicker">${escapeHtml(headline(row.category))}</p>
                            <p class="admin-table-mobile-title">${escapeHtml(row.actor_name || row.actor_email || 'System')}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        ${actionPill(row.action)}
                        <span class="audit-when-muted" title="${escapeHtml(whenAbsolute)}">${escapeHtml(whenRelative)}</span>
                    </div>
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Event:</span> ${description}</p>
                    <p><span>Subject:</span> ${subject}</p>
                </div>
            </div>`,
            renderActions(row),
        ];
    }

    return [
        renderActorIdentity(row),
        categoryPill(row.category),
        actionPill(row.action),
        `<div class="min-w-0">
            <p class="font-medium text-slate-800 truncate" title="${description}">${description}</p>
            <p class="text-xs text-slate-500 truncate">${subject}</p>
        </div>`,
        `<span class="audit-when" title="${escapeHtml(whenAbsolute)}">${escapeHtml(whenRelative)}</span>`,
        renderActions(row),
    ];
}

function renderActions(row) {
    return buildAdminActionButtons([
        {
            tooltip: 'View details',
            icon: 'fas fa-eye',
            className: 'admin-table-action-primary',
            attrs: `data-audit-view-button="${row.id}"`,
        },
    ]);
}

function bindTableActions(tableElement) {
    if (actionsBound || !tableElement) {
        return;
    }

    actionsBound = true;

    const handle = (event) => {
        const button = event.target.closest('[data-audit-view-button]');
        if (!button) {
            return;
        }
        event.preventDefault();
        viewAuditLog(Number(button.getAttribute('data-audit-view-button')));
    };

    tableElement.addEventListener('click', handle);
    document.addEventListener('click', (event) => {
        if (event.target.closest('.admin-action-overflow-menu [data-audit-view-button]')) {
            handle(event);
        }
    });
}

function bindSearch() {
    if (searchBound || !auditTable) {
        return;
    }

    const input = auditTable.table().container()?.querySelector('.dt-search input');
    if (!input) {
        return;
    }

    searchBound = true;
    input.value = activeSearch;
    input.addEventListener('input', () => {
        window.clearTimeout(searchDebounceTimer);
        searchDebounceTimer = window.setTimeout(() => {
            activeSearch = String(input.value || '').trim();
            loadAuditLogs();
        }, 320);
    });
}

async function viewAuditLog(id) {
    try {
        const { data } = await axios.get(`/admin/audit-logs/${id}`);
        const row = data.data;
        if (!row) {
            showErrorToast('Audit event not found.');
            return;
        }

        await Swal.fire(buildSwalOptions({
            title: 'Audit event',
            html: renderDetailDrawer(row),
            showConfirmButton: false,
            showCancelButton: true,
            cancelButtonText: 'Close',
        }, { size: 'lg' }));
    } catch (error) {
        showErrorToast(error.response?.data?.message ?? 'Unable to load audit event.');
    }
}

function renderDetailDrawer(row) {
    const whenAbsolute = formatAbsolute(row.created_at);
    const oldJson = formatJsonBlock(row.old_values);
    const newJson = formatJsonBlock(row.new_values);
    const propsJson = formatJsonBlock(row.properties);

    return `
        <div class="audit-detail-drawer">
            <div class="audit-detail-hero">
                ${renderActorAvatar(row)}
                <div class="min-w-0">
                    <p class="audit-detail-name">${escapeHtml(row.actor_name || 'Unknown actor')}</p>
                    <p class="audit-detail-email">${escapeHtml(row.actor_email || '—')}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        ${categoryPill(row.category)}
                        ${actionPill(row.action)}
                    </div>
                </div>
            </div>
            <p class="audit-detail-description">${escapeHtml(row.description || '')}</p>
            <dl class="audit-detail-meta">
                <div><dt>When</dt><dd>${escapeHtml(whenAbsolute)}</dd></div>
                <div><dt>IP address</dt><dd>${escapeHtml(row.ip_address || '—')}</dd></div>
                <div><dt>Subject</dt><dd>${escapeHtml(row.subject_label || '—')}</dd></div>
                <div><dt>Role</dt><dd>${escapeHtml(formatRole(row.actor_role))}</dd></div>
            </dl>
            ${row.user_agent ? `<p class="audit-detail-ua" title="${escapeHtml(row.user_agent)}">${escapeHtml(truncate(row.user_agent, 140))}</p>` : ''}
            ${oldJson || newJson ? `
                <div class="audit-detail-diff">
                    ${oldJson ? `<div><p class="audit-detail-block-title">Before</p><pre>${oldJson}</pre></div>` : ''}
                    ${newJson ? `<div><p class="audit-detail-block-title">After</p><pre>${newJson}</pre></div>` : ''}
                </div>
            ` : ''}
            ${propsJson ? `<div class="audit-detail-props"><p class="audit-detail-block-title">Request summary</p><pre>${propsJson}</pre></div>` : ''}
        </div>
    `;
}

async function exportCsv() {
    try {
        const response = await axios.get('/admin/audit-logs/export', {
            params: currentParams(),
            responseType: 'blob',
        });

        const disposition = response.headers['content-disposition'] || '';
        const match = disposition.match(/filename="?([^"]+)"?/i);
        const filename = match?.[1] || `dape-ma-audit-trail-${Date.now()}.csv`;
        const blob = new Blob([response.data], { type: 'text/csv;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        showSuccessToast('CSV download started.', 'Exported');
    } catch (error) {
        showErrorToast(error.response?.data?.message ?? 'Unable to export audit trail.');
    }
}

function renderActorIdentity(row) {
    return `
        <div class="admin-user-identity">
            ${renderActorAvatar(row)}
            <div class="min-w-0">
                <p class="admin-user-identity-name truncate">${escapeHtml(row.actor_name || 'System')}</p>
                <p class="admin-user-identity-email truncate">${escapeHtml(row.actor_email || '—')}</p>
            </div>
        </div>
    `;
}

function renderActorAvatar(row) {
    const name = row.actor_name || row.actor_email || '?';
    const initials = String(name)
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() || '')
        .join('') || '?';

    return `<span class="admin-user-avatar admin-user-avatar-fallback audit-actor-avatar" aria-hidden="true">${escapeHtml(initials)}</span>`;
}

function categoryPill(category) {
    const tone = CATEGORY_TONES[category] || 'slate';
    return `<span class="audit-pill audit-pill-category is-${tone}">${escapeHtml(headline(category))}</span>`;
}

function actionPill(action) {
    const tone = ACTION_TONES[action] || 'slate';
    return `<span class="audit-pill audit-pill-action is-${tone}">${escapeHtml(headline(action))}</span>`;
}

function formatJsonBlock(value) {
    if (value == null || (typeof value === 'object' && Object.keys(value).length === 0)) {
        return '';
    }

    try {
        return escapeHtml(JSON.stringify(value, null, 2));
    } catch {
        return escapeHtml(String(value));
    }
}

function formatCount(value) {
    const number = Number(value) || 0;
    return number.toLocaleString();
}

function formatRole(role) {
    if (!role) {
        return '—';
    }
    return headline(String(role).replaceAll('_', ' '));
}

function headline(value) {
    return String(value || '—')
        .replaceAll('-', ' ')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function truncate(value, max) {
    const text = String(value || '');
    return text.length > max ? `${text.slice(0, max)}…` : text;
}

function parseDate(value) {
    if (!value) {
        return null;
    }
    const normalized = typeof value === 'string' && value.includes(' ')
        ? value.replace(' ', 'T')
        : value;
    const date = new Date(normalized);
    return Number.isNaN(date.getTime()) ? null : date;
}

function formatAbsolute(value) {
    const date = parseDate(value);
    if (!date) {
        return value ? String(value) : '—';
    }
    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    });
}

function formatRelative(value) {
    const date = parseDate(value);
    if (!date) {
        return '—';
    }

    const seconds = Math.round((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) {
        return 'Just now';
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min ago`;
    }
    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }
    const days = Math.round(hours / 24);
    if (days < 30) {
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }
    const months = Math.round(days / 30);
    if (months < 12) {
        return `${months} month${months === 1 ? '' : 's'} ago`;
    }
    const years = Math.round(months / 12);
    return `${years} year${years === 1 ? '' : 's'} ago`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.AuditTrail = {
    reload: loadAuditLogs,
};
