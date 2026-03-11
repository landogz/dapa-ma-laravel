import Swal from 'sweetalert2';
import { initAnalyticsModule } from './analytics';
import { logout } from './auth';
import { initNotificationsModule } from './notifications';
import { initPostsModule } from './posts';
import { initRehabCentersModule } from './rehab-centers';
import { initUsersModule } from './users';

const ADMIN_ROLE_SECTIONS = {
    super_admin: ['posts', 'rehab-centers', 'notifications', 'analytics', 'users'],
    editor: ['posts', 'rehab-centers'],
    publisher: ['posts', 'rehab-centers', 'notifications'],
    analytics_viewer: ['rehab-centers', 'analytics'],
};

const PAGE_SECTION_REQUIREMENTS = {
    'admin-posts': 'posts',
    'admin-rehab-centers': 'rehab-centers',
    'admin-notifications': 'notifications',
    'admin-analytics': 'analytics',
    'admin-users': 'users',
};

const SECTION_PATHS = {
    posts: '/admin/posts',
    'rehab-centers': '/admin/rehab-centers',
    notifications: '/admin/notifications',
    analytics: '/admin/analytics',
    users: '/admin/users',
};

let inboxFilter = 'unread';

export async function initAdminPage(pageName) {
    const appRoot = document.getElementById('admin-app');

    if (!appRoot) {
        return;
    }

    bindShell(appRoot);

    const profile = await loadCurrentUser();

    if (!profile) {
        return;
    }

    renderProfile(profile);

    const allowedSections = ADMIN_ROLE_SECTIONS[profile.role] ?? [];

    revealNavigation(allowedSections);
    revealSidebarNavigation();

    await refreshInboxBadge();

    const hasAccess = await guardPageAccess(pageName, allowedSections);

    if (!hasAccess) {
        return;
    }

    bindQuickActions();
    initializePageContent(pageName, allowedSections);
}

function bindShell(appRoot) {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const logoutButton = document.getElementById('logout-button');
    const sidebarBackdrop = document.getElementById('admin-sidebar-backdrop');
    const sidebar = document.getElementById('admin-sidebar');
    const sidebarLinks = document.querySelectorAll('#admin-sidebar a');

    const isDesktopViewport = () => window.matchMedia('(min-width: 768px)').matches;

    const setSidebarOpen = (isOpen) => {
        const shouldOpen = isOpen && !isDesktopViewport();

        appRoot.classList.toggle('sidebar-open', shouldOpen);
        sidebar?.classList.toggle('-translate-x-full', !shouldOpen);
        sidebarBackdrop?.classList.toggle('hidden', !shouldOpen);
        document.body.classList.toggle('overflow-hidden', shouldOpen);
        sidebarToggle?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        sidebarToggle?.setAttribute('aria-label', shouldOpen ? 'Close navigation menu' : 'Open navigation menu');
        sidebar?.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
    };

    setSidebarOpen(false);

    sidebarToggle?.addEventListener('click', () => {
        const isOpen = appRoot.classList.contains('sidebar-open');

        setSidebarOpen(!isOpen);
    });

    sidebarBackdrop?.addEventListener('click', () => {
        setSidebarOpen(false);
    });

    logoutButton?.addEventListener('click', logout);

    sidebarLinks.forEach((link) => {
        link.addEventListener('click', () => {
            setSidebarOpen(false);
        });
    });

    window.addEventListener('resize', () => {
        if (isDesktopViewport()) {
            setSidebarOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
        }
    });

    bindNotificationsBell();
}

async function loadCurrentUser() {
    try {
        const response = await window.axios.get('/auth/me');

        return response.data.data;
    } catch {
        logout();

        return null;
    }
}

function renderProfile(profile) {
    const nameElements = document.querySelectorAll('[data-admin-name]');
    const roleElements = document.querySelectorAll('[data-admin-role]');
    const initialElements = document.querySelectorAll('[data-admin-initials]');

    nameElements.forEach((element) => {
        element.textContent = profile.name;
    });

    roleElements.forEach((element) => {
        element.textContent = formatRole(profile.role);
    });

    initialElements.forEach((element) => {
        element.textContent = buildInitials(profile.name);
    });
}

function revealNavigation(allowedSections) {
    document.querySelectorAll('[data-nav-section]').forEach((link) => {
        const sectionName = link.getAttribute('data-nav-section');
        const canView = sectionName && allowedSections.includes(sectionName);

        link.classList.toggle('hidden', !canView);
    });

    document.querySelectorAll('[data-card-section]').forEach((card) => {
        const sectionName = card.getAttribute('data-card-section');
        const canView = sectionName && allowedSections.includes(sectionName);

        card.classList.toggle('hidden', !canView);
    });

    document.querySelectorAll('[data-overview-analytics]').forEach((panel) => {
        panel.classList.toggle('hidden', !allowedSections.includes('analytics'));
    });
}

function revealSidebarNavigation() {
    const navigation = document.querySelector('[data-admin-nav]');

    if (!navigation) {
        return;
    }

    navigation.classList.remove('opacity-0', 'pointer-events-none');
    navigation.setAttribute('aria-hidden', 'false');
}

function bindNotificationsBell() {
    const button = document.querySelector('[data-admin-notifications-button]');
    const panel = document.getElementById('admin-notifications-panel');

    if (!button || !panel) {
        return;
    }

    let isOpen = false;

    const closePanel = () => {
        panel.classList.add('hidden');
        isOpen = false;
    };

    const openPanel = async () => {
        await loadAdminInbox(panel);
        panel.classList.remove('hidden');
        isOpen = true;
    };

    button.addEventListener('click', (event) => {
        event.stopPropagation();

        if (isOpen) {
            closePanel();
        } else {
            openPanel();
        }
    });

    document.addEventListener('click', (event) => {
        if (!isOpen) {
            return;
        }

        if (!panel.contains(event.target) && !button.contains(event.target)) {
            closePanel();
        }
    });
}

async function guardPageAccess(pageName, allowedSections) {
    const requiredSection = PAGE_SECTION_REQUIREMENTS[pageName];

    if (!requiredSection || allowedSections.includes(requiredSection)) {
        return true;
    }

    await Swal.fire({
        icon: 'warning',
        title: 'Access restricted',
        text: 'You do not have permission to view this page.',
        confirmButtonColor: '#055498',
    });

    window.location.href = getFallbackPath(allowedSections);

    return false;
}

function initializePageContent(pageName, allowedSections) {
    switch (pageName) {
        case 'admin-dashboard':
            loadOverviewCounts(allowedSections);

            if (allowedSections.includes('analytics')) {
                initAnalyticsModule();
            }
            break;
        case 'admin-posts':
            initPostsModule();
            break;
        case 'admin-rehab-centers':
            initRehabCentersModule();
            break;
        case 'admin-notifications':
            initNotificationsModule();
            break;
        case 'admin-analytics':
            initAnalyticsModule();
            break;
        case 'admin-users':
            initUsersModule();
            break;
        default:
            break;
    }
}

function bindQuickActions() {
    document.querySelector('[data-admin-action="create-post"]')?.addEventListener('click', () => {
        window.Posts?.createDraftPrompt();
    });

    document.querySelector('[data-admin-action="create-rehab-center"]')?.addEventListener('click', () => {
        window.RehabCenters?.create();
    });

    document.querySelector('[data-admin-action="send-notification"]')?.addEventListener('click', () => {
        window.Notifications?.send();
    });

    document.querySelector('[data-admin-action="export-analytics"]')?.addEventListener('click', () => {
        window.Analytics?.exportCsvWithRange?.();
    });

    document.querySelector('[data-admin-action="create-user"]')?.addEventListener('click', () => {
        window.Users?.create();
    });
}

async function loadOverviewCounts(allowedSections) {
    const storageKey = 'dape-admin-overview-totals';
    let previousTotals = {};

    try {
        const raw = window.localStorage.getItem(storageKey);

        if (raw) {
            previousTotals = JSON.parse(raw);
        }
    } catch {
        previousTotals = {};
    }

    const requests = [
        {
            key: 'posts',
            allowed: allowedSections.includes('posts'),
            request: () => window.axios.get('/admin/posts', { params: { per_page: 1 } }),
            count: (response) => response.data.data?.total,
        },
        {
            key: 'rehab-centers',
            allowed: allowedSections.includes('rehab-centers'),
            request: () => window.axios.get('/admin/rehab-centers', { params: { per_page: 1 } }),
            count: (response) => response.data.data?.total,
        },
        {
            key: 'notifications',
            allowed: allowedSections.includes('notifications'),
            request: () => window.axios.get('/admin/notifications', { params: { per_page: 1 } }),
            count: (response) => response.data.data?.total,
        },
        {
            key: 'users',
            allowed: allowedSections.includes('users'),
            request: () => window.axios.get('/admin/users', { params: { per_page: 1 } }),
            count: (response) => response.data.data?.total,
        },
    ];

    await Promise.all(requests.map(async (item) => {
        const elements = document.querySelectorAll(`[data-overview-count="${item.key}"]`);

        if (!item.allowed) {
            elements.forEach((element) => {
                element.textContent = '--';
            });

            return;
        }

        try {
            const response = await item.request();
            const total = item.count(response) ?? 0;

            elements.forEach((element) => {
                element.textContent = Number(total).toLocaleString();
            });

            if (item.key === 'notifications') {
                updateNotificationBadge(total);
            }

            updateTrend(item.key, total, previousTotals[item.key]);
            previousTotals[item.key] = total;
        } catch {
            elements.forEach((element) => {
                element.textContent = '--';
            });
        }
    }));

    try {
        window.localStorage.setItem(storageKey, JSON.stringify(previousTotals));
    } catch {
        // ignore storage errors
    }
}

function getFallbackPath(allowedSections) {
    const firstAvailablePath = allowedSections
        .map((section) => SECTION_PATHS[section])
        .find(Boolean);

    return firstAvailablePath ?? '/admin/dashboard';
}

function formatRole(role) {
    return role
        .split('_')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

function updateTrend(key, total, previous) {
    const elements = document.querySelectorAll(`[data-overview-trend="${key}"]`);

    if (!elements.length) {
        return;
    }

    const previousValue = typeof previous === 'number' ? previous : total;
    const delta = Number(total) - Number(previousValue);

    elements.forEach((element) => {
        element.classList.remove('admin-trend-up', 'admin-trend-down', 'admin-trend-neutral');

        if (delta > 0) {
            element.classList.add('admin-trend', 'admin-trend-up');
            element.textContent = `↑ ${delta} since last check`;
        } else if (delta < 0) {
            element.classList.add('admin-trend', 'admin-trend-down');
            element.textContent = `↓ ${Math.abs(delta)} since last check`;
        } else {
            element.classList.add('admin-trend', 'admin-trend-neutral');
            element.textContent = 'No change since last check';
        }
    });
}

function updateNotificationBadge(total) {
    const dot = document.querySelector('[data-admin-notifications-dot]');

    if (!dot) {
        return;
    }

    if (Number(total) > 0) {
        dot.classList.remove('hidden');
    } else {
        dot.classList.add('hidden');
    }
}

async function refreshInboxBadge() {
    const dot = document.querySelector('[data-admin-notifications-dot]');

    if (!dot) {
        return;
    }

    try {
        const { data } = await window.axios.get('/admin/inbox/summary');
        const unread = Number(data.data?.unread_count ?? 0);

        if (unread > 0) {
            dot.classList.remove('hidden');
        } else {
            dot.classList.add('hidden');
        }
    } catch {
        dot.classList.add('hidden');
    }
}

async function loadAdminInbox(panel) {
    try {
        const filter = inboxFilter || 'unread';
        const { data } = await window.axios.get('/admin/inbox', {
            params: {
                per_page: 10,
                unread_only: filter === 'unread' ? 1 : 0,
            },
        });
        const items = data.data?.data ?? [];

        if (!items.length) {
            panel.innerHTML = `
                <div class="admin-notifications-header">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Notifications</p>
                </div>
                <div class="admin-notifications-empty">
                    No ${filter === 'unread' ? 'unread ' : ''}notifications. Actions like post submissions, rejections, schedules, and role updates will appear here.
                </div>
            `;

            return;
        }

        panel.innerHTML = `
            <div class="admin-notifications-header">
                <div class="flex flex-col gap-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Notifications</p>
                    <div class="inline-flex overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-[11px]">
                        <button type="button"
                                class="px-3 py-0.5 ${filter === 'unread' ? 'bg-white text-[#055498] font-semibold' : 'text-slate-500'}"
                                data-admin-inbox-filter="unread">
                            Unread
                        </button>
                        <button type="button"
                                class="px-3 py-0.5 ${filter === 'all' ? 'bg-white text-[#055498] font-semibold' : 'text-slate-500'}"
                                data-admin-inbox-filter="all">
                            All
                        </button>
                    </div>
                </div>
                <button type="button" class="text-[11px] font-semibold text-[#055498] hover:text-[#123a60]" data-admin-inbox-mark-all>Mark all as read</button>
            </div>
            <div class="admin-notifications-items">
                ${items.map(renderInboxItem).join('')}
            </div>
        `;

        panel.querySelectorAll('[data-admin-inbox-filter]').forEach((button) => {
            button.addEventListener('click', async () => {
                inboxFilter = button.getAttribute('data-admin-inbox-filter') || 'unread';
                await loadAdminInbox(panel);
            });
        });

        panel.querySelector('[data-admin-inbox-mark-all]')?.addEventListener('click', async () => {
            try {
                await window.axios.post('/admin/inbox/read-all');
                await refreshInboxBadge();
                panel.classList.add('hidden');
            } catch {
                // ignore errors in badge update here
            }
        });

        panel.querySelectorAll('[data-admin-inbox-item]').forEach((row) => {
            row.addEventListener('click', async () => {
                const id = row.getAttribute('data-admin-inbox-item');
                const url = row.getAttribute('data-admin-inbox-url');

                try {
                    await window.axios.post(`/admin/inbox/${id}/read`);
                    await refreshInboxBadge();
                } catch {
                    // best-effort; do not block navigation
                }

                if (url) {
                    window.location.href = url;
                }
            });
        });
    } catch {
        panel.innerHTML = `
            <div class="admin-notifications-header">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Notifications</p>
            </div>
            <div class="admin-notifications-empty text-[#CE2028]">
                Failed to load notifications. Please try again.
            </div>
        `;
    }
}

function renderInboxItem(item) {
    const isUnread = !item.read_at;
    const url = item.data?.admin_url ?? '';
    const createdAt = item.created_at ?? '';
    const actor = item.data?.actor_name ?? '';
    const postTitle = item.data?.post_title ?? '';
    const type = String(item.type || '').toLowerCase();

    const metaPieces = [];

    if (actor) {
        metaPieces.push(actor);
    }

    if (postTitle) {
        metaPieces.push(`“${postTitle}”`);
    }

    const metaText = metaPieces.join(' • ');
    const timeLabel = formatInboxRelativeTime(createdAt);

    let typeLabel = 'Notification';
    let typeClass = 'bg-slate-100 text-slate-600';

    if (type === 'post_submitted') {
        typeLabel = 'Draft submitted';
        typeClass = 'bg-[#055498]/10 text-[#055498]';
    } else if (type === 'post_rejected') {
        typeLabel = 'Rejected';
        typeClass = 'bg-[#FBD116]/15 text-[#123a60]';
    } else if (type === 'post_scheduled') {
        typeLabel = 'Scheduled';
        typeClass = 'bg-emerald-100 text-emerald-700';
    } else if (type === 'user_created') {
        typeLabel = 'New account';
        typeClass = 'bg-sky-100 text-sky-700';
    } else if (type === 'user_role_changed') {
        typeLabel = 'Role updated';
        typeClass = 'bg-purple-100 text-purple-700';
    }

    return `
        <button type="button"
                class="admin-notifications-item ${isUnread ? 'admin-notifications-item-unread' : ''}"
                data-admin-inbox-item="${item.id}"
                data-admin-inbox-url="${url}">
            <span class="mt-1 inline-flex h-7 w-7 items-center justify-center rounded-full bg-[#055498]/10 text-[#055498]">
                <i class="fas fa-circle-exclamation text-[11px]"></i>
            </span>
            <div class="flex-1 text-left">
                <p class="admin-notifications-title">${item.title}</p>
                <div class="mt-0.5 flex flex-wrap items-center gap-1">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold ${typeClass}">${typeLabel}</span>
                    ${metaText ? `<span class="admin-notifications-meta">${metaText}</span>` : ''}
                </div>
                <p class="admin-notifications-meta">${timeLabel}</p>
            </div>
        </button>
    `;
}

function formatInboxRelativeTime(isoString) {
    if (!isoString) {
        return '';
    }

    const eventDate = new Date(isoString);
    const now = new Date();
    const diffMs = now.getTime() - eventDate.getTime();
    const diffSeconds = Math.round(diffMs / 1000);
    const diffMinutes = Math.round(diffSeconds / 60);
    const diffHours = Math.round(diffMinutes / 60);
    const diffDays = Math.round(diffHours / 24);

    if (diffSeconds < 45) {
        return 'Just now';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes} min${diffMinutes === 1 ? '' : 's'} ago`;
    }

    if (diffHours < 24) {
        return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
    }

    if (diffDays < 7) {
        return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
    }

    return eventDate.toLocaleDateString();
}

function buildInitials(name) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}
