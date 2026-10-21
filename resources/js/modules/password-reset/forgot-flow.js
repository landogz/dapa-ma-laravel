import axios from 'axios';
import { bindPasswordToggles } from '../shared/password-toggle';

const RESEND_SECONDS = 45;

export function bindForgotPasswordFlow() {
    const loginView = document.getElementById('admin-login-view');
    const forgotView = document.getElementById('admin-forgot-view');
    const openButton = document.querySelector('[data-forgot-password]');

    if (!loginView || !forgotView || !openButton) {
        return;
    }

    const steps = {
        email: forgotView.querySelector('[data-forgot-step="email"]'),
        code: forgotView.querySelector('[data-forgot-step="code"]'),
        password: forgotView.querySelector('[data-forgot-step="password"]'),
    };
    const emailForm = document.getElementById('forgot-email-form');
    const codeForm = document.getElementById('forgot-code-form');
    const passwordForm = document.getElementById('forgot-password-form');
    const digits = [...forgotView.querySelectorAll('[data-reset-digit]')];
    const backButton = forgotView.querySelector('[data-forgot-back]');
    const resendWait = forgotView.querySelector('[data-resend-wait]');
    const resendButton = forgotView.querySelector('[data-resend-code]');
    const sentTo = forgotView.querySelector('[data-forgot-sent-to]');
    const emailLabel = forgotView.querySelector('[data-forgot-email-label]');
    const successNote = document.getElementById('login-reset-success');

    let email = '';
    let token = '';
    let step = 'email';
    let secondsLeft = 0;
    let timerId = null;
    let verifying = false;

    bindPasswordToggles(forgotView);

    const showStep = (nextStep) => {
        step = nextStep;
        loginView.classList.add('hidden');
        forgotView.classList.remove('hidden');
        Object.entries(steps).forEach(([name, element]) => {
            element?.classList.toggle('hidden', name !== nextStep);
        });
        const backLabel = forgotView.querySelector('[data-forgot-back-label]');
        if (backLabel) {
            backLabel.textContent = nextStep === 'email' ? 'Back to Login' : 'Back';
        }
    };

    const showLogin = (message = '') => {
        stopTimer();
        forgotView.classList.add('hidden');
        loginView.classList.remove('hidden');
        if (!successNote) {
            return;
        }
        successNote.textContent = message;
        successNote.classList.toggle('hidden', message === '');
    };

    openButton.addEventListener('click', () => {
        const loginEmail = document.getElementById('email')?.value?.trim() ?? '';
        const emailInput = document.getElementById('forgot-email');
        if (emailInput && loginEmail && emailInput.value.trim() === '') {
            emailInput.value = loginEmail;
        }
        clearError('forgot-email-error');
        showStep('email');
        emailInput?.focus();
    });

    backButton?.addEventListener('click', () => {
        if (step === 'password') {
            showStep('code');
            return;
        }
        if (step === 'code') {
            showStep('email');
            return;
        }
        showLogin();
    });

    emailForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const input = document.getElementById('forgot-email');
        const nextEmail = input?.value?.trim() ?? '';
        clearError('forgot-email-error');

        if (!isEmail(nextEmail)) {
            showError('forgot-email-error', 'Enter a valid email address.');
            return;
        }

        const button = emailForm.querySelector('[type="submit"]');
        setBusy(button, true, 'Sending...');
        try {
            await sendCode(nextEmail);
            email = nextEmail;
            if (sentTo) {
                sentTo.textContent = `We've sent a six-digit code to ${email}.`;
            }
            if (emailLabel) {
                emailLabel.textContent = email;
            }
            setBusy(button, false, 'Send reset code');
            clearDigits();
            showStep('code');
            startTimer();
            digits[0]?.focus();
        } catch (error) {
            showError('forgot-email-error', errorMessage(error, 'Unable to send reset link. Please try again.'));
            setBusy(button, false, 'Send reset code');
        }
    });

    digits.forEach((input, index) => {
        input.addEventListener('input', () => {
            const value = input.value.replace(/\D/g, '');
            if (value.length > 1) {
                fillDigits(value);
                if (currentCode().length === digits.length) {
                    verifyCode();
                }
                return;
            }
            input.value = value;
            if (value && index < digits.length - 1) {
                digits[index + 1].focus();
            }
            syncVerifyButton();
            if (currentCode().length === digits.length) {
                verifyCode();
            }
        });

        input.addEventListener('keydown', (event) => {
            if (event.key === 'Backspace' && input.value === '' && index > 0) {
                digits[index - 1].focus();
                digits[index - 1].value = '';
                syncVerifyButton();
            }
        });

        input.addEventListener('paste', (event) => {
            event.preventDefault();
            const pasted = event.clipboardData?.getData('text') ?? '';
            fillDigits(pasted);
            if (currentCode().length === digits.length) {
                verifyCode();
            }
        });
    });

    codeForm?.addEventListener('submit', (event) => {
        event.preventDefault();
        verifyCode();
    });

    resendButton?.addEventListener('click', async () => {
        if (secondsLeft > 0 || !email) {
            return;
        }
        resendButton.disabled = true;
        clearError('forgot-code-error');
        try {
            await sendCode(email);
            clearDigits();
            startTimer();
            digits[0]?.focus();
        } catch (error) {
            showError('forgot-code-error', errorMessage(error, 'Unable to send reset link. Please try again.'));
            resendButton.disabled = false;
        }
    });

    passwordForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const password = document.getElementById('forgot-new-password')?.value ?? '';
        const confirmation = document.getElementById('forgot-confirm-password')?.value ?? '';
        clearError('forgot-password-error');
        clearError('forgot-confirm-error');

        if (password.length < 8) {
            showError('forgot-password-error', 'Password must be at least 8 characters.');
            return;
        }
        if (password !== confirmation) {
            showError('forgot-confirm-error', 'Passwords do not match.');
            return;
        }

        const button = passwordForm.querySelector('[type="submit"]');
        setBusy(button, true, 'Saving...');
        try {
            const { data } = await axios.post('/auth/reset-password', {
                email,
                token,
                password,
                password_confirmation: confirmation,
            });
            const loginEmail = document.getElementById('email');
            if (loginEmail) {
                loginEmail.value = email;
            }
            passwordForm.reset();
            showLogin(data.message || 'Password has been reset. You can sign in now.');
        } catch (error) {
            const message = errorMessage(error, 'Unable to reset password. The link may have expired.');
            const field = error.response?.data?.errors?.password ? 'forgot-password-error' : 'forgot-confirm-error';
            showError(field, message);
            setBusy(button, false, 'Save new password');
        }
    });

    function currentCode() {
        return digits.map((input) => input.value).join('');
    }

    function clearDigits() {
        digits.forEach((input) => {
            input.value = '';
        });
        syncVerifyButton();
        clearError('forgot-code-error');
    }

    function fillDigits(raw) {
        const chars = raw.replace(/\D/g, '').slice(0, digits.length).split('');
        digits.forEach((input, index) => {
            input.value = chars[index] ?? '';
        });
        const next = digits[Math.min(chars.length, digits.length - 1)];
        next?.focus();
        syncVerifyButton();
    }

    function syncVerifyButton() {
        const button = codeForm?.querySelector('[type="submit"]');
        if (!button || button.getAttribute('aria-busy') === 'true') {
            return;
        }
        button.disabled = currentCode().length !== digits.length;
    }

    async function verifyCode() {
        if (verifying || currentCode().length !== digits.length) {
            return;
        }
        verifying = true;
        clearError('forgot-code-error');
        const button = codeForm?.querySelector('[type="submit"]');
        setBusy(button, true, 'Verifying...');
        try {
            const { data } = await axios.post('/auth/verify-reset-code', {
                email,
                code: currentCode(),
            });
            token = data?.data?.token ?? '';
            if (!token) {
                showError('forgot-code-error', 'That code does not match. Check your email and try again.');
                setBusy(button, false, 'Verify Code');
                syncVerifyButton();
                return;
            }
            setBusy(button, false, 'Verify Code');
            syncVerifyButton();
            showStep('password');
            document.getElementById('forgot-new-password')?.focus();
        } catch (error) {
            showError('forgot-code-error', errorMessage(error, 'That code does not match. Check your email and try again.'));
            setBusy(button, false, 'Verify Code');
            syncVerifyButton();
        } finally {
            verifying = false;
        }
    }

    function startTimer() {
        stopTimer();
        secondsLeft = RESEND_SECONDS;
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

    async function sendCode(targetEmail) {
        await axios.post('/auth/forgot-password', { email: targetEmail });
    }
}

function isEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function clearError(id) {
    const label = document.getElementById(id);
    if (!label) {
        return;
    }
    label.textContent = '';
    label.classList.remove('is-visible');
}

function showError(id, message) {
    const label = document.getElementById(id);
    if (!label) {
        return;
    }
    label.textContent = message;
    label.classList.add('is-visible');
}

function errorMessage(error, fallback) {
    const data = error.response?.data ?? {};
    const fieldError = data.errors
        ? Object.values(data.errors).flat().find(Boolean)
        : '';

    return fieldError || data.message || fallback;
}

function setBusy(button, isBusy, label) {
    if (!button) {
        return;
    }

    button.disabled = isBusy;
    button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
    button.replaceChildren();
    if (isBusy) {
        const spinner = document.createElement('span');
        spinner.className = 'inline-block h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white';
        spinner.setAttribute('aria-hidden', 'true');
        button.append(spinner);
    }
    button.append(document.createTextNode(label));
    button.classList.toggle('gap-2', isBusy);
}
