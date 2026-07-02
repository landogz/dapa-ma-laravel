import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { buildSwalOptions } from './shared/swal-forms';
import { showErrorToast, showSuccessToast } from './shared/toast';

let diaryTable;
const diaryCache = new Map();

export function initDiaryModule() {
    const tableElement = document.getElementById('diary-table');

    if (!tableElement) {
        return;
    }

    if (diaryTable) {
        loadDiaryEntries();
        return;
    }

    initializeDiaryTable(tableElement).then(() => {
        loadDiaryEntries();
    });
}

function loadDiaryEntries() {
    if (!diaryTable) {
        return;
    }

    axios
        .get('/admin/diary-entries', { params: { per_page: 200 } })
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
                error.response?.data?.message ?? 'Unable to load diary entries.',
                'My Diary',
            );
        });
}

async function initializeDiaryTable(tableElement) {
    diaryTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: [
                { title: 'Date' },
                { title: 'User' },
                { title: 'Title' },
                { title: 'Preview' },
                { title: 'Updated' },
                { title: 'Actions', orderable: false, searchable: false },
            ],
            searchPlaceholder: 'Search diary entries...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ diary entries',
            pageLength: 10,
        }),
    );
}

function buildDiaryRowData(entry) {
    const userName = escapeHtml(entry.user?.name ?? 'Unknown user');
    const userEmail = escapeHtml(entry.user?.email ?? '');
    const title = escapeHtml(entry.title?.trim() || '—');
    const preview = escapeHtml(stripHtml(entry.body_html ?? '').slice(0, 120));
    const entryDate = formatDate(entry.entry_date);
    const updatedAt = formatDateTime(entry.updated_at ?? entry.created_at);

    return [
        entryDate,
        `<div class="min-w-[10rem]"><p class="font-semibold text-slate-800">${userName}</p><p class="text-xs text-slate-500">${userEmail}</p></div>`,
        title,
        preview || '—',
        updatedAt,
        buildActionButtons(entry.id),
    ];
}

function buildActionButtons(entryId) {
    return `
        <div class="admin-table-actions">
            <button type="button" class="admin-table-action admin-table-action-primary" data-diary-view="${entryId}" title="View entry" aria-label="View entry">
                <i class="fas fa-eye"></i>
            </button>
            <button type="button" class="admin-table-action admin-table-action-danger" data-diary-delete="${entryId}" title="Delete entry" aria-label="Delete entry">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
}

function bindDiaryActions() {
    document.querySelectorAll('[data-diary-view]').forEach((button) => {
        button.addEventListener('click', () => {
            const entryId = button.getAttribute('data-diary-view');
            const entry = diaryCache.get(String(entryId));

            if (!entry) {
                showErrorToast('Diary entry not found in the current list.', 'My Diary');
                return;
            }

            openDiaryView(entry);
        });
    });

    document.querySelectorAll('[data-diary-delete]').forEach((button) => {
        button.addEventListener('click', async () => {
            const entryId = button.getAttribute('data-diary-delete');
            const entry = diaryCache.get(String(entryId));
            const userName = entry?.user?.name ?? 'this user';

            const result = await Swal.fire(buildSwalOptions({
                icon: 'warning',
                title: 'Delete diary entry?',
                text: `This will permanently remove the diary note for ${userName}.`,
                showCancelButton: true,
                confirmButtonText: 'Delete entry',
            }, { danger: true }));

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await axios.delete(`/admin/diary-entries/${entryId}`);
                showSuccessToast(response.data.message ?? 'Diary entry deleted.');
                loadDiaryEntries();
            } catch (error) {
                showErrorToast(
                    error.response?.data?.message ?? 'Unable to delete diary entry.',
                    'My Diary',
                );
            }
        });
    });
}

function openDiaryView(entry) {
    const title = entry.title?.trim() ? escapeHtml(entry.title) : 'Diary entry';
    const userName = escapeHtml(entry.user?.name ?? 'Unknown user');
    const entryDate = formatDate(entry.entry_date);
    const bodyHtml = entry.body_html ?? '<p><em>No content</em></p>';

    Swal.fire({
        title,
        width: '48rem',
        html: `
            <div class="text-left">
                <p class="mb-1 text-sm text-slate-500"><strong>User:</strong> ${userName}</p>
                <p class="mb-4 text-sm text-slate-500"><strong>Date:</strong> ${entryDate}</p>
                <div class="diary-entry-preview rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-800">
                    ${bodyHtml}
                </div>
            </div>
        `,
        confirmButtonText: 'Close',
        confirmButtonColor: '#055498',
    });
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
