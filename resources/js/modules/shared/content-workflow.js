import Swal from 'sweetalert2';
import axios from 'axios';
import { buildSwalForm, buildSwalOptions, confirmWithSwal } from './swal-forms';
import {
    applyAdminFilterTabCounts,
    bindAdminFilterTabs,
    countByKey,
    renderAdminFilterTabs,
    setAdminFilterTab,
} from './filter-tabs';
import { showErrorToast, showSuccessToast } from './toast';
import { getStoredUser } from '../auth';

export const WORKFLOW_DRAFT = 'draft';
export const WORKFLOW_PENDING = 'pending_review';
export const WORKFLOW_PUBLISHED = 'published';

export function currentAdminRole() {
    return getStoredUser()?.role ?? null;
}

export function isEditorRole(role = currentAdminRole()) {
    return role === 'editor';
}

export function isPublisherRole(role = currentAdminRole()) {
    return role === 'publisher';
}

export function isSuperAdminRole(role = currentAdminRole()) {
    return role === 'super_admin';
}

export function canCreateWorkflowDraft(role = currentAdminRole()) {
    return role === 'editor' || role === 'super_admin';
}

export function canReviewWorkflow(role = currentAdminRole()) {
    return role === 'publisher' || role === 'super_admin';
}

export function workflowStatusLabel(status) {
    switch (String(status || '')) {
        case WORKFLOW_PENDING:
            return 'Pending review';
        case WORKFLOW_PUBLISHED:
            return 'Published';
        case WORKFLOW_DRAFT:
        default:
            return 'Draft';
    }
}

export function workflowStatusBadge(status) {
    const value = String(status || WORKFLOW_DRAFT);
    const tone = value === WORKFLOW_PUBLISHED
        ? 'bg-emerald-100 text-emerald-700'
        : value === WORKFLOW_PENDING
            ? 'bg-[#055498]/10 text-[#055498]'
            : 'bg-slate-100 text-slate-600';

    return `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${tone}">${workflowStatusLabel(value)}</span>`;
}

export function workflowFilterTabs() {
    return [
        { value: '', label: 'All', icon: 'fas fa-layer-group', hint: 'Every status' },
        { value: WORKFLOW_DRAFT, label: 'Draft', icon: 'fas fa-pen', hint: 'Editor drafts & rejected' },
        { value: WORKFLOW_PENDING, label: 'Pending', icon: 'fas fa-clock', hint: 'Awaiting publisher' },
        { value: WORKFLOW_PUBLISHED, label: 'Published', icon: 'fas fa-circle-check', hint: 'Live in the app' },
    ];
}

export function renderReviewNotesBanner(item) {
    const notes = String(item?.review_notes ?? '').trim();
    if (!notes) {
        return '';
    }

    return `
        <div class="mb-3 rounded-xl border border-[#FBD116] bg-[#FBD116]/15 px-3 py-2 text-left text-sm text-[#123a60]">
            <p class="font-semibold">Publisher review notes</p>
            <p class="mt-1 whitespace-pre-wrap">${escapeWorkflowHtml(notes)}</p>
        </div>
    `;
}

export function workflowActionDefs(item, {
    basePath,
    id = item?.id,
    role = currentAdminRole(),
} = {}) {
    const status = item?.workflow_status ?? WORKFLOW_DRAFT;
    const actions = [];

    if (canCreateWorkflowDraft(role) && [WORKFLOW_DRAFT, WORKFLOW_PENDING].includes(status)) {
        actions.push({
            key: 'edit',
            label: 'Edit',
            tooltip: 'Edit draft',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
        });
        if (status === WORKFLOW_DRAFT) {
            actions.push({
                key: 'submit',
                label: 'Submit',
                tooltip: 'Submit for review',
                icon: 'fas fa-paper-plane',
                className: 'admin-table-action-primary',
            });
        }
    }

    if (canReviewWorkflow(role) && status === WORKFLOW_PENDING) {
        actions.push({
            key: 'publish',
            label: 'Publish',
            tooltip: 'Publish now',
            icon: 'fas fa-circle-check',
            className: 'admin-table-action-primary',
        });
        actions.push({
            key: 'reject',
            label: 'Reject',
            tooltip: 'Reject with notes',
            icon: 'fas fa-rotate-left',
            className: 'admin-table-action-warning',
        });
    }

    if (isSuperAdminRole(role)) {
        actions.push({
            key: 'delete',
            label: 'Delete',
            tooltip: 'Delete permanently',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
        });
    }

    return actions.map((action) => ({
        ...action,
        path: `${basePath}/${id}`,
    }));
}

export async function submitForReview(basePath, id, { onSuccess } = {}) {
    const { isConfirmed } = await confirmWithSwal({
        icon: 'question',
        title: 'Submit for review?',
        text: 'This will notify publishers to review this item.',
        confirmButtonText: 'Submit',
    });

    if (!isConfirmed) {
        return false;
    }

    try {
        const { data } = await axios.put(`${basePath}/${id}/submit`);
        showSuccessToast(data.message ?? 'Submitted for review.', 'Submitted');
        onSuccess?.(data.data);
        return true;
    } catch ({ response }) {
        showErrorToast(response?.data?.message ?? 'Submit failed.', 'Error');
        return false;
    }
}

export async function publishContent(basePath, id, { onSuccess } = {}) {
    const { isConfirmed } = await confirmWithSwal({
        icon: 'question',
        title: 'Publish this item?',
        text: 'It will become available in the mobile app (when otherwise active).',
        confirmButtonText: 'Publish',
    });

    if (!isConfirmed) {
        return false;
    }

    try {
        const { data } = await axios.put(`${basePath}/${id}/publish`);
        showSuccessToast(data.message ?? 'Published.', 'Published');
        onSuccess?.(data.data);
        return true;
    } catch ({ response }) {
        showErrorToast(response?.data?.message ?? 'Publish failed.', 'Error');
        return false;
    }
}

export async function rejectContent(basePath, id, { onSuccess } = {}) {
    const result = await Swal.fire(buildSwalOptions({
        title: 'Reject submission',
        subtitle: 'Send this back to the editor with clear notes.',
        html: buildSwalForm({
            fields: [
                {
                    id: 'workflow-review-notes',
                    label: 'Review notes *',
                    type: 'textarea',
                    placeholder: 'Explain what needs to be fixed',
                },
            ],
        }),
        confirmButtonText: 'Reject',
        showCancelButton: true,
        preConfirm: () => {
            const notes = document.getElementById('workflow-review-notes')?.value?.trim() ?? '';
            if (!notes) {
                Swal.showValidationMessage('Review notes are required.');
                return false;
            }
            return { review_notes: notes };
        },
    }, { danger: true }));

    if (!result.isConfirmed) {
        return false;
    }

    try {
        const { data } = await axios.put(`${basePath}/${id}/reject`, result.value);
        showSuccessToast(data.message ?? 'Returned to draft.', 'Rejected');
        onSuccess?.(data.data);
        return true;
    } catch ({ response }) {
        showErrorToast(response?.data?.message ?? 'Reject failed.', 'Error');
        return false;
    }
}

export function filterByWorkflowStatus(items, statusFilter) {
    if (!statusFilter) {
        return items;
    }

    return items.filter((item) => String(item.workflow_status || WORKFLOW_DRAFT) === statusFilter);
}

export function openItemFromQuery(paramName, opener) {
    const id = new URLSearchParams(window.location.search).get(paramName);
    if (!id) {
        return;
    }
    opener(id);
}

export function syncWorkflowCreateButton(selectors) {
    const list = Array.isArray(selectors) ? selectors : [selectors];
    const allowed = canCreateWorkflowDraft();

    list.forEach((selector) => {
        if (!selector) {
            return;
        }

        document.querySelectorAll(selector).forEach((element) => {
            element.classList.toggle('hidden', !allowed);
        });
    });
}

export function guardWorkflowCreateAction(callback) {
    if (!canCreateWorkflowDraft()) {
        showErrorToast('Publishers cannot create new drafts.', 'Not allowed');
        return undefined;
    }

    return callback();
}

const WORKFLOW_ACTION_HANDLERS = {
    edit: 'edit',
    submit: 'submitWorkflow',
    publish: 'publishWorkflow',
    reject: 'rejectWorkflow',
    delete: 'remove',
};

export function buildWorkflowTableActions(item, globalName, {
    basePath,
    extraBefore = [],
    extraAfter = [],
    deleteMethod = 'remove',
    includeDelete = true,
} = {}) {
    let defs = workflowActionDefs(item, { basePath, id: item?.id });

    if (!includeDelete) {
        defs = defs.filter((def) => def.key !== 'delete');
    }

    const mapped = defs.map((def) => {
        const handler = def.key === 'delete'
            ? deleteMethod
            : (WORKFLOW_ACTION_HANDLERS[def.key] ?? def.key);

        return {
            tooltip: def.tooltip,
            label: def.label,
            icon: def.icon,
            className: def.className,
            attrs: `onclick="window.${globalName}.${handler}(${item.id})"`,
        };
    });

    return [...extraBefore, ...mapped, ...extraAfter];
}

export function attachWorkflowWindowHandlers(globalName, basePath, reloadFn) {
    const root = window[globalName] ?? {};

    root.submitWorkflow = (id) => submitForReview(basePath, id, { onSuccess: reloadFn });
    root.publishWorkflow = (id) => publishContent(basePath, id, { onSuccess: reloadFn });
    root.rejectWorkflow = (id) => rejectContent(basePath, id, { onSuccess: reloadFn });
    window[globalName] = root;
}

let workflowFilterMountSeq = 0;

export function ensureWorkflowStatusFilter({
    tabsRoot,
    selectMount,
    onChange,
    initialValue = '',
} = {}) {
    if (tabsRoot) {
        renderAdminFilterTabs(tabsRoot, workflowFilterTabs());
        bindAdminFilterTabs(tabsRoot, onChange);
        setAdminFilterTab(tabsRoot, initialValue);
        return;
    }

    if (!selectMount || selectMount.querySelector('[data-workflow-status-filter]')) {
        return;
    }

    const selectId = `workflow-status-filter-${++workflowFilterMountSeq}`;
    const wrapper = document.createElement('div');
    wrapper.className = 'mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between';
    wrapper.innerHTML = `
        <label class="text-xs font-semibold uppercase tracking-wide text-slate-500" for="${selectId}">Workflow status</label>
        <select id="${selectId}" data-workflow-status-filter class="admin-swal-input max-w-xs min-h-11 w-full sm:w-auto">
            ${workflowFilterTabs().map((tab) => (
                `<option value="${escapeWorkflowHtml(tab.value)}">${escapeWorkflowHtml(tab.label)}</option>`
            )).join('')}
        </select>
    `;
    selectMount.prepend(wrapper);

    const select = wrapper.querySelector('[data-workflow-status-filter]');
    select.value = initialValue;
    select.addEventListener('change', () => onChange(select.value ?? ''));
}

export function applyWorkflowStatusCounts(tabsRoot, rows) {
    if (!tabsRoot) {
        return;
    }

    const counts = countByKey(rows, (row) => String(row.workflow_status || WORKFLOW_DRAFT));
    applyAdminFilterTabCounts(tabsRoot, counts);
}

function escapeWorkflowHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}
