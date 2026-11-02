import axios from 'axios';
import Swal from 'sweetalert2';
import { showErrorToast, showSuccessToast } from './shared/toast';
import { bindPasswordToggles } from './shared/password-toggle';
import { assertPasswordFields, bindPasswordPolicyFields } from './password-validation';

const TOKEN_KEY = 'dape_ma_admin_token';
const USER_KEY = 'dape_ma_admin_user';
const VERIFIED_KEY = 'dape_ma_admin_verified';
const GATE_HOP_KEY = 'dape_admin_gate_hop';
const OTP_RESEND_SECONDS = 45;

export function hydrateApiAuth() {
    const token = sessionStorage.getItem(TOKEN_KEY);

    if (token) {
        axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    }
}

export function isAuthenticated() {
    return Boolean(sessionStorage.getItem(TOKEN_KEY))
        && sessionStorage.getItem(VERIFIED_KEY) === '1';
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

export function persistAuth(payload, { pending = false } = {}) {
    sessionStorage.setItem(TOKEN_KEY, payload.token);
    sessionStorage.setItem(USER_KEY, JSON.stringify(payload.user ?? {}));
    sessionStorage.setItem(VERIFIED_KEY, pending ? '0' : '1');
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
    sessionStorage.removeItem(VERIFIED_KEY);
    delete axios.defaults.headers.common.Authorization;
}

export async function redirectIfAuthenticated() {
    if (!isAuthenticated()) {
        sessionStorage.removeItem(GATE_HOP_KEY);
        return;
    }

    if (sessionStorage.getItem(GATE_HOP_KEY) === '1') {
        sessionStorage.removeItem(GATE_HOP_KEY);
        return;
    }

    sessionStorage.setItem(GATE_HOP_KEY, '1');

    try {
        await axios.get('/auth/me');
        window.location.replace('/admin/dashboard');
    } catch {
        sessionStorage.removeItem(GATE_HOP_KEY);
        clearAuth();
    }
}

export function requireAuthentication() {
    sessionStorage.removeItem(GATE_HOP_KEY);

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
    bindLoginOtpFlow();

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
            await continueAfterLogin(payload);
        } catch (error) {
            showLoginFailure(form, error);
            setBusy(submitButton, false, 'Sign In');
        }
    });
}

async function submitLogin({ email, password }) {
    const response = await axios.post('/auth/login', { email, password });
    const pending = Boolean(response.data?.data?.requires_verification);
    persistAuth(response.data.data, { pending });
    return response.data;
}

async function continueAfterLogin(payload) {
    if (payload?.data?.requires_verification) {
        showLoginOtp(payload?.data?.user?.email ?? '');
        const submitButton = document.querySelector('#admin-login-form [type="submit"]');
        setBusy(submitButton, false, 'Sign In');
        return;
    }

    window.location.replace(payload?.data?.user?.must_change_password
        ? '/admin/profile?force_password=1'
        : '/admin/dashboard');
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
                await continueAfterLogin(payload);
            } catch (error) {
                showLoginFailure(form, error);
            } finally {
                setBusy(submitButton, false, 'Sign In');
                button.disabled = false;
            }
        });
    });
}

function bindLoginOtpFlow() {
    const loginView = document.getElementById('admin-login-view');
    const otpView = document.getElementById('admin-login-otp-view');
    const form = document.getElementById('login-otp-form');

    if (!loginView || !otpView || !form) {
        return;
    }

    const digits = [...otpView.querySelectorAll('[data-login-otp-digit]')];
    const backButton = otpView.querySelector('[data-login-otp-back]');
    const resendWait = otpView.querySelector('[data-login-otp-resend-wait]');
    const resendButton = otpView.querySelector('[data-login-otp-resend]');
    const sentTo = otpView.querySelector('[data-login-otp-sent-to]');
    let secondsLeft = 0;
    let timerId = null;
    let verifying = false;

    window.showLoginOtpView = (email) => {
        loginView.classList.add('hidden');
        document.getElementById('admin-forgot-view')?.classList.add('hidden');
        otpView.classList.remove('hidden');
        if (sentTo) {
            sentTo.textContent = email ? `We sent a code to ${email}.` : 'We sent a code to your email.';
        }
        digits.forEach((input) => {
            input.value = '';
        });
        clearOtpError();
        startTimer();
        digits[0]?.focus();
        syncVerifyButton();
    };

    backButton?.addEventListener('click', async () => {
        stopTimer();
        try {
            await axios.post('/auth/logout');
        } catch {
            // Ignore stale pending tokens.
        }
        clearAuth();
        otpView.classList.add('hidden');
        loginView.classList.remove('hidden');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await verifyOtp();
    });

    digits.forEach((input, index) => {
        input.addEventListener('input', (event) => {
            const value = event.target.value.replace(/\D/g, '').slice(-1);
            event.target.value = value;
            if (value && digits[index + 1]) {
                digits[index + 1].focus();
            }
            syncVerifyButton();
            if (currentOtp().length === digits.length) {
                verifyOtp();
            }
        });
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && !event.target.value && digits[index - 1]) {
                digits[index - 1].focus();
            }
        });
        input.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = String(event.clipboardData?.getData('text') ?? '').replace(/\D/g, '').slice(0, digits.length);
            pasted.split('').forEach((char, offset) => {
                if (digits[offset]) {
                    digits[offset].value = char;
                }
            });
            digits[Math.min(pasted.length, digits.length) - 1]?.focus();
            syncVerifyButton();
            if (pasted.length === digits.length) {
                verifyOtp();
            }
        });
    });

    resendButton?.addEventListener('click', async () => {
        try {
            await axios.post('/auth/login-code');
            showSuccessToast('A new code is on its way.', 'Check your email');
            startTimer();
        } catch (error) {
            showErrorToast(error.response?.data?.message ?? 'Unable to resend the code.', 'Verification');
        }
    });

    function currentOtp() {
        return digits.map((input) => input.value).join('');
    }

    function syncVerifyButton() {
        const button = form.querySelector('[type="submit"]');
        if (!button || button.getAttribute('aria-busy') === 'true') {
            return;
        }
        button.disabled = currentOtp().length !== digits.length;
    }

    function clearOtpError() {
        const errorLabel = document.getElementById('login-otp-error');
        if (!errorLabel) {
            return;
        }
        errorLabel.textContent = '';
        errorLabel.classList.remove('is-visible');
    }

    async function verifyOtp() {
        if (verifying || currentOtp().length !== digits.length) {
            return;
        }
        verifying = true;
        clearOtpError();
        const button = form.querySelector('[type="submit"]');
        setBusy(button, true, 'Verifying...');
        try {
            const { data } = await axios.post('/auth/verify-login-code', {
                code: currentOtp(),
            });
            persistAuth(data.data);
            window.location.href = data?.data?.user?.must_change_password
                ? '/admin/profile?force_password=1'
                : '/admin';
        } catch (error) {
            const message = error.response?.data?.message ?? 'That code does not match. Check your email and try again.';
            const errorLabel = document.getElementById('login-otp-error');
            if (errorLabel) {
                errorLabel.textContent = message;
                errorLabel.classList.add('is-visible');
            }
            setBusy(button, false, 'Verify Code');
            syncVerifyButton();
        } finally {
            verifying = false;
        }
    }

    function startTimer() {
        stopTimer();
        secondsLeft = OTP_RESEND_SECONDS;
        renderTimer();
        timerId = window.setInterval(() => {
            secondsLeft -= 1;
            if (secondsLeft <= 0) {
                secondsLeft = 0;
                stopTimer();
            }
            renderTimer();
        }, 1000);
    }

    function stopTimer() {
        if (timerId) {
            window.clearInterval(timerId);
            timerId = null;
        }
    }

    function renderTimer() {
        const waiting = secondsLeft > 0;
        if (resendWait) {
            const minutes = String(Math.floor(secondsLeft / 60)).padStart(2, '0');
            const seconds = String(secondsLeft % 60).padStart(2, '0');
            resendWait.textContent = `Resend in ${minutes}:${seconds}`;
            resendWait.classList.toggle('hidden', !waiting);
        }
        if (resendButton) {
            resendButton.classList.toggle('hidden', waiting);
            resendButton.disabled = waiting;
        }
    }
}

function showLoginOtp(email) {
    if (typeof window.showLoginOtpView === 'function') {
        window.showLoginOtpView(email);
    }
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
            showErrorToast(
                'Please accept the Terms of Use, Privacy Policy, and Privacy Notice before continuing.',
                'Agreements required',
            );
            return;
        }

        const passwordError = assertPasswordFields(form);
        if (passwordError) {
            return;
        }

        setBusy(submitButton, true, 'Creating account...');

        try {
            const response = await axios.post('/auth/bootstrap-admin', {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
                accepted_terms_of_use: acceptedTerms,
                accepted_privacy_policy: acceptedPrivacyPolicy,
                accepted_privacy_notice: acceptedPrivacyNotice,
            });

            persistAuth(response.data.data);
            showSuccessToast(response.data.message ?? 'Administrator account created.', 'Admin ready');
            window.location.replace('/admin/dashboard');
        } catch (error) {
            applyFieldErrors(form, error.response?.data?.errors ?? {});
            showErrorToast(
                buildErrorPrompt(error.response?.data?.errors, error.response?.data?.message, 'Unable to create the administrator account.'),
                'Registration failed',
            );
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
        showErrorToast(error.response?.data?.message ?? 'Please try again.', 'Unable to load document');
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
