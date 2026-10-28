import axios from 'axios';
import Swal from 'sweetalert2';
import { showSuccessToast } from './shared/toast';
import { bindPasswordToggles } from './shared/password-toggle';
import { assertPasswordFields, bindPasswordPolicyFields } from './password-validation';

const TOKEN_KEY = 'dape_ma_admin_token';
const USER_KEY = 'dape_ma_admin_user';

export function hydrateApiAuth() {
    const token = sessionStorage.getItem(TOKEN_KEY);

    if (token) {
        axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    }
}

export function isAuthenticated() {
    return Boolean(sessionStorage.getItem(TOKEN_KEY));
}

export function getStoredUser() {
    const rawUser = sessionStorage.getItem(USER_KEY);

    if (!rawUser) {
        return null;
    }

    try {
        return JSON.parse(rawUser);
    } catch {
        return null;
    }
}

export function persistAuth(payload) {
    sessionStorage.setItem(TOKEN_KEY, payload.token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(payload.user));
    axios.defaults.headers.common.Authorization = `Bearer ${payload.token}`;
}

export function mergeStoredUser(updates) {
    const current = getStoredUser() ?? {};
    const next = { ...current, ...updates };

    sessionStorage.setItem(USER_KEY, JSON.stringify(next));
    window.dispatchEvent(new CustomEvent('admin-profile-updated', { detail: next }));

    return next;
}

export function clearAuth() {
    sessionStorage.removeItem(TOKEN_KEY);
    sessionStorage.removeItem(USER_KEY);
    delete axios.defaults.headers.common.Authorization;
}

export function redirectIfAuthenticated() {
    if (isAuthenticated()) {
        window.location.href = '/admin';
    }
}

export function requireAuthentication() {
    if (!isAuthenticated()) {
        window.location.href = '/admin/login';
    }
}

export function bindLoginForm() {
    const form = document.getElementById('admin-login-form');

    if (!form) {
        return;
    }

    bindPasswordToggles(form);
    bindDemoLoginAccounts(form);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('[type="submit"]');
        const formData = new FormData(form);
        clearFieldErrors(form);

        setBusy(submitButton, true, 'Signing in...');

        try {
            const payload = await submitLogin({
                email: formData.get('email'),
                password: formData.get('password'),
            });

            window.location.href = payload?.data?.user?.must_change_password
                ? '/admin/profile?force_password=1'
                : '/admin';
        } catch (error) {
            showLoginFailure(form, error);
            setBusy(submitButton, false, 'Sign In');
        }
    });
}

async function submitLogin({ email, password }) {
    const response = await axios.post('/auth/login', { email, password });
    persistAuth(response.data.data);
    return response.data;
}

function bindDemoLoginAccounts(form) {
    document.querySelectorAll('[data-demo-login]').forEach((button) => {
        button.addEventListener('click', async () => {
            const email = button.getAttribute('data-demo-email') ?? '';
            const password = button.getAttribute('data-demo-password') ?? '';
            const label = button.getAttribute('data-demo-label') ?? 'Account';
            const emailInput = form.querySelector('[name="email"]');
            const passwordInput = form.querySelector('[name="password"]');
            const submitButton = form.querySelector('[type="submit"]');

            if (emailInput) {
                emailInput.value = email;
            }

            if (passwordInput) {
                passwordInput.value = password;
            }

            clearFieldErrors(form);
            setBusy(submitButton, true, 'Signing in...');
            button.disabled = true;

            try {
                const payload = await submitLogin({ email, password });
                showSuccessToast(`Signed in as ${label}`, 'Welcome');
                window.location.href = payload?.data?.user?.must_change_password
                    ? '/admin/profile?force_password=1'
                    : '/admin';
            } catch (error) {
                showLoginFailure(form, error);
            } finally {
                setBusy(submitButton, false, 'Sign In');
                button.disabled = false;
            }
        });
    });
}

export function bindRegisterForm() {
    const form = document.getElementById('admin-register-form');

    if (!form) {
        return;
    }

    bindPasswordToggles(form);
    bindPasswordPolicyFields(form);

    const submitButton = form.querySelector('[type="submit"]');
    const consentFields = [
        'accepted_terms_of_use',
        'accepted_privacy_policy',
        'accepted_privacy_notice',
    ];

    const syncConsent = () => {
        const allAccepted = consentFields.every((name) => form.querySelector(`[name="${name}"]`)?.checked);
        if (submitButton) {
            submitButton.disabled = !allAccepted;
        }
    };

    consentFields.forEach((name) => {
        form.querySelector(`[name="${name}"]`)?.addEventListener('change', syncConsent);
    });
    syncConsent();

    form.querySelectorAll('[data-legal-preview]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            showLegalPreview(button.getAttribute('data-legal-preview'));
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(form);
        clearFieldErrors(form);

        const acceptedTerms = Boolean(form.querySelector('[name="accepted_terms_of_use"]')?.checked);
        const acceptedPrivacyPolicy = Boolean(form.querySelector('[name="accepted_privacy_policy"]')?.checked);
        const acceptedPrivacyNotice = Boolean(form.querySelector('[name="accepted_privacy_notice"]')?.checked);

        if (!acceptedTerms || !acceptedPrivacyPolicy || !acceptedPrivacyNotice) {
            await Swal.fire({
                icon: 'warning',
                title: 'Agreements required',
                text: 'Please accept the Terms of Use, Privacy Policy, and Privacy Notice before continuing.',
                confirmButtonColor: '#055498',
            });
            return;
        }

        const passwordError = assertPasswordFields(form);
        if (passwordError) {
            return;
        }

        setBusy(submitButton, true, 'Creating account...');

        try {
            const response = await axios.post('/auth/register', {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
                accepted_terms_of_use: acceptedTerms,
                accepted_privacy_policy: acceptedPrivacyPolicy,
                accepted_privacy_notice: acceptedPrivacyNotice,
            });

            persistAuth(response.data.data);

            await Swal.fire({
                icon: 'success',
                title: 'Admin ready',
                text: response.data.message,
                confirmButtonColor: '#055498',
            });

            window.location.href = '/admin';
        } catch (error) {
            applyFieldErrors(form, error.response?.data?.errors ?? {});

            await Swal.fire({
                icon: 'error',
                title: 'Registration failed',
                text: buildErrorPrompt(error.response?.data?.errors, error.response?.data?.message, 'Unable to create the administrator account.'),
                confirmButtonColor: '#CE2028',
            });
        } finally {
            setBusy(submitButton, false, 'Create Administrator');
            syncConsent();
        }
    });
}

export async function logout() {
    try {
        await axios.post('/auth/logout');
    } catch {
        // Keep logout resilient even if the token is already invalid.
    } finally {
        clearAuth();
        window.location.href = '/admin/login';
    }
}

function setBusy(button, isBusy, label) {
    if (!button) {
        return;
    }

    button.disabled = isBusy;
    button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    button.classList.toggle('opacity-80', isBusy);
    button.classList.toggle('cursor-not-allowed', isBusy);
    button.classList.toggle('gap-2', isBusy);

    if (button.dataset.busySpinner === 'true') {
        button.replaceChildren();
        if (isBusy) {
            const spinner = document.createElement('span');
            spinner.className = 'inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white';
            spinner.setAttribute('aria-hidden', 'true');
            button.append(spinner);
        }
        button.append(document.createTextNode(label));
        return;
    }

    button.textContent = label;
}

function showLoginFailure(form, error) {
    const errors = error.response?.data?.errors ?? {};

    if (Object.keys(errors).length > 0) {
        applyFieldErrors(form, errors);
        return;
    }

    const message = error.response?.data?.message || 'Unable to sign in right now.';
    ['email', 'password'].forEach((field) => {
        form.querySelector(`[name="${field}"]`)?.classList.add('is-invalid');
    });

    const errorLabel = form.querySelector('#password-error');
    if (!errorLabel) {
        return;
    }

    errorLabel.textContent = message;
    errorLabel.classList.add('is-visible');
}

function clearFieldErrors(form) {
    form.querySelectorAll('.admin-auth-input').forEach((input) => {
        input.classList.remove('is-invalid');
    });

    form.querySelectorAll('.admin-auth-error').forEach((errorLabel) => {
        errorLabel.textContent = '';
        errorLabel.classList.remove('is-visible');
    });
}

function applyFieldErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        const errorLabel = form.querySelector(`#${field}-error`);

        if (input) {
            input.classList.add('is-invalid');
        }

        if (errorLabel && messages.length > 0) {
            errorLabel.textContent = messages[0];
            errorLabel.classList.add('is-visible');
        }
    });
}

function renderLegalMarkup(value) {
    const raw = String(value ?? '').trim();

    if (!raw) {
        return '';
    }

    if (/<[a-z][\s\S]*>/i.test(raw)) {
        return raw.replace(/<script[\s\S]*?>[\s\S]*?<\/script>/gi, '');
    }

    return `<p>${escapeHtml(raw).replaceAll('\n', '<br>')}</p>`;
}

async function showLegalPreview(slug) {
    if (!slug) {
        return;
    }

    try {
        const { data } = await axios.get(`/legal-pages/${slug}`, { params: { locale: 'en' } });
        const page = data.data ?? {};
        const intro = renderLegalMarkup(page.intro);
        const body = renderLegalMarkup(page.body);
        const html = [intro, body].filter(Boolean).join('');

        await Swal.fire({
            title: page.title || 'Legal document',
            html: `<div class="admin-legal-preview max-h-[60vh] overflow-y-auto text-left text-sm leading-6 text-slate-700">${html}</div>`,
            confirmButtonText: 'Close',
            confirmButtonColor: '#055498',
            width: '36rem',
        });
    } catch (error) {
        await Swal.fire({
            icon: 'error',
            title: 'Unable to load document',
            text: error.response?.data?.message ?? 'Please try again.',
            confirmButtonColor: '#CE2028',
        });
    }
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function buildErrorPrompt(errors, message, fallback) {
    if (errors && Object.keys(errors).length > 0) {
        const parts = Object.values(errors)
            .flat()
            .filter(Boolean);

        if (parts.length > 0) {
            return parts.join(' | ');
        }
    }

    return message ?? fallback;
}
