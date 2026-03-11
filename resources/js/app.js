import './bootstrap';
import axios from 'axios';
import Swal from 'sweetalert2';
import {
    bindLoginForm,
    bindRegisterForm,
    hydrateApiAuth,
    redirectIfAuthenticated,
    requireAuthentication,
} from './modules/auth';
import { initAdminPage } from './modules/admin-dashboard';

axios.defaults.baseURL = '/api/v1';
axios.defaults.headers.common.Accept = 'application/json';

hydrateApiAuth();

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const requestUrl = error.config?.url ?? '';
        const isAuthFormRequest = requestUrl.includes('/auth/login') || requestUrl.includes('/auth/register');

        if (status === 401 && !isAuthFormRequest) {
            Swal.fire({
                icon: 'warning',
                title: 'Session expired',
                text: 'Please log in again.',
            }).then(() => {
                window.location.href = '/admin/login';
            });
        }

        if (status === 403) {
            Swal.fire({
                icon: 'error',
                title: 'Forbidden',
                text: error.response?.data?.message ?? 'You do not have permission.',
            });
        }

        return Promise.reject(error);
    },
);

window.axios = axios;
window.Swal = Swal;

document.addEventListener('DOMContentLoaded', () => {
    const page = document.body.dataset.page;

    if (page === 'admin-login') {
        redirectIfAuthenticated();
        bindLoginForm();
        return;
    }

    if (page === 'admin-register') {
        redirectIfAuthenticated();
        bindRegisterForm();
        return;
    }

    if (page?.startsWith('admin-')) {
        requireAuthentication();
        initAdminPage(page);
    }
});
