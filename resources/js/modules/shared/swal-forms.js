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
        return `
            <div class="admin-swal-field">
                ${label}
                <input id="${field.id}" class="admin-swal-input" type="file"${field.accept ? ` accept="${escapeHtml(field.accept)}"` : ''}>
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

    popup.insertBefore(bar, popup.firstChild);

    if (nativeTitle) {
        nativeTitle.setAttribute('hidden', 'hidden');
        nativeTitle.style.display = 'none';
    }

    const nativeHeader = popup.querySelector('.swal2-header');
    if (nativeHeader) {
        nativeHeader.classList.add('admin-swal-header-native-hidden');
    }

    const formDescription = popup.querySelector('.admin-swal-description');
    if (formDescription && subtitleText) {
        formDescription.hidden = true;
    }
}

/**
 * Standard admin modal shell:
 * - modern brand header (title + optional subtitle + close)
 * - scrollable body
 * - footer actions with Cancel + Confirm
 */
export function buildSwalOptions(options = {}, { danger = false, size = 'lg' } = {}) {
    const userDidOpen = options.didOpen;
    const popupSizeClass = {
        sm: 'admin-swal-popup admin-swal-popup-sm',
        md: 'admin-swal-popup admin-swal-popup-md',
        lg: 'admin-swal-popup',
    }[size] ?? 'admin-swal-popup';

    const showCancel = options.showCancelButton !== false;
    const showClose = options.showCloseButton !== false;
    const subtitle = options.subtitle ?? null;
    const title = options.title ?? '';

    // Keep title for Swal accessibility, but we render our own visible header bar.
    const { subtitle: _ignoredSubtitle, ...restOptions } = options;

    return {
        ...restOptions,
        title: title || restOptions.title || ' ',
        showCancelButton: showCancel,
        showCloseButton: false,
        cancelButtonText: options.cancelButtonText ?? 'Cancel',
        confirmButtonText: options.confirmButtonText ?? 'Save',
        reverseButtons: options.reverseButtons ?? true,
        focusConfirm: options.focusConfirm ?? false,
        buttonsStyling: false,
        didOpen: (popup) => {
            bindPasswordToggles(popup);

            const resolvedSubtitle = resolveSubtitle(popup, subtitle);
            mountAdminModalHeader(popup, {
                title: title || popup.querySelector('.swal2-title')?.textContent || '',
                subtitle: resolvedSubtitle,
                showClose,
            });

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
                options.customClass?.popup,
            ].filter(Boolean).join(' '),
            actions: [
                'admin-swal-actions',
                options.customClass?.actions,
            ].filter(Boolean).join(' '),
            header: [
                'admin-swal-header',
                options.customClass?.header,
            ].filter(Boolean).join(' '),
            title: [
                'admin-swal-title',
                options.customClass?.title,
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
