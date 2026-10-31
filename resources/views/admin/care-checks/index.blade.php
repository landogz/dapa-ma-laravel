@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-insights-header">
            <div class="admin-section-header admin-section-header-accent admin-insights-header-title min-w-0 items-center gap-3 sm:gap-4">
                <span class="admin-icon-badge">
                    <i class="fas fa-clipboard-check"></i>
                </span>
                <div class="min-w-0">
                    <h2 class="admin-shell-title text-base sm:text-lg">Care Checks</h2>
                    <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                        Stress, Anxiety, and Sleep Quality results from the mobile Self-care Toolkit.
                    </p>
                    <p class="mt-1 text-[11px] text-slate-400" data-care-checks-updated-at></p>
                </div>
            </div>

            <div class="admin-filter-bar" data-admin-filter-bar>
                <div class="admin-filter-bar-main">
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="care-checks-range-select">Period</label>
                        <select id="care-checks-range-select" class="admin-filter-bar-select" aria-label="Care checks date range">
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
                        <button type="button" class="admin-filter-bar-preset" data-care-checks-range="today">Today</button>
                        <button type="button" class="admin-filter-bar-preset is-active" data-care-checks-range="7">7 days</button>
                        <button type="button" class="admin-filter-bar-preset" data-care-checks-range="30">30 days</button>
                    </div>

                    <div class="admin-filter-bar-actions">
                        <button type="button" class="admin-filter-bar-btn is-primary" data-admin-action="export-care-checks" aria-label="Export care checks">
                            <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>

                <div id="care-checks-custom-range" class="admin-filter-bar-custom hidden">
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="care-checks-range-from">From</label>
                        <input id="care-checks-range-from" type="date" class="admin-filter-bar-input" aria-label="Start date">
                    </div>
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="care-checks-range-to">To</label>
                        <input id="care-checks-range-to" type="date" class="admin-filter-bar-input" aria-label="End date">
                    </div>
                    <button type="button" id="care-checks-apply-range" class="admin-filter-bar-btn is-secondary">Apply range</button>
                </div>
            </div>
        </div>
        <p class="mt-3 text-xs leading-5 text-slate-500">
            <i class="fas fa-circle-info mr-1 text-[#055498]" aria-hidden="true"></i>
            Self-reported screening for reflection only. Not a clinical diagnosis.
        </p>

        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="admin-stat-card admin-dashboard-summary analytics-metric-card text-left">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-[0.16em] text-[#055498]">Completions</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Total checks</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900" data-care-checks-summary="total">--</p>
                        <p class="admin-trend" data-care-checks-trend="total">–</p>
                        <div data-care-checks-sparkline="total" class="analytics-metric-sparkline mt-3" aria-hidden="true"></div>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-clipboard-check"></i>
                    </span>
                </div>
            </div>
            <div class="admin-stat-card admin-dashboard-summary analytics-metric-card text-left">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-[0.16em] text-[#123a60]">People</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Unique users</p>
                        <p class="mt-2 text-2xl font-bold text-slate-900" data-care-checks-summary="unique">--</p>
                        <p class="admin-trend" data-care-checks-trend="unique">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-users"></i>
                    </span>
                </div>
            </div>
            <div class="admin-stat-card admin-dashboard-summary analytics-metric-card text-left">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-[0.16em] text-[#055498]">Lower tier</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Lower-tier results</p>
                        <p class="mt-2 text-2xl font-bold text-[#055498]" data-care-checks-summary="low">--</p>
                        <p class="admin-trend" data-care-checks-trend="low">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-arrow-trend-down"></i>
                    </span>
                </div>
            </div>
            <div class="admin-stat-card admin-dashboard-summary analytics-metric-card text-left">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs uppercase tracking-[0.16em] text-[#CE2028]">Higher tier</p>
                        <p class="mt-3 text-base font-semibold text-slate-900">Higher-tier results</p>
                        <p class="mt-2 text-2xl font-bold text-[#CE2028]" data-care-checks-summary="high">--</p>
                        <p class="admin-trend" data-care-checks-trend="high">–</p>
                    </div>
                    <span class="admin-icon-badge">
                        <i class="fas fa-arrow-trend-up"></i>
                    </span>
                </div>
            </div>
        </div>

        <div class="mt-6 grid min-w-0 gap-4 lg:grid-cols-[0.9fr_1.1fr] lg:items-stretch">
            <div class="min-w-0 rounded-3xl border border-slate-200 p-4 sm:p-5">
                <p class="text-sm font-semibold text-slate-900">Check mix</p>
                <div class="mt-4 space-y-3">
                    <article class="care-check-type-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Stress</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900" data-care-checks-type="stress">--</p>
                                <p class="mt-1 text-xs text-slate-500" data-care-checks-type-avg="stress"></p>
                            </div>
                            <span class="care-check-type-chip is-stress">Stress</span>
                        </div>
                        <div class="care-check-type-bar" aria-hidden="true">
                            <span data-care-checks-type-bar="stress"></span>
                        </div>
                    </article>
                    <article class="care-check-type-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Anxiety</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900" data-care-checks-type="anxiety">--</p>
                                <p class="mt-1 text-xs text-slate-500" data-care-checks-type-avg="anxiety"></p>
                            </div>
                            <span class="care-check-type-chip is-anxiety">Anxiety</span>
                        </div>
                        <div class="care-check-type-bar" aria-hidden="true">
                            <span data-care-checks-type-bar="anxiety"></span>
                        </div>
                    </article>
                    <article class="care-check-type-card">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-slate-800">Sleep</p>
                                <p class="mt-1 text-2xl font-bold text-slate-900" data-care-checks-type="sleep">--</p>
                                <p class="mt-1 text-xs text-slate-500" data-care-checks-type-avg="sleep"></p>
                            </div>
                            <span class="care-check-type-chip is-sleep">Sleep</span>
                        </div>
                        <div class="care-check-type-bar" aria-hidden="true">
                            <span data-care-checks-type-bar="sleep"></span>
                        </div>
                    </article>
                </div>
            </div>
            <div class="min-w-0 rounded-3xl border border-slate-200 p-4 sm:p-5">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm font-semibold text-slate-900">Daily completions</p>
                    <div class="care-check-chart-legend" aria-hidden="true">
                        <span class="is-stress">Stress</span>
                        <span class="is-anxiety">Anxiety</span>
                        <span class="is-sleep">Sleep</span>
                    </div>
                </div>
                <div class="mt-6" data-care-checks-daily></div>
            </div>
        </div>

        <div class="admin-filter-split mt-8 min-w-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Checks</p>
                <div id="care-checks-type-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by check">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All checks</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="stress" data-icon="fas fa-bolt">Stress Check</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="anxiety" data-icon="fas fa-heart-pulse">Anxiety Check</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="sleep" data-icon="fas fa-moon">Sleep Quality</button>
                </div>
                <p class="admin-filter-rail-title mt-5">Result</p>
                <div id="care-checks-tier-tabs" class="admin-filter-tabs" role="tablist" aria-label="Filter by result tier">
                    <button type="button" class="admin-filter-tab is-active" role="tab" aria-selected="true" data-filter-value="" data-icon="fas fa-layer-group">All results</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="low" data-icon="fas fa-arrow-trend-down">Lower tier</button>
                    <button type="button" class="admin-filter-tab" role="tab" aria-selected="false" data-filter-value="high" data-icon="fas fa-arrow-trend-up">Higher tier</button>
                </div>
            </aside>
            <div class="admin-filter-main min-w-0">
                <div class="admin-table-shell mt-0">
                    <table id="care-checks-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
