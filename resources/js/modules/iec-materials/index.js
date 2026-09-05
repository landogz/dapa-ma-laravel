import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let iecMaterialsTable;
let iecMaterialsDataTableClass;
let iecMaterialsTableMode;
let hasBoundViewportListener = false;

const IEC_TOPICS = ['Prevention', 'Awareness', 'Youth', 'Family', 'Recovery'];
const IEC_MEDIA_TYPES = [
    { value: 'gif', label: 'Animated GIF' },
    { value: 'image', label: 'Image' },
    { value: 'youtube', label: 'YouTube' },
    { value: 'lottie', label: 'Lottie JSON URL' },
];

export function initIecMaterialsModule() {
    const tableEl = document.getElementById('iec-materials-table');
    if (!tableEl) return;

    loadAdminDataTableLibrary().then(async (DataTable) => {
        iecMaterialsDataTableClass = DataTable;
        await initializeIecMaterialsTable(tableEl);
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadIecMaterials();
    });
}

export function loadIecMaterials(search = '') {
    if (!iecMaterialsTable) return;

    axios.get('/admin/iec-materials', { params: { search, per_page: 200 } })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            iecMaterialsTable.clear();
            rows.forEach((item) => {
                iecMaterialsTable.row.add(buildRowData(item));
            });
            iecMaterialsTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load IEC materials.');
        });
}

export function createIecMaterial() {
    showIecMaterialForm(null);
}

export function editIecMaterial(id) {
    axios.get(`/admin/iec-materials/${id}`).then(({ data }) => {
        showIecMaterialForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load IEC material.');
    });
}

export function removeIecMaterial(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete IEC material?',
        html: '<p class="admin-swal-description">This cannot be undone.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/iec-materials/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Deleted');
                loadIecMaterials();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function buildFormHtml(existing) {
    const statusValue = existing?.is_active === false ? '0' : '1';
    const topicOptions = ['', ...IEC_TOPICS].map((topic) => {
        const selected = (existing?.topic ?? '') === topic ? 'selected' : '';
        const label = topic || 'Select topic';
        return `<option value="${escapeHtml(topic)}" ${selected}>${escapeHtml(label)}</option>`;
    }).join('');
    const mediaOptions = IEC_MEDIA_TYPES.map((type) => {
        const selected = (existing?.media_type ?? 'gif') === type.value ? 'selected' : '';
        return `<option value="${escapeHtml(type.value)}" ${selected}>${escapeHtml(type.label)}</option>`;
    }).join('');

    return `
        <div class="admin-swal-form">
            <p class="admin-swal-description">Add animated IEC content for the mobile gallery (GIF, image, YouTube, or Lottie URL).</p>
            <div class="admin-swal-fields">
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-title">Title *</label>
                    <input id="iec-title" class="admin-swal-input" type="text" value="${escapeHtml(existing?.title ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-topic">Topic</label>
                    <select id="iec-topic" class="admin-swal-input">${topicOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-media-type">Media type *</label>
                    <select id="iec-media-type" class="admin-swal-input">${mediaOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-media-url">Media URL *</label>
                    <input id="iec-media-url" class="admin-swal-input" type="url" placeholder="https://..." value="${escapeHtml(existing?.media_url ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-thumb-url">Thumbnail URL</label>
                    <input id="iec-thumb-url" class="admin-swal-input" type="url" placeholder="Optional cover/preview" value="${escapeHtml(existing?.thumbnail_url ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-description">Description</label>
                    <textarea id="iec-description" class="admin-swal-input" rows="3">${escapeHtml(existing?.description ?? '')}</textarea>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-sort">Sort order</label>
                    <input id="iec-sort" class="admin-swal-input" type="number" min="0" value="${escapeHtml(String(existing?.sort_order ?? 0))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-active">Status *</label>
                    <select id="iec-active" class="admin-swal-input">
                        <option value="1" ${statusValue === '1' ? 'selected' : ''}>Active</option>
                        <option value="0" ${statusValue === '0' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    `;
}

function collectPayload() {
    return {
        title: document.getElementById('iec-title')?.value?.trim() ?? '',
        topic: document.getElementById('iec-topic')?.value?.trim() || null,
        media_type: document.getElementById('iec-media-type')?.value ?? 'gif',
        media_url: document.getElementById('iec-media-url')?.value?.trim() ?? '',
        thumbnail_url: document.getElementById('iec-thumb-url')?.value?.trim() || null,
        description: document.getElementById('iec-description')?.value?.trim() || null,
        sort_order: Number(document.getElementById('iec-sort')?.value || 0),
        is_active: document.getElementById('iec-active')?.value === '1',
    };
}

function showIecMaterialForm(existing) {
    const isEdit = Boolean(existing?.id);
    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit IEC material' : 'Add IEC material',
        html: buildFormHtml(existing),
        confirmButtonText: isEdit ? 'Save changes' : 'Create',
        cancelButtonText: 'Cancel',
        preConfirm: () => {
            const payload = collectPayload();
            if (!payload.title || !payload.media_url) {
                Swal.showValidationMessage('Title and media URL are required.');
                return false;
            }
            return payload;
        },
    }, { size: 'lg' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) return;
        const request = isEdit
            ? axios.put(`/admin/iec-materials/${existing.id}`, value)
            : axios.post('/admin/iec-materials', value);

        request.then(({ data }) => {
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            loadIecMaterials();
        }).catch(({ response }) => {
            const errors = response?.data?.errors;
            const firstError = errors ? Object.values(errors).flat()[0] : null;
            showErrorToast(firstError || response?.data?.message || 'Save failed.');
        });
    });
}

async function initializeIecMaterialsTable(tableEl) {
    const nextMode = getTableMode();

    if (!iecMaterialsDataTableClass) {
        return;
    }

    if (iecMaterialsTable && iecMaterialsTableMode === nextMode) {
        return;
    }

    if (iecMaterialsTable) {
        iecMaterialsTable.destroy();
        tableEl.innerHTML = '';
    }

    iecMaterialsTableMode = nextMode;
    iecMaterialsTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search IEC materials:',
        searchPlaceholder: 'Search IEC materials',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ materials',
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
        if (nextMode === iecMaterialsTableMode) {
            return;
        }
        await initializeIecMaterialsTable(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadIecMaterials();
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
            { title: 'IEC Material', className: 'dt-col-mobile-summary' },
            { title: 'Actions', orderable: false, className: 'dt-col-actions' },
        ];
    }

    return [
        { title: 'ID', className: 'dt-col-id' },
        { title: 'Title', className: 'dt-col-primary dt-col-name' },
        { title: 'Topic', className: 'dt-col-nowrap' },
        { title: 'Type', className: 'dt-col-nowrap' },
        { title: 'Sort', className: 'dt-col-nowrap' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(item) {
    const actions = [
        {
            tooltip: 'Edit material',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.IecMaterials.edit(${item.id})"`,
        },
        {
            tooltip: 'Delete material',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.IecMaterials.remove(${item.id})"`,
        },
    ];

    const desktopActionsMarkup = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActionsMarkup = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    if (iecMaterialsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">IEC #${escapeHtml(String(item.id))}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(item.title)}</p>
                    </div>
                    ${statusBadge(item.is_active)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Topic:</span> ${escapeHtml(item.topic ?? '—')}</p>
                    <p><span>Type:</span> ${escapeHtml((item.media_type ?? '').toUpperCase())}</p>
                </div>
            </div>`,
            mobileActionsMarkup,
        ];
    }

    return [
        item.id,
        escapeHtml(item.title ?? ''),
        escapeHtml(item.topic ?? '—'),
        escapeHtml((item.media_type ?? '').toUpperCase()),
        item.sort_order ?? 0,
        statusBadge(item.is_active),
        desktopActionsMarkup,
    ];
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

window.IecMaterials = {
    create: createIecMaterial,
    edit: editIecMaterial,
    remove: removeIecMaterial,
    load: loadIecMaterials,
};
