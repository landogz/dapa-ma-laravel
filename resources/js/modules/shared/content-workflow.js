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
import {
    annotateSwalFieldsForPendingReview,
    formatWorkflowValuePreview,
    getRevisionFieldDiffs,
    hasPendingRevisionPayload as hasPendingRevisionPayloadFromDiff,
    highlightChangedFieldsInPopup,
    humanizeWorkflowAttribute,
    inferAttributeFromFieldId,
    normalizeWorkflowCompareValue,
    renderPendingChangesBanner,
} from './workflow-revision-diff';

export {
    annotateSwalFieldsForPendingReview,
    formatWorkflowValuePreview,
    getRevisionFieldDiffs,
    highlightChangedFieldsInPopup,
    humanizeWorkflowAttribute,
    inferAttributeFromFieldId,
    normalizeWorkflowCompareValue,
    renderPendingChangesBanner,
};

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

/**
 * Editors stage published edits for publisher approval.
 * Super admins apply published edits live — never treat them as staging.
 */
export function isEditorStagingPublished(item = null, role = currentAdminRole()) {
    if (!isEditorRole(role) || !item) {
        return false;
    }

    const workflow = String(item.workflow_status || item.status || '');

    return workflow === WORKFLOW_PUBLISHED;
}

/**
 * Publishers may open forms to review pending content, but must not save via editor APIs.
 */
export function isPublisherReviewOnly(item = null, role = currentAdminRole()) {
    if (!isPublisherRole(role) || isSuperAdminRole(role)) {
        return false;
    }

    // Any existing item opened by a publisher is review-only.
    return Boolean(item?.id ?? item);
}

/**
 * True when a publisher must Approve/Publish or request Revision for this item.
 * Posts use `status`; other modules use `workflow_status` (+ optional staged update).
 */
export function itemHasPublisherPendingReview(item = null) {
    if (!item) {
        return false;
    }

    if (isPublishedUpdatePending(item)) {
        return true;
    }

    // Posts: published live + staged update awaiting approval
    if (
        String(item.status || '') === WORKFLOW_PUBLISHED
        && String(item.revision_status || '') === WORKFLOW_PENDING
    ) {
        return true;
    }

    const workflow = String(item.workflow_status || '');
    const status = String(item.status || '');

    return workflow === WORKFLOW_PENDING || status === WORKFLOW_PENDING;
}

/**
 * Swal button set for publisher Review dialogs.
 * Publish / Revision only when the item is awaiting publisher decision; otherwise Close only.
 */
export function publisherReviewSwalButtons(item = null) {
    if (!itemHasPublisherPendingReview(item)) {
        return {
            confirmButtonText: 'Close',
            showDenyButton: false,
            showCancelButton: false,
        };
    }

    const isUpdate = isPublishedUpdatePending(item)
        || (
            String(item?.status || '') === WORKFLOW_PUBLISHED
            && String(item?.revision_status || '') === WORKFLOW_PENDING
        );

    return {
        confirmButtonText: isUpdate ? 'Approve update' : 'Publish',
        showDenyButton: true,
        denyButtonText: 'Revision',
        showCancelButton: true,
        cancelButtonText: 'Close',
        customClass: {
            denyButton: 'admin-secondary-button admin-swal-button admin-swal-button-warning',
        },
    };
}

/**
 * Handle Publish / Revision / Close from a publisher Review Swal result.
 */
export async function resolvePublisherReviewSwalResult(result, {
    basePath,
    id,
    item = null,
    onSuccess,
} = {}) {
    if (!result || !itemHasPublisherPendingReview(item)) {
        return false;
    }

    if (!result.isConfirmed && !result.isDenied) {
        return false;
    }

    if (result.isConfirmed) {
        const isUpdate = isPublishedUpdatePending(item)
            || (
                String(item?.status || '') === WORKFLOW_PUBLISHED
                && String(item?.revision_status || '') === WORKFLOW_PENDING
            );

        return publishContent(basePath, id, {
            onSuccess,
            isPublishedUpdate: isUpdate,
            item,
        });
    }

    return rejectContent(basePath, id, { onSuccess });
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

export function isPublishedUpdateDraft(item) {
    return String(item?.workflow_status || '') === WORKFLOW_PUBLISHED
        && String(item?.revision_status || '') === WORKFLOW_DRAFT;
}

export function isPublishedUpdatePending(item) {
    return String(item?.workflow_status || '') === WORKFLOW_PUBLISHED
        && String(item?.revision_status || '') === WORKFLOW_PENDING;
}

export function hasPendingRevisionPayload(item) {
    return hasPendingRevisionPayloadFromDiff(item);
}

export function itemForEditorForm(item) {
    if (!hasPendingRevisionPayload(item)) {
        return item;
    }

    return {
        ...item,
        ...item.pending_revision,
        workflow_status: item.workflow_status,
        revision_status: item.revision_status,
        review_notes: item.review_notes,
        pending_revision: item.pending_revision,
        id: item.id,
        slug: item.slug,
    };
}

export function workflowStatusBadge(status, item = null) {
    const value = String(status || WORKFLOW_DRAFT);
    const tone = value === WORKFLOW_PUBLISHED
        ? 'bg-emerald-100 text-emerald-700'
        : value === WORKFLOW_PENDING
            ? 'bg-[#055498]/10 text-[#055498]'
            : 'bg-slate-100 text-slate-600';

    const base = `<span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${tone}">${workflowStatusLabel(value)}</span>`;

    if (isPublishedUpdatePending(item)) {
        return `${base}<span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-[#055498]/10 text-[#055498]">Update pending</span>`;
    }

    if (isPublishedUpdateDraft(item)) {
        return `${base}<span class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold bg-[#FBD116]/20 text-[#123a60]">Update draft</span>`;
    }

    return base;
}

export function workflowFilterTabs(role = currentAdminRole()) {
    if (isPublisherRole(role)) {
        return [
            { value: WORKFLOW_PENDING, label: 'Pending', icon: 'fas fa-clock', hint: 'Awaiting your review' },
        ];
    }

    return [
        { value: '', label: 'All', icon: 'fas fa-layer-group', hint: 'Every status' },
        { value: WORKFLOW_DRAFT, label: 'Draft', icon: 'fas fa-pen', hint: 'Editor drafts & rejected' },
        { value: WORKFLOW_PENDING, label: 'Pending', icon: 'fas fa-clock', hint: 'Awaiting publisher' },
        { value: WORKFLOW_PUBLISHED, label: 'Published', icon: 'fas fa-circle-check', hint: 'Live in the app' },
    ];
}

export function defaultWorkflowFilter(role = currentAdminRole()) {
    return isPublisherRole(role) ? WORKFLOW_PENDING : '';
}

export function renderReviewNotesBanner(item) {
    const notes = String(item?.review_notes ?? '').trim();
    if (!notes) {
        return '';
    }

    return `
        <div class="mb-3 rounded-xl border border-[#FBD116] bg-[#FBD116]/15 px-3 py-2 text-left text-sm text-[#123a60]">
            <p class="font-semibold">Revision notes from publisher</p>
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
    const canEditPublished = canCreateWorkflowDraft(role) && status === WORKFLOW_PUBLISHED;
    const canSubmitUpdate = isEditorRole(role) && isPublishedUpdateDraft(item);
    const superAdmin = isSuperAdminRole(role);

    if (canCreateWorkflowDraft(role) && ([WORKFLOW_DRAFT, WORKFLOW_PENDING].includes(status) || canEditPublished)) {
        actions.push({
            key: 'edit',
            label: 'Edit',
            tooltip: status === WORKFLOW_PUBLISHED
                ? (superAdmin ? 'Edit published' : 'Edit published (stages an update draft)')
                : 'Edit draft',
            icon: 'fas fa-pen-to-square',
            className: 'admin-table-action-primary',
        });
        // Editors submit for publisher review; super admins publish directly (no approval).
        if (!superAdmin && (status === WORKFLOW_DRAFT || canSubmitUpdate)) {
            actions.push({
                key: 'submit',
                label: 'Submit',
                tooltip: canSubmitUpdate
                    ? 'Submit update for publisher review'
                    : 'Submit for review',
                icon: 'fas fa-paper-plane',
                className: 'admin-table-action-primary',
            });
        }
    }

    const awaitingReview = status === WORKFLOW_PENDING || isPublishedUpdatePending(item);
    const superAdminCanPublishDraft = superAdmin && status === WORKFLOW_DRAFT;

    if (canReviewWorkflow(role) && (awaitingReview || superAdminCanPublishDraft)) {
        if (!actions.some((action) => action.key === 'edit') && awaitingReview) {
            actions.push({
                key: 'edit',
                label: 'Review',
                tooltip: isPublishedUpdatePending(item)
                    ? 'Review highlighted field changes'
                    : 'Review before publishing',
                icon: 'fas fa-clipboard-check',
                className: 'admin-table-action-primary',
            });
        }

        actions.push({
            key: 'publish',
            label: isPublishedUpdatePending(item) ? 'Approve update' : 'Publish',
            tooltip: isPublishedUpdatePending(item)
                ? 'Apply update to the live published version'
                : 'Publish now',
            icon: 'fas fa-circle-check',
            className: 'admin-table-action-primary',
        });

        if (awaitingReview) {
            actions.push({
                key: 'reject',
                label: 'Revision',
                tooltip: 'Return to draft with revision notes',
                icon: 'fas fa-rotate-left',
                className: 'admin-table-action-warning',
            });
        }
    }

    if (superAdmin) {
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

export async function submitForReview(basePath, id, { onSuccess, isPublishedUpdate = false } = {}) {
    const { isConfirmed } = await confirmWithSwal({
        icon: 'question',
        title: isPublishedUpdate ? 'Submit update for review?' : 'Submit for review?',
        text: isPublishedUpdate
            ? 'Publishers will review this update. The live published version stays unchanged until they approve it.'
            : 'This will notify publishers to review this item.',
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

export async function publishContent(basePath, id, { onSuccess, isPublishedUpdate = false, item = null } = {}) {
    const changedFields = isPublishedUpdate && item
        ? getRevisionFieldDiffs(item).map((key) => humanizeWorkflowAttribute(key))
        : [];
    const changedSummary = changedFields.length
        ? ` Changed fields: ${changedFields.join(', ')}.`
        : '';

    const { isConfirmed } = await confirmWithSwal({
        icon: 'question',
        title: isPublishedUpdate ? 'Approve this update?' : 'Publish this item?',
        text: isPublishedUpdate
            ? `This will replace the live published version with the proposed update.${changedSummary}`
            : 'It will become available in the mobile app (when otherwise active).',
        confirmButtonText: isPublishedUpdate ? 'Approve update' : 'Publish',
    });

    if (!isConfirmed) {
        return false;
    }

    try {
        const { data } = await axios.put(`${basePath}/${id}/publish`);
        showSuccessToast(data.message ?? 'Published.', isPublishedUpdate ? 'Update approved' : 'Published');
        onSuccess?.(data.data);
        return true;
    } catch ({ response }) {
        showErrorToast(response?.data?.message ?? 'Publish failed.', 'Error');
        return false;
    }
}

export async function rejectContent(basePath, id, { onSuccess } = {}) {
    const result = await Swal.fire(buildSwalOptions({
        title: 'Request revision',
        subtitle: 'Return this to draft so the editor can fix it. Your notes will be visible to them.',
        html: buildSwalForm({
            fields: [
                {
                    id: 'workflow-review-notes',
                    label: 'Revision notes *',
                    type: 'textarea',
                    placeholder: 'Describe what the editor should fix before resubmitting…',
                },
            ],
        }),
        confirmButtonText: 'Return for revision',
        showCancelButton: true,
        preConfirm: () => {
            const notes = document.getElementById('workflow-review-notes')?.value?.trim() ?? '';
            if (!notes) {
                Swal.showValidationMessage('Revision notes are required.');
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
        showSuccessToast(data.message ?? 'Returned to draft for revision.', 'Revision requested');
        onSuccess?.(data.data);
        return true;
    } catch ({ response }) {
        showErrorToast(response?.data?.message ?? 'Unable to return for revision.', 'Error');
        return false;
    }
}

export function filterByWorkflowStatus(items, statusFilter) {
    if (!statusFilter) {
        return items;
    }

    return items.filter((item) => {
        if (statusFilter === WORKFLOW_PENDING) {
            return String(item.workflow_status || WORKFLOW_DRAFT) === WORKFLOW_PENDING
                || isPublishedUpdatePending(item);
        }

        if (statusFilter === WORKFLOW_DRAFT) {
            return String(item.workflow_status || WORKFLOW_DRAFT) === WORKFLOW_DRAFT
                || isPublishedUpdateDraft(item);
        }

        return String(item.workflow_status || WORKFLOW_DRAFT) === statusFilter;
    });
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
    initialValue = null,
} = {}) {
    const resolvedInitial = initialValue == null ? defaultWorkflowFilter() : initialValue;
    const tabs = workflowFilterTabs();

    if (tabsRoot) {
        renderAdminFilterTabs(tabsRoot, tabs);
        bindAdminFilterTabs(tabsRoot, onChange);
        setAdminFilterTab(tabsRoot, resolvedInitial);
        if (typeof onChange === 'function' && isPublisherRole()) {
            onChange(resolvedInitial);
        }
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
            ${tabs.map((tab) => (
                `<option value="${escapeWorkflowHtml(tab.value)}">${escapeWorkflowHtml(tab.label)}</option>`
            )).join('')}
        </select>
    `;
    selectMount.prepend(wrapper);

    const select = wrapper.querySelector('[data-workflow-status-filter]');
    select.value = resolvedInitial;
    select.addEventListener('change', () => onChange(select.value ?? ''));
    if (typeof onChange === 'function' && isPublisherRole()) {
        onChange(resolvedInitial);
    }
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
