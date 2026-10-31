const WORKFLOW_DIFF_SKIP_KEYS = new Set([
    'id',
    'slug',
    'workflow_status',
    'revision_status',
    'review_notes',
    'pending_revision',
    'authored_by',
    'submitted_at',
    'reviewed_by',
    'reviewed_at',
    'created_at',
    'updated_at',
]);

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function hasPendingRevisionPayload(item) {
    return Boolean(item?.pending_revision) && typeof item.pending_revision === 'object';
}

export function normalizeWorkflowCompareValue(value) {
    if (value === null || value === undefined) {
        return '';
    }

    if (typeof value === 'boolean') {
        return value ? '1' : '0';
    }

    if (typeof value === 'number') {
        return String(value);
    }

    if (Array.isArray(value)) {
        return value.map((entry) => normalizeWorkflowCompareValue(entry)).join('\n');
    }

    if (typeof value === 'object') {
        try {
            return JSON.stringify(value);
        } catch {
            return String(value);
        }
    }

    const text = String(value).trim();

    if (/^<p>(?:\s|&nbsp;|<br\s*\/?>)*<\/p>$/i.test(text)) {
        return '';
    }

    return text;
}

export function humanizeWorkflowAttribute(attribute) {
    return String(attribute || '')
        .replaceAll('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

export function inferAttributeFromFieldId(fieldId) {
    const id = String(fieldId || '').trim();
    if (!id) {
        return '';
    }

    const match = id.match(/^[a-z]{1,10}-(.+)$/i);
    const rest = match ? match[1] : id;

    return rest.replaceAll('-', '_');
}

export function getRevisionFieldDiffs(item, attributeKeys = null) {
    if (!hasPendingRevisionPayload(item)) {
        return [];
    }

    const pending = item.pending_revision;
    const keys = Array.isArray(attributeKeys) && attributeKeys.length
        ? attributeKeys
        : Object.keys(pending);

    return keys.filter((key) => {
        if (WORKFLOW_DIFF_SKIP_KEYS.has(key)) {
            return false;
        }

        if (!Object.prototype.hasOwnProperty.call(pending, key)) {
            return false;
        }

        return normalizeWorkflowCompareValue(pending[key])
            !== normalizeWorkflowCompareValue(item?.[key]);
    });
}

export function formatWorkflowValuePreview(value, { maxLength = 120 } = {}) {
    const normalized = normalizeWorkflowCompareValue(value);
    if (!normalized) {
        return '(empty)';
    }

    const plain = normalized
        .replace(/<[^>]+>/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    if (plain.length <= maxLength) {
        return plain;
    }

    return `${plain.slice(0, Math.max(0, maxLength - 1))}…`;
}

/**
 * Mark Swal fields that differ from the live published values.
 *
 * @param {Array<object>} fields
 * @param {object|null} item Live row (with pending_revision)
 * @returns {Array<object>}
 */
export function annotateSwalFieldsForPendingReview(fields, item) {
    if (!Array.isArray(fields) || !hasPendingRevisionPayload(item)) {
        return fields;
    }

    const changedKeys = new Set(getRevisionFieldDiffs(item));

    return fields.map((field) => {
        const attribute = field.attribute || inferAttributeFromFieldId(field.id);
        if (!attribute || !changedKeys.has(attribute)) {
            return field;
        }

        const livePreview = formatWorkflowValuePreview(item?.[attribute]);

        return {
            ...field,
            attribute,
            changed: true,
            changedFrom: livePreview,
            changedHint: field.changedHint
                || `Changed — live value: ${livePreview}`,
        };
    });
}

/**
 * Highlight already-rendered Swal fields for modules that hand-roll form HTML.
 *
 * @param {ParentNode|null} root
 * @param {object|null} item
 * @param {Record<string, string>} fieldMap id → attribute
 */
export function highlightChangedFieldsInPopup(root, item, fieldMap = {}) {
    if (!root || !hasPendingRevisionPayload(item)) {
        return;
    }

    const changed = new Set(getRevisionFieldDiffs(item));

    Object.entries(fieldMap).forEach(([fieldId, attribute]) => {
        if (!changed.has(attribute)) {
            return;
        }

        const control = root.querySelector(`#${CSS.escape(fieldId)}`);
        const field = control?.closest('.admin-swal-field');
        if (!field) {
            return;
        }

        field.classList.add('admin-swal-field--changed');

        const label = field.querySelector('.admin-swal-label');
        if (label && !label.querySelector('.admin-swal-changed-badge')) {
            label.insertAdjacentHTML('beforeend', '<span class="admin-swal-changed-badge">Changed</span>');
        }

        if (!field.querySelector('.admin-swal-changed-hint')) {
            const livePreview = formatWorkflowValuePreview(item?.[attribute]);
            field.insertAdjacentHTML(
                'beforeend',
                `<p class="admin-swal-changed-hint">Changed — live value: ${escapeHtml(livePreview)}</p>`,
            );
        }
    });
}

export function renderPendingChangesBanner(item, fields = []) {
    if (!hasPendingRevisionPayload(item)) {
        return '';
    }

    const labelByAttribute = {};
    (Array.isArray(fields) ? fields : []).forEach((field) => {
        const attribute = field.attribute || inferAttributeFromFieldId(field.id);
        if (attribute && field.label) {
            labelByAttribute[attribute] = String(field.label).replace(/\s*\*$/, '');
        }
    });

    const diffs = getRevisionFieldDiffs(item);
    if (!diffs.length) {
        return `
            <div class="mb-3 rounded-xl border border-[#055498]/25 bg-[#055498]/8 px-3 py-2 text-left text-sm text-[#123a60]">
                <p class="font-semibold">Proposed update awaiting review</p>
                <p class="mt-1">Compare the form values with the live published version before you approve.</p>
            </div>
        `;
    }

    const chips = diffs.map((key) => {
        const label = escapeHtml(labelByAttribute[key] || humanizeWorkflowAttribute(key));
        return `<span class="admin-swal-changed-chip">${label}</span>`;
    }).join('');

    return `
        <div class="mb-3 rounded-xl border border-[#CE2028]/30 bg-[#CE2028]/8 px-3 py-2 text-left text-sm text-[#123a60]" role="status">
            <p class="font-semibold text-[#CE2028]">
                <i class="fas fa-highlighter mr-1.5" aria-hidden="true"></i>
                Fields to review (${diffs.length})
            </p>
            <p class="mt-1">Highlighted fields below differ from the live published version.</p>
            <div class="mt-2 flex flex-wrap gap-1.5">${chips}</div>
        </div>
    `;
}
