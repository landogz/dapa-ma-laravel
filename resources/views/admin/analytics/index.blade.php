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
                <div class="flex min-w-0 flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span class="font-semibold uppercase tracking-[0.12em]">Range</span>
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

        <div class="analytics-insight-strip mt-5" data-analytics-insights></div>

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

        <div class="analytics-loading-overlay hidden" data-analytics-loading>
            <div class="analytics-loading-card">
                <i class="fas fa-circle-notch fa-spin text-[#055498]" aria-hidden="true"></i>
                <p>Updating analytics…</p>
            </div>
        </div>
    </section>
@endsection
