@extends('admin.layout')

@section('content')
    <section id="dashboard-overview" class="admin-dashboard space-y-6">
        <div class="admin-dashboard-header flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-[var(--color-text)]" data-dashboard-greeting>Good day</h2>
                <p class="mt-1 text-sm text-[var(--color-text-muted)]" data-dashboard-date></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <label class="sr-only" for="dashboard-range">Trend range</label>
                <select id="dashboard-range" class="admin-filter-select admin-dashboard-range" data-dashboard-range>
                    <option value="1">Today</option>
                    <option value="7" selected>7 days</option>
                    <option value="30">30 days</option>
                </select>
            </div>
        </div>

        <div class="admin-dashboard-quick-actions flex max-w-full gap-2 overflow-x-auto overscroll-x-contain pb-1 sm:flex-wrap sm:overflow-visible">
            <button type="button" class="admin-primary-button shrink-0" data-admin-action="create-post" data-action-section="posts">
                <i class="fas fa-pen-to-square mr-2" aria-hidden="true"></i>
                New Post
            </button>
            <button type="button" class="admin-secondary-button shrink-0" data-admin-action="create-rehab-center" data-action-section="rehab-centers">
                <i class="fas fa-house-medical mr-2" aria-hidden="true"></i>
                Add Rehab Center
            </button>
            <button type="button" class="admin-secondary-button shrink-0" data-admin-action="create-contest" data-action-section="contests">
                <i class="fas fa-trophy mr-2" aria-hidden="true"></i>
                Add Contest
            </button>
            <button type="button" class="admin-secondary-button shrink-0" data-admin-action="create-iec-material" data-action-section="iec-materials">
                <i class="fas fa-photo-film mr-2" aria-hidden="true"></i>
                Add IEC Material
            </button>
            <button type="button" class="admin-secondary-button shrink-0" data-admin-action="create-app-translation" data-action-section="app-translations">
                <i class="fas fa-language mr-2" aria-hidden="true"></i>
                Add Translation
            </button>
            <button type="button" class="admin-secondary-button shrink-0" data-admin-action="send-notification" data-action-section="notifications">
                <i class="fas fa-bell mr-2" aria-hidden="true"></i>
                Send Notification
            </button>
        </div>

        <div id="function-cards-primary" class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['users', 'Access', 'Users', 'fa-users', '/admin/users'],
                ['posts', 'Content', 'Posts', 'fa-file-lines', '/admin/posts'],
                ['notifications', 'Campaigns', 'Notifications', 'fa-bell', '/admin/notifications'],
                ['diary', 'Journal', 'Journal', 'fa-book-open', '/admin/diary'],
            ] as [$key, $label, $title, $icon, $href])
                <a href="{{ $href }}" class="admin-stat-card admin-stat-card-tier-primary admin-function-card admin-dashboard-summary text-left" data-card-section="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="admin-stat-card-label">{{ $label }}</p>
                            <p class="mt-2 text-base font-semibold text-[var(--color-text)]">{{ $title }}</p>
                            <div class="mt-2 flex items-end justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-3xl font-bold text-[var(--color-text)]" data-overview-count="{{ $key }}">--</p>
                                    <p class="admin-trend hidden" data-overview-trend="{{ $key }}"></p>
                                </div>
                                <canvas class="admin-stat-sparkline hidden" data-overview-sparkline="{{ $key }}" width="80" height="24" aria-hidden="true"></canvas>
                            </div>
                        </div>
                        <span class="admin-stat-card-icon" aria-hidden="true">
                            <i class="fas {{ $icon }}"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div id="function-cards-secondary" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['rehab-centers', 'Directory', 'Rehab Centers', 'fa-hospital-user', '/admin/rehab-centers'],
                ['contests', 'Contests', 'Contests', 'fa-trophy', '/admin/contests'],
                ['iec-materials', 'IEC', 'IEC Materials', 'fa-photo-film', '/admin/iec-materials'],
                ['app-translations', 'Admin', 'App Translations', 'fa-language', '/admin/app-translations'],
            ] as [$key, $label, $title, $icon, $href])
                <a href="{{ $href }}" class="admin-stat-card admin-stat-card-tier-secondary admin-function-card admin-dashboard-summary text-left" data-card-section="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="admin-stat-card-label">{{ $label }}</p>
                            <p class="mt-1.5 text-sm font-semibold text-[var(--color-text)]">{{ $title }}</p>
                            <p class="mt-1 text-2xl font-bold text-[var(--color-text)]" data-overview-count="{{ $key }}">--</p>
                            <p class="admin-trend hidden" data-overview-trend="{{ $key }}"></p>
                        </div>
                        <span class="admin-stat-card-icon" aria-hidden="true">
                            <i class="fas {{ $icon }}"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="admin-shell-card p-4 sm:p-6" data-overview-analytics>
                <div class="admin-panel-header">
                    <div class="flex items-center gap-3">
                        <span class="admin-stat-card-icon" aria-hidden="true">
                            <i class="fas fa-chart-line"></i>
                        </span>
                        <div>
                            <p class="text-base font-semibold text-[var(--color-text)]">Activity Over Time</p>
                            <p class="text-xs text-[var(--color-text-muted)]" data-activity-subtitle>Events in the last 7 days</p>
                        </div>
                    </div>
                </div>
                <div id="dashboard-activity-chart" class="mt-6"></div>
                <div id="dashboard-activity-summary" class="admin-chart-summary mt-4" hidden></div>
            </div>

            <div class="space-y-6">
                <div class="admin-shell-card p-4 sm:p-6">
                    <div class="admin-panel-header">
                        <div class="flex items-center gap-3">
                            <span class="admin-stat-card-icon" aria-hidden="true">
                                <i class="fas fa-triangle-exclamation"></i>
                            </span>
                            <div>
                                <p class="text-base font-semibold text-[var(--color-text)]">Needs attention</p>
                                <p class="text-xs text-[var(--color-text-muted)]">Actionable items across the admin</p>
                            </div>
                        </div>
                    </div>
                    <div id="dashboard-needs-attention" class="mt-4 space-y-2">
                        <div class="h-10 animate-pulse rounded-xl bg-slate-100"></div>
                        <div class="h-10 animate-pulse rounded-xl bg-slate-100"></div>
                    </div>
                </div>

                <div class="admin-shell-card p-4 sm:p-6" data-overview-analytics>
                    <div class="admin-panel-header">
                        <div class="flex items-center gap-3">
                            <span class="admin-stat-card-icon" aria-hidden="true">
                                <i class="fas fa-clock-rotate-left"></i>
                            </span>
                            <div>
                                <p class="text-base font-semibold text-[var(--color-text)]">Recent Activity</p>
                                <p class="text-xs text-[var(--color-text-muted)]">Latest app and content events</p>
                            </div>
                        </div>
                        <a href="/admin/analytics" class="text-xs font-semibold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">View all</a>
                    </div>
                    <div id="dashboard-recent-activity" class="mt-4 space-y-2"></div>
                </div>
            </div>
        </div>

        <div class="admin-shell-card p-4 sm:p-6" data-overview-analytics>
            <div class="admin-panel-header">
                <div class="flex items-center gap-3">
                    <span class="admin-stat-card-icon" aria-hidden="true">
                        <i class="fas fa-ranking-star"></i>
                    </span>
                    <div>
                        <p class="text-base font-semibold text-[var(--color-text)]">Top Content</p>
                        <p class="text-xs text-[var(--color-text-muted)]">Most viewed posts</p>
                    </div>
                </div>
                <a href="/admin/posts" class="text-xs font-semibold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]">View all</a>
            </div>
            <div id="dashboard-top-posts" class="mt-4 grid gap-3"></div>
        </div>
    </section>
@endsection
