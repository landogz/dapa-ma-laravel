import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { enhanceAdminFilterSelects } from './shared/filter-select';
import { buildSwalOptions, confirmWithSwal } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
import { showErrorToast, showSuccessToast } from './shared/toast';

let diaryTable;
let diaryTableMode;
let hasBoundViewportListener = false;
let hasBoundContextMenu = false;
let hasBoundFilters = false;
let contextEntryId = null;
const diaryCache = new Map();

let userFilter = '';
let skyFilter = '';

const SKY_LABELS = {
    clear_skies: 'Clear skies',
    passing_mist: 'Passing mist',
    overcast: 'Overcast',
    stormy: 'Stormy',
};

export function initDiaryModule() {
    const tableElement = document.getElementById('diary-table');

    if (!tableElement) {
        return;
    }

    bindDiaryFilters();
    bindDiaryContextMenu(tableElement);
    loadDiaryUsers();

    if (diaryTable) {
        loadDiaryEntries();
        return;
    }

    initializeDiaryTable(tableElement).then(() => {
        bindViewportListener(tableElement);
        bindAdminActionTooltipSuppression(tableElement);
        loadDiaryEntries();
    });
}

function bindDiaryFilters() {
    if (hasBoundFilters) {
        return;
    }

    hasBoundFilters = true;

    document.getElementById('diary-user-filter')?.addEventListener('change', (event) => {
        userFilter = event.target.value || '';
        loadDiaryEntries();
    });

    document.getElementById('diary-sky-filter')?.addEventListener('change', (event) => {
        skyFilter = event.target.value || '';
        loadDiaryEntries();
    });

    enhanceAdminFilterSelects(document.querySelector('.admin-shell-card') || document);
}

async function loadDiaryUsers() {
    const select = document.getElementById('diary-user-filter');
    if (!select) {
        return;
    }

    try {
        const { data } = await axios.get('/admin/diary-users');
        const users = data.data ?? [];
        const previous = userFilter || select.value || '';

        select.innerHTML = [
            '<option value="">All users</option>',
            ...users.map((user) => {
                const label = `${escapeHtml(user.name || 'User')}${user.entries_count ? ` (${user.entries_count})` : ''}`;
                return `<option value="${escapeHtml(String(user.id))}">${label}</option>`;
            }),
        ].join('');

        if (previous && Array.from(select.options).some((opt) => opt.value === previous)) {
            select.value = previous;
            userFilter = previous;
        } else {
            select.value = '';
            userFilter = '';
        }

        enhanceAdminFilterSelects(select.parentElement || document);
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Unable to load journal users.',
            'Journal',
        );
    }
}

function loadDiaryEntries() {
    if (!diaryTable) {
        return;
    }

    axios
        .get('/admin/diary-entries', {
            params: {
                per_page: 200,
                ...(userFilter ? { user_id: userFilter } : {}),
                ...(skyFilter ? { sky: skyFilter } : {}),
            },
        })
        .then((response) => {
            const entries = response.data.data?.data ?? [];

            diaryTable.clear();
            diaryCache.clear();

            entries.forEach((entry) => {
                diaryCache.set(String(entry.id), entry);
                diaryTable.row.add(buildDiaryRowData(entry));
            });

            diaryTable.draw();
            bindDiaryActions();
        })
        .catch((error) => {
            showErrorToast(
                error.response?.data?.message ?? 'Unable to load journal entries.',
                'Journal',
            );
        });
}

async function initializeDiaryTable(tableElement) {
    const nextMode = getTableMode();

    if (diaryTable && diaryTableMode === nextMode) {
        return;
    }

    if (diaryTable) {
        diaryTable.destroy();
        tableElement.innerHTML = '';
    }

    diaryTableMode = nextMode;
    diaryTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: buildColumns(nextMode),
            searchPlaceholder: 'Search journal entries...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ journal entries',
            pageLength: nextMode === 'mobile' ? 5 : 10,
            scrollX: nextMode !== 'mobile',
            scrollCollapse: nextMode !== 'mobile',
        }),
    );
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 767px)');
    const handleViewportChange = async () => {
        const nextMode = getTableMode();
        if (nextMode === diaryTableMode) {
            return;
        }

        await initializeDiaryTable(tableEl);
        loadDiaryEntries();
    };

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleViewportChange);
    } else {
        mobileQuery.addListener(handleViewportChange);
    }

    hasBoundViewportListener = true;
}

function getTableMode() {
    return window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop';
}

function buildColumns(mode) {
    if (mode === 'mobile') {
        return [
            { title: 'Entry', className: 'dt-col-mobile-summary', orderable: false },
        ];
    }

    return [
        { title: 'Date', className: 'dt-col-nowrap' },
        { title: 'User', className: 'dt-col-primary dt-col-name' },
        { title: 'Sky', className: 'dt-col-nowrap' },
        { title: 'Title', className: 'dt-col-wide' },
        { title: 'Preview', className: 'dt-col-wide' },
        { title: 'Updated', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

function buildDiaryRowData(entry) {
    const userName = escapeHtml(entry.user?.name ?? 'Unknown user');
    const userEmail = escapeHtml(entry.user?.email ?? '');
    const title = escapeHtml(entry.title?.trim() || entry.sky?.replaceAll('_', ' ') || '—');
    const notesPreview = stripHtml(entry.body_html ?? '').slice(0, 80);
    const feelings = Array.isArray(entry.feelings) ? entry.feelings.join(', ') : '';
    const previewBits = [notesPreview, feelings].filter(Boolean).join(' · ');
    const preview = escapeHtml(previewBits.slice(0, 120));
    const entryDate = formatDate(entry.entry_date);
    const updatedAt = formatDateTime(entry.updated_at ?? entry.created_at);
    const skyLabel = formatSky(entry.sky);
    const rowAttrs = `data-diary-row-id="${entry.id}"`;

    if (diaryTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card" ${rowAttrs}>
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${escapeHtml(entryDate)} · ${escapeHtml(skyLabel)}</p>
                        <p class="admin-table-mobile-title">${title}</p>
                    </div>
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>User:</span> ${userName}</p>
                    ${userEmail ? `<p><span>Email:</span> ${userEmail}</p>` : ''}
                    <p><span>Preview:</span> ${preview || '—'}</p>
                    <p><span>Updated:</span> ${escapeHtml(updatedAt)}</p>
                </div>
                <div class="admin-table-mobile-actions">${buildActionButtons(entry.id, true)}</div>
            </div>`,
        ];
    }

    return [
        `<span ${rowAttrs}>${escapeHtml(entryDate)}</span>`,
        `<div class="min-w-[10rem]"><p class="font-semibold text-slate-800">${userName}</p><p class="text-xs text-slate-500">${userEmail}</p></div>`,
        escapeHtml(skyLabel),
        title,
        preview || '—',
        updatedAt,
        buildActionButtons(entry.id, false),
    ];
}

function formatSky(sky) {
    if (!sky) {
        return '—';
    }

    return SKY_LABELS[sky] || String(sky).replaceAll('_', ' ');
}

function buildActionButtons(entryId, isMobile) {
    const actions = [
        {
            tooltip: 'View entry',
            icon: 'fas fa-eye',
            className: 'admin-table-action-primary',
            attrs: `data-diary-view="${entryId}"`,
            label: 'View',
        },
        {
            tooltip: 'Delete entry',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `data-diary-delete="${entryId}"`,
            label: 'Delete',
        },
    ];

    return buildAdminActionButtons(actions, { isMobile: Boolean(isMobile), nowrap: true });
}

function bindDiaryActions() {
    document.querySelectorAll('[data-diary-view]').forEach((button) => {
        if (button.dataset.bound === '1') {
            return;
        }
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            viewDiaryEntry(button.getAttribute('data-diary-view'));
        });
    });

    document.querySelectorAll('[data-diary-delete]').forEach((button) => {
        if (button.dataset.bound === '1') {
            return;
        }
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            deleteDiaryEntry(button.getAttribute('data-diary-delete'));
        });
    });
}

function bindDiaryContextMenu(tableElement) {
    if (hasBoundContextMenu) {
        return;
    }

    hasBoundContextMenu = true;
    const menu = document.getElementById('diary-context-menu');
    if (!menu) {
        return;
    }

    const hideMenu = () => {
        menu.classList.add('hidden');
        menu.setAttribute('hidden', 'hidden');
        contextEntryId = null;
    };

    tableElement.addEventListener('contextmenu', (event) => {
        const row = event.target.closest('[data-diary-row-id], tr');
        if (!row) {
            return;
        }

        const entryId = row.getAttribute('data-diary-row-id')
            || row.querySelector('[data-diary-row-id]')?.getAttribute('data-diary-row-id')
            || row.querySelector('[data-diary-view]')?.getAttribute('data-diary-view');

        if (!entryId || !diaryCache.has(String(entryId))) {
            return;
        }

        event.preventDefault();
        contextEntryId = String(entryId);

        menu.classList.remove('hidden');
        menu.removeAttribute('hidden');
        menu.style.left = `${Math.min(event.clientX, window.innerWidth - 220)}px`;
        menu.style.top = `${Math.min(event.clientY, window.innerHeight - 120)}px`;
    });

    menu.querySelectorAll('[data-diary-context]').forEach((button) => {
        button.addEventListener('click', async () => {
            const action = button.getAttribute('data-diary-context');
            const entryId = contextEntryId;
            hideMenu();

            if (!entryId) {
                return;
            }

            if (action === 'view') {
                viewDiaryEntry(entryId);
            } else if (action === 'delete') {
                await deleteDiaryEntry(entryId);
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

function viewDiaryEntry(entryId) {
    const entry = diaryCache.get(String(entryId));

    if (!entry) {
        showErrorToast('Journal entry not found in the current list.', 'Journal');
        return;
    }

    openDiaryView(entry);
}

async function deleteDiaryEntry(entryId) {
    const entry = diaryCache.get(String(entryId));
    const userName = entry?.user?.name ?? 'this user';

    const result = await confirmWithSwal({
        icon: 'warning',
        title: 'Delete journal entry?',
        text: `This will permanently remove the journal note for ${userName}.`,
        confirmButtonText: 'Delete entry',
        danger: true,
    });

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await axios.delete(`/admin/diary-entries/${entryId}`);
        showSuccessToast(response.data.message ?? 'Journal entry deleted.');
        await loadDiaryUsers();
        loadDiaryEntries();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Unable to delete journal entry.',
            'Journal',
        );
    }
}

function openDiaryView(entry) {
    const title = entry.title?.trim() ? escapeHtml(entry.title) : 'Journal entry';
    const userName = escapeHtml(entry.user?.name ?? 'Unknown user');
    const userEmail = escapeHtml(entry.user?.email ?? '');
    const entryDate = formatDate(entry.entry_date);
    const bodyHtml = entry.body_html ?? '<p><em>No written notes</em></p>';
    const sky = formatSky(entry.sky);
    const impact = entry.impact ? escapeHtml(String(entry.impact).replaceAll('_', ' ')) : '—';
    const feelings = Array.isArray(entry.feelings) && entry.feelings.length
        ? escapeHtml(entry.feelings.join(', '))
        : '—';
    const gratitude = entry.gratitude
        ? escapeHtml(String(entry.gratitude))
        : '—';

    Swal.fire(buildSwalOptions({
        title,
        subtitle: `${userName}${userEmail ? ` · ${userEmail}` : ''} · ${entryDate}`,
        html: `
            <div class="admin-swal-form text-left">
                <div class="admin-swal-fields">
                    <div class="admin-swal-fields-grid-2">
                        <p class="text-sm text-slate-600"><strong>Sky:</strong> ${escapeHtml(sky)}</p>
                        <p class="text-sm text-slate-600"><strong>Impact:</strong> ${impact}</p>
                    </div>
                    <p class="text-sm text-slate-600"><strong>Feelings:</strong> ${feelings}</p>
                    <p class="text-sm text-slate-600"><strong>Gratitude:</strong> ${gratitude}</p>
                    ${entry.image_url ? `<img src="${escapeHtml(entry.image_url)}" alt="Journal photo" class="mb-2 max-h-72 w-full rounded-xl object-cover" />` : ''}
                    <div class="diary-entry-preview rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-800">
                        ${bodyHtml}
                    </div>
                </div>
            </div>
        `,
        showCancelButton: false,
        confirmButtonText: 'Close',
        confirmOnly: false,
    }));
}

function stripHtml(value) {
    return String(value)
        .replace(/<[^>]*>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value.includes(' ') ? value.replace(' ', 'T') : value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

function formatDateTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

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

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.Diary = {
    reload: loadDiaryEntries,
};
