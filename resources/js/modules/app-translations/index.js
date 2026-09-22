import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let translationsTable;
let translationsDataTableClass;
let translationsTableMode;
let hasBoundViewportListener = false;
let translationsGroupFilter = '';
let translationGroups = [];

export function initAppTranslationsModule() {
    const tableEl = document.getElementById('app-translations-table');
    if (!tableEl) return;

    loadAdminDataTableLibrary().then(async (DataTable) => {
        translationsDataTableClass = DataTable;
        await initializeTranslationsTable(tableEl);
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadAppTranslations();
    });
}

export function loadAppTranslations(search = '') {
    if (!translationsTable) return;

    axios.get('/admin/app-translations', {
        params: {
            search,
            per_page: 500,
            ...(translationsGroupFilter ? { group: translationsGroupFilter } : {}),
        },
    })
        .then(({ data }) => {
            translationGroups = data.meta?.groups ?? [];
            mountTranslationsToolbarFilters(document.getElementById('app-translations-table'));
            const rows = data.data?.data ?? [];
            translationsTable.clear();
            rows.forEach((item) => {
                translationsTable.row.add(buildRowData(item));
            });
            translationsTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load translations.');
        });
}

export function createAppTranslation() {
    showTranslationForm(null);
}

export function editAppTranslation(id) {
    axios.get(`/admin/app-translations/${id}`).then(({ data }) => {
        showTranslationForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load translation.');
    });
}

export function removeAppTranslation(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete translation?',
        html: '<p class="admin-swal-description">This removes the string key from the mobile catalog.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/app-translations/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Deleted');
                loadAppTranslations();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function mountTranslationsToolbarFilters(tableEl) {
    if (!tableEl) return;

    const container = tableEl.closest('.dt-container');
    if (!container) return;

    const layoutEnd = [...container.querySelectorAll('.dt-layout-end')]
        .find((el) => el.querySelector('.dt-search'));

    if (!layoutEnd) return;

    layoutEnd.querySelector('#app-translations-toolbar-filters')?.remove();
    layoutEnd.classList.add('admin-dt-toolbar-filters-host');

    const groupOptions = [
        `<option value="">All groups</option>`,
        ...translationGroups.map((group) => `
            <option value="${escapeHtml(group)}" ${translationsGroupFilter === group ? 'selected' : ''}>
                ${escapeHtml(formatGroupLabel(group))}
            </option>
        `),
    ].join('');

    const filters = document.createElement('div');
    filters.id = 'app-translations-toolbar-filters';
    filters.className = 'admin-dt-filters';
    filters.innerHTML = `
        <label class="sr-only" for="app-translations-group-filter">Filter by group</label>
        <select id="app-translations-group-filter" class="admin-filter-select admin-filter-select-inline" aria-label="Filter by group">
            ${groupOptions}
        </select>
    `;

    const search = layoutEnd.querySelector('.dt-search');
    if (search) {
        layoutEnd.insertBefore(filters, search);
    } else {
        layoutEnd.prepend(filters);
    }

    const groupFilterEl = document.getElementById('app-translations-group-filter');
    if (groupFilterEl && !groupFilterEl.dataset.bound) {
        groupFilterEl.dataset.bound = '1';
        groupFilterEl.addEventListener('change', () => {
            translationsGroupFilter = groupFilterEl.value || '';
            loadAppTranslations();
        });
    }
}

function buildFormHtml(existing) {
    const statusValue = existing?.is_active === false ? '0' : '1';
    const groupOptions = ['', ...new Set([...translationGroups, existing?.group].filter(Boolean))]
        .map((group) => {
            const selected = (existing?.group ?? '') === group ? 'selected' : '';
            const label = group ? formatGroupLabel(group) : 'Select group';
            return `<option value="${escapeHtml(group)}" ${selected}>${escapeHtml(label)}</option>`;
        })
        .join('');

    return `
        <div class="admin-swal-form">
            <p class="admin-swal-description">Edit the English and Tagalog copy shown in the mobile app for this key.</p>
            <div class="admin-swal-fields">
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-key">Key *</label>
                    <input id="tr-key" class="admin-swal-input" type="text" placeholder="e.g. navHome" value="${escapeHtml(existing?.key ?? '')}" ${existing?.id ? 'disabled' : ''}>
                    <p class="mt-1 text-xs text-slate-500">Must match the Flutter AppStrings getter name. Locked after create.</p>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-group">Group</label>
                    <select id="tr-group" class="admin-swal-input">${groupOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-group-custom">Or custom group</label>
                    <input id="tr-group-custom" class="admin-swal-input" type="text" placeholder="Optional custom group slug" value="">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-en">English *</label>
                    <textarea id="tr-en" class="admin-swal-input" rows="3">${escapeHtml(existing?.value_en ?? '')}</textarea>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-tl">Tagalog *</label>
                    <textarea id="tr-tl" class="admin-swal-input" rows="3">${escapeHtml(existing?.value_tl ?? '')}</textarea>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-description">Admin note</label>
                    <input id="tr-description" class="admin-swal-input" type="text" placeholder="Optional hint for editors" value="${escapeHtml(existing?.description ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-active">Status *</label>
                    <select id="tr-active" class="admin-swal-input">
                        <option value="1" ${statusValue === '1' ? 'selected' : ''}>Active</option>
                        <option value="0" ${statusValue === '0' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    `;
}

function collectPayload() {
    const customGroup = document.getElementById('tr-group-custom')?.value?.trim() || '';
    const selectedGroup = document.getElementById('tr-group')?.value?.trim() || '';

    return {
        key: document.getElementById('tr-key')?.value?.trim() ?? '',
        group: customGroup || selectedGroup || '',
        description: document.getElementById('tr-description')?.value?.trim() || '',
        value_en: document.getElementById('tr-en')?.value?.trim() ?? '',
        value_tl: document.getElementById('tr-tl')?.value?.trim() ?? '',
        is_active: document.getElementById('tr-active')?.value === '1',
    };
}

function showTranslationForm(existing) {
    const isEdit = Boolean(existing?.id);
    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit translation' : 'Add translation',
        html: buildFormHtml(existing),
        confirmButtonText: isEdit ? 'Save changes' : 'Create',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const payload = collectPayload();
            if (!isEdit && !payload.key) {
                Swal.showValidationMessage('Key is required.');
                return false;
            }
            if (!payload.value_en || !payload.value_tl) {
                Swal.showValidationMessage('English and Tagalog values are required.');
                return false;
            }
            return payload;
        },
    }, { size: 'lg' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) return;

        const request = isEdit
            ? axios.put(`/admin/app-translations/${existing.id}`, value)
            : axios.post('/admin/app-translations', value);

        request.then(({ data }) => {
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            loadAppTranslations();
        }).catch(({ response }) => {
            const errors = response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || response?.data?.message || 'Save failed.');
        });
    });
}

async function initializeTranslationsTable(tableEl) {
    const nextMode = getTableMode();

    if (!translationsDataTableClass) {
        return;
    }

    if (translationsTable && translationsTableMode === nextMode) {
        mountTranslationsToolbarFilters(tableEl);
        return;
    }

    if (translationsTable) {
        translationsTable.destroy();
        tableEl.innerHTML = '';
    }

    translationsTableMode = nextMode;
    translationsTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search translations:',
        searchPlaceholder: 'Search key, English, or Tagalog',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ translations',
        columns: buildColumns(nextMode),
        pageLength: nextMode === 'mobile' ? 5 : 10,
        scrollX: nextMode !== 'mobile',
        scrollCollapse: nextMode !== 'mobile',
    }));
    mountTranslationsToolbarFilters(tableEl);
    bindAdminActionTooltipSuppression(tableEl);
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 767px)');
    const handleViewportChange = async () => {
        const nextMode = getTableMode();
        if (nextMode === translationsTableMode) {
            return;
        }
        await initializeTranslationsTable(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadAppTranslations();
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
            { title: 'Translation', className: 'dt-col-mobile-summary' },
            { title: 'Actions', orderable: false, className: 'dt-col-actions' },
        ];
    }

    return [
        { title: 'Key', className: 'dt-col-primary dt-col-name' },
        { title: 'Group', className: 'dt-col-nowrap' },
        { title: 'English', className: 'dt-col-primary' },
        { title: 'Tagalog', className: 'dt-col-primary' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(item) {
    const actions = [
        {
            tooltip: 'Edit translation',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.AppTranslations.edit(${item.id})"`,
        },
        {
            tooltip: 'Delete translation',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.AppTranslations.remove(${item.id})"`,
        },
    ];

    const desktopActionsMarkup = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActionsMarkup = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    const enPreview = truncateText(item.value_en ?? '', 80);
    const tlPreview = truncateText(item.value_tl ?? '', 80);

    if (translationsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div class="min-w-0">
                        <p class="admin-table-mobile-kicker">${escapeHtml(formatGroupLabel(item.group ?? 'general'))}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(item.key)}</p>
                    </div>
                    ${statusBadge(item.is_active)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>EN:</span> ${escapeHtml(enPreview)}</p>
                    <p><span>TL:</span> ${escapeHtml(tlPreview)}</p>
                </div>
            </div>`,
            mobileActionsMarkup,
        ];
    }

    return [
        escapeHtml(item.key ?? ''),
        escapeHtml(formatGroupLabel(item.group ?? '—')),
        escapeHtml(enPreview),
        escapeHtml(tlPreview),
        statusBadge(item.is_active),
        desktopActionsMarkup,
    ];
}

function formatGroupLabel(group) {
    if (!group || group === '—') return '—';
    return String(group)
        .split('_')
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function truncateText(value, max = 80) {
    const text = String(value ?? '');
    if (text.length <= max) return text;
    return `${text.slice(0, max - 1)}…`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function statusBadge(isActive) {
    if (isActive) {
        return '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>';
    }

    return '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';
}

window.AppTranslations = {
    create: createAppTranslation,
    edit: editAppTranslation,
    remove: removeAppTranslation,
    load: loadAppTranslations,
};
