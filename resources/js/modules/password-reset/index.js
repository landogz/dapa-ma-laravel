import axios from 'axios';
import { showErrorToast, showSuccessToast } from '../shared/toast';
import { assertPasswordFields, bindPasswordPolicyFields } from '../password-validation';

export function initResetPasswordPage() {
    const form = document.getElementById('reset-password-form');
    if (!form) return;

    const emailInput = document.getElementById('reset-email');
    const passwordInput = document.getElementById('reset-password');
    const confirmInput = document.getElementById('reset-password-confirmation');
    const submitBtn = document.getElementById('reset-password-submit');
    const messageEl = document.getElementById('reset-password-message');

    const presetEmail = (document.body.dataset.email || '').trim();
    const token = document.body.dataset.token || '';

    if (emailInput) {
        emailInput.value = presetEmail;
        emailInput.readOnly = true;
        emailInput.disabled = true;
    }

    if (!token) {
        showMessage(messageEl, 'This reset link is missing a token. Request a new link from the app.', true);
        if (submitBtn) submitBtn.disabled = true;
        return;
    }

    bindPasswordPolicyFields(form);

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!emailInput || !passwordInput || !confirmInput || !submitBtn) return;

        const email = presetEmail || emailInput.value.trim();
        const password = passwordInput.value;
        const passwordConfirmation = confirmInput.value;

        if (!email || !password) {
            showMessage(messageEl, 'Email and password are required.', true);
            return;
        }

        const policyError = assertPasswordFields(form);
        if (policyError) {
            showMessage(messageEl, policyError, true);
            return;
        }

        if (password !== passwordConfirmation) {
            showMessage(messageEl, 'Passwords do not match.', true);
            return;
        }

        submitBtn.disabled = true;
        const original = submitBtn.textContent;
        submitBtn.textContent = 'Saving…';

        try {
            const { data } = await axios.post('/auth/reset-password', {
                email,
                token,
                password,
                password_confirmation: passwordConfirmation,
            });

            showMessage(messageEl, data.message || 'Password updated.', false);
            showSuccessToast(data.message || 'Password updated.');
            setTimeout(() => {
                window.location.href = '/admin/login';
            }, 1200);
        } catch (error) {
            const message =
                error.response?.data?.message ||
                error.response?.data?.errors?.email?.[0] ||
                'Unable to reset password. The link may have expired.';
            showMessage(messageEl, message, true);
            showErrorToast(message);
            submitBtn.disabled = false;
            submitBtn.textContent = original;
        }
    });
}

function showMessage(el, text, isError) {
    if (!el) return;
    el.textContent = text;
    el.classList.remove('hidden', 'text-[#CE2028]', 'text-emerald-700');
    el.classList.add(isError ? 'text-[#CE2028]' : 'text-emerald-700');
}
