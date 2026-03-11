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
        <div id="admin-sidebar-backdrop" class="fixed inset-0 z-30 hidden bg-slate-950/50 md:hidden"></div>

        <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col bg-[#123a60] px-5 py-6 text-white shadow-xl transition-transform duration-300 md:translate-x-0">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded-2xl bg-white shadow-lg shadow-[#055498]/25">
                    <img src="{{ asset('ddb.svg') }}" alt="DDB logo" class="h-10 w-10 object-contain">
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#FBD116]">DAPE-MA</p>
                    <p class="text-sm font-semibold text-white">Admin Control Panel</p>
                </div>
            </div>

            <div class="mt-8 rounded-2xl border border-[#055498] bg-white/5 p-4">
                <p class="text-xs uppercase tracking-[0.2em] text-slate-300">Signed in as</p>
                <p data-admin-name class="mt-2 text-base font-semibold text-white">Loading...</p>
                <p data-admin-role class="text-sm text-[#FBD116]">Loading role...</p>
            </div>

            <nav data-admin-nav aria-hidden="true" class="pointer-events-none mt-8 space-y-1 text-sm opacity-0 transition-opacity duration-200">
                <a href="/admin/dashboard" class="admin-nav-link {{ ($activePage ?? 'dashboard') === 'dashboard' ? 'admin-nav-link-active' : '' }}">
                    <i class="fas fa-gauge-high w-5 text-[#FBD116]"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/posts" class="admin-nav-link {{ ($activePage ?? '') === 'posts' ? 'admin-nav-link-active' : '' }}" data-nav-section="posts">
                    <i class="fas fa-file-pen w-5 text-[#FBD116]"></i>
                    <span>Posts</span>
                </a>
                <a href="/admin/rehab-centers" class="admin-nav-link {{ ($activePage ?? '') === 'rehab-centers' ? 'admin-nav-link-active' : '' }}" data-nav-section="rehab-centers">
                    <i class="fas fa-hospital w-5 text-[#FBD116]"></i>
                    <span>Rehab Centers</span>
                </a>
                <a href="/admin/notifications" class="admin-nav-link {{ ($activePage ?? '') === 'notifications' ? 'admin-nav-link-active' : '' }}" data-nav-section="notifications">
                    <i class="fas fa-bell w-5 text-[#FBD116]"></i>
                    <span>Notifications</span>
                </a>
                <a href="/admin/analytics" class="admin-nav-link {{ ($activePage ?? '') === 'analytics' ? 'admin-nav-link-active' : '' }}" data-nav-section="analytics">
                    <i class="fas fa-chart-column w-5 text-[#FBD116]"></i>
                    <span>Analytics</span>
                </a>
                <a href="/admin/users" class="admin-nav-link {{ ($activePage ?? '') === 'users' ? 'admin-nav-link-active' : '' }}" data-nav-section="users">
                    <i class="fas fa-users w-5 text-[#FBD116]"></i>
                    <span>Users</span>
                </a>
            </nav>

            <div class="mt-auto border-t border-white/20 pt-4">
                <button id="logout-button" type="button" class="admin-secondary-button w-full border-slate-500 bg-transparent text-white hover:bg-white/10">
                    Sign Out
                </button>
            </div>
        </aside>

        <div class="md:pl-72">
            <header class="sticky top-0 z-20 border-b border-slate-200 bg-white shadow-sm">
                <div class="mx-auto flex max-w-[1600px] flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button id="sidebar-toggle" type="button" class="admin-secondary-button admin-icon-button md:hidden" aria-controls="admin-sidebar" aria-expanded="false" aria-label="Open navigation menu">
                            <i class="fas fa-bars"></i>
                        </button>
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#055498]">Admin Panel</p>
                            <h1 class="truncate text-lg font-semibold text-slate-800 sm:text-xl">{{ $headerTitle ?? 'Dashboard' }}</h1>
                        </div>
                    </div>
                    <div class="relative flex w-full items-center justify-end gap-2 sm:w-auto">
                        <button type="button" class="admin-secondary-button admin-icon-button relative hidden lg:inline-flex" aria-label="Notifications" data-admin-notifications-button>
                            <span class="relative inline-flex items-center justify-center">
                                <i class="fas fa-bell text-slate-500"></i>
                                <span class="admin-notification-dot hidden" data-admin-notifications-dot></span>
                            </span>
                        </button>
                        <div class="hidden items-center gap-3 rounded-full border border-slate-200 bg-slate-50 px-3 py-2 lg:flex">
                            <span data-admin-initials class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[#123a60] text-xs font-bold text-white">DM</span>
                            <div class="text-left">
                                <p data-admin-name class="text-sm font-semibold text-slate-800">Loading...</p>
                                <p data-admin-role class="text-xs text-slate-500">Loading role...</p>
                            </div>
                        </div>
                        <div id="admin-notifications-panel" class="admin-notifications-panel hidden"></div>
                    </div>
                </div>
            </header>

            <main class="mx-auto max-w-[1600px] space-y-6 px-3 py-4 sm:px-6 sm:py-6 lg:px-8">
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
