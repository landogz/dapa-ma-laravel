import Swal from 'sweetalert2';

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    showCancelButton: false,
    timer: 3500,
    timerProgressBar: true,
    customClass: {
        popup: 'admin-toast-popup',
    },
});

const DOM_TOAST_TTL_MS = 3500;

/**
 * True when a full (non-toast) SweetAlert2 dialog is open.
 * Calling Toast.fire() in that state replaces/closes the form modal.
 */
function isBlockingSwalOpen() {
    if (!Swal.isVisible()) {
        return false;
    }

    const popup = Swal.getPopup();
    if (!popup) {
        return false;
    }

    return !popup.classList.contains('swal2-toast')
        && !popup.classList.contains('admin-toast-popup');
}

function ensureDomToastHost() {
    let host = document.getElementById('admin-dom-toast-host');
    if (host) {
        return host;
    }

    host = document.createElement('div');
    host.id = 'admin-dom-toast-host';
    host.className = 'admin-dom-toast-host';
    host.setAttribute('aria-live', 'polite');
    document.body.appendChild(host);
    return host;
}

function showDomToast({ icon = 'success', title = '', message = '' } = {}) {
    const host = ensureDomToastHost();
    const toast = document.createElement('div');
    toast.className = `admin-dom-toast admin-dom-toast-${icon}`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `
        <div class="admin-dom-toast-icon" aria-hidden="true">
            <i class="fas ${icon === 'error' ? 'fa-circle-xmark' : 'fa-circle-check'}"></i>
        </div>
        <div class="admin-dom-toast-body">
            ${title ? `<div class="admin-dom-toast-title">${escapeToastText(title)}</div>` : ''}
            ${message ? `<div class="admin-dom-toast-text">${escapeToastText(message)}</div>` : ''}
        </div>
    `;

    host.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('is-visible'));

    window.setTimeout(() => {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 220);
    }, DOM_TOAST_TTL_MS);

    return Promise.resolve({ isConfirmed: true, isDismissed: false });
}

function escapeToastText(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;');
}

export function showSuccessToast(message, title = 'Success') {
    if (isBlockingSwalOpen()) {
        return showDomToast({ icon: 'success', title, message });
    }

    return Toast.fire({
        toast: true,
        icon: 'success',
        title,
        text: message,
        showConfirmButton: false,
        showCancelButton: false,
    });
}

export function showErrorToast(message, title = 'Error') {
    if (isBlockingSwalOpen()) {
        return showDomToast({ icon: 'error', title, message });
    }

    return Toast.fire({
        toast: true,
        icon: 'error',
        title,
        text: message,
        showConfirmButton: false,
        showCancelButton: false,
    });
}
