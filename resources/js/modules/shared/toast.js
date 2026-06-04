import Swal from 'sweetalert2';

const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    customClass: {
        popup: 'admin-toast-popup',
    },
});

export function showSuccessToast(message, title = 'Success') {
    Toast.fire({ icon: 'success', title, text: message });
}

export function showErrorToast(message, title = 'Error') {
    Toast.fire({ icon: 'error', title, text: message });
}
