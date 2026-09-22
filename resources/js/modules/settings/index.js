import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalForm, buildSwalOptions, formatApiValidationMessage } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

const LEGAL_META = {
    privacy_policy: {
        icon: 'fa-shield-halved',
        iconWrapClass: 'admin-settings-tile-icon admin-settings-tile-icon-privacy',
        fallbackTitle: 'Privacy Policy',
        fallbackSubtitle: 'Learn how we protect your data',
    },
    terms_of_use: {
        icon: 'fa-file-lines',
        iconWrapClass: 'admin-settings-tile-icon admin-settings-tile-icon-terms',
        fallbackTitle: 'Terms of Use',
        fallbackSubtitle: 'Read the terms and conditions',
    },
};

let quotesTable;
let quotesDataTableClass;
let quotesTableMode;
let hasBoundQuotesViewportListener = false;

export function initSettingsModule() {
    const listEl = document.querySelector('[data-settings-legal-list]');
    if (listEl) {
        loadLegalPages(listEl);
    }

    initKidListoQuotesModule();

    document.querySelector('[data-admin-action="create-kid-listo-quote"]')?.addEventListener('click', () => {
        createKidListoQuote();
    });
}

async function loadLegalPages(listEl) {
    try {
        const { data } = await axios.get('/admin/legal-pages');
        const pages = data.data ?? [];

        listEl.innerHTML = pages.map(renderLegalTile).join('');

        listEl.querySelectorAll('[data-legal-slug]').forEach((button) => {
            button.addEventListener('click', () => {
                openLegalEditor(button.getAttribute('data-legal-slug'));
            });
        });
    } catch (error) {
        listEl.innerHTML = `
            <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 sm:col-span-2">
                ${escapeHtml(error.response?.data?.message ?? 'Unable to load settings pages.')}
            </div>
        `;
        showErrorToast(error.response?.data?.message ?? 'Unable to load settings.');
    }
}

function renderLegalTile(page) {
    const meta = LEGAL_META[page.slug] ?? {
        icon: 'fa-file',
        iconWrapClass: 'admin-settings-tile-icon',
        fallbackTitle: page.slug,
        fallbackSubtitle: '',
    };

    const title = page.title_en || meta.fallbackTitle;
    const subtitle = page.subtitle_en || meta.fallbackSubtitle;
    const activeBadge = page.is_active
        ? '<span class="admin-settings-tile-badge admin-settings-tile-badge-active">Active</span>'
        : '<span class="admin-settings-tile-badge">Hidden</span>';

    return `
        <button
            type="button"
            class="admin-settings-tile"
            data-legal-slug="${escapeHtml(page.slug)}"
            aria-label="Edit ${escapeHtml(title)}"
        >
            <span class="${meta.iconWrapClass}" aria-hidden="true">
                <i class="fas ${meta.icon}"></i>
            </span>
            <span class="admin-settings-tile-copy min-w-0 flex-1 text-left">
                <span class="admin-settings-tile-title-row">
                    <span class="admin-settings-tile-title">${escapeHtml(title)}</span>
                    ${activeBadge}
                </span>
                <span class="admin-settings-tile-subtitle">${escapeHtml(subtitle)}</span>
            </span>
            <span class="admin-settings-tile-chevron" aria-hidden="true">
                <i class="fas fa-chevron-right"></i>
            </span>
        </button>
    `;
}

async function openLegalEditor(slug) {
    let page;

    try {
        const { data } = await axios.get(`/admin/legal-pages/${slug}`);
        page = data.data;
    } catch (error) {
        showErrorToast(error.response?.data?.message ?? 'Unable to load page.');
        return;
    }

    const meta = LEGAL_META[slug] ?? { fallbackTitle: slug };
    const result = await Swal.fire(buildSwalOptions({
        title: `Edit ${page.title_en || meta.fallbackTitle}`,
        html: buildSwalForm({
            description: 'Updates appear in the mobile Settings screens (English and Tagalog).',
            fields: [
                {
                    id: 'legal-title-en',
                    label: 'Title (English) *',
                    value: page.title_en ?? '',
                },
                {
                    id: 'legal-title-tl',
                    label: 'Title (Tagalog) *',
                    value: page.title_tl ?? '',
                },
                {
                    id: 'legal-subtitle-en',
                    label: 'Subtitle (English)',
                    value: page.subtitle_en ?? '',
                },
                {
                    id: 'legal-subtitle-tl',
                    label: 'Subtitle (Tagalog)',
                    value: page.subtitle_tl ?? '',
                },
                {
                    id: 'legal-intro-en',
                    label: 'Intro (English)',
                    type: 'textarea',
                    value: page.intro_en ?? '',
                },
                {
                    id: 'legal-intro-tl',
                    label: 'Intro (Tagalog)',
                    type: 'textarea',
                    value: page.intro_tl ?? '',
                },
                {
                    id: 'legal-body-en',
                    label: 'Body (English) *',
                    type: 'textarea',
                    value: page.body_en ?? '',
                },
                {
                    id: 'legal-body-tl',
                    label: 'Body (Tagalog) *',
                    type: 'textarea',
                    value: page.body_tl ?? '',
                },
                {
                    id: 'legal-is-active',
                    label: 'Visibility *',
                    type: 'select',
                    value: page.is_active ? '1' : '0',
                    options: [
                        { value: '1', label: 'Active (shown in mobile app)' },
                        { value: '0', label: 'Hidden' },
                    ],
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const payload = {
                title_en: document.getElementById('legal-title-en')?.value.trim(),
                title_tl: document.getElementById('legal-title-tl')?.value.trim(),
                subtitle_en: document.getElementById('legal-subtitle-en')?.value.trim() || null,
                subtitle_tl: document.getElementById('legal-subtitle-tl')?.value.trim() || null,
                intro_en: document.getElementById('legal-intro-en')?.value.trim() || null,
                intro_tl: document.getElementById('legal-intro-tl')?.value.trim() || null,
                body_en: document.getElementById('legal-body-en')?.value.trim(),
                body_tl: document.getElementById('legal-body-tl')?.value.trim(),
                is_active: document.getElementById('legal-is-active')?.value === '1',
            };

            if (!payload.title_en || !payload.title_tl || !payload.body_en || !payload.body_tl) {
                Swal.showValidationMessage('Title and body are required for both English and Tagalog.');
                return false;
            }

            try {
                const { data } = await axios.put(`/admin/legal-pages/${slug}`, payload);
                return data;
            } catch (error) {
                Swal.showValidationMessage(
                    formatApiValidationMessage(
                        error.response,
                        error.response?.data?.message ?? 'Unable to save. Please try again.',
                    ),
                );
                return false;
            }
        },
    }, { size: 'lg' }));

    if (!result.isConfirmed || !result.value) {
        return;
    }

    showSuccessToast(result.value.message ?? 'Legal page updated.', 'Settings saved');
    const listEl = document.querySelector('[data-settings-legal-list]');
    if (listEl) {
        loadLegalPages(listEl);
    }
}

function initKidListoQuotesModule() {
    const tableEl = document.getElementById('kid-listo-quotes-table');
    if (!tableEl) {
        return;
    }

    loadAdminDataTableLibrary().then(async (DataTable) => {
        quotesDataTableClass = DataTable;
        await initializeQuotesTable(tableEl);
        bindQuotesViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadKidListoQuotes();
    });
}

export function loadKidListoQuotes(search = '') {
    if (!quotesTable) {
        return;
    }

    axios.get('/admin/kid-listo-quotes', { params: { search, per_page: 500 } })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            quotesTable.clear();
            rows.forEach((quote) => {
                quotesTable.row.add(buildQuoteRowData(quote));
            });
            quotesTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load Kid Listo quotes.');
        });
}

export function createKidListoQuote() {
    showQuoteForm(null);
}

export function editKidListoQuote(id) {
    axios.get(`/admin/kid-listo-quotes/${id}`)
        .then(({ data }) => showQuoteForm(data.data))
        .catch(() => showErrorToast('Failed to load quote.'));
}

export function removeKidListoQuote(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete Kid Listo quote?',
        html: '<p class="admin-swal-description">This quote will no longer appear in the mobile app.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) {
            return;
        }

        axios.delete(`/admin/kid-listo-quotes/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message ?? 'Quote deleted.', 'Deleted');
                loadKidListoQuotes();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function showQuoteForm(existing) {
    const isEdit = Boolean(existing);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Kid Listo Quote' : 'Add Kid Listo Quote',
        html: buildSwalForm({
            description: 'Motivational quotes are shown randomly on the mobile Kid Listo screen.',
            fields: [
                {
                    id: 'klq-message-en',
                    label: 'Message (English) *',
                    type: 'textarea',
                    value: existing?.message_en ?? '',
                    placeholder: 'Kid Listo says: ...',
                },
                {
                    id: 'klq-message-tl',
                    label: 'Message (Tagalog) *',
                    type: 'textarea',
                    value: existing?.message_tl ?? '',
                    placeholder: 'Sabi ni Kid Listo: ...',
                },
                {
                    id: 'klq-attribution',
                    label: 'Attribution',
                    value: existing?.attribution ?? 'Kid Listo',
                    placeholder: 'Kid Listo',
                },
                {
                    id: 'klq-is-active',
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
        confirmButtonText: isEdit ? 'Save Changes' : 'Create Quote',
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
            const payload = {
                message_en: document.getElementById('klq-message-en')?.value.trim(),
                message_tl: document.getElementById('klq-message-tl')?.value.trim(),
                attribution: document.getElementById('klq-attribution')?.value.trim() || null,
                is_active: document.getElementById('klq-is-active')?.value === '1',
            };

            if (!payload.message_en || !payload.message_tl) {
                Swal.showValidationMessage('English and Tagalog messages are required.');
                return false;
            }

            try {
                const request = isEdit
                    ? axios.put(`/admin/kid-listo-quotes/${existing.id}`, payload)
                    : axios.post('/admin/kid-listo-quotes', payload);
                const { data } = await request;
                return data;
            } catch (error) {
                Swal.showValidationMessage(
                    formatApiValidationMessage(
                        error.response,
                        error.response?.data?.message ?? 'Unable to save quote.',
                    ),
                );
                return false;
            }
        },
    }, { size: 'md' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) {
            return;
        }

        showSuccessToast(value.message ?? 'Quote saved.', isEdit ? 'Updated' : 'Created');
        loadKidListoQuotes();
    });
}

async function initializeQuotesTable(tableEl) {
    const nextMode = getQuotesTableMode();

    if (!quotesDataTableClass) {
        return;
    }

    if (quotesTable && quotesTableMode === nextMode) {
        return;
    }

    if (quotesTable) {
        quotesTable.destroy();
        tableEl.innerHTML = '';
    }

    quotesTableMode = nextMode;
    quotesTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search quotes:',
        searchPlaceholder: 'Search Kid Listo quotes',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ quotes',
        columns: buildQuoteColumns(nextMode),
        pageLength: nextMode === 'mobile' ? 5 : 10,
        scrollX: nextMode !== 'mobile',
        scrollCollapse: nextMode !== 'mobile',
    }));
}

function buildQuoteColumns(mode) {
    if (mode === 'mobile') {
        return [
            { title: 'Quote', className: 'dt-col-mobile-summary' },
            { title: 'Actions', orderable: false, className: 'dt-col-actions' },
        ];
    }

    return [
        { title: 'English', className: 'dt-col-primary' },
        { title: 'Tagalog' },
        { title: 'Attribution', className: 'dt-col-nowrap' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildQuoteRowData(quote) {
    const actions = [
        {
            tooltip: 'Edit quote',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.KidListoQuotes.edit(${quote.id})"`,
        },
        {
            tooltip: 'Delete quote',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.KidListoQuotes.remove(${quote.id})"`,
        },
    ];

    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    const statusBadge = quote.is_active
        ? '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>'
        : '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';

    if (quotesTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Kid Listo</p>
                        <p class="admin-table-mobile-title">${escapeHtml(truncate(quote.message_en, 140))}</p>
                    </div>
                    ${statusBadge}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Tagalog:</span> ${escapeHtml(truncate(quote.message_tl, 120))}</p>
                    <p><span>By:</span> ${escapeHtml(quote.attribution || 'Kid Listo')}</p>
                </div>
            </div>`,
            mobileActions,
        ];
    }

    return [
        escapeHtml(truncate(quote.message_en, 120)),
        escapeHtml(truncate(quote.message_tl, 120)),
        escapeHtml(quote.attribution || '—'),
        statusBadge,
        desktopActions,
    ];
}

function bindQuotesViewportListener(tableEl) {
    if (hasBoundQuotesViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 767px)');
    const handleViewportChange = async () => {
        const nextMode = getQuotesTableMode();
        if (nextMode === quotesTableMode || !document.getElementById('kid-listo-quotes-table')) {
            return;
        }
        await initializeQuotesTable(tableEl);
        loadKidListoQuotes();
    };

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleViewportChange);
    } else {
        mobileQuery.addListener(handleViewportChange);
    }

    hasBoundQuotesViewportListener = true;
}

function getQuotesTableMode() {
    return window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop';
}

function truncate(value, max = 100) {
    const text = String(value ?? '');
    if (text.length <= max) {
        return text;
    }
    return `${text.slice(0, max - 1)}…`;
}

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

window.KidListoQuotes = {
    create: createKidListoQuote,
    edit: editKidListoQuote,
    remove: removeKidListoQuote,
    reload: loadKidListoQuotes,
};

window.Settings = {
    reload: () => {
        const listEl = document.querySelector('[data-settings-legal-list]');
        if (listEl) {
            loadLegalPages(listEl);
        }
        loadKidListoQuotes();
    },
};
