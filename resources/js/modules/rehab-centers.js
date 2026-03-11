import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from './shared/datatables';
import { buildSwalForm, buildSwalOptions } from './shared/swal-forms';

let rehabTable;
let rehabDataTableClass;
let rehabTableMode;
let hasBoundViewportListener = false;

export function initRehabCentersModule() {
    const tableEl = document.getElementById('rehab-centers-table');
    if (!tableEl) return;

    loadAdminDataTableLibrary().then(async (DataTable) => {
        rehabDataTableClass = DataTable;
        await initializeRehabTable(tableEl);
        bindViewportListener(tableEl);
        loadRehabCenters();
    });
}

export function loadRehabCenters(search = '') {
    if (!rehabTable) return;
    axios.get('/admin/rehab-centers', { params: { search } })
        .then(({ data }) => {
            rehabTable.clear();
            (data.data?.data ?? []).forEach((c) => {
                rehabTable.row.add(buildRowData(c));
            });
            rehabTable.draw();
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Rehab Centers', text: 'Failed to load listings.' });
        });
}

export function createRehabCenter() {
    showRehabForm(null);
}

export function editRehabCenter(id) {
    axios.get(`/admin/rehab-centers/${id}`).then(({ data }) => {
        showRehabForm(data.data);
    });
}

export function removeRehabCenter(id) {
    Swal.fire({
        icon: 'warning',
        title: 'Delete Rehab Center?',
        text: 'This cannot be undone.',
        showCancelButton: true,
        confirmButtonText: 'Delete',
        confirmButtonColor: '#CE2028',
    }).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/rehab-centers/${id}`)
            .then(({ data }) => {
                Swal.fire({ icon: 'success', title: 'Deleted', text: data.message });
                loadRehabCenters();
            })
            .catch(({ response }) => {
                Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Delete failed.' });
            });
    });
}

function showRehabForm(existing) {
    const isEdit = Boolean(existing);
    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Rehab Center' : 'Add Rehab Center',
        html: buildSwalForm({
            description: 'Manage directory information for the selected rehabilitation center.',
            fields: [
                { id: 'rc-name', label: 'Name *', placeholder: 'Enter facility name', value: existing?.name ?? '' },
                { id: 'rc-region', label: 'Region *', placeholder: 'Enter region', value: existing?.region ?? '' },
                { id: 'rc-province', label: 'Province *', placeholder: 'Enter province', value: existing?.province ?? '' },
                { id: 'rc-address', label: 'Address *', placeholder: 'Enter address', value: existing?.address ?? '' },
                { id: 'rc-contact', label: 'Contact', placeholder: 'Enter contact details', value: existing?.contact ?? '' },
                { id: 'rc-website', label: 'Website URL', placeholder: 'Paste website URL', value: existing?.website ?? '' },
                {
                    id: 'rc-status',
                    label: 'Status *',
                    type: 'select',
                    value: existing?.is_active === false ? '0' : '1',
                    options: [
                        { value: '1', label: 'Active' },
                        { value: '0', label: 'Inactive' },
                    ],
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Save Changes' : 'Create',
        preConfirm: () => {
            const name     = document.getElementById('rc-name')?.value.trim();
            const region   = document.getElementById('rc-region')?.value.trim();
            const province = document.getElementById('rc-province')?.value.trim();
            const address  = document.getElementById('rc-address')?.value.trim();
            if (!name || !region || !province || !address) {
                Swal.showValidationMessage('Name, Region, Province and Address are required.');
                return false;
            }
            return {
                name, region, province, address,
                contact: document.getElementById('rc-contact')?.value.trim() || null,
                website: document.getElementById('rc-website')?.value.trim() || null,
                is_active: document.getElementById('rc-status')?.value === '1',
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;
        const request = isEdit
            ? axios.put(`/admin/rehab-centers/${existing.id}`, value)
            : axios.post('/admin/rehab-centers', value);

        request.then(({ data }) => {
            Swal.fire({ icon: 'success', title: isEdit ? 'Updated' : 'Created', text: data.message });
            loadRehabCenters();
        }).catch(({ response }) => {
            Swal.fire({ icon: 'error', title: 'Error', text: response?.data?.message ?? 'Operation failed.' });
        });
    });
}

window.RehabCenters = {
    create: createRehabCenter,
    edit:   editRehabCenter,
    remove: removeRehabCenter,
};

async function initializeRehabTable(tableEl) {
    const nextMode = getTableMode();

    if (!rehabDataTableClass) {
        return;
    }

    if (rehabTable && rehabTableMode === nextMode) {
        return;
    }

    if (rehabTable) {
        rehabTable.destroy();
        tableEl.innerHTML = '';
    }

    rehabTableMode = nextMode;
    rehabTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search centers:',
        searchPlaceholder: 'Search rehab centers',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ rehab centers',
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

        if (nextMode === rehabTableMode) {
            return;
        }

        await initializeRehabTable(tableEl);
        loadRehabCenters();
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
            { title: 'Center', className: 'dt-col-mobile-summary' },
            { title: 'Actions', orderable: false, className: 'dt-col-actions' },
        ];
    }

    return [
        { title: 'ID', className: 'dt-col-id' },
        { title: 'Name', className: 'dt-col-primary dt-col-name' },
        { title: 'Region', className: 'dt-col-nowrap' },
        { title: 'Province', className: 'dt-col-nowrap' },
        { title: 'Address', className: 'dt-col-wide' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Contact', className: 'dt-col-nowrap' },
        { title: 'Website', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(center) {
    const desktopActionsMarkup = `<div class="admin-table-actions">
        <button onclick="window.RehabCenters.edit(${center.id})" class="admin-table-action admin-table-action-primary rehab-table-action-icon" title="Edit rehab center" aria-label="Edit rehab center"><i class="fas fa-pen-to-square"></i><span class="sr-only">Edit</span></button>
        <button onclick="window.RehabCenters.remove(${center.id})" class="admin-table-action admin-table-action-danger rehab-table-action-icon" title="Delete rehab center" aria-label="Delete rehab center"><i class="fas fa-trash"></i><span class="sr-only">Delete</span></button>
    </div>`;

    const mobileActionsMarkup = `<div class="admin-table-actions admin-table-actions-mobile">
        <button onclick="window.RehabCenters.edit(${center.id})" class="admin-table-action admin-table-action-primary"><i class="fas fa-pen-to-square"></i><span>Edit</span></button>
        <button onclick="window.RehabCenters.remove(${center.id})" class="admin-table-action admin-table-action-danger"><i class="fas fa-trash"></i><span>Delete</span></button>
    </div>`;

    if (rehabTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Center #${escapeHtml(String(center.id))}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(center.name)}</p>
                    </div>
                    ${statusBadge(center.is_active)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Region:</span> ${escapeHtml(center.region)}</p>
                    <p><span>Province:</span> ${escapeHtml(center.province)}</p>
                    <p><span>Address:</span> ${escapeHtml(center.address)}</p>
                    <p><span>Contact:</span> ${contactValue(center.contact)}</p>
                    <p><span>Website:</span> ${websiteValue(center.website, 'Visit website')}</p>
                </div>
            </div>`,
            mobileActionsMarkup,
        ];
    }

    return [
        center.id,
        escapeHtml(center.name),
        escapeHtml(center.region),
        escapeHtml(center.province),
        escapeHtml(center.address),
        statusBadge(center.is_active),
        contactValue(center.contact),
        websiteValue(center.website, 'Visit'),
        desktopActionsMarkup,
    ];
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function escapeAttribute(value) {
    return escapeHtml(value);
}

function statusBadge(isActive) {
    if (isActive) {
        return '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>';
    }

    return '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';
}

function emptyBadge(label = 'N/A') {
    return `<span class="rehab-empty-badge">${escapeHtml(label)}</span>`;
}

function contactValue(contact) {
    return contact ? escapeHtml(contact) : emptyBadge();
}

function websiteValue(website, label) {
    return website
        ? `<a href="${escapeAttribute(website)}" target="_blank" rel="noopener noreferrer" class="admin-table-link-chip">${escapeHtml(label)}</a>`
        : emptyBadge();
}
