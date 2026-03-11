import axios from 'axios';
import Swal from 'sweetalert2';
import { getStoredUser } from './auth';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import { buildSwalForm, buildSwalOptions } from './shared/swal-forms';

let usersTable;
let usersTableMode = null;
let usersViewportBound = false;
const USER_ROLES = [
    'super_admin',
    'editor',
    'publisher',
    'analytics_viewer',
    'app_user',
];

export function initUsersModule() {
    const tableElement = document.getElementById('users-table');

    if (!tableElement) {
        return;
    }

    if (usersTable && usersTableMode === getUsersTableMode()) {
        loadUsers();
        return;
    }

    initializeUsersTable(tableElement).then(() => {
        loadUsers();
    });

    bindUsersViewportListener(tableElement);
}

export function loadUsers() {
    if (!usersTable) {
        return;
    }

    axios
        .get('/admin/users')
        .then((response) => {
            const users = response.data.data?.data ?? [];

            usersTable.clear();

            users.forEach((user) => {
                usersTable.row.add(buildUserRowData(user));
            });

            usersTable.draw();
            bindRoleButtons();
            bindEditButtons();
            bindDeleteButtons();
        })
        .catch((error) => {
            const message =
                error.response?.data?.message ??
                'Unable to load users, please try again.';

            Swal.fire({
                icon: 'error',
                title: 'Users',
                text: message,
            });
        });
}

function bindDeleteButtons() {
    document.querySelectorAll('[data-user-delete-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            const userId = button.getAttribute('data-user-delete-button');
            const userName = button.getAttribute('data-user-name');

            const result = await Swal.fire(buildSwalOptions({
                icon: 'warning',
                title: `Delete User: ${userName}`,
                text: 'This will permanently remove the selected user account.',
                showCancelButton: true,
                confirmButtonText: 'Delete User',
            }, { danger: true }));

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await axios.delete(`/admin/users/${userId}`);

                await Swal.fire({
                    icon: 'success',
                    title: 'User deleted',
                    text: response.data.message,
                    confirmButtonColor: '#055498',
                });

                loadUsers();
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Unable to delete user',
                    text: error.response?.data?.message ?? 'Please try again.',
                    confirmButtonColor: '#CE2028',
                });
            }
        });
    });
}

function bindEditButtons() {
    document.querySelectorAll('[data-user-edit-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            const userId = button.getAttribute('data-user-edit-button');
            const userName = button.getAttribute('data-user-name');
            const userEmail = button.getAttribute('data-user-email');
            const userRole = button.getAttribute('data-user-role');

            const result = await Swal.fire(buildSwalOptions({
                title: `Edit User: ${userName}`,
                html: buildSwalForm({
                    description: 'Update core user details and access level.',
                    fields: [
                        {
                            id: 'user-name',
                            label: 'Full Name *',
                            value: userName,
                            placeholder: 'Administrator name',
                        },
                        {
                            id: 'user-email',
                            label: 'Email Address *',
                            value: userEmail,
                            type: 'email',
                            placeholder: 'admin@example.gov.ph',
                        },
                        {
                            id: 'user-role',
                            label: 'Role *',
                            type: 'select',
                            value: userRole,
                            options: USER_ROLES.map((role) => ({
                                value: role,
                                label: formatRole(role),
                            })),
                        },
                        {
                            id: 'user-password',
                            label: 'Reset Password',
                            type: 'password',
                            placeholder: 'Leave blank to keep current password',
                        },
                        {
                            id: 'user-password-confirmation',
                            label: 'Confirm Password',
                            type: 'password',
                            placeholder: 'Repeat new password',
                        },
                    ],
                }),
                showCancelButton: true,
                confirmButtonText: 'Save Changes',
                preConfirm: () => {
                    const name = document.getElementById('user-name')?.value.trim();
                    const email = document.getElementById('user-email')?.value.trim();
                    const role = document.getElementById('user-role')?.value;
                    const password = document.getElementById('user-password')?.value;
                    const passwordConfirmation = document.getElementById('user-password-confirmation')?.value;

                    if (!name || !email || !role) {
                        Swal.showValidationMessage('Name, email, and role are required.');

                        return false;
                    }

                    if (password && password !== passwordConfirmation) {
                        Swal.showValidationMessage('Password confirmation does not match.');

                        return false;
                    }

                    return {
                        name,
                        email,
                        role,
                        password: password || undefined,
                        password_confirmation: password ? passwordConfirmation : undefined,
                    };
                },
            }));

            if (!result.isConfirmed) {
                return;
            }

            try {
                const payload = { ...result.value };

                if (!payload.password) {
                    delete payload.password;
                    delete payload.password_confirmation;
                }

                const response = await axios.put(`/admin/users/${userId}`, payload);

                await Swal.fire({
                    icon: 'success',
                    title: 'User updated',
                    text: response.data.message,
                    confirmButtonColor: '#055498',
                });

                loadUsers();
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Unable to update user',
                    text: error.response?.data?.message ?? 'Please try again.',
                    confirmButtonColor: '#CE2028',
                });
            }
        });
    });
}

function renderUserActions(user) {
    const currentUser = getStoredUser();
    const isCurrentUser = Number(currentUser?.id) === Number(user.id);
    const buttonBaseClass = usersTableMode === 'mobile' ? 'admin-table-action' : 'admin-table-action admin-table-action-icon';

    if (isCurrentUser) {
        return `
            <div class="admin-table-actions">
                <span class="admin-empty-badge">Current account</span>
            </div>
        `;
    }

    return `
        <div class="admin-table-actions${usersTableMode === 'mobile' ? ' admin-table-actions-mobile' : ''}">
            <button type="button" class="${buttonBaseClass} admin-table-action-primary" data-user-edit-button="${user.id}" data-user-name="${user.name}" data-user-email="${user.email}" data-user-role="${user.role}" title="Edit User" aria-label="Edit User"><i class="fas fa-user-pen"></i><span class="${usersTableMode === 'mobile' ? '' : 'sr-only'}">Edit User</span></button>
            <button type="button" class="${buttonBaseClass}" data-user-role-button="${user.id}" data-user-name="${user.name}" data-user-role="${user.role}" title="Change Role" aria-label="Change Role"><i class="fas fa-user-gear"></i><span class="${usersTableMode === 'mobile' ? '' : 'sr-only'}">Change Role</span></button>
            <button type="button" class="${buttonBaseClass} admin-table-action-danger" data-user-delete-button="${user.id}" data-user-name="${user.name}" title="Delete User" aria-label="Delete User"><i class="fas fa-trash"></i><span class="${usersTableMode === 'mobile' ? '' : 'sr-only'}">Delete User</span></button>
        </div>
    `;
}

function bindRoleButtons() {
    document.querySelectorAll('[data-user-role-button]').forEach((button) => {
        button.addEventListener('click', async () => {
            const userId = button.getAttribute('data-user-role-button');
            const userName = button.getAttribute('data-user-name');
            const currentRole = button.getAttribute('data-user-role');

            const options = USER_ROLES.reduce((accumulator, role) => {
                accumulator[role] = formatRole(role);

                return accumulator;
            }, {});

            const result = await Swal.fire(buildSwalOptions({
                title: `Update Role: ${userName}`,
                html: buildSwalForm({
                    description: 'Choose the appropriate access level for this user account.',
                    fields: [
                        {
                            id: 'user-role',
                            label: 'Role *',
                            type: 'select',
                            value: currentRole,
                            options: Object.entries(options).map(([value, label]) => ({ value, label })),
                        },
                    ],
                }),
                showCancelButton: true,
                confirmButtonText: 'Save Role',
                preConfirm: () => document.getElementById('user-role')?.value,
            }));

            if (!result.isConfirmed) {
                return;
            }

            try {
                const response = await axios.put(`/admin/users/${userId}/role`, {
                    role: result.value,
                });

                await Swal.fire({
                    icon: 'success',
                    title: 'Role updated',
                    text: response.data.message,
                    confirmButtonColor: '#055498',
                });

                loadUsers();
            } catch (error) {
                await Swal.fire({
                    icon: 'error',
                    title: 'Unable to update role',
                    text: error.response?.data?.message ?? 'Please try again.',
                    confirmButtonColor: '#CE2028',
                });
            }
        });
    });
}

function badge(role) {
    return `<span class="inline-flex rounded-full bg-[#055498]/10 px-2.5 py-1 text-xs font-semibold text-[#055498]">${formatRole(role)}</span>`;
}

function formatRole(role) {
    return role
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function getUsersTableMode() {
    return window.matchMedia('(max-width: 767px)').matches ? 'mobile' : 'desktop';
}

async function initializeUsersTable(tableElement) {
    const nextMode = getUsersTableMode();

    if (usersTable) {
        usersTable.destroy();
        tableElement.innerHTML = '';
    }

    usersTableMode = nextMode;
    usersTable = await createAdminDataTable(tableElement, getAdminDataTableOptions({
        searchLabel: 'Search users:',
        searchPlaceholder: 'Search users',
        infoLabel: 'Showing _START_ to _END_ of _TOTAL_ users',
        pageLength: nextMode === 'mobile' ? 5 : 10,
        scrollX: nextMode !== 'mobile',
        scrollCollapse: nextMode !== 'mobile',
        columns: nextMode === 'mobile'
            ? [
                { title: 'User', className: 'dt-col-mobile-summary' },
                { title: 'Actions', orderable: false, className: 'dt-col-actions' },
            ]
            : [
                { title: 'ID', className: 'dt-col-id' },
                { title: 'Name', className: 'dt-col-primary' },
                { title: 'Email', className: 'dt-col-wide' },
                { title: 'Role', className: 'dt-col-nowrap' },
                { title: 'Actions', orderable: false, className: 'dt-col-actions' },
            ],
    }));
}

function bindUsersViewportListener(tableElement) {
    if (usersViewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 767px)');
    const onChange = async () => {
        const nextMode = getUsersTableMode();

        if (nextMode === usersTableMode) {
            return;
        }

        await initializeUsersTable(tableElement);
        loadUsers();
    };

    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', onChange);
    } else {
        query.addListener(onChange);
    }

    usersViewportBound = true;
}

function buildUserRowData(user) {
    if (usersTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div>
                        <p class="admin-table-mobile-kicker">User #${user.id}</p>
                        <p class="admin-table-mobile-title">${escapeHtml(user.name)}</p>
                    </div>
                    ${badge(user.role)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Email:</span> ${escapeHtml(user.email)}</p>
                </div>
            </div>`,
            renderUserActions(user),
        ];
    }

    return [
        user.id,
        escapeHtml(user.name),
        escapeHtml(user.email),
        badge(user.role),
        renderUserActions(user),
    ];
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

async function createUserPrompt() {
    const result = await Swal.fire(buildSwalOptions({
        title: 'Add User',
        html: buildSwalForm({
            description: 'Create a new admin or analytics user.',
            fields: [
                {
                    id: 'user-name',
                    label: 'Full Name *',
                    placeholder: 'Administrator name',
                },
                {
                    id: 'user-email',
                    label: 'Email Address *',
                    type: 'email',
                    placeholder: 'admin@example.gov.ph',
                },
                {
                    id: 'user-role',
                    label: 'Role *',
                    type: 'select',
                    value: 'editor',
                    options: USER_ROLES.map((role) => ({
                        value: role,
                        label: formatRole(role),
                    })),
                },
                {
                    id: 'user-password',
                    label: 'Password *',
                    type: 'password',
                    placeholder: 'Minimum 8 characters',
                },
                {
                    id: 'user-password-confirmation',
                    label: 'Confirm Password *',
                    type: 'password',
                    placeholder: 'Repeat password',
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Create User',
        preConfirm: () => {
            const name = document.getElementById('user-name')?.value.trim();
            const email = document.getElementById('user-email')?.value.trim();
            const role = document.getElementById('user-role')?.value;
            const password = document.getElementById('user-password')?.value;
            const passwordConfirmation = document.getElementById('user-password-confirmation')?.value;

            if (!name || !email || !role || !password || !passwordConfirmation) {
                Swal.showValidationMessage('All fields are required.');

                return false;
            }

            if (password !== passwordConfirmation) {
                Swal.showValidationMessage('Password confirmation does not match.');

                return false;
            }

            return {
                name,
                email,
                role,
                password,
                password_confirmation: passwordConfirmation,
            };
        },
    }));

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await axios.post('/admin/users', result.value);

        await Swal.fire({
            icon: 'success',
            title: 'User created',
            text: response.data.message,
            confirmButtonColor: '#055498',
        });

        loadUsers();
    } catch (error) {
        await Swal.fire({
            icon: 'error',
            title: 'Unable to create user',
            text: error.response?.data?.message ?? 'Please try again.',
            confirmButtonColor: '#CE2028',
        });
    }
}

window.Users = { create: createUserPrompt };

