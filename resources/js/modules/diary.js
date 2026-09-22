import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { buildSwalOptions } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
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
        bindAdminActionTooltipSuppression(tableElement);
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
                error.response?.data?.message ?? 'Unable to load journal entries.',
                'My Journal',
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
            searchPlaceholder: 'Search journal entries...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ journal entries',
            pageLength: 10,
        }),
    );
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
    return buildAdminActionButtons([
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
    ], { isMobile: false, nowrap: true });
}

function bindDiaryActions() {
    document.querySelectorAll('[data-diary-view]').forEach((button) => {
        button.addEventListener('click', () => {
            const entryId = button.getAttribute('data-diary-view');
            const entry = diaryCache.get(String(entryId));

            if (!entry) {
                showErrorToast('Journal entry not found in the current list.', 'My Journal');
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
                title: 'Delete journal entry?',
                text: `This will permanently remove the journal note for ${userName}.`,
                showCancelButton: true,
                confirmButtonText: 'Delete entry',
            }, { danger: true }));

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await axios.delete(`/admin/diary-entries/${entryId}`);
                showSuccessToast(response.data.message ?? 'Journal entry deleted.');
                loadDiaryEntries();
            } catch (error) {
                showErrorToast(
                    error.response?.data?.message ?? 'Unable to delete journal entry.',
                    'My Journal',
                );
            }
        });
    });
}

function openDiaryView(entry) {
    const title = entry.title?.trim() ? escapeHtml(entry.title) : 'Journal entry';
    const userName = escapeHtml(entry.user?.name ?? 'Unknown user');
    const entryDate = formatDate(entry.entry_date);
    const bodyHtml = entry.body_html ?? '<p><em>No written notes</em></p>';
    const sky = entry.sky ? escapeHtml(String(entry.sky).replaceAll('_', ' ')) : '—';
    const impact = entry.impact ? escapeHtml(String(entry.impact).replaceAll('_', ' ')) : '—';
    const feelings = Array.isArray(entry.feelings) && entry.feelings.length
        ? escapeHtml(entry.feelings.join(', '))
        : '—';
    const gratitude = entry.gratitude
        ? escapeHtml(String(entry.gratitude))
        : '—';

    Swal.fire({
        title,
        width: '48rem',
        html: `
            <div class="text-left">
                <p class="mb-1 text-sm text-slate-500"><strong>User:</strong> ${userName}</p>
                <p class="mb-1 text-sm text-slate-500"><strong>Date:</strong> ${entryDate}</p>
                <p class="mb-1 text-sm text-slate-500"><strong>Sky:</strong> ${sky}</p>
                <p class="mb-1 text-sm text-slate-500"><strong>Feelings:</strong> ${feelings}</p>
                <p class="mb-1 text-sm text-slate-500"><strong>Impact:</strong> ${impact}</p>
                <p class="mb-4 text-sm text-slate-500"><strong>Gratitude:</strong> ${gratitude}</p>
                ${entry.image_url ? `<img src="${escapeHtml(entry.image_url)}" alt="Journal photo" class="mb-4 max-h-72 w-full rounded-xl object-cover" />` : ''}
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
