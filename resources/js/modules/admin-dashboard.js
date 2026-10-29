import Swal from 'sweetalert2';
import { initAnalyticsModule } from './analytics';
import { initDiaryModule } from './diary';
import { logout } from './auth';
import { initNotificationsModule } from './notifications';
import { initPostsModule } from './posts';
import { initProfileModule } from './profile';
import { initRehabCentersModule } from './rehab-centers/index';
import { enableAdminSwalHeaders } from './shared/swal-forms';
import { startAdminFilterSelectWatcher } from './shared/filter-select';
import { initContestsModule } from './contests/index';
import { initIecMaterialsModule } from './iec-materials/index';
import { initAppTranslationsModule } from './app-translations/index';
import { initSettingsModule } from './settings/index';
import { initAppOptionsModule } from './app-options/index';
import { initCareToolkitModule } from './care-toolkit/index';
import { initCareChecksModule } from './care-checks/index';
import { initCareSupportModule } from './care-support/index';
import { initWordBlockingModule } from './word-blocking/index';
import { initHopeDirectoryModule } from './hope-directory/index';
import { initHopeEventsModule } from './hope-events/index';
import { initUsersModule } from './users';
import { initAdminMegaMenu } from './admin-mega-menu/index';

const ADMIN_ROLE_SECTIONS = {
    super_admin: ['posts', 'rehab-centers', 'contests', 'iec-materials', 'app-translations', 'notifications', 'analytics', 'users', 'diary', 'care-toolkit', 'care-checks', 'care-support', 'hope-directory', 'hope-events', 'settings', 'lookups', 'word-blocking'],
    editor: ['posts'],
    publisher: ['posts', 'rehab-centers', 'contests', 'iec-materials', 'app-translations', 'notifications', 'care-toolkit', 'care-checks', 'care-support', 'hope-directory', 'hope-events', 'settings', 'lookups', 'word-blocking'],
    analytics_viewer: ['analytics'],
};

const PAGE_SECTION_REQUIREMENTS = {
    'admin-posts': 'posts',
    'admin-rehab-centers': 'rehab-centers',
    'admin-contests': 'contests',
    'admin-iec-materials': 'iec-materials',
    'admin-app-translations': 'app-translations',
    'admin-notifications': 'notifications',
    'admin-analytics': 'analytics',
    'admin-users': 'users',
    'admin-diary': 'diary',
    'admin-care-toolkit': 'care-toolkit',
    'admin-care-checks': 'care-checks',
    'admin-care-support': 'care-support',
    'admin-hope-directory': 'hope-directory',
    'admin-hope-events': 'hope-events',
    'admin-settings': 'settings',
    'admin-lookups': 'lookups',
    'admin-word-blocking': 'word-blocking',
};

const SECTION_PATHS = {
    posts: '/admin/posts',
    'rehab-centers': '/admin/rehab-centers',
    'contests': '/admin/contests',
    'iec-materials': '/admin/iec-materials',
    'app-translations': '/admin/app-translations',
    notifications: '/admin/notifications',
    analytics: '/admin/analytics',
    users: '/admin/users',
    diary: '/admin/diary',
    'care-toolkit': '/admin/care-toolkit',
    'care-checks': '/admin/care-checks',
    'care-support': '/admin/care-support',
    'hope-directory': '/admin/hope-directory',
    'hope-events': '/admin/hope-events',
    settings: '/admin/settings',
    lookups: '/admin/lookups',
    'word-blocking': '/admin/word-blocking',
};

let inboxFilter = 'unread';

export async function initAdminPage(pageName) {
    const appRoot = document.getElementById('admin-app');

    if (!appRoot) {
        return;
    }

    enableAdminSwalHeaders();

    bindShell(appRoot);

    const profile = await loadCurrentUser();

    if (!profile) {
        return;
    }

    renderProfile(profile);

    if (profile.must_change_password && pageName !== 'admin-profile') {
        window.location.href = '/admin/profile?force_password=1';
        return;
    }

    const allowedSections = ADMIN_ROLE_SECTIONS[profile.role] ?? [];

    revealNavigation(allowedSections);
    revealSidebarNavigation();
    initAdminMegaMenu(allowedSections);

    await refreshInboxBadge();

    const hasAccess = await guardPageAccess(pageName, allowedSections);

    if (!hasAccess) {
        return;
    }

    bindQuickActions();
    initializePageContent(pageName, allowedSections, profile);
    startAdminFilterSelectWatcher(appRoot);
}

function bindShell(appRoot) {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const logoutButton = document.getElementById('logout-button');
    const sidebarBackdrop = document.getElementById('admin-sidebar-backdrop');
    const sidebar = document.getElementById('admin-sidebar');
    const sidebarLinks = document.querySelectorAll('#admin-sidebar a');
    const collapseToggle = document.getElementById('sidebar-collapse-toggle');
    const sidebarCollapsedKey = 'dape-admin-sidebar-collapsed';

    const isDesktopViewport = () => window.matchMedia('(min-width: 1024px)').matches;

    const applyCollapsed = (collapsed) => {
        appRoot.classList.toggle('sidebar-collapsed', Boolean(collapsed) && isDesktopViewport());
        const icon = collapseToggle?.querySelector('i');
        if (icon) {
            icon.className = collapsed ? 'fas fa-angles-right' : 'fas fa-angles-left';
        }
        collapseToggle?.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        collapseToggle?.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };

    try {
        applyCollapsed(window.localStorage.getItem(sidebarCollapsedKey) === '1');
    } catch {
        applyCollapsed(false);
    }

    collapseToggle?.addEventListener('click', () => {
        const next = !appRoot.classList.contains('sidebar-collapsed');
        applyCollapsed(next);
        try {
            window.localStorage.setItem(sidebarCollapsedKey, next ? '1' : '0');
        } catch {
            // ignore
        }
    });

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

    window.addEventListener('admin-profile-updated', (event) => {
        if (event.detail) {
            renderProfile(event.detail);
        }
    });

    window.addEventListener('resize', () => {
        if (isDesktopViewport()) {
            setSidebarOpen(false);
            try {
                applyCollapsed(window.localStorage.getItem(sidebarCollapsedKey) === '1');
            } catch {
                applyCollapsed(false);
            }
        } else {
            appRoot.classList.remove('sidebar-collapsed');
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            setSidebarOpen(false);
            closeAdminAccountMenu();
            window.AdminMegaMenu?.close();
        }
    });

    bindNotificationsBell();
    bindAccountMenu();
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
    const avatarImages = document.querySelectorAll('[data-admin-avatar-img]');
    const displayName = buildDisplayName(profile);

    nameElements.forEach((element) => {
        element.textContent = displayName;
    });

    roleElements.forEach((element) => {
        element.textContent = formatRole(profile.role);
    });

    if (profile.profile_image_url && avatarImages.length > 0) {
        avatarImages.forEach((image) => {
            image.src = profile.profile_image_url;
            image.classList.remove('hidden');
        });

        initialElements.forEach((element) => {
            element.classList.add('hidden');
        });

        return;
    }

    avatarImages.forEach((image) => {
        image.removeAttribute('src');
        image.classList.add('hidden');
    });

    initialElements.forEach((element) => {
        element.textContent = buildInitials(displayName);
        element.classList.remove('hidden');
    });
}

function buildDisplayName(profile) {
    const firstName = profile.first_name ?? '';
    const lastName = profile.last_name ?? '';
    const combined = `${firstName} ${lastName}`.trim();

    return combined || profile.name || '';
}

function revealNavigation(allowedSections) {
    document.querySelectorAll('[data-nav-section]').forEach((link) => {
        const sectionName = link.getAttribute('data-nav-section');
        const canView = sectionName && allowedSections.includes(sectionName);

        link.classList.toggle('hidden', !canView);
    });

    document.querySelectorAll('.admin-nav-group').forEach((group) => {
        const gatedLinks = group.querySelectorAll('[data-nav-section]');

        // Groups with no permission-gated links (e.g. Overview → Dashboard) always stay visible.
        if (gatedLinks.length === 0) {
            group.classList.remove('hidden');
            return;
        }

        const hasVisibleLink = Array.from(gatedLinks).some(
            (link) => !link.classList.contains('hidden'),
        );

        group.classList.toggle('hidden', !hasVisibleLink);
    });

    document.querySelectorAll('[data-card-section]').forEach((card) => {
        const sectionName = card.getAttribute('data-card-section');
        const canView = sectionName && allowedSections.includes(sectionName);

        card.classList.toggle('hidden', !canView);
    });

    document.querySelectorAll('[data-action-section]').forEach((action) => {
        const sectionName = action.getAttribute('data-action-section');
        const canView = sectionName && allowedSections.includes(sectionName);

        action.classList.toggle('hidden', !canView);
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

function closeAdminAccountMenu() {
    const button = document.getElementById('admin-account-menu-button');
    const menu = document.getElementById('admin-account-menu');

    menu?.classList.add('hidden');
    button?.setAttribute('aria-expanded', 'false');
}

function bindAccountMenu() {
    const root = document.querySelector('[data-admin-account-menu]');
    const button = document.getElementById('admin-account-menu-button');
    const menu = document.getElementById('admin-account-menu');

    if (!root || !button || !menu) {
        return;
    }

    const setOpen = (open) => {
        menu.classList.toggle('hidden', !open);
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
    };

    button.addEventListener('click', (event) => {
        event.stopPropagation();
        const notificationsPanel = document.getElementById('admin-notifications-panel');
        notificationsPanel?.classList.add('hidden');
        setOpen(menu.classList.contains('hidden'));
    });

    document.addEventListener('click', (event) => {
        if (!root.contains(event.target)) {
            setOpen(false);
        }
    });
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
        closeAdminAccountMenu();

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

function initializePageContent(pageName, allowedSections, profile = null) {
    switch (pageName) {
        case 'admin-dashboard':
            initializeDashboardChrome(profile);
            loadOverviewCounts(allowedSections);
            loadNeedsAttention(allowedSections);

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
        case 'admin-contests':
            initContestsModule();
            break;
        case 'admin-iec-materials':
            initIecMaterialsModule();
            break;
        case 'admin-app-translations':
            initAppTranslationsModule();
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
        case 'admin-diary':
            initDiaryModule();
            break;
        case 'admin-care-toolkit':
            initCareToolkitModule();
            break;
        case 'admin-care-checks':
            initCareChecksModule();
            break;
        case 'admin-care-support':
            initCareSupportModule();
            break;
        case 'admin-hope-directory':
            initHopeDirectoryModule();
            break;
        case 'admin-hope-events':
            initHopeEventsModule();
            break;
        case 'admin-profile':
            initProfileModule();
            break;
        case 'admin-settings':
            initSettingsModule();
            break;
        case 'admin-lookups':
            initAppOptionsModule();
            break;
        case 'admin-word-blocking':
            initWordBlockingModule();
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

    document.querySelector('[data-admin-action="create-contest"]')?.addEventListener('click', () => {
        window.Contests?.create();
    });

    document.querySelector('[data-admin-action="create-iec-material"]')?.addEventListener('click', () => {
        window.IecMaterials?.create();
    });

    document.querySelector('[data-admin-action="create-app-translation"]')?.addEventListener('click', () => {
        window.AppTranslations?.create();
    });

    document.querySelector('[data-admin-action="send-notification"]')?.addEventListener('click', () => {
        window.Notifications?.send();
    });

    const exportButton = document.querySelector('[data-admin-action="export-analytics"]');
    if (exportButton && exportButton.dataset.boundExport !== '1') {
        exportButton.dataset.boundExport = '1';
        exportButton.addEventListener('click', () => {
            const analytics = window.Analytics;
            const exportCsv = analytics?.exportCsvWithRange ?? analytics?.exportCsv;
            exportCsv?.();
        });
    }

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
            key: 'contests',
            allowed: allowedSections.includes('contests'),
            request: () => window.axios.get('/admin/contests', { params: { per_page: 1 } }),
            count: (response) => response.data.data?.total,
        },
        {
            key: 'iec-materials',
            allowed: allowedSections.includes('iec-materials'),
            request: () => window.axios.get('/admin/iec-materials', { params: { per_page: 1 } }),
            count: (response) => response.data.data?.total,
        },
        {
            key: 'app-translations',
            allowed: allowedSections.includes('app-translations'),
            request: () => window.axios.get('/admin/app-translations', { params: { per_page: 1 } }),
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
        {
            key: 'diary',
            allowed: allowedSections.includes('diary'),
            request: () => window.axios.get('/admin/diary-entries', { params: { per_page: 1 } }),
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

function getDashboardRangeLabel() {
    const range = document.querySelector('[data-dashboard-range]')?.value || '7';

    if (range === '1') return 'today';
    if (range === '30') return 'this month';

    return 'this week';
}

function initializeDashboardChrome(profile) {
    const greetingEl = document.querySelector('[data-dashboard-greeting]');
    const dateEl = document.querySelector('[data-dashboard-date]');
    const firstName = profile?.first_name
        || String(profile?.name || 'there').split(/\s+/)[0]
        || 'there';
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Good morning' : hour < 18 ? 'Good afternoon' : 'Good evening';

    if (greetingEl) {
        greetingEl.textContent = `${greeting}, ${firstName}`;
    }

    if (dateEl) {
        dateEl.textContent = new Date().toLocaleDateString(undefined, {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    }

    const rangeSelect = document.querySelector('[data-dashboard-range]');
    if (rangeSelect && rangeSelect.dataset.bound !== '1') {
        rangeSelect.dataset.bound = '1';
        rangeSelect.addEventListener('change', () => {
            document.querySelectorAll('[data-overview-trend]').forEach((el) => {
                const key = el.getAttribute('data-overview-trend');
                const countEl = document.querySelector(`[data-overview-count="${key}"]`);
                const total = Number(String(countEl?.textContent || '0').replace(/,/g, '')) || 0;
                // TODO: replace localStorage delta with server period stats when available.
                updateTrend(key, total, total);
            });
        });
    }
}

function updateTrend(key, total, previous) {
    const elements = document.querySelectorAll(`[data-overview-trend="${key}"]`);

    if (!elements.length) {
        return;
    }

    // TODO: compute true period deltas from API; currently uses last-visit snapshot delta.
    const previousValue = typeof previous === 'number' ? previous : total;
    const delta = Number(total) - Number(previousValue);
    const period = getDashboardRangeLabel();

    elements.forEach((element) => {
        element.classList.remove('admin-trend-up', 'admin-trend-down', 'admin-trend-neutral');

        if (delta > 0) {
            element.classList.add('admin-trend', 'admin-trend-up');
            element.innerHTML = `<i class="fas fa-arrow-up" aria-hidden="true"></i> +${delta.toLocaleString()} ${period}`;
        } else if (delta < 0) {
            element.classList.add('admin-trend', 'admin-trend-down');
            element.innerHTML = `<i class="fas fa-arrow-down" aria-hidden="true"></i> −${Math.abs(delta).toLocaleString()} ${period}`;
        } else {
            element.classList.add('admin-trend', 'admin-trend-neutral');
            element.innerHTML = `No change ${period}`;
        }
    });

    pushSparklineSample(key, Number(total) || 0);
}

function pushSparklineSample(key, total) {
    const canvas = document.querySelector(`[data-overview-sparkline="${key}"]`);
    if (!(canvas instanceof HTMLCanvasElement)) {
        return;
    }

    const storageKey = 'dape-admin-overview-series';
    let series = {};

    try {
        series = JSON.parse(window.localStorage.getItem(storageKey) || '{}') || {};
    } catch {
        series = {};
    }

    const values = Array.isArray(series[key]) ? series[key].map(Number) : [];
    if (values.length === 0 || values[values.length - 1] !== total) {
        values.push(total);
    }
    series[key] = values.slice(-7);

    try {
        window.localStorage.setItem(storageKey, JSON.stringify(series));
    } catch {
        // ignore
    }

    drawSparkline(canvas, series[key]);
}

function drawSparkline(canvas, values) {
    const width = canvas.width || 80;
    const height = canvas.height || 24;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    ctx.clearRect(0, 0, width, height);
    const points = values.length ? values : [0];
    const min = Math.min(...points);
    const max = Math.max(...points);
    const span = Math.max(max - min, 1);
    const step = points.length > 1 ? width / (points.length - 1) : width;

    ctx.strokeStyle = '#055498';
    ctx.lineWidth = 1.5;
    ctx.beginPath();
    points.forEach((value, index) => {
        const x = index * step;
        const y = height - (((value - min) / span) * (height - 4)) - 2;
        if (index === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
    });
    ctx.stroke();
}

async function loadNeedsAttention(allowedSections) {
    const container = document.getElementById('dashboard-needs-attention');
    if (!container) return;

    try {
        const { data } = await window.axios.get('/admin/dashboard/attention');
        const items = (data.data?.items ?? []).filter((item) => {
            const section = String(item.href || '').replace('/admin/', '').split('?')[0];
            if (section === 'posts') return allowedSections.includes('posts');
            if (section === 'care-checks') return allowedSections.includes('care-checks');
            if (section === 'app-translations') return allowedSections.includes('app-translations');
            if (section === 'hope-events') return allowedSections.includes('hope-events');
            return true;
        });

        if (!items.length) {
            container.innerHTML = `
                <div class="flex items-center gap-3 rounded-xl border border-[var(--color-border)] bg-emerald-50 px-3 py-3 text-sm text-emerald-800">
                    <span class="admin-stat-card-icon !bg-emerald-100 !text-emerald-700"><i class="fas fa-check"></i></span>
                    <span>All caught up</span>
                </div>
            `;
            return;
        }

        container.innerHTML = items.map((item) => `
            <a href="${item.href}" class="flex items-center justify-between gap-3 rounded-xl border border-[var(--color-border)] bg-slate-50 px-3 py-3 transition hover:border-[var(--color-primary)]/30 hover:shadow-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] focus:ring-offset-2">
                <span class="flex min-w-0 items-start gap-3">
                    <span class="admin-stat-card-icon shrink-0"><i class="${item.icon}"></i></span>
                    <span class="text-sm font-medium leading-snug text-[var(--color-text)] break-words">${item.label}</span>
                </span>
                <span class="inline-flex shrink-0 min-h-7 min-w-7 items-center justify-center rounded-full bg-[var(--color-primary-soft)] px-2 text-xs font-bold text-[var(--color-primary)]">${Number(item.count).toLocaleString()}</span>
            </a>
        `).join('');
    } catch {
        container.innerHTML = `
            <div class="rounded-xl border border-dashed border-[var(--color-border)] px-3 py-4 text-sm text-[var(--color-text-muted)]">
                Attention items unavailable right now.
            </div>
        `;
    }
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
