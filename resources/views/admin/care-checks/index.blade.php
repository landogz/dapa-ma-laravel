@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Period</p>
                <div class="admin-filter-bar-field">
                    <label class="sr-only" for="care-checks-range-select">Period</label>
                    <select id="care-checks-range-select" class="admin-filter-select min-h-11 w-full" aria-label="Care checks date range">
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
                <div id="care-checks-custom-range" class="mt-3 hidden space-y-3">
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="care-checks-range-from">From</label>
                        <input id="care-checks-range-from" type="date" class="admin-filter-select min-h-11 w-full" aria-label="Start date">
                    </div>
                    <div class="admin-filter-bar-field">
                        <label class="admin-filter-bar-label" for="care-checks-range-to">To</label>
                        <input id="care-checks-range-to" type="date" class="admin-filter-select min-h-11 w-full" aria-label="End date">
                    </div>
                    <button type="button" id="care-checks-apply-range" class="admin-secondary-button min-h-11 w-full">Apply range</button>
                </div>

                <p class="admin-filter-rail-title mt-5">Checks</p>
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
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-clipboard-check"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="admin-shell-title text-base sm:text-lg">Care Checks</h2>
                            <p class="mt-0.5 text-xs sm:text-sm admin-shell-subtitle">
                                Stress, Anxiety, and Sleep Quality results from the mobile Self-care Toolkit.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered flex w-full flex-col gap-2 sm:flex-row sm:items-center xl:w-auto xl:self-center">
                        <button type="button" class="admin-primary-button w-full sm:w-auto min-h-11 gap-2" data-admin-action="export-care-checks" aria-label="Export care checks">
                            <i class="fas fa-file-arrow-down" aria-hidden="true"></i>
                            <span>Export</span>
                        </button>
                    </div>
                </div>

                <p class="mt-4 text-xs leading-5 text-slate-500">
                    <i class="fas fa-circle-info mr-1 text-[#055498]" aria-hidden="true"></i>
                    Self-reported screening for reflection only. Not a clinical diagnosis.
                </p>

                <div class="admin-table-shell mt-6">
                    <table id="care-checks-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
