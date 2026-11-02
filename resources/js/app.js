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
import { initResetPasswordPage } from './modules/password-reset/index';
import { bindForgotPasswordFlow } from './modules/password-reset/forgot-flow';
import { initPublicPostShow } from './modules/public/post-show';
import { initPublicSharePage } from './modules/public/share-page';
import { initPublicAnalytics } from './modules/public-analytics';
import { bindPasswordPolicyFields } from './modules/password-validation';
import { showErrorToast } from './modules/shared/toast';

axios.defaults.baseURL = '/api/v1';
axios.defaults.headers.common.Accept = 'application/json';

hydrateApiAuth();

axios.interceptors.response.use(
    (response) => response,
    (error) => {
        const status = error.response?.status;
        const requestUrl = error.config?.url ?? '';
        const isAuthFormRequest = requestUrl.includes('/auth/login')
            || requestUrl.includes('/auth/register')
            || requestUrl.includes('/auth/bootstrap-admin')
            || requestUrl.includes('/auth/forgot-password')
            || requestUrl.includes('/auth/verify-reset-code')
            || requestUrl.includes('/auth/reset-password')
            || requestUrl.includes('/auth/login-code')
            || requestUrl.includes('/auth/verify-login-code');
        const isPublicPage =
            document.body.dataset.page === 'public-post-show' ||
            document.body.dataset.page === 'public-share' ||
            document.body.dataset.page === 'reset-password';

        if (status === 401 && !isAuthFormRequest && !isPublicPage) {
            const page = document.body?.dataset?.page;
            if (page !== 'admin-login' && page !== 'admin-register') {
                window.location.href = '/admin/login';
            }
        }

        if (status === 403 && error.response?.data?.data?.must_change_password) {
            const target = '/admin/profile?force_password=1';
            if (!window.location.pathname.startsWith('/admin/profile')) {
                window.location.href = target;
            }
            return Promise.reject(error);
        }

        if (status === 403 && error.response?.data?.data?.requires_verification) {
            return Promise.reject(error);
        }

        const currentPage = document.body?.dataset?.page;
        if (status === 403 && currentPage !== 'admin-login' && currentPage !== 'admin-register') {
            showErrorToast(
                error.response?.data?.message ?? 'You do not have permission.',
                'Forbidden',
            );
        }

        return Promise.reject(error);
    },
);

window.axios = axios;
window.Swal = Swal;

document.addEventListener('DOMContentLoaded', () => {
    bindPasswordPolicyFields(document);

    const page = document.body.dataset.page;

    if (page === 'admin-login') {
        redirectIfAuthenticated();
        bindLoginForm();
        bindForgotPasswordFlow();
        return;
    }

    if (page === 'admin-register') {
        redirectIfAuthenticated();
        bindRegisterForm();
        return;
    }

    if (page === 'reset-password') {
        initResetPasswordPage();
        return;
    }

    if (page === 'public-post-show') {
        initPublicPostShow();
        initPublicAnalytics();
        return;
    }

    if (page === 'public-share') {
        initPublicSharePage();
        initPublicAnalytics();
        return;
    }

    initPublicAnalytics();

    if (page?.startsWith('admin-')) {
        requireAuthentication();
        initAdminPage(page);
    }
});
