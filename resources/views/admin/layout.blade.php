<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle ?? 'DAPE-MA Admin' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('ddb.svg') }}">
    <link rel="shortcut icon" href="{{ asset('ddb.svg') }}">
    <link rel="apple-touch-icon" href="{{ asset('ddb.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/gotham-fonts@1.0.3/css/gotham-rounded.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-page="{{ $bodyPage ?? 'admin-dashboard' }}" class="min-h-screen bg-slate-100 text-slate-900">
    <div id="admin-app" class="relative min-h-screen">
        <div id="admin-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 lg:hidden"></div>

        <aside id="admin-sidebar" class="fixed inset-y-0 left-0 top-0 z-40 flex h-screen max-h-screen w-72 -translate-x-full flex-col overflow-y-auto bg-[#123a60] px-3 py-4 text-white shadow-xl transition-transform duration-300 lg:translate-x-0" data-admin-sidebar>
            <div class="shrink-0 px-2">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-white shadow-lg shadow-[#055498]/25">
                        <img src="{{ asset('ddb.svg') }}" alt="DDB logo" class="h-9 w-9 object-contain">
                    </div>
                    <div class="admin-sidebar-brand-text min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#FBD116]">DAPE-MA</p>
                        <p class="truncate text-sm font-semibold text-white">Admin Control Panel</p>
                    </div>
                    <button
                        type="button"
                        id="sidebar-collapse-toggle"
                        class="admin-sidebar-collapse-toggle hidden lg:inline-flex"
                        aria-label="Collapse sidebar"
                        title="Collapse sidebar"
                    >
                        <i class="fas fa-angles-left" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            <nav data-admin-nav aria-hidden="true" class="admin-sidebar-nav pointer-events-none mt-5 min-h-0 flex-1 space-y-0 overflow-y-auto overscroll-contain pr-1 text-sm opacity-0 transition-opacity duration-200">
                <div class="admin-nav-group">
                    <p class="admin-nav-group-label">Overview</p>
                    <a href="/admin/dashboard" class="admin-nav-link {{ ($activePage ?? 'dashboard') === 'dashboard' ? 'admin-nav-link-active' : '' }}">
                        <i class="fas fa-gauge-high w-5 text-[#FBD116]"></i>
                        <span>Dashboard</span>
                    </a>
                </div>

                <div class="admin-nav-group">
                    <p class="admin-nav-group-label">Publish</p>
                    <a href="/admin/posts" class="admin-nav-link {{ ($activePage ?? '') === 'posts' ? 'admin-nav-link-active' : '' }}" data-nav-section="posts">
                        <i class="fas fa-file-pen w-5 text-[#FBD116]"></i>
                        <span>Posts</span>
                    </a>
                    <a href="/admin/contests" class="admin-nav-link {{ ($activePage ?? '') === 'contests' ? 'admin-nav-link-active' : '' }}" data-nav-section="contests">
                        <i class="fas fa-trophy w-5 text-[#FBD116]"></i>
                        <span>Contests</span>
                    </a>
                    <a href="/admin/iec-materials" class="admin-nav-link {{ ($activePage ?? '') === 'iec-materials' ? 'admin-nav-link-active' : '' }}" data-nav-section="iec-materials">
                        <i class="fas fa-photo-film w-5 text-[#FBD116]"></i>
                        <span>IEC Materials</span>
                    </a>
                </div>

                <div class="admin-nav-group">
                    <p class="admin-nav-group-label">Find help</p>
                    <a href="/admin/rehab-centers" class="admin-nav-link {{ ($activePage ?? '') === 'rehab-centers' ? 'admin-nav-link-active' : '' }}" data-nav-section="rehab-centers">
                        <i class="fas fa-hospital w-5 text-[#FBD116]"></i>
                        <span>Rehab Centers</span>
                    </a>
                    <a href="/admin/care-toolkit" class="admin-nav-link {{ ($activePage ?? '') === 'care-toolkit' ? 'admin-nav-link-active' : '' }}" data-nav-section="care-toolkit">
                        <i class="fas fa-heart-pulse w-5 text-[#FBD116]"></i>
                        <span>Care Toolkit</span>
                    </a>
                    <a href="/admin/care-checks" class="admin-nav-link {{ ($activePage ?? '') === 'care-checks' ? 'admin-nav-link-active' : '' }}" data-nav-section="care-checks">
                        <i class="fas fa-clipboard-check w-5 text-[#FBD116]"></i>
                        <span>Care Checks</span>
                    </a>
                    <a href="/admin/care-support" class="admin-nav-link {{ ($activePage ?? '') === 'care-support' ? 'admin-nav-link-active' : '' }}" data-nav-section="care-support">
                        <i class="fas fa-handshake-angle w-5 text-[#FBD116]"></i>
                        <span>Care Support</span>
                    </a>
                    <a href="/admin/diary" class="admin-nav-link {{ ($activePage ?? '') === 'diary' ? 'admin-nav-link-active' : '' }}" data-nav-section="diary">
                        <i class="fas fa-book-open w-5 text-[#FBD116]"></i>
                        <span>Journal</span>
                    </a>
                </div>

                <div class="admin-nav-group">
                    <p class="admin-nav-group-label">Hope</p>
                    <a href="/admin/hope-directory" class="admin-nav-link {{ ($activePage ?? '') === 'hope-directory' ? 'admin-nav-link-active' : '' }}" data-nav-section="hope-directory">
                        <i class="fas fa-address-book w-5 text-[#FBD116]"></i>
                        <span>Hope Directory</span>
                    </a>
                    <a href="/admin/hope-events" class="admin-nav-link {{ ($activePage ?? '') === 'hope-events' ? 'admin-nav-link-active' : '' }}" data-nav-section="hope-events">
                        <i class="fas fa-calendar-days w-5 text-[#FBD116]"></i>
                        <span>Hope Events</span>
                    </a>
                </div>

                <div class="admin-nav-group">
                    <p class="admin-nav-group-label">Engage</p>
                    <a href="/admin/notifications" class="admin-nav-link {{ ($activePage ?? '') === 'notifications' ? 'admin-nav-link-active' : '' }}" data-nav-section="notifications">
                        <i class="fas fa-bell w-5 text-[#FBD116]"></i>
                        <span>Notifications</span>
                    </a>
                    <a href="/admin/analytics" class="admin-nav-link {{ ($activePage ?? '') === 'analytics' ? 'admin-nav-link-active' : '' }}" data-nav-section="analytics">
                        <i class="fas fa-chart-column w-5 text-[#FBD116]"></i>
                        <span>Analytics</span>
                    </a>
                </div>

                <div class="admin-nav-group">
                    <p class="admin-nav-group-label">System</p>
                    <a href="/admin/users" class="admin-nav-link {{ ($activePage ?? '') === 'users' ? 'admin-nav-link-active' : '' }}" data-nav-section="users">
                        <i class="fas fa-users w-5 text-[#FBD116]"></i>
                        <span>Users</span>
                    </a>
                    <a href="/admin/settings" class="admin-nav-link {{ ($activePage ?? '') === 'settings' ? 'admin-nav-link-active' : '' }}" data-nav-section="settings">
                        <i class="fas fa-gear w-5 text-[#FBD116]"></i>
                        <span>Settings</span>
                    </a>
                    <a href="/admin/lookups" class="admin-nav-link {{ ($activePage ?? '') === 'lookups' ? 'admin-nav-link-active' : '' }}" data-nav-section="lookups">
                        <i class="fas fa-sliders w-5 text-[#FBD116]"></i>
                        <span>Lookups</span>
                    </a>
                    <a href="/admin/app-translations" class="admin-nav-link {{ ($activePage ?? '') === 'app-translations' ? 'admin-nav-link-active' : '' }}" data-nav-section="app-translations">
                        <i class="fas fa-language w-5 text-[#FBD116]"></i>
                        <span>App Translations</span>
                    </a>
                    <a href="/admin/word-blocking" class="admin-nav-link {{ ($activePage ?? '') === 'word-blocking' ? 'admin-nav-link-active' : '' }}" data-nav-section="word-blocking">
                        <i class="fas fa-ban w-5 text-[#FBD116]"></i>
                        <span>Word Blocking</span>
                    </a>
                </div>
            </nav>
        </aside>

        <div class="admin-main-offset lg:pl-72">
            <header class="relative sticky top-0 z-30 border-b border-slate-200 bg-white shadow-sm" data-admin-mega>
                {{-- Until xl, keep one compact header row: sidebar already uses ~18rem at lg. --}}
                <div class="mx-auto flex max-w-[1600px] flex-wrap items-center gap-3 px-4 py-3 sm:px-6 lg:flex-nowrap lg:gap-3 lg:px-6 lg:py-3 xl:gap-4 xl:px-8 xl:py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <button id="sidebar-toggle" type="button" class="admin-secondary-button admin-icon-button lg:hidden" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#055498]">Admin Panel</p>
                            <h1 class="truncate text-lg font-semibold text-slate-800 sm:text-xl">{{ $headerTitle ?? 'Dashboard' }}</h1>
                        </div>
                    </div>
                    <div class="hidden min-w-0 flex-1 basis-[10rem] lg:flex lg:max-w-sm xl:max-w-xl 2xl:max-w-2xl">
                        <label class="sr-only" for="admin-mega-search">Search admin</label>
                        <div class="admin-mega-search">
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                            <input
                                id="admin-mega-search"
                                type="search"
                                class="admin-mega-search-input"
                                placeholder="Search pages, actions, and records…"
                                autocomplete="off"
                                data-admin-mega-input="desktop"
                                aria-expanded="false"
                                aria-controls="admin-mega-panel"
                            >
                            <kbd class="admin-mega-kbd">⌘K</kbd>
                        </div>
                    </div>
                    <div class="relative ml-auto flex shrink-0 items-center justify-end gap-2">
                        <button
                            type="button"
                            class="admin-secondary-button admin-icon-button lg:hidden"
                            data-admin-mega-open
                            aria-label="Search admin"
                            aria-expanded="false"
                            aria-controls="admin-mega-panel"
                        >
                            <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="admin-secondary-button admin-icon-button relative hidden lg:inline-flex" aria-label="Notifications" data-admin-notifications-button>
                            <span class="relative inline-flex items-center justify-center">
                                <i class="fas fa-bell text-slate-500"></i>
                                <span class="admin-notification-dot hidden" data-admin-notifications-dot></span>
                            </span>
                        </button>
                        <div class="relative" data-admin-account-menu>
                            <button
                                id="admin-account-menu-button"
                                type="button"
                                class="flex min-h-11 max-w-[min(100%,18rem)] items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-2 py-1.5 transition hover:border-[#055498]/30 hover:bg-[#055498]/5 sm:gap-3 sm:px-3 sm:py-2"
                                aria-haspopup="menu"
                                aria-expanded="false"
                                aria-controls="admin-account-menu"
                            >
                                <span data-admin-avatar class="relative inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#123a60]">
                                    <img data-admin-avatar-img class="hidden h-full w-full object-cover" alt="Profile photo">
                                    <span data-admin-initials class="text-xs font-bold text-white" aria-hidden="true">--</span>
                                </span>
                                <div class="hidden min-w-0 text-left xl:block">
                                    <p data-admin-name class="truncate text-sm font-semibold text-slate-800">Loading...</p>
                                    <p data-admin-role class="truncate text-xs text-slate-500">Loading role...</p>
                                </div>
                                <i class="fas fa-chevron-down shrink-0 pr-1 text-[10px] text-slate-400" aria-hidden="true"></i>
                            </button>
                            <div id="admin-account-menu" class="admin-account-menu hidden" role="menu" aria-labelledby="admin-account-menu-button">
                                <a
                                    href="/admin/profile"
                                    role="menuitem"
                                    class="admin-account-menu-item {{ ($activePage ?? '') === 'profile' ? 'admin-account-menu-item-active' : '' }}"
                                >
                                    <i class="fas fa-user-pen w-4 text-[#055498]" aria-hidden="true"></i>
                                    <span>Edit Profile</span>
                                </a>
                                <button id="logout-button" type="button" role="menuitem" class="admin-account-menu-item admin-account-menu-item-danger">
                                    <i class="fas fa-right-from-bracket w-4" aria-hidden="true"></i>
                                    <span>Sign Out</span>
                                </button>
                            </div>
                        </div>
                        <div id="admin-notifications-panel" class="admin-notifications-panel hidden"></div>
                    </div>
                </div>
                <div id="admin-mega-panel" class="admin-mega-panel hidden" data-admin-mega-panel>
                    <div class="admin-mega-panel-inner">
                        <div class="admin-mega-panel-search lg:hidden">
                            <label class="sr-only" for="admin-mega-search-panel">Search admin</label>
                            <div class="admin-mega-search">
                                <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                                <input
                                    id="admin-mega-search-panel"
                                    type="search"
                                    class="admin-mega-search-input"
                                    placeholder="Search pages, actions, and records…"
                                    autocomplete="off"
                                    data-admin-mega-input="panel"
                                >
                            </div>
                        </div>
                        <p class="admin-mega-hint" data-admin-mega-hint>Browse every admin function, or type to search pages, actions, and records.</p>
                        <div data-admin-mega-results></div>
                    </div>
                </div>
            </header>
            <div class="admin-mega-backdrop hidden" data-admin-mega-backdrop></div>

            <main class="mx-auto max-w-[1600px] space-y-6 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
