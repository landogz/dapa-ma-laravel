import Swal from 'sweetalert2';
import { bindAddressFields, destroyAddressAutocompletes } from '../address-autocomplete';
import { bindPhilippinePhoneFields, destroyPhilippinePhoneFields } from '../phone-validation';
import {
    assertPasswordFields,
    bindPasswordPolicyFields,
    destroyPasswordPolicyFields,
    PASSWORD_POLICY_HINT,
} from '../password-validation';
import { bindRichTextEditors, destroyRichTextEditors, isBodyRichTextField } from '../rich-text-editor';
import {
    annotateSwalFieldsForPendingReview,
    hasPendingRevisionPayload,
    renderPendingChangesBanner,
} from './workflow-revision-diff';
import { bindPasswordToggles } from './password-toggle';
import { bindSvgIconPickers } from './svg-icon-picker';

const DEFAULT_PICKER_COLOR = '#055498';

const DEFAULT_COLOR_PRESETS = [
    { value: '#055498', className: 'bg-[#055498]', label: 'Medium Electric Blue' },
    { value: '#123a60', className: 'bg-[#123a60]', label: 'Nile Blue' },
    { value: '#CE2028', className: 'bg-[#CE2028]', label: 'Fire Engine Red' },
    { value: '#FBD116', className: 'bg-[#FBD116]', label: 'Bright Gold' },
    { value: '#7C3AED', className: 'bg-[#7C3AED]', label: 'Purple' },
    { value: '#94A3B8', className: 'bg-[#94A3B8]', label: 'Slate' },
];

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

export function normalizeHexColor(value) {
    const raw = String(value ?? '').trim();
    if (!raw) {
        return '';
    }

    const hex = raw.startsWith('#') ? raw : `#${raw}`;
    if (!/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        return '';
    }

    return hex.toUpperCase();
}

function renderOptions(options = [], selectedValue = '') {
    return options
        .map((option) => {
            const value = escapeHtml(option.value);
            const label = escapeHtml(option.label);
            const selected = String(option.value) === String(selectedValue) ? ' selected' : '';

            return `<option value="${value}"${selected}>${label}</option>`;
        })
        .join('');
}

function fieldShellClass(field) {
    return field.changed ? 'admin-swal-field admin-swal-field--changed' : 'admin-swal-field';
}

function fieldChangedBadge(field) {
    if (!field.changed) {
        return '';
    }

    return '<span class="admin-swal-changed-badge">Changed</span>';
}

function fieldChangedHint(field) {
    if (!field.changed) {
        return '';
    }

    const text = field.changedHint
        || (field.changedFrom ? `Changed — live value: ${field.changedFrom}` : 'Changed from the live published version');

    return `<p class="admin-swal-changed-hint">${escapeHtml(text)}</p>`;
}

function renderField(field) {
    const label = `<label class="admin-swal-label" for="${field.id}"><span>${escapeHtml(field.label)}</span>${fieldChangedBadge(field)}</label>`;
    const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
    const value = field.value ? ` value="${escapeHtml(field.value)}"` : '';
    const min = field.min ? ` min="${escapeHtml(field.min)}"` : '';
    const hint = field.hint ? `<p class="admin-swal-hint">${escapeHtml(field.hint)}</p>` : '';
    const changedHint = fieldChangedHint(field);
    const shellClass = fieldShellClass(field);

    if (isBodyRichTextField(field)) {
        const richPlaceholder = field.placeholder
            ? ` placeholder="${escapeHtml(field.placeholder)}"`
            : ' placeholder="Write the main content"';

        return `
            <div class="${shellClass}">
                ${label}
                <div class="admin-rich-text">
                    <textarea id="${field.id}" class="admin-swal-textarea admin-swal-richtext" data-rich-text${richPlaceholder}>${escapeHtml(field.value ?? '')}</textarea>
                </div>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'textarea') {
        return `
            <div class="${shellClass}">
                ${label}
                <textarea id="${field.id}" class="admin-swal-textarea"${placeholder}>${escapeHtml(field.value ?? '')}</textarea>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'checkbox') {
        const checked = field.checked === true
            || field.value === true
            || field.value === 1
            || field.value === '1'
            ? ' checked'
            : '';

        return `
            <div class="${shellClass}">
                <label class="admin-swal-checkbox" for="${field.id}">
                    <input id="${field.id}" class="admin-swal-checkbox-input" type="checkbox" value="1"${checked}>
                    <span class="admin-swal-checkbox-copy">
                        <span class="admin-swal-checkbox-title">${escapeHtml(field.label)}${fieldChangedBadge(field)}</span>
                        ${hint}
                        ${changedHint}
                    </span>
                </label>
            </div>
        `;
    }

    if (field.type === 'select') {
        return `
            <div class="${shellClass}">
                ${label}
                <select id="${field.id}" class="admin-swal-select">
                    ${renderOptions(field.options, field.value)}
                </select>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'file') {
        const previewUrl = field.previewUrl ? escapeHtml(field.previewUrl) : '';
        const showPreview = field.showPreview === true || Boolean(field.previewUrl);
        const previewBlock = showPreview
            ? `
                <div class="admin-swal-image-preview-wrap" data-image-preview-for="${escapeHtml(field.id)}">
                    <img
                        id="${escapeHtml(field.id)}-preview"
                        class="admin-swal-image-preview"
                        ${previewUrl ? `src="${previewUrl}"` : ''}
                        alt=""
                        ${previewUrl ? '' : 'hidden'}
                    >
                    <div
                        id="${escapeHtml(field.id)}-preview-empty"
                        class="admin-swal-image-preview-empty"
                        ${previewUrl ? 'hidden' : ''}
                        aria-hidden="${previewUrl ? 'true' : 'false'}"
                    >
                        <i class="fas fa-image" aria-hidden="true"></i>
                        <span>No image</span>
                    </div>
                </div>
            `
            : '';

        return `
            <div class="${shellClass}">
                ${label}
                ${previewBlock}
                <input id="${field.id}" class="admin-swal-input" type="file"${field.accept ? ` accept="${escapeHtml(field.accept)}"` : ''} data-image-preview-input="${showPreview ? 'true' : 'false'}"${previewUrl ? ` data-preview-fallback="${previewUrl}"` : ''}>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'address') {
        return `
            <div class="${shellClass}">
                ${label}
                <div class="address-autocomplete">
                    <input id="${field.id}" class="admin-swal-input" type="text" data-address-autocomplete autocomplete="off"${placeholder}${value}>
                </div>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'tel' || field.type === 'phone') {
        const multiple = field.allowMultiple ? ' data-phone-multiple' : '';
        const shortCodes = field.allowShortCodes ? ' data-phone-shortcodes' : '';
        const phoneHint = field.hint
            ? hint
            : '<p class="admin-swal-hint">Philippine numbers only, e.g. 09XX XXX XXXX or +63…</p>';

        return `
            <div class="${shellClass}">
                ${label}
                <input id="${field.id}" class="admin-swal-input" type="tel" inputmode="tel" data-phone-ph autocomplete="tel"${multiple}${shortCodes}${placeholder}${value}>
                ${phoneHint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'password') {
        const isCurrent = field.current === true;
        const isConfirm = Boolean(field.confirmFor) || /^confirm(\s+new)?\s+password/i.test(field.label ?? '');
        const optional = field.optional === true ? ' data-password-optional' : '';
        const confirmFor = field.confirmFor ? ` data-password-confirm-for="${escapeHtml(field.confirmFor)}"` : '';
        const policyAttr = isCurrent
            ? ' data-password-current'
            : (isConfirm ? ' data-password-confirm' : ' data-password-policy');
        const autocomplete = isCurrent ? 'current-password' : 'new-password';
        const passwordHint = field.hint
            ? hint
            : (isCurrent || isConfirm
                ? hint
                : `<p class="admin-swal-hint password-policy-hint" data-password-hint>${PASSWORD_POLICY_HINT}</p>`);

        return `
            <div class="${shellClass}">
                ${label}
                <div class="admin-swal-password-wrap">
                    <input id="${field.id}" class="admin-swal-input admin-swal-input-password" type="password"${placeholder}${value}${min} autocomplete="${autocomplete}"${policyAttr}${optional}${confirmFor}>
                    <button type="button" class="admin-swal-password-toggle" data-password-toggle="${field.id}" aria-label="Show password">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                ${passwordHint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'svg-icon') {
        const choicesJson = escapeHtml(JSON.stringify(field.choices ?? []));
        const noneLabel = field.noneLabel != null ? field.noneLabel : 'None (use default art)';

        return `
            <div class="${shellClass}">
                ${label}
                <div class="admin-svg-icon-picker" data-svg-icon-picker data-iconset="outline" data-resettable="1" data-icon-choices="${choicesJson}" data-none-label="${escapeHtml(noneLabel)}">
                    <input type="hidden" id="${field.id}" name="${field.id}" value="${escapeHtml(field.value ?? '')}">
                </div>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    if (field.type === 'color') {
        const hex = normalizeHexColor(field.value);
        const pickerValue = (hex || normalizeHexColor(field.placeholder) || DEFAULT_PICKER_COLOR).toLowerCase();
        const swatchVar = hex ? ` style="--admin-swatch: ${escapeHtml(hex)}"` : '';
        const presets = (Array.isArray(field.presets) ? field.presets : DEFAULT_COLOR_PRESETS)
            .map((preset) => {
                const presetHex = normalizeHexColor(preset.value);
                if (!presetHex) {
                    return '';
                }

                const presetLabel = escapeHtml(preset.label || presetHex);
                const swatchClass = escapeHtml(preset.className || '');

                return `<button type="button" class="admin-swal-color-preset ${swatchClass}" data-color-preset="${presetHex}" title="${presetLabel}" aria-label="${presetLabel}"></button>`;
            })
            .join('');

        return `
            <div class="${shellClass}">
                ${label}
                <div class="admin-swal-color" data-color-field>
                    <div class="admin-swal-color-row">
                        <label class="admin-swal-color-swatch${hex ? '' : ' is-empty'}" title="Pick a color">
                            <span class="admin-swal-color-face" data-color-face${swatchVar} aria-hidden="true"></span>
                            <input id="${field.id}-picker" class="admin-swal-color-native" type="color" value="${escapeHtml(pickerValue)}" data-color-picker aria-label="Pick ${escapeHtml(field.label)}">
                        </label>
                        <input id="${field.id}" class="admin-swal-input admin-swal-color-hex" type="text" value="${escapeHtml(hex)}"${placeholder} maxlength="7" spellcheck="false" autocomplete="off" autocapitalize="characters" inputmode="text" data-color-hex aria-label="${escapeHtml(field.label)} hex">
                        <button type="button" class="admin-swal-color-clear" data-color-clear${hex ? '' : ' hidden'}>Clear</button>
                    </div>
                    ${presets ? `<div class="admin-swal-color-presets" role="group" aria-label="Brand colors">${presets}</div>` : ''}
                </div>
                ${hint}
                ${changedHint}
                ${fieldErrorMarkup(field.id)}
            </div>
        `;
    }

    return `
        <div class="${shellClass}">
            ${label}
            <input id="${field.id}" class="admin-swal-input" type="${field.type ?? 'text'}"${placeholder}${value}${min}>
            ${hint}
            ${changedHint}
            ${fieldErrorMarkup(field.id)}
        </div>
    `;
}

export function buildSwalForm({ description = '', rules = '', fields = [], reviewItem = null } = {}) {
    const preparedFields = hasPendingRevisionPayload(reviewItem)
        ? annotateSwalFieldsForPendingReview(fields, reviewItem)
        : fields;
    const reviewBanner = hasPendingRevisionPayload(reviewItem)
        ? renderPendingChangesBanner(reviewItem, preparedFields)
        : '';

    return `
        <div class="admin-swal-form">
            ${reviewBanner}
            ${description ? `<p class="admin-swal-description" data-swal-subtitle="${escapeHtml(description)}" hidden>${escapeHtml(description)}</p>` : ''}
            ${rules ? `
                <div class="admin-swal-rules">
                    <p class="admin-swal-rules-title">Rules</p>
                    <p class="admin-swal-rules-body">${escapeHtml(rules)}</p>
                </div>
            ` : ''}
            <div class="admin-swal-fields">
                ${preparedFields.map(renderField).join('')}
            </div>
        </div>
    `;
}

/**
 * Sync native color pickers with hex text fields.
 */
export function bindColorPickers(root = document) {
    root.querySelectorAll('[data-color-field]').forEach((field) => {
        if (field.dataset.colorBound === '1') {
            return;
        }

        field.dataset.colorBound = '1';

        const picker = field.querySelector('[data-color-picker]');
        const hexInput = field.querySelector('[data-color-hex]');
        const face = field.querySelector('[data-color-face]');
        const swatch = field.querySelector('.admin-swal-color-swatch');
        const clearBtn = field.querySelector('[data-color-clear]');
        const fallback = normalizeHexColor(hexInput?.getAttribute('placeholder')) || DEFAULT_PICKER_COLOR;

        const apply = (raw, { syncHex = true } = {}) => {
            const hex = normalizeHexColor(raw);

            if (hex) {
                if (picker) {
                    picker.value = hex.toLowerCase();
                }
                if (syncHex && hexInput) {
                    hexInput.value = hex;
                }
                face?.style.setProperty('--admin-swatch', hex);
                swatch?.classList.remove('is-empty');
                if (clearBtn) {
                    clearBtn.hidden = false;
                }
                return;
            }

            if (picker) {
                picker.value = fallback.toLowerCase();
            }
            face?.style.removeProperty('--admin-swatch');
            swatch?.classList.add('is-empty');
            if (clearBtn) {
                clearBtn.hidden = true;
            }
        };

        apply(hexInput?.value, { syncHex: Boolean(normalizeHexColor(hexInput?.value)) });

        picker?.addEventListener('input', () => apply(picker.value));
        hexInput?.addEventListener('input', () => {
            const typed = String(hexInput.value ?? '').trim();
            if (typed === '') {
                apply('');
                return;
            }
            const hex = normalizeHexColor(typed);
            if (hex) {
                apply(hex, { syncHex: false });
            }
        });
        hexInput?.addEventListener('blur', () => {
            const hex = normalizeHexColor(hexInput.value);
            if (hex) {
                hexInput.value = hex;
                apply(hex);
            }
        });
        clearBtn?.addEventListener('click', () => {
            if (hexInput) {
                hexInput.value = '';
            }
            apply('');
            hexInput?.focus();
        });
        field.querySelectorAll('[data-color-preset]').forEach((button) => {
            button.addEventListener('click', () => apply(button.getAttribute('data-color-preset')));
        });
    });
}

/**
 * Live-update image previews for file inputs rendered with showPreview / previewUrl.
 */
export function bindImageFilePreviews(root = document) {
    root.querySelectorAll('input[type="file"][data-image-preview-input="true"]').forEach((input) => {
        if (input.dataset.previewBound === '1') {
            return;
        }

        input.dataset.previewBound = '1';

        const preview = root.querySelector(`#${CSS.escape(input.id)}-preview`);
        const empty = root.querySelector(`#${CSS.escape(input.id)}-preview-empty`);

        if (!preview) {
            return;
        }

        let objectUrl = null;

        input.addEventListener('change', () => {
            const file = input.files?.[0] ?? null;

            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            const showPreviewImage = (url) => {
                preview.src = url;
                preview.hidden = false;
                if (empty) {
                    empty.hidden = true;
                    empty.setAttribute('aria-hidden', 'true');
                }
            };

            const showEmptyState = () => {
                preview.removeAttribute('src');
                preview.hidden = true;
                if (empty) {
                    empty.hidden = false;
                    empty.setAttribute('aria-hidden', 'false');
                }
            };

            if (!file || !file.type.startsWith('image/')) {
                const fallback = input.getAttribute('data-preview-fallback') || '';
                if (fallback) {
                    showPreviewImage(fallback);
                } else {
                    showEmptyState();
                }
                return;
            }

            objectUrl = URL.createObjectURL(file);
            showPreviewImage(objectUrl);
        });
    });
}

/**
 * Flatten Laravel / API validation payloads into a single message for Swal or toasts.
 * Keeps the modal open when used with Swal.showValidationMessage + return false.
 */
export function formatApiValidationMessage(response, fallback = 'Unable to save. Please check the form and try again.') {
    const errors = response?.data?.errors;

    if (errors && typeof errors === 'object') {
        const messages = Object.values(errors).flat().filter(Boolean);
        if (messages.length > 0) {
            return messages.join(' ');
        }
    }

    return response?.data?.message ?? fallback;
}

function fieldErrorMarkup(fieldId = '') {
    return `<p class="admin-swal-field-error" data-swal-field-error="${escapeHtml(fieldId)}" hidden></p>`;
}

export function clearSwalFieldErrors(root = Swal.getPopup()) {
    if (!root) {
        return;
    }

    root.querySelectorAll('.is-invalid').forEach((el) => {
        el.classList.remove('is-invalid');
        el.removeAttribute('aria-invalid');
    });

    root.querySelectorAll('.admin-swal-field.is-invalid-field').forEach((el) => {
        el.classList.remove('is-invalid-field');
    });

    root.querySelectorAll('[data-swal-field-error]').forEach((el) => {
        el.hidden = true;
        el.textContent = '';
    });
}

function resolveFieldControl(fieldId, root = Swal.getPopup()) {
    if (!root || !fieldId) {
        return null;
    }

    return root.querySelector(`#${CSS.escape(String(fieldId))}`);
}

function isControlVisiblyEmpty(control) {
    if (!control) {
        return true;
    }

    if (control.matches('[data-rich-text], .admin-swal-richtext')) {
        const html = String(control.value ?? '')
            .replace(/<p>(&nbsp;|\s|<br\s*\/?>)*<\/p>/gi, '')
            .replace(/<[^>]+>/g, '')
            .replace(/&nbsp;/g, ' ')
            .trim();
        return html === '';
    }

    if (control.type === 'checkbox' || control.type === 'radio') {
        return !control.checked;
    }

    if (control.type === 'file') {
        return !control.files?.length;
    }

    return String(control.value ?? '').trim() === '';
}

export function markSwalFieldInvalid(fieldId, message = '') {
    const popup = Swal.getPopup();
    const control = resolveFieldControl(fieldId, popup);
    if (!control) {
        return;
    }

    const field = control.closest('.admin-swal-field') || control.parentElement;
    control.classList.add('is-invalid');
    control.setAttribute('aria-invalid', 'true');
    field?.classList.add('is-invalid-field');

    const richWrap = field?.querySelector('.admin-rich-text, .ck-editor, .ck.ck-editor');
    richWrap?.classList.add('is-invalid');

    const select = field?.querySelector('select.admin-swal-select, select.admin-swal-input');
    select?.classList.add('is-invalid');

    let errorEl = field?.querySelector(`[data-swal-field-error="${CSS.escape(String(fieldId))}"]`)
        || field?.querySelector('[data-swal-field-error]');

    if (!errorEl && field) {
        errorEl = document.createElement('p');
        errorEl.className = 'admin-swal-field-error';
        errorEl.setAttribute('data-swal-field-error', String(fieldId));
        field.appendChild(errorEl);
    }

    if (errorEl) {
        const labelText = field?.querySelector('.admin-swal-label, .admin-swal-checkbox-title')?.textContent
            ?.replace(/\*/g, '')
            .trim() || 'This field';
        errorEl.textContent = message || `${labelText} is required.`;
        errorEl.hidden = false;
    }
}

function findControlIdForErrorKey(key, root = Swal.getPopup()) {
    if (!root || !key) {
        return null;
    }

    const normalized = String(key).replace(/\./g, '_');
    const candidates = [
        normalized,
        normalized.replace(/_id$/, ''),
        `post-${normalized}`,
        `post-${normalized.replace(/_id$/, '')}`,
        normalized.replaceAll('_', '-'),
    ];

    for (const id of candidates) {
        if (root.querySelector(`#${CSS.escape(id)}`)) {
            return id;
        }
    }

    const match = Array.from(root.querySelectorAll('input[id], textarea[id], select[id]'))
        .find((el) => {
            const id = el.id.toLowerCase();
            return id === normalized.toLowerCase()
                || id.endsWith(`-${normalized.toLowerCase()}`)
                || id.endsWith(`_${normalized.toLowerCase()}`)
                || id.includes(normalized.toLowerCase().replaceAll('_', '-'));
        });

    return match?.id ?? null;
}

function inferInvalidFieldIds(message, root = Swal.getPopup()) {
    if (!root) {
        return [];
    }

    const msg = String(message ?? '').toLowerCase();
    const ids = [];

    root.querySelectorAll('.admin-swal-field').forEach((field) => {
        const label = field.querySelector('.admin-swal-label, .admin-swal-checkbox-title')?.textContent ?? '';
        const control = field.querySelector('input[id], textarea[id], select[id]');
        if (!control?.id) {
            return;
        }

        const labelWords = label
            .replace(/\*/g, ' ')
            .toLowerCase()
            .split(/[^a-z0-9]+/)
            .filter((word) => word.length > 2 && !['the', 'and', 'for', 'with', 'from'].includes(word));

        const mentioned = labelWords.some((word) => msg.includes(word));
        const requiredEmpty = label.includes('*') && isControlVisiblyEmpty(control);

        if ((mentioned && isControlVisiblyEmpty(control)) || (mentioned && requiredEmpty) || (requiredEmpty && /required|must|need/i.test(msg))) {
            if (mentioned || (requiredEmpty && /required/i.test(msg))) {
                ids.push(control.id);
            }
        }
    });

    // Prefer fields mentioned in the message when several required fields are empty.
    const mentionedOnly = ids.filter((id) => {
        const field = resolveFieldControl(id, root)?.closest('.admin-swal-field');
        const label = field?.querySelector('.admin-swal-label, .admin-swal-checkbox-title')?.textContent ?? '';
        const words = label.replace(/\*/g, ' ').toLowerCase().split(/[^a-z0-9]+/).filter((w) => w.length > 2);
        return words.some((word) => msg.includes(word));
    });

    return [...new Set(mentionedOnly.length > 0 ? mentionedOnly : ids)];
}

/**
 * Show the Swal validation banner and mark the related fields with a red indicator.
 * @param {string} message
 * @param {string[]|Record<string,string>} [fields] Field ids, or id → message map
 * @param {object} [apiResponse] Optional Axios-style response with data.errors
 */
export function failSwalValidation(message, fields = [], apiResponse = null) {
    const popup = Swal.getPopup();
    clearSwalFieldErrors(popup);

    const fieldMessages = {};
    let fieldIds = [];

    if (Array.isArray(fields)) {
        fieldIds = fields.filter(Boolean);
    } else if (fields && typeof fields === 'object') {
        Object.entries(fields).forEach(([id, msg]) => {
            fieldIds.push(id);
            fieldMessages[id] = msg;
        });
    }

    const apiErrors = apiResponse?.data?.errors;
    if (apiErrors && typeof apiErrors === 'object') {
        Object.entries(apiErrors).forEach(([key, msgs]) => {
            const id = findControlIdForErrorKey(key, popup);
            if (!id) {
                return;
            }
            fieldIds.push(id);
            fieldMessages[id] = Array.isArray(msgs) ? msgs[0] : String(msgs);
        });
    }

    if (fieldIds.length === 0) {
        fieldIds = inferInvalidFieldIds(message, popup);
    }

    [...new Set(fieldIds)].forEach((id) => {
        markSwalFieldInvalid(id, fieldMessages[id] || '');
    });

    if (popup) {
        popup.dataset.swalSkippingFieldMark = '1';
    }

    Swal.showValidationMessage(message);

    if (popup) {
        delete popup.dataset.swalSkippingFieldMark;
    }

    const firstInvalid = popup?.querySelector('.is-invalid, .is-invalid-field');
    firstInvalid?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });

    return false;
}

function bindSwalFieldErrorClearing(root = Swal.getPopup()) {
    if (!root || root.dataset.swalFieldErrorsBound === '1') {
        return;
    }

    root.dataset.swalFieldErrorsBound = '1';

    const clearOne = (event) => {
        const control = event.target?.closest?.('input, textarea, select');
        if (!control) {
            return;
        }

        control.classList.remove('is-invalid');
        control.removeAttribute('aria-invalid');
        const field = control.closest('.admin-swal-field');
        field?.classList.remove('is-invalid-field');
        field?.querySelector('.admin-rich-text, .ck-editor')?.classList.remove('is-invalid');
        const errorEl = field?.querySelector(`[data-swal-field-error="${CSS.escape(control.id)}"]`)
            || field?.querySelector('[data-swal-field-error]');
        if (errorEl) {
            errorEl.hidden = true;
            errorEl.textContent = '';
        }
    };

    root.addEventListener('input', clearOne, true);
    root.addEventListener('change', clearOne, true);
}

function htmlLooksLikeForm(html) {
    return /admin-swal-form|admin-swal-fields|admin-swal-section|admin-swal-repeatable|data-rich-text|<input|<textarea|<select/i
        .test(String(html ?? ''));
}

/**
 * True for yes/cancel prompts (delete, disable, publish, etc.).
 * False for create/edit form shells.
 */
export function isConfirmDialog(options = {}, { danger = false } = {}) {
    if (options.confirmOnly === true) {
        return true;
    }

    if (options.confirmOnly === false) {
        return false;
    }

    if (htmlLooksLikeForm(options.html)) {
        return false;
    }

    const icon = options.icon;
    const isConfirmIcon = icon === 'warning'
        || icon === 'question'
        || icon === 'info'
        || icon === 'error';

    if (!isConfirmIcon && !danger) {
        return false;
    }

    return options.showCancelButton !== false;
}

/**
 * Shared SweetAlert2 confirmation helper.
 * Use for delete / toggle / irreversible prompts — never hand-roll confirmation modals.
 */
export function confirmWithSwal({
    title,
    text = '',
    html = null,
    icon = 'question',
    confirmButtonText = 'Confirm',
    cancelButtonText = 'Cancel',
    danger = false,
} = {}) {
    return Swal.fire(buildSwalOptions({
        confirmOnly: true,
        icon,
        title,
        text: html ? undefined : text,
        html: html || undefined,
        showCancelButton: true,
        confirmButtonText,
        cancelButtonText,
    }, { danger, size: 'sm' }));
}

function resolveSubtitle(popup, subtitle) {
    const embeddedSubtitle = popup
        .querySelector('[data-swal-subtitle]')
        ?.getAttribute('data-swal-subtitle');
    const formDescription = popup.querySelector('.admin-swal-description');

    return String(
        subtitle
        ?? embeddedSubtitle
        ?? formDescription?.textContent
        ?? '',
    ).trim();
}

/**
 * Build a guaranteed visible branded header bar for every admin modal.
 * SweetAlert's native header/title can be hard to style consistently across versions.
 */
function isEffectivelyEmptyHtmlContainer(htmlContainer) {
    if (!htmlContainer) {
        return true;
    }

    const clone = htmlContainer.cloneNode(true);
    clone.querySelectorAll('[hidden], .admin-swal-description').forEach((node) => node.remove());
    const text = (clone.textContent || '').replace(/\u00a0/g, ' ').trim();
    const hasVisibleWidgets = Boolean(
        clone.querySelector(
            'input, textarea, select, button, img, iframe, table, .admin-swal-form, .admin-swal-fields, .admin-swal-rules, .admin-swal-section',
        ),
    );

    return !hasVisibleWidgets && text === '';
}

function collapseEmptyConfirmBody(popup) {
    const htmlContainer = popup.querySelector('.swal2-html-container');
    const emptyBody = isEffectivelyEmptyHtmlContainer(htmlContainer);

    popup.classList.toggle('admin-swal-popup-confirm', true);
    htmlContainer?.classList.toggle('admin-swal-html-empty', emptyBody);

    if (emptyBody && htmlContainer) {
        htmlContainer.setAttribute('hidden', 'hidden');
        htmlContainer.style.display = 'none';
    }
}

function mountAdminModalHeader(popup, { title, subtitle, showClose }) {
    popup.querySelectorAll('.admin-modal-header-bar').forEach((node) => node.remove());

    const nativeTitle = popup.querySelector('.swal2-title');
    const titleText = String(title || nativeTitle?.textContent || '').trim();
    const subtitleText = String(subtitle || '').trim();

    const bar = document.createElement('div');
    bar.className = 'admin-modal-header-bar';
    bar.innerHTML = `
        <div class="admin-modal-header-bar-copy">
            <h3 class="admin-modal-header-bar-title"></h3>
            ${subtitleText ? '<p class="admin-modal-header-bar-subtitle"></p>' : ''}
        </div>
        ${showClose ? '<button type="button" class="admin-modal-header-bar-close" aria-label="Close">&times;</button>' : ''}
    `;

    bar.querySelector('.admin-modal-header-bar-title').textContent = titleText || 'Dialog';
    if (subtitleText) {
        bar.querySelector('.admin-modal-header-bar-subtitle').textContent = subtitleText;
    }

    const closeBtn = bar.querySelector('.admin-modal-header-bar-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            Swal.close();
        });
    }

    // Keep warning/success icons above the branded banner.
    const icon = popup.querySelector('.swal2-icon');
    if (icon) {
        popup.insertBefore(bar, icon.nextSibling);
    } else {
        popup.insertBefore(bar, popup.firstChild);
    }

    if (nativeTitle) {
        nativeTitle.setAttribute('hidden', 'hidden');
        nativeTitle.style.display = 'none';
    }

    const nativeHeader = popup.querySelector('.swal2-header');
    if (nativeHeader) {
        // Keep the icon visible; only hide the native title wrapper chrome.
        nativeHeader.classList.add('admin-swal-header-native-hidden');
        const headerIcon = nativeHeader.querySelector('.swal2-icon');
        if (headerIcon && headerIcon.parentElement === nativeHeader) {
            popup.insertBefore(headerIcon, bar);
        }
    }

    const formDescription = popup.querySelector('.admin-swal-description');
    if (formDescription && subtitleText) {
        formDescription.hidden = true;
    }
}

/**
 * Classic SweetAlert2 confirm layout (icon + title + text + actions).
 * No form-style blue header bar.
 */
function mountConfirmDialog(popup) {
    popup.querySelectorAll('.admin-modal-header-bar').forEach((node) => node.remove());
    popup.classList.add('admin-swal-popup-confirm');

    const nativeTitle = popup.querySelector('.swal2-title');
    if (nativeTitle) {
        nativeTitle.removeAttribute('hidden');
        nativeTitle.style.display = '';
    }

    const nativeHeader = popup.querySelector('.swal2-header');
    if (nativeHeader) {
        nativeHeader.classList.remove('admin-swal-header-native-hidden');
    }

    collapseEmptyConfirmBody(popup);
}

/**
 * Standard admin modal shell:
 * - modern brand header (title + optional subtitle + close) for forms
 * - classic SweetAlert2 confirm layout for yes/cancel prompts
 * - scrollable body + footer actions
 */
export function buildSwalOptions(options = {}, { danger = false, size = 'lg' } = {}) {
    const userDidOpen = options.didOpen;
    const userWillClose = options.willClose;
    const userPreConfirm = options.preConfirm;
    const confirmDialog = isConfirmDialog(options, { danger });
    const popupSizeClass = {
        sm: 'admin-swal-popup admin-swal-popup-sm',
        md: 'admin-swal-popup admin-swal-popup-md',
        lg: 'admin-swal-popup',
    }[confirmDialog ? 'sm' : size] ?? 'admin-swal-popup';

    const showCancel = options.showCancelButton !== false;
    const showClose = confirmDialog ? false : options.showCloseButton !== false;
    const title = options.title ?? '';

    // Prefer explicit subtitle; otherwise lift plain description HTML into the banner (forms only).
    let subtitle = options.subtitle ?? null;
    let html = options.html;
    let text = options.text;

    if (!confirmDialog && subtitle == null && typeof html === 'string') {
        const descriptionMatch = html.match(
            /<p[^>]*class=["'][^"']*admin-swal-description[^"']*["'][^>]*>[\s\S]*?<\/p>/i,
        );
        if (descriptionMatch) {
            const innerMatch = descriptionMatch[0].match(/>([\s\S]*?)<\/p>/i);
            subtitle = (innerMatch?.[1] ?? '')
                .replaceAll(/<[^>]+>/g, '')
                .replaceAll('&nbsp;', ' ')
                .trim();
            // Lift copy into the header, but keep fields (e.g. Reject Post notes).
            // Never replace the whole form with a blank body when danger/icon is set.
            html = html.replace(descriptionMatch[0], '');
            if (!htmlLooksLikeForm(html) && (danger || options.icon)) {
                html = ' ';
            }
        }
    }

    // Confirm dialogs: prefer plain text body; lift description HTML into `text` when needed.
    if (confirmDialog && !text && typeof html === 'string') {
        const descriptionMatch = html.match(
            /class=["'][^"']*admin-swal-description[^"']*["'][^>]*>([\s\S]*?)<\/p>/i,
        );
        if (descriptionMatch) {
            text = descriptionMatch[1]
                .replaceAll(/<[^>]+>/g, '')
                .replaceAll('&nbsp;', ' ')
                .trim();
            html = undefined;
        }
    }

    // Keep title for Swal accessibility, but forms render our own visible header bar.
    const { subtitle: _ignoredSubtitle, confirmOnly: _ignoredConfirmOnly, preConfirm: _ignoredPreConfirm, ...restOptions } = options;

    return {
        ...restOptions,
        title: title || restOptions.title || ' ',
        text: confirmDialog ? (text ?? restOptions.text) : restOptions.text,
        html: confirmDialog && text && !html ? undefined : html,
        showCancelButton: showCancel,
        showCloseButton: false,
        cancelButtonText: options.cancelButtonText ?? 'Cancel',
        confirmButtonText: options.confirmButtonText ?? (confirmDialog ? 'Confirm' : 'Save'),
        reverseButtons: options.reverseButtons ?? true,
        focusConfirm: options.focusConfirm ?? false,
        buttonsStyling: false,
        preConfirm: async (value) => {
            if (!confirmDialog) {
                const popup = Swal.getPopup();
                if (popup) {
                    clearSwalFieldErrors(popup);
                    const passwordError = assertPasswordFields(popup);
                    if (passwordError) {
                        const passwordIds = Array.from(popup.querySelectorAll('[data-password-policy], [data-password-confirm]'))
                            .map((el) => el.id)
                            .filter(Boolean);
                        return failSwalValidation(passwordError, passwordIds);
                    }
                }
            }

            if (typeof userPreConfirm === 'function') {
                return userPreConfirm(value);
            }

            return value;
        },
        didOpen: async (popup) => {
            bindPasswordToggles(popup);
            bindImageFilePreviews(popup);
            bindColorPickers(popup);
            bindSvgIconPickers(popup);
            bindAddressFields(popup);
            bindPhilippinePhoneFields(popup);
            bindPasswordPolicyFields(popup);
            await bindRichTextEditors(popup);
            bindSwalFieldErrorClearing(popup);

            if (confirmDialog) {
                mountConfirmDialog(popup);
            } else {
                const resolvedSubtitle = resolveSubtitle(popup, subtitle);
                mountAdminModalHeader(popup, {
                    title: title || popup.querySelector('.swal2-title')?.textContent || '',
                    subtitle: resolvedSubtitle,
                    showClose,
                });
            }

            if (typeof userDidOpen === 'function') {
                await userDidOpen(popup);
            }
        },
        willClose: (popup) => {
            destroyAddressAutocompletes(popup);
            destroyPhilippinePhoneFields(popup);
            destroyPasswordPolicyFields(popup);
            destroyRichTextEditors(popup);
            if (typeof userWillClose === 'function') {
                userWillClose(popup);
            }
        },
        customClass: {
            popup: popupSizeClass,
            header: 'admin-swal-header',
            title: 'admin-swal-title',
            htmlContainer: 'admin-swal-html',
            actions: 'admin-swal-actions',
            footer: 'admin-swal-footer',
            confirmButton: danger
                ? 'admin-primary-button admin-swal-button admin-swal-button-danger'
                : 'admin-primary-button admin-swal-button',
            cancelButton: 'admin-secondary-button admin-swal-button',
            denyButton: 'admin-secondary-button admin-swal-button',
            validationMessage: 'admin-swal-validation',
            ...options.customClass,
            popup: [
                popupSizeClass,
                'admin-swal-popup-shell',
                confirmDialog ? 'admin-swal-popup-confirm' : '',
                options.customClass?.popup,
            ].filter(Boolean).join(' '),
            actions: [
                'admin-swal-actions',
                confirmDialog ? 'admin-swal-actions-confirm' : '',
                options.customClass?.actions,
            ].filter(Boolean).join(' '),
            header: [
                'admin-swal-header',
                confirmDialog ? 'admin-swal-header-confirm' : '',
                options.customClass?.header,
            ].filter(Boolean).join(' '),
            title: [
                'admin-swal-title',
                confirmDialog ? 'admin-swal-title-confirm' : '',
                options.customClass?.title,
            ].filter(Boolean).join(' '),
            htmlContainer: [
                'admin-swal-html',
                confirmDialog ? 'admin-swal-html-confirm' : '',
                options.customClass?.htmlContainer,
            ].filter(Boolean).join(' '),
            icon: [
                'admin-swal-icon',
                options.customClass?.icon,
            ].filter(Boolean).join(' '),
        },
    };
}

let adminSwalPatched = false;

/**
 * Ensure every admin Swal.fire modal gets the branded header/footer shell,
 * including dialogs that do not call buildSwalOptions directly.
 *
 * Toast mixins and explicit toast configs must never get the modal chrome.
 */
export function enableAdminSwalHeaders() {
    if (adminSwalPatched || typeof window === 'undefined') {
        return;
    }

    const originalFire = Swal.fire;
    const originalShowValidationMessage = Swal.showValidationMessage.bind(Swal);

    // Every Swal.showValidationMessage call also paints matching fields red.
    Swal.showValidationMessage = (html) => {
        const popup = Swal.getPopup();
        if (popup && !popup.dataset.swalSkippingFieldMark) {
            clearSwalFieldErrors(popup);
            const text = String(html ?? '').replace(/<[^>]+>/g, ' ');
            inferInvalidFieldIds(text, popup).forEach((id) => markSwalFieldInvalid(id));
            const firstInvalid = popup.querySelector('.is-invalid, .is-invalid-field');
            firstInvalid?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
        return originalShowValidationMessage(html);
    };

    Swal.fire = function patchedAdminSwalFire(options, ...rest) {
        // Preserve Swal.mixin subclasses (Toast, etc.) so mixin defaults like toast:true apply.
        if (this !== Swal) {
            return originalFire.call(this, options, ...rest);
        }

        if (options == null || typeof options !== 'object' || Array.isArray(options)) {
            return originalFire.call(Swal, options, ...rest);
        }

        const popupClass = String(options.customClass?.popup ?? '');
        if (
            options.toast
            || popupClass.includes('admin-swal-popup')
            || popupClass.includes('admin-toast')
            || popupClass.includes('post-view-popup')
        ) {
            return originalFire.call(Swal, options, ...rest);
        }

        return originalFire.call(Swal, buildSwalOptions(options), ...rest);
    };

    adminSwalPatched = true;
}
