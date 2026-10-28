export const PASSWORD_POLICY_HINT =
    'Password must include at least 8 characters, upper and lower case, a number, a symbol.';

export const PASSWORD_POLICY = {
    minLength: 8,
    requireMixedCase: true,
    requireNumber: true,
    requireSymbol: true,
};

const CURRENT_LABEL = /^(current\s+password)\b/i;
const CONFIRM_LABEL = /^confirm(\s+new)?\s+password\b/i;
const SKIP_NAME = /^(current_password|db_password|db_root_password)$/i;
const CONFIRM_NAME = /(_confirmation|-confirmation)$/i;

export function passwordChecks(value = '') {
    const password = String(value ?? '');

    return {
        length: password.length >= PASSWORD_POLICY.minLength,
        mixed: /[a-z]/.test(password) && /[A-Z]/.test(password),
        number: /\d/.test(password),
        symbol: /[\p{P}\p{S}]/u.test(password),
    };
}

export function meetsPasswordPolicy(value = '') {
    const checks = passwordChecks(value);

    return checks.length && checks.mixed && checks.number && checks.symbol;
}

export function getPasswordPolicyError(value, { optional = false, required = false } = {}) {
    const password = String(value ?? '');

    if (password === '') {
        return optional || !required ? null : PASSWORD_POLICY_HINT;
    }

    return meetsPasswordPolicy(password) ? null : PASSWORD_POLICY_HINT;
}

export function getPasswordConfirmError(value, matchValue, { optional = false } = {}) {
    const confirmation = String(value ?? '');
    const original = String(matchValue ?? '');

    if (confirmation === '' && original === '' && optional) {
        return null;
    }

    if (confirmation === '') {
        return 'Confirm your password.';
    }

    if (confirmation !== original) {
        return 'Password confirmation does not match.';
    }

    return null;
}

function fieldRoot(input) {
    return input.closest('.admin-swal-field')
        ?? input.closest('.relative')?.parentElement
        ?? input.parentElement;
}

function labelTextFor(input, root = document) {
    const id = input.id;
    if (id) {
        const label = root.querySelector(`label[for="${CSS.escape(id)}"]`);
        if (label) {
            return (label.textContent ?? '').replace(/\s+/g, ' ').trim();
        }
    }

    const wrapping = input.closest('label');
    return wrapping ? (wrapping.textContent ?? '').replace(/\s+/g, ' ').trim() : '';
}

function isSkippedPasswordField(input, root = document) {
    if (input.hasAttribute('data-password-current') || input.hasAttribute('data-password-skip')) {
        return true;
    }

    const autocomplete = (input.getAttribute('autocomplete') ?? '').toLowerCase();
    if (autocomplete === 'current-password') {
        return true;
    }

    const name = input.getAttribute('name') ?? '';
    if (SKIP_NAME.test(name)) {
        return true;
    }

    return CURRENT_LABEL.test(labelTextFor(input, root));
}

function isConfirmPasswordField(input, root = document) {
    if (input.hasAttribute('data-password-confirm') || input.dataset.passwordConfirmFor) {
        return true;
    }

    const name = input.getAttribute('name') ?? '';
    const id = input.id ?? '';

    if (CONFIRM_NAME.test(name) || CONFIRM_NAME.test(id)) {
        return true;
    }

    return CONFIRM_LABEL.test(labelTextFor(input, root));
}

function findPolicyPeer(input, root) {
    const explicit = input.getAttribute('data-password-confirm-for');
    if (explicit) {
        return root.querySelector(`#${CSS.escape(explicit)}`)
            ?? document.getElementById(explicit);
    }

    const form = input.closest('form') ?? root;
    const candidates = [...form.querySelectorAll('input[type="password"]')]
        .filter((field) => field !== input && !isSkippedPasswordField(field, root) && !isConfirmPasswordField(field, root));

    return candidates[0] ?? null;
}

function isOptionalPasswordField(input) {
    return input.hasAttribute('data-password-optional');
}

function ensureMessage(input) {
    const field = fieldRoot(input);
    if (!field) {
        return null;
    }

    let message = field.querySelector('[data-password-error]');
    if (message) {
        return message;
    }

    message = document.createElement('p');
    message.className = 'password-policy-error';
    message.hidden = true;
    message.setAttribute('data-password-error', '');
    message.setAttribute('role', 'alert');
    field.appendChild(message);
    return message;
}

function ensureHint(input) {
    if (isConfirmPasswordField(input)) {
        return;
    }

    const field = fieldRoot(input);
    if (!field || field.querySelector('[data-password-hint]')) {
        return;
    }

    const hint = document.createElement('p');
    hint.className = 'admin-swal-hint password-policy-hint';
    hint.setAttribute('data-password-hint', '');
    hint.textContent = PASSWORD_POLICY_HINT;

    const checklist = field.querySelector('[data-password-checklist]');
    const error = field.querySelector('[data-password-error]');
    if (checklist) {
        checklist.insertAdjacentElement('beforebegin', hint);
        return;
    }
    if (error) {
        field.insertBefore(hint, error);
        return;
    }

    field.appendChild(hint);
}

function ensureChecklist(input) {
    if (isConfirmPasswordField(input)) {
        return null;
    }

    const field = fieldRoot(input);
    if (!field) {
        return null;
    }

    let list = field.querySelector('[data-password-checklist]');
    if (list) {
        return list;
    }

    list = document.createElement('ul');
    list.className = 'password-policy-checklist';
    list.setAttribute('data-password-checklist', '');
    list.innerHTML = `
        <li data-rule="length">At least 8 characters</li>
        <li data-rule="mixed">Upper and lower case</li>
        <li data-rule="number">A number</li>
        <li data-rule="symbol">A symbol</li>
    `;

    const error = field.querySelector('[data-password-error]');
    if (error) {
        field.insertBefore(list, error);
    } else {
        field.appendChild(list);
    }

    return list;
}

function setPasswordError(input, error) {
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

function refreshChecklist(input) {
    const list = ensureChecklist(input);
    if (!list) {
        return;
    }

    const checks = passwordChecks(input.value);
    list.querySelectorAll('[data-rule]').forEach((item) => {
        const rule = item.getAttribute('data-rule');
        const met = Boolean(checks[rule]);
        item.classList.toggle('is-met', met);
        item.classList.toggle('is-unmet', input.value !== '' && !met);
    });
}

export function validatePasswordInput(input, { showError = true, root = document } = {}) {
    if (!input) {
        return true;
    }

    const optional = isOptionalPasswordField(input);
    let error = null;

    if (isConfirmPasswordField(input, root)) {
        const peer = findPolicyPeer(input, root);
        error = getPasswordConfirmError(input.value, peer?.value ?? '', { optional });
    } else {
        error = getPasswordPolicyError(input.value, {
            optional,
            required: input.hasAttribute('required') && !optional,
        });
        refreshChecklist(input);
    }

    if (showError) {
        setPasswordError(input, error);
    }

    return error == null;
}

function collectPasswordFields(root) {
    const fields = new Set();

    root.querySelectorAll('input[type="password"]').forEach((field) => {
        if (!isSkippedPasswordField(field, root)) {
            fields.add(field);
        }
    });

    root.querySelectorAll('[data-password-policy], [data-password-confirm]').forEach((field) => {
        if (field.matches('input')) {
            fields.add(field);
        }
    });

    return [...fields];
}

export function bindPasswordPolicy(input, root = document) {
    if (!input || input.dataset.passwordPolicyBound === '1') {
        return input?._passwordPolicy ?? null;
    }

    input.dataset.passwordPolicyBound = '1';

    if (isConfirmPasswordField(input, root)) {
        input.setAttribute('data-password-confirm', '');
        if (!input.getAttribute('autocomplete')) {
            input.setAttribute('autocomplete', 'new-password');
        }
    } else {
        input.setAttribute('data-password-policy', '');
        if (!input.getAttribute('autocomplete') || input.getAttribute('autocomplete') === 'off') {
            input.setAttribute('autocomplete', 'new-password');
        }
        ensureHint(input);
        ensureChecklist(input);
    }

    ensureMessage(input);

    const controller = new AbortController();
    const { signal } = controller;

    const refresh = () => {
        validatePasswordInput(input, { showError: true, root });
        if (!isConfirmPasswordField(input, root)) {
            const form = input.closest('form') ?? root;
            form.querySelectorAll('input[type="password"]').forEach((field) => {
                if (field !== input && isConfirmPasswordField(field, root) && field.dataset.passwordPolicyBound === '1') {
                    validatePasswordInput(field, { showError: field.value !== '', root });
                }
            });
        }
    };

    input.addEventListener('input', refresh, { signal });
    input.addEventListener('blur', refresh, { signal });
    refreshChecklist(input);

    const api = {
        destroy() {
            controller.abort();
            setPasswordError(input, null);
            delete input.dataset.passwordPolicyBound;
            delete input._passwordPolicy;
        },
        isValid() {
            return validatePasswordInput(input, { showError: true, root });
        },
    };

    input._passwordPolicy = api;
    return api;
}

export function bindPasswordPolicyFields(root = document) {
    return collectPasswordFields(root).map((field) => bindPasswordPolicy(field, root));
}

export function destroyPasswordPolicyFields(root = document) {
    root.querySelectorAll('[data-password-policy-bound="1"], [data-password-policy], [data-password-confirm]').forEach((field) => {
        field._passwordPolicy?.destroy();
    });
}

export function assertPasswordFields(root = document) {
    const fields = collectPasswordFields(root);
    for (const field of fields) {
        if (!validatePasswordInput(field, { showError: true, root })) {
            if (isConfirmPasswordField(field, root)) {
                const peer = findPolicyPeer(field, root);
                return getPasswordConfirmError(field.value, peer?.value ?? '', {
                    optional: isOptionalPasswordField(field),
                });
            }

            return getPasswordPolicyError(field.value, {
                optional: isOptionalPasswordField(field),
                required: field.hasAttribute('required') && !isOptionalPasswordField(field),
            });
        }
    }

    return null;
}
