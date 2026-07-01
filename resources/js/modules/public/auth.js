import axios from 'axios';
import Swal from 'sweetalert2';
import { showErrorToast, showSuccessToast } from '../shared/toast';

const TOKEN_KEY = 'dape_ma_user_token';

export function hydratePublicAuth() {
    const token = localStorage.getItem(TOKEN_KEY);

    if (token) {
        axios.defaults.headers.common.Authorization = `Bearer ${token}`;
    }
}

export function isPublicAuthenticated() {
    return Boolean(localStorage.getItem(TOKEN_KEY));
}

export function persistPublicAuth(payload) {
    localStorage.setItem(TOKEN_KEY, payload.token);
    axios.defaults.headers.common.Authorization = `Bearer ${payload.token}`;
}

export function clearPublicAuth() {
    localStorage.removeItem(TOKEN_KEY);
    delete axios.defaults.headers.common.Authorization;
}

export async function ensurePublicAuth() {
    if (isPublicAuthenticated()) {
        return true;
    }

    const { value: formValues } = await Swal.fire({
        title: 'Sign in to rate',
        html: `
            <input id="public-login-email" type="email" class="swal2-input" placeholder="Email">
            <input id="public-login-password" type="password" class="swal2-input" placeholder="Password">
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Sign in',
        confirmButtonColor: '#055498',
        preConfirm: () => {
            const email = document.getElementById('public-login-email')?.value.trim();
            const password = document.getElementById('public-login-password')?.value ?? '';

            if (!email || !password) {
                Swal.showValidationMessage('Email and password are required.');
                return false;
            }

            return { email, password };
        },
    });

    if (!formValues) {
        return false;
    }

    try {
        const response = await axios.post('/auth/login', formValues);
        persistPublicAuth(response.data.data);
        showSuccessToast('You can now rate this post.', 'Signed in');
        return true;
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Unable to sign in.',
            'Login failed',
        );
        return false;
    }
}
