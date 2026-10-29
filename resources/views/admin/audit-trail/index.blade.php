@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Category</p>
                <div
                    id="audit-category-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Filter by category"
                ></div>

                <p class="admin-filter-rail-title">Action</p>
                <div
                    id="audit-action-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Filter by action"
                ></div>

                <p class="admin-filter-rail-title">Date range</p>
                <div class="audit-date-filters space-y-2">
                    <label class="block text-xs font-semibold text-slate-500" for="audit-from">From</label>
                    <input
                        id="audit-from"
                        type="date"
                        class="admin-swal-input w-full"
                        autocomplete="off"
                    >
                    <label class="block text-xs font-semibold text-slate-500" for="audit-to">To</label>
                    <input
                        id="audit-to"
                        type="date"
                        class="admin-swal-input w-full"
                        autocomplete="off"
                    >
                    <button
                        type="button"
                        id="audit-clear-dates"
                        class="admin-secondary-button w-full min-h-11 justify-center text-sm"
                    >
                        Clear dates
                    </button>
                </div>
            </aside>

            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-clock-rotate-left"></i>
                        </span>
                        <div class="flex min-w-0 flex-col sm:flex-row sm:items-baseline sm:gap-3">
                            <h2 class="admin-shell-title text-base sm:text-lg">Audit Trail</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">
                                Super Admin log of admin mutations and security events.
                            </p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered xl:self-center">
                        <button type="button" class="admin-secondary-button xl:w-auto" data-admin-action="export-audit-trail">
                            <i class="fas fa-file-csv mr-2" aria-hidden="true"></i>
                            Export CSV
                        </button>
                    </div>
                </div>

                <div
                    id="audit-metrics"
                    class="audit-metrics-strip mt-5 grid grid-cols-2 gap-3 xl:grid-cols-4"
                    aria-label="Audit trail metrics"
                >
                    <div class="admin-stat-card">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Today</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-audit-metric="today">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-sun"></i></span>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">This week</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-audit-metric="this_week">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-calendar-week"></i></span>
                        </div>
                    </div>
                    <div class="admin-stat-card">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Unique actors</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-audit-metric="unique_actors">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-user-check"></i></span>
                        </div>
                    </div>
                    <div class="admin-stat-card col-span-2 xl:col-span-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Mutations</p>
                                <p class="mt-2 text-2xl font-bold text-[#055498]" data-audit-metric="mutations">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-bolt"></i></span>
                        </div>
                    </div>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="audit-trail-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
