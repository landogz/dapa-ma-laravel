const PHONE_LABEL = /^phone\b/i;
const DEFAULT_ERROR = 'Enter a valid Philippine phone number (09XXXXXXXXX or +63…).';
const MULTI_ERROR = 'Each value must be a valid Philippine phone number or hotline, separated by |.';

export function digitsOnly(value = '') {
    return String(value).replace(/\D+/g, '');
}

export function isPhilippineShortCode(value = '') {
    const trimmed = String(value).trim();
    if (/^#\d{3,6}$/.test(trimmed)) {
        return true;
    }

    return /^\d{3,4}$/.test(digitsOnly(trimmed));
}

export function isPhilippineMobile(value = '') {
    return /^(?:0?9\d{9}|639\d{9})$/.test(digitsOnly(value));
}

export function isPhilippineLandline(value = '') {
    return /^(?:632\d{8}|63[3-8]\d{7,8}|02\d{8}|0[3-8]\d{7,8}|\d{7,8})$/.test(digitsOnly(value));
}

export function isPhilippinePhone(value = '', { allowShortCodes = false } = {}) {
    const trimmed = String(value).trim();
    if (!trimmed) {
        return false;
    }

    if (allowShortCodes && isPhilippineShortCode(trimmed)) {
        return true;
    }

    return isPhilippineMobile(trimmed) || isPhilippineLandline(trimmed);
}

export function isPhilippinePhoneList(value = '', { allowShortCodes = false } = {}) {
    const parts = String(value).split(/\s*\|\s*/);
    if (!parts.length) {
        return false;
    }

    return parts.every((part) => part.trim() !== '' && isPhilippinePhone(part, { allowShortCodes }));
}

export function phoneOptionsFromInput(input) {
    return {
        allowMultiple: input?.hasAttribute('data-phone-multiple') ?? false,
        allowShortCodes: input?.hasAttribute('data-phone-shortcodes') ?? false,
    };
}

export function getPhoneValidationError(value, options = {}) {
    const trimmed = String(value ?? '').trim();
    if (!trimmed) {
        return null;
    }

    const { allowMultiple = false, allowShortCodes = false } = options;
    const valid = allowMultiple
        ? isPhilippinePhoneList(trimmed, { allowShortCodes })
        : isPhilippinePhone(trimmed, { allowShortCodes });

    return valid ? null : (allowMultiple ? MULTI_ERROR : DEFAULT_ERROR);
}

function allowedCharPattern({ allowMultiple, allowShortCodes }) {
    let extras = '';
    if (allowMultiple) {
        extras += '|';
    }
    if (allowShortCodes) {
        extras += '#';
    }

    return new RegExp(`[^0-9+()\\-\\.\\s${extras}]`, 'g');
}

function sanitizePhoneValue(value, options) {
    return String(value ?? '').replace(allowedCharPattern(options), '');
}

function ensureMessage(input) {
    const field = input.closest('.admin-swal-field') ?? input.parentElement;
    if (!field) {
        return null;
    }

    let message = field.querySelector('[data-phone-error]');
    if (message) {
        return message;
    }

    message = document.createElement('p');
    message.className = 'phone-validation-error';
    message.hidden = true;
    message.setAttribute('data-phone-error', '');
    message.setAttribute('role', 'alert');
    field.appendChild(message);
    return message;
}

function setPhoneError(input, error) {
    const message = ensureMessage(input);
    if (error) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (message) {
            message.hidden = false;
            message.textContent = error;
        }
        return;
    }

    input.classList.remove('is-invalid');
    input.removeAttribute('aria-invalid');
    if (message) {
        message.hidden = true;
        message.textContent = '';
    }
}

export function validatePhoneInput(input, { showError = true } = {}) {
    if (!input) {
        return true;
    }

    const error = getPhoneValidationError(input.value, phoneOptionsFromInput(input));
    if (showError) {
        setPhoneError(input, error);
    }

    return error == null;
}

function collectPhoneFields(root) {
    const fields = new Set();

    root.querySelectorAll('[data-phone-ph], input[type="tel"]').forEach((field) => {
        if (field.matches('input, textarea')) {
            fields.add(field);
        }
    });

    root.querySelectorAll('label').forEach((label) => {
        const text = (label.textContent ?? '').replace(/\s+/g, ' ').trim();
        if (!PHONE_LABEL.test(text)) {
            return;
        }

        const forId = label.getAttribute('for');
        const input = forId
            ? root.querySelector(`#${CSS.escape(forId)}`)
            : label.querySelector('input, textarea');

        if (input?.matches('input, textarea')) {
            fields.add(input);
        }
    });

    return [...fields];
}

export function bindPhilippinePhone(input) {
    if (!input || input.dataset.phoneBound === '1') {
        return input?._phoneValidation ?? null;
    }

    input.dataset.phoneBound = '1';
    if (input.tagName === 'INPUT') {
        input.setAttribute('type', 'tel');
        input.setAttribute('inputmode', 'tel');
        input.setAttribute('autocomplete', input.getAttribute('autocomplete') || 'tel');
    }
    input.setAttribute('data-phone-ph', '');

    const options = phoneOptionsFromInput(input);
    const controller = new AbortController();
    const { signal } = controller;

    const sanitize = () => {
        const next = sanitizePhoneValue(input.value, options);
        if (next !== input.value) {
            input.value = next;
        }
    };

    const refresh = () => {
        sanitize();
        validatePhoneInput(input);
    };

    input.addEventListener('input', refresh, { signal });
    input.addEventListener('blur', refresh, { signal });
    ensureMessage(input);

    const api = {
        destroy() {
            controller.abort();
            setPhoneError(input, null);
            delete input.dataset.phoneBound;
            delete input._phoneValidation;
        },
        isValid() {
            return validatePhoneInput(input, { showError: true });
        },
    };

    input._phoneValidation = api;
    return api;
}

export function bindPhilippinePhoneFields(root = document) {
    return collectPhoneFields(root).map((field) => bindPhilippinePhone(field));
}

export function destroyPhilippinePhoneFields(root = document) {
    root.querySelectorAll('[data-phone-bound="1"], [data-phone-ph]').forEach((field) => {
        field._phoneValidation?.destroy();
    });
}

export function assertPhoneFields(root = document) {
    const fields = collectPhoneFields(root);
    for (const field of fields) {
        if (!validatePhoneInput(field, { showError: true })) {
            return getPhoneValidationError(field.value, phoneOptionsFromInput(field));
        }
    }

    return null;
}
