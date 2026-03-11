@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-chart-pie"></i>
                </span>
                <div class="flex flex-col sm:flex-row sm:items-baseline sm:gap-3">
                    <h2 class="admin-shell-title text-base sm:text-lg">Analytics dashboard</h2>
                    <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Review totals, trends, and exports.</p>
                </div>
            </div>
            <div class="admin-page-actions admin-page-actions-centered">
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <span>Range:</span>
                    <div class="inline-flex overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-[11px]">
                        <button type="button" class="px-3 py-1 text-slate-600 is-active" data-analytics-range="7">7 days</button>
                        <button type="button" class="px-3 py-1 text-slate-500" data-analytics-range="30">30 days</button>
                    </div>
                </div>
                <button type="button" class="admin-primary-button bg-amber-500 hover:bg-amber-600" data-admin-action="export-analytics">
                    Export CSV
                </button>
            </div>
        </div>

        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="admin-stat-card admin-dashboard-summary text-left">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-[#055498]">Views</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Total Views</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900" data-analytics-summary="views">--</p>
                        <p class="admin-trend" data-analytics-trend="views">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-eye"></i>
                    </span>
                </div>
            </div>
            <div class="admin-stat-card admin-dashboard-summary text-left">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-emerald-600">Bookmarks</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Saved Content</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900" data-analytics-summary="bookmarks">--</p>
                        <p class="admin-trend" data-analytics-trend="bookmarks">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-bookmark"></i>
                    </span>
                </div>
            </div>
            <div class="admin-stat-card admin-dashboard-summary text-left">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-sky-600">Searches</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Total Searches</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900" data-analytics-summary="searches">--</p>
                        <p class="admin-trend" data-analytics-trend="searches">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-magnifying-glass"></i>
                    </span>
                </div>
            </div>
            <div class="admin-stat-card admin-dashboard-summary text-left">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.16em] text-amber-600">Shares</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Total Shares</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900" data-analytics-summary="shares">--</p>
                        <p class="admin-trend" data-analytics-trend="shares">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-share-nodes"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:gap-6 xl:grid-cols-[0.9fr_1.1fr]">
            <div class="space-y-4">
                <div class="rounded-3xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-sm font-semibold text-slate-900">Events by type</p>
                    <div id="analytics-event-summary" class="mt-4 space-y-3"></div>
                </div>
                <div class="rounded-3xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-sm font-semibold text-slate-900">Top viewed posts</p>
                    <div id="analytics-top-posts" class="mt-4"></div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="rounded-3xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-sm font-semibold text-slate-900">Daily activity trend</p>
                    <div id="analytics-daily-chart" class="mt-6"></div>
                </div>
                <div class="rounded-3xl border border-slate-200 p-4 sm:p-5">
                    <p class="text-sm font-semibold text-slate-900">Device breakdown</p>
                    <div class="mt-4 space-y-2 text-xs text-slate-600">
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                Android
                            </span>
                            <span data-analytics-device="android">--</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span class="inline-flex h-2.5 w-2.5 rounded-full bg-sky-500"></span>
                                iOS
                            </span>
                            <span data-analytics-device="ios">--</span>
                        </div>
                    </div>
                    <p class="mt-3 text-[11px] text-slate-400" data-analytics-device-empty>
                        Device usage data will appear here once analytics events include platform information.
                    </p>
                </div>
            </div>
        </div>
    </section>
@endsection
