import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildSwalForm, buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let supportTable;
let currentCategoryFilter = '';

const CATEGORY_LABELS = {
    hotline: 'Hotline',
    counseling: 'Counseling',
    crisis_emergency: 'Crisis emergency',
    crisis_resource: 'Crisis resource',
};

export function initCareSupportModule() {
    const tableEl = document.getElementById('care-support-resources-table');
    if (!tableEl) {
        return;
    }

    document.querySelector('[data-admin-action="create-care-support-resource"]')
        ?.addEventListener('click', () => createCareSupportResource());

    document.getElementById('care-support-category-filter')
        ?.addEventListener('change', (event) => {
            currentCategoryFilter = event.target.value || '';
            loadCareSupportResources();
        });

    initializeSupportTable(tableEl).then(() => {
        bindAdminActionTooltipSuppression(tableEl);
        loadCareSupportResources();
    });
}

async function initializeSupportTable(tableElement) {
    supportTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: [
                { title: 'Order', width: '64px' },
                { title: 'Category' },
                { title: 'Title (EN)' },
                { title: 'Phone / Link' },
                { title: 'Status' },
                { title: 'Actions', orderable: false, searchable: false },
            ],
            searchPlaceholder: 'Search support resources...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ resources',
            pageLength: 15,
        }),
    );
}

export function loadCareSupportResources() {
    if (!supportTable) {
        return;
    }

    axios.get('/admin/care-support-resources', {
        params: {
            per_page: 500,
            category: currentCategoryFilter || undefined,
        },
    })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            supportTable.clear();
            rows.forEach((row) => supportTable.row.add(buildRowData(row)));
            supportTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load Care Support resources.');
        });
}

export function createCareSupportResource() {
    showResourceForm(null);
}

export function editCareSupportResource(id) {
    axios.get(`/admin/care-support-resources/${id}`)
        .then(({ data }) => showResourceForm(data.data))
        .catch(() => showErrorToast('Failed to load resource.'));
}

export function removeCareSupportResource(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete this resource?',
        html: '<p class="admin-swal-description">It will no longer appear in the mobile Get Support pages.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) {
            return;
        }

        axios.delete(`/admin/care-support-resources/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message ?? 'Resource deleted.', 'Deleted');
                loadCareSupportResources();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function buildRowData(row) {
    const title = escapeHtml(truncate(row.title_en, 70));
    const category = escapeHtml(CATEGORY_LABELS[row.category] ?? row.category);
    const contact = escapeHtml(row.phone || row.web_url || '—');
    const status = row.is_active
        ? '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>'
        : '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';

    return [
        String(row.sort_order ?? 0),
        category,
        title || '—',
        contact,
        status,
        buildAdminActionButtons([
            {
                tooltip: 'Edit resource',
                icon: 'fas fa-pen-to-square',
                className: 'admin-table-action-primary',
                attrs: `onclick="window.CareSupport.edit(${row.id})"`,
            },
            {
                tooltip: 'Delete resource',
                icon: 'fas fa-trash',
                className: 'admin-table-action-danger',
                attrs: `onclick="window.CareSupport.remove(${row.id})"`,
            },
        ]),
    ];
}

function showResourceForm(existing) {
    const isEdit = Boolean(existing);
    const bodyEn = Array.isArray(existing?.body_en) ? existing.body_en.join('\n') : '';
    const bodyTl = Array.isArray(existing?.body_tl) ? existing.body_tl.join('\n') : '';

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Care Support Resource' : 'Add Care Support Resource',
        html: buildSwalForm({
            description: 'Hotlines need a phone number. Counseling usually needs a description and optional website. Crisis resources can include modal body paragraphs (one per line).',
            fields: [
                {
                    id: 'csr-category',
                    label: 'Category *',
                    type: 'select',
                    value: existing?.category ?? 'hotline',
                    options: [
                        { value: 'hotline', label: '24/7 Hotline' },
                        { value: 'counseling', label: 'Counseling' },
                        { value: 'crisis_emergency', label: 'Crisis emergency banner' },
                        { value: 'crisis_resource', label: 'Crisis info resource' },
                    ],
                },
                {
                    id: 'csr-title-en',
                    label: 'Title (English) *',
                    type: 'textarea',
                    value: existing?.title_en ?? '',
                },
                {
                    id: 'csr-title-tl',
                    label: 'Title (Tagalog) *',
                    type: 'textarea',
                    value: existing?.title_tl ?? '',
                },
                {
                    id: 'csr-role-en',
                    label: 'Role EN (e.g. Counselors)',
                    value: existing?.role_en ?? '',
                },
                {
                    id: 'csr-role-tl',
                    label: 'Role TL',
                    value: existing?.role_tl ?? '',
                },
                {
                    id: 'csr-description-en',
                    label: 'Description EN',
                    type: 'textarea',
                    value: existing?.description_en ?? '',
                },
                {
                    id: 'csr-description-tl',
                    label: 'Description TL',
                    type: 'textarea',
                    value: existing?.description_tl ?? '',
                },
                {
                    id: 'csr-meta-en',
                    label: 'Meta EN (e.g. 24/7 | Free | Confidential)',
                    value: existing?.meta_en ?? '',
                },
                {
                    id: 'csr-meta-tl',
                    label: 'Meta TL',
                    value: existing?.meta_tl ?? '',
                },
                {
                    id: 'csr-phone',
                    label: 'Phone (multiple OK: 0945… | #33733)',
                    value: existing?.phone ?? '',
                },
                {
                    id: 'csr-web-url',
                    label: 'Website URL',
                    value: existing?.web_url ?? '',
                },
                {
                    id: 'csr-logo-url',
                    label: 'Logo image URL',
                    value: existing?.logo_url ?? '',
                },
                {
                    id: 'csr-logo-initial',
                    label: 'Logo initial (fallback letter)',
                    value: existing?.logo_initial ?? '',
                },
                {
                    id: 'csr-icon-key',
                    label: 'Icon key (info / plan / tips / friend)',
                    value: existing?.icon_key ?? '',
                },
                {
                    id: 'csr-body-en',
                    label: 'Modal body EN (one paragraph per line)',
                    type: 'textarea',
                    value: bodyEn,
                },
                {
                    id: 'csr-body-tl',
                    label: 'Modal body TL (one paragraph per line)',
                    type: 'textarea',
                    value: bodyTl,
                },
                {
                    id: 'csr-sort-order',
                    label: 'Sort order',
                    value: String(existing?.sort_order ?? ''),
                    placeholder: 'Auto if blank',
                },
                {
                    id: 'csr-is-emergency',
                    label: 'Emergency?',
                    type: 'select',
                    value: existing?.is_emergency ? '1' : '0',
                    options: [
                        { value: '0', label: 'No' },
                        { value: '1', label: 'Yes' },
                    ],
                },
                {
                    id: 'csr-is-active',
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
        confirmButtonText: isEdit ? 'Save changes' : 'Create resource',
        showCancelButton: true,
        preConfirm: () => {
            const titleEn = document.getElementById('csr-title-en')?.value?.trim() ?? '';
            const titleTl = document.getElementById('csr-title-tl')?.value?.trim() ?? '';
            if (!titleEn || !titleTl) {
                Swal.showValidationMessage('English and Tagalog titles are required.');
                return false;
            }

            const sortRaw = document.getElementById('csr-sort-order')?.value?.trim() ?? '';
            const payload = {
                category: document.getElementById('csr-category')?.value,
                title_en: titleEn,
                title_tl: titleTl,
                role_en: document.getElementById('csr-role-en')?.value ?? '',
                role_tl: document.getElementById('csr-role-tl')?.value ?? '',
                description_en: document.getElementById('csr-description-en')?.value ?? '',
                description_tl: document.getElementById('csr-description-tl')?.value ?? '',
                meta_en: document.getElementById('csr-meta-en')?.value ?? '',
                meta_tl: document.getElementById('csr-meta-tl')?.value ?? '',
                phone: document.getElementById('csr-phone')?.value ?? '',
                web_url: document.getElementById('csr-web-url')?.value ?? '',
                logo_url: document.getElementById('csr-logo-url')?.value ?? '',
                logo_initial: document.getElementById('csr-logo-initial')?.value ?? '',
                icon_key: document.getElementById('csr-icon-key')?.value ?? '',
                body_en: document.getElementById('csr-body-en')?.value ?? '',
                body_tl: document.getElementById('csr-body-tl')?.value ?? '',
                is_emergency: document.getElementById('csr-is-emergency')?.value === '1',
                is_active: document.getElementById('csr-is-active')?.value === '1',
            };

            if (sortRaw !== '') {
                payload.sort_order = Number.parseInt(sortRaw, 10);
            }

            const request = isEdit
                ? axios.put(`/admin/care-support-resources/${existing.id}`, payload)
                : axios.post('/admin/care-support-resources', payload);

            return request
                .then(({ data }) => data)
                .catch((error) => {
                    Swal.showValidationMessage(
                        error.response?.data?.message
                        ?? 'Unable to save resource. Please try again.',
                    );
                    return false;
                });
        },
    }, { size: 'lg' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) {
            return;
        }
        showSuccessToast(value.message ?? 'Resource saved.', 'Care Support');
        loadCareSupportResources();
    });
}

function truncate(value, max = 70) {
    const text = String(value ?? '');
    return text.length <= max ? text : `${text.slice(0, max - 1)}…`;
}

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.CareSupport = {
    create: createCareSupportResource,
    edit: editCareSupportResource,
    remove: removeCareSupportResource,
    reload: loadCareSupportResources,
};
