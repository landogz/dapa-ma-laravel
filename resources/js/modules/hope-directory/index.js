import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let directoryTable;
let categoryFilter = '';

const CATEGORY_LABELS = {
    government: "Gov't",
    ngo: 'NGOs',
    school: 'Schools',
};

export function initHopeDirectoryModule() {
    const tableEl = document.getElementById('hope-directory-table');
    if (!tableEl) return;

    document.querySelector('[data-admin-action="create-hope-directory"]')
        ?.addEventListener('click', () => showForm(null));

    document.getElementById('hope-directory-category-filter')
        ?.addEventListener('change', (event) => {
            categoryFilter = event.target.value || '';
            loadRows();
        });

    initializeTable(tableEl).then(() => {
        bindAdminActionTooltipSuppression(tableEl);
        loadRows();
    });
}

async function initializeTable(tableElement) {
    directoryTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: [
                { title: 'Order', width: '64px' },
                { title: 'Organization' },
                { title: 'Category' },
                { title: 'Contact' },
                { title: 'Status' },
                { title: 'Actions', orderable: false, searchable: false },
            ],
            searchPlaceholder: 'Search directory...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ organizations',
            pageLength: 15,
            drawCallback: () => bindRowActions(),
        }),
    );
}

function loadRows() {
    if (!directoryTable) return;

    axios.get('/admin/hope-directory', {
        params: {
            per_page: 500,
            category: categoryFilter || undefined,
        },
    })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            directoryTable.clear();
            rows.forEach((row) => directoryTable.row.add(buildRow(row)));
            directoryTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load Hope Directory.');
        });
}

function categoryBadge(category) {
    const label = CATEGORY_LABELS[category] ?? category ?? '—';
    const className = CATEGORY_LABELS[category]
        ? `hope-category-badge hope-category-badge-${category}`
        : 'hope-category-badge hope-audience-badge-all';
    return `<span class="${className}">${escapeHtml(label)}</span>`;
}

function mediaThumb(url) {
    if (url) {
        return `<img class="admin-table-media-thumb" src="${escapeAttr(url)}" alt="" loading="lazy">`;
    }
    return '<span class="admin-table-media-placeholder" aria-hidden="true"><i class="fas fa-building"></i></span>';
}

function buildRow(row) {
    const status = row.is_active
        ? '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>'
        : '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';
    const contact = escapeHtml([row.phone, row.email].filter(Boolean).join(' · ') || '—');
    const actions = buildAdminActionButtons([
        {
            tooltip: 'Edit organization',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `data-hope-directory-edit="${row.id}"`,
        },
        {
            tooltip: 'Delete organization',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `data-hope-directory-delete="${row.id}"`,
        },
    ]);

    return [
        row.sort_order ?? 0,
        `<div class="admin-table-media-cell">
            ${mediaThumb(row.logo_url)}
            <div class="admin-table-media-copy">
                <div class="font-semibold text-slate-800">${escapeHtml(row.name)}</div>
                <div class="text-xs text-slate-500 line-clamp-2">${escapeHtml(row.description ?? row.address ?? '')}</div>
            </div>
         </div>`,
        categoryBadge(row.category),
        contact,
        status,
        `<div class="admin-table-actions-nowrap">${actions}</div>`,
    ];
}

function bindRowActions() {
    document.querySelectorAll('[data-hope-directory-edit]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-hope-directory-edit');
            axios.get(`/admin/hope-directory/${id}`)
                .then(({ data }) => showForm(data.data))
                .catch(() => showErrorToast('Failed to load organization.'));
        });
    });

    document.querySelectorAll('[data-hope-directory-delete]').forEach((button) => {
        if (button.dataset.bound === '1') return;
        button.dataset.bound = '1';
        button.addEventListener('click', () => {
            const id = button.getAttribute('data-hope-directory-delete');
            Swal.fire(buildSwalOptions({
                icon: 'warning',
                title: 'Delete this organization?',
                html: '<p class="admin-swal-description">It will no longer appear in the mobile Hope Directory.</p>',
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
            }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
                if (!isConfirmed) return;
                axios.delete(`/admin/hope-directory/${id}`)
                    .then(({ data }) => {
                        showSuccessToast(data.message ?? 'Organization deleted.');
                        loadRows();
                    })
                    .catch(({ response }) => {
                        showErrorToast(response?.data?.message ?? 'Delete failed.');
                    });
            });
        });
    });
}

function buildFormHtml(row) {
    const logoPreview = row?.logo_url
        ? `<img class="admin-swal-media-preview" src="${escapeAttr(row.logo_url)}" alt="Current logo">`
        : '';

    return `
        <div class="admin-swal-form">
            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Organization</p>
                <div class="admin-swal-fields">
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="hd-name">Name *</label>
                        <input id="hd-name" class="admin-swal-input" type="text" placeholder="Organization name" value="${escapeAttr(row?.name ?? '')}">
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="hd-description">Short description</label>
                        <input id="hd-description" class="admin-swal-input" type="text" placeholder="One-line summary for the directory card" value="${escapeAttr(row?.description ?? '')}">
                    </div>
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="hd-category">Category *</label>
                            <select id="hd-category" class="admin-swal-select">
                                <option value="government" ${row?.category === 'government' || !row?.category ? 'selected' : ''}>Gov't</option>
                                <option value="ngo" ${row?.category === 'ngo' ? 'selected' : ''}>NGOs</option>
                                <option value="school" ${row?.category === 'school' ? 'selected' : ''}>Schools</option>
                            </select>
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="hd-active">Visibility *</label>
                            <select id="hd-active" class="admin-swal-select">
                                <option value="1" ${row?.is_active !== false ? 'selected' : ''}>Active in app</option>
                                <option value="0" ${row?.is_active === false ? 'selected' : ''}>Hidden</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="hd-sort">Sort order</label>
                        <input id="hd-sort" class="admin-swal-input" type="number" min="0" value="${row?.sort_order ?? 0}">
                        <p class="admin-swal-hint">Lower numbers appear first in the mobile directory.</p>
                    </div>
                </div>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Contact details</p>
                <div class="admin-swal-fields">
                    <div class="admin-swal-field">
                        <label class="admin-swal-label" for="hd-address">Address</label>
                        <input id="hd-address" class="admin-swal-input" type="text" placeholder="Street, city, province" value="${escapeAttr(row?.address ?? '')}">
                    </div>
                    <div class="admin-swal-fields-grid-2">
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="hd-phone">Phone</label>
                            <input id="hd-phone" class="admin-swal-input" type="text" placeholder="+63 ..." value="${escapeAttr(row?.phone ?? '')}">
                        </div>
                        <div class="admin-swal-field">
                            <label class="admin-swal-label" for="hd-email">Email</label>
                            <input id="hd-email" class="admin-swal-input" type="email" placeholder="name@example.gov.ph" value="${escapeAttr(row?.email ?? '')}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-swal-section">
                <p class="admin-swal-section-title">Logo</p>
                <div class="admin-swal-field">
                    <div class="admin-swal-media-row">
                        ${logoPreview}
                        <div class="min-w-0 flex-1">
                            <label class="admin-swal-label" for="hd-logo">Upload logo</label>
                            <input id="hd-logo" class="admin-swal-input" type="file" accept="image/*">
                            <p class="admin-swal-hint mt-1">Square PNG or JPG works best on mobile cards.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function showForm(row) {
    const isEdit = Boolean(row);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Organization' : 'Add Organization',
        subtitle: 'Partners listed in the DAPE Hope Official Directory.',
        html: buildFormHtml(row),
        confirmButtonText: isEdit ? 'Save Changes' : 'Create Organization',
        cancelButtonText: 'Cancel',
        width: '42rem',
        preConfirm: () => {
            const name = document.getElementById('hd-name')?.value.trim();
            if (!name) {
                Swal.showValidationMessage('Name is required.');
                return false;
            }
            return {
                name,
                description: document.getElementById('hd-description')?.value.trim() || '',
                category: document.getElementById('hd-category')?.value,
                address: document.getElementById('hd-address')?.value.trim() || '',
                phone: document.getElementById('hd-phone')?.value.trim() || '',
                email: document.getElementById('hd-email')?.value.trim() || '',
                sort_order: document.getElementById('hd-sort')?.value || 0,
                is_active: document.getElementById('hd-active')?.value === '1' ? '1' : '0',
                logo: document.getElementById('hd-logo')?.files?.[0] || null,
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) return;

        const form = new FormData();
        Object.entries(value).forEach(([key, val]) => {
            if (key === 'logo') {
                if (val) form.append('logo', val);
                return;
            }
            form.append(key, val);
        });

        const request = isEdit
            ? axios.post(`/admin/hope-directory/${row.id}`, form)
            : axios.post('/admin/hope-directory', form);

        request
            .then(({ data }) => {
                showSuccessToast(data.message ?? (isEdit ? 'Updated.' : 'Created.'));
                loadRows();
            })
            .catch(({ response }) => {
                const msg = response?.data?.message
                    ?? Object.values(response?.data?.errors ?? {})?.[0]?.[0]
                    ?? 'Save failed.';
                showErrorToast(msg);
            });
    });
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function escapeAttr(value) {
    return escapeHtml(value).replaceAll('`', '&#96;');
}
