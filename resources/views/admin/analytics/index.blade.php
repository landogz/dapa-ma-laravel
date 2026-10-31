@extends('admin.layout')

@section('content')
    <section class="admin-shell-card relative p-4 sm:p-6" data-analytics-shell>
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div class="admin-section-header admin-section-header-accent min-w-0 flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-chart-pie"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">Analytics command center</h2>
                    <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">Live engagement, audience, content, and device performance across the DAPE-MA app.</p>
                    <p class="mt-1 text-[11px] text-slate-400" data-analytics-updated-at>Refreshing metrics…</p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered flex w-full min-w-0 flex-col gap-2 xl:w-auto xl:flex-row xl:flex-wrap xl:items-center xl:justify-end">
                <div class="flex min-w-0 flex-col gap-2 text-xs text-slate-500 sm:flex-row sm:flex-wrap sm:items-center">
                    <label class="font-semibold uppercase tracking-[0.12em]" for="analytics-range-select">Range</label>
                    <select id="analytics-range-select" class="admin-auth-input min-h-11 min-w-[10rem] py-2 text-xs">
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
                    <div id="analytics-custom-range" class="hidden flex w-full min-w-0 flex-col gap-2 sm:w-auto sm:flex-row sm:items-center">
                        <input id="analytics-range-from" type="date" class="admin-auth-input min-h-11 py-2 text-xs" aria-label="Start date">
                        <input id="analytics-range-to" type="date" class="admin-auth-input min-h-11 py-2 text-xs" aria-label="End date">
                        <button type="button" id="analytics-apply-range" class="admin-secondary-button min-h-11 px-3 text-xs">Apply</button>
                    </div>
                    <div class="admin-range-toggle inline-flex min-h-11 max-w-full overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-[11px]">
                        <button type="button" class="admin-range-toggle-button" data-analytics-range="today">Today</button>
                        <button type="button" class="admin-range-toggle-button is-active" data-analytics-range="7">7 days</button>
                        <button type="button" class="admin-range-toggle-button" data-analytics-range="30">30 days</button>
                    </div>
                </div>
                <div class="flex w-full min-w-0 flex-col gap-2 sm:flex-row xl:w-auto">
                    <button type="button" class="admin-secondary-button min-h-11 w-full sm:flex-1 xl:w-auto xl:flex-none" data-admin-action="refresh-analytics" aria-label="Refresh analytics">
                        <i class="fas fa-rotate" aria-hidden="true"></i>
                        <span>Refresh</span>
                    </button>
                    <button type="button" class="admin-primary-button min-h-11 w-full sm:flex-1 xl:w-auto xl:flex-none" data-admin-action="export-analytics">
                        <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>
        </div>

        <nav class="mt-5 flex min-h-11 gap-2 overflow-x-auto pb-1" aria-label="Analytics sections">
            @foreach ([
                'overview' => 'Overview',
                'users' => 'Users',
                'mobile' => 'Mobile',
                'content' => 'Content',
                'retention' => 'Retention',
                'journeys' => 'Journeys',
                'leaderboard' => 'Leaderboard',
                'geography' => 'Geography',
                'alerts' => 'Alerts',
                'reports' => 'Reports',
            ] as $tabKey => $tabLabel)
                <button
                    type="button"
                    class="admin-range-toggle-button min-h-11 shrink-0 rounded-full px-4 text-xs {{ $tabKey === 'overview' ? 'is-active' : '' }}"
                    data-analytics-tab="{{ $tabKey }}"
                >{{ $tabLabel }}</button>
            @endforeach
        </nav>

        <div class="analytics-insight-strip mt-5" data-analytics-insights></div>

        <p class="mt-3 text-[11px] text-slate-400" data-analytics-tracking-since>Session/DAU metrics appear after mobile app opens are recorded.</p>

        <div class="mt-3 grid gap-3 grid-cols-2 sm:grid-cols-3 xl:grid-cols-4">
            <article class="analytics-mini-kpi" data-analytics-tip="registered_users" title="Registered app users">
                <p class="analytics-mini-kpi-label">Registered users</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="registered-users">--</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">New users</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="new-users">--</p>
            </article>
            <article class="analytics-mini-kpi" data-analytics-tip="dau">
                <p class="analytics-mini-kpi-label">DAU</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="dau">--</p>
            </article>
            <article class="analytics-mini-kpi" data-analytics-tip="wau">
                <p class="analytics-mini-kpi-label">WAU</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="wau">--</p>
            </article>
            <article class="analytics-mini-kpi" data-analytics-tip="mau">
                <p class="analytics-mini-kpi-label">MAU</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="mau">--</p>
            </article>
            <article class="analytics-mini-kpi" data-analytics-tip="app_opens">
                <p class="analytics-mini-kpi-label">App opens</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="app-opens">--</p>
            </article>
            <article class="analytics-mini-kpi" data-analytics-tip="avg_session_duration_seconds">
                <p class="analytics-mini-kpi-label">Avg session</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="avg-session">--</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Engagements</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="rel-engagements">--</p>
            </article>
        </div>

        <div data-analytics-panel="overview">

        <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <article class="admin-stat-card analytics-kpi-card text-left">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#055498]">Volume</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Total events</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900" data-analytics-summary="total-events">--</p>
                        <p class="admin-trend" data-analytics-trend="total-events">–</p>
                    </div>
                    <span class="analytics-change-badge" data-analytics-change="total-events">—</span>
                </div>
                <div data-analytics-sparkline="total" class="analytics-metric-sparkline mt-3" aria-hidden="true"></div>
            </article>
            <article class="admin-stat-card analytics-kpi-card text-left">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#055498]">Content</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Views</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900" data-analytics-summary="views">--</p>
                        <p class="admin-trend" data-analytics-trend="views">–</p>
                    </div>
                    <span class="analytics-change-badge" data-analytics-change="views">—</span>
                </div>
                <div data-analytics-sparkline="views" class="analytics-metric-sparkline mt-3" aria-hidden="true"></div>
            </article>
            <article class="admin-stat-card analytics-kpi-card text-left">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#123a60]">Audience</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Unique users</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900" data-analytics-summary="unique-users">--</p>
                        <p class="admin-trend" data-analytics-trend="unique-users">–</p>
                    </div>
                    <span class="analytics-change-badge" data-analytics-change="unique-users">—</span>
                </div>
                <p class="mt-3 text-[11px] text-slate-500">Signed-in people who generated at least one event.</p>
            </article>
            <article class="admin-stat-card analytics-kpi-card text-left">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[#123a60]">Quality</p>
                        <p class="mt-1 text-sm font-semibold text-slate-900">Engagement rate</p>
                        <p class="mt-2 text-2xl font-bold tracking-tight text-slate-900" data-analytics-summary="engagement">--</p>
                        <p class="admin-trend" data-analytics-trend="engagement">Bookmarks + shares / views</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-bolt"></i>
                    </span>
                </div>
            </article>
        </div>

        <div class="mt-3 grid gap-3 grid-cols-2 sm:grid-cols-3 xl:grid-cols-6">
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Sessions</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="unique-sessions">--</p>
                <p class="admin-trend" data-analytics-trend="unique-sessions">–</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Bookmarks</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="bookmarks">--</p>
                <p class="admin-trend" data-analytics-trend="bookmarks">–</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Searches</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="searches">--</p>
                <p class="admin-trend" data-analytics-trend="searches">–</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Shares</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="shares">--</p>
                <p class="admin-trend" data-analytics-trend="shares">–</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Reviews</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="ratings-total">--</p>
                <p class="admin-trend" data-analytics-trend="ratings-total">–</p>
            </article>
            <article class="analytics-mini-kpi">
                <p class="analytics-mini-kpi-label">Avg rating</p>
                <p class="analytics-mini-kpi-value" data-analytics-summary="ratings-average">--</p>
                <p class="mt-1 text-[11px] text-slate-500">Across rated posts</p>
            </article>
        </div>

        <div class="mt-5 grid gap-4 xl:grid-cols-[1.4fr_0.9fr]">
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Trend</p>
                        <h3 class="analytics-panel-title">Daily activity</h3>
                    </div>
                    <p class="text-[11px] text-slate-500" data-analytics-avg-day>—</p>
                </div>
                <div id="analytics-daily-chart" class="mt-4"></div>
            </section>
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Timing</p>
                        <h3 class="analytics-panel-title">Hourly heatmap</h3>
                    </div>
                    <p class="text-[11px] text-slate-500">Local time, 24 hours</p>
                </div>
                <div id="analytics-hourly-heatmap" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-3">
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Mix</p>
                        <h3 class="analytics-panel-title">Events by type</h3>
                    </div>
                </div>
                <div id="analytics-event-summary" class="mt-4 space-y-3"></div>
            </section>
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Conversion</p>
                        <h3 class="analytics-panel-title">Engagement funnel</h3>
                    </div>
                </div>
                <div id="analytics-funnel" class="mt-4"></div>
            </section>
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Platforms</p>
                        <h3 class="analytics-panel-title">Device breakdown</h3>
                    </div>
                </div>
                <div id="analytics-device-chart" class="analytics-device-chart-wrap mt-4"></div>
                <div id="analytics-device-legend" class="mt-4 space-y-2 text-xs text-slate-600"></div>
                <p class="mt-3 text-[11px] text-slate-400 hidden" data-analytics-device-empty>
                    Device usage data will appear here once analytics events include platform information.
                </p>
                <div class="mt-4" id="analytics-audience-split"></div>
            </section>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-3">
            <section class="analytics-panel">
                <div class="analytics-panel-header gap-3">
                    <div class="min-w-0">
                        <p class="analytics-panel-kicker">Content</p>
                        <h3 class="analytics-panel-title">Top viewed posts</h3>
                    </div>
                    <input
                        id="analytics-top-posts-search"
                        type="search"
                        class="admin-auth-input hidden max-w-full py-2 text-xs sm:max-w-[11rem]"
                        placeholder="Search posts..."
                        data-analytics-posts-search
                        autocomplete="off"
                    >
                </div>
                <div id="analytics-top-posts" class="mt-4"></div>
            </section>
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Quality</p>
                        <h3 class="analytics-panel-title">Top rated posts</h3>
                    </div>
                </div>
                <div id="analytics-top-rated-posts" class="mt-4"></div>
            </section>
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Topics</p>
                        <h3 class="analytics-panel-title">Top categories</h3>
                    </div>
                </div>
                <div id="analytics-top-categories" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-4 grid gap-4 xl:grid-cols-2">
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Calendar</p>
                        <h3 class="analytics-panel-title">Weekday performance</h3>
                    </div>
                </div>
                <div id="analytics-weekday-chart" class="mt-4"></div>
            </section>
            <section class="analytics-panel">
                <div class="analytics-panel-header">
                    <div>
                        <p class="analytics-panel-kicker">Reviews</p>
                        <h3 class="analytics-panel-title">Rating distribution</h3>
                    </div>
                    <p class="text-[11px] text-slate-500">Lifetime star mix</p>
                </div>
                <div id="analytics-rating-distribution" class="mt-4"></div>
            </section>
        </div>

        <section class="analytics-panel mt-4">
            <div class="analytics-panel-header gap-3">
                <div>
                    <p class="analytics-panel-kicker">Live feed</p>
                    <h3 class="analytics-panel-title">Recent activity</h3>
                </div>
                <input
                    id="analytics-recent-search"
                    type="search"
                    class="admin-auth-input max-w-full py-2 text-xs sm:max-w-[14rem]"
                    placeholder="Filter activity..."
                    autocomplete="off"
                >
            </div>
            <p class="mt-2 text-[11px] text-slate-500" data-analytics-recent-meta>Latest tracked events in this range</p>
            <div id="analytics-recent-feed" class="mt-4 space-y-2"></div>
        </section>
        </div>{{-- overview panel --}}

        <div class="mt-5 hidden space-y-4" data-analytics-panel="users">
            <section class="analytics-panel">
                <div class="analytics-panel-header"><div><p class="analytics-panel-kicker">Audience</p><h3 class="analytics-panel-title">Advanced user search</h3></div><p class="text-[11px] text-slate-500" data-analytics-users-count></p></div>
                <div class="mt-4 grid gap-2 sm:grid-cols-2 xl:grid-cols-4">
                    <input id="analytics-users-q" type="search" class="admin-auth-input min-h-11 text-xs" placeholder="Name, email, ID">
                    <input id="analytics-users-age-min" type="number" min="0" max="120" class="admin-auth-input min-h-11 text-xs" placeholder="Age min (e.g. 17)">
                    <input id="analytics-users-age-max" type="number" min="0" max="120" class="admin-auth-input min-h-11 text-xs" placeholder="Age max (e.g. 29)">
                    <select id="analytics-users-inactive" class="admin-auth-input min-h-11 text-xs"><option value="">Any activity</option><option value="7">Inactive 7+ days</option><option value="30">Inactive 30+ days</option><option value="60">Inactive 60+ days</option><option value="90">Inactive 90+ days</option></select>
                    <select id="analytics-users-platform" class="admin-auth-input min-h-11 text-xs"><option value="">Any platform</option><option value="android">Android</option><option value="ios">iOS</option><option value="web">Web</option></select>
                    <select id="analytics-users-activity" class="admin-auth-input min-h-11 text-xs"><option value="">Any activity type</option><option value="opened_app">Opened app</option><option value="viewed_post">Viewed post</option><option value="liked">Liked</option><option value="commented">Commented</option><option value="searched">Searched</option></select>
                    <button type="button" id="analytics-users-apply" class="admin-primary-button min-h-11 text-xs">Apply filters</button>
                </div>
                <div id="analytics-users-results" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-5 hidden" data-analytics-panel="mobile">
            <section class="analytics-panel"><div class="analytics-panel-header"><div><p class="analytics-panel-kicker">Mobile</p><h3 class="analytics-panel-title">App usage</h3></div></div><div id="analytics-mobile-panel" class="mt-4"></div></section>
        </div>

        <div class="mt-5 hidden space-y-4" data-analytics-panel="content">
            <section class="analytics-panel">
                <div class="analytics-panel-header"><div><p class="analytics-panel-kicker">Content</p><h3 class="analytics-panel-title">Post analytics</h3></div></div>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <input id="analytics-content-post-id" type="number" min="1" class="admin-auth-input min-h-11 text-xs sm:max-w-[10rem]" placeholder="Post ID">
                    <select id="analytics-content-interaction" class="admin-auth-input min-h-11 text-xs sm:max-w-[12rem]"><option value="any">All engagers</option><option value="viewed_only">Viewed only</option><option value="liked">Liked</option><option value="commented">Commented</option><option value="shared">Shared</option><option value="bookmarked">Bookmarked</option></select>
                    <button type="button" id="analytics-content-apply" class="admin-primary-button min-h-11 text-xs">Inspect post</button>
                </div>
                <div id="analytics-content-detail" class="mt-4"></div>
                <div id="analytics-content-engagers" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-5 hidden space-y-4" data-analytics-panel="retention">
            <section class="analytics-panel">
                <div class="analytics-panel-header gap-3"><div><p class="analytics-panel-kicker">Retention</p><h3 class="analytics-panel-title">Cohort heatmap</h3></div>
                    <select id="analytics-retention-grain" class="admin-auth-input min-h-11 py-2 text-xs"><option value="week">Weekly cohorts</option><option value="month">Monthly cohorts</option></select>
                    <button type="button" id="analytics-retention-apply" class="admin-secondary-button min-h-11 px-3 text-xs">Refresh</button>
                </div>
                <div id="analytics-retention-heatmap" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-5 hidden" data-analytics-panel="journeys">
            <section class="analytics-panel"><div class="analytics-panel-header"><div><p class="analytics-panel-kicker">Journeys</p><h3 class="analytics-panel-title">Funnel conversion</h3></div></div><div id="analytics-journeys-funnel" class="mt-4"></div></section>
        </div>

        <div class="mt-5 hidden space-y-4" data-analytics-panel="leaderboard">
            <section class="analytics-panel">
                <div class="analytics-panel-header gap-3 flex-wrap">
                    <div><p class="analytics-panel-kicker">Ranking</p><h3 class="analytics-panel-title">Top users</h3></div>
                    <select id="analytics-leaderboard-metric" class="admin-auth-input min-h-11 py-2 text-xs"><option value="engagements">Engagements</option><option value="score">Engagement score</option><option value="views">Views</option><option value="likes">Likes</option><option value="comments">Comments</option><option value="shares">Shares</option><option value="app_opens">App opens</option><option value="sessions">Sessions</option></select>
                    <select id="analytics-leaderboard-limit" class="admin-auth-input min-h-11 py-2 text-xs"><option value="10">Top 10</option><option value="25" selected>Top 25</option><option value="50">Top 50</option><option value="100">Top 100</option></select>
                    <button type="button" id="analytics-leaderboard-apply" class="admin-secondary-button min-h-11 px-3 text-xs">Refresh</button>
                </div>
                <div id="analytics-leaderboard-table" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-5 hidden space-y-4" data-analytics-panel="geography">
            <section class="analytics-panel">
                <div class="analytics-panel-header gap-3"><div><p class="analytics-panel-kicker">Geography</p><h3 class="analytics-panel-title">User distribution</h3></div>
                    <select id="analytics-geo-level" class="admin-auth-input min-h-11 py-2 text-xs"><option value="region">Region</option><option value="province" selected>Province</option><option value="city">City</option></select>
                    <button type="button" id="analytics-geo-apply" class="admin-secondary-button min-h-11 px-3 text-xs">Refresh</button>
                </div>
                <div id="analytics-geography-panel" class="mt-4"></div>
            </section>
        </div>

        <div class="mt-5 hidden space-y-4" data-analytics-panel="alerts">
            <section class="analytics-panel">
                <div class="analytics-panel-header gap-3"><div><p class="analytics-panel-kicker">Monitoring</p><h3 class="analytics-panel-title">Anomaly alerts</h3></div>
                    <select id="analytics-alerts-status" class="admin-auth-input min-h-11 py-2 text-xs"><option value="">All statuses</option><option value="new">New</option><option value="acknowledged">Acknowledged</option><option value="resolved">Resolved</option><option value="dismissed">Dismissed</option></select>
                    <button type="button" id="analytics-alerts-apply" class="admin-secondary-button min-h-11 px-3 text-xs">Refresh</button>
                </div>
                <div id="analytics-alerts-list" class="mt-4 space-y-3"></div>
            </section>
        </div>

        <div class="mt-5 hidden space-y-4" data-analytics-panel="reports">
            <section class="analytics-panel">
                <div class="analytics-panel-header"><div><p class="analytics-panel-kicker">Exports</p><h3 class="analytics-panel-title">Queued reports</h3></div></div>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                    <select id="analytics-export-type" class="admin-auth-input min-h-11 text-xs"><option value="summary">Summary events</option><option value="users">Users</option><option value="retention">Retention</option><option value="leaderboard">Leaderboard</option><option value="geography">Geography</option><option value="journeys">Journeys</option><option value="alerts">Alerts</option><option value="complete">Complete</option></select>
                    <select id="analytics-export-format" class="admin-auth-input min-h-11 text-xs"><option value="csv">CSV</option><option value="xlsx">Excel (CSV fallback)</option><option value="pdf">PDF (text)</option></select>
                    <button type="button" id="analytics-export-queue" class="admin-primary-button min-h-11 text-xs">Queue export</button>
                </div>
                <div id="analytics-exports-history" class="mt-4"></div>
            </section>
        </div>

        <div class="analytics-loading-overlay hidden" data-analytics-loading>
            <div class="analytics-loading-card">
                <i class="fas fa-circle-notch fa-spin text-[#055498]" aria-hidden="true"></i>
                <p>Updating analytics…</p>
            </div>
        </div>
    </section>
@endsection
