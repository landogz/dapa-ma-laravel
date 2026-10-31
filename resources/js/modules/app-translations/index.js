import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions, confirmWithSwal } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { bindAdminFilterTabs, setAdminFilterTab } from '../shared/filter-tabs';
import {
    attachWorkflowWindowHandlers,
    buildWorkflowTableActions,
    ensureWorkflowStatusFilter,
    filterByWorkflowStatus,
    guardWorkflowCreateAction,
    isPublisherReviewOnly,
    openItemFromQuery,
    publisherReviewSwalButtons,
    renderReviewNotesBanner,
    resolvePublisherReviewSwalResult,
    syncWorkflowCreateButton,
    workflowStatusBadge,
} from '../shared/content-workflow';
import { showErrorToast, showSuccessToast } from '../shared/toast';
import { bindSvgIconPickers } from '../shared/svg-icon-picker';
import { readAdminPageFocus } from '../shared/page-focus';

let translationsTable;
let translationsDataTableClass;
let translationsTableMode;
let hasBoundViewportListener = false;
let translationsGroupFilter = 'bottom_menu';
let translationsSearchAll = false;
let appliedFocusSearch = false;
let translationGroups = [];
let translationGroupCounts = {};
let translationIconChoices = [];
let translationCardKeys = [];
let translationMenuKeys = [];
let translationLogoKeys = [];
let translationPageKeys = [];
let workflowStatusFilter = '';

const GROUP_SECTIONS = [
    {
        title: 'App',
        groups: [
            { value: 'bottom_menu', label: 'Bottom menu', icon: 'fas fa-mobile-screen-button', virtual: true },
            { value: 'navigation', label: 'Navigation', icon: 'fas fa-compass' },
            { value: 'home', label: 'Home', icon: 'fas fa-house' },
        ],
    },
    {
        title: 'Care',
        groups: [
            { value: 'care', label: 'Care', icon: 'fas fa-heart' },
            { value: 'journal', label: 'Journal', icon: 'fas fa-book' },
            { value: 'kid_listo', label: 'Kid Listo', icon: 'fas fa-quote-left' },
        ],
    },
    {
        title: 'Hope',
        groups: [
            { value: 'hope', label: 'Hope', icon: 'fas fa-handshake' },
            { value: 'rehab', label: 'Rehab', icon: 'fas fa-hospital' },
            { value: 'ddb_services', label: 'DDB Services', icon: 'fas fa-briefcase' },
        ],
    },
    {
        title: 'Content',
        groups: [
            { value: 'categories', label: 'Categories', icon: 'fas fa-tags' },
            { value: 'post_engagement', label: 'Post actions', icon: 'fas fa-thumbs-up' },
            { value: 'post_detail', label: 'Post details', icon: 'fas fa-file-lines' },
            { value: 'comments', label: 'Comments', icon: 'fas fa-comments' },
            { value: 'reviews', label: 'Reviews', icon: 'fas fa-star' },
            { value: 'bookmarks', label: 'Bookmarks', icon: 'fas fa-bookmark' },
        ],
    },
    {
        title: 'Account',
        groups: [
            { value: 'account', label: 'Account', icon: 'fas fa-user' },
            { value: 'auth', label: 'Sign in', icon: 'fas fa-right-to-bracket' },
            { value: 'registration', label: 'Registration', icon: 'fas fa-user-plus' },
        ],
    },
    {
        title: 'System',
        groups: [
            { value: 'errors', label: 'Errors', icon: 'fas fa-triangle-exclamation' },
        ],
    },
];

const GROUP_META = Object.fromEntries(
    GROUP_SECTIONS.flatMap((section) => section.groups.map((group) => [group.value, group])),
);

export function initAppTranslationsModule() {
    const tableEl = document.getElementById('app-translations-table');
    if (!tableEl) return;

    const focus = readAdminPageFocus();
    if (focus.group) {
        translationsGroupFilter = focus.group;
    } else if (focus.q) {
        translationsSearchAll = true;
    }

    loadAdminDataTableLibrary().then(async (DataTable) => {
        translationsDataTableClass = DataTable;
        await initializeTranslationsTable(tableEl);
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        bindAdminFilterTabs(document.getElementById('app-translations-group-tabs'), (value) => {
            translationsGroupFilter = value;
            translationsSearchAll = false;
            loadAppTranslations();
        });
        syncWorkflowCreateButton('[data-admin-action="create-app-translation"]');
        attachWorkflowWindowHandlers('AppTranslations', '/admin/app-translations', () => loadAppTranslations());
        ensureWorkflowStatusFilter({
            selectMount: tableEl.closest('.admin-table-shell'),
            onChange: (value) => {
                workflowStatusFilter = value;
                loadAppTranslations();
            },
        });
        openItemFromQuery('item', (id) => editAppTranslation(id));
        loadAppTranslations();
    });
}

export function loadAppTranslations(search = '') {
    if (!translationsTable) return;

    const focus = readAdminPageFocus();
    let apiSearch = search;
    if (!appliedFocusSearch && focus.q) {
        apiSearch = focus.q;
        appliedFocusSearch = true;
    }

    axios.get('/admin/app-translations', {
        params: {
            search: apiSearch,
            per_page: 200,
            group: translationsSearchAll ? undefined : (translationsGroupFilter || firstAvailableGroup()),
        },
    })
        .then(({ data }) => {
            translationGroups = (data.meta?.groups ?? []).filter((group) => group && group !== 'bible');
            translationGroupCounts = data.meta?.counts ?? {};
            translationIconChoices = data.meta?.icons ?? translationIconChoices;
            translationCardKeys = data.meta?.card_keys ?? translationCardKeys;
            translationMenuKeys = data.meta?.menu_keys ?? translationMenuKeys;
            translationLogoKeys = data.meta?.logo_keys ?? translationLogoKeys;
            translationPageKeys = data.meta?.page_keys ?? translationPageKeys;
            const requestedGroup = translationsGroupFilter;
            if (!isKnownGroup(translationsGroupFilter)) {
                translationsGroupFilter = firstAvailableGroup();
                if (translationsGroupFilter !== requestedGroup) {
                    renderTranslationGroupTabs();
                    loadAppTranslations(search);
                    return;
                }
            }
            const rows = filterByWorkflowStatus(sortTranslationRows(data.data?.data ?? []), workflowStatusFilter);
            renderTranslationGroupTabs();
            updateGroupHeading();
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
    guardWorkflowCreateAction(() => showTranslationForm(null));
}

export function editAppTranslation(id) {
    axios.get(`/admin/app-translations/${id}`).then(({ data }) => {
        showTranslationForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load translation.');
    });
}

export function removeAppTranslation(id) {
    confirmWithSwal({
        icon: 'warning',
        title: 'Delete translation?',
        text: 'This removes the string key from the mobile catalog.',
        confirmButtonText: 'Delete',
        danger: true,
    }).then(({ isConfirmed }) => {
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

function isVirtualGroup(group) {
    return Boolean(GROUP_META[group]?.virtual);
}

function isKnownGroup(group) {
    return Boolean(GROUP_META[group]) || translationGroups.includes(group);
}

function firstAvailableGroup() {
    if (isKnownGroup(translationsGroupFilter)) {
        return translationsGroupFilter;
    }
    return 'bottom_menu';
}

function renderTranslationGroupTabs() {
    const tabs = document.getElementById('app-translations-group-tabs');
    if (!tabs) {
        return;
    }

    const counts = translationGroupCounts || {};
    const extraGroups = translationGroups.filter((group) => !GROUP_META[group] && group !== 'bottom_menu');
    const signature = `${translationGroups.join('|')}:${JSON.stringify(counts)}`;
    if (tabs.dataset.filled === signature) {
        setAdminFilterTab(tabs, translationsGroupFilter);
        return;
    }

    tabs.replaceChildren();
    GROUP_SECTIONS.forEach((section) => {
        const visible = section.groups.filter((group) => group.virtual || translationGroups.includes(group.value));
        if (visible.length === 0) {
            return;
        }

        tabs.appendChild(buildSectionTitle(section.title));
        const list = document.createElement('div');
        list.className = 'admin-filter-tabs';
        visible.forEach((group) => {
            list.appendChild(buildGroupTab(group, counts[group.value] ?? 0));
        });
        tabs.appendChild(list);
    });

    if (extraGroups.length > 0) {
        tabs.appendChild(buildSectionTitle('Other'));
        const list = document.createElement('div');
        list.className = 'admin-filter-tabs';
        extraGroups.forEach((group) => {
            list.appendChild(buildGroupTab({
                value: group,
                label: formatGroupLabel(group),
                icon: 'fas fa-language',
            }, counts[group] ?? 0));
        });
        tabs.appendChild(list);
    }

    tabs.dataset.filled = signature;
    setAdminFilterTab(tabs, translationsGroupFilter);
}

function buildSectionTitle(title) {
    const heading = document.createElement('p');
    heading.className = 'admin-filter-rail-title';
    heading.textContent = title;
    return heading;
}

function buildGroupTab(group, count) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'admin-filter-tab';
    button.setAttribute('role', 'tab');
    button.dataset.filterValue = group.value;
    button.innerHTML = `
        <span class="admin-filter-tab-icon" aria-hidden="true"><i class="${escapeHtml(group.icon || 'fas fa-language')}"></i></span>
        <span class="admin-filter-tab-label">${escapeHtml(group.label)}</span>
        <span class="admin-filter-tab-count">${escapeHtml(String(count))}</span>
    `;
    return button;
}

function updateGroupHeading() {
    const title = document.getElementById('app-translations-group-title');
    const subtitle = document.getElementById('app-translations-group-subtitle');
    const label = formatGroupLabel(translationsGroupFilter);
    const count = translationGroupCounts[translationsGroupFilter] ?? 0;
    if (title) {
        title.textContent = translationsGroupFilter === 'bottom_menu'
            ? 'Bottom menu'
            : `${label} labels`;
    }
    if (subtitle) {
        subtitle.textContent = translationsGroupFilter === 'bottom_menu'
            ? 'Home, Hope, FAQ, Care, and Account — upload an icon or image for each tab.'
            : `Edit the ${count} English and Tagalog ${label.toLowerCase()} strings used in the mobile app.`;
    }
}

function sortTranslationRows(rows) {
    if (!Array.isArray(rows)) {
        return rows;
    }

    if (translationsGroupFilter === 'kid_listo') {
        return [...rows].sort((left, right) => {
            if (left.key === 'kidListoLogo') {
                return -1;
            }
            if (right.key === 'kidListoLogo') {
                return 1;
            }
            return 0;
        });
    }

    if (translationsGroupFilter !== 'bottom_menu') {
        return rows;
    }

    const order = translationMenuKeys.length ? translationMenuKeys : ['navHome', 'navHope', 'navFaq', 'navCare', 'navAccount'];
    return [...rows].sort((left, right) => {
        const leftIndex = order.indexOf(left.key);
        const rightIndex = order.indexOf(right.key);
        return (leftIndex === -1 ? 99 : leftIndex) - (rightIndex === -1 ? 99 : rightIndex);
    });
}

function translationVisualKind(key) {
    if (translationMenuKeys.includes(key)) {
        return 'menu';
    }
    if (translationLogoKeys.includes(key) || key === 'kidListoLogo') {
        return 'logo';
    }
    if (translationPageKeys.includes(key) || key === 'settingsTitle') {
        return 'page';
    }
    if (translationCardKeys.includes(key)) {
        return 'card';
    }
    return null;
}

function translationUsesCardImage(key) {
    return translationVisualKind(key) != null;
}

function iconPickerHtml(selected) {
    const choicesJson = escapeHtml(JSON.stringify(translationIconChoices));
    return `
        <div class="admin-svg-icon-picker" data-svg-icon-picker data-iconset="outline" data-resettable="1" data-icon-choices="${choicesJson}" data-none-label="">
            <input type="hidden" id="tr-icon" name="icon" value="${escapeHtml(selected ?? '')}">
        </div>
    `;
}

function iconFaClass(value) {
    const match = translationIconChoices.find((choice) => String(choice.value ?? '') === String(value ?? ''));
    return match?.fa || 'fas fa-icons';
}

function buildFormHtml(existing) {
    const statusValue = existing?.is_active === false ? '0' : '1';
    const visualKind = translationVisualKind(existing?.key ?? '') || existing?.visual_kind || null;
    const usesCard = visualKind != null;
    const imageHint = visualKind === 'menu'
        ? 'Upload a square PNG or JPG for the bottom menu icon. Keep it simple so it stays clear at 22px.'
        : visualKind === 'logo'
            ? 'Upload a PNG or JPG of Kid Listo. This replaces the default portrait on the welcome screen, Care card, and home thought banner.'
            : visualKind === 'page'
            ? 'Upload a PNG or JPG to replace the default illustration on this screen.'
            : visualKind === 'card'
            ? 'Upload a PNG or JPG to replace the default toolkit illustration. Square works best.'
            : 'Optional. JPG, PNG, WEBP, or GIF up to 5MB.';
    const removeImageField = existing?.image_url
        ? `
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-remove-image">Current image</label>
                    <select id="tr-remove-image" class="admin-swal-input">
                        <option value="0" selected>Keep current image</option>
                        <option value="1">Remove image (use icon or default)</option>
                    </select>
                </div>`
        : '';
    const previewUrl = existing?.image_url ? escapeHtml(existing.image_url) : '';
    const suggestedGroups = [
        'navigation', 'splash', 'home', 'care', 'hope', 'journal', 'kid_listo',
        'ddb_services', 'account', 'auth', 'registration', 'categories',
        'post_engagement', 'post_detail', 'comments', 'reviews', 'bookmarks',
        'rehab', 'errors',
    ];
    const groupOptions = ['', ...new Set([...suggestedGroups, ...translationGroups, existing?.group].filter((group) => group && group !== 'bible'))]
        .map((group) => {
            const selected = (existing?.group ?? translationsGroupFilter) === group ? 'selected' : '';
            const label = group ? formatGroupLabel(group) : 'Select group';
            return `<option value="${escapeHtml(group)}" ${selected}>${escapeHtml(label)}</option>`;
        })
        .join('');

    return `
        <div class="admin-swal-form">
            ${renderReviewNotesBanner(existing)}
            <p class="admin-swal-description">${visualKind === 'menu'
        ? 'This label is a mobile bottom-menu item (Home, Hope, FAQ, Care, Account). You can change the copy and replace the icon or upload an image.'
        : visualKind === 'logo'
            ? 'This is the Kid Listo portrait on the mobile app. Upload an image here to replace the default art on the welcome screen, Care card, and home thought banner.'
            : visualKind === 'page'
            ? 'This label appears as a heading on a mobile screen. You can change the copy and upload an image to replace the default art on that screen.'
            : visualKind === 'card'
            ? 'This label appears on a Care toolkit card. You can change the copy and replace the illustration or icon.'
            : 'Edit the English and Tagalog copy shown in the mobile app for this key. Add an icon or image when this label is shown as a card or menu item.'}</p>
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
                    <label class="admin-swal-label" for="tr-icon">Icon</label>
                    ${iconPickerHtml(existing?.icon)}
                    <p class="mt-1 text-xs text-slate-500">${visualKind === 'menu'
        ? 'Used on the bottom menu when no custom image is uploaded.'
        : visualKind === 'logo'
            ? 'Used if no Kid Listo image is uploaded.'
            : visualKind === 'page'
            ? 'Used on this screen when no custom image is uploaded.'
            : visualKind === 'card'
            ? 'Used on this Care card when no custom image is uploaded.'
            : 'Optional. Shown on the mobile card or menu when this label has an icon.'}</p>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-image">${visualKind === 'menu' ? 'Menu icon image' : visualKind === 'logo' ? 'Kid Listo image' : visualKind === 'page' ? 'Screen image' : usesCard ? 'Card image' : 'Image (optional)'}</label>
                    <div class="admin-swal-image-preview-wrap" data-image-preview-for="tr-image">
                        <img id="tr-image-preview" class="admin-swal-image-preview" ${previewUrl ? `src="${previewUrl}"` : ''} alt="" ${previewUrl ? '' : 'hidden'}>
                        <div id="tr-image-preview-empty" class="admin-swal-image-preview-empty" ${previewUrl ? 'hidden' : ''} aria-hidden="${previewUrl ? 'true' : 'false'}">
                            <i class="fas fa-image" aria-hidden="true"></i>
                            <span>No image</span>
                        </div>
                    </div>
                    <input id="tr-image" class="admin-swal-input" type="file" accept="image/jpeg,image/png,image/webp,image/gif" data-image-preview-input="true"${previewUrl ? ` data-preview-fallback="${previewUrl}"` : ''}>
                    <p class="mt-1 text-xs text-slate-500">${escapeHtml(imageHint)}</p>
                </div>
                ${removeImageField}
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
    const image = document.getElementById('tr-image')?.files?.[0] || null;

    return {
        key: document.getElementById('tr-key')?.value?.trim() ?? '',
        group: customGroup || selectedGroup || '',
        description: document.getElementById('tr-description')?.value?.trim() || '',
        value_en: document.getElementById('tr-en')?.value?.trim() ?? '',
        value_tl: document.getElementById('tr-tl')?.value?.trim() ?? '',
        icon: document.getElementById('tr-icon')?.value?.trim() || '',
        is_active: document.getElementById('tr-active')?.value === '1' ? '1' : '0',
        remove_image: document.getElementById('tr-remove-image')?.value === '1' ? '1' : '0',
        image,
    };
}

function showTranslationForm(existing) {
    const isEdit = Boolean(existing?.id);
    const publisherReviewOnly = isPublisherReviewOnly(existing);
    Swal.fire(buildSwalOptions({
        title: publisherReviewOnly
            ? 'Review translation'
            : (isEdit ? 'Edit translation' : 'Add translation'),
        html: buildFormHtml(existing),
        ...(publisherReviewOnly
            ? publisherReviewSwalButtons(existing)
            : {
                confirmButtonText: isEdit ? 'Save changes' : 'Create',
                showCancelButton: true,
                cancelButtonText: 'Cancel',
            }),
        didOpen: (popup) => {
            const keyInput = popup.querySelector('#tr-key');
            if (isEdit && keyInput) {
                keyInput.disabled = true;
            }
            bindSvgIconPickers(popup);
        },
        preConfirm: () => {
            if (publisherReviewOnly) {
                return { reviewedOnly: true };
            }

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
    }, { size: 'lg' })).then(async (result) => {
        if (publisherReviewOnly) {
            await resolvePublisherReviewSwalResult(result, {
                basePath: '/admin/app-translations',
                id: existing.id,
                item: existing,
                onSuccess: () => loadAppTranslations(),
            });
            return;
        }

        const { isConfirmed, value } = result;
        if (!isConfirmed || !value) return;

        const form = new FormData();
        Object.entries(value).forEach(([key, val]) => {
            if (key === 'image') {
                if (val) form.append('image', val);
                return;
            }
            form.append(key, val ?? '');
        });

        const request = isEdit
            ? axios.post(`/admin/app-translations/${existing.id}`, form)
            : axios.post('/admin/app-translations', form);

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
    bindAdminActionTooltipSuppression(tableEl);
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 1023px)');
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
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
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
        { title: 'English', className: 'dt-col-primary' },
        { title: 'Tagalog', className: 'dt-col-primary' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Workflow', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(item) {
    const actions = buildWorkflowTableActions(item, 'AppTranslations', {
        basePath: '/admin/app-translations',
    });

    const desktopActionsMarkup = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActionsMarkup = buildAdminActionButtons(actions, { isMobile: true, nowrap: true });
    const workflowBadge = workflowStatusBadge(item.workflow_status);

    const enPreview = truncateText(item.value_en ?? '', 80);
    const tlPreview = truncateText(item.value_tl ?? '', 80);
    const visual = visualMarkup(item);

    if (translationsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div class="min-w-0">
                        <p class="admin-table-mobile-kicker">${escapeHtml(formatGroupLabel(item.group ?? 'general'))}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(item.key)}</p>
                    </div>
                    <div class="flex flex-col items-end gap-1">${workflowBadge}${statusBadge(item.is_active)}</div>
                </div>
                <div class="admin-table-mobile-details">
                    ${visual ? `<p>${visual}</p>` : ''}
                    <p><span>EN:</span> ${escapeHtml(enPreview)}</p>
                    <p><span>TL:</span> ${escapeHtml(tlPreview)}</p>
                </div>
            </div>`,
            mobileActionsMarkup,
        ];
    }

    return [
        `<span class="inline-flex max-w-full items-center gap-2">${visual}<span class="min-w-0 truncate">${escapeHtml(item.key ?? '')}</span></span>`,
        escapeHtml(enPreview),
        escapeHtml(tlPreview),
        statusBadge(item.is_active),
        workflowBadge,
        desktopActionsMarkup,
    ];
}

function visualMarkup(item) {
    if (item?.image_url) {
        return `<img src="${escapeHtml(item.image_url)}" alt="" class="h-8 w-8 shrink-0 rounded-lg bg-slate-50 object-contain ring-1 ring-slate-200">`;
    }
    if (item?.icon) {
        return `<span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-[#055498]" title="${escapeHtml(item.icon)}"><i class="${escapeHtml(iconFaClass(item.icon))}" aria-hidden="true"></i></span>`;
    }
    if (item?.uses_card_image) {
        return `<span class="inline-flex h-8 shrink-0 items-center rounded-lg bg-amber-50 px-2 text-[11px] font-semibold text-amber-800">${item.visual_kind === 'menu' ? 'Menu icon' : item.visual_kind === 'logo' ? 'Kid Listo art' : item.visual_kind === 'page' ? 'Screen art' : 'Default art'}</span>`;
    }
    return '';
}

function formatGroupLabel(group) {
    if (!group || group === '—') return '—';
    if (GROUP_META[group]?.label) {
        return GROUP_META[group].label;
    }
    return String(group)
        .split('_')
        .filter(Boolean)
        .map((part) => (part === 'ddb' ? 'DDB' : part.charAt(0).toUpperCase() + part.slice(1)))
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
