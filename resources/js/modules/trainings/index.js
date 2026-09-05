import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions, loadAdminDataTableLibrary } from '../shared/datatables';
import { buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let trainingsTable;
let trainingsDataTableClass;
let trainingsTableMode;
let hasBoundViewportListener = false;

const TRAINING_CATEGORIES = [
    'Preventive Education',
    'Capacity Building',
    'Community-Based',
    'Anti-Drug Advocacy',
    'Youth Development',
];

export function initTrainingsModule() {
    const tableEl = document.getElementById('trainings-table');
    if (!tableEl) return;

    loadAdminDataTableLibrary().then(async (DataTable) => {
        trainingsDataTableClass = DataTable;
        await initializeTrainingsTable(tableEl);
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadTrainings();
    });
}

export function loadTrainings(search = '') {
    if (!trainingsTable) return;

    axios.get('/admin/trainings', { params: { search, per_page: 200 } })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            trainingsTable.clear();
            rows.forEach((training) => {
                trainingsTable.row.add(buildRowData(training));
            });
            trainingsTable.draw();
        })
        .catch(() => {
            showErrorToast('Failed to load training listings.');
        });
}

export function createTraining() {
    showTrainingForm(null);
}

export function editTraining(id) {
    axios.get(`/admin/trainings/${id}`).then(({ data }) => {
        showTrainingForm(data.data);
    }).catch(() => {
        showErrorToast('Failed to load training details.');
    });
}

export function removeTraining(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete Training?',
        html: '<p class="admin-swal-description">This cannot be undone.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) return;
        axios.delete(`/admin/trainings/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message, 'Deleted');
                loadTrainings();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function buildTrainingFormHtml(existing) {
    const statusValue = existing?.is_active === false ? '0' : '1';
    const categoryOptions = TRAINING_CATEGORIES.map((category) => {
        const selected = (existing?.category ?? 'Preventive Education') === category ? 'selected' : '';
        return `<option value="${escapeHtml(category)}" ${selected}>${escapeHtml(category)}</option>`;
    }).join('');

    return `
        <div class="admin-swal-form">
            <p class="admin-swal-description">Publish DDB training opportunities for the mobile Trainings list.</p>
            <div class="admin-swal-fields">
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-title">Title *</label>
                    <input id="tr-title" class="admin-swal-input" type="text" placeholder="Training title" value="${escapeHtml(existing?.title ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-category">Category *</label>
                    <select id="tr-category" class="admin-swal-input">${categoryOptions}</select>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-description">Description</label>
                    <textarea id="tr-description" class="admin-swal-input" rows="3" placeholder="Short description">${escapeHtml(existing?.description ?? '')}</textarea>
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-region">Region</label>
                    <input id="tr-region" class="admin-swal-input" type="text" placeholder="e.g. NCR" value="${escapeHtml(existing?.region ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-venue">Venue</label>
                    <input id="tr-venue" class="admin-swal-input" type="text" placeholder="Venue or online platform" value="${escapeHtml(existing?.venue ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-start-date">Start date</label>
                    <input id="tr-start-date" class="admin-swal-input" type="date" value="${escapeHtml(formatDateInput(existing?.start_date))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-end-date">End date</label>
                    <input id="tr-end-date" class="admin-swal-input" type="date" value="${escapeHtml(formatDateInput(existing?.end_date))}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-schedule">Schedule notes</label>
                    <input id="tr-schedule" class="admin-swal-input" type="text" placeholder="e.g. 8:00 AM – 4:00 PM" value="${escapeHtml(existing?.schedule_notes ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-organizer">Organizer</label>
                    <input id="tr-organizer" class="admin-swal-input" type="text" placeholder="Organizing office" value="${escapeHtml(existing?.organizer ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-contact">Contact</label>
                    <input id="tr-contact" class="admin-swal-input" type="text" placeholder="Phone or email" value="${escapeHtml(existing?.contact ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-registration">Registration URL</label>
                    <input id="tr-registration" class="admin-swal-input" type="url" placeholder="https://" value="${escapeHtml(existing?.registration_url ?? '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-slots">Slots</label>
                    <input id="tr-slots" class="admin-swal-input" type="number" min="1" placeholder="Optional capacity" value="${escapeHtml(existing?.slots != null ? String(existing.slots) : '')}">
                </div>
                <div class="admin-swal-field">
                    <label class="admin-swal-label" for="tr-status">Status *</label>
                    <select id="tr-status" class="admin-swal-input">
                        <option value="1" ${statusValue === '1' ? 'selected' : ''}>Active</option>
                        <option value="0" ${statusValue === '0' ? 'selected' : ''}>Inactive</option>
                    </select>
                </div>
            </div>
        </div>
    `;
}

function showTrainingForm(existing) {
    const isEdit = Boolean(existing);

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Training' : 'Add Training',
        html: buildTrainingFormHtml(existing),
        confirmButtonText: isEdit ? 'Save changes' : 'Create training',
        cancelButtonText: 'Cancel',
        width: '40rem',
        preConfirm: () => {
            const title = document.getElementById('tr-title')?.value.trim();
            const category = document.getElementById('tr-category')?.value.trim();
            const startDate = document.getElementById('tr-start-date')?.value || null;
            const endDate = document.getElementById('tr-end-date')?.value || null;
            const slotsRaw = document.getElementById('tr-slots')?.value.trim();
            const registrationUrl = document.getElementById('tr-registration')?.value.trim() || null;

            if (!title || !category) {
                Swal.showValidationMessage('Title and Category are required.');
                return false;
            }

            if (startDate && endDate && endDate < startDate) {
                Swal.showValidationMessage('End date must be on or after start date.');
                return false;
            }

            return {
                title,
                category,
                description: document.getElementById('tr-description')?.value.trim() || null,
                region: document.getElementById('tr-region')?.value.trim() || null,
                venue: document.getElementById('tr-venue')?.value.trim() || null,
                start_date: startDate,
                end_date: endDate,
                schedule_notes: document.getElementById('tr-schedule')?.value.trim() || null,
                organizer: document.getElementById('tr-organizer')?.value.trim() || null,
                contact: document.getElementById('tr-contact')?.value.trim() || null,
                registration_url: registrationUrl,
                slots: slotsRaw ? Number(slotsRaw) : null,
                is_active: document.getElementById('tr-status')?.value === '1',
            };
        },
    })).then(({ isConfirmed, value }) => {
        if (!isConfirmed) return;

        const request = isEdit
            ? axios.put(`/admin/trainings/${existing.id}`, value)
            : axios.post('/admin/trainings', value);

        request.then(({ data }) => {
            showSuccessToast(data.message, isEdit ? 'Updated' : 'Created');
            loadTrainings();
        }).catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Operation failed.');
        });
    });
}

window.Trainings = {
    create: createTraining,
    edit: editTraining,
    remove: removeTraining,
};

async function initializeTrainingsTable(tableEl) {
    const nextMode = getTableMode();

    if (!trainingsDataTableClass) {
        return;
    }

    if (trainingsTable && trainingsTableMode === nextMode) {
        return;
    }

    if (trainingsTable) {
        trainingsTable.destroy();
        tableEl.innerHTML = '';
    }

    trainingsTableMode = nextMode;
    trainingsTable = await createAdminDataTable(tableEl, getAdminDataTableOptions({
        searchLabel: 'Search trainings:',
        searchPlaceholder: 'Search trainings',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ trainings',
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

        if (nextMode === trainingsTableMode) {
            return;
        }

        await initializeTrainingsTable(tableEl);
        loadTrainings();
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
            { title: 'Training', className: 'dt-col-mobile-summary' },
            { title: 'Actions', orderable: false, className: 'dt-col-actions' },
        ];
    }

    return [
        { title: 'ID', className: 'dt-col-id' },
        { title: 'Title', className: 'dt-col-primary dt-col-name' },
        { title: 'Category', className: 'dt-col-nowrap' },
        { title: 'Region', className: 'dt-col-nowrap' },
        { title: 'Schedule', className: 'dt-col-nowrap' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, className: 'dt-col-actions' },
    ];
}

function buildRowData(training) {
    const actions = [
        {
            tooltip: 'Edit training',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.Trainings.edit(${training.id})"`,
        },
        {
            tooltip: 'Delete training',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.Trainings.remove(${training.id})"`,
        },
    ];

    const desktopActionsMarkup = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActionsMarkup = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    const scheduleLabel = formatSchedule(training);

    if (trainingsTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">Training #${escapeHtml(String(training.id))}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(training.title)}</p>
                    </div>
                    ${statusBadge(training.is_active)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Category:</span> ${escapeHtml(training.category ?? '—')}</p>
                    <p><span>Region:</span> ${escapeHtml(training.region ?? '—')}</p>
                    <p><span>Schedule:</span> ${escapeHtml(scheduleLabel)}</p>
                </div>
            </div>`,
            mobileActionsMarkup,
        ];
    }

    return [
        training.id,
        escapeHtml(training.title),
        escapeHtml(training.category ?? '—'),
        escapeHtml(training.region ?? '—'),
        escapeHtml(scheduleLabel),
        statusBadge(training.is_active),
        desktopActionsMarkup,
    ];
}

function formatDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function formatSchedule(training) {
    const start = formatDateInput(training.start_date);
    const end = formatDateInput(training.end_date);

    if (start && end && start !== end) {
        return `${start} → ${end}`;
    }

    if (start) {
        return start;
    }

    return training.schedule_notes || 'TBA';
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
