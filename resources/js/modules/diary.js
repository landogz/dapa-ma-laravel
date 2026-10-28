import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { applyAdminFilterTabCounts, bindAdminFilterTabs, countByKey, renderAdminFilterTabs, setAdminFilterTab } from './shared/filter-tabs';
import { SKY_FILTER_ITEMS, userInitials } from './diary/sky-filters';
import { buildSwalOptions, confirmWithSwal } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
import { showErrorToast, showSuccessToast } from './shared/toast';

let diaryTable;
let diaryTableMode;
let hasBoundViewportListener = false;
let hasBoundFilters = false;
const diaryCache = new Map();

let userFilter = '';
let skyFilter = '';
let skyCounts = null;

const SKY_LABELS = {
    clear_skies: 'Clear skies',
    passing_mist: 'Passing mist',
    overcast: 'Overcast',
    stormy: 'Stormy',
    sky_red: 'Red',
    sky_pink: 'Pink',
    sky_blue: 'Blue',
    sky_white: 'White',
    sky_violet: 'Violet',
    sky_gray: 'Gray',
    sky_purple: 'Purple',
    sky_green: 'Green',
    sky_orange: 'Orange',
    sky_yellow: 'Yellow',
    sky_black: 'Black',
};

const SKY_SWATCH = {
    clear_skies: 'bg-[#FBD116]',
    passing_mist: 'bg-[#94A3B8]',
    overcast: 'bg-[#64748B]',
    stormy: 'bg-[#055498]',
    sky_red: 'bg-[#CE2028]',
    sky_pink: 'bg-[#EC4899]',
    sky_blue: 'bg-[#055498]',
    sky_white: 'bg-white border border-slate-300',
    sky_violet: 'bg-[#7C3AED]',
    sky_gray: 'bg-[#64748B]',
    sky_purple: 'bg-[#581C87]',
    sky_green: 'bg-[#16A34A]',
    sky_orange: 'bg-[#EA580C]',
    sky_yellow: 'bg-[#FBD116]',
    sky_black: 'bg-[#0F172A]',
};

export function initDiaryModule() {
    const tableElement = document.getElementById('diary-table');

    if (!tableElement) {
        return;
    }

    bindDiaryFilters();
    renderDiarySkyTabs();
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

    bindAdminFilterTabs(document.getElementById('diary-user-tabs'), (value) => {
        userFilter = value;
        loadDiaryEntries();
    });

    bindAdminFilterTabs(document.getElementById('diary-sky-tabs'), (value) => {
        skyFilter = value;
        loadDiaryEntries();
    });

    document.querySelector('[data-diary-user-search]')?.addEventListener('input', (event) => {
        filterDiaryUserTabs(event.target.value);
    });
}

function renderDiarySkyTabs() {
    const tabs = document.getElementById('diary-sky-tabs');
    if (!tabs) {
        return;
    }

    renderAdminFilterTabs(tabs, SKY_FILTER_ITEMS.map((item) => ({
        ...item,
        count: skyCounts ? (skyCounts[item.value] ?? 0) : '',
    })));
    setAdminFilterTab(tabs, skyFilter);
}

async function loadDiaryUsers() {
    const tabs = document.getElementById('diary-user-tabs');
    if (!tabs) {
        return;
    }

    try {
        const { data } = await axios.get('/admin/diary-users');
        const users = data.data ?? [];
        const known = new Set(users.map((user) => String(user.id)));
        if (userFilter && !known.has(userFilter)) {
            userFilter = '';
        }

        const total = users.reduce((sum, user) => sum + Number(user.entries_count ?? 0), 0);
        renderAdminFilterTabs(tabs, [
            { value: '', label: 'All users', hint: 'Every writer', icon: 'fas fa-users', count: total },
            ...users.map((user) => ({
                value: String(user.id),
                label: user.name || 'User',
                initials: userInitials(user.name),
                count: user.entries_count ?? 0,
            })),
        ]);
        setAdminFilterTab(tabs, userFilter);
        filterDiaryUserTabs(document.querySelector('[data-diary-user-search]')?.value || '');
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
            if (!userFilter) {
                skyCounts = countByKey(entries, (entry) => entry.sky || '');
            }
            if (skyCounts) {
                applyAdminFilterTabCounts(document.getElementById('diary-sky-tabs'), skyCounts);
            }
        })
        .catch((error) => {
            showErrorToast(
                error.response?.data?.message ?? 'Unable to load journal entries.',
                'Journal',
            );
        });
}

function filterDiaryUserTabs(query) {
    const tabs = document.getElementById('diary-user-tabs');
    const empty = document.querySelector('[data-diary-user-empty]');
    if (!tabs) {
        return;
    }

    const needle = String(query || '').trim().toLowerCase();
    let visiblePeople = 0;

    tabs.querySelectorAll('[data-filter-value]').forEach((tab) => {
        const value = tab.dataset.filterValue ?? '';
        if (!value) {
            tab.hidden = false;
            return;
        }

        const haystack = `${tab.dataset.filterLabel || ''} ${tab.textContent || ''}`.toLowerCase();
        const match = !needle || haystack.includes(needle);
        tab.hidden = !match;
        if (match) {
            visiblePeople += 1;
        }
    });

    empty?.classList.toggle('hidden', !needle || visiblePeople > 0);
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
            searchPlaceholder: 'Search entries',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ entries',
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

    const mobileQuery = window.matchMedia('(max-width: 1023px)');
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
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
}

function buildColumns(mode) {
    if (mode === 'mobile') {
        return [
            { title: 'Entry', className: 'dt-col-mobile-summary', orderable: false },
        ];
    }

    return [
        { title: 'Date', className: 'dt-col-nowrap' },
        { title: 'User', className: 'dt-col-primary dt-col-name', orderable: false },
        { title: 'Sky', className: 'dt-col-nowrap', orderable: false },
        { title: 'Preview', className: 'dt-col-wide', orderable: false },
        { title: 'Updated', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

function buildDiaryRowData(entry) {
    const userName = escapeHtml(entry.user?.name ?? 'Unknown user');
    const userEmail = escapeHtml(entry.user?.email ?? '');
    const skyLabel = formatSky(entry.sky);
    const distinctTitle = distinctDiaryTitle(entry, skyLabel);
    const title = escapeHtml(distinctTitle || skyLabel || '—');
    const notesPreview = stripHtml(entry.body_html ?? '');
    const feelings = Array.isArray(entry.feelings)
        ? entry.feelings.map((feeling) => formatFeelingLabel(feeling)).filter(Boolean)
        : [];
    const preview = buildDiaryPreview(notesPreview || distinctTitle, feelings);
    const entryDate = formatDate(entry.entry_date);
    const updatedAt = formatDateTime(entry.updated_at ?? entry.created_at);
    const rowAttrs = `data-diary-row-id="${entry.id}"`;

    if (diaryTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card" ${rowAttrs}>
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${escapeHtml(entryDate)} · ${formatSkyMarkup(entry.sky)}</p>
                        <p class="admin-table-mobile-title">${title}</p>
                    </div>
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>User:</span> ${userName}</p>
                    ${userEmail ? `<p><span>Email:</span> ${userEmail}</p>` : ''}
                    <div><span>Preview:</span> ${preview}</div>
                    <p><span>Updated:</span> ${escapeHtml(updatedAt)}</p>
                </div>
                <div class="admin-table-mobile-actions">${buildActionButtons(entry.id, true)}</div>
            </div>`,
        ];
    }

    return [
        `<span ${rowAttrs}>${escapeHtml(entryDate)}</span>`,
        `<div class="min-w-[10rem]"><p class="font-semibold text-slate-800">${userName}</p>${
            userEmail ? `<p class="diary-user-email">${userEmail}</p>` : ''
        }</div>`,
        formatSkyMarkup(entry.sky),
        preview,
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

function formatSkyMarkup(sky) {
    const label = formatSky(sky);
    if (!sky) {
        return escapeHtml(label);
    }

    const swatch = SKY_SWATCH[sky] || 'bg-slate-300';

    return `<span class="inline-flex min-h-11 items-center gap-2"><span class="inline-block h-4 w-4 shrink-0 rounded-full ${swatch}" aria-hidden="true"></span><span>${escapeHtml(label)}</span></span>`;
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

function viewDiaryEntry(entryId) {
    const entry = diaryCache.get(String(entryId));

    if (!entry) {
        showErrorToast('Journal entry not found in the current list.', 'Journal');
        return;
    }

    openDiaryView(entry);
}

async function deleteDiaryEntry(entryId) {
    const result = await confirmWithSwal({
        icon: 'warning',
        title: 'Delete this entry?',
        text: 'This can’t be undone.',
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
                        <p class="text-sm text-slate-600"><strong>Sky:</strong> ${formatSkyMarkup(entry.sky)}</p>
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

function distinctDiaryTitle(entry, skyLabel) {
    const raw = String(entry?.title ?? '').trim();
    if (!raw) {
        return '';
    }

    const skyRaw = String(entry?.sky ?? '').replaceAll('_', ' ').trim();
    if (raw.toLowerCase() === skyRaw.toLowerCase()
        || raw.toLowerCase() === String(skyLabel ?? '').toLowerCase()) {
        return '';
    }

    return raw;
}

function buildDiaryPreview(notesPreview, feelings = []) {
    const text = notesPreview.trim();
    const visibleFeelings = feelings.slice(0, 2);
    const extraCount = Math.max(0, feelings.length - visibleFeelings.length);
    const extraTitle = extraCount > 0
        ? feelings.slice(2).join(', ')
        : '';
    const chips = [
        ...visibleFeelings.map((feeling) => (
            `<span class="diary-mood-chip">${escapeHtml(feeling)}</span>`
        )),
        extraCount > 0
            ? `<span class="diary-mood-chip diary-mood-more" title="${escapeHtml(extraTitle)}">+${extraCount}</span>`
            : '',
    ].join('');

    if (!text && !chips) {
        return '—';
    }

    const textHtml = text
        ? `<p class="diary-preview-text" title="${escapeHtml(text)}">${escapeHtml(text)}</p>`
        : '';

    return `<div class="diary-preview-cell">${textHtml}${
        chips ? `<div class="diary-preview-moods">${chips}</div>` : ''
    }</div>`;
}

function formatFeelingLabel(value) {
    return String(value ?? '')
        .replaceAll('_', ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\b\w/g, (letter) => letter.toUpperCase());
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
