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
    const currentMedia = existing?.media_url
        ? `<p class="mt-1 text-xs text-slate-500">Current: <a class="text-[#055498] underline" href="${escapeHtml(existing.media_url)}" target="_blank" rel="noopener noreferrer">Open media</a></p>`
        : '';
    const currentThumb = existing?.thumbnail_url
        ? `<p class="mt-1 text-xs text-slate-500">Current: <a class="text-[#055498] underline" href="${escapeHtml(existing.thumbnail_url)}" target="_blank" rel="noopener noreferrer">Open thumbnail</a></p>`
        : '';

    return `
        <div class="admin-swal-form">
            <p class="admin-swal-description">Upload a GIF/image or paste a YouTube/Lottie URL for the mobile IEC gallery.</p>
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
                <div class="admin-swal-field" data-iec-upload-field>
                    <label class="admin-swal-label" for="iec-media-file">Upload media (GIF / image)</label>
                    <input id="iec-media-file" class="admin-swal-input" type="file" accept="image/gif,image/jpeg,image/png,image/webp,.gif,.jpg,.jpeg,.png,.webp">
                    <p class="mt-1 text-xs text-slate-500">Max 10MB. For GIF/Image types you can upload instead of pasting a URL.</p>
                    ${currentMedia}
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-media-url">Media URL</label>
                    <input id="iec-media-url" class="admin-swal-input" type="url" placeholder="https://... (required for YouTube/Lottie)" value="${escapeHtml(existing?.media_url ?? '')}">
                </div>
                <div class="admin-swal-field" data-iec-upload-field>
                    <label class="admin-swal-label" for="iec-thumb-file">Upload thumbnail (optional)</label>
                    <input id="iec-thumb-file" class="admin-swal-input" type="file" accept="image/gif,image/jpeg,image/png,image/webp,.gif,.jpg,.jpeg,.png,.webp">
                    ${currentThumb}
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="iec-thumb-url">Thumbnail URL</label>
                    <input id="iec-thumb-url" class="admin-swal-input" type="url" placeholder="Optional cover/preview URL" value="${escapeHtml(existing?.thumbnail_url ?? '')}">
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
        topic: document.getElementById('iec-topic')?.value?.trim() || '',
        media_type: document.getElementById('iec-media-type')?.value ?? 'gif',
        media_url: document.getElementById('iec-media-url')?.value?.trim() || '',
        thumbnail_url: document.getElementById('iec-thumb-url')?.value?.trim() || '',
        description: document.getElementById('iec-description')?.value?.trim() || '',
        sort_order: Number(document.getElementById('iec-sort')?.value || 0),
        is_active: document.getElementById('iec-active')?.value === '1',
        media_file: document.getElementById('iec-media-file')?.files?.[0] ?? null,
        thumbnail_file: document.getElementById('iec-thumb-file')?.files?.[0] ?? null,
    };
}

function buildMultipartPayload(payload, { method = 'POST' } = {}) {
    const formData = new FormData();
    formData.append('title', payload.title);
    formData.append('media_type', payload.media_type);
    formData.append('sort_order', String(payload.sort_order ?? 0));
    formData.append('is_active', payload.is_active ? '1' : '0');

    if (payload.topic) formData.append('topic', payload.topic);
    if (payload.description) formData.append('description', payload.description);
    if (payload.media_url) formData.append('media_url', payload.media_url);
    if (payload.thumbnail_url) formData.append('thumbnail_url', payload.thumbnail_url);
    if (payload.media_file) formData.append('media_file', payload.media_file);
    if (payload.thumbnail_file) formData.append('thumbnail_file', payload.thumbnail_file);
    if (method !== 'POST') formData.append('_method', method);

    return formData;
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
            const needsUrl = ['youtube', 'lottie'].includes(payload.media_type);
            if (!payload.title) {
                Swal.showValidationMessage('Title is required.');
                return false;
            }
            if (needsUrl && !payload.media_url) {
                Swal.showValidationMessage('Media URL is required for YouTube/Lottie.');
                return false;
            }
            if (!needsUrl && !payload.media_url && !payload.media_file && !existing?.media_url) {
                Swal.showValidationMessage('Upload a GIF/image or paste a media URL.');
                return false;
            }
            return payload;
        },
    }, { size: 'lg' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) return;

        const request = isEdit
            ? axios.post(`/admin/iec-materials/${existing.id}`, buildMultipartPayload(value, { method: 'PUT' }))
            : axios.post('/admin/iec-materials', buildMultipartPayload(value));

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
