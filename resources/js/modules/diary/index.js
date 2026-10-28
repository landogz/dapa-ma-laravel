import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { applyAdminFilterTabCounts, bindAdminFilterTabs, countByKey, renderAdminFilterTabs, setAdminFilterTab } from '../shared/filter-tabs';
import { SKY_FILTER_ITEMS, userInitials } from './sky-filters';
import { buildSwalOptions, confirmWithSwal } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let diaryTable;
let diaryTableMode;
let hasBoundViewportListener = false;
let hasBoundFilters = false;
let hasBoundChrome = false;
const diaryCache = new Map();

let userFilter = '';
let skyFilter = '';
let skyCounts = null;
let summaryDays = 30;

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

export function initDiaryModule() {
    const tableElement = document.getElementById('diary-table');

    if (!tableElement) {
        return;
    }

    bindDiaryChrome();
    bindDiaryFilters();
    renderDiarySkyTabs();
    loadDiaryUsers();
    loadDiarySummary();

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

function bindDiaryChrome() {
    if (hasBoundChrome) {
        return;
    }

    hasBoundChrome = true;

    document.querySelectorAll('[data-diary-range]').forEach((button) => {
        button.addEventListener('click', () => {
            const nextDays = Number(button.getAttribute('data-diary-range'));
            if (![7, 30, 90].includes(nextDays) || nextDays === summaryDays) {
                return;
            }

            summaryDays = nextDays;
            document.querySelectorAll('[data-diary-range]').forEach((node) => {
                node.classList.toggle('is-active', Number(node.getAttribute('data-diary-range')) === summaryDays);
            });
            loadDiarySummary();
        });
    });

    document.querySelector('[data-admin-action="refresh-diary"]')?.addEventListener('click', () => {
        loadDiaryUsers();
        loadDiarySummary();
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

function loadDiarySummary() {
    axios.get('/admin/diary-entries/summary', { params: { days: summaryDays } })
        .then(({ data }) => {
            renderDiarySummary(data.data ?? {});
        })
        .catch((error) => {
            showErrorToast(
                error.response?.data?.message ?? 'Unable to load journal analytics.',
                'Journal',
            );
        });
}

function renderDiarySummary(summary) {
    const totals = summary.totals ?? {};
    const previous = summary.previous ?? {};
    const skies = Array.isArray(summary.skies) ? summary.skies : [];
    const daily = Array.isArray(summary.daily) ? summary.daily : [];
    const days = Number(summary.days || summaryDays);
    const topSky = skies[0];

    setSummaryValue('entries', formatNumber(totals.entries));
    setSummaryValue('users', formatNumber(totals.users));
    setSummaryValue('today', formatNumber(totals.today));
    setSummaryValue('with_photo', formatNumber(totals.with_photo));
    setSummaryValue('with_notes', formatNumber(totals.with_notes));
    setSummaryValue('with_gratitude', formatNumber(totals.with_gratitude));
    setSummaryValue('top-sky', topSky ? formatSky(topSky.key) : '—');
    setSummaryValue('avg-day', days > 0 ? formatNumber((Number(totals.entries) || 0) / days, 1) : '—');

    setTrend('entries', trendCopy(totals.entries, previous.entries, `${days}-day window`));
    setTrend('users', trendCopy(totals.users, previous.users, 'writers vs prior window'));
    setTrend('with_photo', `${percentOf(totals.with_photo, totals.entries)} of entries include a photo`);
    setTrend('top-sky', topSky
        ? `${formatNumber(topSky.count)} of ${formatNumber(totals.entries)} entries`
        : 'No sky colors in this range');

    const updated = document.querySelector('[data-diary-updated-at]');
    if (updated) {
        const stamp = summary.updated_at ? formatDateTime(summary.updated_at) : formatDateTime(new Date().toISOString());
        updated.textContent = `Updated ${stamp} · last ${days} days`;
    }

    renderDiaryInsights(totals, skies, days);
    renderDiaryActivity(daily);
    renderDiarySkyMix(skies, Number(totals.entries) || 0);
}

function renderDiaryInsights(totals, skies, days) {
    const root = document.querySelector('[data-diary-insights]');
    if (!root) {
        return;
    }

    const topSky = skies[0];
    const insights = [
        {
            icon: 'fas fa-book-open',
            text: `${formatNumber(totals.entries)} notes in the last ${days} days from ${formatNumber(totals.users)} people.`,
        },
        {
            icon: 'fas fa-cloud-sun',
            text: topSky
                ? `${formatSky(topSky.key)} is the leading sky (${percentOf(topSky.count, totals.entries)}).`
                : 'No sky colors recorded in this window yet.',
        },
        {
            icon: 'fas fa-heart',
            text: `${formatNumber(totals.with_gratitude)} entries name something they are grateful for.`,
        },
        {
            icon: 'fas fa-image',
            text: totals.today
                ? `${formatNumber(totals.today)} written today. ${formatNumber(totals.with_photo)} include a photo.`
                : `No notes dated today. ${formatNumber(totals.with_photo)} include a photo in-range.`,
        },
    ];

    root.innerHTML = insights.map((insight) => `
        <article class="analytics-insight-chip">
            <span class="analytics-insight-chip-icon">
                <i class="${insight.icon}" aria-hidden="true"></i>
            </span>
            <p class="analytics-insight-chip-text">${escapeHtml(insight.text)}</p>
        </article>
    `).join('');
    root.classList.add('is-visible');
}

function renderDiaryActivity(daily) {
    const root = document.querySelector('[data-diary-activity]');
    if (!root) {
        return;
    }

    if (!daily.length) {
        root.innerHTML = '<p class="text-sm text-slate-500">No journal activity in this range.</p>';
        return;
    }

    const max = Math.max(...daily.map((row) => Number(row.count) || 0), 1);
    root.innerHTML = daily.map((row) => {
        const count = Number(row.count) || 0;
        const height = Math.max(8, Math.round((count / max) * 88));
        const label = formatBarDay(row.date);
        return `
            <div class="diary-activity-bar" title="${escapeHtml(`${label}: ${count}`)}">
                <span class="diary-activity-bar-fill" data-diary-bar-height="${height}"></span>
                <span class="diary-activity-bar-label">${escapeHtml(label)}</span>
            </div>
        `;
    }).join('');

    root.querySelectorAll('[data-diary-bar-height]').forEach((bar) => {
        bar.style.height = `${bar.getAttribute('data-diary-bar-height')}px`;
    });
}

function renderDiarySkyMix(skies, total) {
    const root = document.querySelector('[data-diary-sky-mix]');
    if (!root) {
        return;
    }

    if (!skies.length) {
        root.innerHTML = '<p class="text-sm text-slate-500">No sky colors in this range.</p>';
        return;
    }

    const visible = skies.slice(0, 6);
    root.innerHTML = visible.map((row) => {
        const pct = total > 0 ? Math.round((row.count / total) * 100) : 0;
        return `
            <div class="diary-sky-mix-row">
                ${skyChip(row.key)}
                <span class="diary-sky-mix-count">${formatNumber(row.count)}</span>
                    <span class="diary-sky-mix-track" aria-hidden="true">
                    <span class="diary-sky-mix-fill" data-sky="${escapeHtml(row.key)}" data-diary-mix="${pct}"></span>
                </span>
                <span class="diary-sky-mix-pct">${pct}%</span>
            </div>
        `;
    }).join('');

    root.querySelectorAll('[data-diary-mix]').forEach((fill) => {
        const pct = Number(fill.getAttribute('data-diary-mix')) || 0;
        fill.style.width = `${Math.max(pct, pct > 0 ? 4 : 0)}%`;
    });
}

function setSummaryValue(key, value) {
    const node = document.querySelector(`[data-diary-summary="${key}"]`);
    if (node) {
        node.textContent = value;
    }
}

function setTrend(key, value) {
    const node = document.querySelector(`[data-diary-trend="${key}"]`);
    if (node) {
        node.textContent = value;
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
            searchPlaceholder: 'Search people, skies, or notes',
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
        { title: 'When', className: 'dt-col-nowrap' },
        { title: 'Person', className: 'dt-col-primary dt-col-name', orderable: false },
        { title: 'Sky', className: 'dt-col-nowrap', orderable: false },
        { title: 'Note', className: 'dt-col-wide', orderable: false },
        { title: 'Updated', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

function buildDiaryRowData(entry) {
    const userName = entry.user?.name ?? 'Unknown user';
    const skyLabel = formatSky(entry.sky);
    const distinctTitle = distinctDiaryTitle(entry, skyLabel);
    const title = distinctTitle || skyLabel || 'Untitled note';
    const notesPreview = stripHtml(entry.body_html ?? '');
    const feelings = Array.isArray(entry.feelings)
        ? entry.feelings.map((feeling) => formatFeelingLabel(feeling)).filter(Boolean)
        : [];
    const preview = buildDiaryPreview(notesPreview || distinctTitle, feelings, Boolean(entry.image_url));
    const entryDate = formatDate(entry.entry_date);
    const updatedAt = formatRelativeTime(entry.updated_at ?? entry.created_at);
    const rowAttrs = `data-diary-row-id="${entry.id}"`;

    if (diaryTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card" ${rowAttrs}>
                <div class="admin-table-mobile-title-row">
                    ${userAvatar(userName)}
                    <div class="min-w-0">
                        <p class="admin-table-mobile-kicker">${escapeHtml(entryDate)} · ${skyChip(entry.sky)}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(title)}</p>
                    </div>
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Person:</span> ${escapeHtml(userName)}</p>
                    <div><span>Note:</span> ${preview}</div>
                    <p><span>Updated:</span> ${escapeHtml(updatedAt)}</p>
                </div>
                <div class="admin-table-mobile-actions">${buildActionButtons(entry.id, true)}</div>
            </div>`,
        ];
    }

    return [
        `<span ${rowAttrs} class="diary-when">
            <strong>${escapeHtml(entryDate)}</strong>
            <span>${escapeHtml(weekdayLabel(entry.entry_date))}</span>
        </span>`,
        `<div class="diary-person">
            ${userAvatar(userName)}
            <div class="min-w-0">
                <p class="diary-person-name">${escapeHtml(userName)}</p>
            </div>
        </div>`,
        skyChip(entry.sky),
        preview,
        `<span class="diary-updated">${escapeHtml(updatedAt)}</span>`,
        buildActionButtons(entry.id, false),
    ];
}

function formatSky(sky) {
    if (!sky) {
        return '—';
    }

    return SKY_LABELS[sky] || String(sky).replaceAll('_', ' ');
}

function skyChip(sky) {
    const label = formatSky(sky);
    if (!sky) {
        return `<span class="diary-sky-chip is-empty">${escapeHtml(label)}</span>`;
    }

    const border = sky === 'sky_white' ? 'is-light' : '';

    return `<span class="diary-sky-chip ${border}">
        <span class="diary-sky-dot" data-sky="${escapeHtml(sky)}"></span>
        <span>${escapeHtml(label)}</span>
    </span>`;
}

function userAvatar(name) {
    return `<span class="diary-user-avatar" aria-hidden="true">${escapeHtml(initials(name))}</span>`;
}

function initials(name) {
    const parts = String(name || 'User').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return 'U';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0]}${parts[parts.length - 1][0]}`.toUpperCase();
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

async function viewDiaryEntry(entryId) {
    let entry = diaryCache.get(String(entryId));

    try {
        const { data } = await axios.get(`/admin/diary-entries/${entryId}`);
        entry = data.data;
        diaryCache.set(String(entryId), entry);
    } catch (error) {
        if (!entry) {
            showErrorToast(
                error.response?.data?.message ?? 'Journal entry not found.',
                'Journal',
            );
            return;
        }
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
        loadDiarySummary();
        loadDiaryEntries();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Unable to delete journal entry.',
            'Journal',
        );
    }
}

function openDiaryView(entry) {
    const skyLabel = formatSky(entry.sky);
    const title = distinctDiaryTitle(entry, skyLabel) || skyLabel || 'Journal entry';
    const userName = entry.user?.name ?? 'Unknown user';
    const entryDate = formatDate(entry.entry_date);
    const bodyHtml = entry.body_html ?? '<p><em>No written notes</em></p>';
    const impact = entry.impact ? formatFeelingLabel(entry.impact) : '';
    const feelings = Array.isArray(entry.feelings)
        ? entry.feelings.map((feeling) => formatFeelingLabel(feeling)).filter(Boolean)
        : [];
    const gratitude = String(entry.gratitude ?? '').trim();

    Swal.fire(buildSwalOptions({
        title,
        subtitle: `${userName} · ${entryDate}`,
        html: `
            <div class="admin-swal-form text-left">
                <div class="diary-reader">
                    <div class="diary-reader-meta">
                        ${skyChip(entry.sky)}
                        ${impact ? `<span class="diary-reader-pill">${escapeHtml(impact)}</span>` : ''}
                        ${entry.image_url ? '<span class="diary-reader-pill"><i class="fas fa-image" aria-hidden="true"></i> Photo</span>' : ''}
                        <span class="diary-reader-pill">${escapeHtml(formatDateTime(entry.updated_at ?? entry.created_at))}</span>
                    </div>
                    ${feelings.length ? `<div class="diary-reader-feelings">${feelings.map((feeling) => `<span class="diary-mood-chip">${escapeHtml(feeling)}</span>`).join('')}</div>` : ''}
                    ${gratitude ? `<blockquote class="diary-reader-gratitude"><p class="diary-reader-kicker">Gratitude</p><p>${escapeHtml(gratitude)}</p></blockquote>` : ''}
                    ${entry.image_url ? `<img src="${escapeHtml(entry.image_url)}" alt="" class="diary-reader-photo">` : ''}
                    <div class="diary-entry-preview diary-reader-body">
                        ${bodyHtml}
                    </div>
                </div>
            </div>
        `,
        showCancelButton: false,
        confirmButtonText: 'Close',
        confirmOnly: false,
    }), { size: 'lg' });
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

function buildDiaryPreview(notesPreview, feelings = [], hasPhoto = false) {
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
        hasPhoto
            ? '<span class="diary-mood-chip diary-mood-photo"><i class="fas fa-image" aria-hidden="true"></i> Photo</span>'
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

    const date = new Date(String(value).includes(' ') ? String(value).replace(' ', 'T') : value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleDateString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
    });
}

function weekdayLabel(value) {
    if (!value) {
        return '';
    }

    const date = new Date(String(value).includes(' ') ? String(value).replace(' ', 'T') : value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString(undefined, { weekday: 'short' });
}

function formatBarDay(value) {
    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
        return String(value ?? '');
    }

    return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
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

function formatRelativeTime(value) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    const diff = Date.now() - date.getTime();
    const minutes = Math.round(diff / 60000);

    if (minutes < 1) {
        return 'Just now';
    }
    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours}h ago`;
    }

    const days = Math.round(hours / 24);
    if (days < 7) {
        return `${days}d ago`;
    }

    return formatDateTime(value);
}

function formatNumber(value, digits = 0) {
    const number = Number(value);
    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString(undefined, {
        maximumFractionDigits: digits,
        minimumFractionDigits: digits,
    });
}

function percentOf(part, total) {
    const count = Number(total);
    if (!count) {
        return '0%';
    }

    return `${Math.round((Number(part) / count) * 100)}%`;
}

function trendCopy(current, previous, suffix) {
    const now = Number(current) || 0;
    const was = Number(previous) || 0;

    if (was === 0 && now === 0) {
        return `No change vs prior ${suffix}`;
    }
    if (was === 0) {
        return `New activity vs prior ${suffix}`;
    }

    const delta = Math.round(((now - was) / was) * 100);
    const direction = delta > 0 ? 'up' : delta < 0 ? 'down' : 'flat';

    return `${Math.abs(delta)}% ${direction} vs prior ${suffix}`;
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
