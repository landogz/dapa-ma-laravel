import axios from 'axios';
import Swal from 'sweetalert2';
import { createAdminDataTable, getAdminDataTableOptions } from '../shared/datatables';
import { buildSwalForm, buildSwalOptions } from '../shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from '../shared/table-actions';
import { showErrorToast, showSuccessToast } from '../shared/toast';

let toolkitTable;
let toolkitTableMode;
let hasBoundViewportListener = false;
let currentTypeFilter = '';

const TYPE_LABELS = {
    stress: 'Stress Check',
    anxiety: 'Anxiety Check',
    sleep: 'Sleep Quality',
};

const ANSWER_LABELS = {
    likert5: '5-point scale',
    likert4: '4-point scale',
    time: 'Time input',
};

export function initCareToolkitModule() {
    const tableEl = document.getElementById('care-toolkit-questions-table');
    if (!tableEl) {
        return;
    }

    document.querySelector('[data-admin-action="create-care-toolkit-question"]')
        ?.addEventListener('click', () => createCareToolkitQuestion());

    document.getElementById('care-toolkit-type-filter')
        ?.addEventListener('change', (event) => {
            currentTypeFilter = event.target.value || '';
            loadCareToolkitQuestions();
        });

    initializeToolkitTable(tableEl).then(() => {
        bindViewportListener(tableEl);
        bindAdminActionTooltipSuppression(tableEl);
        loadCareToolkitQuestions();
    });
}

async function initializeToolkitTable(tableElement) {
    const nextMode = getTableMode();

    if (toolkitTable && toolkitTableMode === nextMode) {
        return;
    }

    if (toolkitTable) {
        toolkitTable.destroy();
        tableElement.innerHTML = '';
    }

    toolkitTableMode = nextMode;
    toolkitTable = await createAdminDataTable(
        tableElement,
        getAdminDataTableOptions({
            columns: buildColumns(nextMode),
            searchPlaceholder: 'Search toolkit questions...',
            infoLabel: 'Showing _START_ to _END_ of _TOTAL_ questions',
            pageLength: nextMode === 'mobile' ? 5 : 15,
            scrollX: nextMode !== 'mobile',
            scrollCollapse: nextMode !== 'mobile',
        }),
    );
}

function bindViewportListener(tableEl) {
    if (hasBoundViewportListener) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 767px)');
    const handleViewportChange = async () => {
        const nextMode = getTableMode();
        if (nextMode === toolkitTableMode) {
            return;
        }

        await initializeToolkitTable(tableEl);
        loadCareToolkitQuestions();
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
            { title: 'Question', className: 'dt-col-mobile-summary', orderable: false },
        ];
    }

    return [
        { title: 'Order', width: '64px', className: 'dt-col-nowrap' },
        { title: 'Toolkit', className: 'dt-col-nowrap' },
        { title: 'Answer type', className: 'dt-col-nowrap' },
        { title: 'Question (EN)', className: 'dt-col-primary dt-col-wide' },
        { title: 'Status', className: 'dt-col-nowrap' },
        { title: 'Actions', orderable: false, searchable: false, className: 'dt-col-actions' },
    ];
}

export function loadCareToolkitQuestions() {
    if (!toolkitTable) {
        return;
    }

    axios.get('/admin/care-toolkit-questions', {
        params: {
            per_page: 500,
            toolkit_type: currentTypeFilter || undefined,
        },
    })
        .then(({ data }) => {
            const rows = data.data?.data ?? [];
            toolkitTable.clear();
            rows.forEach((row) => toolkitTable.row.add(buildRowData(row)));
            toolkitTable.draw();
        })
        .catch(({ response }) => {
            showErrorToast(response?.data?.message ?? 'Failed to load Care Toolkit questions.');
        });
}

export function createCareToolkitQuestion() {
    showQuestionForm(null);
}

export function editCareToolkitQuestion(id) {
    axios.get(`/admin/care-toolkit-questions/${id}`)
        .then(({ data }) => showQuestionForm(data.data))
        .catch(() => showErrorToast('Failed to load question.'));
}

export function removeCareToolkitQuestion(id) {
    Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: 'Delete this question?',
        html: '<p class="admin-swal-description">It will no longer appear in the mobile Care Toolkit.</p>',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel',
    }, { danger: true, size: 'sm' })).then(({ isConfirmed }) => {
        if (!isConfirmed) {
            return;
        }

        axios.delete(`/admin/care-toolkit-questions/${id}`)
            .then(({ data }) => {
                showSuccessToast(data.message ?? 'Question deleted.', 'Deleted');
                loadCareToolkitQuestions();
            })
            .catch(({ response }) => {
                showErrorToast(response?.data?.message ?? 'Delete failed.');
            });
    });
}

function buildRowData(row) {
    const preview = escapeHtml(truncate(row.question_en, 90));
    const type = escapeHtml(TYPE_LABELS[row.toolkit_type] ?? row.toolkit_type);
    const answer = escapeHtml(ANSWER_LABELS[row.answer_type] ?? row.answer_type);
    const status = row.is_active
        ? '<span class="admin-status-badge rehab-status-badge rehab-status-badge-active">Active</span>'
        : '<span class="admin-status-badge rehab-status-badge rehab-status-badge-inactive">Inactive</span>';

    const actions = [
        {
            tooltip: 'Edit question',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
            attrs: `onclick="window.CareToolkit.edit(${row.id})"`,
        },
        {
            tooltip: 'Delete question',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `onclick="window.CareToolkit.remove(${row.id})"`,
        },
    ];

    const desktopActions = buildAdminActionButtons(actions, { isMobile: false, nowrap: true });
    const mobileActions = buildAdminActionButtons([
        { ...actions[0], label: 'Edit' },
        { ...actions[1], label: 'Delete' },
    ], { isMobile: true, nowrap: true });

    if (toolkitTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">${type}</p>
                        <p class="admin-table-mobile-title">${preview || '—'}</p>
                    </div>
                    ${status}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Order:</span> ${escapeHtml(String(row.sort_order ?? 0))}</p>
                    <p><span>Answer type:</span> ${answer}</p>
                </div>
                <div class="admin-table-mobile-actions">${mobileActions}</div>
            </div>`,
        ];
    }

    return [
        String(row.sort_order ?? 0),
        type,
        answer,
        preview || '—',
        status,
        desktopActions,
    ];
}

function showQuestionForm(existing) {
    const isEdit = Boolean(existing);
    const optionsEn = Array.isArray(existing?.options_en)
        ? existing.options_en.join('\n')
        : '';
    const optionsTl = Array.isArray(existing?.options_tl)
        ? existing.options_tl.join('\n')
        : '';

    Swal.fire(buildSwalOptions({
        title: isEdit ? 'Edit Care Toolkit Question' : 'Add Care Toolkit Question',
        html: buildSwalForm({
            description: 'These questions power Stress, Anxiety, and Sleep checks in the mobile Self-care Toolkit.',
            fields: [
                {
                    id: 'ctq-toolkit-type',
                    label: 'Toolkit *',
                    type: 'select',
                    value: existing?.toolkit_type ?? 'stress',
                    options: [
                        { value: 'stress', label: 'Stress Check' },
                        { value: 'anxiety', label: 'Anxiety Check' },
                        { value: 'sleep', label: 'Sleep Quality' },
                    ],
                },
                {
                    id: 'ctq-answer-type',
                    label: 'Answer type *',
                    type: 'select',
                    value: existing?.answer_type ?? 'likert5',
                    options: [
                        { value: 'likert5', label: '5-point scale (Never → Very Often)' },
                        { value: 'likert4', label: '4-point scale (Not at all → Nearly every day)' },
                        { value: 'time', label: 'Time input (HH:MM)' },
                    ],
                },
                {
                    id: 'ctq-question-en',
                    label: 'Question (English) *',
                    type: 'textarea',
                    value: existing?.question_en ?? '',
                },
                {
                    id: 'ctq-question-tl',
                    label: 'Question (Tagalog) *',
                    type: 'textarea',
                    value: existing?.question_tl ?? '',
                },
                {
                    id: 'ctq-options-en',
                    label: 'Custom options EN (one per line, optional)',
                    type: 'textarea',
                    value: optionsEn,
                    placeholder: 'Leave blank to use the default scale',
                },
                {
                    id: 'ctq-options-tl',
                    label: 'Custom options TL (one per line, optional)',
                    type: 'textarea',
                    value: optionsTl,
                },
                {
                    id: 'ctq-sort-order',
                    label: 'Sort order',
                    value: String(existing?.sort_order ?? ''),
                    placeholder: 'Auto if blank',
                },
                {
                    id: 'ctq-is-active',
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
        confirmButtonText: isEdit ? 'Save changes' : 'Create question',
        showCancelButton: true,
        preConfirm: () => {
            const toolkitType = document.getElementById('ctq-toolkit-type')?.value;
            const answerType = document.getElementById('ctq-answer-type')?.value;
            const questionEn = document.getElementById('ctq-question-en')?.value?.trim() ?? '';
            const questionTl = document.getElementById('ctq-question-tl')?.value?.trim() ?? '';
            const sortRaw = document.getElementById('ctq-sort-order')?.value?.trim() ?? '';
            const isActive = document.getElementById('ctq-is-active')?.value === '1';

            if (!questionEn || !questionTl) {
                Swal.showValidationMessage('English and Tagalog questions are required.');
                return false;
            }

            const payload = {
                toolkit_type: toolkitType,
                answer_type: answerType,
                question_en: questionEn,
                question_tl: questionTl,
                options_en: document.getElementById('ctq-options-en')?.value ?? '',
                options_tl: document.getElementById('ctq-options-tl')?.value ?? '',
                is_active: isActive,
            };

            if (sortRaw !== '') {
                payload.sort_order = Number.parseInt(sortRaw, 10);
            }

            const request = isEdit
                ? axios.put(`/admin/care-toolkit-questions/${existing.id}`, payload)
                : axios.post('/admin/care-toolkit-questions', payload);

            return request
                .then(({ data }) => data)
                .catch((error) => {
                    Swal.showValidationMessage(
                        error.response?.data?.message
                        ?? 'Unable to save question. Please try again.',
                    );
                    return false;
                });
        },
    }, { size: 'lg' })).then(({ isConfirmed, value }) => {
        if (!isConfirmed || !value) {
            return;
        }
        showSuccessToast(value.message ?? 'Question saved.', 'Care Toolkit');
        loadCareToolkitQuestions();
    });
}

function truncate(value, max = 90) {
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

window.CareToolkit = {
    create: createCareToolkitQuestion,
    edit: editCareToolkitQuestion,
    remove: removeCareToolkitQuestion,
    reload: loadCareToolkitQuestions,
};
