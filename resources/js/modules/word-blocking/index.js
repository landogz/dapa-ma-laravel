import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildSwalForm, buildSwalOptions, confirmWithSwal } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { applyAdminFilterTabCounts, bindAdminFilterTabs } from '../shared/filter-tabs';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let wordsTable;
let wordsTableMode;
let hasBoundViewportListener = false;
let currentStatusFilter = '';

export function initWordBlockingModule() {
    const tableEl = document.getElementById('word-blocking-table');
    if (!tableEl) {
        return;
    }

    document.querySelector('[data-admin-action="create-blocked-word"]')
        ?.addEventListener('click', () => createBlockedWord());

    bindAdminFilterTabs(document.getElementById('word-blocking-status-tabs'), (value) => {
        currentStatusFilter = value;
        loadBlockedWords();
    });

    initializeTable(tableEl).then(() => {
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadBlockedWords();
    });
}

async function initializeTable(tableElement) {
    const nextMode = getTableMode();
    if (wordsTable && wordsTableMode === nextMode) {
        return;
    }
    if (wordsTable) {
        wordsTable.destroy();
        tableElement.innerHTML = '';
    }
    wordsTableMode = nextMode;
    wordsTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: buildColumns(nextMode),
            searchPlaceholder: 'Search words...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ words',
            pageLength: nextMode === 'mobile' ? 8 : 25,
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
        await initializeTable(tableEl);
        loadBlockedWords();
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
        return [{ title: 'Word', className: 'dt-col-mobile-summary', orderable: false }];
    }
    return [
        { title: 'Word', className: 'dt-col-primary dt-col-name' },
        { title: 'Status', className: 'dt-col-nowrap', orderable: false },
        { title: 'Updated', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

export function loadBlockedWords() {
    if (!wordsTable) {
        return;
    }

    const params = { per_page: 500 };
    if (currentStatusFilter !== '') {
        params.is_active = currentStatusFilter;
    }

    axios.get('/admin/blocked-words', { params })
        .then(({ data }) => {
            const rows = data.data?.data ?? data.data ?? [];
            const list = Array.isArray(rows) ? rows : [];
            wordsTable.clear();
            list.forEach((row) => wordsTable.row.add(buildRowData(row)));
            wordsTable.draw();
            applyAdminFilterTabCounts(
                document.getElementById('word-blocking-status-tabs'),
                data.meta?.status_counts || {},
            );
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Unable to load blocked words.');
        });
}

export function createBlockedWord() {
    showWordForm(null);
}

export function editBlockedWord(id) {
    axios.get(`/admin/blocked-words/${id}`)
        .then(({ data }) => showWordForm(data.data))
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Unable to load this word.');
        });
}

export async function removeBlockedWord(id) {
    const confirmed = await confirmWithSwal({
        icon: 'warning',
        title: 'Delete this word?',
        text: 'The mobile app will stop masking it after the next sync.',
        confirmButtonText: 'Delete',
        danger: true,
    });
    if (!confirmed.isConfirmed) {
        return;
    }

    axios.delete(`/admin/blocked-words/${id}`)
        .then(({ data }) => {
            showSuccessToast(data.message ?? 'Blocked word deleted.', 'Deleted');
            loadBlockedWords();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Delete failed.');
        });
}

function buildRowData(row) {
    const word = escapeHtml(row.word ?? '');
    const status = row.is_active
        ? '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>'
        : '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';
    const updated = formatDateTime(row.updated_at);
    const actions = [
        {
            tooltip: 'Edit word',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.WordBlocking.edit(${row.id})"`,
        },
        {
            tooltip: 'Delete word',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.WordBlocking.remove(${row.id})"`,
        },
    ];
    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    if (wordsTableMode === 'mobile') {
        return [`
            <div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Blocked word</p>
                        <p class="admin-table-mobile-title">${word || '—'}</p>
                    </div>
                    ${status}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Updated:</span> ${escapeHtml(updated)}</p>
                </div>
                <div class="admin-table-mobile-actions">${mobileActions}</div>
            </div>
        `];
    }

    return [word, status, escapeHtml(updated), desktopActions];
}

function showWordForm(existing) {
    const isEdit = Boolean(existing);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit blocked word' : 'Add blocked word',
        html: buildSwalForm({
            description: 'The app replaces this whole word with **** in comments, journal notes, profiles, and other user text.',
            fields: [
                {
                    id: 'blocked-word-value',
                    label: 'Word *',
                    value: existing?.word ?? '',
                    placeholder: 'example',
                },
                {
                    id: 'blocked-word-active',
                    label: 'Active',
                    type: 'select',
                    value: existing?.is_active === false ? '0' : '1',
                    options: [
                        { value: '1', label: 'Active' },
                        { value: '0', label: 'Inactive' },
                    ],
                },
            ],
        }),
        confirmButtonText: isEdit ? 'Save word' : 'Add word',
        preConfirm: () => {
            const word = document.getElementById('blocked-word-value')?.value.trim() ?? '';
            if (word.length < 2) {
                Swal.showValidationMessage('Enter a word with at least 2 characters.');
                return false;
            }
            return {
                word,
                is_active: document.getElementById('blocked-word-active')?.value === '1' ? '1' : '0',
            };
        },
    }, { size: 'md' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) {
            return;
        }
        const request = isEdit
            ? axios.put(`/admin/blocked-words/${existing.id}`, value)
            : axios.post('/admin/blocked-words', value);

        request.then(({ data }) => {
            showSuccessToast(data.message ?? (isEdit ? 'Blocked word updated.' : 'Blocked word added.'), 'Saved');
            loadBlockedWords();
        }).catch(({ response }) => {
            const errors = response?.data?.errors ?? {};
            const first = Object.values(errors)[0];
            const message = Array.isArray(first) ? first[0] : (response?.data?.message ?? 'Unable to save this word.');
            showErrorToast(message);
        });
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

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.WordBlocking = {
    edit: editBlockedWord,
    remove: removeBlockedWord,
    reload: loadBlockedWords,
};
