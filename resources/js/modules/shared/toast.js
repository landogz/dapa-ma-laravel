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

export function showSuccessToast(message, title = 'Success') {
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
    return Toast.fire({
        toast: true,
        icon: 'error',
        title,
        text: message,
        showConfirmButton: false,
        showCancelButton: false,
    });
}
