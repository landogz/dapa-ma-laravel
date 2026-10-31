import axios from 'axios';
import Swal from 'sweetalert2';
import {
    buildSwalForm,
    buildSwalOptions,
    confirmWithSwal,
    formatApiValidationMessage,
    normalizeHexColor,
} from '../shared/swal-forms';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import {
    applyAdminFilterTabCounts,
    bindAdminFilterTabs,
    countByKey,
    renderAdminFilterTabs,
    setAdminFilterTab,
} from '../shared/filter-tabs';
import {
    attachWorkflowWindowHandlers,
    buildWorkflowTableActions,
    ensureWorkflowStatusFilter,
    filterByWorkflowStatus,
    guardWorkflowCreateAction,
    isEditorStagingPublished,
    isPublisherReviewOnly,
    isSuperAdminRole,
    itemForEditorForm,
    openItemFromQuery,
    publisherReviewSwalButtons,
    renderReviewNotesBanner,
    resolvePublisherReviewSwalResult,
    syncWorkflowCreateButton,
    workflowStatusBadge,
} from '../shared/content-workflow';
import { showErrorToast, showSuccessToast } from '../shared/toast';
import { readAdminPageFocus } from '../shared/page-focus';

let optionsTable;
let optionsTableMode;
let hasBoundViewportListener = false;
let currentGroupFilter = '';
let groupMeta = [];
let iconChoices = [];
let workflowStatusFilter = '';

export function initAppOptionsModule() {
    const tableEl = document.getElementById('app-options-table');
    if (!tableEl) {
        return;
    }

    const focus = readAdminPageFocus();
    if (focus.group) {
        currentGroupFilter = focus.group;
    }

    document.querySelector('[data-admin-action="create-app-option"]')
        ?.addEventListener('click', () => createAppOption());

    bindAdminFilterTabs(document.getElementById('app-option-group-tabs'), (value) => {
        currentGroupFilter = value;
        loadAppOptions();
    });

    initializeOptionsTable(tableEl).then(() => {
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        syncWorkflowCreateButton('[data-admin-action="create-app-option"]');
        attachWorkflowWindowHandlers('AppOptions', '/admin/app-options', () => loadAppOptions());
        ensureWorkflowStatusFilter({
            selectMount: tableEl.closest('.admin-table-shell'),
            onChange: (value) => {
                workflowStatusFilter = value;
                loadAppOptions();
            },
        });
        openItemFromQuery('item', (id) => editAppOption(id));
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
            drawCallback: () => applyColorSwatches(tableElement),
        }),
    );
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 1023px)');
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
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
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
        { title: 'Workflow', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

function fillGroupTabs() {
    const tabs = document.getElementById('app-option-group-tabs');
    if (!tabs || groupMeta.length === 0) {
        return;
    }

    const signature = groupMeta.map((group) => `${group.key}:${group.title}:${group.count ?? 0}`).join('|');
    if (tabs.dataset.filled === signature) {
        setAdminFilterTab(tabs, currentGroupFilter);
        return;
    }

    const total = groupMeta.reduce((sum, group) => sum + Number(group.count ?? 0), 0);
    const icons = {
        pronouns: 'fas fa-user',
        persona: 'fas fa-id-card',
        care_toolkit: 'fas fa-heart-pulse',
        care_support: 'fas fa-headset',
        journal_sky: 'fas fa-palette',
        journal_feeling: 'fas fa-face-smile',
        journal_impact: 'fas fa-sun',
        care_toolkit: 'fas fa-heart-pulse',
    };
    renderAdminFilterTabs(tabs, [
        { value: '', label: 'All catalogs', icon: 'fas fa-layer-group', count: total },
        ...groupMeta.map((group) => ({
            value: group.key,
            label: group.title,
            icon: icons[group.key] || 'fas fa-table',
            count: group.count ?? 0,
        })),
    ]);
    tabs.dataset.filled = signature;
    setAdminFilterTab(tabs, currentGroupFilter);
}

export function loadAppOptions() {
    if (!optionsTable) {
        return;
    }

    // Always load every catalog so sidebar badges can match the active workflow filter.
    axios.get('/admin/app-options', {
        params: {
            per_page: 500,
        },
    })
        .then(({ data }) => {
            groupMeta = data.meta?.groups ?? groupMeta;
            if (currentGroupFilter && !groupMeta.some((group) => group.key === currentGroupFilter)) {
                currentGroupFilter = '';
            }
            iconChoices = data.meta?.icons ?? iconChoices;

            const workflowRows = filterByWorkflowStatus(data.data?.data ?? [], workflowStatusFilter);
            const groupCounts = countByKey(workflowRows, (row) => row.group);
            groupMeta = groupMeta.map((group) => ({
                ...group,
                count: groupCounts[group.key] ?? 0,
            }));
            fillGroupTabs();
            applyAdminFilterTabCounts(
                document.getElementById('app-option-group-tabs'),
                {
                    '': groupCounts[''] ?? workflowRows.length,
                    ...Object.fromEntries(groupMeta.map((group) => [group.key, group.count ?? 0])),
                },
            );

            const rows = currentGroupFilter
                ? workflowRows.filter((row) => row.group === currentGroupFilter)
                : workflowRows;
            optionsTable.clear();
            rows.forEach((row) => optionsTable.row.add(buildRowData(row)));
            optionsTable.draw();
            applyColorSwatches(optionsTable.table().container());
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load lookups.');
        });
}

export function createAppOption() {
    guardWorkflowCreateAction(() => showOptionForm(null));
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
    const workflowBadge = workflowStatusBadge(row.workflow_status, row);

    const actions = buildWorkflowTableActions(row, 'AppOptions', {
        basePath: '/admin/app-options',
    });

    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons(actions, { isMobile: true, nowrap: true });

    if (optionsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${catalog}</p>
                        <p class="admin-table-mobile-title admin-option-label-cell">${optionIconHtml(row)}${accentSwatchHtml(row)}${label}</p>
                    </div>
                    ${status}${workflowBadge}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Key:</span> ${key}</p>
                    <p><span>Order:</span> ${escapeHtml(String(row.sort_order ?? 0))}</p>
                    <p><span>Selection:</span> ${escapeHtml(selection)}</p>
                    <p><span>Accent:</span> ${escapeHtml(normalizeHexColor(row.accent_color) || 'None')}</p>
                </div>
                <div class="admin-table-mobile-actions">${mobileActions}</div>
            </div>`,
        ];
    }

    return [
        String(row.sort_order ?? 0),
        catalog,
        `<span class="admin-option-label-cell">${optionIconHtml(row)}${accentSwatchHtml(row)}${label}</span>`,
        key,
        escapeHtml(selection),
        status,
        workflowBadge,
        desktopActions,
    ];
}

function showOptionForm(existingRaw) {
    const existing = existingRaw ? itemForEditorForm(existingRaw) : null;
    const isEdit = Boolean(existing);
    const editingPublished = isEdit && isEditorStagingPublished(existingRaw);
    const isSuperAdmin = isSuperAdminRole();
    const groupOptions = (groupMeta.length ? groupMeta : [{ key: 'pronouns', title: 'Pronouns' }])
        .map((group) => ({ value: group.key, label: group.title }));
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
            type: 'svg-icon',
            value: existing?.icon ?? '',
            choices: iconChoices,
            noneLabel: 'None',
        },
        {
            id: 'ao-accent',
            label: 'Accent color',
            type: 'color',
            value: existing?.accent_color ?? '',
            placeholder: '#055498',
            hint: 'Used on journal sky cards. Leave blank for the default.',
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

    const reviewingUpdate = Boolean(existingRaw && (
        String(existingRaw.revision_status || '') === 'pending_review'
        || String(existingRaw.workflow_status || '') === 'pending_review'
        || (existingRaw.pending_revision && typeof existingRaw.pending_revision === 'object')
    ));
    const publisherReviewOnly = isPublisherReviewOnly(existingRaw);

    Swal.fire(buildSwalOptions({
        title: isEdit
            ? (publisherReviewOnly || reviewingUpdate
                ? 'Review option changes'
                : (editingPublished ? 'Edit published option' : 'Edit option'))
            : (isSuperAdmin ? 'Add & publish option' : 'Add option'),
        html: `${renderReviewNotesBanner(existingRaw)}${buildSwalForm({
            description: editingPublished
                ? 'Changes are saved as an update draft. The live published option stays unchanged until a publisher approves it.'
                : (isEdit
                    ? `Key “${existing.key}” stays the same so existing answers still match. English and Tagalog labels appear in the mobile app.`
                    : (isSuperAdmin
                        ? 'Saved as published — no publisher approval needed. English and Tagalog labels appear in the mobile app.'
                        : 'English and Tagalog labels appear in the mobile app. Leave Key blank to generate it from the English label.')),
            fields,
            reviewItem: existingRaw?.pending_revision ? existingRaw : null,
        })}`,
        ...(publisherReviewOnly
            ? publisherReviewSwalButtons(existingRaw)
            : {
                confirmButtonText: editingPublished
                    ? 'Save update draft'
                    : (isEdit ? 'Save changes' : (isSuperAdmin ? 'Create & publish' : 'Create option')),
                showCancelButton: true,
            }),
        preConfirm: () => {
            if (publisherReviewOnly) {
                return { reviewedOnly: true };
            }

            const group = document.getElementById('ao-group')?.value;
            const labelEn = document.getElementById('ao-label-en')?.value?.trim() ?? '';
            const labelTl = document.getElementById('ao-label-tl')?.value?.trim() ?? '';
            const key = isEdit
                ? (existing.key ?? '')
                : (document.getElementById('ao-key')?.value?.trim() ?? '');
            const sortRaw = document.getElementById('ao-sort-order')?.value?.trim() ?? '';
            const accentRaw = document.getElementById('ao-accent')?.value?.trim() ?? '';
            const accent = normalizeHexColor(accentRaw);

            if (!labelEn || !labelTl) {
                Swal.showValidationMessage('English and Tagalog labels are required.');
                return false;
            }

            if (accentRaw && !accent) {
                Swal.showValidationMessage('Use a 6-digit hex color like #055498, or leave blank.');
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
                ? axios.put(`/admin/app-options/${existingRaw.id}`, payload)
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
    }, { size: 'lg' })).then(async (result) => {
        if (publisherReviewOnly) {
            await resolvePublisherReviewSwalResult(result, {
                basePath: '/admin/app-options',
                id: existingRaw.id,
                item: existingRaw,
                onSuccess: () => loadAppOptions(),
            });
            return;
        }

        const { isConfirmed, value } = result;
        if (!isConfirmed || !value) {
            return;
        }
        showSuccessToast(
            value.message ?? 'Option saved.',
            editingPublished ? 'Update draft saved' : (isSuperAdmin && !isEdit ? 'Published' : 'Lookups'),
        );
        loadAppOptions();
        if (editingPublished && existingRaw?.id) {
            setTimeout(() => editAppOption(existingRaw.id), 250);
        }
    });
}

function optionIconHtml(row) {
    if (!row?.icon) {
        return '';
    }
    const match = iconChoices.find((choice) => String(choice.value ?? '') === String(row.icon));
    const fa = match?.fa || 'fas fa-icons';
    return `<span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-[#055498]" title="${escapeHtml(row.icon)}"><i class="${escapeHtml(fa)}" aria-hidden="true"></i></span>`;
}

function accentSwatchHtml(row) {
    const hex = normalizeHexColor(row?.accent_color);
    if (!hex) {
        return '<span class="admin-color-swatch admin-color-swatch-empty" title="No accent" aria-hidden="true"></span>';
    }

    return `<span class="admin-color-swatch" data-swatch="${escapeHtml(hex)}" title="${escapeHtml(hex)}" aria-label="Accent ${escapeHtml(hex)}"></span>`;
}

function applyColorSwatches(root) {
    if (!root) {
        return;
    }

    root.querySelectorAll('[data-swatch]').forEach((swatch) => {
        const hex = normalizeHexColor(swatch.getAttribute('data-swatch'));
        if (hex) {
            swatch.style.setProperty('--admin-swatch', hex);
        }
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
