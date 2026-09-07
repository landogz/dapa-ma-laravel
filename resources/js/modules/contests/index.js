import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let contestsTable;
let contestsDataTableClass;
let contestsTableMode;
let hasBoundViewportListener = false;

const CATEGORY_OPTIONS = [
    { value: 'song', label: 'Song Contest' },
    { value: 'poster', label: 'Poster Contest' },
    { value: 'video', label: 'Video Contest' },
];

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open for submissions' },
    { value: 'closed', label: 'Closed (reviewing)' },
    { value: 'completed', label: 'Completed' },
];

export function initContestsModule() {
    const tableEl = document.getElementById('contests-table');
    if (!tableEl) return;

    loadAdminDataTableLibrary().then(async (DataTable) => {
        contestsDataTableClass = DataTable;
        await initializeContestsTable(tableEl);
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadContests();
    });
}

export function loadContests(search = '') {
    if (!contestsTable) return;

    axios.get('/admin/contests', { params: { search, per_page: 200 } })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            contestsTable.clear();
            rows.forEach((contest) => {
                contestsTable.row.add(buildRowData(contest));
            });
            contestsTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load contests.');
        });
}

export function createContest() {
    showContestForm(null);
}

export function editContest(id) {
    axios.get(`/admin/contests/${id}`).then(({ data }) => {
        showContestForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load contest details.');
    });
}

export function removeContest(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete Contest?',
        html: '<p class="admin-swal-description">All submissions under this contest will also be deleted.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/contests/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Deleted');
                loadContests();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

export function reviewSubmissions(contestId) {
    axios.get(`/admin/contests/${contestId}/entries`, { params: { per_page: 200 } })
        .then(({ data }) => {
            const contest = data.data?.contest;
            const entries = data.data?.entries?.data ?? [];
            showSubmissionsModal(contest, entries);
        })
        .catch(() => {
            showErrorToast('Failed to load submissions.');
        });
}

function categoryLabel(value) {
    return CATEGORY_OPTIONS.find((item) => item.value === value)?.label ?? value ?? '—';
}

function buildContestFormHtml(existing) {
    const statusValue = existing?.status ?? 'open';
    const activeValue = existing?.is_active === false ? '0' : '1';
    const categoryValue = existing?.category ?? 'song';
    const allowedValue = existing?.allowed_entry_types ?? 'both';
    const statusOptions = STATUS_OPTIONS.map((option) => {
        const selected = statusValue === option.value ? 'selected' : '';
        return `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
    }).join('');
    const categoryOptions = CATEGORY_OPTIONS.map((option) => {
        const selected = categoryValue === option.value ? 'selected' : '';
        return `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
    }).join('');

    return `
        <div class="admin-swal-form">
            <div class="admin-swal-fields">
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-category">Category *</label>
                    <select id="ct-category" class="admin-swal-input">${categoryOptions}</select>
                </div>
                <div class="admin-swal-field" id="ct-allowed-wrap">
                    <label class="admin-swal-label" for="ct-allowed">Allowed song entry types</label>
                    <select id="ct-allowed" class="admin-swal-input">
                        <option value="both" ${allowedValue === 'both' ? 'selected' : ''}>Song + Playlist</option>
                        <option value="song" ${allowedValue === 'song' ? 'selected' : ''}>Song only</option>
                        <option value="playlist" ${allowedValue === 'playlist' ? 'selected' : ''}>Playlist only</option>
                    </select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-title">Contest title *</label>
                    <input id="ct-title" class="admin-swal-input" type="text" value="${escapeHtml(existing?.title ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-theme">Theme</label>
                    <input id="ct-theme" class="admin-swal-input" type="text" value="${escapeHtml(existing?.theme ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-year">Contest year</label>
                    <input id="ct-year" class="admin-swal-input" type="number" min="2000" max="2100" value="${escapeHtml(existing?.contest_year != null ? String(existing.contest_year) : String(new Date().getFullYear()))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-start">Submission starts</label>
                    <input id="ct-start" class="admin-swal-input" type="date" value="${escapeHtml(formatDateInput(existing?.submission_starts_at))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-end">Submission ends</label>
                    <input id="ct-end" class="admin-swal-input" type="date" value="${escapeHtml(formatDateInput(existing?.submission_ends_at))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-status">Status *</label>
                    <select id="ct-status" class="admin-swal-input">${statusOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-active">Visibility *</label>
                    <select id="ct-active" class="admin-swal-input">
                        <option value="1" ${activeValue === '1' ? 'selected' : ''}>Active in app</option>
                        <option value="0" ${activeValue === '0' ? 'selected' : ''}>Hidden</option>
                    </select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="ct-description">Description</label>
                    <textarea id="ct-description" class="admin-swal-input" rows="3">${escapeHtml(existing?.description ?? '')}</textarea>
                </div>
            </div>
            <div class="admin-swal-rules mt-4">
                <p class="admin-swal-rules-title">Contest rules</p>
                <p class="admin-swal-hint mb-2">These rules are shown to users on the contest detail screen.</p>
                <textarea id="ct-rules" class="admin-swal-textarea" rows="5" placeholder="List the contest rules...">${escapeHtml(existing?.rules ?? '')}</textarea>
            </div>
        </div>
    `;
}

function syncAllowedFieldVisibility() {
    const category = document.getElementById('ct-category')?.value;
    const wrap = document.getElementById('ct-allowed-wrap');
    if (wrap) {
        wrap.style.display = category === 'song' ? '' : 'none';
    }
}

function showContestForm(existing) {
    const isEdit = Boolean(existing);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Contest' : 'Add Contest',
        subtitle: 'Choose Song, Poster, or Video category, then configure the contest.',
        html: buildContestFormHtml(existing),
        confirmButtonText: isEdit ? 'Save changes' : 'Create contest',
        cancelButtonText: 'Cancel',
        width: '42rem',
        didOpen: () => {
            syncAllowedFieldVisibility();
            document.getElementById('ct-category')?.addEventListener('change', syncAllowedFieldVisibility);
        },
        preConfirm: () => {
            const title = document.getElementById('ct-title')?.value.trim();
            const status = document.getElementById('ct-status')?.value;
            const category = document.getElementById('ct-category')?.value;
            const yearRaw = document.getElementById('ct-year')?.value.trim();
            const start = document.getElementById('ct-start')?.value || null;
            const end = document.getElementById('ct-end')?.value || null;

            if (!title || !status || !category) {
                Swal.showValidationMessage('Category, title, and status are required.');
                return false;
            }

            if (start && end && end < start) {
                Swal.showValidationMessage('End date must be on or after start date.');
                return false;
            }

            return {
                category,
                title,
                theme: document.getElementById('ct-theme')?.value.trim() || null,
                allowed_entry_types: category === 'song'
                    ? (document.getElementById('ct-allowed')?.value || 'both')
                    : null,
                contest_year: yearRaw ? Number(yearRaw) : null,
                submission_starts_at: start,
                submission_ends_at: end,
                status,
                description: document.getElementById('ct-description')?.value.trim() || null,
                rules: document.getElementById('ct-rules')?.value.trim() || null,
                is_active: document.getElementById('ct-active')?.value === '1',
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;

        const request = isEdit
            ? axios.put(`/admin/contests/${existing.id}`, value)
            : axios.post('/admin/contests', value);

        request.then(({ data }) => {
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            loadContests();
        }).catch(({ response }) => {
            const errors = response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || response?.data?.message || 'Operation failed.');
        });
    });
}

function entryMediaHtml(contest, entry) {
    if (contest?.category === 'poster' && entry.poster_image_url) {
        return `<p class="mt-2 text-xs"><a class="text-[#055498] underline" href="${escapeAttribute(entry.poster_image_url)}" target="_blank" rel="noopener noreferrer">Open poster</a></p>`;
    }
    if (contest?.category === 'video' && (entry.video_url || entry.media_url)) {
        const url = entry.video_url || entry.media_url;
        return `<p class="mt-2 text-xs"><a class="text-[#055498] underline" href="${escapeAttribute(url)}" target="_blank" rel="noopener noreferrer">Open YouTube / video</a></p>`;
    }
    if (entry.media_url) {
        return `<p class="mt-2 text-xs"><a class="text-[#055498] underline" href="${escapeAttribute(entry.media_url)}" target="_blank" rel="noopener noreferrer">Open media</a></p>`;
    }
    return '';
}

function showSubmissionsModal(contest, entries) {
    const rows = entries.length
        ? entries.map((entry) => `
            <div class="rounded-xl border border-slate-200 p-3 text-left">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">${escapeHtml(entry.title)}</p>
                        <p class="text-xs text-slate-500">${escapeHtml(entry.creator_name)} · ${escapeHtml(entry.user?.name ?? 'User')}</p>
                    </div>
                    ${statusBadge(entry.status)}
                </div>
                <p class="mt-2 text-xs text-slate-600">${escapeHtml(entry.description ?? 'No description')}</p>
                ${entryMediaHtml(contest, entry)}
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="admin-table-action admin-table-action-primary" onclick="window.Contests.reviewEntry(${entry.id}, ${contest.id}, 'approved')">Approve</button>
                    <button type="button" class="admin-table-action" onclick="window.Contests.reviewEntry(${entry.id}, ${contest.id}, 'finalist')">Finalist</button>
                    <button type="button" class="admin-table-action admin-table-action-danger" onclick="window.Contests.reviewEntry(${entry.id}, ${contest.id}, 'rejected')">Reject</button>
                    <button type="button" class="admin-primary-button" style="padding:0.35rem 0.75rem;font-size:0.75rem;" onclick="window.Contests.setWinner(${entry.id}, ${contest.id})">Set Winner</button>
                </div>
            </div>
        `).join('')
        : '<p class="text-sm text-slate-500">No submissions yet for this contest.</p>';

    Swal.fire(buildSwalOptions({
        title: `Submissions · ${contest?.title ?? 'Contest'}`,
        html: `<div class="space-y-3 text-left">${rows}</div>`,
        confirmButtonText: 'Done',
        cancelButtonText: 'Cancel',
        width: '48rem',
    }));
}

export function reviewEntry(entryId, contestId, status) {
    axios.post(`/admin/contests/entries/${entryId}/review`, {
        status,
        admin_notes: null,
    }).then(({ data }) => {
        showSuccessToast(data.message, 'Reviewed');
        reviewSubmissions(contestId);
    }).catch(({ response }) => {
        showErrorToast(response?.data?.message ?? 'Review failed.');
    });
}

export function setWinner(entryId, contestId) {
    Swal.fire(buildSwalOptions({
        icon: 'question',
        title: 'Set as Winner?',
        html: '<p class="admin-swal-description">This will mark the contest completed and demote any previous winner to finalist.</p>',
        confirmButtonText: 'Yes, set winner',
        cancelButtonText: 'Cancel',
    }, { size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.post(`/admin/contests/entries/${entryId}/winner`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Winner set');
                loadContests();
                reviewSubmissions(contestId);
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Failed to set winner.');
            });
    });
}

window.Contests = {
    create: createContest,
    edit: editContest,
    remove: removeContest,
    reviewSubmissions,
    reviewEntry,
    setWinner,
};

async function initializeContestsTable(tableEl) {
    const nextMode = getTableMode();

    if (!contestsDataTableClass) {
        return;
    }

    if (contestsTable && contestsTableMode === nextMode) {
        return;
    }

    if (contestsTable) {
        contestsTable.destroy();
        tableEl.innerHTML = '';
    }

    contestsTableMode = nextMode;
    contestsTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search contests:',
        searchPlaceholder: 'Search contests',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ contests',
        columns: buildColumns(nextMode),
        pageLength: nextMode === 'mobile' ? 5 : 10,
        scrollX: nextMode !== 'mobile',
        scrollCollapse: nextMode !== 'mobile',
    }));
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 767px)');
    const handleViewportChange = async () => {
        const nextMode = getTableMode();
        if (nextMode === contestsTableMode) return;
        await initializeContestsTable(tableEl);
        loadContests();
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
            { title: 'Contest', className: 'dt-col-mobile-summary' },
            { title: 'Actions', orderable: false, className: 'dt-col-actions' },
        ];
    }

    return [
        { title: 'ID', className: 'dt-col-id' },
        { title: 'Category', className: 'dt-col-nowrap' },
        { title: 'Title', className: 'dt-col-primary dt-col-name' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Entries', className: 'dt-col-nowrap' },
        { title: 'Pending', className: 'dt-col-nowrap' },
        { title: 'Year', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(contest) {
    const actions = [
        {
            tooltip: 'Review submissions',
            icon: 'fas fa-inbox',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.Contests.reviewSubmissions(${contest.id})"`,
        },
        {
            tooltip: 'Edit contest',
            icon: 'fas fa-pen-to-square',
            attrs: `onclick="window.Contests.edit(${contest.id})"`,
        },
        {
            tooltip: 'Delete contest',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.Contests.remove(${contest.id})"`,
        },
    ];

    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons(actions.map((action) => ({
        ...action,
        label: action.tooltip.split(' ')[0],
    })), { isMobile: true, nowrap: true });

    if (contestsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${escapeHtml(categoryLabel(contest.category))} #${escapeHtml(String(contest.id))}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(contest.title)}</p>
                    </div>
                    ${statusBadge(contest.status)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Entries:</span> ${escapeHtml(String(contest.entries_count ?? 0))}</p>
                    <p><span>Pending:</span> ${escapeHtml(String(contest.pending_count ?? 0))}</p>
                    <p><span>Year:</span> ${escapeHtml(contest.contest_year != null ? String(contest.contest_year) : '—')}</p>
                </div>
            </div>`,
            mobileActions,
        ];
    }

    return [
        contest.id,
        escapeHtml(categoryLabel(contest.category)),
        escapeHtml(contest.title),
        statusBadge(contest.status),
        escapeHtml(String(contest.entries_count ?? 0)),
        escapeHtml(String(contest.pending_count ?? 0)),
        escapeHtml(contest.contest_year != null ? String(contest.contest_year) : '—'),
        desktopActions,
    ];
}

function formatDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function escapeAttribute(value) {
    return escapeHtml(value);
}

function statusBadge(status) {
    const label = String(status ?? 'unknown');
    const tone = {
        open: 'rehab-status-badge-active',
        approved: 'rehab-status-badge-active',
        winner: 'rehab-status-badge-active',
        pending: '',
        closed: '',
        finalist: '',
        draft: 'rehab-status-badge-inactive',
        rejected: 'rehab-status-badge-inactive',
        completed: 'rehab-status-badge-active',
    }[label] ?? '';

    return `<span class="admin-status-badge rehab-status-badge ${tone}">${escapeHtml(label)}</span>`;
}
