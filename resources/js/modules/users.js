import axios from 'axios';
import Swal from 'sweetalert2';
import { getStoredUser } from './auth';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import {
    applyAdminFilterTabCounts,
    bindAdminFilterTabs,
    countByKey,
    renderAdminFilterTabs,
    setAdminFilterTab,
} from './shared/filter-tabs';
import { buildSwalForm, buildSwalOptions, formatApiValidationMessage } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
import { showErrorToast, showSuccessToast } from './shared/toast';

let usersTable;
let usersTableMode = null;
let usersViewportBound = false;
let usersActionsBound = false;
let usersFiltersBound = false;
let cachedUsers = [];
let activeAudience = 'staff';
let activeRole = '';

const STAFF_ROLES = [
    'super_admin',
    'publisher',
    'editor',
    'analytics_viewer',
];

const USER_ROLES = [
    ...STAFF_ROLES,
    'app_user',
];

const ROLE_META = {
    super_admin: {
        label: 'Super Admin',
        short: 'Super Admin',
        icon: 'fas fa-shield-halved',
        tone: 'navy',
        hint: 'Full admin access',
    },
    publisher: {
        label: 'Publisher',
        short: 'Publisher',
        icon: 'fas fa-bullhorn',
        tone: 'blue',
        hint: 'Publish & campaigns',
    },
    editor: {
        label: 'Editor',
        short: 'Editor',
        icon: 'fas fa-pen-to-square',
        tone: 'slate',
        hint: 'Content editing',
    },
    analytics_viewer: {
        label: 'Analytics Viewer',
        short: 'Analytics',
        icon: 'fas fa-chart-column',
        tone: 'gold',
        hint: 'Reports only',
    },
    app_user: {
        label: 'App User',
        short: 'App User',
        icon: 'fas fa-mobile-screen-button',
        tone: 'muted',
        hint: 'Mobile app account',
    },
};

export function initUsersModule() {
    const tableElement = document.getElementById('users-table');

    if (!tableElement) {
        return;
    }

    bindUsersFilters();

    if (usersTable && usersTableMode === getUsersTableMode()) {
        loadUsers();
        return;
    }

    initializeUsersTable(tableElement).then(() => {
        bindAdminActionTooltipSuppression(tableElement);
        bindUserTableActions(tableElement);
        loadUsers();
    });

    bindUsersViewportListener(tableElement);
}

export function loadUsers() {
    if (!usersTable) {
        return;
    }

    axios
        .get('/admin/users', { params: { per_page: 200 } })
        .then((response) => {
            cachedUsers = Array.isArray(response.data.data?.data)
                ? response.data.data.data
                : [];
            updateFilterCounts();
            renderFilteredUsers();
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

function bindUsersFilters() {
    if (usersFiltersBound) {
        return;
    }

    const audienceRoot = document.getElementById('users-audience-tabs');
    const roleRoot = document.getElementById('users-role-tabs');

    if (!audienceRoot || !roleRoot) {
        return;
    }

    usersFiltersBound = true;

    renderAdminFilterTabs(audienceRoot, [
        {
            value: 'staff',
            label: 'Admin staff',
            icon: 'fas fa-user-tie',
            hint: 'Console access',
            count: 0,
        },
        {
            value: 'app',
            label: 'App users',
            icon: 'fas fa-mobile-screen-button',
            hint: 'Mobile accounts',
            count: 0,
        },
    ]);
    setAdminFilterTab(audienceRoot, activeAudience);
    bindAdminFilterTabs(audienceRoot, (value) => {
        activeAudience = value || 'staff';
        activeRole = '';
        renderRoleTabs();
        updateFilterCounts();
        renderFilteredUsers();
    });

    renderRoleTabs();
}

function renderRoleTabs() {
    const roleRoot = document.getElementById('users-role-tabs');
    if (!roleRoot) {
        return;
    }

    const roleTitle = document.querySelector('[data-users-role-title]');

    // App users have a single role — hide the redundant role strip.
    if (activeAudience === 'app') {
        activeRole = '';
        roleRoot.hidden = true;
        if (roleTitle) {
            roleTitle.hidden = true;
        }
        roleRoot.replaceChildren();
        delete roleRoot.dataset.tabsBound;
        return;
    }

    roleRoot.hidden = false;
    if (roleTitle) {
        roleTitle.hidden = false;
    }
    const items = [
        {
            value: '',
            label: 'All',
            icon: 'fas fa-users',
            hint: 'All staff roles',
            count: 0,
        },
        ...STAFF_ROLES.map((role) => ({
            value: role,
            label: ROLE_META[role]?.short ?? formatRole(role),
            icon: ROLE_META[role]?.icon ?? 'fas fa-user',
            hint: ROLE_META[role]?.hint ?? '',
            count: 0,
        })),
    ];

    delete roleRoot.dataset.tabsBound;
    renderAdminFilterTabs(roleRoot, items);
    setAdminFilterTab(roleRoot, activeRole);
    bindAdminFilterTabs(roleRoot, (value) => {
        activeRole = value ?? '';
        renderFilteredUsers();
    });
    updateFilterCounts();
}

function updateFilterCounts() {
    const audienceRoot = document.getElementById('users-audience-tabs');
    const roleRoot = document.getElementById('users-role-tabs');
    const staffUsers = cachedUsers.filter((user) => isStaffRole(user.role));
    const appUsers = cachedUsers.filter((user) => user.role === 'app_user');
    const scoped = activeAudience === 'app' ? appUsers : staffUsers;

    applyAdminFilterTabCounts(audienceRoot, {
        staff: staffUsers.length,
        app: appUsers.length,
    });

    applyAdminFilterTabCounts(roleRoot, {
        '': scoped.length,
        ...countByKey(scoped, (user) => user.role),
    });
}

function getFilteredUsers() {
    return cachedUsers.filter((user) => {
        const role = String(user.role || '');
        const inAudience = activeAudience === 'app'
            ? role === 'app_user'
            : isStaffRole(role);

        if (!inAudience) {
            return false;
        }

        if (!activeRole) {
            return true;
        }

        return role === activeRole;
    });
}

function renderFilteredUsers() {
    if (!usersTable) {
        return;
    }

    const users = getFilteredUsers();
    usersTable.clear();
    users.forEach((user) => {
        usersTable.row.add(buildUserRowData(user));
    });
    usersTable.draw();
}

function isStaffRole(role) {
    return STAFF_ROLES.includes(String(role || ''));
}

function bindUserTableActions(tableElement) {
    if (usersActionsBound || !tableElement) {
        return;
    }

    usersActionsBound = true;

    const handleClick = (event) => {
        const deleteButton = event.target.closest('[data-user-delete-button]');
        if (deleteButton) {
            event.preventDefault();
            deleteUser(deleteButton);
            return;
        }

        const editButton = event.target.closest('[data-user-edit-button]');
        if (editButton) {
            event.preventDefault();
            editUser(editButton);
            return;
        }

        const roleButton = event.target.closest('[data-user-role-button]');
        if (roleButton) {
            event.preventDefault();
            changeUserRole(roleButton);
        }
    };

    tableElement.addEventListener('click', handleClick);
    document.addEventListener('click', (event) => {
        const overflowItem = event.target.closest(
            '.admin-action-overflow-menu [data-user-delete-button], .admin-action-overflow-menu [data-user-edit-button], .admin-action-overflow-menu [data-user-role-button]',
        );
        if (overflowItem) {
            handleClick(event);
        }
    });
}

async function deleteUser(button) {
    const userId = button.getAttribute('data-user-delete-button');
    const userName = button.getAttribute('data-user-name');

    const result = await Swal.fire(buildSwalOptions({
        icon: 'warning',
        title: `Delete User: ${userName}`,
        text: 'This will permanently remove the selected user account.',
        showCancelButton: true,
        confirmButtonText: 'Delete User',
    }, { danger: true, size: 'sm' }));

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await axios.delete(`/admin/users/${userId}`);
        showSuccessToast(response.data.message ?? 'User deleted successfully.', 'User deleted');
        loadUsers();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Please try again.',
            'Unable to delete user',
        );
    }
}

async function editUser(button) {
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
                    options: roleSelectOptions(),
                },
                {
                    id: 'user-password',
                    label: 'Reset Password',
                    type: 'password',
                    optional: true,
                    placeholder: 'Leave blank to keep current password',
                },
                {
                    id: 'user-password-confirmation',
                    label: 'Confirm Password',
                    type: 'password',
                    optional: true,
                    confirmFor: 'user-password',
                    placeholder: 'Repeat new password',
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
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

            const payload = {
                name,
                email,
                role,
            };

            if (password) {
                payload.password = password;
                payload.password_confirmation = passwordConfirmation;
            }

            try {
                const response = await axios.put(`/admin/users/${userId}`, payload);
                return response.data;
            } catch (error) {
                Swal.showValidationMessage(
                    formatApiValidationMessage(
                        error.response,
                        error.response?.data?.message ?? 'Unable to update user. Please try again.',
                    ),
                );
                return false;
            }
        },
    }, { size: 'lg' }));

    if (!result.isConfirmed || !result.value) {
        return;
    }

    showSuccessToast(result.value.message ?? 'User updated successfully.', 'User updated');
    loadUsers();
}

function renderUserActions(user) {
    const currentUser = getStoredUser();
    const isCurrentUser = Number(currentUser?.id) === Number(user.id);
    const isMobile = usersTableMode === 'mobile';

    if (isCurrentUser) {
        return `
            <div class="admin-table-actions">
                <span class="admin-empty-badge">Current account</span>
            </div>
        `;
    }

    return buildAdminActionButtons([
        {
            tooltip: 'Edit User',
            icon: 'fas fa-user-pen',
            className: 'admin-table-action-primary',
            attrs: `data-user-edit-button="${user.id}" data-user-name="${escapeHtml(user.name)}" data-user-email="${escapeHtml(user.email)}" data-user-role="${escapeHtml(user.role)}"`,
            label: 'Edit User',
        },
        {
            tooltip: 'Change Role',
            icon: 'fas fa-user-gear',
            attrs: `data-user-role-button="${user.id}" data-user-name="${escapeHtml(user.name)}" data-user-role="${escapeHtml(user.role)}"`,
            label: 'Change Role',
        },
        {
            tooltip: 'Delete User',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `data-user-delete-button="${user.id}" data-user-name="${escapeHtml(user.name)}"`,
            label: 'Delete User',
        },
    ], { isMobile, nowrap: true });
}

async function changeUserRole(button) {
    const userId = button.getAttribute('data-user-role-button');
    const userName = button.getAttribute('data-user-name');
    const currentRole = button.getAttribute('data-user-role');

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
                    options: roleSelectOptions(),
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Save Role',
        preConfirm: () => document.getElementById('user-role')?.value,
    }, { size: 'sm' }));

    if (!result.isConfirmed) {
        return;
    }

    try {
        const response = await axios.put(`/admin/users/${userId}/role`, {
            role: result.value,
        });

        showSuccessToast(response.data.message ?? 'Role updated successfully.', 'Role updated');
        loadUsers();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Please try again.',
            'Unable to update role',
        );
    }
}

function roleSelectOptions() {
    return USER_ROLES.map((role) => ({
        value: role,
        label: ROLE_META[role]?.label ?? formatRole(role),
    }));
}

function badge(role) {
    const meta = ROLE_META[role] ?? {
        label: formatRole(role),
        tone: 'muted',
    };

    return `<span class="admin-user-role-badge is-${escapeHtml(meta.tone)}">${escapeHtml(meta.label)}</span>`;
}

function formatRole(role) {
    return String(role || '')
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function getUserInitials(name) {
    const parts = String(name ?? '').trim().split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return parts[0].charAt(0).toUpperCase();
    }

    return `${parts[0].charAt(0)}${parts[parts.length - 1].charAt(0)}`.toUpperCase();
}

function renderUserAvatar(user) {
    const name = escapeHtml(user.name ?? 'User');
    const initials = escapeHtml(getUserInitials(user.name));
    const imageUrl = user.profile_image_url;

    if (imageUrl) {
        return `
            <span class="admin-user-avatar" title="${name}">
                <img
                    src="${escapeHtml(imageUrl)}"
                    alt="${name}"
                    class="admin-user-avatar-img"
                    loading="lazy"
                >
            </span>
        `;
    }

    return `<span class="admin-user-avatar admin-user-avatar-fallback" title="${name}" aria-label="${name}">${initials}</span>`;
}

function renderUserIdentity(user) {
    return `
        <div class="admin-user-identity">
            ${renderUserAvatar(user)}
            <div class="admin-user-identity-copy min-w-0">
                <p class="admin-user-identity-name">${escapeHtml(user.name)}</p>
                <p class="admin-user-identity-email">${escapeHtml(user.email)}</p>
            </div>
        </div>
    `;
}

function getUsersTableMode() {
    return window.matchMedia('(max-width: 1023px)').matches ? 'mobile' : 'desktop';
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
        searchPlaceholder: 'Search name or email',
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
                { title: 'User', className: 'dt-col-primary', orderable: true },
                { title: 'Role', className: 'dt-col-nowrap' },
                { title: 'Last Login', className: 'dt-col-nowrap' },
                { title: 'Actions', orderable: false, className: 'dt-col-actions' },
            ],
    }));
}

function bindUsersViewportListener(tableElement) {
    if (usersViewportBound) {
        return;
    }

    const query = window.matchMedia('(max-width: 1023px)');
    const onChange = async () => {
        const nextMode = getUsersTableMode();

        if (nextMode === usersTableMode) {
            return;
        }

        await initializeUsersTable(tableElement);
        renderFilteredUsers();
    };

    if (typeof query.addEventListener === 'function') {
        query.addEventListener('change', onChange);
    } else {
        query.addListener(onChange);
    }

    usersViewportBound = true;
}

function buildUserRowData(user) {
    const lastLogin = formatLastLogin(user.last_login_at);
    const neverLoggedIn = !user.last_login_at;

    if (usersTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card">
                <div class="admin-table-mobile-title-row">
                    <div class="flex min-w-0 items-center gap-3">
                        ${renderUserAvatar(user)}
                        <div class="min-w-0">
                            <p class="admin-table-mobile-kicker">User #${user.id}</p>
                            <p class="admin-table-mobile-title">${escapeHtml(user.name)}</p>
                        </div>
                    </div>
                    ${badge(user.role)}
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Email:</span> ${escapeHtml(user.email)}</p>
                    <p><span>Last login:</span> ${neverLoggedIn ? '<span class="admin-user-login-muted">Never</span>' : escapeHtml(lastLogin)}</p>
                </div>
            </div>`,
            renderUserActions(user),
        ];
    }

    return [
        user.id,
        renderUserIdentity(user),
        badge(user.role),
        neverLoggedIn
            ? '<span class="admin-user-login-muted">Never</span>'
            : escapeHtml(lastLogin),
        renderUserActions(user),
    ];
}

function formatLastLogin(value) {
    if (!value) {
        return 'Never';
    }

    const normalized = typeof value === 'string' && value.includes(' ')
        ? value.replace(' ', 'T')
        : value;

    const date = new Date(normalized);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
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
    const defaultRole = activeAudience === 'app' ? 'app_user' : 'editor';

    const result = await Swal.fire(buildSwalOptions({
        title: 'Add User',
        html: buildSwalForm({
            description: 'Create a staff console account or a mobile app user.',
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
                    value: defaultRole,
                    options: roleSelectOptions(),
                },
                {
                    id: 'user-password',
                    label: 'Password *',
                    type: 'password',
                    placeholder: 'Create a strong password',
                },
                {
                    id: 'user-password-confirmation',
                    label: 'Confirm Password *',
                    type: 'password',
                    confirmFor: 'user-password',
                    placeholder: 'Repeat password',
                },
            ],
        }),
        showCancelButton: true,
        confirmButtonText: 'Create User',
        allowOutsideClick: () => !Swal.isLoading(),
        preConfirm: async () => {
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

            try {
                const response = await axios.post('/admin/users', {
                    name,
                    email,
                    role,
                    password,
                    password_confirmation: passwordConfirmation,
                });

                return response.data;
            } catch (error) {
                Swal.showValidationMessage(
                    formatApiValidationMessage(
                        error.response,
                        error.response?.data?.message ?? 'Unable to create user. Please try again.',
                    ),
                );
                return false;
            }
        },
    }, { size: 'lg' }));

    if (!result.isConfirmed || !result.value) {
        return;
    }

    showSuccessToast(result.value.message ?? 'User created successfully.', 'User created');

    const createdRole = result.value.data?.role;
    if (createdRole === 'app_user') {
        activeAudience = 'app';
        activeRole = '';
        const audienceRoot = document.getElementById('users-audience-tabs');
        setAdminFilterTab(audienceRoot, 'app');
        renderRoleTabs();
    } else if (createdRole && isStaffRole(createdRole)) {
        activeAudience = 'staff';
        activeRole = '';
        const audienceRoot = document.getElementById('users-audience-tabs');
        setAdminFilterTab(audienceRoot, 'staff');
        renderRoleTabs();
    }

    loadUsers();
}

window.Users = { create: createUserPrompt };
