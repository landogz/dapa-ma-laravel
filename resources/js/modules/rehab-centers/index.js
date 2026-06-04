import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { showErrorToast, showSuccessToast } from '../shared/toast';
import {
    buildRehabMapPickerHtml,
    destroyRehabCentersOverviewMap,
    destroyRehabMapPicker,
    getRehabMapPickerCoords,
    initRehabCentersOverviewMap,
    initRehabMapPicker,
} from './rehab-map';

let rehabTable;
let rehabDataTableClass;
let rehabTableMode;
let hasBoundViewportListener = false;
let latestCenters = [];

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

    axios.get('/admin/rehab-centers', { params: { search, per_page: 200 } })
        .then(({ data }) => {
            latestCenters = data.data?.data ?? [];
            rehabTable.clear();
            latestCenters.forEach((center) => {
                rehabTable.row.add(buildRowData(center));
            });
            rehabTable.draw();
            initRehabCentersOverviewMap('rehab-centers-overview-map', latestCenters);
        })
        .catch(() => {
            showErrorToast('Failed to load rehab center listings.');
        });
}

export function createRehabCenter() {
    showRehabForm(null);
}

export function editRehabCenter(id) {
    axios.get(`/admin/rehab-centers/${id}`).then(({ data }) => {
        showRehabForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load rehab center details.');
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
                showSuccessToast(data.message, 'Deleted');
                loadRehabCenters();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function applyGeocodedFields(meta = {}) {
    if (meta.address) {
        const addressInput = document.getElementById('rc-address');
        if (addressInput) {
            addressInput.value = meta.address;
        }
    }

    if (meta.region) {
        const regionInput = document.getElementById('rc-region');
        if (regionInput) {
            regionInput.value = meta.region;
        }
    }

    if (meta.province) {
        const provinceInput = document.getElementById('rc-province');
        if (provinceInput) {
            provinceInput.value = meta.province;
        }
    }
}

function buildRehabCenterFormHtml(existing) {
    const statusValue = existing?.is_active === false ? '0' : '1';

    return `
        <div class="admin-swal-form rehab-center-form">
            <p class="admin-swal-description">Search and pin the location on the map. Address details are filled automatically from the pin.</p>
            <div class="rehab-center-form-grid">
                <div class="rehab-center-form-fields">
                    <div class="admin-swal-fields">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="rc-name">Name *</label>
                            <input id="rc-name" class="admin-swal-input" type="text" placeholder="Enter facility name" value="${escapeHtml(existing?.name ?? '')}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="rc-address">Address *</label>
                            <textarea id="rc-address" class="admin-swal-textarea rehab-center-address-field" placeholder="Filled from map pin or edit manually">${escapeHtml(existing?.address ?? '')}</textarea>
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="rc-contact">Contact</label>
                            <input id="rc-contact" class="admin-swal-input" type="text" placeholder="Enter contact details" value="${escapeHtml(existing?.contact ?? '')}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="rc-website">Website URL</label>
                            <input id="rc-website" class="admin-swal-input" type="url" placeholder="Paste website URL" value="${escapeHtml(existing?.website ?? '')}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="rc-status">Status *</label>
                            <select id="rc-status" class="admin-swal-select">
                                <option value="1"${statusValue === '1' ? ' selected' : ''}>Active</option>
                                <option value="0"${statusValue === '0' ? ' selected' : ''}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" id="rc-region" value="${escapeHtml(existing?.region ?? '')}">
                    <input type="hidden" id="rc-province" value="${escapeHtml(existing?.province ?? '')}">
                </div>
                <div class="rehab-center-form-map">
                    ${buildRehabMapPickerHtml()}
                </div>
            </div>
        </div>
    `;
}

function showRehabForm(existing) {
    const isEdit = Boolean(existing);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Rehab Center' : 'Add Rehab Center',
        customClass: {
            popup: 'admin-swal-popup admin-swal-popup-map',
            htmlContainer: 'admin-swal-html',
        },
        html: buildRehabCenterFormHtml(existing),
        showCancelButton: true,
        confirmButtonText: isEdit ? 'Save Changes' : 'Create',
        didOpen: () => {
            const picker = initRehabMapPicker({
                lat: existing?.latitude ?? null,
                lng: existing?.longitude ?? null,
                onLocationChange: (location) => applyGeocodedFields(location),
            });
            requestAnimationFrame(() => {
                picker?.map?.resize();
            });
        },
        willClose: () => {
            destroyRehabMapPicker();
        },
        preConfirm: () => {
            const name = document.getElementById('rc-name')?.value.trim();
            const region = document.getElementById('rc-region')?.value.trim();
            const province = document.getElementById('rc-province')?.value.trim();
            const address = document.getElementById('rc-address')?.value.trim();
            const coords = getRehabMapPickerCoords();

            if (!name || !address) {
                Swal.showValidationMessage('Name and Address are required.');
                return false;
            }

            if (!coords) {
                Swal.showValidationMessage('Pin the exact location on the map before saving.');
                return false;
            }

            if (!region || !province) {
                Swal.showValidationMessage('Search and pin a location on the map to set region and province.');
                return false;
            }

            return {
                name,
                region,
                province,
                address,
                latitude: coords.latitude,
                longitude: coords.longitude,
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
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            loadRehabCenters();
        }).catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Operation failed.');
        });
    });
}

window.RehabCenters = {
    create: createRehabCenter,
    edit: editRehabCenter,
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

    window.addEventListener('beforeunload', () => {
        destroyRehabCentersOverviewMap();
        destroyRehabMapPicker();
    });
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
        { title: 'Address', className: 'dt-col-wide' },
        { title: 'Location', className: 'dt-col-nowrap' },
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

    const locationLabel = hasCoordinates(center)
        ? `${Number(center.latitude).toFixed(4)}, ${Number(center.longitude).toFixed(4)}`
        : 'Not pinned';

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
                    <p><span>Address:</span> ${escapeHtml(center.address)}</p>
                    <p><span>Coordinates:</span> ${escapeHtml(locationLabel)}</p>
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
        escapeHtml(center.address),
        hasCoordinates(center)
            ? `<span class="rehab-coords-chip" title="Latitude and longitude">${escapeHtml(locationLabel)}</span>`
            : emptyBadge('Not pinned'),
        statusBadge(center.is_active),
        contactValue(center.contact),
        websiteValue(center.website, 'Visit'),
        desktopActionsMarkup,
    ];
}

function hasCoordinates(center) {
    return Number.isFinite(Number(center.latitude)) && Number.isFinite(Number(center.longitude));
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
