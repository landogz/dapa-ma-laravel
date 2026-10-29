@extends('admin.layout')

@section('content')
    <section class="admin-shell-card p-4 sm:p-6">
        <div class="admin-filter-split mt-0">
            <aside class="admin-filter-rail">
                <p class="admin-filter-rail-title">Account type</p>
                <div
                    id="users-audience-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Account type"
                ></div>

                <p class="admin-filter-rail-title" data-users-role-title>Roles</p>
                <div
                    id="users-role-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Filter by role"
                ></div>

                <p class="admin-filter-rail-title">Status</p>
                <div
                    id="users-status-tabs"
                    class="admin-filter-tabs"
                    role="tablist"
                    aria-label="Filter by status"
                ></div>
            </aside>

            <div class="admin-filter-main">
                <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                    <div class="admin-section-header admin-section-header-accent flex-1 items-center gap-3 sm:gap-4">
                        <span class="admin-icon-badge">
                            <i class="fas fa-user-shield"></i>
                        </span>
                        <div class="flex min-w-0 flex-col sm:flex-row sm:items-baseline sm:gap-3">
                            <h2 class="admin-shell-title text-base sm:text-lg">User administration</h2>
                            <p class="mt-0.5 text-xs sm:mt-0 sm:text-sm admin-shell-subtitle">Manage staff access and mobile app accounts.</p>
                        </div>
                    </div>
                    <div class="admin-page-actions admin-page-actions-centered xl:self-center">
                        <button type="button" class="admin-primary-button xl:w-auto" data-admin-action="create-user">
                            Add User
                        </button>
                    </div>
                </div>

                <div
                    id="users-metrics"
                    class="users-metrics-strip mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5"
                    aria-label="User account metrics"
                >
                    <button type="button" class="admin-stat-card users-metric-card text-left" data-users-metric="staff" aria-pressed="false">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Staff accounts</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-users-metric-value="staff">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-user-tie"></i></span>
                        </div>
                    </button>
                    <button type="button" class="admin-stat-card users-metric-card text-left" data-users-metric="app" aria-pressed="false">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">App users</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-users-metric-value="app">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-mobile-screen-button"></i></span>
                        </div>
                    </button>
                    <button type="button" class="admin-stat-card users-metric-card text-left" data-users-metric="never" aria-pressed="false">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Never logged in</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-users-metric-value="never_logged_in">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-clock"></i></span>
                        </div>
                    </button>
                    <button type="button" class="admin-stat-card users-metric-card text-left" data-users-metric="password_due" aria-pressed="false">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Must change password</p>
                                <p class="mt-2 text-2xl font-bold text-[#123a60]" data-users-metric-value="must_change_password">—</p>
                            </div>
                            <span class="admin-stat-card-icon" aria-hidden="true"><i class="fas fa-key"></i></span>
                        </div>
                    </button>
                    <button type="button" class="admin-stat-card users-metric-card text-left col-span-2 sm:col-span-1" data-users-metric="suspended" aria-pressed="false">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="admin-stat-card-label">Suspended</p>
                                <p class="mt-2 text-2xl font-bold text-[#CE2028]" data-users-metric-value="suspended">—</p>
                            </div>
                            <span class="admin-stat-card-icon users-metric-icon-danger" aria-hidden="true"><i class="fas fa-ban"></i></span>
                        </div>
                    </button>
                </div>

                <div class="admin-table-shell mt-6">
                    <table id="users-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
