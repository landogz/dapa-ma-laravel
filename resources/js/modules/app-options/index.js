import axios from 'axios';
import Swal from 'sweetalert2';
import {
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
    formatApiValidationMessage,
} from '../shared/swal-forms';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let optionsTable;
let optionsTableMode;
let hasBoundViewportListener = false;
let currentGroupFilter = '';
let groupMeta = [];
let iconChoices = [];

export function initAppOptionsModule() {
    const tableEl = document.getElementById('app-options-table');
    if (!tableEl) {
        return;
    }

    document.querySelector('[data-admin-action="create-app-option"]')
        ?.addEventListener('click', () => createAppOption());

    document.getElementById('app-option-group-filter')
        ?.addEventListener('change', (event) => {
            currentGroupFilter = event.target.value || '';
            loadAppOptions();
        });

    initializeOptionsTable(tableEl).then(() => {
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadAppOptions();
    });
}

async function initializeOptionsTable(tableElement) {
    const nextMode = getTableMode();

    if (optionsTable && optionsTableMode === nextMode) {
        return;
    }

    if (optionsTable) {
        optionsTable.destroy();
        tableElement.innerHTML = '';
    }

    optionsTableMode = nextMode;
    optionsTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: buildColumns(nextMode),
            searchPlaceholder: 'Search options...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ options',
            pageLength: nextMode === 'mobile' ? 6 : 25,
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
        if (nextMode === optionsTableMode) {
            return;
        }

        await initializeOptionsTable(tableEl);
        loadAppOptions();
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
            { title: 'Option', className: 'dt-col-mobile-summary', orderable: false },
        ];
    }

    return [
        { title: 'Order', width: '64px', className: 'dt-col-nowrap' },
        { title: 'Catalog', className: 'dt-col-nowrap' },
        { title: 'Label', className: 'dt-col-primary dt-col-wide' },
        { title: 'Key', className: 'dt-col-nowrap' },
        { title: 'Selection', className: 'dt-col-nowrap' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

function fillGroupFilter() {
    const select = document.getElementById('app-option-group-filter');
    if (!select || select.dataset.filled === 'true' || groupMeta.length === 0) {
        return;
    }

    groupMeta.forEach((group) => {
        const option = document.createElement('option');
        option.value = group.key;
        option.textContent = group.title;
        select.appendChild(option);
    });
    select.dataset.filled = 'true';
}

export function loadAppOptions() {
    if (!optionsTable) {
        return;
    }

    axios.get('/admin/app-options', {
        params: {
            per_page: 500,
            group: currentGroupFilter || undefined,
        },
    })
        .then(({ data }) => {
            groupMeta = data.meta?.groups ?? groupMeta;
            iconChoices = data.meta?.icons ?? iconChoices;
            fillGroupFilter();

            const rows = data.data?.data ?? [];
            optionsTable.clear();
            rows.forEach((row) => optionsTable.row.add(buildRowData(row)));
            optionsTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load admin settings.');
        });
}

export function createAppOption() {
    showOptionForm(null);
}

export function editAppOption(id) {
    axios.get(`/admin/app-options/${id}`)
        .then(({ data }) => showOptionForm(data.data))
        .catch(() => showErrorToast('Failed to load option.'));
}

export async function removeAppOption(id) {
    const confirmed = await confirmWithSwal({
        icon: 'warning',
        title: 'Delete this option?',
        text: 'It will no longer appear in the mobile app. Existing answers keep their saved value.',
        confirmButtonText: 'Delete',
        danger: true,
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const { data } = await axios.delete(`/admin/app-options/${id}`);
        showSuccessToast(data.message ?? 'Option deleted.', 'Deleted');
        loadAppOptions();
    } catch ({ response }) {
        showErrorToast(response?.data?.message ?? 'Delete failed.');
    }
}

function buildRowData(row) {
    const label = escapeHtml(row.label_en || '—');
    const catalog = escapeHtml(row.group_title || row.group || '—');
    const key = escapeHtml(row.key || '—');
    const selection = row.selection === 'multiple' ? 'Multiple' : 'Single';
    const status = row.is_active
        ? '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>'
        : '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';

    const actions = [
        {
            tooltip: 'Edit option',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.AppOptions.edit(${row.id})"`,
        },
        {
            tooltip: 'Delete option',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.AppOptions.remove(${row.id})"`,
        },
    ];

    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    if (optionsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${catalog}</p>
                        <p class="admin-table-mobile-title">${label}</p>
                    </div>
                    ${status}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Key:</span> ${key}</p>
                    <p><span>Order:</span> ${escapeHtml(String(row.sort_order ?? 0))}</p>
                    <p><span>Selection:</span> ${escapeHtml(selection)}</p>
                </div>
                <div class="admin-table-mobile-actions">${mobileActions}</div>
            </div>`,
        ];
    }

    return [
        String(row.sort_order ?? 0),
        catalog,
        label,
        key,
        escapeHtml(selection),
        status,
        desktopActions,
    ];
}

function showOptionForm(existing) {
    const isEdit = Boolean(existing);
    const groupOptions = (groupMeta.length ? groupMeta : [{ key: 'pronouns', title: 'Pronouns' }])
        .map((group) => ({ value: group.key, label: group.title }));
    const icons = [{ value: '', label: 'None' }].concat(
        iconChoices.map((icon) => ({ value: icon.value, label: icon.label })),
    );
    const defaultGroup = existing?.group || currentGroupFilter || groupMeta[0]?.key || 'persona';
    const fields = [
        {
            id: 'ao-group',
            label: 'Catalog *',
            type: 'select',
            value: defaultGroup,
            options: groupOptions,
        },
        {
            id: 'ao-label-en',
            label: 'Label (English) *',
            value: existing?.label_en ?? '',
        },
        {
            id: 'ao-label-tl',
            label: 'Label (Tagalog) *',
            value: existing?.label_tl ?? '',
        },
    ];

    if (!isEdit) {
        fields.push({
            id: 'ao-key',
            label: 'Key',
            value: '',
            placeholder: 'Auto from English label if blank',
            hint: 'Stored with user answers. Use letters, numbers, _ or -.',
        });
    }

    fields.push(
        {
            id: 'ao-subtitle-en',
            label: 'Subtitle (English)',
            value: existing?.subtitle_en ?? '',
            placeholder: 'Used on sky / optional helper text',
        },
        {
            id: 'ao-subtitle-tl',
            label: 'Subtitle (Tagalog)',
            value: existing?.subtitle_tl ?? '',
        },
        {
            id: 'ao-icon',
            label: 'Icon',
            type: 'select',
            value: existing?.icon ?? '',
            options: icons,
        },
        {
            id: 'ao-accent',
            label: 'Accent color',
            value: existing?.accent_color ?? '',
            placeholder: '#055498',
        },
        {
            id: 'ao-sort-order',
            label: 'Sort order',
            value: existing?.sort_order != null ? String(existing.sort_order) : '',
            placeholder: 'Auto if blank',
        },
        {
            id: 'ao-is-active',
            label: 'Status *',
            type: 'select',
            value: existing?.is_active === false ? '0' : '1',
            options: [
                { value: '1', label: 'Active' },
                { value: '0', label: 'Inactive' },
            ],
        },
    );

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit option' : 'Add option',
        html: buildSwalForm({
            description: isEdit
                ? `Key “${existing.key}” stays the same so existing answers still match. English and Tagalog labels appear in the mobile app.`
                : 'English and Tagalog labels appear in the mobile app. Leave Key blank to generate it from the English label.',
            fields,
        }),
        confirmButtonText: isEdit ? 'Save changes' : 'Create option',
        showCancelButton: true,
        preConfirm: () => {
            const group = document.getElementById('ao-group')?.value;
            const labelEn = document.getElementById('ao-label-en')?.value?.trim() ?? '';
            const labelTl = document.getElementById('ao-label-tl')?.value?.trim() ?? '';
            const key = isEdit
                ? (existing.key ?? '')
                : (document.getElementById('ao-key')?.value?.trim() ?? '');
            const sortRaw = document.getElementById('ao-sort-order')?.value?.trim() ?? '';
            const accent = document.getElementById('ao-accent')?.value?.trim() ?? '';

            if (!labelEn || !labelTl) {
                Swal.showValidationMessage('English and Tagalog labels are required.');
                return false;
            }

            const payload = {
                group,
                key: key || undefined,
                label_en: labelEn,
                label_tl: labelTl,
                subtitle_en: document.getElementById('ao-subtitle-en')?.value?.trim() || null,
                subtitle_tl: document.getElementById('ao-subtitle-tl')?.value?.trim() || null,
                icon: document.getElementById('ao-icon')?.value || null,
                accent_color: accent || null,
                is_active: document.getElementById('ao-is-active')?.value === '1',
            };

            if (sortRaw !== '') {
                payload.sort_order = Number.parseInt(sortRaw, 10);
            }

            const request = isEdit
                ? axios.put(`/admin/app-options/${existing.id}`, payload)
                : axios.post('/admin/app-options', payload);

            return request
                .then(({ data }) => data)
                .catch((error) => {
                    Swal.showValidationMessage(
                        formatApiValidationMessage(
                            error.response,
                            error.response?.data?.message ?? 'Unable to save option.',
                        ),
                    );
                    return false;
                });
        },
    }, { size: 'lg' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) {
            return;
        }
        showSuccessToast(value.message ?? 'Option saved.', 'Admin Settings');
        loadAppOptions();
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

window.AppOptions = {
    create: createAppOption,
    edit: editAppOption,
    remove: removeAppOption,
    reload: loadAppOptions,
};
