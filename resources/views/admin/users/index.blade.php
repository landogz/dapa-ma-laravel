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

                <div class="admin-table-shell mt-6">
                    <table id="users-table" class="min-w-full text-left text-sm text-slate-700"></table>
                </div>
            </div>
        </div>
    </section>
@endsection
