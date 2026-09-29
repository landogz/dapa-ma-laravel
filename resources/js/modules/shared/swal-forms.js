import Swal from 'sweetalert2';
import { bindPasswordToggles } from './password-toggle';

function escapeHtml(value = '') {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
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

function renderField(field) {
    const label = `<label class="admin-swal-label" for="${field.id}">${escapeHtml(field.label)}</label>`;
    const placeholder = field.placeholder ? ` placeholder="${escapeHtml(field.placeholder)}"` : '';
    const value = field.value ? ` value="${escapeHtml(field.value)}"` : '';
    const min = field.min ? ` min="${escapeHtml(field.min)}"` : '';
    const hint = field.hint ? `<p class="admin-swal-hint">${escapeHtml(field.hint)}</p>` : '';

    if (field.type === 'textarea') {
        return `
            <div class="admin-swal-field">
                ${label}
                <textarea id="${field.id}" class="admin-swal-textarea"${placeholder}>${escapeHtml(field.value ?? '')}</textarea>
                ${hint}
            </div>
        `;
    }

    if (field.type === 'select') {
        return `
            <div class="admin-swal-field">
                ${label}
                <select id="${field.id}" class="admin-swal-select">
                    ${renderOptions(field.options, field.value)}
                </select>
                ${hint}
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
            <div class="admin-swal-field">
                ${label}
                ${previewBlock}
                <input id="${field.id}" class="admin-swal-input" type="file"${field.accept ? ` accept="${escapeHtml(field.accept)}"` : ''} data-image-preview-input="${showPreview ? 'true' : 'false'}"${previewUrl ? ` data-preview-fallback="${previewUrl}"` : ''}>
                ${hint}
            </div>
        `;
    }

    if (field.type === 'password') {
        return `
            <div class="admin-swal-field">
                ${label}
                <div class="admin-swal-password-wrap">
                    <input id="${field.id}" class="admin-swal-input admin-swal-input-password" type="password"${placeholder}${value}${min} autocomplete="new-password">
                    <button type="button" class="admin-swal-password-toggle" data-password-toggle="${field.id}" aria-label="Show password">
                        <i class="fa-solid fa-eye-slash"></i>
                    </button>
                </div>
                ${hint}
            </div>
        `;
    }

    return `
        <div class="admin-swal-field">
            ${label}
            <input id="${field.id}" class="admin-swal-input" type="${field.type ?? 'text'}"${placeholder}${value}${min}>
            ${hint}
        </div>
    `;
}

export function buildSwalForm({ description = '', rules = '', fields = [] }) {
    return `
        <div class="admin-swal-form">
            ${description ? `<p class="admin-swal-description" data-swal-subtitle="${escapeHtml(description)}" hidden>${escapeHtml(description)}</p>` : ''}
            ${rules ? `
                <div class="admin-swal-rules">
                    <p class="admin-swal-rules-title">Rules</p>
                    <p class="admin-swal-rules-body">${escapeHtml(rules)}</p>
                </div>
            ` : ''}
            <div class="admin-swal-fields">
                ${fields.map(renderField).join('')}
            </div>
        </div>
    `;
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

function htmlLooksLikeForm(html) {
    return /admin-swal-form|admin-swal-fields|admin-swal-section|admin-swal-repeatable|<input|<textarea|<select/i
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
            /class=["'][^"']*admin-swal-description[^"']*["'][^>]*>([\s\S]*?)<\/p>/i,
        );
        if (descriptionMatch) {
            subtitle = descriptionMatch[1]
                .replaceAll(/<[^>]+>/g, '')
                .replaceAll('&nbsp;', ' ')
                .trim();
            if (danger || options.icon) {
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
    const { subtitle: _ignoredSubtitle, confirmOnly: _ignoredConfirmOnly, ...restOptions } = options;

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
        didOpen: (popup) => {
            bindPasswordToggles(popup);
            bindImageFilePreviews(popup);

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
                userDidOpen(popup);
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
