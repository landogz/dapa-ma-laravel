import axios from 'axios';
import Swal from 'sweetalert2';
import { getStoredUser } from './auth';
import { createAdminDataTable, getAdminDataTableOptions } from './shared/datatables';
import {
    applyAdminFilterTabCounts,
    bindAdminFilterTabs,
    renderAdminFilterTabs,
    setAdminFilterTab,
} from './shared/filter-tabs';
import { buildSwalForm, buildSwalOptions, confirmWithSwal, formatApiValidationMessage } from './shared/swal-forms';
import { buildAdminActionButtons, bindAdminActionTooltipSuppression } from './shared/table-actions';
import { showErrorToast, showSuccessToast } from './shared/toast';

let usersTable;
let usersTableMode = null;
let usersViewportBound = false;
let usersActionsBound = false;
let usersFiltersBound = false;
let usersMetricsBound = false;
let usersSearchBound = false;
let loadUsersRequestId = 0;
let searchDebounceTimer = null;
let cachedSummary = {
    staff: 0,
    app: 0,
    never_logged_in: 0,
    must_change_password: 0,
    suspended: 0,
    roles: {},
};
let activeAudience = 'staff';
let activeRole = '';
let activeStatus = '';
let activeSearch = '';

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

const IDLE_DAYS = 30;

const ROLE_META = {
    super_admin: {
        label: 'Super Admin',
        short: 'Super Admin',
        icon: 'fas fa-shield-halved',
        tone: 'navy',
        hint: 'Full admin access',
        duty: 'Full console access: users, content, publishing, analytics, and system settings.',
    },
    publisher: {
        label: 'Publisher',
        short: 'Publisher',
        icon: 'fas fa-bullhorn',
        tone: 'blue',
        hint: 'Publish & campaigns',
        duty: 'Publish content, manage campaigns and notifications, and review ready-to-go material.',
    },
    editor: {
        label: 'Editor',
        short: 'Editor',
        icon: 'fas fa-pen-to-square',
        tone: 'slate',
        hint: 'Content editing',
        duty: 'Create and edit posts, materials, and supporting content before publication.',
    },
    analytics_viewer: {
        label: 'Analytics Viewer',
        short: 'Analytics',
        icon: 'fas fa-chart-column',
        tone: 'gold',
        hint: 'Reports only',
        duty: 'View dashboards and reports only — no content or user administration.',
    },
    app_user: {
        label: 'App User',
        short: 'App User',
        icon: 'fas fa-mobile-screen-button',
        tone: 'muted',
        hint: 'Mobile app account',
        duty: 'Mobile app account with access to DAPE-MA public features only.',
    },
};

export function initUsersModule() {
    const tableElement = document.getElementById('users-table');

    if (!tableElement) {
        return;
    }

    bindUsersFilters();
    bindUsersMetrics();

    if (usersTable && usersTableMode === getUsersTableMode()) {
        loadUsers();
        return;
    }

    initializeUsersTable(tableElement).then(() => {
        bindAdminActionTooltipSuppression(tableElement);
        bindUserTableActions(tableElement);
        bindUsersSearch();
        loadUsers();
    });

    bindUsersViewportListener(tableElement);
}

export function loadUsers() {
    if (!usersTable) {
        return;
    }

    const requestId = ++loadUsersRequestId;
    const params = {
        per_page: 100,
        audience: activeAudience || undefined,
        role: activeRole || undefined,
        status: activeStatus || undefined,
        q: activeSearch || undefined,
    };

    axios
        .get('/admin/users', { params })
        .then((response) => {
            if (requestId !== loadUsersRequestId) {
                return;
            }

            const users = Array.isArray(response.data.data?.data)
                ? response.data.data.data
                : [];
            cachedSummary = {
                staff: 0,
                app: 0,
                never_logged_in: 0,
                must_change_password: 0,
                suspended: 0,
                roles: {},
                ...(response.data.summary ?? {}),
            };

            updateFilterCounts();
            renderMetrics();
            renderUsers(users);
        })
        .catch((error) => {
            if (requestId !== loadUsersRequestId) {
                return;
            }

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
    const statusRoot = document.getElementById('users-status-tabs');

    if (!audienceRoot || !roleRoot || !statusRoot) {
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
        syncMetricPressedState();
        loadUsers();
    });

    renderAdminFilterTabs(statusRoot, [
        {
            value: '',
            label: 'All',
            icon: 'fas fa-list',
            hint: 'Any status',
            count: 0,
        },
        {
            value: 'active',
            label: 'Active',
            icon: 'fas fa-circle-check',
            hint: 'Can sign in',
            count: 0,
        },
        {
            value: 'suspended',
            label: 'Suspended',
            icon: 'fas fa-ban',
            hint: 'Access blocked',
            count: 0,
        },
    ]);
    setAdminFilterTab(statusRoot, activeStatus);
    bindAdminFilterTabs(statusRoot, (value) => {
        activeStatus = value ?? '';
        syncMetricPressedState();
        loadUsers();
    });

    renderRoleTabs();
}

function renderRoleTabs() {
    const roleRoot = document.getElementById('users-role-tabs');
    if (!roleRoot) {
        return;
    }

    const roleTitle = document.querySelector('[data-users-role-title]');

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
        loadUsers();
    });
    updateFilterCounts();
}

function updateFilterCounts() {
    const audienceRoot = document.getElementById('users-audience-tabs');
    const roleRoot = document.getElementById('users-role-tabs');
    const statusRoot = document.getElementById('users-status-tabs');
    const roles = cachedSummary.roles ?? {};
    const staffTotal = Number(cachedSummary.staff ?? 0);
    const appTotal = Number(cachedSummary.app ?? 0);
    const suspended = Number(cachedSummary.suspended ?? 0);
    const scopedTotal = activeAudience === 'app' ? appTotal : staffTotal;

    applyAdminFilterTabCounts(audienceRoot, {
        staff: staffTotal,
        app: appTotal,
    });

    applyAdminFilterTabCounts(roleRoot, {
        '': scopedTotal,
        ...Object.fromEntries(
            STAFF_ROLES.map((role) => [role, Number(roles[role] ?? 0)]),
        ),
    });

    applyAdminFilterTabCounts(statusRoot, {
        '': scopedTotal,
        active: Math.max(scopedTotal - suspended, 0),
        suspended,
    });
}

function bindUsersMetrics() {
    if (usersMetricsBound) {
        return;
    }

    const root = document.getElementById('users-metrics');
    if (!root) {
        return;
    }

    usersMetricsBound = true;

    root.addEventListener('click', (event) => {
        const card = event.target.closest('[data-users-metric]');
        if (!card) {
            return;
        }

        const metric = card.getAttribute('data-users-metric');
        applyMetricFilter(metric);
    });
}

function applyMetricFilter(metric) {
    if (metric === 'staff') {
        activeAudience = 'staff';
        activeRole = '';
        activeStatus = '';
    } else if (metric === 'app') {
        activeAudience = 'app';
        activeRole = '';
        activeStatus = '';
    } else if (metric === 'never') {
        activeStatus = activeStatus === 'never' ? '' : 'never';
    } else if (metric === 'password_due') {
        activeStatus = activeStatus === 'password_due' ? '' : 'password_due';
    } else if (metric === 'suspended') {
        activeStatus = activeStatus === 'suspended' ? '' : 'suspended';
    }

    const audienceRoot = document.getElementById('users-audience-tabs');
    const statusRoot = document.getElementById('users-status-tabs');
    setAdminFilterTab(audienceRoot, activeAudience);
    setAdminFilterTab(statusRoot, activeStatus);
    renderRoleTabs();
    syncMetricPressedState();
    loadUsers();
}

function renderMetrics() {
    const map = {
        staff: cachedSummary.staff,
        app: cachedSummary.app,
        never_logged_in: cachedSummary.never_logged_in,
        must_change_password: cachedSummary.must_change_password,
        suspended: cachedSummary.suspended,
    };

    Object.entries(map).forEach(([key, value]) => {
        const el = document.querySelector(`[data-users-metric-value="${key}"]`);
        if (el) {
            el.textContent = Number(value ?? 0).toLocaleString();
        }
    });

    syncMetricPressedState();
}

function syncMetricPressedState() {
    document.querySelectorAll('[data-users-metric]').forEach((card) => {
        const metric = card.getAttribute('data-users-metric');
        let pressed = false;

        if (metric === 'staff') {
            pressed = activeAudience === 'staff' && !activeStatus && !activeRole;
        } else if (metric === 'app') {
            pressed = activeAudience === 'app' && !activeStatus;
        } else if (metric === 'never') {
            pressed = activeStatus === 'never';
        } else if (metric === 'password_due') {
            pressed = activeStatus === 'password_due';
        } else if (metric === 'suspended') {
            pressed = activeStatus === 'suspended';
        }

        card.classList.toggle('is-active', pressed);
        card.setAttribute('aria-pressed', pressed ? 'true' : 'false');
    });
}

function renderUsers(users) {
    if (!usersTable) {
        return;
    }

    usersTable.clear();
    users.forEach((user) => {
        usersTable.row.add(buildUserRowData(user));
    });
    usersTable.draw();
}

function isStaffRole(role) {
    return STAFF_ROLES.includes(String(role || ''));
}

function bindUsersSearch() {
    if (usersSearchBound || !usersTable) {
        return;
    }

    const container = usersTable.table().container();
    const input = container?.querySelector('.dt-search input');
    if (!input) {
        return;
    }

    usersSearchBound = true;
    input.value = activeSearch;

    input.addEventListener('input', () => {
        window.clearTimeout(searchDebounceTimer);
        searchDebounceTimer = window.setTimeout(() => {
            activeSearch = String(input.value || '').trim();
            loadUsers();
        }, 320);
    });
}

function bindUserTableActions(tableElement) {
    if (usersActionsBound || !tableElement) {
        return;
    }

    usersActionsBound = true;

    const handleClick = (event) => {
        const viewButton = event.target.closest('[data-user-view-button]');
        if (viewButton) {
            event.preventDefault();
            viewUser(viewButton);
            return;
        }

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
            return;
        }

        const forcePasswordButton = event.target.closest('[data-user-force-password-button]');
        if (forcePasswordButton) {
            event.preventDefault();
            forcePasswordChange(forcePasswordButton);
            return;
        }

        const suspendButton = event.target.closest('[data-user-suspend-button]');
        if (suspendButton) {
            event.preventDefault();
            suspendUser(suspendButton);
            return;
        }

        const activateButton = event.target.closest('[data-user-activate-button]');
        if (activateButton) {
            event.preventDefault();
            activateUser(activateButton);
        }
    };

    tableElement.addEventListener('click', handleClick);
    document.addEventListener('click', (event) => {
        const overflowItem = event.target.closest(
            [
                '.admin-action-overflow-menu [data-user-view-button]',
                '.admin-action-overflow-menu [data-user-delete-button]',
                '.admin-action-overflow-menu [data-user-edit-button]',
                '.admin-action-overflow-menu [data-user-role-button]',
                '.admin-action-overflow-menu [data-user-force-password-button]',
                '.admin-action-overflow-menu [data-user-suspend-button]',
                '.admin-action-overflow-menu [data-user-activate-button]',
            ].join(', '),
        );
        if (overflowItem) {
            handleClick(event);
        }
    });
}

function userActionAttrs(user) {
    return [
        `data-user-id="${user.id}"`,
        `data-user-name="${escapeHtml(user.name)}"`,
        `data-user-email="${escapeHtml(user.email)}"`,
        `data-user-role="${escapeHtml(user.role)}"`,
        `data-user-active="${user.is_active === false ? '0' : '1'}"`,
        `data-user-must-change="${user.must_change_password ? '1' : '0'}"`,
        `data-user-last-login="${escapeHtml(user.last_login_at ?? '')}"`,
        `data-user-avatar="${escapeHtml(user.profile_image_url ?? '')}"`,
    ].join(' ');
}

function findUserFromButton(button) {
    return {
        id: Number(button.getAttribute('data-user-id') || button.getAttribute('data-user-view-button') || button.getAttribute('data-user-edit-button') || button.getAttribute('data-user-delete-button') || button.getAttribute('data-user-role-button') || button.getAttribute('data-user-force-password-button') || button.getAttribute('data-user-suspend-button') || button.getAttribute('data-user-activate-button')),
        name: button.getAttribute('data-user-name') || '',
        email: button.getAttribute('data-user-email') || '',
        role: button.getAttribute('data-user-role') || '',
        is_active: button.getAttribute('data-user-active') !== '0',
        must_change_password: button.getAttribute('data-user-must-change') === '1',
        last_login_at: button.getAttribute('data-user-last-login') || null,
        profile_image_url: button.getAttribute('data-user-avatar') || null,
    };
}

async function viewUser(button) {
    const user = findUserFromButton(button);
    const status = resolveAccountStatus(user);
    const meta = ROLE_META[user.role] ?? {
        label: formatRole(user.role),
        duty: 'Account role duties are not defined.',
        tone: 'muted',
    };
    const isCurrentUser = Number(getStoredUser()?.id) === Number(user.id);
    const lastLoginAbsolute = formatLastLoginAbsolute(user.last_login_at);
    const lastLoginRelative = formatLastLoginRelative(user.last_login_at);

    const result = await Swal.fire(buildSwalOptions({
        title: 'Account details',
        html: `
            <div class="users-account-drawer">
                <div class="users-account-drawer-hero">
                    ${renderUserAvatar(user)}
                    <div class="min-w-0">
                        <p class="users-account-drawer-name">${escapeHtml(user.name)}</p>
                        <p class="users-account-drawer-email">${escapeHtml(user.email)}</p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            ${badge(user.role)}
                            ${statusPill(status)}
                        </div>
                    </div>
                </div>
                <p class="users-account-drawer-duty">${escapeHtml(meta.duty)}</p>
                <dl class="users-account-drawer-meta">
                    <div><dt>Last login</dt><dd title="${escapeHtml(lastLoginAbsolute)}">${escapeHtml(lastLoginRelative)}</dd></div>
                    <div><dt>Password</dt><dd>${user.must_change_password ? 'Must change on next sign-in' : 'Current'}</dd></div>
                    <div><dt>Access</dt><dd>${user.is_active === false ? 'Suspended' : 'Enabled'}</dd></div>
                </dl>
                ${isCurrentUser ? '<p class="users-account-drawer-note">This is your signed-in account. Management actions are limited.</p>' : `
                <div class="users-account-drawer-actions">
                    <button type="button" class="admin-secondary-button" data-drawer-action="edit">Edit</button>
                    <button type="button" class="admin-secondary-button" data-drawer-action="role">Change role</button>
                    <button type="button" class="admin-secondary-button" data-drawer-action="force-password">Force password</button>
                    <button type="button" class="admin-secondary-button ${user.is_active === false ? '' : 'admin-table-action-danger'}" data-drawer-action="${user.is_active === false ? 'activate' : 'suspend'}">
                        ${user.is_active === false ? 'Enable' : 'Suspend'}
                    </button>
                    <button type="button" class="admin-secondary-button admin-table-action-danger" data-drawer-action="delete">Delete</button>
                </div>`}
            </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        didOpen: (popup) => {
            popup.querySelectorAll('[data-drawer-action]').forEach((actionButton) => {
                actionButton.addEventListener('click', async () => {
                    const action = actionButton.getAttribute('data-drawer-action');
                    Swal.close();
                    const proxy = document.createElement('button');
                    proxy.setAttribute('data-user-id', String(user.id));
                    Object.entries({
                        'data-user-name': user.name,
                        'data-user-email': user.email,
                        'data-user-role': user.role,
                        'data-user-active': user.is_active === false ? '0' : '1',
                        'data-user-must-change': user.must_change_password ? '1' : '0',
                        'data-user-last-login': user.last_login_at || '',
                        'data-user-avatar': user.profile_image_url || '',
                    }).forEach(([key, value]) => proxy.setAttribute(key, value));

                    if (action === 'edit') {
                        proxy.setAttribute('data-user-edit-button', String(user.id));
                        await editUser(proxy);
                    } else if (action === 'role') {
                        proxy.setAttribute('data-user-role-button', String(user.id));
                        await changeUserRole(proxy);
                    } else if (action === 'force-password') {
                        proxy.setAttribute('data-user-force-password-button', String(user.id));
                        await forcePasswordChange(proxy);
                    } else if (action === 'suspend') {
                        proxy.setAttribute('data-user-suspend-button', String(user.id));
                        await suspendUser(proxy);
                    } else if (action === 'activate') {
                        proxy.setAttribute('data-user-activate-button', String(user.id));
                        await activateUser(proxy);
                    } else if (action === 'delete') {
                        proxy.setAttribute('data-user-delete-button', String(user.id));
                        await deleteUser(proxy);
                    }
                });
            });
        },
    }, { size: 'lg' }));

    void result;
}

async function deleteUser(button) {
    const user = findUserFromButton(button);

    const confirmed = await confirmWithSwal({
        title: `Delete ${user.name}?`,
        text: 'This permanently removes the account.',
        confirmButtonText: 'Delete User',
        icon: 'warning',
        danger: true,
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const response = await axios.delete(`/admin/users/${user.id}`);
        showSuccessToast(response.data.message ?? 'User deleted successfully.', 'User deleted');
        loadUsers();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Please try again.',
            'Unable to delete user',
        );
    }
}

async function forcePasswordChange(button) {
    const user = findUserFromButton(button);

    const confirmed = await confirmWithSwal({
        title: `Force password change for ${user.name}?`,
        text: 'They must set a new password before continuing in the app or console.',
        confirmButtonText: 'Force change',
        icon: 'question',
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const response = await axios.post(`/admin/users/${user.id}/force-password`);
        showSuccessToast(response.data.message ?? 'Password change required.', 'Password flagged');
        loadUsers();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Please try again.',
            'Unable to force password change',
        );
    }
}

async function suspendUser(button) {
    const user = findUserFromButton(button);

    const confirmed = await confirmWithSwal({
        title: `Suspend ${user.name}?`,
        text: 'They will be signed out and cannot log in until enabled again.',
        confirmButtonText: 'Suspend account',
        icon: 'warning',
        danger: true,
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const response = await axios.post(`/admin/users/${user.id}/suspend`);
        showSuccessToast(response.data.message ?? 'User suspended.', 'Account suspended');
        loadUsers();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Please try again.',
            'Unable to suspend user',
        );
    }
}

async function activateUser(button) {
    const user = findUserFromButton(button);

    const confirmed = await confirmWithSwal({
        title: `Enable ${user.name}?`,
        text: 'They will be able to sign in again.',
        confirmButtonText: 'Enable account',
        icon: 'question',
    });

    if (!confirmed.isConfirmed) {
        return;
    }

    try {
        const response = await axios.post(`/admin/users/${user.id}/activate`);
        showSuccessToast(response.data.message ?? 'User enabled.', 'Account enabled');
        loadUsers();
    } catch (error) {
        showErrorToast(
            error.response?.data?.message ?? 'Please try again.',
            'Unable to enable user',
        );
    }
}

async function editUser(button) {
    const user = findUserFromButton(button);

    const result = await Swal.fire(buildSwalOptions({
        title: `Edit User: ${user.name}`,
        html: buildSwalForm({
            description: 'Update core user details and access level.',
            fields: [
                {
                    id: 'user-name',
                    label: 'Full Name *',
                    value: user.name,
                    placeholder: 'Administrator name',
                },
                {
                    id: 'user-email',
                    label: 'Email Address *',
                    value: user.email,
                    type: 'email',
                    placeholder: 'admin@example.gov.ph',
                },
                {
                    id: 'user-role',
                    label: 'Role *',
                    type: 'select',
                    value: user.role,
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
                const response = await axios.put(`/admin/users/${user.id}`, payload);
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
    const attrs = userActionAttrs(user);

    if (isCurrentUser) {
        return buildAdminActionButtons([
            {
                tooltip: 'View Account',
                icon: 'fas fa-eye',
                className: 'admin-table-action-primary',
                attrs: `data-user-view-button="${user.id}" ${attrs}`,
                label: 'View Account',
            },
        ], { isMobile, nowrap: true, overflowAfter: 2 });
    }

    const isActive = user.is_active !== false;

    return buildAdminActionButtons([
        {
            tooltip: 'View Account',
            icon: 'fas fa-eye',
            className: 'admin-table-action-primary',
            attrs: `data-user-view-button="${user.id}" ${attrs}`,
            label: 'View Account',
        },
        {
            tooltip: 'Edit User',
            icon: 'fas fa-user-pen',
            attrs: `data-user-edit-button="${user.id}" ${attrs}`,
            label: 'Edit User',
        },
        {
            tooltip: 'Change Role',
            icon: 'fas fa-user-gear',
            attrs: `data-user-role-button="${user.id}" ${attrs}`,
            label: 'Change Role',
        },
        {
            tooltip: 'Force password change',
            icon: 'fas fa-key',
            attrs: `data-user-force-password-button="${user.id}" ${attrs}`,
            label: 'Force password',
        },
        isActive
            ? {
                tooltip: 'Suspend account',
                icon: 'fas fa-ban',
                className: 'admin-table-action-danger',
                attrs: `data-user-suspend-button="${user.id}" ${attrs}`,
                label: 'Suspend',
            }
            : {
                tooltip: 'Enable account',
                icon: 'fas fa-circle-check',
                attrs: `data-user-activate-button="${user.id}" ${attrs}`,
                label: 'Enable',
            },
        {
            tooltip: 'Delete User',
            icon: 'fas fa-trash',
            className: 'admin-table-action-danger',
            attrs: `data-user-delete-button="${user.id}" ${attrs}`,
            label: 'Delete User',
        },
    ], { isMobile, nowrap: true, overflowAfter: 2 });
}

async function changeUserRole(button) {
    const user = findUserFromButton(button);

    const result = await Swal.fire(buildSwalOptions({
        title: `Update Role: ${user.name}`,
        html: buildSwalForm({
            description: 'Choose the appropriate access level for this user account.',
            fields: [
                {
                    id: 'user-role',
                    label: 'Role *',
                    type: 'select',
                    value: user.role,
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
        const response = await axios.put(`/admin/users/${user.id}/role`, {
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

function resolveAccountStatus(user) {
    if (user.is_active === false) {
        return { key: 'suspended', label: 'Suspended', tone: 'danger' };
    }

    if (user.must_change_password) {
        return { key: 'password_due', label: 'Password due', tone: 'gold' };
    }

    if (!user.last_login_at) {
        return { key: 'never', label: 'Never', tone: 'muted' };
    }

    const last = parseDate(user.last_login_at);
    if (last && ((Date.now() - last.getTime()) / 86400000) > IDLE_DAYS) {
        return { key: 'idle', label: 'Idle', tone: 'slate' };
    }

    return { key: 'active', label: 'Active', tone: 'success' };
}

function statusPill(status) {
    return `<span class="admin-user-status-pill is-${escapeHtml(status.tone)}">${escapeHtml(status.label)}</span>`;
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
        usersSearchBound = false;
    }

    usersTableMode = nextMode;
    usersTable = await createAdminDataTable(tableElement, {
        ...getAdminDataTableOptions({
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
                    { title: 'Status', className: 'dt-col-nowrap' },
                    { title: 'Last Login', className: 'dt-col-nowrap' },
                    { title: 'Actions', orderable: false, className: 'dt-col-actions' },
                ],
        }),
        createdRow: (row) => {
            if (row.querySelector('.admin-user-status-pill.is-danger')) {
                row.classList.add('users-row-suspended');
            }
        },
    });
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
        bindUsersSearch();
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
    const status = resolveAccountStatus(user);
    const lastLoginAbsolute = formatLastLoginAbsolute(user.last_login_at);
    const lastLoginRelative = formatLastLoginRelative(user.last_login_at);
    const lastLoginHtml = user.last_login_at
        ? `<span class="admin-user-login" title="${escapeHtml(lastLoginAbsolute)}">${escapeHtml(lastLoginRelative)}</span>`
        : '<span class="admin-user-login-muted">Never</span>';

    if (usersTableMode === 'mobile') {
        return [
            `<div class="admin-table-mobile-card ${user.is_active === false ? 'is-suspended' : ''}">
                <div class="admin-table-mobile-title-row">
                    <div class="flex min-w-0 items-center gap-3">
                        ${renderUserAvatar(user)}
                        <div class="min-w-0">
                            <p class="admin-table-mobile-kicker">User #${user.id}</p>
                            <p class="admin-table-mobile-title">${escapeHtml(user.name)}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-1">
                        ${badge(user.role)}
                        ${statusPill(status)}
                    </div>
                </div>
                <div class="admin-table-mobile-details">
                    <p><span>Email:</span> ${escapeHtml(user.email)}</p>
                    <p><span>Last login:</span> ${lastLoginHtml}</p>
                </div>
            </div>`,
            renderUserActions(user),
        ];
    }

    return [
        user.id,
        renderUserIdentity(user),
        badge(user.role),
        statusPill(status),
        lastLoginHtml,
        renderUserActions(user),
    ];
}

function parseDate(value) {
    if (!value) {
        return null;
    }

    const normalized = typeof value === 'string' && value.includes(' ')
        ? value.replace(' ', 'T')
        : value;
    const date = new Date(normalized);

    return Number.isNaN(date.getTime()) ? null : date;
}

function formatLastLoginAbsolute(value) {
    const date = parseDate(value);
    if (!date) {
        return value ? String(value) : 'Never';
    }

    return date.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function formatLastLoginRelative(value) {
    const date = parseDate(value);
    if (!date) {
        return 'Never';
    }

    const seconds = Math.round((Date.now() - date.getTime()) / 1000);
    if (seconds < 60) {
        return 'Just now';
    }

    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min ago`;
    }

    const hours = Math.round(minutes / 60);
    if (hours < 24) {
        return `${hours} hour${hours === 1 ? '' : 's'} ago`;
    }

    const days = Math.round(hours / 24);
    if (days < 30) {
        return `${days} day${days === 1 ? '' : 's'} ago`;
    }

    const months = Math.round(days / 30);
    if (months < 12) {
        return `${months} month${months === 1 ? '' : 's'} ago`;
    }

    const years = Math.round(months / 12);
    return `${years} year${years === 1 ? '' : 's'} ago`;
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
