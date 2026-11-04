@extends('admin.layout')

@section('content')
    <section id="dashboard-overview" class="admin-dashboard space-y-6">
        <div class="admin-dashboard-header flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0">
                <h2 class="text-2xl font-bold text-[var(--color-text)]" data-dashboard-greeting>Good day</h2>
                <p class="mt-1 text-sm text-[var(--color-text-muted)]" data-dashboard-date></p>
            </div>
            <div class="admin-filter-bar w-full min-w-0 xl:max-w-3xl" data-admin-filter-bar data-dashboard-filter-bar>
                <div class="admin-filter-bar-main">
                    <div class="admin-filter-bar-field min-w-0 flex-1 sm:flex-none sm:min-w-[12rem]">
                        <label class="admin-filter-bar-label" for="dashboard-range-select">Period</label>
                        <select id="dashboard-range-select" class="admin-filter-bar-select" data-dashboard-range aria-label="Dashboard date range">
                            <option value="today">Today</option>
                            <option value="yesterday">Yesterday</option>
                            <option value="7" selected>Last 7 days</option>
                            <option value="14">Last 14 days</option>
                            <option value="30">Last 30 days</option>
                            <option value="60">Last 60 days</option>
                            <option value="90">Last 90 days</option>
                            <option value="this_week">This week</option>
                            <option value="last_week">Last week</option>
                            <option value="this_month">This month</option>
                            <option value="last_month">Last month</option>
                            <option value="this_quarter">This quarter</option>
                            <option value="last_quarter">Last quarter</option>
                            <option value="this_year">This year</option>
                            <option value="last_year">Last year</option>
                            <option value="all_time">All time</option>
                            <option value="custom">Custom range</option>
                        </select>
                    </div>

                    <div class="admin-filter-bar-presets" role="group" aria-label="Quick period">
                        <button type="button" class="admin-filter-bar-preset" data-dashboard-range-preset="today">Today</button>
                        <button type="button" class="admin-filter-bar-preset is-active" data-dashboard-range-preset="7">7 days</button>
                        <button type="button" class="admin-filter-bar-preset" data-dashboard-range-preset="30">30 days</button>
                    </div>
                </div>

                <div id="dashboard-custom-range" class="admin-filter-bar-custom hidden">
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="dashboard-range-from">From</label>
                        <input id="dashboard-range-from" type="date" class="admin-filter-bar-input" aria-label="Start date">
                    </div>
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="dashboard-range-to">To</label>
                        <input id="dashboard-range-to" type="date" class="admin-filter-bar-input" aria-label="End date">
                    </div>
                    <button type="button" id="dashboard-apply-range" class="admin-filter-bar-btn is-secondary min-h-11">Apply range</button>
                </div>
            </div>
        </div>

        <div class="admin-dashboard-quick-actions flex max-w-full gap-2 overflow-x-auto overscroll-x-contain pb-1 sm:flex-wrap sm:overflow-visible">
            <button type="button" class="admin-primary-button shrink-0" data-admin-action="create-post" data-action-section="posts">
                <i class="fas fa-pen-to-square mr-2" aria-hidden="true"></i>
                New Post
            </button>
            <button type="button" class="admin-secondary-button shrink-0" data-admin-action="create-rehab-center" data-action-section="rehab-centers">
                <i class="fas fa-house-medical mr-2" aria-hidden="true"></i>
                Add Center
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
                ['analytics-views', 'Reach', 'Post views', 'fa-eye', '/admin/analytics'],
                ['analytics-bookmarks', 'Engagement', 'Bookmarks', 'fa-bookmark', '/admin/analytics'],
                ['analytics-shares', 'Reach', 'Shares', 'fa-share-nodes', '/admin/analytics'],
                ['analytics-engagement', 'Engagement', 'Engagement rate', 'fa-chart-pie', '/admin/analytics'],
            ] as [$key, $label, $title, $icon, $href])
                <a href="{{ $href }}" class="admin-stat-card admin-stat-card-tier-primary admin-function-card admin-dashboard-summary text-left" data-card-section="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="admin-stat-card-label">{{ $label }}</p>
                            <p class="mt-2 text-base font-semibold text-[var(--color-text)]">{{ $title }}</p>
                            <div class="mt-2 flex items-end justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-3xl font-bold text-[var(--color-text)]" data-overview-count="{{ $key }}">--</p>
                                    @unless (str_starts_with($key, 'analytics-'))
                                        <p class="admin-trend admin-trend-neutral" data-overview-trend="{{ $key }}">No change this week</p>
                                    @endunless
                                </div>
                                @unless (str_starts_with($key, 'analytics-'))
                                    <canvas class="admin-stat-sparkline hidden" data-overview-sparkline="{{ $key }}" width="80" height="24" aria-hidden="true"></canvas>
                                @endunless
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
                ['rehab-centers', 'Directory', 'Treatment Rehabilitation Center', 'fa-hospital-user', '/admin/rehab-centers'],
                ['contests', 'Contests', 'Contests', 'fa-trophy', '/admin/contests'],
                ['iec-materials', 'IEC', 'IEC Materials', 'fa-photo-film', '/admin/iec-materials'],
                ['sticker-library', 'Stickers', 'Sticker Library', 'fa-face-smile', '/admin/sticker-library'],
                ['color-fun-library', 'Care', 'Color Fun Library', 'fa-palette', '/admin/color-fun-library'],
                ['app-translations', 'Admin', 'App Translations', 'fa-language', '/admin/app-translations'],
            ] as [$key, $label, $title, $icon, $href])
                <a href="{{ $href }}" class="admin-stat-card admin-stat-card-tier-secondary admin-function-card admin-dashboard-summary text-left" data-card-section="{{ $key }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="admin-stat-card-label">{{ $label }}</p>
                            <p class="mt-1.5 text-sm font-semibold text-[var(--color-text)]">{{ $title }}</p>
                            <p class="mt-1 text-2xl font-bold text-[var(--color-text)]" data-overview-count="{{ $key }}">--</p>
                            <p class="admin-trend admin-trend-neutral" data-overview-trend="{{ $key }}">No change this week</p>
                        </div>
                        <span class="admin-stat-card-icon" aria-hidden="true">
                            <i class="fas {{ $icon }}"></i>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2 xl:items-stretch">
            <div class="admin-shell-card admin-activity-panel p-4 sm:p-6" data-overview-analytics>
                <div class="admin-panel-header shrink-0">
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
                <div id="dashboard-activity-chart" class="admin-activity-chart-area mt-6"></div>
                <div id="dashboard-activity-summary" class="admin-chart-summary mt-4 shrink-0" hidden></div>
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
                        <a href="/admin/analytics" class="text-xs font-semibold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]" data-analytics-link>View all</a>
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
                <a href="/admin/posts" class="text-xs font-semibold text-[var(--color-primary)] hover:text-[var(--color-primary-hover)]" data-card-section="posts">View all</a>
            </div>
            <div id="dashboard-top-posts" class="mt-4 grid gap-3"></div>
        </div>
    </section>
@endsection
