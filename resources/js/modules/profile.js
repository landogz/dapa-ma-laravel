import { getStoredUser, mergeStoredUser } from './auth';
import { bindPasswordToggles } from './shared/password-toggle';
import { showErrorToast, showSuccessToast } from './shared/toast';

const ROLE_LABELS = {
    super_admin: 'Super Admin',
    editor: 'Editor',
    publisher: 'Publisher',
    analytics_viewer: 'Analytics Viewer',
    app_user: 'App User',
};

let pendingPhotoRemoval = false;
let hasProfilePhoto = false;

export function initProfileModule() {
    const form = document.getElementById('admin-profile-form');

    if (!form) {
        return;
    }

    bindPasswordToggles(form);
    bindPhotoPreview(form);
    bindRemovePhoto(form);
    bindPasswordSubmit(form);
    bindSubmit(form);
    loadProfile(form);
}

async function loadProfile(form) {
    try {
        const response = await window.axios.get('/auth/me');
        populateForm(form, response.data.data);
    } catch (error) {
        const stored = getStoredUser();

        if (stored) {
            populateForm(form, stored);
        }

        showErrorToast(
            error.response?.data?.message ?? 'Unable to load your profile right now.',
            'Load failed',
        );
    }
}

function populateForm(form, user) {
    const firstNameInput = form.querySelector('[name="first_name"]');
    const lastNameInput = form.querySelector('[name="last_name"]');
    const emailInput = form.querySelector('[data-profile-email]');
    const roleInput = form.querySelector('[data-profile-role]');

    if (firstNameInput) {
        firstNameInput.value = user.first_name ?? splitName(user.name).firstName;
    }

    if (lastNameInput) {
        lastNameInput.value = user.last_name ?? splitName(user.name).lastName;
    }

    if (emailInput) {
        emailInput.value = user.email ?? '';
    }

    if (roleInput) {
        roleInput.value = ROLE_LABELS[user.role] ?? user.role ?? '';
    }

    pendingPhotoRemoval = false;
    hasProfilePhoto = Boolean(user.profile_image_url);
    updatePhotoPreview(user);
    updateRemovePhotoButton(form);
    clearFieldErrors(form);
}

function bindPhotoPreview(form) {
    const photoInput = form.querySelector('[data-profile-photo-input]');

    photoInput?.addEventListener('change', () => {
        const file = photoInput.files?.[0];

        if (!file) {
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            showErrorToast('Profile photo must be 5 MB or smaller.', 'Invalid file');
            photoInput.value = '';
            return;
        }

        pendingPhotoRemoval = false;
        const previewUrl = URL.createObjectURL(file);
        setPreviewImage(previewUrl);
        updateRemovePhotoButton(form);
    });
}

function bindRemovePhoto(form) {
    const removeButton = form.querySelector('[data-profile-remove-photo]');
    const photoInput = form.querySelector('[data-profile-photo-input]');

    removeButton?.addEventListener('click', () => {
        if (photoInput) {
            photoInput.value = '';
        }

        pendingPhotoRemoval = true;
        showInitialsPreview(form);
        updateRemovePhotoButton(form);
    });
}

function bindPasswordSubmit(form) {
    const passwordButton = form.querySelector('[data-profile-password-submit]');

    passwordButton?.addEventListener('click', async () => {
        const submitLabel = form.querySelector('[data-profile-password-submit-label]');
        const currentPassword = form.querySelector('[name="current_password"]')?.value ?? '';
        const password = form.querySelector('[name="password"]')?.value ?? '';
        const passwordConfirmation = form.querySelector('[name="password_confirmation"]')?.value ?? '';

        if (!currentPassword && !password && !passwordConfirmation) {
            showErrorToast('Enter your current password and a new password to update.', 'Nothing to update');
            return;
        }

        clearPasswordFieldErrors(form);
        setPasswordBusy(passwordButton, submitLabel, true);

        try {
            const response = await window.axios.put('/auth/password', {
                current_password: currentPassword,
                password,
                password_confirmation: passwordConfirmation,
            });

            clearPasswordFields(form);
            showSuccessToast(response.data.message ?? 'Password updated.', 'Saved');
        } catch (error) {
            applyFieldErrors(form, error.response?.data?.errors ?? {});
            showErrorToast(
                buildErrorMessage(error.response?.data?.errors, error.response?.data?.message),
                'Update failed',
            );
        } finally {
            setPasswordBusy(passwordButton, submitLabel, false);
        }
    });
}

function bindSubmit(form) {
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const submitButton = form.querySelector('[data-profile-submit]');
        const submitLabel = form.querySelector('[data-profile-submit-label]');
        clearFieldErrors(form);
        setBusy(submitButton, submitLabel, true);

        try {
            const formData = new FormData();
            formData.append('first_name', form.querySelector('[name="first_name"]')?.value?.trim() ?? '');
            formData.append('last_name', form.querySelector('[name="last_name"]')?.value?.trim() ?? '');

            const photoFile = form.querySelector('[data-profile-photo-input]')?.files?.[0];

            if (photoFile) {
                formData.append('profile_photo', photoFile);
            } else if (pendingPhotoRemoval) {
                formData.append('remove_profile_photo', '1');
            }

            const response = await window.axios.post('/auth/profile', formData, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });

            const user = response.data.data;
            mergeStoredUser(user);
            populateForm(form, user);
            showSuccessToast(response.data.message ?? 'Profile updated.', 'Saved');
        } catch (error) {
            applyFieldErrors(form, error.response?.data?.errors ?? {});
            showErrorToast(
                buildErrorMessage(error.response?.data?.errors, error.response?.data?.message),
                'Update failed',
            );
        } finally {
            setBusy(submitButton, submitLabel, false);
        }
    });
}

function updatePhotoPreview(user) {
    const preview = document.querySelector('[data-profile-preview]');
    const initials = document.querySelector('[data-profile-initials]');
    const displayName = buildDisplayName(user);

    if (user.profile_image_url && preview) {
        preview.src = user.profile_image_url;
        preview.classList.remove('hidden');
        initials?.classList.add('hidden');
        return;
    }

    showInitialsFromName(displayName);
}

function showInitialsPreview(form) {
    const firstName = form.querySelector('[name="first_name"]')?.value?.trim() ?? '';
    const lastName = form.querySelector('[name="last_name"]')?.value?.trim() ?? '';
    const displayName = `${firstName} ${lastName}`.trim();

    showInitialsFromName(displayName);
}

function showInitialsFromName(displayName) {
    const preview = document.querySelector('[data-profile-preview]');
    const initials = document.querySelector('[data-profile-initials]');

    if (preview) {
        preview.removeAttribute('src');
        preview.classList.add('hidden');
    }

    if (initials) {
        initials.textContent = buildInitials(displayName);
        initials.classList.remove('hidden');
    }
}

function setPreviewImage(url) {
    const preview = document.querySelector('[data-profile-preview]');
    const initials = document.querySelector('[data-profile-initials]');

    if (!preview) {
        return;
    }

    preview.src = url;
    preview.classList.remove('hidden');
    initials?.classList.add('hidden');
}

function updateRemovePhotoButton(form) {
    const removeButton = form.querySelector('[data-profile-remove-photo]');
    const photoInput = form.querySelector('[data-profile-photo-input]');
    const hasLocalPhoto = Boolean(photoInput?.files?.[0]);
    const shouldShow = (hasProfilePhoto || hasLocalPhoto) && !pendingPhotoRemoval;

    removeButton?.classList.toggle('hidden', !shouldShow);
}

function clearPasswordFields(form) {
    form.querySelectorAll('[data-profile-password-field]').forEach((input) => {
        input.value = '';
        input.type = 'password';
    });

    form.querySelectorAll('[data-password-toggle] i').forEach((icon) => {
        icon.classList.add('fa-eye');
        icon.classList.remove('fa-eye-slash');
    });
}

function clearPasswordFieldErrors(form) {
    form.querySelectorAll('[data-profile-password-field]').forEach((input) => {
        input.classList.remove('is-invalid');
    });

    ['current_password', 'password', 'password_confirmation'].forEach((field) => {
        const errorLabel = form.querySelector(`[data-field-error="${field}"]`);

        if (errorLabel) {
            errorLabel.textContent = '';
            errorLabel.classList.remove('is-visible');
        }
    });
}

function splitName(name = '') {
    const parts = String(name).trim().split(/\s+/);
    const firstName = parts[0] ?? '';
    const lastName = parts.slice(1).join(' ');

    return { firstName, lastName };
}

function buildDisplayName(user) {
    const firstName = user.first_name ?? splitName(user.name).firstName;
    const lastName = user.last_name ?? splitName(user.name).lastName;

    return `${firstName} ${lastName}`.trim() || user.name || '';
}

function buildInitials(name) {
    const parts = String(name).trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return 'DM';
    }

    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }

    return `${parts[0][0] ?? ''}${parts[parts.length - 1][0] ?? ''}`.toUpperCase();
}

function setBusy(button, labelElement, isBusy) {
    if (button) {
        button.disabled = isBusy;
        button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        button.classList.toggle('opacity-80', isBusy);
        button.classList.toggle('cursor-not-allowed', isBusy);
    }

    if (labelElement) {
        labelElement.textContent = isBusy ? 'Saving...' : 'Save Changes';
    }
}

function setPasswordBusy(button, labelElement, isBusy) {
    if (button) {
        button.disabled = isBusy;
        button.setAttribute('aria-busy', isBusy ? 'true' : 'false');
        button.classList.toggle('opacity-80', isBusy);
        button.classList.toggle('cursor-not-allowed', isBusy);
    }

    if (labelElement) {
        labelElement.textContent = isBusy ? 'Updating...' : 'Update Password';
    }
}

function clearFieldErrors(form) {
    form.querySelectorAll('.admin-auth-input').forEach((input) => {
        input.classList.remove('is-invalid');
    });

    form.querySelectorAll('[data-field-error]').forEach((errorLabel) => {
        errorLabel.textContent = '';
        errorLabel.classList.remove('is-visible');
    });
}

function applyFieldErrors(form, errors) {
    Object.entries(errors).forEach(([field, messages]) => {
        const input = form.querySelector(`[name="${field}"]`);
        const errorLabel = form.querySelector(`[data-field-error="${field}"]`);

        if (input) {
            input.classList.add('is-invalid');
        }

        if (errorLabel && messages.length > 0) {
            errorLabel.textContent = messages[0];
            errorLabel.classList.add('is-visible');
        }
    });
}

function buildErrorMessage(errors, message) {
    if (errors && Object.keys(errors).length > 0) {
        const parts = Object.values(errors).flat().filter(Boolean);

        if (parts.length > 0) {
            return parts.join(' | ');
        }
    }

    return message ?? 'Unable to update your profile right now.';
}
