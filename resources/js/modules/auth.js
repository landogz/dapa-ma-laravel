import axios from 'axios';
import Swal from 'sweetalert2';

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

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('[type="submit"]');
        const formData = new FormData(form);
        clearFieldErrors(form);

        setBusy(submitButton, true, 'Signing in...');

        try {
            const response = await axios.post('/auth/login', {
                email: formData.get('email'),
                password: formData.get('password'),
            });

            persistAuth(response.data.data);

            await Swal.fire({
                icon: 'success',
                title: 'Welcome back',
                text: response.data.message,
                confirmButtonColor: '#055498',
            });

            window.location.href = '/admin';
        } catch (error) {
            applyFieldErrors(form, error.response?.data?.errors ?? {});

            await Swal.fire({
                icon: 'error',
                title: 'Login failed',
                text: buildErrorPrompt(error.response?.data?.errors, error.response?.data?.message, 'Unable to sign in right now.'),
                confirmButtonColor: '#CE2028',
            });
        } finally {
            setBusy(submitButton, false, 'Sign In');
        }
    });
}

export function bindRegisterForm() {
    const form = document.getElementById('admin-register-form');

    if (!form) {
        return;
    }

    bindPasswordToggles(form);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('[type="submit"]');
        const formData = new FormData(form);
        clearFieldErrors(form);

        setBusy(submitButton, true, 'Creating account...');

        try {
            const response = await axios.post('/auth/register', {
                name: formData.get('name'),
                email: formData.get('email'),
                password: formData.get('password'),
                password_confirmation: formData.get('password_confirmation'),
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
    button.textContent = label;
    button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    button.classList.toggle('opacity-80', isBusy);
    button.classList.toggle('cursor-not-allowed', isBusy);
}

function bindPasswordToggles(scope) {
    scope.querySelectorAll('[data-password-toggle]').forEach((toggleButton) => {
        toggleButton.addEventListener('click', () => {
            const targetId = toggleButton.getAttribute('data-password-toggle');
            const input = document.getElementById(targetId);
            const icon = toggleButton.querySelector('i');

            if (!input || !icon) {
                return;
            }

            const shouldShow = input.type === 'password';

            input.type = shouldShow ? 'text' : 'password';
            icon.classList.toggle('fa-eye', shouldShow);
            icon.classList.toggle('fa-eye-slash', !shouldShow);
        });
    });
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
