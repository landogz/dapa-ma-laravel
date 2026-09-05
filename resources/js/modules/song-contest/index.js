import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let songContestTable;
let songContestDataTableClass;
let songContestTableMode;
let hasBoundViewportListener = false;

const ENTRY_TYPE_OPTIONS = [
    { value: 'both', label: 'Song writing + Playlist' },
    { value: 'song', label: 'Song writing only' },
    { value: 'playlist', label: 'Playlist only' },
];

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'open', label: 'Open for submissions' },
    { value: 'closed', label: 'Closed (reviewing)' },
    { value: 'completed', label: 'Completed' },
];

export function initSongContestModule() {
    const tableEl = document.getElementById('song-contest-table');
    if (!tableEl) return;

    loadAdminDataTableLibrary().then(async (DataTable) => {
        songContestDataTableClass = DataTable;
        await initializeSongContestTable(tableEl);
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadSongContests();
    });
}

export function loadSongContests(search = '') {
    if (!songContestTable) return;

    axios.get('/admin/song-contest', { params: { search, per_page: 200 } })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            songContestTable.clear();
            rows.forEach((contest) => {
                songContestTable.row.add(buildRowData(contest));
            });
            songContestTable.draw();
        })
        .catch(() => {
            showErrorToast('Failed to load song contests.');
        });
}

export function createSongContest() {
    showContestForm(null);
}

export function editSongContest(id) {
    axios.get(`/admin/song-contest/${id}`).then(({ data }) => {
        showContestForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load contest details.');
    });
}

export function removeSongContest(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete Contest?',
        html: '<p class="admin-swal-description">All submissions under this contest will also be deleted.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/song-contest/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Deleted');
                loadSongContests();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

export function reviewSubmissions(contestId) {
    axios.get(`/admin/song-contest/${contestId}/entries`, { params: { per_page: 200 } })
        .then(({ data }) => {
            const contest = data.data?.contest;
            const entries = data.data?.entries?.data ?? [];
            showSubmissionsModal(contest, entries);
        })
        .catch(() => {
            showErrorToast('Failed to load submissions.');
        });
}

function buildContestFormHtml(existing) {
    const statusValue = existing?.status ?? 'open';
    const activeValue = existing?.is_active === false ? '0' : '1';
    const typeOptions = ENTRY_TYPE_OPTIONS.map((option) => {
        const selected = (existing?.allowed_entry_types ?? 'both') === option.value ? 'selected' : '';
        return `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
    }).join('');
    const statusOptions = STATUS_OPTIONS.map((option) => {
        const selected = statusValue === option.value ? 'selected' : '';
        return `<option value="${escapeHtml(option.value)}" ${selected}>${escapeHtml(option.label)}</option>`;
    }).join('');

    return `
        <div class="admin-swal-form">
            <div class="admin-swal-fields">
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-title">Contest title *</label>
                    <input id="sc-title" class="admin-swal-input" type="text" value="${escapeHtml(existing?.title ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-theme">Theme</label>
                    <input id="sc-theme" class="admin-swal-input" type="text" value="${escapeHtml(existing?.theme ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-types">Allowed entries *</label>
                    <select id="sc-types" class="admin-swal-input">${typeOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-year">Contest year</label>
                    <input id="sc-year" class="admin-swal-input" type="number" min="2000" max="2100" value="${escapeHtml(existing?.contest_year != null ? String(existing.contest_year) : String(new Date().getFullYear()))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-start">Submission starts</label>
                    <input id="sc-start" class="admin-swal-input" type="date" value="${escapeHtml(formatDateInput(existing?.submission_starts_at))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-end">Submission ends</label>
                    <input id="sc-end" class="admin-swal-input" type="date" value="${escapeHtml(formatDateInput(existing?.submission_ends_at))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-status">Status *</label>
                    <select id="sc-status" class="admin-swal-input">${statusOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-active">Visibility *</label>
                    <select id="sc-active" class="admin-swal-input">
                        <option value="1" ${activeValue === '1' ? 'selected' : ''}>Active in app</option>
                        <option value="0" ${activeValue === '0' ? 'selected' : ''}>Hidden</option>
                    </select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="sc-description">Description</label>
                    <textarea id="sc-description" class="admin-swal-input" rows="3">${escapeHtml(existing?.description ?? '')}</textarea>
                </div>
            </div>
            <div class="admin-swal-rules mt-4">
                <p class="admin-swal-rules-title">Contest rules</p>
                <p class="admin-swal-hint mb-2">These rules are shown to users on the contest detail screen.</p>
                <textarea id="sc-rules" class="admin-swal-textarea" rows="5" placeholder="List the contest rules...">${escapeHtml(existing?.rules ?? '')}</textarea>
            </div>
        </div>
    `;
}

function showContestForm(existing) {
    const isEdit = Boolean(existing);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Contest' : 'Open Contest',
        subtitle: 'Create or open a contest so mobile users can submit entries for review.',
        html: buildContestFormHtml(existing),
        confirmButtonText: isEdit ? 'Save changes' : 'Create contest',
        cancelButtonText: 'Cancel',
        width: '42rem',
        preConfirm: () => {
            const title = document.getElementById('sc-title')?.value.trim();
            const status = document.getElementById('sc-status')?.value;
            const allowed = document.getElementById('sc-types')?.value;
            const yearRaw = document.getElementById('sc-year')?.value.trim();
            const start = document.getElementById('sc-start')?.value || null;
            const end = document.getElementById('sc-end')?.value || null;

            if (!title || !status || !allowed) {
                Swal.showValidationMessage('Title, status, and allowed entry types are required.');
                return false;
            }

            if (start && end && end < start) {
                Swal.showValidationMessage('End date must be on or after start date.');
                return false;
            }

            return {
                title,
                theme: document.getElementById('sc-theme')?.value.trim() || null,
                allowed_entry_types: allowed,
                contest_year: yearRaw ? Number(yearRaw) : null,
                submission_starts_at: start,
                submission_ends_at: end,
                status,
                description: document.getElementById('sc-description')?.value.trim() || null,
                rules: document.getElementById('sc-rules')?.value.trim() || null,
                is_active: document.getElementById('sc-active')?.value === '1',
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;

        const request = isEdit
            ? axios.put(`/admin/song-contest/${existing.id}`, value)
            : axios.post('/admin/song-contest', value);

        request.then(({ data }) => {
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            loadSongContests();
        }).catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Operation failed.');
        });
    });
}

function showSubmissionsModal(contest, entries) {
    const rows = entries.length
        ? entries.map((entry) => `
            <div class="rounded-xl border border-slate-200 p-3 text-left">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">${escapeHtml(entry.title)}</p>
                        <p class="text-xs text-slate-500">${escapeHtml(entry.artist_name)} · ${escapeHtml(entry.user?.name ?? 'User')} · ${escapeHtml(entry.entry_type)}</p>
                    </div>
                    ${statusBadge(entry.status)}
                </div>
                <p class="mt-2 text-xs text-slate-600">${escapeHtml(entry.description ?? 'No description')}</p>
                ${entry.media_url ? `<p class="mt-1 text-xs"><a class="text-[#055498] underline" href="${escapeAttribute(entry.media_url)}" target="_blank" rel="noopener noreferrer">Open media</a></p>` : ''}
                ${entry.lyrics ? `<pre class="mt-2 max-h-24 overflow-auto whitespace-pre-wrap rounded-lg bg-slate-50 p-2 text-xs text-slate-700">${escapeHtml(entry.lyrics)}</pre>` : ''}
                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="button" class="admin-table-action admin-table-action-primary" onclick="window.SongContest.reviewEntry(${entry.id}, ${contest.id}, 'approved')">Approve</button>
                    <button type="button" class="admin-table-action" onclick="window.SongContest.reviewEntry(${entry.id}, ${contest.id}, 'finalist')">Finalist</button>
                    <button type="button" class="admin-table-action admin-table-action-danger" onclick="window.SongContest.reviewEntry(${entry.id}, ${contest.id}, 'rejected')">Reject</button>
                    <button type="button" class="admin-primary-button" style="padding:0.35rem 0.75rem;font-size:0.75rem;" onclick="window.SongContest.setWinner(${entry.id}, ${contest.id})">Set Winner</button>
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
    axios.post(`/admin/song-contest/entries/${entryId}/review`, {
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
        axios.post(`/admin/song-contest/entries/${entryId}/winner`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Winner set');
                loadSongContests();
                reviewSubmissions(contestId);
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Failed to set winner.');
            });
    });
}

window.SongContest = {
    create: createSongContest,
    edit: editSongContest,
    remove: removeSongContest,
    reviewSubmissions,
    reviewEntry,
    setWinner,
};

async function initializeSongContestTable(tableEl) {
    const nextMode = getTableMode();

    if (!songContestDataTableClass) {
        return;
    }

    if (songContestTable && songContestTableMode === nextMode) {
        return;
    }

    if (songContestTable) {
        songContestTable.destroy();
        tableEl.innerHTML = '';
    }

    songContestTableMode = nextMode;
    songContestTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search contests:',
        searchPlaceholder: 'Search song contests',
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
        if (nextMode === songContestTableMode) return;
        await initializeSongContestTable(tableEl);
        loadSongContests();
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
            attrs: `onclick="window.SongContest.reviewSubmissions(${contest.id})"`,
        },
        {
            tooltip: 'Edit contest',
            icon: 'fas fa-pen-to-square',
            attrs: `onclick="window.SongContest.edit(${contest.id})"`,
        },
        {
            tooltip: 'Delete contest',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.SongContest.remove(${contest.id})"`,
        },
    ];

    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons(actions.map((action) => ({
        ...action,
        label: action.tooltip.split(' ')[0],
    })), { isMobile: true, nowrap: true });

    if (songContestTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Contest #${escapeHtml(String(contest.id))}</p>
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
